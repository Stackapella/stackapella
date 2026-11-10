"""Drives headless Chrome over the DevTools protocol, with nothing but the Python standard library.

    python3 -I tool/cdp.py shot <url> <out.png> [--size 1440x900] [--mobile] [--wait <js>] [--profile <dir>]
    python3 -I tool/cdp.py eval <url> <js> [--wait <js>] [--timeout <seconds>] [--profile <dir>]

Chrome runs muted. `--wait` is a JS expression polled until true (up to `--timeout` seconds, 30 by default). Console errors and
uncaught exceptions are printed, and make the command exit 1.
"""
import argparse
import atexit
import base64
import hashlib
import json
import os
import socket
import struct
import subprocess
import sys
import shutil
import signal
import tempfile
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import cdn_cache  # noqa: E402

CHROME = os.environ.get("CHROME", "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome")
# Chrome plays into its own silent output, whose clock runs on Chrome's timer, so an AudioContext keeps
# time even when the Mac's audio daemon (coreaudiod) stalls, which hung every run. STK_AUDIO_DEVICE=1 uses
# the real output device instead, for a check that needs the hardware's own latency.
AUDIO_FLAGS = [] if os.environ.get("STK_AUDIO_DEVICE") == "1" else ["--disable-audio-output"]
CLOSE_SECONDS = 5  # a polite close, then forced
# A single DevTools call answered within this, or the flow fails naming it (a page script that never finishes, such as
# a wait on a stalled audio clock, would otherwise hold a landing for hours).
CALL_SECONDS = float(os.environ.get("STK_CDP_CALL_SECONDS", "300"))
# How long Chrome may take to open its DevTools port, and how many times it's launched before giving up:
# a CI runner can be slow to start it (2026-11-03: three release runs failed at the first launch and passed
# on a rerun), so each try waits a while and a failed one starts again with a fresh profile.
START_SECONDS = float(os.environ.get("STK_CHROME_START_SECONDS", "30"))
START_TRIES = int(os.environ.get("STK_CHROME_START_TRIES", "3"))


def _exit_on_term(signum, frame):
    # SIGTERM (an outer timeout, a killed chain) ends Python the normal way, so every browser closes.
    raise SystemExit(128 + signum)


if signal.getsignal(signal.SIGTERM) is signal.SIG_DFL:
    signal.signal(signal.SIGTERM, _exit_on_term)


class WebSocket:
    def __init__(self, url):
        host_port, path = url[len("ws://"):].split("/", 1)
        host, port = host_port.split(":")
        self.sock = socket.create_connection((host, int(port)))
        key = base64.b64encode(os.urandom(16)).decode()
        self.sock.sendall(
            f"GET /{path} HTTP/1.1\r\nHost: {host_port}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            f"Sec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n".encode()
        )
        response = b""
        while b"\r\n\r\n" not in response:
            response += self.sock.recv(4096)
        head, self.buffer = response.split(b"\r\n\r\n", 1)
        accept = base64.b64encode(hashlib.sha1((key + "258EAFA5-E914-47DA-95CA-C5AB0DC85B11").encode()).digest())
        if b" 101 " not in head.split(b"\r\n")[0] or accept not in head:
            raise RuntimeError(f"WebSocket handshake failed: {head!r}")
        self.sock.settimeout(CALL_SECONDS)  # a read that waits longer is a call that never comes back (Chrome.send)

    def send(self, text):
        data = text.encode()
        mask = os.urandom(4)
        header = bytearray([0x81])
        if len(data) < 126:
            header.append(0x80 | len(data))
        elif len(data) < 65536:
            header += bytes([0x80 | 126]) + struct.pack(">H", len(data))
        else:
            header += bytes([0x80 | 127]) + struct.pack(">Q", len(data))
        self.sock.sendall(bytes(header) + mask + bytes(b ^ mask[i % 4] for i, b in enumerate(data)))

    def _read(self, n):
        while len(self.buffer) < n:
            chunk = self.sock.recv(1 << 16)
            if not chunk:
                raise RuntimeError("WebSocket closed")
            self.buffer += chunk
        data, self.buffer = self.buffer[:n], self.buffer[n:]
        return data

    def receive(self):
        message = b""
        while True:
            first, second = self._read(2)
            length = second & 0x7F
            if length == 126:
                length = struct.unpack(">H", self._read(2))[0]
            elif length == 127:
                length = struct.unpack(">Q", self._read(8))[0]
            payload = self._read(length)
            opcode = first & 0x0F
            if opcode in (0x1, 0x0):
                message += payload
                if first & 0x80:
                    return message.decode()
            elif opcode == 0x8:
                raise RuntimeError("WebSocket closed by Chrome")


