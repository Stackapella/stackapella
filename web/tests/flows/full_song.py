"""Every song's play button plays the whole song, and answers a press at once (js/public/mixer.js, play-state.js; Hillel,
2026-11-12: "let's always play the full songs. none are that long" and "make sure there is instant feedback if we need
to load for good UX"). On a collection page a card's Play shows loading in the same frame as the press, then plays,
its ring filling with the song's progress, its part rows lit while their part sings, and it goes on past its opening
line (the rest comes from the catalogue and joins on the audio clock). A part page's player whose song is slow to come
says "Still loading" after a few seconds, then plays. Needs the real server (site pages)."""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CARD = "document.querySelector('#songbook [data-player]')"
# The catalogue's song answers this many ms late (the page's own fetch, wrapped before any page script runs).
SLOW_SONG = """(() => { const real = window.fetch; window.fetch = (input, init) => { const url = String(input?.url ?? input);
  return /\\/api\\/templates\\/[^/?]+/.test(url) ? new Promise((resolve) => setTimeout(resolve, 9000)).then(() => real(input, init)) : real(input, init); }; })();"""


def journey(f):
    f.go("/songs/hymns", CARD + " && " + CARD + ".hasAttribute('data-ready')")
    f.wait("document.fonts.status === 'loaded'", what="fonts")
    song = json.loads(f.js(CARD + ".querySelector('[data-song]').textContent"))
    assert song["whole"] and song["songBeats"] > song["beats"], f"the card's song isn't whole: {song['song']}"
    f.js(CARD + ".scrollIntoView({ block: 'center' })")
    # Pressed: loading at once, in the same turn as the press.
    pressed = f.js("(() => { const card = " + CARD + "; card.querySelector('[data-play]').click(); return card.hasAttribute('data-loading') && card.querySelector('[data-play]').getAttribute('aria-busy') === 'true'; })()")
    assert pressed, "a press didn't show loading at once"
    f.wait(CARD + ".hasAttribute('data-playing')", timeout=40, what="the card playing")
    assert not f.js(CARD + ".hasAttribute('data-loading')"), "still loading while it plays"
    f.wait(CARD + ".querySelector('[data-part-key][data-sounding]') !== null", timeout=10, what="a part row lit")
    f.wait("Number(getComputedStyle(" + CARD + ").getPropertyValue('--p')) > 0", timeout=10, what="the progress ring moving")
    f.shot("card-playing")
    if f.size == "desktop":
        # Past its opening line it still plays: the rest has joined.
        line = song["beats"] * 60 / song["tempo"]
        time.sleep(line + 3)
        assert f.js(CARD + ".hasAttribute('data-playing')"), f"it stopped at the end of its opening line ({line:.1f} s)"
        progress = float(f.js("getComputedStyle(" + CARD + ").getPropertyValue('--p')") or 0)
        whole = song["songBeats"] * 60 / song["tempo"]
        assert progress < 1 and progress > (line - 1) / whole, f"the progress isn't the whole song's: {progress}"
    # Stop.
    f.run(CARD + ".querySelector('[data-play]').click()")
    f.wait("!" + CARD + ".hasAttribute('data-playing')", timeout=5, what="stopped")

    # A part page whose song is slow to come: loading at once, then "Still loading", then it plays.
    script = f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": SLOW_SONG})["identifier"]
    try:
        f.go("/songs/amazing-grace/alto", "document.querySelector('[data-ui=part-play]')")
        button = "document.querySelector('[data-ui=part-play]')"
        f.js(button + ".scrollIntoView({ block: 'center' })")
        pressed = f.js("(() => { " + button + ".click(); return " + button + ".getAttribute('aria-busy') === 'true'; })()")
        assert pressed, "the part page's press didn't show loading at once"
        f.wait("document.querySelector('[data-ui=part-player], [data-ui=part-play]').closest('[data-slow]') !== null && " + button + ".querySelector('.s-play-hint').textContent.length > 0", timeout=8, what="Still loading")
        f.shot("part-still-loading")
        f.wait(button + ".dataset.state === 'playing'", timeout=40, what="the part playing")
        assert f.js(button + ".querySelector('.s-play-hint').textContent") == "", "the hint stayed while it plays"
        f.run(button + ".click()")
    finally:
        f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": script})


run("full_song", journey)
