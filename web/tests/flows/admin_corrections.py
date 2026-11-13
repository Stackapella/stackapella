"""Inbox > Corrections (docs/corrections-plan.md): readers' suggested corrections, To read first; the State and Kind
filters ask again and are remembered; each item links to its page in a new tab; Accept, Reject (a reason required)
and Mark done go through a styled sheet naming the action, and an item that leaves the filter leaves the list. Home's
What needs you now has a Corrections row, and the menu badges the inbox. Its answers are fixtures
(tests/fixtures/corrections/) until backend's examples are in the contract.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

STUB = """const { api } = await import('/js/api.js');
const get = async (name) => (await fetch('/tests/fixtures/corrections/' + name + '.json')).json();
const [list, updated] = await Promise.all([get('admin.corrections.list'), get('admin.corrections.update')]);
api.admin.corrections.list = async (q) => {
    (window.correctionQueries ??= []).push(q);
    const answer = structuredClone(list);
    answer.data = answer.data.filter((c) => (!q.state || c.state === q.state) && (!q.kind || c.kind === q.kind));
    return answer;
};
api.admin.corrections.update = async (id, body) => {
    (window.correctionUpdates ??= []).push({ id, ...body });
    const item = structuredClone(list.data.find((c) => c.id === id) ?? updated.data);
    item.state = body.state; item.decision = { state: body.state, note: body.note, by: { name: 'Hillel Coren' }, at: '2026-11-12T16:00:00Z' };
    return { data: item };
};
const overview = api.admin.overview;
api.admin.overview = async () => { const answer = await overview(); answer.data.corrections = { new: 3 }; return answer; };"""


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/sources?api=mock&as=admin", "document.querySelector('h1')")
    f.run("Object.keys(localStorage).filter((k) => k.startsWith('stackapella.admin.')).forEach((k) => localStorage.removeItem(k))")
    f.run(STUB)
    # Home: What needs you now has the corrections to read, a row to the inbox; the menu badges the inbox.
    f.run("(await import('/js/router.js')).navigate('/admin')")
    f.wait("document.querySelector('[data-needs-corrections-row] [data-count]')?.textContent.trim() === '3'", what="Home's corrections row")
    assert f.js("document.querySelector('[data-needs-corrections-row]').textContent").find(t(f, "admin.needs.corrections", {"count": 3})) >= 0
    f.run("document.querySelector('[data-needs-corrections-row]').click()")
    f.wait("document.querySelectorAll('[data-corrections-list] [data-correction]').length === 3", what="the three to read")
    assert f.js("window.correctionQueries.at(-1).state") == "new"
    # Each item: its page in a new tab, the kind and state, the reader's words, their source when given, a language tag.
    link = "document.querySelector('[data-correction=c7Hq2] [data-correction-page]')"
    assert f.js(f"{link}.getAttribute('href')") == "/songs/amazing-grace" and f.js(f"{link}.target") == "_blank"
    assert f.js("!!document.querySelector('[data-correction=c7Hq2] [data-correction-source]')")
    assert f.js("document.querySelector('[data-correction=c7Hq4] [data-correction-lang]').textContent.trim().toLowerCase()") == "es"
    assert f.js("[...document.querySelectorAll('[data-correction=c7Hq2] [data-act]')].map((b) => b.dataset.act)") == ["accepted", "rejected"]
    f.no_sideways("Corrections")
    f.shot("corrections")

    # Reject needs a reason: the sheet names the action, and its button waits for the words.
    f.run("document.querySelector('[data-correction=c7Hq3] [data-act=rejected]').click()")
    f.wait("document.querySelector('[data-corrections-sheet]')", what="the sheet")
    assert f.js("document.querySelector('[data-corrections-confirm]').disabled"), "a reason first"
    assert f.js("document.querySelector('[data-corrections-confirm]').textContent.trim()") == t(f, "admin.corrections.act.reject")
    f.shot("reject-sheet")
    f.run("const n = document.querySelector('[data-corrections-note]'); n.value = 'The 1818 printing has B.'; n.dispatchEvent(new Event('input'))")
    f.run("document.querySelector('[data-corrections-confirm]').click()")
    f.wait("!document.querySelector('[data-corrections-sheet]') && !document.querySelector('[data-correction=c7Hq3]')", what="rejected, and gone from To read")
    assert f.js("window.correctionUpdates.at(-1)") == {"id": "c7Hq3", "state": "rejected", "note": "The 1818 printing has B."}

    # Accept with no note: it's optional.
    f.run("document.querySelector('[data-correction=c7Hq2] [data-act=accepted]').click()")
    f.wait("document.querySelector('[data-corrections-confirm]') && !document.querySelector('[data-corrections-confirm]').disabled", what="accept needs no note")
    f.run("document.querySelector('[data-corrections-confirm]').click()")
    f.wait("!document.querySelector('[data-correction=c7Hq2]')", what="accepted, and gone from To read")
    assert f.js("window.correctionUpdates.at(-1).note") is None

    # The filters: Accepted and A fact ask again, and are remembered.
    f.run("const s = document.querySelector('[data-corrections-state]'); s.value = 'accepted'; s.dispatchEvent(new Event('change'))")
    if f.size == "phone":
        f.run("const k = document.querySelector('[data-corrections-kind-select]'); k.value = 'other'; k.dispatchEvent(new Event('change'))")
    else:
        f.run("document.querySelector('[data-corrections-kind] [data-kind=other]').click()")
    f.wait("window.correctionQueries.at(-1).state === 'accepted' && window.correctionQueries.at(-1).kind === 'other'", what="both filters asked")
    f.wait("document.querySelector('[data-correction=c7Hq5] [data-correction-decision]')", what="the accepted one with its decision")
    assert f.js("[...document.querySelectorAll('[data-correction=c7Hq5] [data-act]')].map((b) => b.dataset.act)") == ["done", "rejected"]
    assert f.js("localStorage.getItem('stackapella.admin.corrections.state')") == "accepted"
    assert f.js("localStorage.getItem('stackapella.admin.corrections.kind')") == "other"
    f.shot("accepted")


run("admin_corrections", journey)
