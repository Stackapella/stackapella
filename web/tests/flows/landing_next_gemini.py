"""The landing's next version, the gemini variant (/next-gemini; docs/landing-next-gemini-plan.md), as a visitor meets
it, at a phone and a desktop, in English and Spanish:

- kept apart: the X-Robots-Tag header and the robots meta say noindex, no canonical, no alternates (hreflang), not in
  the sitemap, not linked from the landing, and its one count is its own kind (sv_next_gemini), never a site section's;
- the first frame is the server's: every note of the song has its gem before any script runs, the headline is there;
- no sound before a press; Play starts the song on the audio clock (the playhead moves);
- a lane plays its part alone, said in words and to a screen reader, and again brings every part back, the song
  carrying on from the same spot;
- scrolling through the six scenes moves the stage (data-scene) and never stops or seeks the song; the bar shrinks on the
  way down and comes back on the way up; Stop in the bar stops it from any scene;
- the keyboard: the lanes are one group (arrows between them, Enter plays one alone), Play is a button;
- reduced motion: no animation or transition, and Play and the lanes work the same;
- the page and its own files weigh no more than a site page may (650 KB), and no audio is fetched before a press;
- the calm skin whatever the visitor's skin (no game sheet), no sideways scroll, no console error.

It runs against the server's own site, at SITE (default http://127.0.0.1:8412, `php -S 127.0.0.1:8412 tool/serve.php`
from server/):

    python3 -I tests/flows/landing_next_gemini.py
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
FILM = "document.querySelector('[data-lngm-film]')"
OWN_KB = 650  # as site_integration allows a page and its own files
PLAY = f"{FILM}.querySelector('[data-play]')"
# Every beacon the page sends, kept for the counts check (set before any page script).
BEACONS = """window.__beacons = []; const send = navigator.sendBeacon.bind(navigator);
    navigator.sendBeacon = (url, body) => { Promise.resolve(body?.text ? body.text() : String(body ?? '')).then((text) => window.__beacons.push([String(url), text])); return send(url, body); };"""


def p(f):
    return float(f.js(f"{FILM}.style.getPropertyValue('--p') || '0'"))


def moving(f, what):
    before = p(f)
    time.sleep(1.0)
    after = p(f)
    assert after > before, f"{what}: the playhead didn't move ({before} then {after})"
    return after


def apart(f, path, language):
    """Never indexed, no canonical, no alternates, out of the sitemap, linked from nowhere on the landing."""
    header = f.js(f"fetch('{path}').then((r) => r.headers.get('x-robots-tag') || '')")
    assert "noindex" in header, f"{path}: the X-Robots-Tag header says noindex, not {header!r}"
    robots = f.js("document.querySelector('meta[name=robots]')?.content || ''")
    assert "noindex" in robots, f"{path}: the robots meta says noindex, not {robots!r}"
    assert f.js("document.querySelectorAll('link[rel=canonical], link[rel=alternate]').length") == 0, f"{path}: no canonical and no hreflang alternates"
    assert f.js("document.documentElement.lang") == language, f"{path}: the page is in {language}"
    sitemap = f.js("fetch('/sitemap.xml').then((r) => r.text())")
    assert "<loc>" in sitemap and "next-gemini" not in sitemap, "the sitemap never lists the page"
    home = f.js("fetch('/').then((r) => r.text())")
    assert "next-gemini" not in home, "the landing never links the page"


def first_frame(f):
    """The server's own first frame: the headline and a gem for every note, placed without a script."""
    notes = f.js(f"JSON.parse({FILM}.querySelector('[data-song]').textContent).parts.reduce((n, part) => n + part.notes.length, 0)")
    gems = f.js("document.querySelectorAll('.lngm-gem[data-beat][data-length]').length")
    assert notes > 0 and gems == notes, f"every note has its gem: {gems} gems for {notes} notes"
    placed = f.js("[...document.querySelectorAll('.lngm-gem')].every((g) => g.style.getPropertyValue('--b') !== '' && g.style.getPropertyValue('--y') !== '' && g.getBoundingClientRect().width > 0)")
    assert placed, "each gem is placed by its beat and pitch"
    assert f.js("document.querySelector('#lngm-s0 h1').textContent.trim().length") > 5, "the headline is in the page"
    assert f.js("document.querySelectorAll('[data-lngm-lane] [data-hear-alone]').length") == 4, "four lanes, each a button"
    assert f.js("[...document.querySelectorAll('.lngm-mine')].every((a) => /[?&]part=/.test(a.href) && /[?&]song=/.test(a.href))"), "each lane's I sing this leads to that part in the app"


