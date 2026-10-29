"""M3's accounts, against the real server (API=http://127.0.0.1:8000 tool/flows.sh
accounts_integration), end to end with email and a password: sign up, verify from the email's link
(the local server writes mail to its log, storage/logs/laravel.log), sign out and in again, ask for
the data export and wait for its download, then delete the account; the password no longer signs
in. Google and Apple need real keys, so they're on Hillel's device pass. Each run makes a new
account, so it can run again without reseeding, but sign-ups are limited to 5 an hour from one
address and a run makes two. Needs the queue worker (the mail and the export are jobs).
"""
import json
import os
import re
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
# The server the flow runs against (FLOW_SERVER, its `server` folder; the backend worktree by default):
# its log is where the mail driver writes the verification links.
LOG = Path(os.environ.get("FLOW_SERVER", Path.home() / "Code/stackapella-backend/server")) / "storage/logs/laravel.log"
PASSWORD = "a-long-flow-password-2026"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def verify_link(after, email):
    """The newest verification link written to the log after `after` bytes, for `email`."""
    deadline = time.time() + 90  # the mail is a queued job
    while time.time() < deadline:
        text = LOG.read_bytes()[after:].decode(errors="replace")
        if email in text:
            found = re.findall(r"/verify\?token=([A-Za-z0-9._~%-]+)", text[text.index(email):])
            if found:
                return found[0]
        time.sleep(0.5)
    raise AssertionError(f"no verification email for {email} in {LOG}")


def sign_in(f, email, password):
    f.go("/signin?api=real", "document.querySelector('form')")
    f.type("input[type=email]", email)
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
    f.type("input[autocomplete=current-password]", password)
    f.run("document.querySelector('form').requestSubmit()")


def journey(f):
    f.expected_errors.append("401 (Unauthorized) http://127.0.0.1")  # signed out, and the deleted account
    f.expected_errors.append("419 (Unknown Status Code) http://127.0.0.1")  # a first CSRF token, retried
    f.expected_errors.append("422 (Unprocessable Content) http://127.0.0.1")  # the deleted account's sign-in
    f.expected_errors.append("404 (Not Found) http://127.0.0.1")  # templates and other optional lookups
    email = f"flow-{f.size}-{int(time.time())}@example.com"

    # Sign up: the email's link verifies the address and signs in.
    before = LOG.stat().st_size if LOG.exists() else 0
    f.go("/signin?mode=signup&api=real", "document.querySelector('form')")
    f.type("input[autocomplete=name]", "Flow Singer")
    f.type("input[type=email]", email)
    f.type("input[autocomplete=new-password]", PASSWORD)
    f.run("const [m, y] = document.querySelectorAll('form fieldset select'); m.value = '4'; m.dispatchEvent(new Event('change')); y.value = '1990'; y.dispatchEvent(new Event('change'));")
    time.sleep(2.5)  # a form sent in under 2 seconds is taken for a bot: the same answer, and nothing happens
    f.run("document.querySelector('form').requestSubmit()")
    f.wait_text(t(f, "auth.sent.title"), timeout=20)
    f.shot("sent")
    token = verify_link(before, email)
    f.go(f"/verify?token={token}&api=real", "document.querySelector('#app')")
    f.shot("verify")
    f.wait("(await import('/js/stores/account.js')).account.me?.email === " + repr(email), timeout=20, what="signed in, verified")

    # Out and back in with the password.
    f.run("await (await import('/js/stores/account.js')).signOut()")
    f.wait("!(await import('/js/stores/account.js')).account.me", what="signed out")
    sign_in(f, email, PASSWORD)
    f.wait("(await import('/js/stores/account.js')).account.me?.email === " + repr(email), timeout=20, what="signed in again")

    # Download my data: asked for, prepared by the queue, then ready to download.
    f.go("/settings?api=real", "document.querySelector('#export-heading')")
    f.click_t("settings.export.request")
    f.wait("document.querySelector('a[href*=\"export\"], a.btn-primary[href]')", timeout=90, what="the export ready to download")
    f.shot("export-ready")

    # Delete the account: typed word and password; then the password no longer works.
    f.click_t("settings.delete.button")
    f.wait("document.querySelector('[role=dialog] form')", what="the delete form")
    f.type("[role=dialog] input[type=password]", PASSWORD)
    word = t(f, "settings.delete.word")
    f.type("[role=dialog] input[autocomplete=off]", word)
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("location.pathname === '/' && !(await import('/js/stores/account.js')).account.me", timeout=20, what="deleted and signed out")
    sign_in(f, email, PASSWORD)
    f.wait("document.querySelector('[role=alert], .text-bad, [class*=bad]')", timeout=20, what="the sign-in refused")
    assert not f.js("(await import('/js/stores/account.js')).account.me"), "a deleted account signed in"
    f.shot("gone")


if __name__ == "__main__":
    run("accounts_integration", journey)
