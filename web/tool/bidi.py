"""Drives Firefox (headless) and Safari over WebDriver BiDi, with nothing but the Python standard library.

The same interface as tool/cdp.py's Chrome (goto, evaluate, screenshot, errors, close, and the few
DevTools calls the flows make, translated), so a flow runs in either browser unchanged:

    FLOW_BROWSER=firefox tool/flows.sh first_words sing
    FLOW_BROWSER=safari tool/flows.sh first_words sing
    python3 -I tool/bidi.py eval <url> <js> [--wait <js>] [--browser safari]

Firefox runs headless and muted, with its fake microphone and no permission prompts.

Safari can't run headless: safaridriver opens one real window (Safari > Settings > Developer >
Allow remote automation must be on), one session at a time. Its mic prompt can't be answered from
here, so the page gets a synthetic mic (FAKE_MIC: a quiet tone from its own AudioContext) in place of
getUserMedia; everything after the mic (the worklet, the engine, the scoring) is Safari's own.

A DevTools call with no BiDi equivalent raises ChromeOnly, so a flow that needs one fails saying so
rather than passing wrongly.
"""
import argparse
import atexit
import base64
import json
import os
import re
import shutil
import signal
import socket
import subprocess
import sys
import tempfile
import threading
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from cdp import CLOSE_SECONDS, WebSocket  # noqa: E402
import cdn_cache  # noqa: E402  (the pinned CDN libraries from this machine's cache, as cdp.py serves them)

FIREFOX = os.environ.get("FIREFOX", "/Applications/Firefox.app/Contents/MacOS/firefox")
SAFARIDRIVER = os.environ.get("SAFARIDRIVER", "/usr/bin/safaridriver")
CALL_SECONDS = 90  # one protocol call (an awaited evaluate included) that takes longer fails the flow

# Firefox's profile preferences: a quiet start, a fake mic with no prompt, sound allowed but silent.
PREFS = {
    "browser.shell.checkDefaultBrowser": False,
    "browser.startup.homepage_override.mstone": "ignore",
    "browser.aboutwelcome.enabled": False,
    "datareporting.policy.dataSubmissionEnabled": False,
    "toolkit.telemetry.reportingpolicy.firstRun": False,
    "app.update.disabledForTesting": True,
    "app.normandy.enabled": False,
    "browser.newtabpage.enabled": False,
    "media.navigator.streams.fake": True,
    "media.navigator.permission.disabled": True,
    "media.autoplay.default": 0,
    "media.autoplay.block-webaudio": False,
    "media.volume_scale": "0.0",
    "dom.disable_open_during_load": False,
    "network.dns.localDomains": "stackapella.test,www.stackapella.test,app.stackapella.test",
}

# The browser's own notices, logged at error level but no fault of the page's, kept as warnings:
# Firefox rejecting a CDN's Cloudflare cookie (__cf_bm) for a localhost page; and Firefox logging a
# fetch the service worker answered but the page abandoned (a flow navigating while views are being
# fetched ahead, main.js fetchAhead). A module that really fails to load still fails the flow, which
# waits for the screen it builds.
BROWSER_NOTICE = re.compile(r"^Cookie “[^”]+” has been rejected|^Failed to load ‘[^’]+’\. A ServiceWorker intercepted the request and encountered an unexpected error\.$")

# WebDriver's key values for the keys the flows press (the spec's private-use code points).
KEYS = {"Enter": chr(0xE007), "Escape": chr(0xE00C), "Tab": chr(0xE004)}