def weight(f):
    """The page and its own files, before any press: inside the site's budget for a page (site_integration's OWN_KB)."""
    time.sleep(2)  # the player's modules and the worker, fetched as the stage comes into view
    kb = f.js("""(() => { const nav = performance.getEntriesByType('navigation')[0];
        return Math.round((performance.getEntriesByType('resource').reduce((n, e) => n + (e.transferSize || e.encodedBodySize), 0) + nav.transferSize) / 1024); })()""")
    assert kb <= OWN_KB, f"the page weighs {kb} KB before a press, over its {OWN_KB} KB"
    audio = f.js("performance.getEntriesByType('resource').filter((e) => /\\.(mp3|m4a|flac|wav)(\\?|$)/.test(e.name)).length")
    assert audio == 0, "no audio is fetched before a press"


def calm(f):
    """The calm skin whatever the visitor's (this browser's is game): no game sheet, none of the site's arcade."""
    assert f.js("!document.querySelector('link[href*=\"site-game\"], link[href*=\"-game.css\"], meta[name=game-css]')"), "no game sheet on the page"
    assert f.js("!document.body.classList.contains('s-arcade')"), "none of the site's arcade screen"


def counted(f):
    """Its one count is its own kind; nothing under a site section's or the landing's."""
    kinds = f.js("window.__beacons.filter(([url]) => url.includes('/api/counts')).flatMap(([, text]) => { try { return JSON.parse(text).events.map((e) => e.kind); } catch (e) { return []; } })")
    assert not [k for k in kinds if k.startswith("sv_") and k != "sv_next_gemini"] and "site_visit" not in kinds, f"no site section's count from this page: {kinds}"
    assert f.js("!document.querySelector('[data-site-visit]')"), "the site's own visit count isn't on the page"
    if f.js("!!document.querySelector('[data-lngm-visit]')"):
        assert kinds.count("sv_next_gemini") == 1, f"one sv_next_gemini for the page load: {kinds}"
    else:
        assert "sv_next_gemini" not in kinds, "counts are off here: nothing sent"


def plays(f, label):
    assert f.js(f"!{FILM}.hasAttribute('data-playing') && !{FILM}.hasAttribute('data-loading')"), f"{label}: nothing plays before a press"
    assert p(f) == 0, f"{label}: the playhead stands still before a press"
    f.run(f"{PLAY}.click()")
    assert f.js(f"{FILM}.hasAttribute('data-loading') || {FILM}.hasAttribute('data-playing')"), f"{label}: the press answers at once"
    f.wait(f"{FILM}.hasAttribute('data-playing')", timeout=30, what=f"{label}: the song playing")
    assert f.js(f"{PLAY}.getAttribute('aria-label') === {PLAY}.dataset.labelStop"), f"{label}: Play now says Stop"
    moving(f, f"{label}: playing")
    f.wait("document.querySelector('.lngm-words [data-lit]')", timeout=10, what=f"{label}: a word lit as it's sung")


