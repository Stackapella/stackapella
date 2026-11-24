"""The choir gallery, version 1 (docs/choir-gallery-plan.md; js/components/choir-gallery-row.js, js/admin/choir-gallery.js).
A ready choir song's page has one quiet row, "Add to the gallery"; turning it on opens the consent sheet, and Cancel
leaves nothing. Its button shares the song unlisted (on the mock, which can't share, a stand-in id) and adds that share with the consent's version and the words' hash,
and the row says it waits for a listen. The status line follows the server: declined (a kind reason in the singer's
language), held after a report, hidden after a remake (with Send the new version), hidden while AI features are off and
back when they're on. Turning it off removes it after a styled confirm. A refusal is said in the sheet. Admin's tab: the
checklist, the mix, Approve, and Take it down with staff's own sentence.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

PAGE = "/t/silent-night/choir?api=mock&scenario={scenario}&as=member"
ROW = "document.querySelector('[data-choir-gallery]')"
STATUS = f"{ROW}?.dataset.galleryStatus"
LINE = "document.querySelector('[data-gallery-line]')?.textContent.trim() ?? ''"
SONG = "Rq5Sn1"  # the example's ready Silent Night


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def open_page(f, scenario=""):
    f.go(PAGE.format(scenario=scenario), ROW)


def set_item(f, item):
    """The server's item for the song, as the next ownChoir.show answers it (the mock's kept state)."""
    f.run(f"""const s = JSON.parse(sessionStorage.getItem('stackapella.mockChoir'));
        s.requests.find((r) => r.id === {json.dumps(SONG)}).gallery = {json.dumps(item)};
        sessionStorage.setItem('stackapella.mockChoir', JSON.stringify(s));""")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run("""sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockChoirGallery', '1');
        for (const k of ['stackapella.mockChoir', 'stackapella.mockChoirState', 'stackapella.mockGalleryAiOff', 'stackapella.mockTogether']) sessionStorage.removeItem(k);""")
    clear_projects(f)
    open_page(f)

    # The row: off, its hint saying who can hear it.
    assert f.js(STATUS) == "none", f.js(STATUS)
    assert f.js(f"{ROW}.querySelector('[role=switch]').getAttribute('aria-checked')") == "false"
    assert t(f, "ownChoir.gallery.rowHint") in f.js(f"{ROW}.textContent"), "the row's hint"
    f.no_sideways("the gallery row")
    f.shot("row")

    # Turning it on opens the consent sheet: what goes public, the label, whose voice, a listen first, how to remove it.
    f.run(f"{ROW}.querySelector('[role=switch]').click()")
    f.wait("document.querySelector('[data-gallery-sheet]')", what="the consent sheet")
    sheet = f.js("document.querySelector('[role=dialog]').textContent")
    for key in ["sheet.shown", "sheet.label", "sheet.whose", "sheet.next", "sheet.remove", "sheet.confirm"]:
        assert t(f, "ownChoir.gallery." + key) in sheet, key
    assert t(f, "ownChoir.gallery.sheet.title", {"song": "Silent Night"}) in sheet, "the title names the song"
    f.shot("sheet")
    # Cancel leaves nothing.
    f.run("document.querySelector('[data-gallery-cancel]').click()")
    f.wait("!document.querySelector('[data-gallery-sheet]')", what="the sheet closed")
    assert f.js(STATUS) == "none" and f.js("globalThis.mockGalleryAdd === undefined"), "a cancel adds nothing"

    # Adding: the song shared unlisted, then added with the consent's version and the hash of the words shown.
    f.run(f"{ROW}.querySelector('[role=switch]').click()")
    f.wait("document.querySelector('[data-gallery-confirm]')", what="the sheet again")
    f.run("window.__warned = []; const w = console.warn; console.warn = (...a) => { window.__warned.push(a.map((x) => x?.stack ?? String(x)).join(' ')); w(...a); };")
    f.run("document.querySelector('[data-gallery-confirm]').click()")
    f.wait(f"{STATUS} === 'pending' || document.querySelector('[data-gallery-error]')", timeout=60, what="the add's answer")
    assert f.js(STATUS) == "pending", f.js("window.__warned.join(' | ')")
    sent = f.js("globalThis.mockGalleryAdd")
    assert sent["consentVersion"] == 1 and sent["ownVoice"] is True and len(sent["wordsHash"]) == 64 and sent["harmonyId"] == "hMockGallery", sent
    assert f.js(LINE) == t(f, "ownChoir.gallery.status.pending"), f.js(LINE)
    assert f.js(f"{ROW}.querySelector('[role=switch]').getAttribute('aria-checked')") == "true"
    f.shot("pending")

    # Declined: the reason in the singer's own words, the song still theirs; the switch reads off.
    set_item(f, {"id": "cg_1", "harmonyId": "h1", "status": "declined", "reason": "noise", "reasonText": None, "hiddenBecause": None, "addedAt": "2026-11-24T10:00:00Z", "decidedAt": "2026-11-24T12:00:00Z"})
    open_page(f)
    want = t(f, "ownChoir.gallery.status.declined", {"reason": t(f, "ownChoir.gallery.reason.noise", {"song": "Silent Night"})})
    assert f.js(LINE) == want, f.js(LINE)
    assert f.js(f"{ROW}.querySelector('[role=switch]').getAttribute('aria-checked')") == "false"
    f.shot("declined")

    # Held after a report.
    set_item(f, {"id": "cg_1", "harmonyId": "h1", "status": "held", "reason": None, "reasonText": None, "hiddenBecause": None, "addedAt": "2026-11-24T10:00:00Z", "decidedAt": None})
    open_page(f)
    assert f.js(LINE) == t(f, "ownChoir.gallery.status.held"), f.js(LINE)

    # Remade since it was heard: hidden, and Send the new version opens the sheet; adding again waits for a listen.
    set_item(f, {"id": "cg_1", "harmonyId": "h1", "status": "hidden", "reason": None, "reasonText": None, "hiddenBecause": "changed", "addedAt": "2026-11-24T10:00:00Z", "decidedAt": None})
    open_page(f)
    assert f.js(LINE) == t(f, "ownChoir.gallery.status.changed"), f.js(LINE)
    f.shot("changed")
    f.run("document.querySelector('[data-gallery-resend]').click()")
    f.wait("document.querySelector('[data-gallery-confirm]')", what="the sheet for the new version")
    f.run("document.querySelector('[data-gallery-confirm]').click()")
    f.wait(f"{STATUS} === 'pending'", timeout=60, what="the new version waiting for a listen")

    # Hidden while AI features are off, back as it was when they're on.
    set_item(f, {"id": "cg_1", "harmonyId": "h1", "status": "approved", "reason": None, "reasonText": None, "hiddenBecause": None, "addedAt": "2026-11-24T10:00:00Z", "decidedAt": "2026-11-24T12:00:00Z"})
    f.run("sessionStorage.setItem('stackapella.mockGalleryAiOff', '1')")
    open_page(f)
    assert f.js(LINE) == t(f, "ownChoir.gallery.status.aiOff"), f.js(LINE)
    f.run("sessionStorage.removeItem('stackapella.mockGalleryAiOff')")
    open_page(f)
    assert f.js(LINE) == t(f, "ownChoir.gallery.status.approved"), f.js(LINE)
    f.shot("approved")

    # Turning it off: a styled confirm naming the song, then gone at once.
    f.run(f"{ROW}.querySelector('[role=switch]').click()")
    f.wait("document.querySelector('[role=dialog] form')", what="the remove confirmation")
    assert f.js("document.querySelector('[role=dialog] h2').textContent") == t(f, "ownChoir.gallery.remove.title", {"song": "Silent Night"})
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait(f"{STATUS} === 'none'", what="the song out of the gallery")

    # A refusal is said in the sheet (20 in the gallery already).
    open_page(f, "ownChoir.gallery.add:limit_reached")
    f.run(f"{ROW}.querySelector('[role=switch]').click()")
    f.wait("document.querySelector('[data-gallery-confirm]')", what="the sheet")
    f.run("document.querySelector('[data-gallery-confirm]').click()")
    f.wait("document.querySelector('[data-gallery-error]')", timeout=60, what="the refusal")
    assert f.js("document.querySelector('[data-gallery-error]').textContent.trim()") == t(f, "ownChoir.gallery.error.limitLive")

    # Admin: the checklist, the mix to hear, Approve; then Take it down with staff's own sentence.
    f.go("/admin/choir-gallery?api=mock&scenario=&as=admin", "document.querySelector('[data-gallery-item]')")
    assert f.js("document.querySelectorAll('[data-gallery-checklist] li').length") == 4
    assert f.js("!!document.querySelector('[data-gallery-audio]').getAttribute('src')"), "the mix to hear"
    f.no_sideways("the gallery review")
    f.shot("admin")
    f.run("document.querySelector('[data-gallery-approve]').click()")
    f.wait("!document.querySelector('[data-gallery-item]')", what="the approved item off the waiting list")
    assert f.js("globalThis.mockGalleryDecision.decision") == "approve"
    f.run("document.querySelector('[data-gallery-review-status] [data-status=approved]').click()")
    f.wait("document.querySelector('[data-gallery-item] [data-gallery-decline-open]')", what="the approved item")
    f.run("document.querySelector('[data-gallery-decline-open]').click()")
    f.run("const s = document.querySelector('[data-gallery-reason]'); s.value = 'other'; s.dispatchEvent(new Event('change'));")
    f.wait("document.querySelector('[data-gallery-reason-text]')", what="the sentence box")
    f.run("const a = document.querySelector('[data-gallery-reason-text]'); a.value = 'We took it down after a check.'; a.dispatchEvent(new Event('input'));")
    f.run("document.querySelector('[data-gallery-decline-send]').click()")
    f.wait("!document.querySelector('[data-gallery-item]')", what="the item taken down")
    sent = f.js("globalThis.mockGalleryDecision")
    assert sent == {"decision": "takedown", "reason": "other", "reasonText": "We took it down after a check."}, sent


if __name__ == "__main__":
    run("choir_gallery", journey)
