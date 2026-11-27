"""Admin > Support after the feedback plan (docs/feedback-plan.md, backend 196bfed48), against the mock: the statuses
worded New, Replied and Closed; a message says where it was opened from and what came with it, worded; a guest who left
no email is "No reply address", with no reply box, only Close; Beta notes only lists beta singers' notes, which say
their song, part and beat and can be answered; Maybe spam lists those, still shown, marked quietly.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

INBOX = "/admin/support?api=mock&as=admin"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def sent(f, key):
    return f.js(f"document.querySelector('[data-sent-with] [data-sent={json.dumps(key)}]')?.textContent.trim() ?? null")


def journey(f):
    f.go(INBOX, "document.querySelector('main a[href=\"/admin/support/Sm4kP1\"]')")
    # New, Replied, Closed, All: the wire's open, answered and closed.
    words = f.js("[...document.querySelectorAll('[data-support-filter]')].map((b) => b.textContent.trim())")
    assert words == [t(f, "admin.support.filter.open"), t(f, "admin.support.filter.answered"), t(f, "admin.support.filter.closed"), t(f, "admin.support.filter.all")] and words[0] == "New", words
    assert t(f, "admin.support.status.open") in f.js("document.querySelector('main a[href=\"/admin/support/Sm4kP1\"]').textContent"), "a new message says New"
    # The guest who left no email: Guest, and No reply address, quietly in the row.
    guest = "document.querySelector('main a[href=\"/admin/support/Gs3vW8\"]')"
    assert f.js(f"{guest}.querySelector('[data-no-reply]').textContent.trim()") == t(f, "admin.support.noReply")
    assert t(f, "admin.support.guest") in f.js(f"{guest}.textContent") and t(f, "admin.support.moment.site_404") in f.js(f"{guest}.textContent")
    f.shot("inbox")

    # A message in full: where it was opened from, and what came with it, worded.
    f.run("document.querySelector('main a[href=\"/admin/support/Sm4kP1\"]').click()")
    f.wait("document.querySelector('[data-sent-with] [data-sent=moment]')", what="the message, with what was sent with it")
    assert sent(f, "moment") == t(f, "admin.support.moment.mic"), sent(f, "moment")
    assert sent(f, "device") == f"{t(f, 'admin.support.device.phone')}, Safari", sent(f, "device")
    assert sent(f, "language") == "English" and sent(f, "skin") == t(f, "admin.support.skin.serious"), (sent(f, "language"), sent(f, "skin"))
    assert sent(f, "audioErrors") is None, "no sound errors, no line"
    assert f.js("!!document.querySelector('main form textarea')"), "a reply address: the reply box"
    f.shot("message")

    # No reply address: said in the heading's line, no reply box, Close still there.
    f.run(f"{guest}.click()")
    f.wait("document.querySelector('[data-no-reply-note]')", what="No reply address, and no reply box")
    assert not f.js("!!document.querySelector('main form textarea')"), "no reply box without an address"
    assert f.js("document.querySelector('#message-heading').textContent.trim()") == t(f, "admin.support.guest")
    assert f.js("document.querySelector('#message-heading').parentElement.querySelector('[data-no-reply]').textContent.trim()") == t(f, "admin.support.noReply")
    assert f.js(f"[...document.querySelectorAll('main section button')].some((b) => b.textContent.trim() === {json.dumps(t(f, 'admin.support.close'))})"), "Close"
    assert sent(f, "moment") == t(f, "admin.support.moment.site_404")
    f.shot("no-reply")

    # Beta notes only: a beta singer's note, its song, part and beat, and a reply box.
    f.run("window.mockCalls = [];")
    f.run("document.querySelector('[data-support-only=beta]').click()")
    f.wait("document.querySelector('[data-support-only=beta]').getAttribute('aria-pressed') === 'true' && document.querySelectorAll('main a[href^=\"/admin/support/\"]').length === 1 && document.querySelector('main a[href=\"/admin/support/Bt7nQ2\"]')", what="the beta notes alone")
    assert t(f, "admin.support.beta") in f.js("document.querySelector('main a[href=\"/admin/support/Bt7nQ2\"]').textContent")
    f.run("document.querySelector('main a[href=\"/admin/support/Bt7nQ2\"]').click()")
    f.wait("document.querySelector('[data-sent-with] [data-sent=song]')", what="the note's song, part and beat")
    assert sent(f, "song") == t(f, "admin.support.with.atBeat", {"song": "amazing-grace, alto", "beat": "33.5"}), sent(f, "song")
    assert f.js("!!document.querySelector('main form textarea')"), "a beta note can be answered"
    f.shot("beta")
    # The same chip again: every category.
    f.run("document.querySelector('[data-support-only=beta]').click()")
    f.wait("document.querySelector('[data-support-only=beta]').getAttribute('aria-pressed') === 'false' && document.querySelectorAll('main a[href^=\"/admin/support/\"]').length > 1", what="every category again")

    # Maybe spam (?maybeSpam=1): the guest's message full of links, still listed, marked quietly.
    f.run("""const { api } = await import('/js/api.js');
        const list = api.admin.support.list; window.spamAsked = null;
        api.admin.support.list = async (options) => { const answer = await list(options);
            if (options?.query?.maybeSpam) { window.spamAsked = options.query.maybeSpam;
                return { ...answer, data: [{ id: 'Sp4mX1', category: 'other', status: 'open', from: { email: 'win@example.com', account: null }, preview: 'Great deals www.example.com/a www.example.com/b', createdAt: '2026-11-27T12:30:00Z', answeredAt: null, moment: 'help', maybeSpam: true, canReply: true }] }; }
            return answer; };""")
    f.run("document.querySelector('[data-maybe-spam-only]').click()")
    f.wait("document.querySelector('main a[href=\"/admin/support/Sp4mX1\"] [data-maybe-spam]')", what="the Maybe spam ones")
    assert f.js("window.spamAsked") == 1, f.js("window.spamAsked")
    assert f.js("document.querySelector('[data-maybe-spam-only]').getAttribute('aria-pressed')") == "true"
    # Quiet: muted words, no chip, no colour.
    assert "text-muted" in f.js("document.querySelector('[data-support-marks=Sp4mX1]').className")
    f.run("window.scrollTo(0, 0)")
    f.shot("maybe-spam")
    f.no_sideways("the support inbox")


if __name__ == "__main__":
    run("admin_support_feedback", journey)
