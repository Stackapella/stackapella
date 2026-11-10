"""The song part pages' player (js/public/part-player.js) on its demo page (web/tests/part-player-demo.html, the bundled
Amazing Grace's soprano): it loads when shown; Your part plays the part alone and With the others plays it with the
other parts; Sing it follows a stand-in mic (?sing=in sings the part's notes, ?sing=off a tritone away), lighting the
notes sung in tune and missing the rest, and says how many it lit; a new way to play starts the song again; nothing
spills sideways on a phone; Open in the app keeps the song and the part. Both skins (FLOW_SKINS=game,serious)."""
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

    # Your part (the default), then With the others: the song starts again for the new way to play it.
    play_for(f, 1.2, "the part alone, on the audio clock")
    assert f.js(f"{P}.mode()") == "part" and f.js(f"{PLAY}.dataset.state") == "playing"
    f.shot("part")
    mode(f, "all")
    f.wait(f"{P}.playing() && {P}.mode() === 'all' && {P}.time() < 1.5", timeout=20, what="with the others, from the start")
    f.wait(f"{P}.time() > 1", timeout=20, what="the others playing")
    f.run(f"{PLAY}.click()")
    f.wait(f"!{P}.playing() && {PLAY}.dataset.state === 'paused'", what="paused")
    held = f.js(f"{P}.time()")
    f.js("new Promise((r) => setTimeout(r, 400))")
    assert abs(f.js(f"{P}.time()") - held) < 0.01, "time moves while paused"

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
        line = f.js("document.querySelector('[data-ui=part-status]').textContent")
        assert line.endswith("notes lit"), line
        f.shot(f"sing-{sung}")
        f.run(f"{PLAY}.click()")
        f.wait(f"!{P}.playing()", what="paused")


if __name__ == "__main__":
    run("part_player", journey)
