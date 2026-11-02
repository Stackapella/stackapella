"""The sign-in confirm step (login CSRF; Hillel: "Yes, add the confirm screen"): a sign-in link (or a
verify link) for another account than this browser's, or asked for elsewhere, answers 409
confirm_sign_in without being used up; the app asks "Sign in as kim@example.com?", Not now stays put,
and Yes sends the token again with confirm: true.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
# The first try answers 409 confirm_sign_in; the second (with confirm: true) is the mock's own answer.
ASK_FIRST = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    window.sent = [];
    api.useTransport(async (url, init = {}) => {
        if (init.operation === %s) {
            const body = JSON.parse(init.body || '{}'); window.sent.push(body);
            if (!body.confirm) return new Response(JSON.stringify({ message: 'Conflict.', code: 'confirm_sign_in', email: 'kim@example.com' }), { status: 409, headers: { 'Content-Type': 'application/json' } });
        }
        return mockFetch(url, init);
    });"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_in_app(f, path, token):
    """As the app keeps a link it was opened with (stores/account.js), then on to its page."""
    f.run(f"sessionStorage.setItem('stackapella.link', JSON.stringify({{ path: {json.dumps(path)}, token: {json.dumps(token)}, email: '' }})); (await import('/js/router.js')).navigate({json.dumps(path)})")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run(ASK_FIRST % json.dumps("auth.link.open"))
    open_in_app(f, "/signin/link", "Lk8mQ2pL7vR4tB8nW3cJ5hYz")
    f.wait("document.querySelector('[data-confirm-sign-in]')", what="the question")
    assert t(f, "auth.confirm.title", {"email": "kim@example.com"}) in f.js("document.querySelector('[data-confirm-sign-in]').textContent")
    f.shot("confirm")
    f.run("document.querySelector('[data-confirm-yes]').click()")
    f.wait("window.sent.length === 2 && window.sent[1].confirm === true && !document.querySelector('[data-confirm-sign-in]')", what="Yes sends it again, confirmed")
    assert f.js("window.sent[0].token === window.sent[1].token"), "the same link"

    # The verify link too; Not now stays put (nothing sent again).
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run(ASK_FIRST % json.dumps("auth.verify"))
    open_in_app(f, "/verify", "Vf3kQ9mN2pL8xT4b")
    f.wait("document.querySelector('[data-confirm-sign-in]')", what="the question, from verify")
    f.run("document.querySelector('[data-confirm-no]').click()")
    f.wait("location.pathname === '/'", what="Not now: home, still the same account")
    assert f.js("window.sent.length") == 1, "nothing sent on Not now"


if __name__ == "__main__":
    run("confirm_sign_in", journey)
