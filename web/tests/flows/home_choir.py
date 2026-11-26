"""Home's "Hear yourself as a choir" panel (Hillel, 2026-11-22: "a main panel on the app page ... in the top right"): on a
wide screen in the course card's side column (top right); on a phone its own card right after the course card, never
above it. Fresh, it offers Try it; AI's first showing (only) carries a quiet Not for me (Hillel, 2026-11-26), which hides it for good, and Settings brings it back; chose off or under 18, nothing (to the free song's choir page); with a song ready and not yet heard, its row and
Listen in the same panel, and no second ready card anywhere; off, nothing.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

PANEL = "document.querySelector('[data-choir-panel]')"


def place(f):
    """Where the panel is: inside the course card's side column (desktop), or after the course card (phone)."""
    if f.size == "desktop":
        assert f.js(f"!!document.querySelector('[data-lead]').contains({PANEL})"), "not in the course card on a wide screen"
        box = f.js(f"(() => {{ const p = {PANEL}.getBoundingClientRect(), l = document.querySelector('[data-lead]').getBoundingClientRect(); return {{ right: l.right - p.right, left: p.left - l.left, width: l.width }}; }})()")
        assert box["left"] > box["width"] / 2, f"not on the right: {box}"
    else:
        assert f.js(f"!document.querySelector('[data-lead]').contains({PANEL}) && !!(document.querySelector('[data-lead]').compareDocumentPosition({PANEL}) & Node.DOCUMENT_POSITION_FOLLOWING)"), "not after the course card on a phone"
    assert f.js("document.querySelectorAll('[data-choir-panel]').length") == 1, "the panel twice"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    # Off: no panel.
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '0'); sessionStorage.setItem('stackapella.mockChoirState', 'fresh'); sessionStorage.removeItem('stackapella.mockChoir');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1')")
    f.wait("(await import('/js/stores/account.js')).account.loaded", what="the account")
    assert not f.js(PANEL), "a panel while it's off"

    # On, nothing made yet, with AI optional's switch on (Hillel, 2026-11-26: "most new users to see it but have the option
    # to hide forever", then: "When we first show a user the AI they can dismiss it. After that the option should only be
    # in profile settings."): an older account's "existing" on, and undecided, both see the offer, Try it; only AI's first
    # showing on this device carries the quiet Not for me.
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockAiOptional', '1'); sessionStorage.setItem('stackapella.mockAiFeatures', '1'); sessionStorage.setItem('stackapella.mockAiSource', 'existing'); localStorage.removeItem('stackapella.aiFirstShown');")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}?.querySelector('[data-ai-hide=home_choir]')")
    assert f.js(f"{PANEL}.dataset.choirPanelState") == "try"
    f.run(f"{PANEL}.scrollIntoView({{ block: 'center' }})")
    f.shot("first")
    f.run("sessionStorage.removeItem('stackapella.mockAiFeatures'); sessionStorage.removeItem('stackapella.mockAiSource');")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}")
    f.js("new Promise((r) => setTimeout(r, 400))")
    assert f.js(f"{PANEL}.dataset.choirPanelState") == "try", "undecided"
    assert not f.js(f"!!{PANEL}.querySelector('[data-ai-hide]')"), "Not for me again after the first showing"
    assert t(f, "ownChoir.home.body") in f.js(f"{PANEL}.innerText")
    place(f)
    f.no_sideways("Home with the choir panel")
    f.run(f"{PANEL}.scrollIntoView({{ block: 'center' }})")
    f.shot("try")
    f.run("document.querySelector('[data-choir-try]').click()")
    f.wait("location.pathname === '/t/happy-birthday/choir'", what="the free song's choir page")
    # Not for me on a first showing: the panel goes at once, the toast says Settings brings it back, and it stays gone.
    f.run("localStorage.removeItem('stackapella.aiFirstShown');")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}?.querySelector('[data-ai-hide=home_choir]')")
    f.run(f"{PANEL}.querySelector('[data-ai-hide=home_choir]').click()")
    f.wait(f"!{PANEL}", what="the panel hidden")
    f.wait_text(t(f, "settings.ai.hidden"))
    f.shot("hidden")
    assert f.js("sessionStorage.getItem('stackapella.mockAiSource')") == "hide", "the account's hide wasn't saved with its source"
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1')")
    f.wait("(await import('/js/stores/account.js')).account.loaded && (await import('/js/own-choir-state.js')).ownChoir.loaded", what="the account and the choir")
    assert not f.js(PANEL), "the panel back after a hide"
    # Another device (nothing kept on it): the account's hide holds there too.
    f.run("localStorage.removeItem('stackapella.ai'); localStorage.removeItem('stackapella.aiFrom');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1')")
    f.wait("(await import('/js/stores/account.js')).account.loaded && (await import('/js/own-choir-state.js')).ownChoir.loaded", what="the account and the choir")
    assert not f.js(PANEL), "the panel back on another device"
    # Settings brings it back: the panel again, with no dismiss (Settings is the way now).
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-ai-features] [role=switch]')")
    f.run("document.querySelector('[data-ai-features] [role=switch]').click()")
    f.wait("sessionStorage.getItem('stackapella.mockAiFeatures') === '1'", what="AI back on from Settings")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}")
    f.js("new Promise((r) => setTimeout(r, 400))")
    assert not f.js(f"!!{PANEL}.querySelector('[data-ai-hide]')"), "a dismiss once Settings turned AI on"
    # Chose off in Settings: no panel either. Under 18: never a panel.
    f.run("sessionStorage.setItem('stackapella.mockAiSource', 'settings');")
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1')")
    f.wait("(await import('/js/stores/account.js')).account.loaded", what="the account")
    assert not f.js(PANEL), "a panel for someone who chose off"
    f.run("sessionStorage.removeItem('stackapella.mockAiFeatures'); sessionStorage.removeItem('stackapella.mockAiSource');")
    f.go("/?api=mock&scenario=&as=young", "document.querySelector('[data-lead] h1')")
    f.wait("(await import('/js/stores/account.js')).account.loaded", what="the account")
    assert not f.js(PANEL), "a panel for an account under 18"

    # The example's choir: Silent Night ready and not yet heard, in the panel, once; kept even with no yes on record.
    f.run("sessionStorage.removeItem('stackapella.mockChoirState'); sessionStorage.removeItem('stackapella.mockChoir'); sessionStorage.removeItem('stackapella.mockAiFeatures'); sessionStorage.removeItem('stackapella.mockAiSource'); sessionStorage.removeItem('stackapella.mockAiOptional');")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}?.dataset.choirPanelState === 'ready' && {PANEL}")
    assert f.js("document.querySelectorAll('[data-choir-notice]').length") >= 1
    assert "Silent Night" in f.js(f"{PANEL}.innerText")
    assert f.js("document.querySelectorAll('[data-choir-ready]').length") == 1, "the ready song said twice"
    place(f)
    f.run(f"{PANEL}.scrollIntoView({{ block: 'center' }})")
    f.shot("ready")
    f.run("sessionStorage.removeItem('stackapella.mockOwnChoir'); sessionStorage.removeItem('stackapella.mockChoir');")


if __name__ == "__main__":
    run("home_choir", journey)
