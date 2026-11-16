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


def journey(f):
    # The player asks for the song's recording on the media host, which a local server doesn't run.
    f.expected_errors.append("/media/song-audio/")
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

    f.go("/courses", "document.querySelector('[data-courses-filter]')")
    f.run("try { localStorage.removeItem('stackapella.coursesChip'); } catch {}")
    f.go("/courses", "document.querySelector('[data-courses-filter]')")
    assert "note to four" in f.js("document.querySelector('.s-crs-start-h a').textContent").replace("\n", " ").replace("  ", " "), "the card's title reads as words"
    tab_to(f, "[data-filter=harmony]")
    f.press("Enter")
    assert f.js("document.activeElement.getAttribute('aria-pressed')") == "true", "Enter presses the chip, and says so"
    assert f.js("document.querySelector('[data-courses-shown]').textContent.trim() !== ''"), "the live line says what's shown"

    f.go("/pricing", "document.querySelector('[data-compare-diff]')")
    tab_to(f, "[data-compare-diff]")
    f.press(" ")
    assert f.js("document.activeElement.checked") is True, "Space turns Only what's different on"

    f.go("/songs/choral-classics", "document.querySelector('[data-player]')")
    tab_to(f, ".s-xcoll-try [data-hear-alone]")
    assert f.js("[...document.querySelectorAll('.s-xpart-name')].every((n) => !/[a-z][A-Z]/.test(n.textContent))"), "a part's name and its tag run together"
    f.shot("collection")


run("site_keyboard", journey, sizes=("phone", "desktop"))
