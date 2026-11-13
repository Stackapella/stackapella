"""Choose your part as a character select (docs/game-elements-plan.md; the proposal's board 09), as a
reskin of the part list: in game mode each part is a portrait in its colour carrying its own numbers
from the song (its range, how many notes, how often it leaps) and "Best for you" when the range check
says so; a 1P cursor in your accent follows the arrows; a tap chooses it, as the list did. The
Character select switch and serious mode give today's list.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, every_theme, frames, light  # noqa: E402
from help_shots import SESSION, clear_projects, new_song, t  # noqa: E402

SELECT = "document.querySelector('[data-game-select]')"


def part_step(f, select=True, sung=True):
    clear_projects(f)
    if sung:
        new_song(f)  # a song before this one: not a first song, so the select's numbers show
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('[data-way=whole]')")
    # Game mode shows the character select even when Sing has picked your part (Hillel, 2026-11-01), with that part
    # pre-selected; serious mode goes straight in on the part it picked.
    f.wait(f"(async () => {{ const s = {SESSION}; return !!s.session?.parts?.length; }})()", what="the song's parts")
    picked = f.js(f"(async () => {{ const s = {SESSION}; return s.session.practice?.focus ?? null; }})()")
    f.run("document.querySelector('[data-way=whole]').click()")
    if f.skin == "game" and (select or not picked):
        f.wait_text(t(f, "sing.chooser.yourPart"))
    elif picked:
        f.wait("!document.querySelector('[data-way]')", what="straight in on the part Sing picked")
    return picked


def journey(f):
    f.go("/voice?api=mock&as=signedOut", "document.querySelector('#app header, #app main')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); localStorage.removeItem('stackapella.singPrefs')")
    # A first song (the first-session review, proposal 5): 1P SELECT, Busy, Leaps and their key wait for a song sung, the
    # 1P chip stays (the choice by shape), and each part is said in words under its name (proposal 2).
    part_step(f, sung=False)
    if f.skin == "game":
        f.wait("document.querySelector('[data-select-first]')?.dataset.selectFirst === 'true'", what="the select, on a first song")
        fresh = f.js("({ kick: !!document.querySelector('[data-select-kick]'), key: !!document.querySelector('[data-select-key]'), stats: document.querySelectorAll('.ge-statbar').length, chip: !!document.querySelector('.ge-1p'), words: [...document.querySelectorAll('.ge-portrait [data-part-words]')].map((e) => e.innerText.trim()).filter(Boolean).length })")
        assert fresh == {"kick": False, "key": False, "stats": 0, "chip": True, "words": 4}, f"a first song's select: {fresh}"
        f.no_sideways("the first song's select")
        f.shot("select-first")
    picked = part_step(f)
    if f.skin == "serious":
        assert f.js(f"!{SELECT}"), "serious mode: no character select"
        clear_projects(f)
        return
    f.wait(SELECT, what="the character select")
    f.wait("document.querySelector('[data-select-first]')?.dataset.selectFirst === 'false'", what="the select, a song sung before")
    if picked:
        shown = f.js(f"{SELECT}.querySelector('[aria-current=true]')?.dataset.slot")
        assert shown == picked, f"the part Sing picked is pre-selected, one tap to confirm: {shown} vs {picked}"
    assert f.js(f"{SELECT}.querySelectorAll('.ge-portrait').length") == 4, "four portraits"
    assert f.js("!document.querySelector('[data-game-stats]')"), "no stats card: each portrait has its own"
    notes = f.js(f"(async () => {{ const s = {SESSION}; return s.templatePart(s.session.parts.filter((p) => p.templatePartKey)[0]).guide.notes.filter((n) => Number.isFinite(n.pitch)).length; }})()")
    first = f.js("document.querySelector('.ge-portrait').innerText + ' ' + document.querySelector('.ge-portrait').textContent")
    assert t(f, "game.select.notes", {"count": notes}) in first, f"the first part's real note count {notes}: {first}"
    f.no_sideways("the character select")
    frames(f, "select", [0, 250, 600])
    # The arrows move the 1P cursor.
    at = "[...document.querySelectorAll('.ge-portrait')].indexOf(document.querySelector('.ge-portrait.is-picked'))"
    start = f.js(at)
    key, to = ("ArrowRight", start + 1) if start < 3 else ("ArrowLeft", start - 1)
    f.run(f"const b = document.querySelector('.ge-portrait.is-picked'); b.focus(); b.dispatchEvent(new KeyboardEvent('keydown', {{ key: '{key}', bubbles: true }}))")
    f.wait(f"{at} === {to}", what="the cursor moved")
    picked = f.js("document.querySelector('.ge-portrait.is-picked .ge-portrait-name').innerText")
    f.shot("select")
    every_theme(f, "the part select")
    light(f)
    f.shot("select-light")
    dark(f)
    # "Sing Tenor" names the part the cursor is on, the screen's one primary (first-15 review, item 10), and the key
    # says what Busy and Leaps measure (item 11).
    sing = f.js("document.querySelector('[data-select-sing]').innerText")
    assert sing.upper() == t(f, "game.select.sing", {"part": picked}).upper(), f"the Sing button names the cursor's part: {sing} vs {picked}"
    assert f.js("!!document.querySelector('[data-select-key]') && !!document.querySelector('[data-select-kick]')"), "Busy and Leaps explained, 1P SELECT back"
    assert f.js("getComputedStyle(document.querySelector('.ge-portrait')).borderTopLeftRadius") == "0px", "square cards"
    # The Sing button (phone) or a tap (desktop) chooses it: the song starts on that part.
    f.run("document.querySelector('[data-select-sing]').click()" if f.size == "phone" else "document.querySelector('.ge-portrait.is-picked').click()")
    f.wait(f"!{SELECT}", timeout=5, what="locked in")
    f.wait(f"!!{SESSION}.session.practice?.focus", timeout=10, what="a part chosen")
    # The select shows once per song start: another part from the header's part chip (Your part) changes the part and
    # never brings the select back (the coordinator, 2026-11-02).
    chosen = f.js(f"{SESSION}.session.practice.focus")
    label = t(f, "sing.yourPart.open", {"part": picked})
    f.run(f"[...document.querySelectorAll('button')].find((b) => b.getAttribute('aria-label') === {label!r}).click()")
    f.wait("document.querySelectorAll('[role=dialog] button[aria-pressed]').length > 1", what="Your part")
    f.run("document.querySelector('[role=dialog] button[aria-pressed=false]').click()")
    f.wait(f"{SESSION}.session.practice.focus !== {chosen!r}", timeout=5, what="another part")
    f.js("new Promise((r) => setTimeout(r, 1200))")
    assert f.js(f"!{SELECT}"), "a later part change brings the select back"
    # The switch off: today's list.
    f.run("(await import('/js/game/effects.js')).effects.select = false")
    part_step(f, select=False)
    assert f.js(f"!{SELECT}"), "the switch turns it off"
    print(f"    picked {picked}")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    clear_projects(f)


if __name__ == "__main__":
    run("game_select", journey)
