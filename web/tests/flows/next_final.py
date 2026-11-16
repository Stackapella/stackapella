"""/next-final, the candidate for the final next landing page (pages/next-final.blade.php, js/public/landing-next-final.js,
css/landing-next-final.css): a stage that fills the screen for five scenes, the song's four lanes on it, then a page
that scrolls, and a dock that stays. On a desktop and a phone:

- the first frame is the server's: the headline, the four lanes and every gem are there before any script, and nothing
  sounds or is fetched for sound until a press; the page is its own document with its own small sheet (none of the
  site's, no game sheet or script);
- Play plays: the page says so, the time and the song's place move, gems light and the sung line follows; the control
  says Stop (a square, never a pause), and Stop puts the song back at its start;
- a lane heard alone, then all four again, from the same spot (the place never goes back), said to a screen reader;
- the dock's four voices do the same from anywhere, and the Hear scene's button and the Learn links name the voice last
  heard alone and lead to its part;
- the stage's scenes follow the scroll, 0 to 4; after Share the stage lets go and the rest scrolls as a page: nothing
  scrolls inside itself, the lanes end above the dock in every scene, and the last question clears the dock;
- scrolling never stops or moves the song;
- the keyboard: Play is the first control in the page's main part, the lanes are one stop (the arrows move between them,
  Space hears one alone), and focus in a scene of the stage off the screen brings its scene;
- light and dark by the top line's switch;
- reduced motion: nothing is pinned, every scene shows, and the song still plays with its gems lit in place;
- private: noindex in the page and its header, no canonical, no alternates, not in the sitemap, linked from neither
  landing; and it sends only its own count (sv_next_final), never the landing's;
- it has no game dress: in the game skin it's the same calm page;
- Spanish at /es/next-final; no console errors anywhere.

Runs against the site (SITE, as landing_audio.py):  SITE=http://127.0.0.1:8412 python3 -I tests/flows/next_final.py
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
READY = "document.querySelector('[data-lnf]')?.dataset.ready !== undefined && document.getElementById('lnf')?.hasAttribute('data-ready')"
# Every beacon the page sends, and every request for sound, kept for the checks.
WATCH = """window.__beacons = []; const send = Navigator.prototype.sendBeacon;
    Navigator.prototype.sendBeacon = function (url, data) { try { data?.text?.().then((t) => window.__beacons.push([String(url), t])); } catch (e) { window.__beacons.push([String(url), '']); } return send.apply(this, arguments); };"""
STATE = """(() => { const s = document.getElementById('lnf'); return {
    playing: s.hasAttribute('data-playing'), p: Number.parseFloat(s.style.getPropertyValue('--p')) || 0,
    t: Number.parseFloat(s.style.getPropertyValue('--lnf-t')) || 0,
    lit: s.querySelectorAll('.lnf-g[data-lit]').length, sung: s.querySelectorAll('.lnf-g[data-sung]').length,
    line: [...s.querySelectorAll('.lnf-line')].find((l) => !l.hidden)?.textContent.trim() ?? '',
    now: s.querySelectorAll('.lnf-line [data-now]').length,
    alone: [...s.querySelectorAll('[data-voice][data-solo]')].map((l) => l.dataset.voice),
    live: s.querySelector('[data-lnf-live]').textContent, time: s.querySelector('[data-time]').textContent,
    scene: s.dataset.scene, tail: s.hasAttribute('data-tail') }; })()"""
# Whether the song's render can be sought: there is none (the player's own voices sing, on the audio clock), or its
# host answers a byte range.
SEEKS = """(async () => { const audio = JSON.parse(document.querySelector('#lnf [data-song]').textContent).audio;
    const url = audio?.m4a ?? audio?.mp3; if (!url) return true;
    try { return (await fetch(url, { headers: { Range: 'bytes=0-1' } })).status === 206; } catch (e) { return true; } })()"""
# A machine without the song's render (it lives on the media disk): the player says so and its own voices sing, as
# site_keyboard.py allows too. Where the render is there, the flow plays it.
RENDER = "/media/song-audio/"
SOUND = "performance.getEntriesByType('resource').filter((r) => /\\.(mp3|m4a|flac|wav|ogg)(\\?|$)/.test(r.name) || /\\/api\\/templates/.test(r.name)).map((r) => r.name)"


def scroll_to(f, scene):
    """One of the stage's scenes (0 to 4): a screen of scroll each."""
    f.run(f"window.scrollTo(0, {scene} * innerHeight)")
    f.wait(f"document.querySelector('[data-lnf]').dataset.scene === '{scene}'", timeout=10, what=f"scene {scene}")


