"""Helpers for the flow scripts: whole journeys through the app in muted headless Chrome, driven
by tool/cdp.py (Python standard library only, no npm).

Each flow script is `tests/flows/<name>.py`, run by tool/flows.sh (or on its own:
`python3 -I tests/flows/<name>.py http://127.0.0.1:8081`). It runs at phone size (390 x 844) and
desktop size (1440 x 900), and saves a screenshot of each step to tests/flows/shots/.
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402

SIZES = {"phone": (390, 844, True), "desktop": (1440, 900, False), "small": (375, 667, True), "laptop": (1050, 700, False), "narrow": (360, 780, True)}  # small, laptop, narrow: FLOW_SIZES=small
SHOTS = Path(__file__).resolve().parent / "shots"


class Flow:
    """One browser at one size. Fails loudly: every step raises with what it was waiting for."""

    def __init__(self, base, size="desktop", name="flow", skin="game"):
        width, height, mobile = SIZES[size]
        self.base = base.rstrip("/")
        self.size = size
        self.skin = skin
        self.name = name if skin == "game" else f"{name}-{skin}"
        self.chrome = Chrome(None, width, height, mobile)
        if skin != "game":
            # The skin (docs/skins-plan.md) chosen before any page script runs, as theme-boot reads it, and
            # the mock account's too (the account's choice wins when it loads).
            self.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": f"""try {{ const c = JSON.parse(localStorage.getItem('stackapella.appearance') || '{{}}'); if (c.skin !== '{skin}') localStorage.setItem('stackapella.appearance', JSON.stringify({{ ...c, skin: '{skin}', at: Date.now() }})); const m = JSON.parse(sessionStorage.getItem('stackapella.mockAppearance') || '{{}}'); if (m.skin !== '{skin}') sessionStorage.setItem('stackapella.mockAppearance', JSON.stringify({{ ...m, skin: '{skin}' }})); }} catch (e) {{}}"""})
        self.step = 0
        # Substrings of console errors this journey causes on purpose. Without a server, the
        # app's start-up calls (the catalogue, the account, coming soon) are 404s, all handled.
        self.expected_errors = ["404 (Not Found) " + self.base + "/api/templates", "404 (Not Found) " + self.base + "/api/account", "404 (Not Found) " + self.base + "/api/auth/config", "404 (Not Found) " + self.base + "/api/boards/daily"]

    def go(self, path, wait=None):
        self.chrome.goto(self.base + path, wait)
        return self

    def mic_checked(self):
        """Marks this browser's mic as heard (the mic check, js/mic-check.js, comes up the first time
        anything needs the mic): for journeys about something else."""
        return self.run("""const a = await import('/js/audio.js'); const had = !!a.audio.mic; await a.openMic();
            (await import('/js/mic-check.js')).rememberMic(); if (!had) a.closeMic();""")

    def js(self, expression):
        """Evaluates `expression` in the page and returns its JSON value. It may use `await`, so
        `(await import('/js/audio.js')).engine` reaches the app's own module instances."""
        return self.chrome.evaluate(f"(async () => ({expression}))()")

    def run(self, statements):
        """Runs JavaScript statements in the page (they may use await); returns nothing."""
        self.chrome.evaluate(f"(async () => {{ {statements} }})()")
        return self

    def type(self, selector, text):
        """Types `text` into the field `selector` (sets its value and tells Vue)."""
        return self.run(f"const el = document.querySelector({json.dumps(selector)}); el.value = {json.dumps(text)}; el.dispatchEvent(new Event('input', {{ bubbles: true }}));")

    def wait(self, condition, timeout=20, what=None):
        deadline = time.time() + timeout
        while time.time() < deadline:
            try:
                if self.js(f"!!({condition})"):
                    return self
            except RuntimeError:
                pass
            time.sleep(0.1)
        raise AssertionError(f"[{self.name}, {self.size}] timed out waiting for {what or condition}")

    def wait_text(self, text, timeout=20):
        return self.wait(f"document.body.innerText.includes({json.dumps(text)})", timeout, f'the text "{text}"')

    def click(self, text, selector="button, a, [role=button], [role=radio], summary, label"):
        """Clicks the first visible element of `selector` whose text or aria-label is `text`."""
        found = self.js(f"""(() => {{
            const want = {json.dumps(text)}.toLowerCase();
            const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
            const named = (el) => [el.innerText, el.getAttribute('aria-label'), el.title].filter(Boolean).map((s) => s.trim().toLowerCase());
            const el = [...document.querySelectorAll({json.dumps(selector)})].find((e) => visible(e) && !e.disabled && named(e).some((n) => n === want))
                ?? [...document.querySelectorAll({json.dumps(selector)})].find((e) => visible(e) && !e.disabled && named(e).some((n) => n.includes(want)));
            if (!el) return false;
            el.click();
            return true;
        }})()""")
        if not found:
            raise AssertionError(f'[{self.name}, {self.size}] no button or link "{text}"')
        return self

    def go_on(self):
        """Clicks a lesson's way on (Continue, Finish, or "Turn on my mic" before the note step)."""
        self.run("document.querySelector('[data-go-on]:not(:disabled)').click()")
        return self

    def click_t(self, key, values=None):
        """Clicks the button whose words are t(key) in the page's language, so a flow works in
        the pseudo-language (and any other) as it does in English."""
        label = self.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")
        return self.click(label)

    KEYS = {"Enter": (13, "\r"), "Escape": (27, ""), "Tab": (9, ""), " ": (32, " "), "n": (78, "n"), "m": (77, "m"), "r": (82, "r")}

    def sing_view(self, view):
        """Sing's view (words, notes or staff), chosen in Session settings' Show."""
        self.run("document.querySelector('[data-settings-open]').click()")
        self.wait("document.querySelector('[data-view]')", what="Session settings' Show")
        self.click_t(f"sing.view.{view}")
        self.press("Escape")
        return self.wait("!document.querySelector('[data-session-settings]')", what="Session settings closed")

    def press(self, key):
        """Presses a key for real (the browser's own input, so Enter activates the focused button)."""
        code, text = self.KEYS[key]
        name = "Space" if key == " " else key
        for kind in ("keyDown", "keyUp"):
            params = {"type": kind, "key": key, "code": name, "windowsVirtualKeyCode": code, "nativeVirtualKeyCode": code}
            if kind == "keyDown" and text:
                params["text"] = text
            self.chrome.send("Input.dispatchKeyEvent", params)
        # A real key press can leave headless Chrome's page "hidden", which pauses animation frames.
        self.chrome.send("Page.bringToFront")
        return self

    def no_sideways(self, where):
        """The page doesn't scroll sideways (Hillel: the take's review scrolled left and right on his phone)."""
        over = self.js("document.documentElement.scrollWidth - document.documentElement.clientWidth")
        # A box that scrolls up and down scrolls sideways too when a child is too wide (overflow-x
        # follows overflow-y): Sing's screens scroll inside such boxes, not the page. Rows meant to
        # scroll sideways hide their scrollbar ([scrollbar-width:none]) and are left out.
        inner = self.js("""[...document.querySelectorAll('body *')].filter((e) => { const cs = getComputedStyle(e);
            return ['auto', 'scroll'].includes(cs.overflowX) && cs.scrollbarWidth !== 'none' && e.scrollWidth > e.clientWidth + 1 && e.clientWidth > 0; })
            .map((e) => `${e.tagName.toLowerCase()}.${[...e.classList].slice(0, 5).join('.')} by ${e.scrollWidth - e.clientWidth}px`)""")
        if inner:
            raise AssertionError(f"[{self.name}, {self.size}] {where} has a box scrolling sideways: {inner[:4]}")
        if over > 0:
            wide = self.js("""(() => { const w = document.documentElement.clientWidth;
                return [...document.querySelectorAll('body *')].filter((e) => e.getBoundingClientRect().right > w + 1).slice(-5)
                    .map((e) => `${e.tagName.toLowerCase()}.${[...e.classList].slice(0, 5).join('.')} to ${Math.round(e.getBoundingClientRect().right)}`); })()""")
            raise AssertionError(f"[{self.name}, {self.size}] {where} scrolls sideways by {over}px: {wide}")
        return self

    def shot(self, label):
        SHOTS.mkdir(exist_ok=True)
        self.step += 1
        path = SHOTS / f"{self.name}-{self.size}-{self.step:02d}-{label}.png"
        time.sleep(0.3)
        self.chrome.screenshot(str(path))
        return self

    def close(self):
        self.chrome.close()
        errors = [e for e in self.chrome.errors if "favicon" not in e and not any(x in e for x in self.expected_errors) and not font_blip(e)]
        if errors:
            raise AssertionError(f"[{self.name}, {self.size}] page errors: {errors}")