# Safari's stand-in mic: getUserMedia answers with a quiet 220 Hz tone from a page-owned
# AudioContext, labelled like a real input, and the permission reads as granted, as Chrome's
# --use-fake-ui-for-media-stream gives it.
FAKE_MIC = """
const md = navigator.mediaDevices;
if (md && !md.__fake) {
    md.__fake = true;
    let ctx;
    md.getUserMedia = async (constraints) => {
        if (!constraints || !constraints.audio) throw new DOMException('Only audio is faked', 'NotSupportedError');
        ctx = ctx || new AudioContext();
        if (ctx.state !== 'running') await ctx.resume();
        const tone = ctx.createOscillator(); tone.frequency.value = 220;
        const level = ctx.createGain(); level.gain.value = 0.05;
        const out = ctx.createMediaStreamDestination();
        tone.connect(level).connect(out); tone.start();
        const track = out.stream.getAudioTracks()[0];
        Object.defineProperty(track, 'label', { value: 'Fake mic (tool/bidi.py)' });
        // The same device as enumerateDevices lists, so a mic already checked is known again (mic-check.js).
        const settings = track.getSettings.bind(track);
        track.getSettings = () => ({ ...settings(), deviceId: 'fake-mic', groupId: 'fake', channelCount: 1, sampleRate: ctx.sampleRate });
        return out.stream;
    };
    const devices = md.enumerateDevices.bind(md);
    md.enumerateDevices = async () => {
        const real = (await devices()).filter((d) => d.kind !== 'audioinput');
        return [{ deviceId: 'fake-mic', groupId: 'fake', kind: 'audioinput', label: 'Fake mic (tool/bidi.py)', toJSON() { return this; } }, ...real];
    };
}
if (navigator.permissions && !navigator.permissions.__fake) {
    navigator.permissions.__fake = true;
    const query = navigator.permissions.query.bind(navigator.permissions);
    navigator.permissions.query = (d) => (d && d.name === 'microphone'
        ? Promise.resolve({ state: 'granted', name: 'microphone', onchange: null, addEventListener() {}, removeEventListener() {} })
        : query(d));
}
"""


class ChromeOnly(RuntimeError):
    """A DevTools call the flow made that this browser can't do over BiDi."""


def free_port():
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