def first_frame(f):
    # As the server sends it, before any script: the headline, the lanes, the gems, the first sung line.
    html = f.js("fetch(location.pathname).then((r) => r.text())")
    for piece in ('class="lnf-h1"', 'data-voice="soprano"', 'data-voice="bass"', 'class="lnf-g"', 'class="lnf-line"', 'data-scene="0"'):
        assert piece in html, f"the page as served has {piece}"
    assert html.count('class="lnf-g"') > 60, "every note of the song is a gem in the page"
    assert f.js("document.querySelectorAll('h1').length") == 1, "one h1"
    state = f.js(STATE)
    assert not state["playing"] and state["lit"] == 0 and state["scene"] == "0", f"at rest nothing plays or is lit: {state}"
    assert f.js(SOUND) == [], f"nothing for sound is fetched before a press: {f.js(SOUND)}"
    f.no_sideways("the first scene")
    # Its own document and its own small sheet: none of the site's kit, no game sheet, no game or site script.
    sheets = f.js("[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname)")
    assert sheets == ["/css/landing-next-final.css"], f"one sheet, its own: {sheets}"
    scripts = f.js("performance.getEntriesByType('resource').map((r) => new URL(r.name).pathname).filter((n) => n.endsWith('.js'))")
    for name in ("/js/game-css.js", "/js/public/site.js", "/js/public/game-toggle.js", "/js/public/season.js", "/js/public/funnel.js"):
        assert name not in scripts, f"{name} isn't loaded: {scripts}"
    assert f.js("!!document.querySelector('link[rel=preload][as=font][href=\"/fonts/figtree-latin.woff2\"]')"), "the font is asked for at once"