# Google Fonts' own network blips (ERR_SOCKET_NOT_CONNECTED and the like) fail runs at random, and
# the push gate runs these flows: a network failure from those two hosts only is ignored. A 404, a
# blocked request or any other error from them still fails.
FONT_HOSTS = ("https://fonts.googleapis.com/", "https://fonts.gstatic.com/")


def font_blip(error):
    return "net::ERR_" in error and any(host in error for host in FONT_HOSTS)


def run(name, journey, sizes=("phone", "desktop")):
    """Runs `journey(flow)` at each size against the server in argv[1]; exits 1 on a failure."""
    base = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081"
    failed = False
    # FLOW_SIZES=small,phone runs other sizes (a small phone, 375 x 667, isn't run by default).
    sizes = os.environ.get("FLOW_SIZES", ",".join(sizes)).split(",")
    # FLOW_SKINS=game,serious runs each size in both skins (docs/skins-plan.md); game alone by default.
    skins = os.environ.get("FLOW_SKINS", "game").split(",")
    for size, skin in [(size, skin) for skin in skins for size in sizes]:
        flow = Flow(base, size, name, skin)
        label = size if skin == "game" else f"{size}, {skin}"
        try:
            journey(flow)
            flow.close()
            print(f"ok   {name} ({label})")
        except Exception as error:  # report every size, then fail
            failed = True
            try:
                flow.shot("failed")
                flow.chrome.close()
            except Exception:
                pass
            print(f"FAIL {name} ({label}): {error}")
            for message in flow.chrome.errors[-5:]:
                print(f"     page error: {message[:300]}")
    sys.exit(1 if failed else 0)
