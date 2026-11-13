"""Admin > Review's waiting takes by song part (open guide voices, slice 2b; admin.beta.submissions.list?groupBy=part):
each part a block with its voice now and one tap to hear it, its waiting takes under it; any other status stays one
list. Accepting one as the part's voice says how many other takes of the part will be told they weren't chosen, and
takes one note for them (notChosenNote). The mock's example has one take: a second singer's is added here.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STUB = """const { api } = await import('/js/api.js'); const list = api.admin.beta.submissions.list; const accept = api.admin.beta.submissions.accept;
window.listQueries = []; window.accepted = [];
api.admin.beta.submissions.list = async (q = {}) => {
    window.listQueries.push(q);
    const page = await list({ ...q, groupBy: undefined });
    const first = page.data[0];
    if (first && !page.data.some((x) => x.id === 'Tz4Kq9')) page.data.push({ ...structuredClone(first), id: 'Tz4Kq9', singer: { ...first.singer, id: 'Mk7Pq2', name: 'Kim Ode' }, createdAt: '2026-11-12T09:00:00Z' });
    if (q.groupBy === 'part') page.meta = { ...(page.meta ?? {}), groups: [{ templateId: first.template.id, title: first.template.title, version: first.template.version, partKey: first.partKey, partName: 'Alto', guide: { submission: 'Gd3Vx1', singer: { name: 'Sarah K.' } }, takes: page.data.map((x) => x.id) }] };
    return page;
};
api.admin.beta.submissions.accept = async (id, body) => { window.accepted.push({ id, ...body }); return accept(id, body); };"""


def journey(f):
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run(STUB)
    f.run("(await import('/js/router.js')).navigate('/admin/review')")
    f.wait("document.querySelector('[data-review-groups] [data-review-group=alto]')", what="the waiting takes by part")
    assert f.js("window.listQueries.at(-1).groupBy") == "part"
    assert f.js("document.querySelectorAll('[data-review-group=alto] li').length") == 2
    assert f.js("document.querySelector('[data-group-voice]').textContent").find("Sarah K.") >= 0
    assert f.js("document.querySelector('[data-group-hear]').getAttribute('href')") == "/admin/review/Gd3Vx1"
    f.no_sideways("Review by part")
    f.shot("review-groups")
    # Any other status: one list, as before.
    f.run("const s = document.querySelector('#review-status'); s.value = 'accepted'; s.dispatchEvent(new Event('change'))")
    f.wait("window.listQueries.at(-1).status === 'accepted' && !window.listQueries.at(-1).groupBy", what="no groups for accepted")
    f.run("const s = document.querySelector('#review-status'); s.value = 'waiting'; s.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('[data-review-group=alto] li a')", what="back to the groups")

    # A take accepted as the part's voice: the other waiting take is counted, with a note for its singer.
    f.run("document.querySelector('[data-review-group=alto] li a').click()")
    f.wait("location.pathname.startsWith('/admin/review/')", what="the take")
    f.click(f.js("(await import('/js/i18n.js')).t('admin.review.accept')"), timeout=20)
    f.wait("document.querySelector('[data-not-chosen-count]')", what="the other take counted")
    f.run("const n = document.querySelector('[data-not-chosen-note]'); n.value = 'Thanks for singing it. Try the tenor next?'; n.dispatchEvent(new Event('input'))")
    f.shot("accept-not-chosen")
    f.run("document.querySelector('[role=dialog] form button[type=submit]').click()")
    f.wait("window.accepted.length", what="accepted")
    assert f.js("window.accepted.at(-1).notChosenNote") == "Thanks for singing it. Try the tenor next?", f.js("window.accepted.at(-1)")


run("admin_review_groups", journey)