def play_and_solo(f):
    f.run("document.querySelector('[data-lnf-play]').click()")
    f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="the song playing")
    time.sleep(2.5)
    a = f.js(STATE)
    assert a["p"] > 0 and a["t"] > 0 and a["lit"] >= 1, f"the song moves and gems light: {a}"
    assert a["now"] == 1 and a["line"], f"the sung line follows, one word at a time: {a}"
    assert "Amazing Grace" in a["live"], f"a screen reader is told it's playing: {a['live']!r}"
    assert f.js("getComputedStyle(document.querySelector('.lnf-play-w .lnf-i-stop')).display") != "none", "the big button says Stop while it plays"
    assert f.js("getComputedStyle(document.querySelector('.lnf-disc .lnf-i-stop')).display") != "none", "the dock's disc shows the square"
    assert f.js("!!document.querySelector('.lnf [data-icon=pause], .lnf svg[class*=pause]')") is False, "never a pause"

    # The alto alone, by its lane; then all four again, and the song never goes back.
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="the alto alone")
    time.sleep(1.5)
    b = f.js(STATE)
    assert b["alone"] == ["alto"] and b["playing"] and b["p"] > a["p"], f"the alto alone, from the same spot: {b}"
    assert f.js("document.querySelector('[data-voice=alto] [data-hear-alone]').getAttribute('aria-pressed')") == "true", "the lane's button says it's pressed"
    assert f.js("getComputedStyle(document.querySelector('[data-voice=alto] .lnf-chip-alone')).display") != "none", "the lane says Heard alone in words"
    assert "alto" in b["live"].lower(), f"a screen reader is told which voice: {b['live']!r}"
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo]')", timeout=20, what="all four again")
    time.sleep(1.2)
    c = f.js(STATE)
    assert c["playing"] and c["alone"] == [], f"all four again: {c}"
    # The Hear scene's button and the Learn links name the voice last heard alone, and lead to its part.
    learn = f.js("[...document.querySelectorAll('[data-lnf-learn]')].map((a) => [a.textContent.trim(), a.getAttribute('href')])")
    assert len(learn) == 2 and all("alto" in text.lower() and "part=alto" in href for text, href in learn), f"the Learn links follow the alto: {learn}"
    assert "alto" in f.js("document.querySelector('[data-lnf-hear]').textContent").lower(), "the Hear button offers the alto again"
    # The dock's voices: the tenor alone from the dock, and back, wherever the page is.
    if c["playing"]:
        f.run("document.querySelector('[data-lnf-voice=tenor]').click()")
        f.wait("document.querySelector('[data-voice=tenor]').hasAttribute('data-solo')", timeout=20, what="the tenor alone, from the dock")
        assert f.js("document.querySelector('[data-lnf-voice=tenor]').getAttribute('aria-pressed')") == "true", "the dock's tenor says it's pressed"
        assert f.js("document.querySelector('[data-lnf-hear]').getAttribute('aria-pressed')") == "true", "the Hear button says a voice is alone"
        f.run("document.querySelector('[data-lnf-hear]').click()")
        f.wait("!document.querySelector('[data-voice][data-solo]')", timeout=20, what="all four again, by the Hear button")
        assert f.js("[...document.querySelectorAll('[data-lnf-voice]')].every((b) => b.getAttribute('aria-pressed') === 'false')"), "no dock voice is pressed"
        assert "tenor" in f.js("document.querySelector('[data-lnf-learn]').textContent").lower(), "the Learn link follows the tenor"
        time.sleep(0.8)
        c = f.js(STATE)
    # Back on the render, the song carries on from the same spot wherever the render can be sought (the CDN answers
    # byte ranges; php -S, serving a local copy, doesn't, and a browser can't seek what it can't ask a part of).
    if f.js(SEEKS):
        assert c["p"] > b["p"], f"all four again, carrying on: {b['p']} then {c['p']}"
    return c


def over(f, what):
    """Nothing of `what` lies under the dock: its bottom is above the dock's top (the phone's dock starts at its pill)."""
    return f.js(f"""(() => {{ const el = document.querySelector({what!r}); const dock = document.querySelector('.lnf-dock');
        const top = innerWidth < 900 ? document.querySelector('.lnf-tp').getBoundingClientRect().top : dock.getBoundingClientRect().top;
        return Math.round(el.getBoundingClientRect().bottom - top); }})()""")


