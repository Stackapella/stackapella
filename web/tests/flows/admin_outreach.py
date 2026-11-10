"""Admin > Growth > Outreach (docs/outreach-plan.md): the list with today's cap and what needs Hillel,
an organization's page (its published contact and source, the draft in plain text, Send, Mark sent for
a form, the do-not-contact list, the outcome), a refused Send said plainly (today's cap), Results by
group and angle, and Admin Home's "What needs you now" with follow-ups due and replies to read. Works
on a phone: nothing scrolls sideways.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
CONTRACT = Path(__file__).resolve().parents[3] / "contract" / "examples"
DRAFTED = json.loads((CONTRACT / "admin.outreach.drafts.create.201.json").read_text())
# Marketing's note on when to send, shown beside Send.
DRAFTED["data"]["timingNote"] = "Check their spring concert date first."
# The organization with a draft waiting: its page answers as a fresh draft does.
SHOW_DRAFT = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    api.useTransport(async (url, init = {}) => init.operation === 'admin.outreach.show'
        ? new Response(%s, { status: 200, headers: { 'Content-Type': 'application/json' } })
        : mockFetch(url, init));""" % json.dumps(json.dumps(DRAFTED))


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # Admin Home: outreach's follow-ups due and replies to read, in What needs you now.
    f.go("/admin?api=mock&scenario=", "document.querySelector('main')")
    f.wait("document.querySelector('[data-admin-needs] [data-needs-outreach]')", what="outreach in What needs you now")
    assert t(f, "admin.outreach.followUpsDue", {"count": 1}) in f.js("document.querySelector('[data-needs-outreach]').innerText")

    # The list: today's cap, a row, the filters.
    f.go("/admin/outreach?api=mock&scenario=", "document.querySelector('[data-outreach-summary]')")
    f.wait("document.querySelector('[data-outreach-row]')", what="an organization")
    f.wait_text(t(f, "admin.outreach.sentToday", {"sent": 1, "cap": 5}))
    f.no_sideways("Outreach's list")
    f.shot("list")

    # Results, by group and by angle.
    f.run("document.querySelector('[data-outreach-tab=results]').click()")
    f.wait("document.querySelector('[data-outreach-results] [data-result-row]')", what="the results")
    f.wait_text(t(f, "admin.outreach.results.byAngle"))
    f.no_sideways("Outreach's results")
    f.shot("results")

    # An organization with a draft: its contact and where it was published, the draft, Send.
    f.go("/admin/outreach?api=mock&scenario=", "document.querySelector('[data-outreach-row]')")
    f.run(SHOW_DRAFT)
    f.run("const { navigate } = await import('/js/router.js'); navigate('/admin/outreach/' + document.querySelector('[data-outreach-row]').dataset.outreachRow);")
    f.wait("document.querySelector('[data-draft] [data-draft-body]')", what="the draft")
    assert "harmony.example.com/contact" in f.js("document.querySelector('[data-outreach-target]').innerText"), "where the contact was published"
    f.type("[data-draft-subject]", "Learning parts at home, for Harmony Society's chapters")
    assert "Check their spring concert date first." in f.js("document.querySelector('[data-timing-note]').textContent"), "the timing note beside Send"
    f.no_sideways("an organization's page")
    f.run("document.querySelector('[data-draft-send]').scrollIntoView({ block: 'center' })")
    f.shot("draft")
    # A test to Hillel himself: said where it went, nothing else changes.
    f.run("document.querySelector('[data-draft-test]').click()")
    f.wait_text(t(f, "admin.outreach.testSent", {"email": "admin@example.com"}))
    assert f.js("(globalThis.mockCalls ?? []).includes('admin.outreach.drafts.test')"), "the test asked for"
    f.run("document.querySelector('[data-draft-send]').click()")
    f.wait_text(t(f, "admin.outreach.sentToast", {"name": DRAFTED["data"]["name"]}))
    assert f.js("(globalThis.mockCalls ?? []).includes('admin.outreach.drafts.update') && (globalThis.mockCalls ?? []).includes('admin.outreach.drafts.send')"), "saved, then sent"

    # Outreach email not set up yet: the test says so in one calm line by the button.
    f.go("/admin/outreach?api=mock&scenario=admin.outreach.drafts.test:outreach_off", "document.querySelector('[data-outreach-row]')")
    f.run(SHOW_DRAFT)
    f.run("const { navigate } = await import('/js/router.js'); navigate('/admin/outreach/' + document.querySelector('[data-outreach-row]').dataset.outreachRow);")
    f.wait("document.querySelector('[data-draft-test]')", what="Send a test to me")
    f.run("document.querySelector('[data-draft-test]').click()")
    f.wait("document.querySelector('[data-test-off]')", what="the not-set-up line")
    assert t(f, "admin.outreach.testOff") in f.js("document.querySelector('[data-test-off]').textContent"), "the not-set-up words"
    f.run("document.querySelector('[data-test-off]').scrollIntoView({ block: 'center' })")
    f.shot("test-off")

    # Today's five already gone: said in plain words, still a draft.
    f.go("/admin/outreach?api=mock&scenario=admin.outreach.drafts.send:outreach_daily_cap", "document.querySelector('[data-outreach-row]')")
    f.run(SHOW_DRAFT)
    f.run("const { navigate } = await import('/js/router.js'); navigate('/admin/outreach/' + document.querySelector('[data-outreach-row]').dataset.outreachRow);")
    f.wait("document.querySelector('[data-draft-send]')", what="Send")
    f.run("document.querySelector('[data-draft-send]').click()")
    f.wait_text(t(f, "admin.outreach.refused.cap"))

    # Before its send-not-before day: asked first, in plain words.
    f.go("/admin/outreach?api=mock&scenario=admin.outreach.drafts.send:outreach_too_early", "document.querySelector('[data-outreach-row]')")
    f.run(SHOW_DRAFT)
    f.run("const { navigate } = await import('/js/router.js'); navigate('/admin/outreach/' + document.querySelector('[data-outreach-row]').dataset.outreachRow);")
    f.wait("document.querySelector('[data-draft-send]')", what="Send")
    f.run("document.querySelector('[data-draft-send]').click()")
    f.wait("document.querySelector('[data-stale-body]')", what="held until, send anyway?")
    assert t(f, "admin.outreach.earlyTitle") in f.js("document.querySelector('[role=dialog]').innerText"), "the held question"
    f.click_t("common.cancel")

    # Declined asks first: the do-not-contact list is for good.
    f.run("document.querySelector('[data-outcome=declined]').click()")
    f.wait_text(t(f, "admin.outreach.declineBody"))
    f.shot("declined")
    f.click_t("common.cancel")


if __name__ == "__main__":
    run("admin_outreach", journey)