class Bidi:
    """The protocol side, shared: everything once a session is open on `self.ws`."""

    name = "bidi"

    def _start(self):
        self.ws.sock.settimeout(CALL_SECONDS)
        self.granted = set()
        self.next_id = 0
        self.replies = {}  # replies to calls made while another call waited (a paused request answered meanwhile)
        self.errors = []
        self.warnings = []
        self.cdn = {}

    def _ready(self, width, height, mobile):
        self.context = self.send_bidi("browsingContext.getTree", {})["contexts"][0]["context"]
        self.send_bidi("session.subscribe", {"events": ["log.entryAdded"]})
        # The pinned CDN libraries from this machine's cache (cdn_cache.py), where the browser's BiDi can intercept
        # (Firefox can; a browser that can't simply fetches them from the CDN as before).
        if cdn_cache.ON:
            try:
                self.send_bidi("network.addIntercept", {"phases": ["beforeRequestSent"], "urlPatterns": [{"type": "pattern", "protocol": "https", "hostname": "cdn.jsdelivr.net"}]})
                self.send_bidi("session.subscribe", {"events": ["network.beforeRequestSent"]})
                self.cdn = cdn_cache.pinned()
            except (RuntimeError, TimeoutError):
                self.cdn = {}
        self.set_viewport(width, height, mobile)

    def set_viewport(self, width, height, mobile):
        self.send_bidi("browsingContext.setViewport", {
            "context": self.context, "viewport": {"width": width, "height": height}, "devicePixelRatio": 2 if mobile else 1})

    def send_bidi(self, method, params):
        self.next_id += 1
        my_id = self.next_id
        self.ws.send(json.dumps({"id": my_id, "method": method, "params": params}))
        while True:
            try:
                message = self.replies.pop(my_id, None) or json.loads(self.ws.receive())
            except TimeoutError:
                raise TimeoutError(f"{self.name}: {method} didn't answer in {CALL_SECONDS} s") from None
            if message.get("id") == my_id:
                if message.get("type") == "error":
                    raise RuntimeError(f"{method}: {message.get('error')}: {message.get('message')}")
                return message.get("result", {})
            if message.get("type") == "event":
                self.on_event(message)
            elif "id" in message:
                self.replies[message["id"]] = message  # an outer call's reply, read by a call made inside an event

    def on_event(self, message):
        if message.get("method") == "network.beforeRequestSent" and message["params"].get("isBlocked"):
            self.answer_cdn(message["params"])
            return
        if message.get("method") != "log.entryAdded":
            return
        entry = message["params"]
        text = entry.get("text") or " ".join(str(a.get("value", a.get("type"))) for a in entry.get("args", []))
        if BROWSER_NOTICE.search(text):
            self.warnings.append(text)
            return
        if entry.get("type") == "javascript" or entry.get("level") == "error":
            self.errors.append(text)
        elif entry.get("level") == "warn":
            self.warnings.append(text)

    def send(self, method, params=None):
        """The DevTools calls the flows make, done the BiDi way."""
        params = params or {}
        if method == "Page.addScriptToEvaluateOnNewDocument":
            self.preload(params["source"])
            return {}
        if method == "Page.bringToFront":
            return {}
        if method == "Input.dispatchKeyEvent":
            key = params.get("key", "")
            kind = "keyDown" if params["type"] in ("keyDown", "rawKeyDown") else "keyUp"
            self.send_bidi("input.performActions", {"context": self.context, "actions": [
                {"type": "key", "id": "keyboard", "actions": [{"type": kind, "value": KEYS.get(key, key)}]}]})
            return {}
        if method == "Page.captureScreenshot":
            return {"data": self._screenshot_data()}
        if method == "Emulation.setDeviceMetricsOverride":
            self.set_viewport(params["width"], params["height"], params.get("mobile", False))
            return {}
        raise ChromeOnly(f"{method} has no {self.name} equivalent here (tool/bidi.py)")

    def answer_cdn(self, params):
        """A paused request to the CDN: a pinned file from the cache, anything else on to the network."""
        request = params["request"]["request"]
        url = params["request"]["url"]
        body = cdn_cache.body_for(url, self.cdn[url]) if url in self.cdn else None
        if body is None:
            self.send_bidi("network.continueRequest", {"request": request})
            return
        header = lambda name, value: {"name": name, "value": {"type": "string", "value": value}}  # noqa: E731
        self.send_bidi("network.provideResponse", {"request": request, "statusCode": 200, "reasonPhrase": "OK", "headers": [
            header("Content-Type", "application/javascript; charset=utf-8"), header("Access-Control-Allow-Origin", "*"),
            header("Cache-Control", "public, max-age=31536000, immutable")], "body": {"type": "base64", "value": base64.b64encode(body).decode()}})

    def preload(self, source):
        """Runs `source` in every new document before the page's own scripts."""
        self.send_bidi("script.addPreloadScript", {"functionDeclaration": "() => { " + source + "\n}"})

    def evaluate(self, expression, await_promise=True):
        # Values come back as JSON text, as Chrome's returnByValue gives them. With a user activation,
        # as a tap gives, so audio and the mic start as they do for a singer.
        if await_promise:
            wrapped = f"(async () => {{ const v = await ({expression}); return v === undefined ? undefined : JSON.stringify(v); }})()"
        else:
            wrapped = f"(() => {{ const v = ({expression}); return v === undefined ? undefined : JSON.stringify(v); }})()"
        result = self.send_bidi("script.evaluate", {
            "expression": self._before() + wrapped, "target": {"context": self.context}, "awaitPromise": await_promise, "userActivation": True})
        if result.get("type") == "exception":
            details = result.get("exceptionDetails", {})
            raise RuntimeError(details.get("text") or json.dumps(details)[:500])
        return self._decode(result.get("result", {}))

    def _before(self):
        """Statements run ahead of every evaluate (Safari's stand-in for preload scripts)."""
        return ""

    def _decode(self, value):
        """The JSON text the wrapped expression returned, as a value (None for undefined)."""
        if value.get("type") != "string":
            return None
        return json.loads(value["value"])

    def grant_mic(self, url):
        """The mic reads as granted for `url`'s site, as Chrome's --use-fake-ui-for-media-stream gives it
        (else the app's own "why the mic" step waits for a tap, as it should for a singer)."""
        origin = re.match(r"^[a-z]+://[^/]+", url)
        if not origin or origin.group(0) in self.granted:
            return
        self.granted.add(origin.group(0))
        self.send_bidi("permissions.setPermission", {"descriptor": {"name": "microphone"}, "state": "granted", "origin": origin.group(0)})

    def goto(self, url, wait=None, timeout=30):
        self.grant_mic(url)
        try:
            self.send_bidi("browsingContext.navigate", {"context": self.context, "url": url, "wait": "none"})
        except RuntimeError as error:
            # Firefox says NS_BINDING_ABORTED when this navigation cancels one still loading: asked once more.
            if "NS_BINDING_ABORTED" not in str(error):
                raise
            time.sleep(0.3)
            self.send_bidi("browsingContext.navigate", {"context": self.context, "url": url, "wait": "none"})
        deadline = time.time() + timeout
        condition = wait or "document.readyState === 'complete'"
        time.sleep(0.1)  # the old document can still answer for a moment
        last = None
        while time.time() < deadline:
            try:
                if self.evaluate(f"!!({condition})", await_promise=False):
                    return
                last = None
            except RuntimeError as error:
                last = error
            time.sleep(0.2)
        try:
            where = self.evaluate("JSON.stringify({ at: location.href, ready: document.readyState, app: document.querySelector('#app')?.innerHTML.length ?? null })", await_promise=False)
        except RuntimeError as error:
            where = f"no answer: {error}"
        raise TimeoutError(f"Timed out waiting for: {condition} (the page: {where})" + (f" (the page kept answering: {last})" if last else ""))

    def _screenshot_data(self):
        return self.send_bidi("browsingContext.captureScreenshot", {"context": self.context})["data"]

    def screenshot(self, path):
        Path(path).write_bytes(base64.b64decode(self._screenshot_data()))

    def _watch(self, pid):
        """Ends the browser's process group if Python is killed outright (SIGKILL runs no atexit)."""
        self.watchdog = subprocess.Popen(
            ["/bin/sh", "-c", f"while kill -0 {os.getpid()} 2>/dev/null; do sleep 2; done; kill -9 -{pid} 2>/dev/null"],
            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, start_new_session=True,
        )

    def _end_group(self):
        for sig in (signal.SIGTERM, signal.SIGKILL):
            try:
                os.killpg(self.process.pid, sig)
            except ProcessLookupError:
                break
            except PermissionError:
                try:
                    self.process.send_signal(sig)
                except ProcessLookupError:
                    break
            try:
                self.process.wait(timeout=CLOSE_SECONDS)
                break
            except subprocess.TimeoutExpired:
                continue
        if getattr(self, "watchdog", None) and self.watchdog.poll() is None:
            self.watchdog.kill()