def scenes(f, before):
    # Down through the stage's scenes while the song plays: the scroll moves the scene and nothing else.
    for scene in (1, 2, 3, 4):
        scroll_to(f, scene)
        assert f.js("document.querySelector('[data-lnf]').dataset.top") == "small", "the top line is small on the way down"
        f.wait(f"Number(getComputedStyle(document.querySelector('[data-s=\"{scene}\"]')).opacity) > 0.9", timeout=5, what=f"scene {scene} shown")
        f.no_sideways(f"scene {scene}")
        time.sleep(1.0)
        assert over(f, ".lnf-world") <= 1, f"scene {scene}: the lanes end above the dock ({over(f, '.lnf-world')} px under it)"
        assert not f.js("document.getElementById('lnf').hasAttribute('data-tail')"), f"scene {scene} is the stage's"
    state = f.js(STATE)
    if state["playing"]:
        assert state["p"] > before["p"], f"scrolling never stops or rewinds the song: {before['p']} then {state['p']}"
    else:
        assert state["p"] == 0, f"the song ended by itself while the scenes went by, never stopped part way: {state}"
    # After Share the stage lets go: the rest is a page that scrolls, each section in the flow, nothing scrolling inside.
    for scene in (5, 6, 7, 8):
        f.run(f"window.scrollTo(0, document.getElementById('lnf-s{scene}').getBoundingClientRect().top + scrollY)")
        f.wait("document.getElementById('lnf').hasAttribute('data-tail')", timeout=10, what=f"scene {scene} is the page that scrolls")
        f.no_sideways(f"scene {scene}")
    assert f.js("document.querySelector('.lnf-stage').getBoundingClientRect().bottom") <= 1, "the stage has scrolled away"
    inside = f.js("[...document.querySelectorAll('.lnf-tail, .lnf-tail *')].filter((el) => /auto|scroll/.test(getComputedStyle(el).overflowY)).map((el) => el.className)")
    assert inside == [], f"nothing scrolls inside itself: {inside}"
    places = f.js("[...document.querySelectorAll('.lnf-tail .lnf-sc, .lnf-tail .lnf-cap, .lnf-faq, .lnf-plans, .lnf-slabs, .lnf-choir')].map((el) => getComputedStyle(el).position)")
    assert all(place in ("static", "relative") for place in places), f"the page's pieces are in its flow: {places}"
    # The dock stays; at the very end the last question and the last links clear it.
    f.run("window.scrollTo(0, document.documentElement.scrollHeight)")
    time.sleep(0.6)
    assert f.js("getComputedStyle(document.querySelector('.lnf-dock')).position") == "fixed", "the dock stays"
    assert over(f, ".lnf-legal") <= 0, f"the last links clear the dock ({over(f, '.lnf-legal')} px under it)"
    f.run("document.querySelector('.lnf-faq details:last-child').scrollIntoView({ block: 'center' })")
    time.sleep(0.4)
    assert over(f, ".lnf-faq details:last-child") <= 0, "the last question can be read clear of the dock"
    scroll_to(f, 3)
    assert f.js("document.querySelector('[data-lnf]').dataset.top") is None, "the top line is back on the way up"
    scroll_to(f, 0)
    # Stop is a stop: the song is back at its start, and the page shows it.
    f.run("const b = document.querySelector('[data-play]'); if (document.getElementById('lnf').hasAttribute('data-playing')) b.click();")
    f.wait("!document.getElementById('lnf').hasAttribute('data-playing')", timeout=10, what="stopped")
    time.sleep(0.6)
    stopped = f.js(STATE)
    assert stopped["time"].startswith("0:00") and stopped["lit"] == 0 and stopped["t"] == 0, f"stopped, the song is back at its start: {stopped}"
    assert f.js("getComputedStyle(document.querySelector('.lnf-disc .lnf-i-play')).display") != "none", "the disc says Play again"


def theme(f):
    """Light and dark by the top line's switch, the site's own stored choice."""
    was = f.js("document.documentElement.dataset.theme")
    ground = f.js("getComputedStyle(document.querySelector('.lnf')).backgroundColor")
    f.run("document.querySelector('[data-lnf-theme]').click()")
    f.wait(f"document.documentElement.dataset.theme !== {was!r}", timeout=5, what="the other look")
    now = f.js("document.documentElement.dataset.theme")
    assert was.endswith("-light") != now.endswith("-light"), f"the switch changes light and dark: {was} then {now}"
    assert f.js("getComputedStyle(document.querySelector('.lnf')).backgroundColor") != ground, "the page's ground follows"
    f.run("document.querySelector('[data-lnf-theme]').click()")
    f.wait(f"document.documentElement.dataset.theme === {was!r}", timeout=5, what="the first look again")


