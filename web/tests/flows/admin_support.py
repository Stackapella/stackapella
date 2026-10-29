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


if __name__ == "__main__":
    run("admin_support", journey, sizes=("desktop",))
