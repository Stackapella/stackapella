"""The support inbox (admin.support.*): an admin sees the open messages, opens one with its details,
starts a reply from a saved reply, sends it (closing the message), and can reopen it. Against the
mock (its examples).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/support?api=mock&as=admin", "document.querySelector('main a[href^=\"/admin/support/\"]')")
    f.run("document.querySelector('main a[href^=\"/admin/support/\"]').click()")
    f.wait("document.querySelector('#message-heading') && document.querySelector('main dl')", what="the message, with its details")
    f.click("The mic is silent", "button")
    f.wait("document.querySelector('main textarea').value.startsWith('Thanks for writing')", what="a saved reply to start from")
    f.shot("message")
    f.run("window.mockCalls = [];")
    f.click(t(f, "admin.support.send"), "button")
    f.wait("window.mockCalls.includes('admin.support.reply')", what="the reply sent")
    f.wait_text(t(f, "admin.support.sent"))

    # A director's learning tracks request (backend e3af29f09): the piece, the score's files, what they confirmed, and
    # Give this song from the row, which sends the arranger's file with their confirmation and the request's id.
    f.go("/admin/support?api=mock&as=admin", "document.querySelector('[data-tracks-only]')")
    f.run("document.querySelector('[data-tracks-only]').click()")
    f.wait("document.querySelector('[data-tracks-only]').getAttribute('aria-pressed') === 'true' && document.querySelector('[data-tracks-row]')", what="the tracks requests")
    f.run("""const { api } = await import('/js/api.js');
        const row = (await api.admin.support.list({ query: { category: 'tracks' } })).data.find((m) => m.tracks);
        window.tracksMessage = { ...row, body: row.preview, context: null, replies: [], savedReplies: [], tracks: { ...row.tracks, scores: row.tracks.scores.map((s) => ({ ...s, url: 'https://example.com/score.pdf' })) } };
        api.admin.support.show = async () => ({ data: window.tracksMessage });
        window.gave = null;
        api.admin.people.giveSong = async (id, body) => { window.gave = { id, ...body };
            window.tracksMessage = { ...window.tracksMessage, status: 'closed', tracks: { ...window.tracksMessage.tracks, status: 'delivered', song: { id: 'Gv1Sng', title: 'O God, Our Help' }, deliveredAt: new Date().toISOString() } };
            return { data: { id: 'Gv1Sng', title: 'O God, Our Help' } }; };""")
    f.run("document.querySelector('[data-tracks-row]').closest('a').click()")
    f.wait("document.querySelector('[data-tracks-request] [data-tracks-download]')", what="the request with its score")
    assert f.js("document.querySelector('[data-tracks-rights]').textContent").startswith("I confirm this piece is in the public domain")
    assert f.js("document.querySelector('[data-tracks-status]').innerText.trim()") == t(f, "admin.support.tracks.status.received")
    f.shot("tracks-request")
    f.run("document.querySelector('[data-tracks-give]').click()")
    f.wait("document.querySelector('[role=dialog] [data-tracks-give-rights]')", what="Give this song, with their confirmation as the rights record")
    f.run("""const json = await (await fetch('/templates/amazing-grace.json')).text();
        const input = document.querySelector('[role=dialog] [data-song-file]'); const dt = new DataTransfer();
        dt.items.add(new File([json], 'o-god.json', { type: 'application/json' })); input.files = dt.files;
        input.dispatchEvent(new Event('change'));""")
    f.wait("document.querySelector('[role=dialog]').innerText.includes('o-god.json')", what="the file read")
    f.shot("tracks-give")
    f.click(t(f, "admin.confirmChange"), "[role=dialog] button")
    f.wait("window.gave && !document.querySelector('[role=dialog]')", what="the song given")
    gave = f.js("({ id: window.gave.id, trackRequest: window.gave.trackRequest, by: window.gave.rights.confirmedBy, song: !!window.gave.song })")
    assert gave == {"id": "jR4kLmNo", "trackRequest": "Tr4kQ3", "by": "Ana Ruiz <ana@example.com>, director of Thursday Singers", "song": True}, gave
    f.wait("document.querySelector('[data-tracks-delivered]')", what="the request delivered")
    assert not f.js("!!document.querySelector('[data-tracks-give]')"), "no second Give"
    f.no_sideways("a tracks request")


if __name__ == "__main__":
    run("admin_support", journey, sizes=("desktop",))
