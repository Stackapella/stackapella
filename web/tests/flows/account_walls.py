"""One account wall everywhere (docs/account-nudges-plan.md, the walls and Settings): a guest who
reaches for something an account is for is told why, that their songs and progress come along, and
gets Make a free account (sign-up titled with that reason) and I have an account. Never a dead end,
never "Sign in" with no reason. Settings for a guest says what's only on this device.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
WALLS = [("/groups", "groups", "groups"), ("/settings/reminders", "reminders", "reminders"), ("/voices", "voice", "voices"), ("/storage", "songs", "storage")]


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/settings?api=mock&scenario=&as=signedOut", "document.querySelector('#app main, #app header')")
    f.run("sessionStorage.setItem('stackapella.mockLendVoice', '1');")  # Lend your voice open, so its wall shows
    f.wait("document.querySelector('[data-guest-settings]') || document.querySelector('[data-wall-sign-up]') || document.body.innerText.includes('Sign in')", what="Settings for a guest")
    f.shot("settings")
    for path, reason, name in WALLS:
        f.go(f"{path}?api=mock&scenario=&as=signedOut", "document.querySelector('#app header, #app main')")
        f.js("new Promise((r) => setTimeout(r, 700))")
        f.shot(name)
    # After: every wall the same, with its reason.
    f.go("/settings?api=mock&scenario=&as=signedOut", "document.querySelector('[data-guest-settings]')")
    assert t(f, "settings.guest.title") in f.js("document.querySelector('[data-guest-settings]').textContent")
    for path, reason, name in WALLS:
        f.go(f"{path}?api=mock&scenario=&as=signedOut", f"document.querySelector('[data-account-wall][data-reason={reason}]')")
        href = f.js("document.querySelector('[data-wall-sign-up]').getAttribute('href')")
        assert f"for={reason}" in href and "mode=signup" in href, href
    f.run("document.querySelector('[data-wall-sign-up]').click()")
    f.wait("location.pathname === '/signin'", what="sign-up")
    f.wait_text(t(f, "auth.reason.songs"))


if __name__ == "__main__":
    run("account_walls", journey)
