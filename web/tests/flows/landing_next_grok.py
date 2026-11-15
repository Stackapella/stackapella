"""The combined landing, candidate "grok" (/next-grok and /es/next-grok; docs/landing-next-grok-plan.md), as a visitor
meets it, in the calm skin and in game mode (which shows the same calm page until Hillel picks a candidate):

- kept apart: a noindex meta and an X-Robots-Tag header, no canonical, no hreflang, not in the sitemap, and every count
  it sends is its own kind (sv_next_grok) or the player's render_fallback, never a landing or section count;
- the first frame is the server's: every gem is in the HTML, one per note of the song the page carries;
- nothing sounds by itself; Play starts it on the audio clock and the playhead moves;
- scrolling through all nine scenes changes the picture and never touches the sound (still playing, never back);
- a lane hears its part alone and brings the others back, "I sing this" picks it, and Learn follows;
- the bottom bar's Play and part bubbles press the stage's own; the rail's links lead to their scenes;
- by keyboard alone: Play, a lane, a pick and its arrows, a rail link, each with focus shown;
- reduced motion: the same controls on a flat, still stage (nothing leans, no transitions), and it plays;
- Spanish at /es/next-grok, kept apart the same way; nothing scrolls sideways; no console error.

Needs the real server (a site page):

    python3 -I tests/flows/landing_next_grok.py http://127.0.0.1:8412
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PATH = "/next-grok"
ROOT = "document.querySelector('[data-lng]')"
PLAY = "document.querySelector('[data-lng] [data-play]')"
P = f"parseFloat({ROOT}.style.getPropertyValue('--p') || '0')"
SCENES = 9
# Every count the page sends, kept for the journey to read (navigator.sendBeacon, as site.js and count.js send them).
BEACONS = """window.__beacons = []; const send = navigator.sendBeacon?.bind(navigator);
if (send) navigator.sendBeacon = (url, data) => { try { data?.text?.().then((t) => window.__beacons.push([String(url), t])); } catch (e) {} return send(url, data); };"""
SHOWN = """(() => { const e = document.activeElement; const cs = getComputedStyle(e);
  return (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || cs.boxShadow !== 'none'; })()"""


def tab_to(f, selector, limit=80):
    """Tabs until `selector` holds focus, and says focus shows there."""
    for _ in range(limit):
        f.press("Tab")
        if f.js(f"document.activeElement?.matches({selector!r}) ?? false"):
            assert f.js(SHOWN), f"focus isn't shown on {selector}"
            return
    raise AssertionError(f"never reached {selector} by Tab")


def apart(f, path, lang):
    """The page is kept out of search and out of the languages' links, in its own language."""
    assert f.js("document.documentElement.lang") == lang, f"{path} isn't in {lang}"
    assert f.js("document.querySelector('meta[name=robots]')?.content") == "noindex", f"{path} has no noindex meta"
    assert f.js("document.querySelector('link[rel=canonical]')") is None, f"{path} names a canonical address"
    assert f.js("document.querySelectorAll('link[hreflang]').length") == 0, f"{path} has hreflang links"
    header = f.js("fetch(location.href).then((r) => r.headers.get('x-robots-tag'))")
    assert header == "noindex", f"{path}'s X-Robots-Tag is {header!r}"
    f.no_sideways(path)


def scene(f, n):
    """Brings scene `n` up, and waits for the stage to follow."""
    f.run(f"document.querySelector('[data-lng-scene=\"{n}\"]').scrollIntoView({{ behavior: 'instant' }})")
    f.wait(f"{ROOT}.dataset.scene === '{n}'", what=f"scene {n} up")


def journey(f):
    # A local server has no media host: the recording can't load, and the page's own voices sing (said as a warning).
    f.expected_errors += ["/media/song-audio/", "song-audio"]
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": BEACONS})

    f.go(PATH, f"{ROOT}?.hasAttribute('data-ready') && {ROOT}.hasAttribute('data-lng-live') && document.fonts.status === 'loaded'")
    apart(f, PATH, "en")
    sitemap = f.js("fetch('/sitemap.xml').then((r) => r.text())")
    assert "next-grok" not in sitemap, "the page is in the sitemap"
    assert f.js("[...document.querySelectorAll('a[href]')].every((a) => !/next-(?!grok)/.test(a.getAttribute('href')))"), "it links to another candidate"

    # The first frame is the server's: a gem for every note of the song in the page, placed from its data.
    song = "JSON.parse(document.querySelector('[data-lng] [data-song]').textContent)"
    notes = f.js(f"{song}.parts.reduce((n, p) => n + p.notes.length, 0)")
    html = f.js("fetch(location.href).then((r) => r.text())")
    assert notes > 50 and html.count('class="s-lng-gem gem"') == notes, f"{html.count('s-lng-gem gem')} gems in the HTML for {notes} notes"
    assert f.js("[...document.querySelectorAll('[data-voice]')].length") == 4, "four lanes"
    # Calm, whatever the skin: the arcade's screen never dresses this page.
    assert not f.js("document.documentElement.hasAttribute('data-gs')"), "the arcade screen is on"
    assert "Figtree" in f.js("getComputedStyle(document.querySelector('.s-lng-h1')).fontFamily"), "the headline isn't in the calm face"

    # Nothing sounds by itself.
    time.sleep(1)
    assert not f.js(f"{ROOT}.hasAttribute('data-playing')"), "it started by itself"
    f.shot("open")

    # Play: on the audio clock, the playhead moving.
    f.run(f"{PLAY}.click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=30, what="the song playing")
    f.wait(f"{P} > 0", timeout=10, what="the playhead moving")
    f.wait("document.querySelector('.s-lng-gem[data-lit]') && document.querySelector('.s-lng-words [data-lit]')", timeout=10, what="a gem and a word lit")
    f.shot("playing")

    # Every scene in turn while it plays: the picture changes, the sound is never stopped or moved back.
    last = f.js(P)
    for n in range(1, SCENES):
        scene(f, n)
        time.sleep(0.5)
        now = f.js(P)
        assert f.js(f"{ROOT}.hasAttribute('data-playing')"), f"scrolling to scene {n} stopped the song"
        assert now > last, f"the song didn't carry on through scene {n} ({last} then {now})"
        assert f.js(f"document.querySelector('[data-lng-rail=\"{n}\"]').getAttribute('aria-current')") == "true", f"the rail doesn't mark scene {n}"
        last = now
    scene(f, 1)

    # A lane alone, and back; the bottom bar says the same; Learn follows it.
    alto = "document.querySelector('[data-voice=alto]')"
    f.run(f"{alto}.querySelector('[data-hear-alone]').click()")
    f.wait(f"{alto}.hasAttribute('data-solo') && {alto}.querySelector('[data-hear-alone]').getAttribute('aria-pressed') === 'true'", what="the alto alone")
    assert f.js("[...document.querySelectorAll('[data-voice]')].filter((v) => v.hasAttribute('data-off')).length") == 3, "the other three stepped back"
    assert f.js(f"{ROOT}.hasAttribute('data-playing')") and f.js(P) >= last, "hearing a part alone stopped or moved the song"
    assert "part=alto" in f.js("document.querySelector('[data-lng-learn]').href"), "Learn doesn't follow the part heard alone"
    assert "alto" in f.js("document.querySelector('[data-lng-learn]').textContent").lower(), "Learn doesn't name the alto"
    assert "ref=next-grok" in f.js("document.querySelector('[data-lng-learn]').href"), "Learn doesn't carry the page's ref"
    if f.size == "desktop":
        assert f.js("document.querySelector('[data-lng-part=alto]').getAttribute('aria-pressed')") == "true", "the bar's alto isn't pressed"
        f.shot("alto-alone")
        f.run("document.querySelector('[data-lng-part=alto]').click()")
    else:
        f.shot("alto-alone")
        f.run(f"{alto}.querySelector('[data-hear-alone]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo]') && !document.querySelector('[data-voice][data-off]')", what="all four back")
    # "I sing this" makes a part yours.
    f.run("document.querySelector('[data-voice=tenor] [data-pick]').click()")
    f.wait("document.querySelector('[data-voice=tenor] [data-pick]').getAttribute('aria-checked') === 'true'", what="the tenor picked")
    assert "part=tenor" in f.js("document.querySelector('[data-lng-learn]').href"), "Learn doesn't follow the pick"

    # The bottom bar's Play is the stage's own.
    f.run("document.querySelector('[data-lng-play]').click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", what="stopped from the bottom bar")
    assert f.js("document.querySelector('[data-lng-play]').getAttribute('aria-label')") == f.js(f"{PLAY}.getAttribute('aria-label')"), "the bar's Play says something else"

    # A rail link leads to its scene (a real link).
    f.run("document.querySelector('[data-lng-rail=\"6\"]').click()")
    f.wait(f"{ROOT}.dataset.scene === '6'", what="the rail's link to Choirs")
    assert f.js("location.hash") == "#lng-choirs", "the rail's link isn't an address"
    f.shot("choirs")

    # Every count it sent: its own kind, or the player's render_fallback; never the landing's or a section's.
    time.sleep(0.5)
    kinds = [e["kind"] for url, body in f.js("window.__beacons") if "/api/counts" in url for e in json.loads(body).get("events", [])]
    assert all(k in ("sv_next_grok", "render_fallback") for k in kinds), f"it counted {kinds}"
    visit = f.js("document.querySelector('[data-site-visit]')?.textContent ?? null")
    assert visit is None or json.loads(visit) == {"kind": "sv_next_grok"}, f"its visit is {visit}"
    assert f.js("document.querySelector('[data-funnel], [data-funnel-start]')") is None, "it carries the landing's funnel"

    # By keyboard alone, from the top.
    f.go(PATH, f"{ROOT}?.hasAttribute('data-ready') && {ROOT}.hasAttribute('data-lng-live')")
    tab_to(f, "[data-lng] [data-play]")
    f.press("Enter")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=30, what="Enter plays")
    tab_to(f, "[data-voice=soprano] [data-hear-alone]")
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "true", "Enter hears the lane alone, and says so"
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "false", "and Enter again brings every part back"
    tab_to(f, "[data-voice] [data-pick]")
    f.press(" ")
    assert f.js("document.activeElement.getAttribute('aria-checked')") == "true", "Space picks the part"
    f.press("ArrowDown")
    assert f.js("document.activeElement.closest('[data-voice]').dataset.voice") == "alto", "the arrows move to the next part"
    f.run(f"{PLAY}.click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", what="stopped")
    # The rail's next scene (on a phone the rail is the scene that's up and its two neighbours).
    tab_to(f, "[data-lng-rail='1']")
    assert f.js("document.activeElement.textContent.trim()") != "", "the rail's link has no name"
    f.press("Enter")
    f.wait(f"{ROOT}.dataset.scene === '1'", what="Enter on the rail leads to Listen")
    # The top bar's links are real links, reachable.
    assert f.js("[...document.querySelectorAll('[data-lng-bar] a')].map((a) => a.getAttribute('href')).filter(Boolean).length") >= 6, "the bar's links"

    # Reduced motion: flat and still, the same controls, and it plays.
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.go(PATH, f"{ROOT}?.hasAttribute('data-ready') && {ROOT}.hasAttribute('data-lng-live')")
    deck = "getComputedStyle(document.querySelector('.s-lng-deck'))"
    assert f.js(f"getComputedStyle({ROOT}).getPropertyValue('--lng-rx').trim()") == "0deg", "the lanes still lean under reduced motion"
    assert f.js(f"{deck}.transitionDuration.split(',').every((d) => parseFloat(d) === 0)"), "the deck still glides under reduced motion"
    assert f.js("getComputedStyle(document.documentElement).scrollSnapType") in ("none", ""), "the page still snaps under reduced motion"
    assert f.js("document.querySelectorAll('[data-hear-alone]').length") == 4 and f.js("document.querySelectorAll('[data-pick]').length") == 4, "the controls are the same"
    f.run(f"{PLAY}.click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing') && {P} > 0", timeout=30, what="it plays under reduced motion")
    f.shot("reduced-motion")
    scene(f, 3)
    assert f.js(f"{ROOT}.hasAttribute('data-playing')"), "scrolling stopped the song under reduced motion"
    f.run(f"{PLAY}.click()")
    f.chrome.send("Emulation.setEmulatedMedia", {"features": []})

    # Spanish, kept apart the same way.
    f.go("/es" + PATH, f"{ROOT}?.hasAttribute('data-ready')")
    apart(f, "/es" + PATH, "es")
    assert f.js("document.querySelector('.s-lng-h1').textContent.trim()") != "", "the Spanish headline"
    assert "/es/" in f.js("document.querySelector('[data-lng-bar] nav a').getAttribute('href')"), "the Spanish bar leads to English pages"
    f.shot("es")


run("landing_next_grok", journey, sizes=("phone", "desktop"))
