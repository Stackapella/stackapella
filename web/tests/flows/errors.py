"""Error reporting (js/errors.js): an error thrown in the app goes to /log/errors once, as a Sentry
envelope with our DSN, and carries no name, email or address token; the same error again sends nothing."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

DSN = "https://public@o1.ingest.sentry.io/2"


def journey(f):
    f.expected_errors.append("A flow test error")  # thrown on purpose, to be reported
    f.go("/invite/SeCrEtToKeN?api=mock&as=member", "document.querySelector('#app')")
    f.wait("(await import('/js/stores/account.js')).account.me", what="signed in")
    me = f.js("(async () => { const { account } = await import('/js/stores/account.js'); account.config = { ...(account.config ?? {}), sentryDsn: " + json.dumps(DSN) + " }; return { name: account.me.name, email: account.me.email }; })()")
    f.run("""window.reports = []; const real = window.fetch; window.fetch = (url, init) => { if (String(url).endsWith('/log/errors')) { window.reports.push(init.body); return Promise.resolve(new Response('', { status: 200 })); } return real(url, init); };""")
    f.run("window.boom = () => { throw new Error('A flow test error'); }; setTimeout(window.boom); setTimeout(window.boom, 50);")
    f.wait("window.reports.length >= 1", what="one report sent")
    f.js("new Promise((r) => setTimeout(r, 300))")
    reports = f.js("window.reports")
    assert len(reports) == 1, f"the same error was sent {len(reports)} times"
    header, item, body = reports[0].strip().split("\n")
    assert json.loads(header)["dsn"] == DSN, header
    event = json.loads(body)
    assert event["exception"]["values"][0]["value"] == "A flow test error", body
    for secret in (me["name"], me["email"], "SeCrEtToKeN"):
        assert secret and secret not in reports[0], f"the report carries {secret!r}"


if __name__ == "__main__":
    run("errors", journey)
