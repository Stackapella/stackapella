"""Your songs' cards (Hillel: "add a play/preview button here and put delete in a drop toggle with
more actions like share, invite, etc"): each card has Play on its picture (a recording plays its
current mix, a written song its harmony; one at a time, library previews included) and a More menu
with what that card can do, the destructive one last: a recording's Share, Invite someone to sing,
Open in the studio, Rename and Delete; a written song's Edit and Delete; a draft's Resume and
Discard. Escape and a tap outside close the menu. Shot in Warm light for Hillel's review.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, light_first, new_song, t  # noqa: E402

YOURS = "section[aria-labelledby=band-projects]"


def items(f):
    return f.js("[...document.querySelectorAll('[role=menu] [data-menu-item]')].map((b) => b.dataset.menuItem).join(' ')")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-new-song]')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    clear_projects(f)
    light_first(f)
    song = new_song(f)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-new-song]')")
    f.run("""const { mine } = await import('/js/stores/templates.js'); const { loadTemplate } = await import('/js/templates.js');
        const tpl = await loadTemplate('happy-birthday');
        mine.list.push({ id: 'cards-written', state: 'device', updatedAt: Date.now() - 3600e3, template: { ...tpl, id: 'cards-written', title: 'My Birthday' } });
        mine.drafts.push({ id: 'cards-draft', updatedAt: Date.now() - 7200e3, template: { title: 'Evening Song' } });""")
    f.wait(f"document.querySelectorAll('{YOURS} [data-card-menu]').length === 3", what="a recording, a written song and a draft")
    light_first(f)
    title = f.js(f"{SESSION}.session.project?.title || (await import('/js/stores/projects.js')).projects.list.find((p) => p.id === '{song}').title")
    f.js(f"document.querySelector('{YOURS}').scrollIntoView({{ block: 'start' }})")
    f.shot("cards")
    # Hillel: on a mouse the tools show on hover (or keyboard focus); a touch screen always shows them.
    tools = f"getComputedStyle(document.querySelector('{YOURS} li:not(:first-child) .card-tools')).opacity"
    if f.size == "desktop":
        f.chrome.send("Input.dispatchMouseEvent", {"type": "mouseMoved", "x": 2, "y": 2})
        f.wait(f"{tools} === '0'", what="the library's tools hidden at rest")
        box = f.js(f"(() => {{ const r = document.querySelector('{YOURS} li:not(:first-child)').getBoundingClientRect(); return [r.left + r.width / 2, r.top + 40]; }})()")
        f.chrome.send("Input.dispatchMouseEvent", {"type": "mouseMoved", "x": box[0], "y": box[1]})
        f.wait(f"{tools} === '1'", what="the tools shown on hover")
        f.shot("hovered")
        f.chrome.send("Input.dispatchMouseEvent", {"type": "mouseMoved", "x": 2, "y": 2})
        f.wait(f"{tools} === '0'", what="hidden again")
        f.run(f"document.querySelector('{YOURS} li:not(:first-child) [data-song-play]').focus()")
        f.wait(f"{tools} === '1'", what="the tools shown with keyboard focus")
        f.run("document.activeElement.blur()")
    else:
        assert f.js(tools) == "1", "the tools are hidden on a touch screen"
    # Play: the recording's mix, then a library preview stops it (one at a time).
    f.js(f"{AUDIO}.unlock()")
    play = f"document.querySelector('{YOURS} [data-song-play]')"
    f.run(f"{play}.click()")
    f.wait(f"{SESSION}.session.playing && {play}.getAttribute('aria-pressed') === 'true'", timeout=20, what="the recording playing")
    # A library song's Play (in its sheet) stops it: one thing at a time.
    f.run("document.querySelector('#home-songs [data-song-card] button').click()")
    f.wait("document.querySelector('[role=dialog] [data-song-play]')", what="the song's sheet")
    f.run("document.querySelector('[role=dialog] [data-song-play]').click()")
    f.wait(f"{play}.getAttribute('aria-pressed') === 'false' && document.querySelector('[role=dialog] [data-song-play]').getAttribute('aria-pressed') === 'true'", timeout=20, what="the sheet's Play instead")
    f.run("document.querySelector('[role=dialog] [data-song-play]').click()")
    f.press("Escape")
    # The recording's menu: its actions, Delete last; Escape closes and focus returns.
    menu = f"document.querySelector('{YOURS} [data-card-menu]')"
    f.run(f"{menu}.click()")
    f.wait("document.querySelector('[role=menu]')", what="the menu")
    assert items(f) == "share invite studio rename delete", items(f)
    f.shot("menu-open")
    f.press("Escape")
    f.wait(f"!document.querySelector('[role=menu]') && document.activeElement === {menu}", what="Escape closing it, focus back")
    # Rename, in a styled sheet.
    f.run(f"{menu}.click()")
    f.run("document.querySelector('[data-menu-item=rename]').click()")
    f.wait("document.querySelector('[role=dialog] input')", what="the rename sheet")
    f.type("[role=dialog] input", "Our Renamed Song")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait(f"document.querySelector('{YOURS}').innerText.includes('Our Renamed Song')", what="the new name on the card")
    # A tap outside closes it.
    f.run(f"{menu}.click()")
    f.wait("document.querySelector('[role=menu]')", what="the menu again")
    f.run("document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true }))")
    f.wait("!document.querySelector('[role=menu]')", what="a tap outside closing it")
    # The written song and the draft: only their own actions.
    menus = f"[...document.querySelectorAll('{YOURS} [data-card-menu]')]"
    f.run(f"{menus}.find((b) => b.getAttribute('aria-label').includes('My Birthday')).click()")
    assert items(f) == "edit delete", items(f)
    f.press("Escape")
    f.run(f"{menus}.find((b) => b.getAttribute('aria-label').includes('Evening Song')).click()")
    assert items(f) == "resume discard", items(f)
    f.press("Escape")
    # Share opens the studio on its sheet.
    f.run(f"{menu}.click()")
    f.run("document.querySelector('[data-menu-item=share]').click()")
    f.wait(f"location.pathname === '/p/{song}' && document.querySelector('[role=dialog]')", timeout=20, what="the studio with Share open")
    f.run("const { mine } = await import('/js/stores/templates.js'); mine.list.splice(0); mine.drafts.splice(0);")
    clear_projects(f)


if __name__ == "__main__":
    run("home_cards", journey)
