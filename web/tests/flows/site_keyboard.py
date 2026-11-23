"""This week's public pieces by keyboard alone (the coordinator's accessibility pass, 2026-11-13): a song page's player
(a part's row heard alone and back with Enter, I sing this picked with Space and moved with the arrows, Learn following
it), the courses page's chips (Enter presses one, its state said), the pricing table's Only what's different switch
(Space turns it on) and a collection's player. Every stop shows where focus is, and nothing has a name that runs two
words together. Needs the real server (site pages)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# Where focus shows: an outline or a ring on the element, or on the visible part a hidden input drives.
SHOWN = """(() => { const e = document.activeElement; const seen = (el) => { if (!el) return false; const cs = getComputedStyle(el);
  return (cs.outlineStyle !== 'none' && parseFloat(cs.outlineWidth) > 0) || cs.boxShadow !== 'none'; };
  return seen(e) || seen(e.nextElementSibling) || seen(e.closest('label')) || seen(e.parentElement); })()"""


def tab_to(f, selector, limit=60):
    """Tabs until `selector` holds focus, and says it's visible there."""
    for _ in range(limit):
        f.press("Tab")
        if f.js(f"document.activeElement?.matches({selector!r}) ?? false"):
            assert f.js(SHOWN), f"focus isn't shown on {selector}"
            return
    raise AssertionError(f"never reached {selector} by Tab")


def look_menu(f):
    """Language and appearance (the header's one pill; Hillel, 2026-11-16: "combine the two buttons"): Enter opens it on
    the current choice, the arrows move, Tab stays inside, a look keeps it open, Escape closes it and focus goes back."""
    pill = "document.querySelector('header.s-top [data-look-menu] > summary')"
    f.run(f"{pill}.focus()")
    assert f.js(f"{pill}.getAttribute('aria-expanded')") == "false", "closed, and says so"
    name = f.js(f"{pill}.getAttribute('aria-label')")
    assert name.startswith("Language and appearance: "), f"the pill's name says its values: {name}"
    f.press("Enter")
    f.wait(f"document.querySelector('header.s-top [data-look-menu]').open && {pill}.getAttribute('aria-expanded') === 'true'", timeout=5, what="Enter opens it, and it says so")
    start = f.js("document.activeElement?.matches('[data-lang-choice][aria-current=true], [data-look-mode][aria-pressed=true]') ?? false")
    assert start, "focus is on the current choice"
    assert f.js(SHOWN), "focus is shown there"
    first = f.js("document.activeElement.textContent.trim()")
    f.press("ArrowDown")
    assert f.js("document.activeElement.textContent.trim()") != first, "the arrows move"
    for _ in range(8):
        f.press("Tab")
        assert f.js("!!document.activeElement.closest('header.s-top .s-look-panel')"), "Tab stays inside while it's open"
    f.run("document.querySelector('header.s-top [data-look-mode=light]').focus()")
    f.press("Enter")
    f.wait("document.documentElement.dataset.theme.endsWith('-light')", timeout=5, what="Light")
    assert f.js("document.querySelector('header.s-top [data-look-menu]').open"), "a look keeps it open"
    assert f.js("document.querySelector('header.s-top [data-look-mode=light]').getAttribute('aria-pressed')") == "true", "Light says it's picked"
    phone = f.js("innerWidth < 900")
    game_row = f.js("getComputedStyle(document.querySelector('header.s-top .s-look-game')).display") != "none"
    assert game_row == phone, f"Game mode is in the menu on a phone only: {game_row}"
    f.shot("look-open")
    f.press("Escape")
    f.wait("!document.querySelector('header.s-top [data-look-menu]').open", timeout=5, what="Escape closes it")
    assert f.js(f"document.activeElement === {pill}"), "and focus goes back to the pill"
    f.run("document.querySelector('header.s-top [data-look-menu]').open = true")
    f.run("document.querySelector('header.s-top [data-look-mode=dark]').click()")
    f.wait("document.documentElement.dataset.theme.endsWith('-dark')", timeout=5, what="Dark again")
    f.run("document.querySelector('header.s-top [data-look-menu]').open = false")


def journey(f):
    # The player asks for the song's recording on the media host, which a local server doesn't run.
    f.expected_errors.append("/media/song-audio/")
    f.go("/songs/amazing-grace", "document.querySelector('#song-mixer')?.hasAttribute('data-ready')")
    look_menu(f)
    f.go("/songs/amazing-grace", "document.querySelector('#song-mixer')?.hasAttribute('data-ready')")
    # The part you sing comes first (the hero's left column): one stop, the arrows move the choice.
    tab_to(f, "#song-mixer [data-pick]")
    assert f.js("document.activeElement.getAttribute('aria-checked')") == "true", "focus lands on the part that's chosen"
    f.press("ArrowDown")
    assert f.js("document.activeElement.dataset.pick") == "alto" and f.js("document.activeElement.getAttribute('aria-checked')") == "true", "the arrows choose the next part"
    assert "alto" in f.js("document.querySelector('[data-learn] [data-learn-label]').textContent").lower(), "Learn follows the pick"
    # Then the card: Play, each staff's letter (a part alone), the bars, the full score and the downloads.
    tab_to(f, "#song-mixer [data-play]")
    tab_to(f, "#song-mixer [data-voice=soprano] [data-hear-alone]")
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "true", "Enter hears the staff alone, and says so"
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "false", "and Enter again brings every part back"
    tab_to(f, "#song-mixer [data-score-file=pdf]")
    # The song's sources are named as sources, never a bare number.
    assert f.js("[...document.querySelectorAll('.s-cite a')].every((a) => /\\S/.test(a.getAttribute('aria-label') ?? ''))"), "a citation without a name"
    f.shot("song")

    # Loaded, not just drawn: the chips' module listens only once it has run (a press before that did nothing, and a
    # bigger sheet made that likelier, 2026-11-23).
    f.go("/courses", "document.readyState === 'complete' && document.querySelector('[data-courses-filter]')")
    f.run("try { localStorage.removeItem('stackapella.coursesChip'); } catch {}")
    f.go("/courses", "document.readyState === 'complete' && document.querySelector('[data-courses-filter]')")
    assert "note to four" in f.js("document.querySelector('.s-crs-start-h a').textContent").replace("\n", " ").replace("  ", " "), "the card's title reads as words"
    tab_to(f, "[data-filter=harmony]")
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "true", "Enter presses the chip, and says so"
    assert f.js("document.querySelector('[data-courses-shown]').textContent.trim() !== ''"), "the live line says what's shown"

    f.go("/pricing", "document.querySelector('[data-compare-diff]')")
    # The whole table is behind See every detail (the short pricing page, 2026-11-23): Enter opens it, then its switch.
    tab_to(f, ".s-every > summary")
    f.press("Enter")
    assert f.js("document.querySelector('.s-every').open") is True, "Enter opens See every detail"
    tab_to(f, "[data-compare-diff]")
    f.press(" ")
    assert f.js("document.activeElement.checked") is True, "Space turns Only what's different on"

    f.go("/songs/choral-classics", "document.querySelector('[data-player]')")
    tab_to(f, ".s-xcoll-try [data-hear-alone]")
    assert f.js("[...document.querySelectorAll('.s-xpart-name')].every((n) => !/[a-z][A-Z]/.test(n.textContent))"), "a part's name and its tag run together"
    f.shot("collection")


run("site_keyboard", journey, sizes=("phone", "desktop"))
