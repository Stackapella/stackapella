"""Contact us with the real server (docs/feedback-plan.md; API=http://127.0.0.1:8000 tool/flows.sh feedback_integration):
the app and support.create together. Signed in through /dev/login as the seeded admin, feedback2 is turned on with a
reason (and put back as it was at the end, whatever happens); then a guest sends with no email (the server keeps it,
and the sent card says we can't reply), and an account sends on Something's broken with "Don't reply to me" and
"Sent with it" as the app lists it (device kind and browser family, language, skin, page): each answered 201 with a
reference. Needs the backend's demo data (php artisan db:seed)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

API = "(await import('/js/api.js')).api"
SENT = "document.querySelector('[data-contact-sent]')"


def sign_in(f):
    f.go("/dev/login", "document.querySelector('[data-dev-login]')")
    f.run("document.querySelector('[data-dev-login]').click()")
    f.wait("location.pathname !== '/dev/login'", what="signed in")


def set_switch(f, value, reason):
    f.js(f"{API}.admin.flags.update('feedback2', {{ value: {json.dumps(value)}, reason: {json.dumps(reason)} }}).then(() => true)")


def journey(f):
    f.expected_errors.append("401 (Unauthorized)")  # account.show before signing in
    sign_in(f)
    flags = f.js(f"{API}.admin.flags.list().then((r) => JSON.parse(JSON.stringify(r.data)))")
    flag = next(x for x in (flags if isinstance(flags, list) else flags.get("flags", [])) if x["name"] == "feedback2")
    before = flag["value"]
    try:
        set_switch(f, "on", "Integration check: feedback2 on")

        # An account: Something's broken, Don't reply to me, what Sent with it lists.
        f.go("/contact?from=/settings&moment=help", "document.querySelector('[data-contact-message]')")
        f.wait("(await import('/js/stores/account.js')).account.config?.feedback2 === true && document.querySelector('[data-no-reply]')", what="feedback2 on, signed in")
        f.run("document.querySelector('[data-topic=broken]').click(); document.querySelector('[data-no-reply] input').click();")
        f.type("[data-contact-message]", "Integration check: the app and support.create together.")
        f.wait("!document.querySelector('[data-contact-send]').disabled", what="Send ready")
        f.run("document.querySelector('[data-contact-send]').click()")
        f.wait(SENT, timeout=20, what="the account's message kept")
        assert f.js(f"{SENT}.innerText.includes((await import('/js/i18n.js')).t('contact.sentDone'))"), "no reply address in the thanks"
        assert f.js(f"{SENT}.innerText.includes((await import('/js/i18n.js')).t('contact.reference', {{ id: '' }}).trim())"), "the server's reference"
        f.shot("account-sent")

        # A guest with no email: kept, and told we can't reply.
        f.run("await (await import('/js/stores/account.js')).signOut()")
        f.go("/contact?from=/settings&moment=settings", "document.querySelector('[data-contact-message]')")
        f.wait("(await import('/js/stores/account.js')).account.config?.feedback2 === true && document.querySelector('[data-contact-email]')", what="a guest, feedback2 on")
        f.type("[data-contact-message]", "Integration check: a guest with no email.")
        f.wait("!document.querySelector('[data-contact-send]').disabled", what="Send ready without an email")
        f.run("document.querySelector('[data-contact-send]').click()")
        f.wait(SENT, timeout=20, what="the guest's message kept, no email")
        assert f.js("!!document.querySelector('[data-cant-reply]')"), "we can't reply"
        f.shot("guest-sent")
    finally:
        sign_in(f)
        set_switch(f, before, "Integration check: feedback2 back as it was")
        after = f.js(f"{API}.admin.flags.list().then((r) => JSON.parse(JSON.stringify(r.data)))")
        back = next(x for x in (after if isinstance(after, list) else after.get("flags", [])) if x["name"] == "feedback2")
        assert back["value"] == before, f"feedback2 left {back['value']}, was {before}"


if __name__ == "__main__":
    run("feedback_integration", journey)
