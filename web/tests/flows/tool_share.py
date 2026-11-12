"""Three tools' answers, shared (docs/improvement-log.md, row 5): the vocal key finder's key, the four-part harmony
voicer's parts and the chord transposer's change each offer one quiet Share once there's an answer worth showing; it makes
a card on the device (result-card.js drawAnswerCard) with the tool's link tagged share-<slug>, and the page counts it as
sh:tool-<slug> (page.js onShared). The transposer's card carries the chord names, never the chart's words. On the demo
pages; where files can't be shared (a desktop), the card downloads and the line is copied.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# The card as it downloads, kept to show (the page frees its own address a second later).
CATCH = """navigator.canShare = () => false; window.__lines = [];
navigator.clipboard.writeText = async (line) => { window.__lines.push(line); };
const click = HTMLAnchorElement.prototype.click;
HTMLAnchorElement.prototype.click = function () { if (this.download) fetch(this.href).then((r) => r.blob()).then((b) => { window.__card = URL.createObjectURL(b); window.__cardName = this.download; }); else click.call(this); };"""
SHOW = """const img = Object.assign(document.createElement('img'), { src: window.__card });
img.style.cssText = 'position:fixed;inset:0;margin:auto;max-height:92vh;max-width:92vw;z-index:9999;border-radius:16px;box-shadow:0 0 0 9999px rgba(0,0,0,.7)';
img.dataset.cardShown = ''; document.body.append(img);"""


def share(f, tool, slug, file_name, name):
    f.run(CATCH)
    f.wait("document.querySelector('[data-share]') && !document.querySelector('[data-share]').hidden", what=f"{tool}'s Share")
    f.no_sideways(tool)
    f.shot(f"{name}-share")
    f.run("document.querySelector('[data-share]').click()")
    f.wait("window.__card && window.__shared?.length", what=f"{tool}'s card made and counted")
    assert f.js("window.__shared.at(-1)") == [slug, "saved"], f.js("window.__shared")
    assert f.js("window.__cardName") == file_name
    line = f.js("window.__lines.at(-1)")
    assert line.endswith(f"/tools/{slug}?ref=share-{slug}"), line
    f.wait("document.querySelector('[data-share-said]').textContent.trim()", what="Card saved")
    f.run(SHOW)
    f.wait("document.querySelector('[data-card-shown]').complete", what="the card shown")
    f.shot(f"{name}-card")
    return line


def journey(f):
    # The key finder: a song in G, D4 to E5, for a tenor: a key that fits, shared with the tool's tag.
    f.go("/tests/vocal-key-finder-demo.html", "window.__finder && document.querySelector('[data-answer]')?.textContent")
    line = share(f, "the key finder", "vocal-key-finder", "my-key.png", "key")
    assert line.startswith("I sing it in "), line

    # The voicer: its sample voiced; Share the parts.
    f.go("/tests/satb-voicer-demo.html", "window.__voicer && document.querySelector('[data-rows] tr')")
    line = share(f, "the voicer", "four-part-harmony", "four-part-harmony.png", "satb")
    assert line.startswith("Four-part harmony for "), line

    # The transposer: nothing to share until the chart moves; then the change, never the words.
    f.go("/tests/chord-transposer-demo.html", "window.__transposer && document.querySelector('header [data-tool-key] [data-step]')")
    f.run("window.__transposer.set('G        C\\nAmazing grace, how sweet\\nD        G\\nthe sound'); window.__transposer.shift(0)")
    assert f.js("document.querySelector('[data-share]').hidden"), "no Share before the chart moves"
    f.run("window.__transposer.shift(2)")
    line = share(f, "the transposer", "chord-transposer", "chords-in-a-new-key.png", "transposer")
    assert line.startswith("From G to A"), line
    assert "Amazing" not in line, "never the chart's words"


run("tool_share", journey)
