"""Statements of reasons and their review (contract/api.md notices.* and admin.appeals.*; the finish-line audit, 2026-11-13):
a member who has had a notice finds Notices in Settings, reads what was done, why and how it was decided, and asks for a
review once (the sheet names it, the text goes as the API's `text`). A suspended person's private link (/notice?token=)
shows the same notice with the token out of the address at once. Admin > Appeals lists the waiting review and decides
it with a note the person reads. Every screen says its words are a draft for the lawyer. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Settings: the row, only because there's a notice.
    f.go("/settings?api=mock&as=member", "document.querySelector('[data-notices-row]')")
    f.run("document.querySelector('[data-notices-row]').scrollIntoView({ block: 'center' })")
    f.run("document.querySelector('[data-notices-row]').click()")
    f.wait("location.pathname === '/notices' && document.querySelector('[data-notice]')", what="the notices")
    card = f.js("document.querySelector('[data-notice]').innerText")
    for key in ("notices.kind.harmony_hidden", "notices.what.harmony_hidden", "notices.why.reports", "notices.automatic"):
        assert t(f, key) in card, f"{key} on the notice: {card}"
    assert f.js("!!document.querySelector('[data-notices-draft]')"), "the draft line for the lawyer"
    f.no_sideways("Notices")
    f.shot("notices")
    # Ask for a review: the sheet, the text, sent as `text`.
    f.run("document.querySelector('[data-ask-review]').click()")
    f.wait("document.querySelector('[data-review-text]')", what="the review sheet")
    assert f.js("document.querySelector('[data-send-review]').disabled"), "Send waits for words"
    f.type("[data-review-text]", "It's my own recording of a carol.")
    f.shot("ask-review")
    f.run("window.mockBodies = []; document.querySelector('[data-send-review]').click()")
    f.wait("(window.mockBodies ?? []).some((b) => b.operation === 'notices.appeal')", what="the review asked for")
    body = f.js("window.mockBodies.find((b) => b.operation === 'notices.appeal').body")
    assert body == {"text": "It's my own recording of a carol."}, body
    f.wait("document.querySelector('[data-notice]')?.dataset.review !== 'open'", what="the notice showing its review")
    # The private link: the token leaves the address, the notice shows.
    f.go("/notice?api=mock&token=abc123", "document.querySelector('[data-notice]')")
    assert "token" not in f.js("location.search"), f.js("location.search")
    assert f.js("document.querySelector('h1')?.innerText") == t(f, "notices.oneTitle")
    f.shot("private-link")
    # Without a token: what to do.
    f.go("/notice?api=mock", "document.querySelector('#app main')")
    f.wait_text(t(f, "notices.token.missing"))
    # Admin > Appeals: the waiting review, decided with a note.
    f.go("/admin/appeals?api=mock&as=admin", "document.querySelector('[data-appeal]')")
    assert "It's my own recording of a carol." in f.js("document.querySelector('[data-appeal-text]').innerText")
    f.run("document.querySelector('[data-decide]').click()")
    f.wait("document.querySelector('[data-appeals-sheet]')", what="the decide sheet")
    f.run("document.querySelector('[data-decision=reversed]').click()")
    assert f.js("document.querySelector('[data-appeal-confirm]').disabled"), "deciding waits for a note"
    f.type("[data-appeal-note]", "A carol in the public domain: restored.")
    f.shot("decide")
    f.run("window.mockBodies = []; document.querySelector('[data-appeal-confirm]').click()")
    f.wait("(window.mockBodies ?? []).some((b) => b.operation === 'admin.appeals.decide')", what="the decision sent")
    body = f.js("window.mockBodies.find((b) => b.operation === 'admin.appeals.decide').body")
    assert body == {"decision": "reversed", "note": "A carol in the public domain: restored."}, body
    f.wait("!document.querySelector('[data-appeals-sheet]')", what="the sheet closed")


run("notices", journey)