def solo(f, label):
    """A lane alone, then all of them again, the song carrying on."""
    lane = "document.querySelector('[data-lngm-lane=\"1\"]')"
    before = p(f)
    f.run(f"{lane}.querySelector('[data-hear-alone]').click()")
    f.wait(f"{lane}.hasAttribute('data-solo')", timeout=10, what=f"{label}: the lane heard alone")
    assert f.js("[...document.querySelectorAll('[data-lngm-lane]')].filter((l) => l.hasAttribute('data-off')).length") == 3, f"{label}: the other three step back"
    assert f.js(f"{lane}.querySelector('[data-hear-alone]').getAttribute('aria-pressed')") == "true", f"{label}: the lane's button is pressed"
    assert f.js(f"getComputedStyle({lane}.querySelector('.lngm-chip')).display") != "none", f"{label}: the lane says it plays alone in words"
    assert f.js(f"{lane}.hasAttribute('data-focus') && {FILM}.style.getPropertyValue('--f') === '1'"), f"{label}: the lane is the stage's focus"
    said = f.js("document.querySelector('[data-lngm-say]').textContent")
    assert said.strip(), f"{label}: a screen reader hears which part plays alone"
    learn = f.js("document.querySelector('[data-lngm-learn-link]').href")
    assert learn == f.js(f"{lane}.querySelector('.lngm-mine').href"), f"{label}: Learn names the part heard alone"
    time.sleep(1.5)  # the voices apart take over from the render
    assert f.js(f"{FILM}.hasAttribute('data-playing')"), f"{label}: still playing, alone"
    after = moving(f, f"{label}: alone")
    assert after > before, f"{label}: the song carried on from the same spot ({before} to {after})"
    f.run(f"{lane}.querySelector('[data-hear-alone]').click()")
    f.wait("![...document.querySelectorAll('[data-lngm-lane]')].some((l) => l.hasAttribute('data-off') || l.hasAttribute('data-solo'))", timeout=10, what=f"{label}: every part back")
    assert f.js("document.querySelector('[data-lngm-say]').textContent").strip() != said.strip(), f"{label}: and a screen reader hears that too"
    moving(f, f"{label}: all again")


def scrolls(f, label):
    """Through the six scenes while the song plays: the stage follows, the song never stops or jumps back."""
    last = p(f)
    for scene in range(1, 6):
        f.run(f"document.getElementById('lngm-s{scene}').scrollIntoView({{ behavior: 'instant' }})")
        f.wait(f"{FILM}.dataset.scene === '{scene}'", timeout=10, what=f"{label}: scene {scene} in view")
        assert f.js(f"document.querySelectorAll('[data-lngm-rail] a')[{scene}].getAttribute('aria-current')") == "true", f"{label}: the rail marks scene {scene}"
        if f.js(f"{FILM}.hasAttribute('data-playing')"):
            now = moving(f, f"{label}: scene {scene}")
            assert now > last, f"{label}: scrolling to scene {scene} never took the song back ({last} to {now})"
            last = now
        f.no_sideways(f"{label}, scene {scene}")
        f.shot(f"{label}-scene{scene}")
    assert f.js("document.querySelector('[data-lngm-bar]').hasAttribute('data-small')"), f"{label}: the bar is small on the way down"
    if f.js(f"{FILM}.hasAttribute('data-playing')"):
        assert f.js("!document.querySelector('[data-lngm-stop]').hidden"), f"{label}: Stop is in the bar while the song plays"
        f.run("document.querySelector('[data-lngm-stop]').click()")
        f.wait(f"!{FILM}.hasAttribute('data-playing')", timeout=10, what=f"{label}: stopped from the bar")
        assert f.js("document.querySelector('[data-lngm-stop]').hidden"), f"{label}: and Stop leaves the bar"
    f.run("document.getElementById('lngm-s3').scrollIntoView({ behavior: 'instant' })")
    time.sleep(0.4)
    f.run("window.scrollBy({ top: -200, behavior: 'instant' })")
    f.wait("!document.querySelector('[data-lngm-bar]').hasAttribute('data-small')", timeout=10, what=f"{label}: the bar whole again on the way up")
    f.run("window.scrollTo({ top: 0, behavior: 'instant' })")
    f.wait(f"{FILM}.dataset.scene === '0'", timeout=10, what=f"{label}: back at the first scene")


