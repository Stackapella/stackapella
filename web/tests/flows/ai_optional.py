"""AI optional (docs/ai-optional-plan.md): one choice behind every AI feature, behind its release switch (ai_optional).
Before the switch nothing changes and Settings has no row. With it: a guest from / has AI off and one through
/ai-harmony-singing (?ref=ai-harmony-singing) on; an account's Settings row turns it off, the AI guides give way to the practice voice,
the choir page says it uses AI voices with the way to turn them on, and that button brings the choir page back. A new
account (not chosen) starts with AI off. Both skins.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

FLAGS = "sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockAiVoices', '1'); sessionStorage.removeItem('stackapella.mockAiFeatures');"
SWITCH_ON = "sessionStorage.setItem('stackapella.mockAiOptional', '1');"
AI_ON = "(async () => { const a = await import('/js/stores/account.js'); await a.loadAuthConfig(); await a.loadAccount(); return (await import('/js/ai-choice.js')).aiOn(); })()"
AI_PARTS = "(async () => { const c = await import('/js/stores/catalogue.js'); await c.loadCatalogue(); return c.catalogue.list.reduce((n, song) => n + c.aiPartsOf(song.id).length, 0); })()"
MEMBER = "?api=mock&scenario=&as=member"


def journey(f):
    f.go(f"/{MEMBER}", "document.querySelector('h1')")
    f.run(FLAGS + " sessionStorage.removeItem('stackapella.mockAiOptional'); localStorage.removeItem('stackapella.ai');")

    # Before the release switch: everything as today, and no row in Settings.
    f.go(f"/settings{MEMBER}", "document.querySelector('#choir')")
    assert f.js(AI_ON) is True, "AI off before the switch"
    assert not f.js("!!document.querySelector('[data-ai-features]')"), "the row shows before the switch"
    ai_parts = f.js(AI_PARTS)
    assert ai_parts > 0, "the mock has no AI guide to hide"

    # The switch on, an account from before it (the server set it on): the row, on, with its hint.
    f.run(SWITCH_ON + " sessionStorage.setItem('stackapella.mockAiFeatures', '1');")
    f.go(f"/settings{MEMBER}", "document.querySelector('[data-ai-features]')")
    assert f.js("document.querySelector('[data-ai-features] [role=switch]').getAttribute('aria-checked')") == "true"
    f.run("document.querySelector('[data-ai-features]').scrollIntoView({ block: 'center' })")
    f.shot("settings-on")

    # Off: saved to the account, the AI guides gone (the practice voice sings those parts).
    f.run("document.querySelector('[data-ai-features] [role=switch]').click()")
    f.wait("(async () => (await import('/js/stores/account.js')).account.me?.aiFeatures === false)()", what="the choice saved")
    assert f.js(AI_ON) is False
    assert f.js(AI_PARTS) == 0, "an AI guide still plays with AI off"
    assert not f.js("!!document.querySelector('[data-lend-voice]')"), "Lend your voice shows with AI off"
    f.shot("settings-off")

    # An AI link while off: the choir page says so, and its button turns AI back on and opens the page.
    f.go(f"/t/amazing-grace/choir{MEMBER}", "document.querySelector('[data-ai-off]')")
    f.no_sideways("the AI-off notice")
    f.shot("choir-off")
    f.run("document.querySelector('[data-ai-off-on]').click()")
    f.wait("document.querySelector('[data-choir-agree]') || document.querySelector('[data-choir-start]')", what="the choir page, AI on again")
    assert f.js(AI_ON) is True

    # A new account, not chosen yet: AI off until the card or Settings.
    f.run("sessionStorage.removeItem('stackapella.mockAiFeatures');")
    f.go(f"/settings{MEMBER}", "document.querySelector('[data-ai-features]')")
    assert f.js(AI_ON) is False, "a new account starts with AI on"
    assert f.js("document.querySelector('[data-ai-features] [role=switch]').getAttribute('aria-checked')") == "false"

    # Guests: off from /, on through /ai-harmony-singing (the device remembers it).
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('h1')")
    f.run(SWITCH_ON + " localStorage.removeItem('stackapella.ai');")
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('h1')")
    assert f.js(AI_ON) is False, "a guest from / has AI on"
    f.go("/?api=mock&scenario=&as=signedOut&ref=ai-harmony-singing", "document.querySelector('h1')")
    assert f.js("localStorage.getItem('stackapella.ai')") == "on", "the /ai-harmony-singing arrival wasn't kept"
    assert f.js(AI_ON) is True, "a guest from /ai-harmony-singing has AI off"

    f.run("localStorage.removeItem('stackapella.ai'); ['mockAiOptional', 'mockAiFeatures', 'mockAiVoices', 'mockOwnChoir'].forEach((k) => sessionStorage.removeItem('stackapella.' + k));")


if __name__ == "__main__":
    run("ai_optional", journey)
