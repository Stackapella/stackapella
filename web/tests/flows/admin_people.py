"""People (admin.people.*): an admin finds the accounts, opens one with what the API says about it,
suspends it through a sheet titled with exactly that and a reason, then unsuspends it. Against the
mock (its examples).
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def change(f, button, sheet_title, call):
    """A change through its sheet: the button, the sheet named for it, a reason, Confirm."""
    f.click(t(f, button), "button")
    f.wait(f"document.querySelector('[role=dialog]')?.innerText.includes({json.dumps(sheet_title)})", what=sheet_title)
    f.shot(button.split(".")[-1])
    f.type("[role=dialog] textarea", "Checked with them by email")
    f.run("window.mockCalls = [];")
    f.click(t(f, "admin.confirmChange"), "[role=dialog] button")
    f.wait(f"window.mockCalls.includes({json.dumps(call)}) && !document.querySelector('[role=dialog]')", what=call)


def journey(f):
    f.go("/admin/people?api=mock&as=admin", "document.querySelector('[data-people] a')")
    f.wait_text("ada@example.com")
    f.wait("document.querySelector('[data-people] li')?.innerText.includes('lessons')", what="the activity column")
    # The columns line up: every row's cells start where the header's do, whatever chips a row has.
    if f.size == "desktop":
        lined = f.js("""(() => { const head = [...document.querySelector('[data-people] > div').children].map((c) => Math.round(c.getBoundingClientRect().left));
            return [...document.querySelectorAll('[data-people] li a')].every((row) => [...row.children].map((c) => Math.round(c.getBoundingClientRect().left)).join() === head.join()); })()""")
        assert lined, "every row lines up with the header"
    nav = json.dumps(t(f, "admin.nav.people"))
    assert f.js(f"[...document.querySelectorAll('header nav a')].some((a) => a.textContent.trim() === {nav} && a.getAttribute('aria-current') === 'page')"), "People in the nav, current"
    f.shot("list")
    # The filters and sort reach the API as its query.
    f.run("const { api } = await import('/js/api.js'); const list = api.admin.people.list; api.admin.people.list = (query) => { window.peopleQuery = query; return list(query); };")
    f.run("const s = [...document.querySelectorAll('form[role=search] select')]; s[2].value = '0'; s[2].dispatchEvent(new Event('change')); s.at(-1).value = 'active'; s.at(-1).dispatchEvent(new Event('change'));")
    f.wait("window.peopleQuery?.adult === '0' && window.peopleQuery?.sort === 'active'", what="under 18 and last active asked")
    f.wait("document.querySelector('[data-people] a')", what="the list again")
    # People by country: the top countries with their flags and counts, and Not known; a country narrows the list.
    uk = f.js("new Intl.DisplayNames(['en'], { type: 'region' }).of('GB')")
    f.wait(f"document.querySelector('[data-countries]')?.innerText.includes({json.dumps(uk)})", what="the countries summary")
    f.wait_text(t(f, "admin.people.countryUnknown"))
    assert f.js(f"document.querySelector('[data-people] li')?.innerText.includes({json.dumps(uk)})"), "each row says where they joined from"
    f.run(f"[...document.querySelectorAll('[data-countries] button')].find((b) => b.textContent.includes({json.dumps(uk)})).click()")
    f.wait("window.peopleQuery?.country === 'GB'", what="the list narrowed to the UK")
    f.wait("document.querySelector('[data-countries] button[aria-pressed=true]')", what="the country shown as chosen")
    f.shot("filtered")
    f.run("document.querySelector('[data-people] a').click()")
    f.wait("location.pathname.startsWith('/admin/people/') && document.querySelector('[data-person]')", what="the person's page")
    f.wait_text(t(f, "admin.people.verified"))
    f.wait_text(t(f, "admin.people.events"))
    f.wait_text(t(f, "admin.people.adult"))
    f.wait_text(t(f, "admin.people.country"))
    f.wait_text(t(f, "admin.people.audit"))
    f.wait("document.querySelector('[data-person-audit] li')", what="what was done about them")
    f.wait("document.querySelectorAll('[data-person-stats] > div').length >= 8", what="their activity: songs, takes, lessons, stars, level, streak, practice, storage")
    text = f.js("document.querySelector('main').innerText")
    assert "audio" not in text.lower() and "recording" not in text.lower(), "no recordings shown"
    f.shot("person")
    change(f, "admin.people.suspend", t(f, "admin.people.sheet.suspend", {"name": "Ada Lovelace"}), "admin.people.suspend")
    f.wait_text(t(f, "admin.people.chip.suspended"))
    f.wait_text(t(f, "admin.people.unsuspend"))
    change(f, "admin.people.unsuspend", t(f, "admin.people.sheet.unsuspend", {"name": "Ada Lovelace"}), "admin.people.unsuspend")
    f.wait_text(t(f, "admin.people.suspend"))
    f.click(t(f, "admin.people.title"), "main a")
    f.wait("location.pathname === '/admin/people' && document.querySelector('[data-people]')", what="back to the list")


if __name__ == "__main__":
    run("admin_people", journey, sizes=("desktop", "phone"))
