"""Suggest a correction (docs/corrections-plan.md; components/site/correction.blade.php, js/public/correction.js): on a
song page, the link opens its sheet; sending it empty says what's missing beside the box; a source that isn't a link is
refused by the server and said beside the source; a good one is thanked and the sheet closes; Cancel and the dimmed page
close it too, with focus back on the link; nothing scrolls sideways. Needs the real server (a site page and the API)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SHEET = "document.querySelector('[data-correction]')"


def journey(f):
    f.go("/songs/amazing-grace", "document.querySelector('[data-correction-open]')")
    f.no_sideways("/songs/amazing-grace")
    f.run("document.querySelector('[data-correction-open]').scrollIntoView({ block: 'center' }); document.querySelector('[data-correction-open]').click()")
    f.wait(f"{SHEET}.open", what="the sheet open")
    assert f.js("document.activeElement === document.querySelector('[data-correction] textarea')"), "focus in the box"

    # Empty: said beside the box, nothing sent.
    f.run(f"{SHEET}.querySelector('[data-correction-send]').click()")
    assert f.js(f"!{SHEET}.querySelector('[data-error=text]').hidden"), "the box says what's missing"

    # A source that isn't a link: the server refuses it, and the sheet says so beside the source.
    f.expected_errors.append("422 (Unprocessable Content) " + f.base + "/api/corrections")  # that refusal, on purpose
    f.run(f"const s = {SHEET}; s.querySelector('textarea').value = 'A test correction from the flows.'; s.querySelector('[name=source]').value = 'not a link'; s.querySelector('[data-correction-send]').click();")
    f.wait(f"!{SHEET}.querySelector('[data-error=source]').hidden", what="the source's own message")
    assert f.js(f"{SHEET}.querySelector('[name=source]').getAttribute('aria-invalid')") == "true"
    f.shot("bad-source")

    # A good one: thanked, then closed, focus back on the link.
    f.run(f"const s = {SHEET}; s.querySelector('[name=source]').value = 'https://example.com/a-source'; s.querySelector('[name=kind][value=words]').checked = true; s.querySelector('[data-correction-send]').click();")
    f.wait(f"{SHEET}.querySelector('[data-correction-status]').textContent.length > 0 && !{SHEET}.querySelector('[data-correction-send]').disabled", what="sent")
    f.shot("thanks")
    f.wait(f"!{SHEET}.open", timeout=6, what="the sheet closed")
    assert f.js("document.activeElement === document.querySelector('[data-correction-open]')"), "focus back on the link"

    # Cancel closes it.
    f.run("document.querySelector('[data-correction-open]').click()")
    f.wait(f"{SHEET}.open", what="open again")
    f.run(f"{SHEET}.querySelector('[data-correction-cancel]').click()")
    assert f.js(f"!{SHEET}.open"), "Cancel closes it"


run("correction_form", journey, sizes=("phone", "desktop"))
