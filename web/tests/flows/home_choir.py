"""Home's "Hear yourself as a choir" panel (Hillel, 2026-11-22: "a main panel on the app page ... in the top right"): on a
wide screen in the course card's side column (top right); on a phone its own card right after the course card, never
above it. Fresh, after a real yes to AI (not undecided, not an older account's "existing" on), it offers Try it (to the free song's choir page); with a song ready and not yet heard, its row and
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

    # On, nothing made yet, but no yes to AI (Hillel, 2026-11-24: "hidden from top of home page until we know if they use AI
    # or not"): undecided, or an older account's "existing" on, shows no panel; the AI card after a kept part asks instead.
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.removeItem('stackapella.mockAiFeatures'); sessionStorage.removeItem('stackapella.mockAiSource');")
    for case, setup in (("undecided", ""), ("existing", "sessionStorage.setItem('stackapella.mockAiFeatures', '1'); sessionStorage.setItem('stackapella.mockAiSource', 'existing');")):
        if setup:
            f.run(setup)
        f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-lead] h1')")
        f.wait("(await import('/js/stores/account.js')).account.loaded && (await import('/js/own-choir-state.js')).ownChoir.loaded", what="the account and the choir")
        assert not f.js(PANEL), f"a choir panel with no yes to AI ({case})"
        assert f.js("(await import('/js/ai-choice.js')).aiChosen()") is False, f"{case} reads as chosen: the AI card wouldn't ask"

    # A real yes (the card's Try it): the offer, Try it.
    f.run("sessionStorage.setItem('stackapella.mockAiFeatures', '1'); sessionStorage.setItem('stackapella.mockAiSource', 'card');")
    f.go("/?api=mock&scenario=&as=member", f"{PANEL}")
    assert f.js(f"{PANEL}.dataset.choirPanelState") == "try"
    assert t(f, "ownChoir.home.body") in f.js(f"{PANEL}.innerText")
    place(f)
    f.no_sideways("Home with the choir panel")
    f.run(f"{PANEL}.scrollIntoView({{ block: 'center' }})")
    f.shot("try")
    f.run("document.querySelector('[data-choir-try]').click()")
    f.wait("location.pathname === '/t/happy-birthday/choir'", what="the free song's choir page")

    # The example's choir: Silent Night ready and not yet heard, in the panel, once; kept even with no yes on record.
    f.run("sessionStorage.removeItem('stackapella.mockChoirState'); sessionStorage.removeItem('stackapella.mockChoir'); sessionStorage.removeItem('stackapella.mockAiFeatures'); sessionStorage.removeItem('stackapella.mockAiSource');")
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
