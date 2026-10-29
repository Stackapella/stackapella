"""M3, accounts on the mock: signed out, the projects page offers Sign in; the sign-in screen with
Google, Apple and email; a wrong password said plainly; signing in; Settings (profile saved, a
taken handle, a change that needs a recent sign-in, Download my data, the delete confirmation);
signing out; forgot the password, then the reset link; the email-change undo link offering a new
password; a Google sign-in coming back with an error; and the age question for a new account.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('header')")
    f.wait_text(t(f, "auth.signIn"))
    f.click_t("auth.signIn")
    f.wait("location.pathname === '/signin'", what="the sign-in")
    f.wait_text(t(f, "auth.google"))
    f.wait_text(t(f, "auth.apple"))
    f.shot("sign-in")
    # A wrong password, then the right one.
    f.go("/signin?api=mock&scenario=auth.signin:invalid_credentials", "document.querySelector('form')")
    f.type("input[type=email]", "ada@example.com")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
    f.type("input[autocomplete=current-password]", "not it at all")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait_text(t(f, "auth.error.invalidCredentials"))
    f.go("/signin?api=mock&scenario=", "document.querySelector('form')")
    f.type("input[type=email]", "ada@example.com")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
    f.type("input[autocomplete=current-password]", "demo-password-for-the-flows")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("location.pathname === '/'", what="signed in, back to the projects")
    f.wait("document.querySelector('header a[href=\"/settings\"]')", what="the account in the header")
    # Settings.
    f.run("document.querySelector('header a[href=\"/settings\"]').click()")
    f.wait_text(t(f, "settings.profile"))
    f.shot("settings")
    # Hide profile: saved at once, and kept (Your songs and scores show as Anonymous to others).
    hide = "document.querySelector('[data-hide-profile] [role=switch]')"
    assert f.js(f"{hide}.getAttribute('aria-checked')") == "false", "Hide profile starts off"
    f.run(f"{hide}.click()")
    f.wait(f"{hide}.getAttribute('aria-checked') === 'true'", what="Hide profile on")
    f.wait_text(t(f, "settings.saved"))
    f.run(f"{hide}.click()")
    f.wait(f"{hide}.getAttribute('aria-checked') === 'false'", what="Hide profile off again")
    f.type("textarea", "Alto, and learning the tenor.")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait_text(t(f, "settings.saved"))
    f.go("/settings?api=mock&scenario=account.handle.check:", "document.querySelector('form')")
    f.wait("document.querySelector('input[autocomplete=username]')", what="the handle field")
    f.type("input[autocomplete=username]", "stackapella_official")
    f.wait_text(t(f, "settings.handle.reserved"))
    # A change that needs a recent sign-in.
    f.go("/settings?api=mock&scenario=account.password.update:recent_sign_in_required", "document.querySelector('form')")
    # The password's row opens its change under it (87.png).
    f.wait("document.querySelector('[data-row=password] button')", what="the password row")
    f.run("document.querySelector('[data-row=password] button').click()")
    f.wait("document.querySelector('input[autocomplete=new-password]')", what="the password form")
    f.type("input[autocomplete=new-password]", "another long password")
    f.run("document.querySelector('[data-row=password] form').requestSubmit()")
    f.wait_text(t(f, "settings.signInAgainFirst"))
    f.click_t("settings.export.again")
    f.wait_text(t(f, "settings.export.requested"))
    f.click_t("settings.delete.button")
    f.wait("document.querySelector('[role=dialog] .btn-danger')", what="the delete confirmation")
    assert f.js("document.querySelector('[role=dialog] .btn-danger').disabled"), "delete allowed without typing the word"
    f.run("document.querySelector('[role=dialog] header button').click()")
    f.click_t("settings.signOut")
    f.wait("location.pathname === '/'", what="signed out")
    f.wait_text(t(f, "auth.signIn"))
    # Forgot, then the reset link.
    f.go("/signin?mode=forgot&api=mock", "document.querySelector('form')")
    f.type("input[type=email]", "ada@example.com")
    f.click_t("auth.forgot.send")
    f.wait_text(t(f, "auth.sent.title"))
    f.go("/reset?token=demo-reset&email=ada%40example.com&api=mock", "document.querySelector('form')")
    f.wait("!location.search.includes('token')", what="the token gone from the address bar")
    f.type("input[autocomplete=new-password]", "a brand new password")
    f.click_t("auth.reset.save")
    f.wait("location.pathname === '/'", what="signed in after the reset")
    # The undo link for an email change: a new password, in one tap.
    f.go("/email/revert?token=demo-revert&api=mock", "document.querySelector('h1')")
    f.wait_text(t(f, "auth.revert.title"))
    f.click_t("auth.revert.choosePassword")
    f.wait_text(t(f, "auth.sent.title"))
    # Coming back from Google with an error, and a new Google account's age question.
    f.go("/?api=mock&authError=linked_elsewhere", "document.querySelector('header')")
    f.wait_text(t(f, "auth.back.linkedElsewhere"))
    f.wait("!location.search.includes('authError')", what="the error gone from the address bar")
    f.wait("(await import('/js/stores/account.js')).account.me", what="the account loaded")
    # A new Google account: "When were you born?" (104.png), month and year, and Continue.
    f.run("const a = (await import('/js/stores/account.js')).account; a.me.birthGiven = false;")
    f.wait_text(t(f, "auth.born.firstWhy"))
    assert f.js("document.querySelectorAll('[data-birth] select').length") == 2, "a month and a year"
    f.shot("age")
    f.run("""const [month, year] = document.querySelectorAll('[data-birth] select');
        month.value = '3'; month.dispatchEvent(new Event('change')); year.value = '1990'; year.dispatchEvent(new Event('change'));""")
    f.click_t("auth.continue")
    f.wait(f"!document.querySelector('[data-birth]')", what="the birth date answered")


if __name__ == "__main__":
    run("accounts", journey)
