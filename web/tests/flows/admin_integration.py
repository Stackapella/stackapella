"""M1's integration check, against the real server (API=http://127.0.0.1:8000 tool/flows.sh
admin_integration): sign in through /dev/login as the seeded admin, see the name from admin.me,
then the trust and safety screens on real data: change a switch with a reason (and back), and find
the change in the audit log. Needs the backend's demo data (php artisan db:seed).
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def journey(f):
    f.expected_errors.append("401 (Unauthorized) http://127.0.0.1")  # admin.me before signing in
    f.go("/admin?api=real", "document.readyState === 'complete'")
    f.wait("location.pathname === '/admin/sign-in'", what="signed out: the server's sign-in page")
    f.go("/dev/login", "document.querySelector('[data-dev-login]')")
    f.run("document.querySelector('[data-dev-login]').click()")
    f.wait("location.pathname === '/admin' && document.querySelector('main h2')", what="the admin, signed in")
    name = f.js("document.querySelector('main h2').textContent")
    assert "Hillel" in name, name
    f.shot("signed-in")
    f.click_t("admin.nav.safety")
    f.wait("document.querySelectorAll('li.card [aria-pressed]').length > 1", what="the switches")
    f.shot("switches")
    shield = "[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes(" + f.js(f"JSON.stringify({I18N}.t('admin.flag.shield_mode'))") + "))"
    for value, reason in ((1, "Integration check: on"), (0, "Integration check: off")):
        f.run(f"{shield}.querySelectorAll('[aria-pressed]')[{value}].click()")
        f.wait("document.querySelector('[role=dialog] textarea')", what="the reason")
        f.type("[role=dialog] textarea", reason)
        f.click_t("admin.confirmChange")
        f.wait(f"!document.querySelector('[role=dialog]') && {shield}.textContent.includes('{reason}')", what=f"the switch saved: {reason}")
    for tab in ("admin.safety.tab.limits", "admin.safety.tab.blocklist", "admin.safety.tab.events"):
        f.click_t(tab)
        f.wait("document.querySelector('table') || document.querySelector('main .card.text-center')", what=tab)
    f.shot("events")
    f.click_t("admin.nav.group.system")
    f.click_t("admin.nav.audit")
    f.wait("document.querySelector('table') && document.querySelector('table').textContent.includes('Integration check: off')", what="the change in the audit log")
    f.shot("audit")


if __name__ == "__main__":
    run("admin_integration", journey)