def keyboard(f, label):
    """The lanes are one group for the keyboard; Play is a button that Enter presses."""
    f.run("document.querySelector('[data-lngm-lane=\"0\"] [data-hear-alone]').focus()")
    f.press("ArrowDown")
    f.wait("document.activeElement === document.querySelector('[data-lngm-lane=\"1\"] [data-hear-alone]')", timeout=5, what=f"{label}: the down arrow moves to the next lane")
    assert f.js("[...document.querySelectorAll('[data-lngm-lane] [data-hear-alone]')].filter((b) => b.tabIndex === 0).length") == 1, f"{label}: the lanes are one stop for Tab"
    f.press("ArrowUp")
    f.press("ArrowUp")
    f.wait("document.activeElement === document.querySelector('[data-lngm-lane=\"3\"] [data-hear-alone]')", timeout=5, what=f"{label}: the up arrow wraps to the last lane")
    f.press("Enter")
    f.wait("document.querySelector('[data-lngm-lane=\"3\"]').hasAttribute('data-solo')", timeout=10, what=f"{label}: Enter plays the focused lane alone")
    f.wait(f"{FILM}.hasAttribute('data-playing')", timeout=30, what=f"{label}: and the song plays")
    ring = f.js("getComputedStyle(document.activeElement).outlineStyle")
    assert ring != "none", f"{label}: the focused lane shows a ring"
    f.press("Enter")
    f.wait("!document.querySelector('[data-lngm-lane=\"3\"]').hasAttribute('data-solo')", timeout=10, what=f"{label}: Enter again brings every part back")
    f.run(f"{PLAY}.focus()")
    f.press("Enter")
    f.wait(f"!{FILM}.hasAttribute('data-playing')", timeout=10, what=f"{label}: Enter on Play stops the song")


def still(f, label):
    """Reduced motion: nothing animates or eases, and the page plays the same."""
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    f.go("/next-gemini?still", f"{FILM}?.hasAttribute('data-ready')")
    moves = f.js("""[...document.querySelectorAll('.lngm *')].filter((e) => { const cs = getComputedStyle(e);
        return cs.animationName !== 'none' || cs.transitionDuration.split(',').some((d) => parseFloat(d) > 0); }).length""")
    assert moves == 0, f"{label}: {moves} elements still animate or ease with reduced motion"
    assert f.js("getComputedStyle(document.querySelector('.lngm-play'), '::after').animationName") == "none", f"{label}: Play's ring is still"
    plays(f, label)
    solo(f, label)
    f.run(f"{PLAY}.click()")
    f.wait(f"!{FILM}.hasAttribute('data-playing')", timeout=10, what=f"{label}: stopped")
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "no-preference"}]})


def main():
    failures = []
    for size in ("desktop", "phone"):
        # The visitor's skin here is game (the flows' default): the page is calm all the same.
        f = flow.Flow(SITE, size, "landing_next_gemini")
        # Where the song's render isn't on this machine's media disk, the voices sing it (the player's own fallback).
        f.expected_errors += ["/media/", "net::ERR_"]
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": BEACONS})
        try:
            f.go("/next-gemini", f"{FILM}?.hasAttribute('data-ready') && document.fonts.status === 'loaded'")
            weight(f)  # first, before this flow's own fetches (the sitemap, the landing) are among the page's
            apart(f, "/next-gemini", "en")
            first_frame(f)
            calm(f)
            f.no_sideways("the first scene")
            f.shot("scene0")
            plays(f, "en")
            solo(f, "en")
            scrolls(f, "en")
            keyboard(f, "en")
            counted(f)
            still(f, "reduced motion")
            f.go("/es/next-gemini", f"{FILM}?.hasAttribute('data-ready')")
            apart(f, "/es/next-gemini", "es")
            first_frame(f)
            assert f.js("document.querySelector('#lngm-s0 h1').textContent") != "", "the Spanish page has its headline"
            assert "Armonía" in f.js("document.querySelector('#lngm-s0 h1').textContent"), "the Spanish page is in Spanish"
            assert f.js("document.querySelector('.lngm-nav a').getAttribute('href').startsWith('/es/')"), "the Spanish page's links stay in Spanish"
            f.no_sideways("the Spanish page")
            plays(f, "es")
            f.run(f"{PLAY}.click()")
            f.shot("es")
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()  # raises on any console error the page logged
            except AssertionError as error:
                failures.append(str(error))
    print("FAIL landing_next_gemini\n     " + "\n     ".join(failures) if failures else "ok   landing_next_gemini")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
