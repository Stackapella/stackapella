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
    f.go("/admin/funnel?api=mock&as=admin", "document.querySelector('[data-funnel-steps] tbody tr')")
    f.wait("document.querySelector('[data-funnel-experiments] [role=radio]')", what="the experiment picker")
    f.run("const { api } = await import('/js/api.js'); const show = api.admin.funnel.show; api.admin.funnel.show = (q) => { (window.funnelQueries ??= []).push(q); return show(q); };")
    f.click("onb1", "[data-funnel-experiments] button")
    f.wait("window.funnelQueries?.at(-1)?.experiment === 'onb1' && document.querySelector('[data-funnel-about]')", what="the experiment's dates and steps")
    f.wait("document.querySelectorAll('[data-funnel-steps] thead th').length === 4", what="two arms and the difference")
    f.wait("document.querySelector('[data-funnel-asks] tbody tr')", what="the asks")
    f.shot("funnel")


if __name__ == "__main__":
    run("admin_funnel", journey, sizes=("desktop", "phone"))