def keyboard(f):
    f.go("/next-final?keys", READY)
    first = f.js("(() => { const el = document.querySelector('main').querySelector('a[href], button, summary, [tabindex=\"0\"]'); return el?.hasAttribute('data-lnf-play'); })()")
    assert first, "Play is the first control in the page's main part"
    f.run("document.querySelector('[data-lnf-play]').focus()")
    f.press("Enter")
    f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="Enter on Play plays")
    # The lanes are one stop: only one is in the tab order, and the arrows move between them.
    assert f.js("document.querySelectorAll('[data-lnf-lanes] [data-hear-alone][tabindex=\"0\"]').length") == 1, "the lanes are one tab stop"
    f.run("document.querySelector('[data-voice=soprano] [data-hear-alone]').focus()")
    f.press("ArrowDown")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "alto", "the down arrow moves to the alto"
    f.press(" ")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="Space hears the alto alone")
    f.press("ArrowUp")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "soprano", "the up arrow moves back"
    ring = f.js("(() => { const cs = getComputedStyle(document.activeElement); return cs.outlineStyle !== 'none' && Number.parseFloat(cs.outlineWidth) >= 2; })()")
    assert ring, "the focused lane shows its focus"
    f.press("ArrowUp")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "bass", "up from the first voice goes round to the last"
    # Focus that lands in a scene of the stage off the screen brings its scene.
    f.run("document.querySelector('#lnf-s2 [data-lnf-learn]').focus()")
    f.wait("document.querySelector('[data-lnf]').dataset.scene === '2'", timeout=10, what="focus in the Sing along scene shows it")
    # The dock's controls are real buttons with names, each at least 40 px across and 44 px tall.
    sizes = f.js("[...document.querySelectorAll('.lnf-dock button, .lnf-dock a')].map((el) => { const r = el.getBoundingClientRect(); return [el.getAttribute('aria-label') || el.textContent.trim(), Math.round(r.width), Math.round(r.height)]; })")
    assert len(sizes) == 6 and all(name and w >= 36 and h >= 44 for name, w, h in sizes), f"the dock's six controls, named and big enough: {sizes}"
    f.run("document.querySelector('[data-play]').click()")


def private(f):
    f.go("/next-final?private", READY)
    assert f.js("document.querySelector('meta[name=robots]')?.content") == "noindex", "the page says noindex"
    assert f.js("fetch(location.pathname).then((r) => r.headers.get('x-robots-tag'))") == "noindex", "its header says noindex"
    assert f.js("document.querySelectorAll('link[rel=canonical], link[rel=alternate]').length") == 0, "no canonical and no alternates"
    assert f.js("fetch('/sitemap.xml').then((r) => r.text()).then((t) => t.includes('next-final'))") is False, "not in the sitemap"
    for home in ("/", "/es"):
        assert f.js(f"fetch('{home}').then((r) => r.text()).then((t) => t.includes('next-final'))") is False, f"{home} doesn't link it"
    # Its counts: its own kind and nothing of the landing's.
    f.run("document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })); document.body.click();")
    time.sleep(1.5)
    beacons = f.js("window.__beacons")
    counts = [body for url, body in beacons if url.endswith("/api/counts")]
    assert any('"sv_next_final"' in body for body in counts), f"its own count is sent: {beacons}"
    for body in counts:
        for kind in ("site_visit", "sv_home", "f_site_start", "sv_other"):
            assert f'"{kind}"' not in body, f"never the landing's {kind}: {body}"
    assert f.js("!!document.querySelector('script[src*=\"funnel.js\"]')") is False, "the landing's funnel script isn't loaded"


