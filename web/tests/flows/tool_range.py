"""The free vocal range test (js/public/tools/vocal-range-test.js, /tools/vocal-range-test) on its demo page, sung into
without a mic, against the competitor review's MUSTs and SHOULDs (docs/tools/competitors-tuner-metronome-range.md): Start;
the low step counts only a note held for a second and keeps the lowest; Done plays it back ("We heard G2"); Not quite redoes
the step; the high step says "Stop before it hurts" and asks about falsetto; the result is G2 to E4 with its span, the
baritone guess, the choir part and the honest line, on the keyboard and a staff; More compares every type; Options saves on
this device; the manual mode picks notes by tapping keys. Both skins (FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CARD = "document.querySelector('[data-tool=range]')"


def journey(f):
    f.go("/tests/range-demo.html?play=1", "window.__range")
    f.run("localStorage.removeItem('stackapella.tools.range')")
    f.no_sideways("the range test")
    f.shot("intro")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.step === 'low' && window.__sing", what="the low step")
    # A passing note (half a second) doesn't count; held notes do, and the lowest is kept.
    f.run("window.__sing(45, 0.5); window.__sing(null, 0.5)")
    assert f.js("document.querySelector('[data-ui=tool-primary]').disabled"), "nothing held yet"
    f.run("window.__sing(48, 1.3); window.__sing(null, 0.5); window.__sing(43, 1.3); window.__sing(null, 0.5); window.__sing(46, 1.3)")
    f.wait("document.querySelector('[data-ui=tool-stat]').textContent === 'Lowest so far: G2'", what="the lowest held")
    f.shot("low")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait("document.querySelector('[data-heard]').textContent === 'We heard G2'", what="the play-back check")
    f.shot("check")
    # Not quite: the step again.
    f.run("document.querySelector('[data-retry]').click()")
    f.wait(f"{CARD}.dataset.step === 'low'", what="low again")
    f.run("window.__sing(43, 1.3)")
    f.wait("!document.querySelector('[data-ui=tool-primary]').disabled", what="held again")
    f.run("document.querySelector('[data-ui=tool-primary]').click(); document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.step === 'high'", what="the high step")
    assert f.js("document.querySelector('[data-strain]').textContent") == "Light and easy, no straining: stop at the first sign of strain."
    f.run("window.__sing(60, 1.3); window.__sing(null, 0.4); window.__sing(64, 1.3); window.__sing(null, 0.4); window.__sing(66, 0.4)")
    f.wait("document.querySelector('[data-ui=tool-stat]').textContent === 'Highest so far: E4'", what="the highest held")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait("!document.querySelector('[data-falsetto]').hidden", what="the falsetto question")
    f.run("document.querySelector('[data-falsetto-answer=\"no\"]').click(); document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.step === 'result'", what="the result")
    assert f.js("document.querySelector('[data-range]').textContent") == "G2 to E4"
    assert f.js("document.querySelector('[data-span]').textContent") == "1 octave and 9 notes (21 semitones)", f.js("document.querySelector('[data-span]').textContent")
    assert f.js("document.querySelector('[data-type]').textContent") == "Your range fits the usual baritone range."
    assert f.js("document.querySelector('[data-choir]').textContent") == "In a choir you would likely sing Bass or Tenor 2."
    assert f.js("document.querySelectorAll('[data-staff] ellipse').length") == 2, "both notes on the staff"
    assert f.js("JSON.stringify(window.__results)") == '[{"low":43,"high":64,"type":"baritone"}]'
    f.no_sideways("the result")
    # A piano's colours in every theme: in a light one the white keys are still the light ones.
    light = f.js("""(() => { const root = document.documentElement; const was = root.dataset.theme; root.dataset.theme = 'warm-light';
        const lum = (el) => { const [r, g, b] = getComputedStyle(el).backgroundColor.match(/[\\d.]+/g).map(Number); return r + g + b; };
        const white = document.querySelector('[data-keyboard] [data-key=\"84\"]'); const black = document.querySelector('[data-keyboard] [data-key=\"82\"]');
        const ok = lum(white) > 600 && lum(black) < 200; root.dataset.theme = was; return ok; })()""")
    assert light, "ivory white keys and near-black black keys in a light theme"

    f.shot("result")
    # Share my range: a card made on the device for the share sheet, with the line and the tool's link (?ref=share-range).
    f.run("""window.__sent = null; navigator.canShare = (d) => !!d?.files?.length; navigator.share = async (d) => { window.__sent = { type: d.files[0].type, size: d.files[0].size, name: d.files[0].name, text: d.text, url: d.url }; };""")
    f.run("document.querySelector('[data-share]').click()")
    f.wait("window.__sent && window.__shared === 1", what="the card shared and counted")
    sent = f.js("window.__sent")
    assert sent["type"] == "image/png" and sent["size"] > 20000 and sent["name"] == "my-vocal-range.png", sent
    assert sent["url"].endswith("/tools/vocal-range-test?ref=share-range"), sent["url"]
    assert sent["text"] == "My range: G2 to E4, baritone range. Find yours:", sent["text"]
    # Where files can't be shared (most desktops): the card downloads and the line and link are copied.
    f.run("navigator.canShare = () => false; window.__copied = null; navigator.clipboard.writeText = async (t) => { window.__copied = t; };")
    f.run("document.querySelector('[data-share]').click()")
    f.wait("window.__shared === 2 && window.__copied", what="the card saved and the line copied")
    assert f.js("document.querySelector('[data-share-said]').textContent") == "Card saved, and the line copied"
    assert f.js("window.__copied").endswith("Find yours: " + sent["url"]), f.js("window.__copied")
    f.shot("shared")
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    f.wait("document.querySelectorAll('[data-compare] > div').length === 6", what="every type compared")
    f.run("document.querySelector('[data-save]').click()")
    f.wait("document.querySelector('[data-history] li')", what="saved")
    f.shot("more")
    # No mic: the lowest and highest picked on the keyboard.
    f.run("document.querySelector('[data-ui=tool-primary]').click(); document.querySelector('[data-manual]').click()")
    f.wait(f"{CARD}.dataset.step === 'manual'", what="manual")
    f.run("document.querySelector('[data-key=\"48\"]').click(); document.querySelector('[data-key=\"69\"]').click()")
    f.wait(f"{CARD}.dataset.step === 'result'", what="a manual result")
    assert f.js("document.querySelector('[data-range]').textContent") == "C3 to A4"
    assert f.js("document.querySelector('[data-type]').textContent") == "Your range fits the usual tenor range."
    f.run("localStorage.removeItem('stackapella.tools.range')")


if __name__ == "__main__":
    run("tool_range", journey)
