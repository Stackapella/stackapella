"""Languages, slice 1 (docs/languages-plan.md): the 40% rule. Every language runs up to 40% longer than English, so the
main screens are opened at 390 px in the pseudo-language (?lang=pseudo: every word accented and 40% longer) and must
still fit: nothing scrolls the page sideways, and no button, link, label or heading cuts its words off (a truncate or
a line clamp on purpose is fine: it ends in an ellipsis). Then Settings' Language and Note names rows, which show only
once a language besides English is switched on (auth.config `languages`), and the note names they choose.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CUT = r"""(() => {
    const cut = [];
    for (const el of document.querySelectorAll('#app button, #app a, #app label, #app h1, #app h2, #app h3, #app [role=tab], #app [role=radio], #app .chip')) {
        if (!el.getClientRects().length || el.closest('[translate="no"], [aria-hidden="true"], .sr-only')) continue;
        if (!/\p{L}{2,}/u.test(el.textContent)) continue; // an icon alone: no words to cut
        const style = getComputedStyle(el);
        if (style.textOverflow === 'ellipsis' || style.webkitLineClamp !== 'none' && style.webkitLineClamp) continue;
        // Its words wider than its box, with the overflow hidden: cut off. (A scroller is fine: it scrolls.)
        const hidden = ['hidden', 'clip'].includes(style.overflowX);
        if (hidden && el.scrollWidth > el.clientWidth + 2) cut.push(`${el.tagName.toLowerCase()}: ${el.textContent.trim().slice(0, 40)} (${el.scrollWidth} > ${el.clientWidth})`);
        // Words that spill out of a box that doesn't hide them: past its edge, over whatever is next.
        else if (!hidden && el.scrollWidth > el.clientWidth + 2 && el.clientWidth > 0 && style.display !== 'inline' && style.overflowX !== 'auto') cut.push(`${el.tagName.toLowerCase()} spills: ${el.textContent.trim().slice(0, 40)} (${el.scrollWidth} > ${el.clientWidth})`);
    }
    return cut;
})()"""

SCREENS = [
    ("/", "projects"),
    ("/learn", "learn"),
    ("/learn/1-comfortable", "lesson1"),
    ("/voice", "voice"),
    ("/settings", "settings"),
    ("/plus", "plus"),
    ("/signin?mode=signup", "signup"),
]


def fits(f, name, where):
    """The page never scrolls sideways (a row built to scroll may), and no button, link, label or heading cuts its words."""
    over = f.js("document.documentElement.scrollWidth - document.documentElement.clientWidth")
    assert over <= 0, f"{name} {where}: the page scrolls sideways by {over}px"
    cut = f.js(CUT)
    assert not cut, f"{name} {where}: words cut off: {cut}"


def journey(f):
    # Spanish (es.json, drafted and native-reviewed by the courses lead), opened by ?lang=es while its switch is off.
    for path, name in SCREENS:
        f.go(f"{path}{'&' if '?' in path else '?'}api=mock&scenario=&lang=es", "document.querySelector('#app h1, #app h2')")
        f.wait("!document.querySelector('#app [role=status][aria-busy=true]')", what=f"{name} loaded")
        assert f.js("document.documentElement.lang") == "es", "in Spanish"
        f.run("window.scrollTo(0, 0)")
        f.shot(f"es-{name}")
        fits(f, name, "in Spanish")
    for path, name in SCREENS:
        f.go(f"{path}{'&' if '?' in path else '?'}api=mock&scenario=&lang=pseudo", "document.querySelector('#app h1, #app h2')")
        f.wait("!document.querySelector('#app [role=status][aria-busy=true]')", what=f"{name} loaded")
        f.run("window.scrollTo(0, 0)")
        f.shot(f"pseudo-{name}")
        fits(f, name, "at 40% longer")

    # Settings: no Language row while English is the only language switched on.
    f.go("/settings?api=mock&scenario=", "document.querySelector('[data-sounds]')")
    assert not f.js("!!document.querySelector('[data-language]')"), "hidden until a language is switched on"
    # Spanish switched on (auth.config `languages`): the rows show, each language named in its own words.
    f.run("localStorage.removeItem('stackapella.lang'); localStorage.removeItem('stackapella.noteNames')")
    # After the app's own read of auth.config (which says English alone), so it can't switch Spanish off again.
    f.run("await (await import('/js/stores/account.js')).loadAuthConfig(); await new Promise((done) => setTimeout(done, 300)); await (await import('/js/i18n.js')).setLiveLanguages(['en', 'es'])")
    f.wait("document.querySelector('[data-language] select')", what="the language rows")
    names = f.js("[...document.querySelectorAll('[data-language] option')].map((o) => o.textContent.trim())")
    assert names == ["English", "Español"], names
    choices = f.js("[...document.querySelectorAll('[data-note-names] option')].map((o) => o.textContent.trim())")
    assert choices == [f.js("(await import('/js/i18n.js')).t('settings.noteNames.auto', { sample: 'C D E' })"), "C D E", "Do Re Mi", "C D E … A H"], choices
    f.run("document.querySelector('[data-language]').scrollIntoView({ block: 'center' })")
    f.shot("settings-languages")
    assert f.js("document.documentElement.scrollWidth - document.documentElement.clientWidth") <= 0, "Settings with the language rows"
    # Do Re Mi chosen: every note name follows, on this device.
    f.run("const s = document.querySelector('[data-note-names] select'); s.value = 'fixed'; s.dispatchEvent(new Event('change'))")
    f.wait("localStorage.getItem('stackapella.noteNames') === 'fixed'", what="kept on the device")
    assert f.js("(await import('/js/dsp.js')).midiName(62)") == "Re4"
    f.run("const s = document.querySelector('[data-note-names] select'); s.value = 'auto'; s.dispatchEvent(new Event('change'))")
    assert f.js("(await import('/js/dsp.js')).midiName(62)") == "D4", "auto in English: letters"
    f.run("(await import('/js/i18n.js')).setLiveLanguages(['en']); localStorage.removeItem('stackapella.noteNames')")


if __name__ == "__main__":
    run("language_fit", journey, sizes=("phone",))
