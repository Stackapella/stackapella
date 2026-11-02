"""The school offer (docs/school-offer-plan.md), on the contract's examples with the `schools` flag
on: a teacher finds Class codes in Settings, sees a code's seats and makes one; a student joins from
the class link and is told plainly what they got and until when; a refusal is said plainly; signed
out, the account wall comes first; Admin sees the codes and who redeemed one.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SCHOOLS_ON = """const { account, loadAuthConfig } = await import('/js/stores/account.js'); const c = await loadAuthConfig(); account.config = { ...c, schools: true };"""
# The example member is under 18: class codes are for adults, so the teacher is made one here.
ADULT = """sessionStorage.setItem('stackapella.mockInitials', JSON.stringify({ isAdult: true })); { const s = await import('/js/stores/account.js'); await s.loadAccount({ fresh: true }); }"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


ANSWER = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    window.answers = %s;
    api.useTransport(async (url, init = {}) => window.answers[init.operation]
        ? new Response(JSON.stringify(window.answers[init.operation]), { status: 200, headers: { 'Content-Type': 'application/json' } })
        : mockFetch(url, init));"""
PENDING = {"id": "Pd1", "name": "Period 5 choir", "code": "ZX2C-4VBN", "url": "https://stackapella.com/class/ZX2C-4VBN", "seats": 25, "used": 0, "status": "pending", "expiresAt": "2027-03-01T10:00:00Z", "closedAt": None, "createdAt": "2026-11-01T10:00:00Z"}
WAITING = {"id": "Ab3dEf9h", "name": "Period 3 choir", "code": "KQ7M-3XPA", "teacher": {"id": "Zx81Qw2e", "name": "Ms Rivera", "email": "teacher@example.com", "approved": False, "seatAllowance": 300, "seatsTaken": 30}, "seats": 30, "used": 0, "status": "pending", "createdAt": "2026-11-01T10:00:00Z", "expiresAt": "2027-03-01T10:00:00Z", "closedAt": None, "closedBy": None, "redemptions": []}


def schools_on(f, extra=""):
    """The flag on, once the app's own auth.config answer is in (a late one would replace it)."""
    f.wait("(async () => !!(await import('/js/stores/account.js')).account.config)()", what="the app's settings")
    f.js("new Promise((r) => setTimeout(r, 300))")
    f.run(SCHOOLS_ON + extra)


def go_in_app(f, path):
    f.run(f"(await import('/js/router.js')).navigate({json.dumps(path)})")


def journey(f):
    # The teacher: Settings > Class codes (schools on), the code's seats, then a new code.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    schools_on(f, ADULT)
    go_in_app(f, "/settings")
    f.wait("document.querySelector('[data-class-codes]')", what="Class codes in Settings")
    f.run("document.querySelector('[data-class-codes]').click()")
    f.wait("document.querySelector('[data-classes]')", what="the codes page")
    f.wait("document.querySelector('[data-class=\"KQ7M-3XPA\"]')", what="the teacher's code")
    assert t(f, "school.classes.seatsUsed", {"used": "12", "seats": "30"}) in f.js("document.querySelector('[data-class=\"KQ7M-3XPA\"]').textContent")
    assert f.js("document.querySelector('[data-class=\"KQ7M-3XPA\"]').dataset.state") == "open"
    f.shot("teacher")
    f.run("const i = document.querySelector('[data-class-name]'); i.value = 'Period 5 choir'; i.dispatchEvent(new Event('input'))")
    f.run("document.querySelector('[data-class-make]').click()")
    f.wait("document.querySelectorAll('[data-class]').length === 2", what="the new code listed")
    # Closing asks first, naming the class.
    f.run("document.querySelector('[data-class-close]').click()")
    f.wait("document.body.innerText.includes(" + json.dumps(t(f, "school.classes.closeAction")) + ")", what="the close question")
    f.shot("close-question")
    f.run("[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "common.cancel")) + ")?.click()")
    # A teacher not approved yet: the code waits, said plainly, with no link to share.
    f.run(ANSWER % json.dumps({"school.classes.list": {"data": [PENDING]}}))
    go_in_app(f, "/settings")
    go_in_app(f, "/settings/classes")
    f.wait("document.querySelector('[data-class=\"ZX2C-4VBN\"][data-state=pending]')", what="a code waiting for approval")
    assert f.js("!!document.querySelector('[data-class-pending]')") and not f.js("!!document.querySelector('[data-class=\"ZX2C-4VBN\"] [data-class-copy]')"), "waiting: said, and no link to share"
    f.shot("pending")
    f.run(ANSWER % "{}")

    # The student, from the class link: the code filled in, then Plus for a year, until when.
    go_in_app(f, "/class/KQ7M-3XPA")
    f.wait("document.querySelector('[data-join-code]')?.value === 'KQ7M-3XPA'", what="the code from the link")
    f.shot("join")
    f.run("document.querySelector('[data-join]').click()")
    f.wait("document.querySelector('[data-joined]')", what="joined")
    assert "2027" in f.js("document.querySelector('[data-class-join]').textContent"), "the year's end said"
    f.shot("joined")

    # A refusal, said plainly: already on Plus.
    f.go("/?api=mock&scenario=school.redeem:school_already_plus&as=member", "document.querySelector('#app header')")
    schools_on(f)
    go_in_app(f, "/class")
    f.wait("document.querySelector('[data-join-code]')", what="the join form")
    f.run("const i = document.querySelector('[data-join-code]'); i.value = 'kq7m3xpa'; i.dispatchEvent(new Event('input'))")
    f.wait("document.querySelector('[data-join-code]').value === 'KQ7M-3XPA'", what="the typed code made clean")
    f.run("document.querySelector('[data-join]').click()")
    f.wait_text(t(f, "school.error.alreadyPlus"))

    # Signed out: the account wall, back to the class link after.
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
    schools_on(f)
    go_in_app(f, "/class/KQ7M-3XPA")
    f.wait("document.querySelector('[data-account-wall][data-reason=school]')", what="the account wall")
    assert "next=%2Fclass%2FKQ7M-3XPA" in f.js("document.querySelector('[data-wall-sign-up]').getAttribute('href')")

    # Admin: the codes, then one code's students.
    f.go("/admin/school?api=mock&scenario=", "document.querySelector('[data-admin-school] [data-school-code]')")
    f.run("document.querySelector('[data-school-code] button').click()")
    f.wait("document.querySelector('[data-redemption]')", what="who redeemed it")
    f.shot("admin")
    # A teacher waiting for approval: one tap, through the audited sheet.
    f.run(ANSWER % json.dumps({"admin.school.list": {"data": [WAITING]}, "admin.school.show": {"data": WAITING}}))
    go_in_app(f, "/admin/people")
    go_in_app(f, "/admin/school")
    f.wait("document.querySelector('[data-teacher-waiting]')", what="the teacher waiting")
    f.run("document.querySelector('[data-school-code] button').click()")
    f.wait("document.querySelector('[data-school-approve]')", what="Approve")
    f.run("document.querySelector('[data-school-approve]').click()")
    f.wait("document.querySelector('form textarea')", what="the approval sheet")
    f.shot("approve")


if __name__ == "__main__":
    run("school_classes", journey)
