"""Downloading a choir made from your voice is Plus's (Hillel, 2026-11-21: "for the first song, they can only listen to it
or share it; if they want to download it they need to subscribe to Plus"); the server says who may (`canDownload` on
each song; the mock gives it to the Plus account only). Free: Download stays, and opens the Plus sheet saying so, with
no file made; Play and Share stay; Get Plus leads to the Plus page from `choir_download`, which leads back to the choir.
The studio's Export of that choir project says the same and its button is Get Plus. Plus: Download makes the file.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

SESSION = "(await import('/js/stores/session.js')).session"
PAGE = "/t/silent-night/choir?api=mock&scenario=&as={who}"
COUNT_FILES = "window.__files = 0; const made = URL.createObjectURL; URL.createObjectURL = (b) => { window.__files += 1; return made.call(URL, b); };"


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def menu(f, key):
    """A header button on a wide screen; on a phone, the same in the More menu."""
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t(key)


def journey(f):
    # The example's choir: Silent Night ready, made from this singer's voice (own_choir on for the tab).
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    # With own_choir on, the mock's Silent Night is at the example choir's version, so its parts play in the studio.
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.removeItem('stackapella.mockAiVoices'); sessionStorage.removeItem('stackapella.mockChoirState'); sessionStorage.removeItem('stackapella.mockChoir');")
    clear_projects(f)

    # Free: Download is there, and opens the Plus sheet; no file is made.
    f.go(PAGE.format(who="member"), "document.querySelector('[data-choir-download]')")
    assert f.js("!!document.querySelector('[data-choir-play]') && !document.querySelector('[data-choir-share]').disabled"), "Play and Share stay free"
    f.run(COUNT_FILES)
    f.run("document.querySelector('[data-choir-download]').click()")
    f.wait("document.querySelector('[data-plus-moment][data-moment=choir_download]')", what="the Plus sheet")
    words = f.js("document.querySelector('[data-plus-moment]').innerText")
    assert t(f, "plus.moment.choirDownload") in words, f"not the choir's words: {words}"
    assert f.js("document.querySelector('[data-plus-go]').textContent.trim()") == t(f, "plus.subscribe"), "the button isn't Get Plus"
    assert f.js("window.__files") == 0, "a file was made without Plus"
    f.no_sideways("the Plus sheet")
    f.shot("free-download")
    f.press("Escape")
    f.wait("!document.querySelector('[data-plus-moment]')", what="the sheet closed")

    # Get Plus: the Plus page says why, and keeps the way back to this choir.
    f.run("document.querySelector('[data-choir-download]').click()")
    f.wait("document.querySelector('[data-plus-go]')", what="the Plus sheet again")
    f.run("document.querySelector('[data-plus-go]').click()")
    f.wait("location.pathname === '/plus' && location.search.includes('from=choir_download')", what="the Plus page from the choir")
    f.wait(f"document.body.innerText.includes({json.dumps(t(f, 'plus.moment.choirDownload'))})", what="why it's here")
    assert json.loads(f.js("sessionStorage.getItem('stackapella.plus.next')"))["path"] == "/t/silent-night/choir"
    f.shot("plus-page")

    # The studio's Export of the choir project: the same words, and Get Plus.
    f.go(PAGE.format(who="member"), "document.querySelector('[data-choir-learn-part]')")
    f.run("document.querySelector('[data-choir-learn-part]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing/.test(location.pathname)", what="Sing on the choir project")
    project = f.js("location.pathname.split('/')[2]")
    f.go(f"/p/{project}?api=mock&scenario=&as=member", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=30, what="the voices")
    assert f.js(f"Object.values({SESSION}.guideVoices).filter((v) => v?.kind === 'real' && v.choir).length") == 4, "the parts made from their singing"
    menu(f, "studio.export")
    f.wait("document.querySelector('[data-export]')", what="the export sheet")
    f.wait(f"document.querySelector('[data-export-plus]')?.textContent.trim() === {json.dumps(t(f, 'plus.moment.choirDownload'))}", what="the choir's Plus line")
    assert f.js("document.querySelector('[data-export] button[type=submit]').textContent.trim()") == t(f, "plus.subscribe"), "Export's button isn't Get Plus"
    f.shot("studio-export")
    f.run("document.querySelector('[data-export] button[type=submit]').click()")
    f.wait("location.pathname === '/plus' && location.search.includes('from=choir_download')", what="the Plus page from Export")

    # Plus: Download makes the file, with no sheet.
    f.go(PAGE.format(who="plus"), "document.querySelector('[data-choir-download]')")
    f.run(COUNT_FILES)
    f.run("document.querySelector('[data-choir-download]').click()")
    f.wait("window.__files === 1", timeout=30, what="the file, with Plus")
    assert f.js("globalThis.mockChoirDownloads ?? 0") >= 1, "the file wasn't made from Download's full-quality parts"
    assert not f.js("document.querySelector('[data-plus-moment]')"), "a Plus sheet for a Plus member"
    clear_projects(f)


run("own_choir_download", journey)