class Firefox(Bidi):
    name = "firefox"

    def __init__(self, profile=None, width=1440, height=900, mobile=False):
        self.own_profile = profile is None
        self.profile = profile or tempfile.mkdtemp(prefix="stk-firefox-")
        if self.own_profile:
            atexit.register(shutil.rmtree, self.profile, True)
        Path(self.profile).mkdir(parents=True, exist_ok=True)
        (Path(self.profile) / "user.js").write_text(
            "".join(f"user_pref({json.dumps(k)}, {json.dumps(v)});\n" for k, v in PREFS.items()))
        self.process = subprocess.Popen(
            [FIREFOX, "--headless", "--no-remote", "--profile", self.profile, "--remote-debugging-port", "0",
             f"--width={width}", f"--height={height}", "about:blank"],
            stdout=subprocess.DEVNULL, stderr=subprocess.PIPE, start_new_session=True,
        )
        self.closed = False
        atexit.register(self.close)
        self._watch(self.process.pid)
        try:
            self.ws = WebSocket(self._listening_url() + "/session")
            self._start()
            self.send_bidi("session.new", {"capabilities": {"alwaysMatch": {"acceptInsecureCerts": True, "unhandledPromptBehavior": {"default": "ignore"}}}})
            self._ready(width, height, mobile)
        except BaseException:
            self.close()
            raise

    def _listening_url(self, timeout=30):
        """Firefox prints "WebDriver BiDi listening on ws://127.0.0.1:<port>" once it's up."""
        found = {}

        def read():
            # Reads every line, so Firefox's chatter never fills the pipe.
            for raw in self.process.stderr:
                match = re.search(r"WebDriver BiDi listening on (ws://[\d.]+:\d+)", raw.decode(errors="replace"))
                if match and "url" not in found:
                    found["url"] = match.group(1)

        threading.Thread(target=read, daemon=True).start()
        deadline = time.time() + timeout
        while time.time() < deadline and "url" not in found:
            if self.process.poll() is not None:
                raise RuntimeError("Firefox exited during start-up")
            time.sleep(0.05)
        if "url" not in found:
            raise RuntimeError("Firefox didn't start (no BiDi address)")
        return found["url"]

    def close(self):
        """Ends Firefox and its helpers: politely, then by force. Safe to call twice."""
        if getattr(self, "closed", True):
            return
        self.closed = True
        self._end_group()
        if self.own_profile:
            shutil.rmtree(self.profile, ignore_errors=True)


