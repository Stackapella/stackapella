"""A song page's score (public/_song-score.blade.php, js/public/score.js, js/engrave.js; docs/song-sheet-music-plan.md;
Hillel's design, 2026-11-16): the hero's card holds the engraved score from the notes in the page (nothing is read
from the API to show it); a wide screen shows one system and turns with the music, a phone every system; Play lights
each sounding note in its part's colour; a staff's letter hears that part alone and quiets the other staves; the part
picker at left chooses the part, tints its staff and Learn follows it; "Full score" opens the whole score under the
hero; and the downloads open the app's sheet music screen. Nothing shifts as the score arrives. A round is its tune on
one staff with its entries numbered, lit where each voice has got to. Needs the real server."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

ROOT = "document.querySelector('#song-mixer')"
VIEW = "document.querySelector('[data-score-view]')"
SYSTEMS = "[...document.querySelectorAll('[data-score-systems] svg')]"
SHOWN = SYSTEMS + ".filter((s) => !s.hasAttribute('hidden') && s.getClientRects().length)"


def journey(f):
    f.expected_errors.append("/media/song-audio/")  # the recording's host isn't run locally: the voices are synthesized
    wide = f.size == "desktop"
    # Every shift of the layout, the page's fonts arriving included (game mode's VT323 has a fallback matched to its
    # width since 2026-11-17): nothing moves as the score arrives, its scripts at idle after the load.
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": "window.shifts = 0; new PerformanceObserver((list) => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.shifts += e.value; }).observe({ type: 'layout-shift', buffered: true });"})
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": "window.reads = []; const was = window.fetch; window.fetch = (...a) => { window.reads.push(String(a[0]?.url ?? a[0])); return was(...a); };"})
    f.go("/songs/amazing-grace", f"{ROOT}?.hasAttribute('data-ready')")
    f.wait(f"{VIEW}.hasAttribute('data-engraved')", what="the engraved score")
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    assert not [u for u in json.loads(f.js("JSON.stringify(window.reads)")) if "/api/templates" in u], "showing the score reads nothing from the API"
    assert f.js("window.shifts") < 0.02, f"the score arriving shifts the layout: {f.js('window.shifts')}"

    count = f.js(f"{SYSTEMS}.length")
    shown = f.js(f"{SHOWN}.length")
    assert count >= 4 and shown == (1 if wide else count), f"a wide card shows one system, a phone every one: {shown} of {count}"
    first = f"{SHOWN}[0]"
    assert f.js(f"{first}.querySelectorAll('[data-staff]').length") == 4, "four staves"
    assert f.js(f"{first}.querySelectorAll('use[href=\"#eg-gClef\"], use[href=\"#eg-gClef8vb\"], use[href=\"#eg-fClef\"]').length") == 4, "a clef a staff, drawn from the music signs"
    assert f.js("!!document.getElementById('eg-noteheadBlack')"), "the signs are on the page"
    assert f.js(f"[...{first}.querySelectorAll('.eg-word')].some((w) => w.textContent === 'grace,')"), "the words under the notes"
    # Each staff's letter sits on its staff, and is big enough to tap.
    places = json.loads(f.js("""JSON.stringify([...document.querySelectorAll('[data-score-view] [data-voice]')].map((v) => { const b = v.querySelector('button').getBoundingClientRect();
        const staff = document.querySelector('[data-score-systems] svg:not([hidden]) [data-staff="' + v.dataset.voice + '"] rect').getBoundingClientRect();
        return { voice: v.dataset.voice, off: Math.round(Math.abs((b.top + b.bottom) / 2 - (staff.top + 20 * staff.width / (staff.width || 1)))), size: Math.round(b.width), top: Math.round(b.top), line: Math.round(staff.top) }; }))"""))
    for place in places:
        assert place["size"] >= 28 and place["line"] - 40 < place["top"] < place["line"] + 40, f"a letter away from its staff: {place}"
    assert f.js(f"getComputedStyle({VIEW}.querySelector('[data-voice=soprano]')).backgroundColor") != "rgba(0, 0, 0, 0)", "the chosen part's staff is tinted"
    f.no_sideways("the song page")
    f.shot("score")

    # Play: the notes light as they sound, each a note of the part it's drawn in; the card turns with the music.
    f.run(f"{ROOT}.querySelector('[data-play]').click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=40, what="playing")
    f.wait("document.querySelectorAll('[data-score-systems] [data-lit]').length > 0", timeout=20, what="a lit note")
    lit = json.loads(f.js("JSON.stringify([...document.querySelectorAll('[data-score-systems] .eg-note[data-lit]')].map((n) => ({ part: n.dataset.part, staff: n.closest('[data-staff]').dataset.staff, ink: getComputedStyle(n).fill, plain: getComputedStyle(n.closest('svg')).color })))"))
    assert lit and all(n["part"] == n["staff"] and n["ink"] != n["plain"] for n in lit), f"a lit note takes its part's colour: {lit}"
    if wide:
        f.wait("document.querySelector('[data-score-systems] svg:not([hidden])').dataset.system !== '0'", timeout=30, what="the card turning to the next system")
        assert f.js("document.querySelector('[data-score-systems] svg:not([hidden]) [data-lit]') !== null"), "the system shown is the one sounding"

    # A staff's letter: that part alone, said in the card, the other staves quiet; again, all of them.
    f.run(f"{VIEW}.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    assert "alto" in f.js("document.querySelector('[data-score-hearing]').textContent").lower(), "the card says which part is heard"
    quiet = json.loads(f.js("JSON.stringify([...document.querySelectorAll('[data-score-systems] svg:not([hidden]) [data-staff]')].filter((g) => g.hasAttribute('data-quiet')).map((g) => g.dataset.staff))"))
    assert sorted(set(quiet)) == ["bass", "soprano", "tenor"], f"the other staves go quiet: {quiet}"
    assert f.js(f"{ROOT}.hasAttribute('data-playing')"), "hearing a part alone keeps the song playing"
    f.shot("alto-alone")
    f.run(f"{VIEW}.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    assert f.js("document.querySelectorAll('[data-score-systems] [data-quiet]').length") == 0, "every part again"

    # Stop: back to the start, nothing lit.
    f.run(f"{ROOT}.querySelector('[data-play]').click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", timeout=5, what="stopped")
    assert f.js("document.querySelectorAll('[data-score-systems] [data-lit]').length") == 0, "nothing lit once stopped"
    if wide:
        assert f.js("document.querySelector('[data-score-systems] svg:not([hidden])').dataset.system") == "0", "a stop returns the card to the start"
        # Back and on.
        f.run("document.querySelector('[data-score-next]').click()")
        assert f.js("document.querySelector('[data-score-systems] svg:not([hidden])').dataset.system") == "1", "on shows the next bars"
        assert f.js("document.querySelector('[data-score-prev]').disabled") is False, "and back is there"
        f.run("document.querySelector('[data-score-prev]').click()")

    # The part you sing: chosen at left, its staff tinted, Learn and the part's download follow.
    f.run("document.querySelector('[data-pick=tenor]').click()")
    assert f.js("document.querySelector('[data-pick=tenor]').getAttribute('aria-checked')") == "true", "the tenor is chosen"
    assert f.js("[...document.querySelectorAll('.s-score-voice.s-is-you')].map((v) => v.dataset.voice).join()") == "tenor", "its staff is the tinted one"
    assert all("tenor" in label.lower() for label in json.loads(f.js("JSON.stringify([...document.querySelectorAll('[data-learn-label]')].map((l) => l.textContent))"))), "Learn names the tenor"
    files = json.loads(f.js("JSON.stringify(Object.fromEntries([...document.querySelectorAll('[data-score-file]')].map((a) => [a.dataset.scoreFile, a.getAttribute('href')])))"))
    assert list(files) == ["pdf", "parts", "musicxml", "midi"], f"the four downloads: {files}"
    assert all("/t/amazing-grace/sheet?" in href and f"get={name}" in href for name, href in files.items()), f"each opens the app's sheet music: {files}"
    assert "part=tenor" in files["parts"], f"Parts is the chosen part's: {files['parts']}"
    assert "free with an account" in f.js("document.querySelector('.s-score-dl').textContent"), "the downloads say what they need"

    # The full score, under the hero (a wide screen; a phone already shows it all).
    if wide:
        f.run("document.querySelector('[data-score-full]').click()")
        f.wait("document.querySelectorAll('[data-score-full-systems] svg').length > 0", what="the full score")
        full = json.loads(f.js("JSON.stringify({ hidden: document.querySelector('[data-score-full-view]').hidden, systems: document.querySelectorAll('[data-score-full-systems] svg').length, names: [...document.querySelectorAll('[data-score-full-systems] svg')][0].textContent.includes('Soprano'), rights: document.querySelector('.s-score-rights').textContent, expanded: document.querySelector('[data-score-full]').getAttribute('aria-expanded') })"))
        assert not full["hidden"] and 2 <= full["systems"] < count and full["names"] and full["expanded"] == "true", f"the whole score, wider systems, its parts named: {full}"
        assert "CC BY-SA 4.0" in full["rights"] and "Stackapella" in full["rights"], f"its rights line: {full['rights']}"
        f.no_sideways("the full score")
        f.shot("full-score")
        f.run("document.querySelector('[data-score-full-close]').click()")
        assert f.js("document.querySelector('[data-score-full-view]').hidden") and f.js("document.activeElement === document.querySelector('[data-score-full]')"), "Close puts the reader back on the button"
    else:
        assert not f.js("document.querySelector('.s-score-foot').getClientRects().length"), "a phone has no pager: it shows every system"

    # A round (Hillel's choice, 2026-11-16): its tune on one staff, each voice's entry numbered, every letter beside that
    # staff; playing, the tune lights where each voice has got to.
    f.go("/songs/londons-burning", f"{ROOT}?.hasAttribute('data-ready')")
    f.wait(f"{VIEW}.hasAttribute('data-engraved')", what="the round's score")
    assert f.js(f"{SYSTEMS}.every((s) => s.querySelectorAll('[data-staff]').length === 1)"), "a round is on one staff"
    if wide:
        assert f.js(f"{SHOWN}.length") == 2, "a wide card shows a round's systems two at a time"
    assert f.js(f"{SYSTEMS}.flatMap((s) => [...s.querySelectorAll('.eg-entry text')].map((t) => t.textContent)).join()") == "1,2,3,4", "its entries numbered 1 to 4"
    bubbles = json.loads(f.js("""JSON.stringify([...document.querySelectorAll('[data-score-view] [data-voice] button')].map((b) => { const r = b.getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; }))"""))
    staff = json.loads(f.js("""JSON.stringify((() => { const svg = document.querySelector('[data-score-systems] svg:not([hidden])'); const r = svg.querySelector('[data-staff] rect').getBoundingClientRect(); const n = svg.querySelector('.eg-note').getBoundingClientRect(); return { top: r.top, left: n.left }; })())"""))
    assert len(bubbles) == 4 and all(b[2] - b[0] >= 28 for b in bubbles), f"four letters, each big enough to tap: {bubbles}"
    assert all(abs((b[1] + b[3]) / 2 - (staff["top"] + 16)) < 40 and b[2] <= staff["left"] for b in bubbles), f"the letters beside the staff, left of its notes: {bubbles} {staff}"
    assert not any(a[0] < b[2] and b[0] < a[2] and a[1] < b[3] and b[1] < a[3] for i, a in enumerate(bubbles) for b in bubbles[i + 1:]), f"no letter over another: {bubbles}"
    f.no_sideways("a round's page")
    f.run(f"{VIEW}.scrollIntoView({{block: 'center'}})")
    f.shot("round")
    f.run(f"{ROOT}.querySelector('[data-play]').click()")
    f.wait(f"{ROOT}.hasAttribute('data-playing')", timeout=40, what="the round playing")
    f.wait("new Set([...document.querySelectorAll('[data-score-systems] .eg-note[data-lit]')].flatMap((n) => n.dataset.lit.split(' '))).size >= 2", timeout=30, what="two voices lit at once, each where it has got to")
    f.shot("round-playing")
    f.run(f"{ROOT}.querySelector('[data-play]').click()")
    f.wait(f"!{ROOT}.hasAttribute('data-playing')", timeout=5, what="the round stopped")
    assert f.js("document.querySelectorAll('[data-score-systems] [data-lit]').length") == 0, "nothing lit once the round stops"


run("song_score", journey, sizes=("phone", "desktop"))
