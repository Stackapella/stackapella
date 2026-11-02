"""Growth's Experiments (admin.experiments.*, admin.funnel.show?experiment=): the registry one
experiment at a time, its card (what B changes, read by, guardrails, who, the plan), the verdict held
back until it can be read, a step through the change sheet, a new draft with the sample calculator,
and the steps per arm from the Funnel. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/experiments?api=mock&scenario=&as=admin", "document.querySelector('[data-experiment-card]')")
    # short1, a draft: what it changes, never with onb1, no verdict, and Start.
    card = f.js("document.querySelector('[data-experiment-card]').innerText")
    assert "short1" in card and "onb1" in card, card
    assert f.js("document.querySelector('[data-experiment-verdict]').dataset.verdict") == "draft"
    assert f.js("!document.querySelector('[data-funnel-steps]')"), "a draft has no steps yet"
    f.no_sideways("the experiments")
    f.shot("draft")

    # Start goes through the change sheet, then the list is read again.
    f.run("document.querySelector('[data-experiment-action=start]').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the change sheet")
    f.run("document.querySelector('[role=dialog] button[type=submit]').click()")
    f.wait("!document.querySelector('[role=dialog]')", what="started")

    # A new draft: the calculator gives the sample, and Use it fills it in.
    f.run("document.querySelector('[data-experiments-new]').click()")
    f.wait("document.querySelector('[data-experiment-calculator]')", what="the new experiment's sheet")
    f.type("[data-calc-base]", "20")
    f.type("[data-calc-lift]", "5")
    f.wait("/\\d/.test(document.querySelector('[data-calc-result]').textContent)", what="the sample")
    f.no_sideways("the new experiment's sheet")
    f.shot("new")
    f.press("Escape")
    f.wait("!document.querySelector('[role=dialog]')", what="closed")

    # Another one, running or read: its steps per arm, the Funnel's own, with no second title.
    f.run("""const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
        api.useTransport(async (url, init = {}) => { const answer = await mockFetch(url, init); if (init.operation !== 'admin.experiments.list') return answer;
            const json = await answer.json(); json.data.find((e) => e.id === 'onb1').status = 'running'; json.data.find((e) => e.id === 'onb1').startsOn = '2026-11-10';
            return new Response(JSON.stringify(json), { status: 200, headers: { 'Content-Type': 'application/json' } }); });
        const { navigate } = await import('/js/router.js'); navigate('/admin/growth'); await new Promise((r) => setTimeout(r, 300)); navigate('/admin/experiments');""")
    f.wait("document.querySelector('[data-experiments-pick]')", what="the list again")
    f.run("const s = document.querySelector('[data-experiments-pick]'); s.value = 'onb1'; s.dispatchEvent(new Event('change'));")
    f.wait("document.querySelector('[data-experiment-verdict]')?.dataset.verdict === 'running'", what="running: no verdict yet")
    f.wait("document.querySelector('[data-funnel-steps] li')", what="onb1's steps per arm")
    assert f.js("!document.querySelector('#funnel-h') && !document.querySelector('[data-funnel-about]')"), "the card says it once"
    f.no_sideways("an experiment's results")
    f.shot("results")


if __name__ == "__main__":
    run("admin_experiments", journey, sizes=("desktop", "phone"))
