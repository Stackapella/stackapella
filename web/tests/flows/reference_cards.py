"""The reference pages' cards (js/public/reference-cards.js, docs/seo-growth-plan.md item 4) on their demo page
(web/tests/reference-cards-demo.html): the major third's card marks C4 and E4 on the keyboard and staff, plays up,
down and together, pure at 5:4 (386.3 cents, 13.7 narrower than a piano's 400), and moves its root a semitone (to D♭, spelled as theory books do); the
dominant seventh's card plays its four notes together and one at a time, its seventh 31.2 cents lower when pure;
the tenor's card shades C3 to A4, plays its ends and the notes between, and links to the range test. Nothing spills
sideways at 320 px. Both skins (FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SIZES, run  # noqa: E402

# Every oscillator the page makes, kept so the flow can read what it played.
CATCH = """(() => { window.__osc = []; const make = AudioContext.prototype.createOscillator;
  AudioContext.prototype.createOscillator = function () { const o = make.call(this); window.__osc.push(o); return o; }; })()"""
HEARD = "window.__osc.map((o) => Math.round(o.frequency.value * 100) / 100)"


def play(f, key):
    f.run("window.__osc = []")
    f.run(f"document.querySelector('[data-play={key}]').click()")
    return f.js(HEARD)


def marked(f):
    return f.js("[...document.querySelectorAll('[data-ui=keyboard] [data-marked]')].map((k) => Number(k.dataset.midi)).sort((a, b) => a - b)")


def fact(f, name):
    return f.js(f"document.querySelector('[data-fact={name}]').textContent")


def narrow(f, where):
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": 320, "height": 720, "deviceScaleFactor": 2, "mobile": True})
    f.js("new Promise((r) => setTimeout(r, 300))")
    f.no_sideways(f"{where} at 320 px")
    width, height, mobile = SIZES[f.size]
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": height, "deviceScaleFactor": 2 if mobile else 1, "mobile": mobile})
    f.js("new Promise((r) => setTimeout(r, 300))")


def journey(f):
    f.go("/tests/reference-cards-demo.html?card=interval&semis=4", "window.__card")
    f.run(CATCH)
    assert marked(f) == [60, 64], f"C4 and E4 marked: {marked(f)}"
    assert f.js("document.querySelectorAll('[data-ui=ref-staff] svg [data-column]').length") == 3, "up, and together, on the staff"
    assert fact(f, "equal") == "400 cents" and fact(f, "pure") == "386.3 cents, 13.7 narrower", (fact(f, "equal"), fact(f, "pure"))
    assert play(f, "up") == [261.63, 329.63], "up: C4 then E4"
    assert play(f, "down") == [329.63, 261.63], "down: E4 then C4"
    f.run("document.querySelector('[data-tuning] [data-segment=pure]').click()")
    assert play(f, "together") == [261.63, 327.03], "together, pure: E at 5:4"
    f.no_sideways("the interval card")
    f.shot("interval")
    f.run("document.querySelector('[data-root-step=\"1\"]').click()")
    assert marked(f) == [61, 65] and f.js("document.querySelector('[data-root-name]').textContent") == "D♭4", f"the root a semitone up: {marked(f)}"
    narrow(f, "the interval card")

    f.go("/tests/reference-cards-demo.html?card=chord&quality=dom7", "window.__card")
    f.run(CATCH)
    assert marked(f) == [60, 64, 67, 70], f"C7's notes: {marked(f)}"
    assert fact(f, "tone-10") == "B♭4, 31.2 cents lower when pure", fact(f, "tone-10")
    assert f.js("[...document.querySelectorAll('[data-ui=ref-staff] text')].some((t) => t.textContent === '♭')"), "the seventh written as B♭ on the staff"
    assert len(play(f, "together")) == 4, "four notes together"
    assert len(play(f, "arpeggio")) == 8, "one at a time, then together"
    f.shot("chord")
    narrow(f, "the chord card")

    f.go("/tests/reference-cards-demo.html?card=voice&type=tenor", "window.__card")
    f.run(CATCH)
    assert marked(f) == [48, 69], f"the tenor's ends: {marked(f)}"
    shaded = f.js("document.querySelectorAll('[data-ui=keyboard] [data-shaded]').length")
    assert shaded == 69 - 48 - 1, f"the notes between shaded: {shaded}"
    assert fact(f, "range") == "C3 to A4" and fact(f, "part") == "Tenor"
    assert play(f, "lowest") == [130.81] and play(f, "highest") == [440.0]
    scale = play(f, "scale")
    assert scale[0] == 130.81 and scale[-1] == 440.0 and len(scale) == 13, f"the white notes C3 to A4: {scale}"
    assert f.js("document.querySelector('[data-ui=ref-find]').getAttribute('href')") == "/tools/vocal-range-test"
    f.shot("voice")
    narrow(f, "the voice range card")


if __name__ == "__main__":
    run("reference_cards", journey)