class Safari(Bidi):
    name = "safari"

    def __init__(self, profile=None, width=1440, height=900, mobile=False):
        port = free_port()
        self.base = f"http://127.0.0.1:{port}"
        self.process = subprocess.Popen([SAFARIDRIVER, "-p", str(port)], stdout=subprocess.DEVNULL,
                                        stderr=subprocess.DEVNULL, start_new_session=True)
        self.closed = False
        self.session = None
        # Safari 26 accepts script.addPreloadScript but never runs it, so the fake mic and any preload
        # run at the start of every evaluate instead (each guards itself). A preload that must beat the
        # page's own scripts (a serious-skin run) only takes effect from the second page load.
        self.preloads = [FAKE_MIC]
        atexit.register(self.close)
        self._watch(self.process.pid)
        try:
            caps = {"browserName": "safari", "webSocketUrl": True, "safari:experimentalWebSocketUrl": True,
                    "webkit:alwaysAllowAutoplay": True, "acceptInsecureCerts": True}
            # Safari picks its BiDi port itself, low in the 8000s, and now and then one another server
            # on this Mac already answers (a 404 from nginx): that session is dropped for a new one.
            for attempt in range(4):
                value = self._http("POST", "/session", {"capabilities": {"alwaysMatch": caps}}, wait=15)
                self.session = value["sessionId"]
                url = value["capabilities"].get("webSocketUrl")
                if not isinstance(url, str):
                    raise RuntimeError("safaridriver gave no BiDi address (Safari too old?)")
                try:
                    self.ws = WebSocket(url)
                    break
                except RuntimeError as error:
                    if "handshake failed" not in str(error) or attempt == 3:
                        raise
                    self._http("DELETE", f"/session/{self.session}")
                    self.session = None
            self._start()
            self._ready(width, height, mobile)
        except BaseException:
            self.close()
            raise

    def _http(self, method, path, body=None, wait=0):
        """A classic WebDriver call; `wait` seconds of retries while safaridriver starts."""
        deadline = time.time() + wait
        while True:
            data = None if method == "GET" else json.dumps(body or {}).encode()
            request = urllib.request.Request(self.base + path, method=method, data=data,
                                             headers={"Content-Type": "application/json"})
            try:
                with urllib.request.urlopen(request, timeout=CALL_SECONDS) as response:
                    return json.load(response)["value"]
            except urllib.error.HTTPError as error:
                value = json.load(error).get("value", {})
                raise RuntimeError(f"safaridriver {path}: {value.get('message', value)}") from None
            except (ConnectionError, urllib.error.URLError):
                if time.time() > deadline:
                    raise
                time.sleep(0.2)

    def preload(self, source):
        self.preloads.append(source)

    def grant_mic(self, url):
        pass  # FAKE_MIC answers navigator.permissions itself

    def send(self, method, params=None):
        # Safari 26's BiDi has no input module: key presses go through classic WebDriver actions.
        if method == "Input.dispatchKeyEvent":
            key = (params or {}).get("key", "")
            kind = "keyDown" if params["type"] in ("keyDown", "rawKeyDown") else "keyUp"
            self._http("POST", f"/session/{self.session}/actions", {"actions": [
                {"type": "key", "id": "keyboard", "actions": [{"type": kind, "value": KEYS.get(key, key)}]}]})
            return {}
        return super().send(method, params)

    def goto(self, url, wait=None, timeout=30):
        self.origin = (re.match(r"^[a-z]+://[^/]+", url) or [None])[0]
        return super().goto(url, wait, timeout)

    def on_event(self, message):
        # Safari words a same-origin fetch the page abandoned (a navigation mid-request) as an access
        # control failure; a same-origin request can't really fail CORS, so it's a notice, not a fault.
        text = (message.get("params") or {}).get("text") or ""
        found = re.match(r"^Fetch API cannot load (\S+) due to access control checks\.$", text)
        if found and getattr(self, "origin", None) and found.group(1).startswith(self.origin + "/"):
            self.warnings.append(text)
            return
        super().on_event(message)

    def _before(self):
        # One expression (Safari evaluates the text as an expression): the preloads, then the comma.
        return "".join(f"(() => {{ try {{ {source}\n }} catch (e) {{ console.warn('preload', e); }} }})(), " for source in self.preloads)

    def _decode(self, value):
        # Safari 26's experimental BiDi sends every result as {"type": "object", "value": <its JSON>}.
        text = value.get("value")
        if not isinstance(text, str):
            return None
        try:
            inner = json.loads(text)
        except ValueError:
            return None
        return json.loads(inner) if isinstance(inner, str) else None

    def _screenshot_data(self):
        return self._http("GET", f"/session/{self.session}/screenshot")

    def set_viewport(self, width, height, mobile):
        """Safari has no viewport override: the window is sized so the page gets `width` x `height`
        (a phone size is as close as macOS lets a window get; no touch, no device pixel ratio)."""
        self._http("POST", f"/session/{self.session}/window/rect", {"width": width, "height": height + 100})
        inner = self.evaluate("[innerWidth, innerHeight]", await_promise=False)
        self._http("POST", f"/session/{self.session}/window/rect",
                   {"width": width + (width - inner[0]), "height": height + 100 + (height - inner[1])})

    def close(self):
        """Ends the session (Safari's window goes) and safaridriver. Safe to call twice."""
        if getattr(self, "closed", True):
            return
        self.closed = True
        if self.session:
            try:
                self._http("DELETE", f"/session/{self.session}")
            except Exception:  # noqa: BLE001 (the driver going below ends the window anyway)
                print("safaridriver: the session didn't end cleanly", file=sys.stderr)
        self._end_group()


BROWSERS = {"firefox": Firefox, "safari": Safari}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("command", choices=["shot", "eval"])
    parser.add_argument("url")
    parser.add_argument("arg")
    parser.add_argument("--browser", choices=BROWSERS, default="firefox")
    parser.add_argument("--size", default="1440x900")
    parser.add_argument("--mobile", action="store_true")
    parser.add_argument("--wait")
    parser.add_argument("--timeout", type=float, default=30)
    args = parser.parse_args()
    width, height = (int(v) for v in args.size.split("x"))
    browser = BROWSERS[args.browser](None, width, height, args.mobile)
    try:
        browser.goto(args.url, args.wait, args.timeout)
        time.sleep(0.4)
        if args.command == "shot":
            browser.screenshot(args.arg)
        else:
            print(json.dumps(browser.evaluate(args.arg), indent=2))
    finally:
        browser.close()
    for error in browser.errors:
        print(f"page error: {error}", file=sys.stderr)
    sys.exit(1 if browser.errors else 0)


if __name__ == "__main__":
    main()