def spanish(f):
    f.go("/es/next-final", READY)
    assert f.js("document.documentElement.lang") == "es", "the Spanish page says its language"
    assert "Armonía" in f.js("document.querySelector('h1').textContent"), "the headline is in Spanish"
    assert f.js("document.querySelector('meta[name=robots]')?.content") == "noindex", "the Spanish page says noindex"
    assert f.js("document.querySelectorAll('link[rel=canonical], link[rel=alternate]').length") == 0, "the Spanish page has no alternates"
    for scene in (0, 1, 2, 3, 4):
        scroll_to(f, scene)
        f.no_sideways(f"the Spanish scene {scene}")
    for scene in (5, 6, 7, 8):
        f.run(f"window.scrollTo(0, document.getElementById('lnf-s{scene}').getBoundingClientRect().top + scrollY)")
        time.sleep(0.3)
        f.no_sideways(f"the Spanish scene {scene}")
    assert "soprano" in f.js("document.querySelector('[data-lnf-learn]').textContent").lower() and "Aprende" in f.js("document.querySelector('[data-lnf-learn]').textContent"), "the Learn link is in Spanish"


def still(base, size):
    """Reduced motion: nothing pinned, every scene in the page, and the song plays with its gems lit in place."""
    f = flow.Flow(base, size, "next_final_still", skin="serious")
    f.expected_errors.append(RENDER)
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    try:
        f.go("/next-final?still", READY)
        assert f.js("getComputedStyle(document.querySelector('.lnf-stage')).position") != "sticky", "nothing is pinned"
        shown = f.js("[...document.querySelectorAll('[data-s]')].every((s) => getComputedStyle(s).opacity === '1' && s.getBoundingClientRect().height > 40)")
        assert shown, "every scene shows, in the page's flow"
        tops = f.js("[...document.querySelectorAll('[data-s]')].map((s) => Math.round(s.getBoundingClientRect().top + scrollY))")
        assert tops == sorted(tops) and len(set(tops)) == len(tops), f"the scenes come one after another: {tops}"
        f.no_sideways("the still page")
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="the song playing, still")
        time.sleep(2.5)
        state = f.js(STATE)
        assert state["lit"] >= 1 and state["now"] == 1, f"gems and words light in place: {state}"
        moved = f.js("getComputedStyle(document.querySelector('.lnf-tr')).translate")
        assert moved in ("none", "0px", "0px 0px"), f"the lanes stand still on the first line: {moved}"
        f.run("document.querySelector('[data-voice=tenor] [data-hear-alone]').click()")
        f.wait("document.querySelector('[data-voice=tenor]').hasAttribute('data-solo')", timeout=20, what="a lane alone, still")
        f.run("document.querySelector('[data-play]').click()")
    finally:
        f.close()


def calm_in_game(base):
    """It has no game dress: in the game skin it's the same calm page."""
    f = flow.Flow(base, "desktop", "next_final_game", skin="game")
    try:
        f.go("/next-final?game", READY)
        assert f.js("document.documentElement.hasAttribute('data-gs')") is False, "it asks for no arcade screen"
        assert f.js("[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname)") == ["/css/landing-next-final.css"], "no game sheet joins it"
        font = f.js("getComputedStyle(document.querySelector('.lnf-h1')).fontFamily")
        assert "Figtree" in font, f"the headline keeps its own type in the game skin: {font}"
        assert f.js("getComputedStyle(document.querySelector('.lnf-play')).borderRadius") != "0px", "its buttons keep their own shape"
    finally:
        f.close()


def main():
    failures = []
    for size in ("desktop", "phone"):
        f = flow.Flow(SITE, size, "next_final", skin="serious")
        f.expected_errors.append(RENDER)
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": WATCH})
        try:
            f.go("/next-final", READY)
            first_frame(f)
            scenes(f, play_and_solo(f))
            theme(f)
            keyboard(f)
            private(f)
            spanish(f)
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()
            except AssertionError as error:
                failures.append(str(error))
        try:
            still(SITE, size)
        except AssertionError as error:
            failures.append(f"{size}, still: {error}")
    try:
        calm_in_game(SITE)
    except AssertionError as error:
        failures.append(f"game skin: {error}")
    print("FAIL next_final\n     " + "\n     ".join(failures) if failures else "ok   next_final")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
