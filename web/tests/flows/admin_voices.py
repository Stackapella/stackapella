"""Lend your voice's moderation in Admin (docs/lend-your-voice-plan.md, "Moderation"), on the
contract's examples: Guides shows a voice's reports and removes one for abuse (the volunteer can't
lend again), and lists the published course clips with Remove; a take can be refused for abuse.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CAPTURE = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js'); window.sent = [];
    api.useTransport(async (url, init = {}) => { window.sent.push({ op: init.operation, body: init.body ? JSON.parse(init.body) : null }); return mockFetch(url, init); });"""


def journey(f):
    f.go("/admin/guides?api=mock&scenario=&as=admin", "document.querySelector('main h1')")
    f.wait("document.querySelector('[data-admin-clips] [data-admin-clip]')", what="the course clips")
    f.shot("guides")
    f.run(CAPTURE)
    f.run("[...document.querySelectorAll('main button')].find((b) => b.textContent.trim() === " + json.dumps(f.js("(await import('/js/i18n.js')).t('admin.guides.remove')")) + ").click()")
    f.wait("document.querySelector('[data-remove-abuse]')", what="the remove sheet")
    f.run("document.querySelector('[data-remove-abuse]').click()")
    f.shot("remove-abuse")
    f.run("document.querySelector('[role=dialog] button[type=submit]').click()")
    f.wait("window.sent.some((s) => s.op === 'admin.beta.guides.delete')", what="the guide removed")
    assert f.js("window.sent.find((s) => s.op === 'admin.beta.guides.delete').body?.abuse") is True, f.js("window.sent")


if __name__ == "__main__":
    run("admin_voices", journey)
