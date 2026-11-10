"""Every free tool remembers its settings on this device (Hillel: "make sure all of the tools remember the settings locally";
js/public/tool-prefs.js): on each tool's demo page a setting is changed, the page reloaded, and the setting is still there,
saved under stackapella.tools.<slug> as { v, updatedAt, settings }. A tool joins by adding a row to TOOLS. Both skins
(FLOW_SKINS=game,serious)."""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# (demo page, its ready check, the store's slug, a change to make, a check that it stuck, what it is)
TOOLS = [
    ("tuner-demo.html", "window.__tuner", "tuner",
     "document.querySelector('[data-transpositions] [data-segment=\"B♭\"]').click()",
     "document.querySelector('[data-transpositions] [data-segment=\"B♭\"]').getAttribute('aria-pressed') === 'true'",
     "the tuner's transposition"),
    ("metronome-demo.html", "window.__metronome", "metronome",
     "(() => { const i = document.querySelector('[data-bpm]'); i.value = '133'; i.dispatchEvent(new Event('change')); })()",
     "document.querySelector('[data-bpm]').value === '133'",
     "the metronome's tempo"),
    ("range-demo.html", "window.__range", "range",
     "document.querySelector('[data-naming=\"fixed-do\"]').click()",
     "document.querySelector('[data-naming=\"fixed-do\"]').getAttribute('aria-pressed') === 'true'",
     "the range test's note names"),
    ("chord-transposer-demo.html", "window.__transposer", "chord-transposer",
     "document.querySelector('[data-spelling=\"flats\"]').click()",
     "document.querySelector('[data-spelling=\"flats\"]').getAttribute('aria-pressed') === 'true'",
     "the transposer's spelling"),
    ("satb-voicer-demo.html", "window.__voicer", "four-part-harmony",
     "document.querySelector('[data-styles] [data-segment=\"warm\"]').click()",
     "document.querySelector('[data-styles] [data-segment=\"warm\"]').getAttribute('aria-pressed') === 'true'",
     "the four-part voicer's style"),
    ("note-frequencies-demo.html", "window.__chart", "note-frequency-chart",
     "document.querySelector('[data-naming=\"fixed-do\"]').click()",
     "document.querySelector('[data-naming=\"fixed-do\"]').getAttribute('aria-pressed') === 'true'",
     "the note chart's note names"),
    ("intonation-meter-demo.html", "window.__meter", "just-intonation-tuner",
     "document.querySelector('[data-segment=\"dom7\"]').click()",
     "document.querySelector('[data-segment=\"dom7\"]').getAttribute('aria-pressed') === 'true'",
     "the just intonation tuner's chord"),
    ("vocal-key-finder-demo.html", "window.__finder", "vocal-key-finder",
     "(() => { const v = document.querySelector('[data-voice-pick]'); v.value = 'alto'; v.dispatchEvent(new Event('change')); })()",
     "document.querySelector('[data-voice-pick]').value === 'alto'",
     "the key finder's voice"),
    ("pitch-matching-demo.html", "window.__game", "pitch-matching",
     "document.querySelector('[data-segment=\"low\"]').click()",
     "document.querySelector('[data-segment=\"low\"]').getAttribute('aria-pressed') === 'true'",
     "pitch matching's voice"),
    ("tap-tempo-demo.html", "window.__tool", "tap-tempo",
     "(() => { const s = document.querySelector('[data-per-tap]'); s.value = '3'; s.dispatchEvent(new Event('change')); })()",
     "document.querySelector('[data-per-tap]').value === '3'",
     "tap tempo's bar of 3"),
    ("latency-demo.html", "window.__latency", "audio-latency-test",
     "document.querySelector('[data-route=\"speakers\"]').click()",
     "document.querySelector('[data-route=\"speakers\"]').getAttribute('aria-pressed') === 'true'",
     "the latency test's route"),
    ("interval-demo.html", "window.__trainer", "interval-ear-training",
     "document.querySelector('[data-ui=tool-modes] [data-mode=\"down\"]').click()",
     "document.querySelector('[data-ui=tool-modes] [data-mode=\"down\"]').getAttribute('aria-pressed') === 'true'",
     "the interval trainer's direction"),
    ("chord-demo.html", "window.__trainer", "chord-ear-training",
     "document.querySelector('[data-ui=tool-modes] [data-mode=\"up\"]').click()",
     "document.querySelector('[data-ui=tool-modes] [data-mode=\"up\"]').getAttribute('aria-pressed') === 'true'",
     "the chord trainer's way of playing"),
    ("warmup-player-demo.html", "window.__warmup", "warmups-oo-scale",
     "document.querySelector('[data-segment=\"high\"]').click()",
     "document.querySelector('[data-segment=\"high\"]').getAttribute('aria-pressed') === 'true'",
     "the warm-up player's voice"),
]


def journey(f):
    for page, ready, slug, change, stuck, what in TOOLS:
        key = f"stackapella.tools.{slug}"
        f.go(f"/tests/{page}", ready)
        f.run(f"localStorage.removeItem({json.dumps(key)}); window.__old = true; location.reload()")
        f.wait(f"!window.__old && {ready}", what=f"{what}: a fresh start")
        f.run(change)
        f.wait(stuck, what=f"{what}: changed")
        record = f.js(f"JSON.parse(localStorage.getItem({json.dumps(key)}) || 'null')")
        assert record and record.get("v") and record.get("updatedAt") and "settings" in record, f"{what}: saved as a versioned record: {record}"
        f.run("window.__old = true; location.reload()")
        f.wait(f"!window.__old && {ready}", what=f"{what}: reloaded")
        f.wait(stuck, what=f"{what}: remembered after a reload")
        f.run(f"localStorage.removeItem({json.dumps(key)})")


if __name__ == "__main__":
    run("tools_remember", journey)
