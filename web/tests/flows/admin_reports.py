"""The admin's reports (M6), against the mock: reported links with their reports and player; Take
down asks why (a copyright notice) and for a note, then the link shows as taken down.
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
    f.go("/admin/reports?api=mock&scenario=", "document.querySelector('main')")
    f.wait_text("Silent Night for Mum")
    f.wait_text(t(f, "admin.reports.reason.copyright"))
    f.shot("reports")
    f.click_t("admin.reports.hide")
    f.wait("document.querySelector('[role=dialog] textarea')", what="the note")
    f.type("[role=dialog] textarea", "Notice from the label, 26 October")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "admin.saved"))
    f.wait_text(t(f, "admin.reports.hidden"))
    f.shot("taken-down")


if __name__ == "__main__":
    run("admin_reports", journey)
