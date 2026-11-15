"""/next-chatgpt, a candidate landing page under test beside / (server/resources/views/pages/landing-next-chatgpt.blade.php),
as a visitor meets it: nothing sounds before a press; Play starts the song on the audio clock; a lane heard alone carries
on from the same spot; scrolling through every scene never stops or seeks it; the keyboard reaches Play and the lanes;
reduced motion gets the still version; and the page stays apart from the real site (never indexed, in no sitemap, no
other-language links, its one count under its own kind). No console error anywhere, and nothing scrolls sideways.

It runs against the server's own site, at SITE (default http://127.0.0.1:8412, `php -S 127.0.0.1:8412 tool/serve.php`
from server/), in both skins (game mode sees the same calm page):

    python3 -I tests/flows/next_chatgpt.py
"""
import json
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
PATH = "/next-chatgpt"
KIND = "sv_next_chatgpt"
FILM = "document.querySelector('[data-lnc-film]')"
READY = f"{FILM} && {FILM}.hasAttribute('data-ready') && document.fonts.status === 'loaded'"
# Every beacon the page sends is kept for the count check (set before any page script runs).
BEACONS = """window.__beacons = []; const send = navigator.sendBeacon.bind(navigator);
navigator.sendBeacon = (url, body) => { Promise.resolve(body && body.text ? body.text() : String(body)).then((text) => window.__beacons.push([String(url), text])); return send(url, body); };"""
# A machine without the song's render at MEDIA_URL (a worktree's own storage) plays the app's voices instead, as the
# player is built to: its one refused download isn't the page's error.
LOCAL = ["/media/song-audio/"]


def position(f):
    return float(f.js(f"{FILM}.style.getPropertyValue('--p') || '0'"))


def moving(f, what):
    """The song is playing and its position moves on the audio clock."""
    assert f.js(f"{FILM}.hasAttribute('data-playing')"), f"{what}: the song stopped"
    before = position(f)
    time.sleep(0.9)
    after = position(f)
    assert after > before, f"{what}: the song's position didn't move ({before} then {after})"
    return after


def apart(f, path, lang):
    """Never indexed, no canonical, no other-language links; in the page's language."""
    f.go(path, READY)
    head = json.loads(f.js("""JSON.stringify({ robots: document.querySelector('meta[name=robots]')?.content ?? null,
        canonical: document.querySelectorAll('link[rel=canonical]').length, alternate: document.querySelectorAll('link[rel=alternate]').length,
        hreflang: document.querySelectorAll('[hreflang]').length, lang: document.documentElement.lang, gs: document.documentElement.hasAttribute('data-gs') })"""))
    assert head["robots"] == "noindex", f"{path}: no noindex meta ({head})"
    assert head["canonical"] == 0 and head["alternate"] == 0 and head["hreflang"] == 0, f"{path}: it names another address ({head})"
    assert head["lang"] == lang, f"{path}: in {head['lang']}, not {lang}"
    assert not head["gs"], f"{path}: on the arcade screen (calm only until Hillel picks)"
    f.wait("window.__header !== undefined || (fetch(location.href).then((r) => (window.__header = r.headers.get('X-Robots-Tag'))), false)", what=f"{path}: its headers")
    assert f.js("window.__header") == "noindex", f"{path}: no X-Robots-Tag header"
    f.no_sideways(f"{path} at the top")