class ChromeDidNotStart(RuntimeError):
    """Chrome never opened its DevTools port (or it gave no page): tried again, then reported with Chrome's stderr."""


class Chrome:
    def __init__(self, profile=None, width=1440, height=900, mobile=False):
        self.given_profile = profile
        self.closed = True
        atexit.register(self.close)  # closed when Python ends normally or by SIGTERM
        for attempt in range(1, START_TRIES + 1):
            port_file = self._launch()
            try:
                self._connect(port_file, width, height, mobile)
                return
            except BaseException as error:
                said = self._stderr_tail()
                self.close()  # a Chrome that never came up is still closed
                if attempt == START_TRIES or not isinstance(error, ChromeDidNotStart):
                    if isinstance(error, ChromeDidNotStart):
                        raise ChromeDidNotStart(f"{error} after {START_TRIES} tries; Chrome's last words:\n{said or '(nothing)'}") from None
                    raise
                print(f"Chrome didn't start (try {attempt} of {START_TRIES}): {error}; trying again with a fresh profile. Chrome said:\n{said or '(nothing)'}", file=sys.stderr)

    def _launch(self):
        """Starts Chrome on a fresh profile (unless one was given), its stderr kept to show if it fails. Returns its port file."""
        self.own_profile = self.given_profile is None  # a profile made here goes when Chrome closes
        self.profile = self.given_profile or tempfile.mkdtemp(prefix="stk-chrome-")
        if self.own_profile:
            # And when the run ends any other way (a failure, Ctrl-C), so profiles never pile up.
            atexit.register(shutil.rmtree, self.profile, True)
        Path(self.profile).mkdir(parents=True, exist_ok=True)
        port_file = Path(self.profile) / "DevToolsActivePort"
        port_file.unlink(missing_ok=True)
        # Chrome's stderr, outside the profile (which goes when Chrome closes), read only when a launch fails.
        self.stderr = tempfile.NamedTemporaryFile(prefix="stk-chrome-stderr-", suffix=".log", delete=False)
        atexit.register(lambda path=self.stderr.name: Path(path).unlink(missing_ok=True))
        # Its own process group, so closing takes its helpers too (renderer, GPU, audio), and never
        # leaves a headless Chrome holding the audio for the next run (2026-10-30: test.sh hung on them).
        self.process = subprocess.Popen(
            [CHROME, "--headless=new", "--mute-audio", f"--user-data-dir={self.profile}", "--no-first-run",
             "--remote-debugging-port=0", "--autoplay-policy=no-user-gesture-required",
             "--use-fake-ui-for-media-stream", "--use-fake-device-for-media-stream",
             "--host-resolver-rules=MAP *.stackapella.test 127.0.0.1",  # two hosts of one domain, for the flows that cross them
             # Chrome drops history.pushState past a rate (IPC flooding protection, ~100 in 10 s) with no error:
             # a flow that opens a lesson every few tenths of a second (course_choir's four parts, about 120
             # opens on one page) would find a navigation silently ignored. No person taps that fast.
             "--disable-ipc-flooding-protection",
             *AUDIO_FLAGS, "about:blank"],
            stdout=subprocess.DEVNULL, stderr=self.stderr, start_new_session=True,
        )
        self.closed = False
        # A watchdog for when Python is killed outright (SIGKILL runs nothing): it waits for this
        # process, then ends the group.
        self.watchdog = subprocess.Popen(
            ["/bin/sh", "-c", f"while kill -0 {os.getpid()} 2>/dev/null; do sleep 2; done; kill -9 -{self.process.pid} 2>/dev/null"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
        )
        return port_file

    def _stderr_tail(self, lines=40):
        """The last of what Chrome wrote to stderr (why it didn't start), or ''."""
        try:
            self.stderr.flush()
            return "\n".join(Path(self.stderr.name).read_text(errors="replace").strip().splitlines()[-lines:])
        except (OSError, AttributeError):
            return ""

    def _connect(self, port_file, width, height, mobile):
        deadline = time.time() + START_SECONDS
        while not (port_file.exists() and port_file.read_text().strip()):
            if self.process.poll() is not None:
                raise ChromeDidNotStart(f"Chrome exited with code {self.process.returncode} before opening its DevTools port")
            if time.time() > deadline:
                raise ChromeDidNotStart(f"Chrome didn't start (no DevTools port after {START_SECONDS:g} seconds)")
            time.sleep(0.1)
        port = port_file.read_text().split()[0]
        # The port can be written a moment before it answers, and the first page a moment after.
        while True:
            try:
                targets = json.load(urllib.request.urlopen(f"http://127.0.0.1:{port}/json", timeout=5))
                page = next(t for t in targets if t["type"] == "page")
                break
            except (OSError, StopIteration, ValueError) as error:
                if time.time() > deadline:
                    raise ChromeDidNotStart(f"Chrome's DevTools port {port} gave no page ({error})") from None
                time.sleep(0.2)
        self.ws = WebSocket(page["webSocketDebuggerUrl"])
        self.next_id = 0
        self.replies = {}  # replies to calls made while another call waited (a paused request answered meanwhile)
        self.errors = []
        self.warnings = []
        # The pinned CDN libraries from this machine's cache (cdn_cache.py): requests to them pause here and are
        # answered with the checked file, every other request goes on as it would.
        self.cdn = cdn_cache.pinned() if cdn_cache.ON else {}
        if self.cdn:
            self.send("Fetch.enable", {"patterns": [{"urlPattern": "https://cdn.jsdelivr.net/*", "requestStage": "Request"}]})
        # Downloads (a share card's desktop fallback, an export) go to a folder of this run's own, removed with it,
        # never the Mac's ~/Downloads (2026-11-08: flows left 22 share cards there). Nothing reads them back.
        self.downloads = tempfile.mkdtemp(prefix="stk-downloads-")
        atexit.register(shutil.rmtree, self.downloads, True)
        try:
            self.send("Browser.setDownloadBehavior", {"behavior": "allow", "downloadPath": self.downloads})
        except Exception:  # a page target that won't take the browser's command: the page's own (deprecated) one
            self.send("Page.setDownloadBehavior", {"behavior": "allow", "downloadPath": self.downloads})
        self.send("Runtime.enable")
        self.send("Page.enable")
        self.send("Log.enable")
        self.send("Emulation.setDeviceMetricsOverride",
                  {"width": width, "height": height, "deviceScaleFactor": 2 if mobile else 1, "mobile": mobile})
        if mobile:
            self.send("Emulation.setTouchEmulationEnabled", {"enabled": True})

    def send(self, method, params=None):
        self.next_id += 1
        my_id = self.next_id
        self.ws.send(json.dumps({"id": my_id, "method": method, "params": params or {}}))
        deadline = time.time() + CALL_SECONDS
        while True:
            if time.time() > deadline:
                raise TimeoutError(f"{method}: no answer in {CALL_SECONDS:.0f} s (a page script that never finishes?)")
            try:
                message = self.replies.pop(my_id, None) or json.loads(self.ws.receive())
            except TimeoutError as error:
                raise TimeoutError(f"{method}: no answer in {CALL_SECONDS:.0f} s (a page script that never finishes?)") from error
            if message.get("id") == my_id:
                if "error" in message:
                    raise RuntimeError(f"{method}: {message['error']}")
                return message.get("result", {})
            if "id" in message:
                self.replies[message["id"]] = message  # an outer call's reply, read by a call made inside an event
            else:
                self.on_event(message)

    def on_event(self, message):
        method = message.get("method")
        params = message.get("params", {})
        if method == "Fetch.requestPaused":
            self.answer_cdn(params)
            return
        if method == "Runtime.exceptionThrown":
            details = params["exceptionDetails"]
            self.errors.append(details.get("exception", {}).get("description") or details.get("text"))
        elif method == "Runtime.consoleAPICalled" and params.get("type") == "error":
            self.errors.append(" ".join(str(a.get("value", a.get("description", ""))) for a in params["args"]))
        elif method == "Log.entryAdded" and params["entry"]["level"] == "error":
            self.errors.append(f"{params['entry']['text']} {params['entry'].get('url', '')}")
        # The browser's own warnings (a preload not used, a deprecation) and the page's console.warn.
        if method == "Log.entryAdded" and params["entry"]["level"] == "warning":
            self.warnings.append(f"{params['entry']['text']} {params['entry'].get('url', '')}")
        elif method == "Runtime.consoleAPICalled" and params.get("type") == "warning":
            self.warnings.append(" ".join(str(a.get("value", a.get("description", ""))) for a in params["args"]))

    def answer_cdn(self, params):
        """A paused request to the CDN: a pinned file from the cache, anything else on to the network."""
        url = params["request"]["url"]
        body = cdn_cache.body_for(url, self.cdn[url]) if url in self.cdn else None
        if body is None:
            self.send("Fetch.continueRequest", {"requestId": params["requestId"]})
            return
        self.send("Fetch.fulfillRequest", {"requestId": params["requestId"], "responseCode": 200, "body": base64.b64encode(body).decode(), "responseHeaders": [
            {"name": "Content-Type", "value": "application/javascript; charset=utf-8"},
            {"name": "Access-Control-Allow-Origin", "value": "*"},
            {"name": "Cache-Control", "value": "public, max-age=31536000, immutable"},
        ]})

    def evaluate(self, expression, await_promise=True):
        result = self.send("Runtime.evaluate", {"expression": expression, "awaitPromise": await_promise, "returnByValue": True})
        if "exceptionDetails" in result:
            raise RuntimeError(result["exceptionDetails"].get("exception", {}).get("description", result["exceptionDetails"]))
        return result["result"].get("value")

    def goto(self, url, wait=None, timeout=30):
        self.send("Page.navigate", {"url": url})
        deadline = time.time() + timeout
        condition = wait or "document.readyState === 'complete'"
        while time.time() < deadline:
            try:
                if self.evaluate(f"!!({condition})", await_promise=False):
                    return
            except RuntimeError:
                pass
            time.sleep(0.2)
        # What the page was doing, when it says (the web tests' harness puts the test running in the title).
        try:
            title = self.evaluate("document.title", await_promise=False)
        except RuntimeError:
            title = None
        raise TimeoutError(f"Timed out waiting for: {condition}" + (f" (the page's title: {title})" if title else ""))

    def screenshot(self, path):
        data = self.send("Page.captureScreenshot", {"format": "png"})["data"]
        Path(path).write_bytes(base64.b64decode(data))

    def close(self):
        """Ends Chrome and its helpers: politely, then by force after CLOSE_SECONDS. Safe to call twice."""
        if getattr(self, "closed", True):
            return
        self.closed = True
        for sig, wait in ((signal.SIGTERM, CLOSE_SECONDS), (signal.SIGKILL, CLOSE_SECONDS)):
            try:
                os.killpg(self.process.pid, sig)
            except ProcessLookupError:
                break  # the whole group has gone
            except PermissionError:
                # A sandbox that won't signal a group (the landing agent's): the browser alone, as before.
                try:
                    self.process.send_signal(sig)
                except ProcessLookupError:
                    break
            try:
                self.process.wait(timeout=wait)
            except subprocess.TimeoutExpired:
                continue
            try:
                os.killpg(self.process.pid, 0)  # helpers still there after the browser went?
            except (ProcessLookupError, PermissionError):
                break  # gone, or not ours to check
        if getattr(self, "watchdog", None) and self.watchdog.poll() is None:
            self.watchdog.kill()
        if getattr(self, "stderr", None):
            self.stderr.close()
            Path(self.stderr.name).unlink(missing_ok=True)
        if self.own_profile:
            shutil.rmtree(self.profile, ignore_errors=True)
        if getattr(self, "downloads", None):
            shutil.rmtree(self.downloads, ignore_errors=True)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["shot", "eval"])
    parser.add_argument("url")
    parser.add_argument("arg")
    parser.add_argument("--size", default="1440x900")
    parser.add_argument("--mobile", action="store_true")
    parser.add_argument("--wait")
    parser.add_argument("--profile")
    parser.add_argument("--timeout", type=float, default=30)
    parser.add_argument("--expect", action="append", default=[], help="a page error the run means to cause (a substring), not counted")
    args = parser.parse_args()
    width, height = (int(v) for v in args.size.split("x"))
    chrome = Chrome(args.profile, width, height, args.mobile)
    try:
        chrome.goto(args.url, args.wait, args.timeout)
        time.sleep(0.4)  # let canvases and fonts settle
        if args.command == "shot":
            chrome.screenshot(args.arg)
        else:
            print(json.dumps(chrome.evaluate(args.arg), indent=2))
    finally:
        chrome.close()
    errors = [e for e in chrome.errors if not any(x in e for x in args.expect)]
    for error in errors:
        print(f"page error: {error}", file=sys.stderr)
    sys.exit(1 if errors else 0)


if __name__ == "__main__":
    main()
