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

CHROME = os.environ.get("CHROME", "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome")
CLOSE_SECONDS = 5  # a polite close, then forced


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


class Chrome:
    def __init__(self, profile=None, width=1440, height=900, mobile=False):
        self.own_profile = profile is None  # a profile made here goes when Chrome closes
        self.profile = profile or tempfile.mkdtemp(prefix="stk-chrome-")
        if self.own_profile:
            # And when the run ends any other way (a failure, Ctrl-C), so profiles never pile up.
            atexit.register(shutil.rmtree, self.profile, True)
        Path(self.profile).mkdir(parents=True, exist_ok=True)
        port_file = Path(self.profile) / "DevToolsActivePort"
        port_file.unlink(missing_ok=True)
        # Its own process group, so closing takes its helpers too (renderer, GPU, audio), and never
        # leaves a headless Chrome holding the audio for the next run (2026-10-30: test.sh hung on them).
        self.process = subprocess.Popen(
            [CHROME, "--headless=new", "--mute-audio", f"--user-data-dir={self.profile}", "--no-first-run",
             "--remote-debugging-port=0", "--autoplay-policy=no-user-gesture-required",
             "--use-fake-ui-for-media-stream", "--use-fake-device-for-media-stream",
             "--host-resolver-rules=MAP *.stackapella.test 127.0.0.1",  # two hosts of one domain, for the flows that cross them
             "about:blank"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
        )
        self.closed = False
        # Closed when Python ends normally or by SIGTERM (atexit), and by a watchdog if Python is
        # killed outright (SIGKILL runs nothing): it waits for this process, then ends the group.
        atexit.register(self.close)
        self.watchdog = subprocess.Popen(
            ["/bin/sh", "-c", f"while kill -0 {os.getpid()} 2>/dev/null; do sleep 2; done; kill -9 -{self.process.pid} 2>/dev/null"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
        )
        try:
            self._connect(port_file, width, height, mobile)
        except BaseException:
            self.close()  # a Chrome that never came up is still closed
            raise

    def _connect(self, port_file, width, height, mobile):
        for _ in range(100):
            if port_file.exists() and port_file.read_text().strip():
                break
            time.sleep(0.1)
        else:
            raise RuntimeError("Chrome didn't start (no DevTools port)")
        port = port_file.read_text().split()[0]
        targets = json.load(urllib.request.urlopen(f"http://127.0.0.1:{port}/json"))
        page = next(t for t in targets if t["type"] == "page")
        self.ws = WebSocket(page["webSocketDebuggerUrl"])
        self.next_id = 0
        self.errors = []
        self.warnings = []
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
        while True:
            message = json.loads(self.ws.receive())
            if message.get("id") == my_id:
                if "error" in message:
                    raise RuntimeError(f"{method}: {message['error']}")
                return message.get("result", {})
            self.on_event(message)

    def on_event(self, message):
        method = message.get("method")
        params = message.get("params", {})
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
        raise TimeoutError(f"Timed out waiting for: {condition}")

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
            try:
                self.process.wait(timeout=wait)
            except subprocess.TimeoutExpired:
                continue
            try:
                os.killpg(self.process.pid, 0)  # helpers still there after the browser went?
            except ProcessLookupError:
                break
        if getattr(self, "watchdog", None) and self.watchdog.poll() is None:
            self.watchdog.kill()
        if self.own_profile:
            shutil.rmtree(self.profile, ignore_errors=True)


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
    for error in chrome.errors:
        print(f"page error: {error}", file=sys.stderr)
    sys.exit(1 if chrome.errors else 0)


if __name__ == "__main__":
    main()
