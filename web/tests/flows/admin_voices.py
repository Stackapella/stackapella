"""Lend your voice's moderation in Admin (docs/lend-your-voice-plan.md, "Moderation"), on the
contract's examples: Guides shows a voice's reports and removes one for abuse (the volunteer can't
lend again), and lists the published course clips with Remove; a take can be refused for abuse.
A singer whose take is live can be asked for a new take (Guides and Beta review), and a live take
withdrawn from Beta review, each through a sheet naming the singer and the part.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CAPTURE = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js'); window.sent = [];
    api.useTransport(async (url, init = {}) => { window.sent.push({ op: init.operation, body: init.body ? JSON.parse(init.body) : null }); return mockFetch(url, init); });"""


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/guides?api=mock&scenario=&as=admin", "document.querySelector('main h1')")
    f.wait("document.querySelector('[data-admin-clips] [data-admin-clip]')", what="the course clips")
    f.shot("guides")
    f.run(CAPTURE)
    alto = t(f, "parts.alto")
    # Ask for a new take: the singer whose Alto is live records it again; their take stays live meanwhile.
    f.run("document.querySelector('[data-ask-new-take]').click()")
    f.wait("document.querySelector('[role=dialog] [data-new-take-note]')", what="the new take sheet")
    title = f.js("document.querySelector('[role=dialog] h2').textContent")
    assert alto in title and "Sarah Kim" in title, title
    assert f.js("document.querySelector('[data-new-take-note]').value") == t(f, "admin.newTake.note")
    f.shot("new-take")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.sent.some((s) => s.op === 'admin.beta.assignments.again') && !document.querySelector('[role=dialog]') && document.querySelector('[data-guide-withdraw]')", what="asked for a new take, the sheet closed")
    assert f.js("window.sent.find((s) => s.op === 'admin.beta.assignments.again').body?.note") == t(f, "admin.newTake.note")
    # Withdraw this take: named by singer, part and song, then down (here for abuse).
    f.run("document.querySelector('[data-guide-withdraw]').click()")
    f.wait("document.querySelector('[data-remove-abuse]')", what="the remove sheet")
    named = f.js("document.querySelector('[data-remove-abuse]').closest('[role=dialog]').querySelector('h2').textContent")
    assert "Amazing Grace" in named and alto in named, named
    f.run("document.querySelector('[data-remove-abuse]').click()")
    f.shot("remove-abuse")
    f.run("document.querySelector('[role=dialog] button[type=submit]').click()")
    f.wait("window.sent.some((s) => s.op === 'admin.beta.guides.delete')", what="the guide removed")
    assert f.js("window.sent.find((s) => s.op === 'admin.beta.guides.delete').body?.abuse") is True, f.js("window.sent")

    # Beta review, an accepted take that's live: Ask for a new take and Withdraw this take.
    f.run("""const { api } = await import('/js/api.js'); const show = api.admin.beta.submissions.show;
        api.admin.beta.submissions.show = async (id) => { const r = await show(id); return { ...r, data: { ...r.data, id: 'Tz4Kq8', status: 'accepted', publish: 'published', reviewedAt: new Date().toISOString() } }; };
        window.sent = []; (await import('/js/router.js')).navigate('/admin/review/Tz4Kq8');""")
    f.wait("document.querySelector('[data-review-new-take]') && document.querySelector('[data-review-withdraw]')", what="the accepted take's actions")
    f.shot("review-accepted")
    f.run("document.querySelector('[data-review-new-take]').click()")
    f.wait("document.querySelector('[role=dialog] [data-new-take-note]')", what="the new take sheet")
    assert alto in f.js("document.querySelector('[role=dialog] h2').textContent")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.sent.some((s) => s.op === 'admin.beta.assignments.again') && !document.querySelector('[role=dialog]')", what="asked from review")
    f.run("document.querySelector('[data-review-withdraw]').click()")
    f.wait("document.querySelector('[role=dialog] h2')?.textContent.includes('Amazing Grace')", what="the withdraw sheet, named")
    assert alto in f.js("document.querySelector('[role=dialog] h2').textContent")
    f.shot("review-withdraw")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.sent.some((s) => s.op === 'admin.beta.guides.delete')", what="withdrawn from review")
    assert f.js("window.sent.find((s) => s.op === 'admin.beta.guides.delete').body") in ({}, None), f.js("window.sent")


if __name__ == "__main__":
    run("admin_voices", journey)
