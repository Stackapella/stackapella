"""Hillel: every singer has a stored range, and once it's set no part picker asks again. A fresh
guest opens a song and picks Alto (kept as their range, "picked"); the next song opens on the part
that fits it with no picker. Then Your voice says "Chosen by you", Change it sets a bass range, and
the next song opens on a low part."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, new_song, t  # noqa: E402

PROGRESS = "(async () => { const L = await import('/js/stores/learning.js'); await L.loadLearning(); const p = L.learning.progress; return JSON.parse(JSON.stringify({ range: p.range, source: p.rangeSource ?? null })); })()"
FOCUS = f"(async () => {{ const s = {SESSION}.session; return s.parts.find((p) => p.id === s.practice.focus)?.templatePartKey ?? null; }})()"


def open_sing(f, song):
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    f.js(f"{AUDIO}.unlock()")
    assert f.js(PROGRESS)["range"] is None, "a fresh guest has no range yet"

    # No range: Sing asks which part, once. Alto is picked, and kept as the range.
    open_sing(f, new_song(f, "amazing-grace"))
    f.wait_text(t(f, "sing.chooser.title"))
    f.run("document.querySelector(\'[data-way=whole]\').click()")  # the way starts at once
    f.wait_text(t(f, "sing.chooser.yourPart"))
    f.shot("asked-once")
    f.click("Alto")
    f.wait(f"{PROGRESS}.then((p) => p.source === 'picked')", what="the part kept as the range")
    kept = f.js(PROGRESS)
    assert kept["range"] == {"low": 59, "high": 71}, f"Amazing Grace's alto range kept: {kept}"

    # The next song: on a part that fits, no picker. Nothing sung yet, so the melody first (Hillel,
    # "Melody first"), in this alto range's own octave.
    song = new_song(f, "happy-birthday")
    open_sing(f, song)
    # This song's session (not the last one's), with a part picked.
    f.wait(f"{SESSION}.session.project?.id === {song!r} && {FOCUS}.then((k) => k !== null)", what="a part picked for you")
    melody = f.js("(async () => (await (await fetch('/templates/happy-birthday.json')).json()).parts.find((p) => p.melody).key)()")
    assert f.js(FOCUS) == melody, f"opened on {f.js(FOCUS)}, not the melody ({melody})"
    f.wait("document.body.innerText.includes('fits your range')", what="said once")
    assert t(f, "sing.chooser.yourPart") not in f.js("document.body.innerText"), "the part picker asked again"
    f.shot("no-picker")

    # Your voice: chosen by you; Change it to a bass range.
    f.go("/voice", "document.querySelector('[data-range-source]')")
    assert f.js("document.querySelector('[data-range-source]').textContent.trim()") == t(f, "voice.chosenByYou")
    f.click_t("voice.change.open")
    f.wait("document.querySelector('[data-change-range]')", what="Change it")
    f.run("document.querySelectorAll('[data-change-range] [role=radio]')[3].click()")
    f.shot("change-it")
    f.click_t("voice.change.save")
    f.wait(f"{PROGRESS}.then((p) => p.source === 'chosen' && p.range.low === 40 && p.range.high === 64)", what="the bass range kept, chosen")

    # The next song, for this low range: still nothing sung, so the melody, an octave lower.
    song = new_song(f, "amazing-grace")
    open_sing(f, song)
    f.wait(f"{SESSION}.session.project?.id === {song!r} && {FOCUS}.then((k) => k !== null)", what="a part picked for the new range")
    assert f.js(FOCUS) == "soprano", f"opened on {f.js(FOCUS)} for a new singer's bass range, not the melody"
    assert t(f, "sing.chooser.yourPart") not in f.js("document.body.innerText"), "the part picker asked again"

    # A measured range beats a picked one when two devices meet; a picked one never beats it.
    merged = f.js("""(async () => { const { mergeRange } = await import('/js/course.js');
        const measured = { range: { low: 48, high: 67 }, rangeSource: 'measured', rangeAt: 1 };
        const picked = { range: { low: 59, high: 71 }, rangeSource: 'picked', rangeAt: 2 };
        const chosen = { range: { low: 40, high: 64 }, rangeSource: 'chosen', rangeAt: 3 };
        return [mergeRange(measured, picked).rangeSource, mergeRange(picked, measured).rangeSource, mergeRange(measured, chosen).rangeSource]; })()""")
    assert merged == ["measured", "measured", "chosen"], f"merges: {merged}"


if __name__ == "__main__":
    run("range_store", journey)