def journey(f):
    f.expected_errors += LOCAL
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": BEACONS})
    apart(f, PATH, "en")

    # Calm in either skin: the page's own face and ground, never the arcade's.
    face = f.js("getComputedStyle(document.querySelector('.lnc h1')).fontFamily")
    assert "Figtree" in face, f"the headline isn't in the calm face: {face}"

    # Nothing plays before a press; the first frame is whole.
    assert not f.js(f"{FILM}.hasAttribute('data-playing') || {FILM}.hasAttribute('data-played')"), "it played by itself"
    gems = int(f.js("document.querySelectorAll('.lnc-gem').length"))
    assert gems > 40, f"the lanes aren't drawn: {gems} notes"
    play = "document.querySelector('[data-play]')"
    f.wait(f"(() => {{ const r = {play}.getBoundingClientRect(); return r.width >= 44 && r.height >= 44 && r.top >= 0 && r.bottom <= innerHeight; }})()", what="Play on the first screen")
    f.shot("opening")

    # The one count: its own kind, once, and no section's or the landing's.
    if f.js("!!document.querySelector('[data-lnc-visit]')"):
        f.wait("window.__beacons.some(([url]) => url.includes('/api/counts'))", what="the page's count")
        time.sleep(0.5)
        kinds = [event["kind"] for url, body in json.loads(f.js("JSON.stringify(window.__beacons)")) if "/api/counts" in url for event in json.loads(body)["events"]]
        assert kinds == [KIND], f"the page counted {kinds}, not just [{KIND}]"
    else:
        print("note next_chatgpt: counts are off on this server; the count check was skipped", flush=True)

    # Play, on the audio clock.
    f.run(f"{play}.click()")
    f.wait(f"{FILM}.hasAttribute('data-playing')", timeout=25, what="the song playing")
    moving(f, "after Play")
    assert f.js("!!document.querySelector('.lnc-gem[data-lit], .lnc-gem[data-sung]')"), "no note lit as it sang"

    # A lane heard alone: the same spot, said on the lane and in the dock; again, all four.
    at = position(f)
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", what="the alto alone")
    assert f.js("document.querySelector('[data-lnc-chip=alto]').getAttribute('aria-pressed')") == "true", "the dock doesn't say the alto is alone"
    assert f.js(f"{FILM}.dataset.focus") == "alto", "the story doesn't follow the voice heard alone"
    assert moving(f, "the alto alone") > at, "hearing a lane alone started the song again"
    f.shot("alone")
    f.run("document.querySelector('[data-lnc-chip=tenor]').click()")
    f.wait("document.querySelector('[data-voice=tenor]').hasAttribute('data-solo') && !document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", what="the tenor alone, from the dock")
    assert "tenor" in f.js("document.querySelector('[data-lnc-learn-link]').href"), "Learn doesn't follow the voice heard alone"
    f.run("document.querySelector('[data-lnc-chip=tenor]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo]')", what="all four again")

    # Every scene, while it plays: scrolling never stops or seeks the song.
    f.run("document.documentElement.style.scrollBehavior = 'auto'")
    for scene in range(1, 8):
        at = position(f)
        f.run(f"window.scrollTo(0, {scene} * window.innerHeight + 8)")
        f.wait(f"{FILM}.dataset.scene === '{min(4, scene)}' && document.body.dataset.lncScene === '{scene}'", what=f"scene {scene}")
        if f.js(f"{FILM}.hasAttribute('data-playing')"):
            assert moving(f, f"scene {scene}") > at, f"scrolling to scene {scene} moved the song back"
        f.no_sideways(f"scene {scene}")
        f.shot(f"scene{scene}")
    assert f.js("document.querySelector('[data-lnc-top]').dataset.lncTop") == "down", "the header didn't shrink on the way down"
    f.run("window.scrollTo(0, 5 * window.innerHeight)")
    f.wait("document.querySelector('[data-lnc-top]').dataset.lncTop === 'up'", what="the header back on the way up")
    f.run(f"if ({FILM}.hasAttribute('data-playing')) {play}.click()")
    f.wait(f"!{FILM}.hasAttribute('data-playing')", what="stopped")

    # The keyboard: Play is reached and pressed with Enter; a lane too; the arrows move "I sing this".
    f.go(PATH, READY)
    for _ in range(14):
        f.press("Tab")
        if f.js("document.activeElement.hasAttribute('data-play')"):
            break
    assert f.js("document.activeElement.hasAttribute('data-play')"), "Tab never reached Play"
    assert f.js("getComputedStyle(document.activeElement).outlineStyle !== 'none'"), "Play shows no focus ring"
    f.press("Enter")
    f.wait(f"{FILM}.hasAttribute('data-playing')", timeout=25, what="playing from the keyboard")
    for _ in range(8):
        f.press("Tab")
        if f.js("document.activeElement.hasAttribute('data-hear-alone')"):
            break
    assert f.js("document.activeElement.hasAttribute('data-hear-alone')"), "Tab never reached a lane"
    lane = f.js("document.activeElement.closest('[data-voice]').dataset.voice")
    f.press("Enter")
    f.wait(f"document.querySelector('[data-voice={lane}]').hasAttribute('data-solo')", what=f"the {lane} alone from the keyboard")
    f.press("Tab")
    assert f.js("document.activeElement.hasAttribute('data-pick')"), "I sing this isn't next after its lane"
    f.press("ArrowDown")
    f.wait("document.activeElement.getAttribute('aria-checked') === 'true' && document.activeElement.closest('[data-voice]').classList.contains('s-is-you')", what="the arrows choosing a part")
    f.run(f"{play}.click()")

    # Reduced motion: the still version. Nothing transitions, the lanes lie flat, and the notes stand still while the
    # playhead crosses them; the same controls play the same song.
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.go(PATH, READY)
    still = json.loads(f.js("""JSON.stringify({ lanes: getComputedStyle(document.querySelector('.lnc-lanes')).transform, move: getComputedStyle(document.querySelector('.lnc-lanes')).transitionDuration,
        running: document.getAnimations().filter((a) => a.playState === 'running').length })"""))
    assert still["lanes"] == "none" and still["move"] in ("0s", "0s, 0s, 0s, 0s, 0s"), f"reduced motion still moves the stage: {still}"
    assert still["running"] == 0, f"reduced motion has {still['running']} animations running"
    f.run(f"{play}.click()")
    f.wait(f"{FILM}.hasAttribute('data-playing')", timeout=25, what="playing with motion reduced")
    f.wait(f"parseFloat({FILM}.style.getPropertyValue('--p') || '0') > 0.02", what="the song under way")
    track = "getComputedStyle(document.querySelector('.lnc-track')).transform"
    head = "document.querySelector('.lnc-playhead').getBoundingClientRect().left"
    first, x1 = f.js(track), float(f.js(head))
    moving(f, "with motion reduced")
    if f.js("CSS.supports('width', 'round(down, 1px, 1px)')"):
        assert f.js(track) == first, "with motion reduced the notes still slide"
        assert float(f.js(head)) > x1, "with motion reduced the playhead doesn't cross the line"
    f.shot("still")
    f.run(f"{play}.click()")
    f.chrome.send("Emulation.setEmulatedMedia", {"features": []})

    # The sitemap doesn't list it.
    f.run("window.__map = undefined; fetch('/sitemap.xml').then((r) => r.text()).then((t) => (window.__map = t)).catch(() => (window.__map = ''))")
    f.wait("window.__map !== undefined", what="the sitemap")
    assert "next-chatgpt" not in f.js("window.__map"), "the sitemap lists the page"

    # Spanish: its own words, the same rules.
    apart(f, "/es" + PATH, "es")
    assert "hecha por ti" in f.js("document.querySelector('h1').textContent"), "the Spanish page isn't in Spanish"
    f.shot("es")


if __name__ == "__main__":
    sys.argv[1:] = [SITE]
    os.environ.setdefault("FLOW_SKINS", "serious,game")
    flow.run("next_chatgpt", journey)
