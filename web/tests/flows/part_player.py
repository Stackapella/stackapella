"""The song part pages' player (js/public/part-player.js) on its demo page (web/tests/part-player-demo.html, the bundled
Amazing Grace's soprano): it loads when shown; Your part plays the part alone and With the others plays it with the
other parts; Sing it follows a stand-in mic (?sing=in sings the part's notes, ?sing=off a tritone away), lighting the
notes sung in tune and missing the rest, and says how many it lit beside the time; a blocked mic (?sing=denied) never
stops the music: the others play on, the lane says why, and once allowed it listens mid-song; a new way to play carries
on from the same place; the toggle at the lane's corner shows the part as sheet music (part-sheet.js), lit as it sounds
and remembered; nothing spills sideways on a phone; Open in the app keeps the song and the part. Both skins
(FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SIZES, run  # noqa: E402

P = "window.__player"
PLAY = "document.querySelector('[data-ui=part-play]')"


def mode(f, key):
    f.run(f"document.querySelector('[data-ui=part-modes] [data-segment={key}]').click()")
    assert f.js(f"document.querySelector('[data-ui=part-modes] [data-segment={key}]').getAttribute('aria-pressed')") == "true", key


def play_for(f, seconds, what):
    f.run(f"{PLAY}.click()")
    f.wait(f"{P}.playing() && {P}.time() > {seconds}", timeout=25, what=what)


def journey(f):
    f.go("/tests/part-player-demo.html", P)
    f.wait(f"{P}.duration() > 10", what="the song and its part, loaded when seen")
    f.no_sideways("the player, before it plays")
    # At 320 px (the game skin's wider type too) the ways to play fit on one row, in their short words.
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": 320, "height": 720, "deviceScaleFactor": 2, "mobile": True})
    f.js("new Promise((r) => setTimeout(r, 300))")
    over = f.js("(() => { const g = document.querySelector('[data-ui=part-modes] [role=group]'); return g.scrollWidth - g.clientWidth; })()")
    assert over <= 1, f"the ways to play run past the row at 320 px by {over}px"
    assert f.js("document.querySelector('[data-segment=all]').innerText.trim()") == "All", "the short word at 320 px"
    f.no_sideways("the player at 320 px")
    f.shot("narrow")
    width, height, mobile = SIZES[f.size]
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": height, "deviceScaleFactor": 2 if mobile else 1, "mobile": mobile})
    f.js("new Promise((r) => setTimeout(r, 300))")
    assert f.js("document.querySelector('[data-ui=part-open]').href") == "https://app.stackapella.com/?song=amazing-grace&part=soprano&ref=part-page", "Open in the app keeps the song and the part"
    f.shot("ready")

    # Your part (the default), then With the others: the new way to play carries on from the same place.
    play_for(f, 1.2, "the part alone, on the audio clock")
    assert f.js(f"{P}.mode()") == "part" and f.js(f"{PLAY}.dataset.state") == "playing"
    f.shot("part")
    was = f.js(f"{P}.time()")
    mode(f, "all")
    f.wait(f"{P}.playing() && {P}.mode() === 'all'", timeout=20, what="with the others")
    assert f.js(f"{P}.time()") >= was - 0.05, "a new way to play went back to the start"
    f.wait(f"{P}.time() > {was} + 0.5", timeout=20, what="the others playing on")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{P}.playing() && {PLAY}.dataset.state === 'paused'", what="paused")
    held = f.js(f"{P}.time()")
    f.js("new Promise((r) => setTimeout(r, 400))")
    assert abs(f.js(f"{P}.time()") - held) < 0.01, "time moves while paused"

    # Sheet music: the part engraved in the lane's box (soprano is the tune: no cue staff), lit as it sounds, remembered.
    f.run("document.querySelector('[data-ui=part-view] [data-view=sheet]').click()")
    f.wait("document.querySelectorAll('[data-ui=part-sheet] [data-system] svg').length > 1", what="the sheet music, engraved")
    assert not f.js("document.querySelector('[data-ui=part-sheet]').hidden"), "the sheet shown"
    assert f.js("document.querySelector('[data-ui=part-view] [data-view=sheet]').getAttribute('aria-pressed')") == "true"
    assert f.js("document.querySelectorAll('[data-ui=part-sheet] [data-staff]').length") == f.js("document.querySelectorAll('[data-ui=part-sheet] > [data-system]').length"), "one staff a line: the part alone"
    f.no_sideways("the sheet music")
    clear = f.js("(() => { const t = document.querySelector('[data-ui=part-view]').getBoundingClientRect(), p = document.querySelector('[data-ui=part-sheet]').getBoundingClientRect(); return t.top - p.bottom; })()")
    assert clear >= 0, f"the view toggle covers the sheet by {-clear}px"
    play_for(f, 1.5, "playing over the sheet")
    f.wait("document.querySelector('[data-ui=part-sheet] [data-lit]')", what="a note lit on the sheet as it sounds")
    f.shot("sheet")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{P}.playing()", what="paused")
    f.go("/tests/part-player-demo.html", P)
    f.wait("!document.querySelector('[data-ui=part-sheet]').hidden && document.querySelector('[data-ui=part-sheet] svg')", what="the sheet remembered on this device")
    f.run("document.querySelector('[data-ui=part-view] [data-view=lane]').click()")
    assert f.js("document.querySelector('[data-ui=part-sheet]').hidden"), "back to the lane"

    # A part that isn't the tune: its staff with the tune cued above it, and only its own notes light (the cue's don't).
    f.go("/tests/part-player-demo.html?part=bass", P)
    f.wait(f"{P}.duration() > 10", what="the bass, loaded")
    f.run("document.querySelector('[data-ui=part-view] [data-view=sheet]').click()")
    f.wait("document.querySelector('[data-ui=part-sheet] [data-system] svg')", what="the bass's sheet music")
    staves = f.js("[...document.querySelectorAll('[data-ui=part-sheet] > [data-system]:first-child [data-staff]')].map((s) => s.dataset.staff)")
    assert staves == ["soprano", "bass"], f"the tune cued over the bass: {staves}"
    play_for(f, 1.5, "the bass over its sheet")
    f.wait("document.querySelector('[data-ui=part-sheet] [data-lit]')", what="a bass note lit")
    assert f.js("[...document.querySelectorAll('[data-ui=part-sheet] [data-lit]')].every((el) => el.closest('[data-staff]').dataset.staff === 'bass')"), "only the bass's own notes light"
    f.shot("sheet-bass")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{P}.playing()", what="paused")
    f.run("document.querySelector('[data-ui=part-view] [data-view=lane]').click()")

    # Sing it, in tune: the notes light and the line says how many.
    for sung, lit in (("in", True), ("off", False)):
        f.go(f"/tests/part-player-demo.html?sing={sung}", P)
        f.wait(f"{P}.duration() > 10", what="loaded")
        mode(f, "sing")
        assert "mic listens" in f.js("document.querySelector('[data-ui=part-status]').textContent"), "the mic's note before singing"
        play_for(f, 0.5, "singing along")
        f.wait("window.__notes.length >= 6", timeout=30, what="the first notes passed")
        hits = f.js("window.__notes.map((n) => n.hit)")
        print(f"    sung {sung}: {hits}")
        if lit:
            assert sum(hits) >= len(hits) - 1, f"sung in tune, the notes should light: {hits}"
        else:
            assert not any(hits), f"sung a tritone off, no note should light: {hits}"
        line = f.js("document.querySelector('[data-ui=part-score]').textContent")
        assert line.endswith("notes lit"), line
        assert f.js("!document.querySelector('[data-ui=part-score]').classList.contains('hidden')"), "the score beside the time"
        assert f.js("document.querySelector('[data-ui=part-mic]').hidden"), "no mic trouble shown"
        f.shot(f"sing-{sung}")
        f.run(f"{PLAY}.click()")
        f.wait(f"!{P}.playing()", what="paused")

    # A blocked mic never stops the music: the others play on, and the lane itself says why (not a line under it).
    f.go("/tests/part-player-demo.html?sing=denied", P)
    f.wait(f"{P}.duration() > 10", what="loaded")
    play_for(f, 0.8, "the part alone")
    mode(f, "sing")
    f.wait("!document.querySelector('[data-ui=part-mic]').hidden", what="the blocked mic, said on the lane")
    assert "blocked" in f.js("document.querySelector('[data-ui=part-mic]').textContent")
    held = f.js(f"{P}.time()")
    f.wait(f"{P}.playing() && {P}.mode() === 'sing' && {P}.time() > {held} + 1", timeout=20, what="the others playing on without the mic")
    over = f.js("(() => { const c = document.querySelector('[data-ui=part-mic]').getBoundingClientRect(), l = document.querySelector('[data-ui=part-lane]').getBoundingClientRect(); return Math.max(l.top - c.top, c.bottom - l.bottom, l.left - c.left, c.right - l.right); })()")
    assert over <= 0, f"the mic's chip runs outside the lane by {over}px"
    assert f.js("document.querySelector('[data-ui=part-score]').classList.contains('hidden')"), "no score without the mic"
    f.shot("sing-denied")
    # Allowed mid-song: it listens from there, the chip goes, and the notes light.
    f.run(f"window.__allowMic = true; {P}.listen()")
    f.wait("document.querySelector('[data-ui=part-mic]').hidden", what="the chip gone once the mic answers")
    f.wait("window.__notes.some((n) => n.hit)", timeout=30, what="notes lit once the mic is allowed")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{P}.playing()", what="paused")


if __name__ == "__main__":
    run("part_player", journey)
