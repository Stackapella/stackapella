"""The song library's cards (Hillel: "show 3 songs per row", "go back to a modal when you click a song
in the library", "keep the modal simple with info and play options", "change footer to 2 to 4 •
Beginner"; his Home design): four to a row on a wide screen (whole rows, Browse all songs a cell), a card's footer the singer
range and level (said in full to a screen reader), and a tap opens the song's sheet: what it is,
Play (and Stop) beside Start, How many singers, Check my range and Make a copy under More options.
Start goes to Sing. Shot in Warm light for Hillel's review.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, clear_projects, light_first, t  # noqa: E402

CARDS = "[...document.querySelectorAll('#home-songs [data-song-card]')]"
PLAY = "document.querySelector('[role=dialog] [data-song-play]')"


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    clear_projects(f)
    light_first(f)
    if f.size == "desktop":
        tops = f.js("[...document.querySelectorAll('#home-songs > li')].map((li) => Math.round(li.getBoundingClientRect().top))")
        total = f.js("(await import('/js/templates.js')).listTemplates().then((l) => l.length)")
        assert len([x for x in tops if x == tops[0]]) == min(4, total), f"not four to a row: {tops}"  # Hillel's Home design
        if total <= 8:  # Hillel: all of them when they fit in two rows, no Browse all
            assert len(tops) == total and not f.js("document.querySelector('[data-browse-all]')"), f"{len(tops)} cells for {total} songs"
        else:
            assert len(tops) % 4 == 0, f"{len(tops)} cells: a row is left part full"
        assert not f.js("document.querySelector('[data-voice-legend]')"), "the part legend is back (Hillel took it off)"
        shown = f.js("document.querySelectorAll('#home-songs [data-song-card]').length")
        hidden = f.js("+(document.querySelector('[data-browse-all]')?.innerText.match(/\\d+/)?.[0] ?? 0)")
        head = f.js("document.querySelector('section[aria-labelledby=band-songs]').innerText")
        assert t(f, "home.songs.count", {"count": shown + hidden}) in head, f"the song count: {shown} + {hidden} in {head[:60]!r}"
    footer = f.js(f"{CARDS}[0].querySelector('[aria-label]').textContent.trim()")
    assert " · " in footer and "singer" in footer, f"the footer reads {footer!r}"
    assert "singers" in f.js(f"{CARDS}[0].querySelector('[aria-label]').getAttribute('aria-label')"), "the footer isn't said in full"
    f.js("document.querySelector('#band-songs').scrollIntoView({ block: 'start' })")
    f.shot("cards")
    # Hillel: the preview's Play at the picture's top right; it plays, and the sheet stays shut.
    corner = f.js(f"(() => {{ const c = {CARDS}[0]; const p = c.querySelector('[data-song-play]').getBoundingClientRect(); const pic = c.querySelector('button > span').getBoundingClientRect(); return [Math.round(pic.right - p.right), Math.round(p.top - pic.top)]; }})()")
    assert 0 <= corner[0] <= 16 and 0 <= corner[1] <= 16, f"Play isn't at the picture's top right: {corner}"
    f.js(f"{AUDIO}.unlock()")
    f.run(f"{CARDS}[0].querySelector('[data-song-play]').click()")
    f.wait(f"{AUDIO}.engine.playing && {CARDS}[0].querySelector('[data-song-play]').getAttribute('aria-pressed') === 'true'", timeout=20, what="the card's preview")
    assert not f.js("document.querySelector('[role=dialog]')"), "Play opened the sheet"
    f.run(f"{CARDS}[0].querySelector('[data-song-play]').click()")
    f.wait(f"!{AUDIO}.engine.playing", what="the preview stopped")
    # A tap: the sheet, with Play, Edit and Start in a row at equal widths (Hillel), no parts list or More options.
    f.run(f"{CARDS}[0].querySelector('button').click()")
    f.wait(f"{PLAY} && document.querySelector('[role=dialog] [data-song-start]')", what="the song's sheet")
    boxes = f.js(f"[{PLAY}, document.querySelector('[data-song-edit]'), document.querySelector('[data-song-start]')].map((b) => {{ const r = b.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.top)]; }})")
    assert boxes[0] == boxes[1] == boxes[2], f"Play, Edit and Start aren't the same width in one row: {boxes}"
    assert not f.js(f"[...document.querySelectorAll('[role=dialog] button')].some((b) => b.textContent.trim() === {t(f, 'template.copy')!r})"), "Make a copy is back"
    assert not f.js("document.querySelector('[role=dialog] [data-more-options]')"), "More options is back"
    f.shot("sheet")
    f.js(f"{AUDIO}.unlock()")
    f.run(f"{PLAY}.click()")
    f.wait(f"{AUDIO}.engine.playing && {PLAY}.getAttribute('aria-pressed') === 'true'", timeout=20, what="the song playing")
    f.shot("sheet-playing")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped")
    # Hillel: "add back the 2/3/4 part toggle": the song's own counts, the full count chosen, and Start sings that many.
    counts = f.js("[...document.querySelectorAll('[data-song-singers] [role=radio]')].map((b) => b.textContent.trim() + (b.getAttribute('aria-checked') === 'true' ? '*' : ''))")
    assert counts == ["2", "3", "4*"], f"the singers: {counts}"
    f.run("[...document.querySelectorAll('[data-song-singers] [role=radio]')][1].click()")
    f.wait("document.querySelectorAll('[data-song-singers] [role=radio]')[1].getAttribute('aria-checked') === 'true'", what="three chosen")
    f.click_t("templates.start")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", timeout=20, what="Start going to Sing")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 3", what="three parts to sing")
    clear_projects(f)


if __name__ == "__main__":
    run("library", journey)
