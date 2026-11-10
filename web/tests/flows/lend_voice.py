"""Lend your voice (docs/lend-your-voice-plan.md, slice 2): with its switch off, no Settings row and
the page says it isn't open; on, a row in Settings opens it. Not yet a volunteer: the agreement, the
credit and "I agree"; a refusal said in words. A volunteer: what they hold, and the Wanted list,
songs or clips, to claim from; a claim let go after asking. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Off: no row, and the page says so.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#device')")
    f.run("sessionStorage.removeItem('stackapella.mockLendVoice'); sessionStorage.removeItem('stackapella.mockVolunteer');")
    f.go("/settings", "document.querySelector('#device')")
    assert f.js("!document.querySelector('[data-lend-voice]')"), "no row while the switch is off"
    f.go("/voices", "document.querySelector('[data-voices-off]')")

    # On: the row in Settings opens it, and a refusal is said in words.
    f.run("sessionStorage.setItem('stackapella.mockLendVoice', '1');")
    f.go("/settings?scenario=voices.join:account_too_new", "document.querySelector('[data-lend-voice]')")
    f.run("document.querySelector('[data-lend-voice]').click()")
    f.wait("location.pathname === '/voices' && document.querySelector('[data-voices-join] [role=region]')", what="the way in, with the agreement")
    f.run("document.querySelector('[data-voices-agree]').click()")
    f.wait(f"document.querySelector('[data-voices-refused]')?.textContent.trim() === {json.dumps(t(f, 'voices.refused.tooNew'))}", what="a week old first")

    # Agreed: a volunteer, with what they hold and what's wanted.
    f.go("/voices?scenario=", "document.querySelector('[data-voices-agree]')")
    f.no_sideways("the way in")
    f.shot("join")
    f.run("document.querySelector('[data-voices-agree]').click()")
    f.wait("document.querySelector('[data-voices-wanted] [data-wanted]')", what="the Wanted list")
    assert f.js("document.querySelectorAll('[data-claim]').length") == 1, "what they hold"
    assert "yodeler" in f.js("document.querySelector('[data-wanted^=clip]').innerText"), "an expert's skill said in words"
    f.no_sideways("Lend your voice")
    f.shot("volunteer")
    f.run("[...document.querySelectorAll('[data-voices-wanted] [role=radio]')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "voices.kind.clips")) + ").click()")
    f.wait("document.querySelector('[data-voices-wanted] [role=radio][aria-checked=true]')?.textContent.trim() === " + json.dumps(t(f, "voices.kind.clips")), what="lesson clips")
    f.wait("document.querySelector('[data-wanted] [data-wanted-claim]')", what="the clips listed")
    # Six claims a day: the seventh is said in words, not a bare failure.
    f.run("history.replaceState(null, '', '/voices?scenario=voices.claims.create:rate_limited')")
    f.run("document.querySelector('[data-wanted] [data-wanted-claim]').click()")
    f.wait(f"document.querySelector('[data-voices-refused]')?.textContent.trim() === {json.dumps(t(f, 'voices.refused.today'))}", what="six a day")
    f.run("history.replaceState(null, '', '/voices?scenario=')")
    f.run("document.querySelector('[data-wanted] [data-wanted-claim]').click()")
    f.wait_text(t(f, "voices.claimed"))

    # Let one go: asked first, never the browser's own dialog.
    f.run("document.querySelector('[data-claim-release]').click()")
    f.wait("document.querySelector('[role=dialog]')", what="asked first")
    f.run("[...document.querySelectorAll('[role=dialog] button')].at(-1).click()")
    f.wait("!document.querySelector('[role=dialog]')", what="let go")


if __name__ == "__main__":
    run("lend_voice", journey)
