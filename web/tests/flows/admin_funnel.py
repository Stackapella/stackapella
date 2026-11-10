"""Funnel (admin.funnel.show): the onboarding funnel from the anonymous counts, every device over a
range, or one experiment's arms side by side with the difference and its interval, the 50/50 split
check and whether it's ready to read. Against the mock (its example).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/funnel?api=mock&as=admin", "document.querySelector('[data-funnel-steps] li')")
    f.shot("everyone")
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.funnel.show; api.admin.funnel.show = (q) => { (window.funnelQueries ??= []).push(q); return show(q); };")
    f.run("const s = document.querySelector('[data-funnel-experiments]'); s.value = 'onb1'; s.dispatchEvent(new Event('change'));")
    f.wait("window.funnelQueries?.at(-1)?.experiment === 'onb1' && document.querySelector('[data-funnel-about]')", what="the experiment's status card")
    # Each step: an A and a B bar, each lettered (never colour alone), and B minus A in points with its range.
    f.wait("document.querySelectorAll('[data-funnel-steps] li')[1]?.querySelectorAll('.rounded-full.bg-raised').length === 2", what="A and B bars")
    assert f.js("document.querySelector('[data-funnel-legend]').innerText.includes('A') && document.querySelector('[data-funnel-legend]').innerText.includes('B')"), "the arms named in the legend"
    diff = f.js("[...document.querySelectorAll('[data-funnel-difference]')].map((d) => d.innerText).find((x) => x.includes('pts'))")
    assert diff and " to " in diff, f"the difference and its range: {diff!r}"
    # "lost here" only between steps that follow one another: never a negative share.
    lost = f.js("[...document.querySelectorAll('[data-funnel-lost]')].map((l) => l.innerText)")
    assert lost and not any("-" in x for x in lost), f"lost lines: {lost}"
    f.wait("document.querySelector('[data-funnel-asks] tbody tr')", what="the asks")
    f.no_sideways("the funnel")
    f.shot("funnel")
    # Every ask the app counts (js/funnel.js ASKS, config counts.asks) has words: the account nudges and the course wall by
    # place too, never a raw key like acct_course_map.
    f.run("""const { api } = await import('/js/api.js'); const { ASKS } = await import('/js/funnel.js'); const show = api.admin.funnel.show;
        api.admin.funnel.show = async (q) => { const r = await show(q); const arms = r.data.asks[0].arms.map((a) => ({ ...a, shown: 4, yes: 1, rate: 0.25 }));
          return { ...r, data: { ...r.data, asks: ASKS.map((ask) => r.data.asks.find((a) => a.ask === ask) ?? { ask, arms }) } }; };""")
    f.run("const s = document.querySelector('[data-funnel-experiments]'); s.value = ''; s.dispatchEvent(new Event('change'));")
    f.wait("document.querySelectorAll('[data-funnel-asks] tbody tr').length >= 16", what="every ask's row")
    raw = f.js("[...document.querySelectorAll('[data-funnel-asks] tbody tr th')].map((th) => th.textContent.trim()).filter((x) => /^[a-z]+(_[a-z]+)+$/.test(x) || /^[a-z]+$/.test(x))")
    assert not raw, f"asks shown as their keys: {raw}"
    f.shot("funnel-asks")


if __name__ == "__main__":
    run("admin_funnel", journey, sizes=("desktop", "phone"))
