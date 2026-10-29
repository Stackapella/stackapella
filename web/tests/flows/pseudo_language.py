"""Every word comes through t(): in the pseudo-language (?lang=pseudo), each visible text is
accented and bracketed, so anything left in plain English is a hard-coded string. Song and user
content (titles, lyrics, part and mic names) is marked translate="no" and skipped. The screenshots
show whether text 40% longer still fits.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

UNTRANSLATED = r"""(() => {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const found = [];
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        const text = node.textContent.trim();
        const element = node.parentElement;
        if (!/[A-Za-z]{2,}/.test(text) || /[\[\]·]/.test(text)) continue;
        if (element.closest('[translate="no"], time, script, style, noscript')) continue;  // time: dates from Intl
        if (!element.getClientRects().length && !element.closest('.sr-only')) continue;
        found.push(text.slice(0, 60));
    }
    // Words people hear or see on hover: labels, titles, placeholders, and the choices in a select.
    for (const element of document.querySelectorAll('[aria-label], [title], [placeholder], option')) {
        if (element.closest('[translate="no"]')) continue;
        for (const value of [element.getAttribute('aria-label'), element.getAttribute('title'), element.getAttribute('placeholder'), element.tagName === 'OPTION' ? element.textContent : null]) {
            const text = (value ?? '').trim();
            if (/[A-Za-z]{2,}/.test(text) && !/[\[\]·]/.test(text)) found.push(`${element.tagName.toLowerCase()}: ${text.slice(0, 50)}`);
        }
    }
    return found;
})()"""


def check(f, screen):
    f.shot(screen)
    left = f.js(UNTRANSLATED)
    if left:
        raise AssertionError(f"{screen}: not translated: {left}")


def close_dialog(f):
    f.js("document.querySelector('[role=dialog] header button').click()")
    f.wait("!document.querySelector('[role=dialog]')", what="the dialog to close")


def journey(f):
    f.go("/?lang=pseudo", "document.querySelector('h1')")
    f.wait("document.querySelectorAll('#templates-title ~ ul button, ul li button').length > 3", what="the templates")
    check(f, "projects")
    f.js("document.querySelector('ul li button').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the template dialog")
    check(f, "template")
    close_dialog(f)
    f.go("/tests/seed.html?title=Pseudo", "location.pathname.startsWith('/p/')")
    f.go(f.js("location.pathname") + "?lang=pseudo", "document.querySelectorAll('canvas').length > 3")
    check(f, "studio")
    f.js("document.querySelector('#song-name').nextElementSibling.click()")
    f.wait("document.querySelector('[role=dialog]')", what="project settings")
    check(f, "settings")
    close_dialog(f)
    f.js("document.querySelector('footer button.glass-record').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the headphones question")
    check(f, "headphones")
    close_dialog(f)
    f.js("document.querySelector('[role=dialog]') || true")
    # A template project: the practice bar, the part dialog, the timing check and the mic.
    template_project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{template_project}?lang=pseudo", "document.querySelectorAll('canvas').length > 3")
    soprano = f.js("(await import('/js/i18n.js')).t('parts.soprano')")
    # A part's row opens in place (checked too), and Voice and more is its dialog.
    f.js(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({soprano!r})).click()")
    f.wait("document.querySelector('button[aria-expanded=true]')", what="the open row")
    check(f, "row")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[role=dialog]')", what="the part dialog")
    check(f, "part")
    close_dialog(f)
    if f.size == "desktop":
        f.js("(await import('/js/audio.js')).unlock()")
        f.run("document.querySelector('[data-mic-button]').click()")  # named "Choose the microphone" once a mic is open
        f.wait("document.querySelector('[role=dialog] fieldset')", what="the mic picker")
        check(f, "mic")
        f.click_t("mic.checkTiming")
        f.wait("document.querySelector('[role=dialog] select')", what="the timing check")
        check(f, "timing")
        close_dialog(f)
        f.click_t("studio.export")
        f.wait("document.querySelector('[role=dialog] form')", what="the export dialog")
        check(f, "export")
        close_dialog(f)
    # The admin's beta screens (on the mock), and a take under review.
    i18n = "(await import('/js/i18n.js'))"
    for path, ready in [
        ("/admin", "admin.home.beta"),
        ("/admin/review", "admin.review.filter.waiting"),
        ("/admin/review/Tz4Kq8", "admin.review.checks"),
        ("/admin/singers", "admin.singers.copyMessage"),
        ("/admin/guides", "admin.guides.default"),
        ("/admin/feedback", "admin.feedback.markRead"),
        ("/signin", "auth.signInTitle"),
        ("/signin?mode=signup", "auth.createAccount"),
        ("/settings", "settings.profile"),
    ]:
        f.go(f"{path}{'&' if '?' in path else '?'}api=mock&scenario=&lang=pseudo", "document.querySelector('#app *')")
        f.wait(f"document.body.innerText.includes({i18n}.t('{ready}'))", what=path)
        check(f, path.replace("/", "-").replace("?", "-").replace("=", "-").strip("-"))
    f.js(f"(async () => {{ const s = await import('/js/stores/projects.js'); await s.loadProjects(); await s.deleteProject('{template_project}'); for (const p of s.projects.list.filter((p) => p.title === 'Pseudo')) await s.deleteProject(p.id); }})()")


if __name__ == "__main__":
    run("pseudo_language", journey)
