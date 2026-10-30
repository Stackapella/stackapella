"""mudeo email in admin (docs/mudeo-transition-plan.md), against the mock: the counts and today's
count against the cap; Send a test to me; Send the next 50 (asked first) and the batch's result;
search, and paste several addresses to mark; the early-access link with its count and Pause. Then
the link's own page, /early/<code>: open, it leads to making an account.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin/mudeo?api=mock&scenario=&as=admin", "document.querySelector('main')")
    f.wait_text(t(f, "admin.mudeo.countsTitle"))
    f.wait_text("3,790")
    f.wait_text(t(f, "admin.mudeo.today", {"sent": "150", "cap": "200"}))
    f.shot("counts")
    f.click_t("admin.mudeo.test")
    f.wait_text(t(f, "admin.mudeo.testSent", {"email": ""}).split("{")[0].strip()[:20])
    f.click_t("admin.mudeo.send", {"count": 50})
    f.wait("document.querySelector('[role=dialog]')", what="the question first")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("document.body.innerText.includes('48')", what="the batch's result")
    f.shot("sent")
    f.type("#mudeo-search", "ana")
    f.run("document.querySelector('form[role=search]').requestSubmit()")
    f.wait_text("ana@example.com")
    f.type("#mudeo-paste", "one@example.org, two@example.org")
    f.click_t("admin.mudeo.markBounced", {"count": 2})
    f.wait("document.body.innerText.match(/marked/i)", what="marked")
    f.wait_text(t(f, "admin.mudeo.linkTitle"))
    f.wait_text(t(f, "admin.mudeo.joined", {"joined": "12", "cap": "500"}))
    f.click_t("admin.mudeo.pause")
    f.shot("link")
    # The link's page.
    f.go("/early/k3Jd8sQw2mZx7Lp9Vb4nRt6yHc1Ga5Ue0FiOoPqA?api=mock&as=signedOut", "document.querySelector('h1')")
    f.wait_text(t(f, "invite.headline"))
    f.shot("early")
    # Start singing leads; making an account is the second choice, behind its own button.
    f.click_t("early.makeAccount")
    f.wait("document.querySelector('#invite-email')", what="the account form")
    f.type("#invite-email", "sam@example.com")
    f.click(t(f, "invite.accept"), "button")
    f.wait_text(t(f, "invite.sent.title"))


if __name__ == "__main__":
    run("admin_mudeo", journey)
