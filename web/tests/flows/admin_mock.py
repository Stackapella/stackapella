"""The admin on the mock API (?api=mock): signed in from admin.me, the trust and safety tabs, a
switch changed with a reason, a block added and lifted, the audit log, and the refusals (signed
out goes to the server's sign-in; a change outside sudo goes to the second factor). Also the
styleguide, in the pseudo-language, so every word on it is checked.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from pseudo_language import UNTRANSLATED  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def journey(f):
    f.go("/admin?api=mock&scenario=", "document.querySelector('main')")
    # Hillel's Home (2026-11-11): the day, a greeting by first name, the account at the right of the same row.
    f.wait("document.querySelector('[data-home-greeting]')?.textContent.trim().endsWith(', Hillel')", what="the greeting by first name")
    assert f.js("document.querySelector('[data-home-head]').innerText").count("hillel@example.com") == 1
    # The example's second-factor check has run out: one line, with the way to confirm it.
    assert f.js("document.querySelector('[data-home-sudo] a').getAttribute('href')") == "/admin/second-factor?sudo=1&intended=%2Fadmin"
    # What needs you now: the total beside the title, and a card per kind; the five numbers as one panel.
    f.wait("document.querySelector('[data-needs-total]')?.textContent.trim()", what="the total")
    assert not f.js("!!document.querySelector('[data-admin-needs] [data-needs-voices], [data-admin-needs] [data-needs-part]')"), "the voices aren't in What needs you now"
    f.wait("document.querySelectorAll('[data-home-panel] [data-tile]').length === 5", what="the five numbers in one panel")
    if f.size == "desktop":
        tops = f.js("[...document.querySelectorAll('[data-home-panel] [data-tile]')].map((d) => Math.round(d.getBoundingClientRect().top))")
        assert len(set(tops)) == 1, f"five across at 1440: {tops}"
    # The last 7 days (docs/admin-analytics-plan.md, slice 1): the overview's growth and songs as cards.
    f.wait("document.querySelectorAll('[data-home-growth] [data-tile]').length === 5", what="the growth cards")
    tiles = f.js("Object.fromEntries([...document.querySelectorAll('[data-home-growth] [data-tile]')].map((c) => [c.dataset.tile, c.querySelector('[data-tile-value]').textContent.trim()]))")
    assert tiles == {"signUps": "40", "upgrades": "6", "activeSingers": "85", "finished": "12", "secondSongs": "3"}, tiles
    f.shot("home")
    f.click_t("admin.nav.safety")
    f.wait("document.querySelectorAll('[role=tab]').length === 4", what="the tabs")
    f.wait_text(f.js(f"{I18N}.t('admin.flag.shield_mode')"))
    f.shot("switches")
    # Safety keeps the emergency controls only; the features (Gallery in the example) are on Switches.
    flags = f.js("[...document.querySelectorAll('[data-flags=emergency] [data-flag]')].map((li) => li.dataset.flag).join()")
    assert flags == "shield_mode,pause_signups,pause_uploads,read_only", flags
    # Turn shield mode on, with a reason.
    f.js("[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes('Shield')).querySelectorAll('[aria-pressed]')[1].click()")
    f.wait("document.querySelector('[role=dialog] textarea')", what="the reason")
    f.type("[role=dialog] textarea", "A wave of bot sign-ups")
    f.click_t("admin.confirmChange")
    f.wait_text(f.js(f"{I18N}.t('admin.saved')"))
    f.wait("[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes('Shield')).textContent.includes('A wave of bot sign-ups')", what="the reason shown")
    # Limits, then a block added and lifted.
    f.click_t("admin.safety.tab.limits")
    f.wait("document.querySelector('table')", what="the limits")
    f.shot("limits")
    f.click_t("admin.safety.tab.blocklist")
    f.wait("document.querySelector('table')", what="the blocklist")
    f.click_t("admin.blocklist.add")
    f.wait("document.querySelector('[role=dialog] input')", what="the block form")
    f.type("[role=dialog] input", "bot@example.com")
    f.type("[role=dialog] textarea", "Bots")
    f.shot("add-block")
    f.click_t("admin.confirmChange")
    f.wait("document.querySelector('table').textContent.includes('bot@example.com')", what="the new block")
    f.js("[...document.querySelectorAll('tbody tr')].find((tr) => tr.textContent.includes('bot@example.com')).querySelector('button').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the lift sheet")
    f.click_t("admin.confirmChange")
    f.wait("!document.querySelector('table').textContent.includes('bot@example.com')", what="the block lifted")
    f.click_t("admin.safety.tab.events")
    f.wait("document.querySelector('table')", what="the events")
    f.shot("events")
    # Settings opens on Switches (Hillel, 2026-11-10): the feature switches, none of Safety's emergency controls.
    f.click_t("admin.nav.group.settings")
    f.wait("document.querySelector('[data-switches] [data-flags=features] [data-flag]')", what="the feature switches")
    flags = f.js("[...document.querySelectorAll('[data-switches] [data-flag]')].map((li) => li.dataset.flag).join()")
    assert flags == "gallery", flags
    assert f.js("location.pathname") == "/admin/switches"
    f.shot("feature-switches")
    f.click_t("admin.nav.audit")
    f.wait("document.querySelector('table, [data-audit-cards]')", what="the audit log")
    f.shot("audit")
    # A change outside sudo goes to the second factor, and back here after.
    f.go("/admin/safety?api=mock&scenario=admin.flags.update:sudo_required", "document.querySelectorAll('[aria-pressed]').length > 1")
    f.js("document.querySelectorAll('[aria-pressed=false]')[0].click()")
    f.type("[role=dialog] textarea", "x")
    f.click_t("admin.confirmChange")
    f.wait("location.pathname === '/admin/second-factor' && location.search.includes('sudo=1') && location.search.includes('intended=%2Fadmin%2Fsafety')", what="the sudo check")
    # Signed out goes to the server's sign-in, keeping where they were going.
    f.go("/admin/audit?api=mock&scenario=unauthenticated", "location.pathname === '/admin/sign-in'")
    assert "intended=%2Fadmin%2Faudit" in f.js("location.search"), f.js("location.search")
    # Every admin screen in the pseudo-language: every word through t().
    for path in ("/admin", "/admin/switches", "/admin/safety?tab=switches", "/admin/safety?tab=limits", "/admin/safety?tab=blocklist", "/admin/safety?tab=events", "/admin/audit"):
        f.go(f"{path}{'&' if '?' in path else '?'}lang=pseudo&scenario=", "document.querySelector('main h1') && !document.querySelector('[role=status]')")
        left = f.js(UNTRANSLATED)
        assert not left, f"{path}: not translated: {left}"
    # Arrow keys move between the tabs.
    f.go("/admin/safety?tab=switches&lang=en", "document.querySelector('[role=tab]')")
    f.run("document.getElementById('tab-switches').focus(); document.querySelector('[role=tablist]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true }))")
    f.wait("document.activeElement.id === 'tab-limits' && location.search.includes('tab=limits')", what="ArrowRight to the next tab")
    # The styleguide, every word through t().
    f.go("/styleguide?lang=pseudo&scenario=", "document.querySelector('h1')")
    f.shot("styleguide")
    left = f.js(UNTRANSLATED)
    assert not left, f"styleguide: not translated: {left}"


if __name__ == "__main__":
    run("admin_mock", journey)
