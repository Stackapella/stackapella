"""The free metronome (js/public/tools/metronome.js, /tools/metronome) on its demo page, against the competitor review's
MUSTs and SHOULDs (docs/tools/competitors-tuner-metronome-range.md): 100 BPM, Andante, 4/4 with the one accented; minus,
plus, the arrows and typing set the tempo; tap tempo; a dot tapped cycles accent, normal, silent; 7/8 offers its groupings;
Play lights the dots as the clicks sound, on the audio clock, and stays steady (the drift check: the clicks scheduled over
ten seconds are exactly a beat apart, and the page's main thread being busy doesn't move them); a tempo change lands
on the next beat; More has the sound, count-in, trainer, silent bars, timer and presets; settings stay after a reload.
Both skins (FLOW_SKINS=game,serious)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

CARD = "document.querySelector('[data-tool=metronome]')"
BPM = "Number(document.querySelector('[data-bpm]').value)"


def journey(f):
    f.go("/tests/metronome-demo.html", "window.__metronome")
    f.run("localStorage.removeItem('stackapella.tools.metronome'); localStorage.removeItem('stackapella.tools.metronome.presets'); window.__old = true; location.reload()")
    f.wait("!window.__old && window.__metronome && document.querySelector('[data-bpm]')", what="fresh")
    assert f.js(BPM) == 100 and f.js("document.querySelector('[data-marking]').textContent") == "Andante"
    assert f.js("document.querySelector('[data-metre]').value") == "4/4"
    assert f.js("[...document.querySelectorAll('[data-dot]')].map(d => d.dataset.accent).join()") == "accent,normal,normal,normal"
    f.no_sideways("the metronome")
    # − and + sit inside the card at a phone's width (they spilled past it at 360 px).
    inside = f.js("""(() => { const c = document.querySelector('[data-tool=metronome]').getBoundingClientRect();
        return [...document.querySelectorAll('[data-nudge]')].every((b) => { const r = b.getBoundingClientRect(); return r.left >= c.left && r.right <= c.right; }); })()""")
    assert inside, "the tempo buttons stay inside the card"
    f.shot("start")
    # Minus and plus by one; the arrows with shift by five; typing a tempo.
    f.run("document.querySelector('[data-nudge=\"1\"]').click()")
    f.wait(f"{BPM} === 101", what="plus")
    f.run("document.querySelector('[data-nudge=\"-1\"]').focus(); document.querySelector('[data-nudge=\"-1\"]').dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowUp', shiftKey: true, bubbles: true }))")
    f.wait(f"{BPM} === 106", what="shift + arrow")
    f.run("const i = document.querySelector('[data-bpm]'); i.value = '72'; i.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('[data-marking]').textContent === 'Adagio'", what="typed 72, Adagio")
    # The marking's list jumps to its middle.
    f.run("document.querySelector('[data-marking]').click(); document.querySelector('[data-jump=\"138\"]').click()")
    f.wait(f"{BPM} === 138", what="Allegro")
    # Tap tempo: four taps half a second apart read 120 (the tool's own clock: performance.now()).
    f.run("""(() => { const t0 = performance.now(); let n = 0; const real = performance.now.bind(performance);
        performance.now = () => t0 + n * 500; for (n = 0; n < 4; n++) document.querySelector('[data-tap]').click(); performance.now = real; })()""")
    f.wait(f"{BPM} === 120", what="tap tempo")
    # 7/8 offers its three groupings.
    f.run("const m = document.querySelector('[data-metre]'); m.value = '7/8'; m.dispatchEvent(new Event('change'))")
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    f.wait("document.querySelector('[data-ui=tool-sheet]').open", what="More")
    assert f.js("[...document.querySelectorAll('[data-groups]')].map(b => b.dataset.groups).join(' ')") == "2+2+3 2+3+2 3+2+2"
    f.shot("more")
    f.run("const m = document.querySelector('[data-metre]'); m.value = '4/4'; m.dispatchEvent(new Event('change'))")
    f.run("document.querySelector('[data-sheet-done]').click()")
    # Play: the clicks go onto the audio clock; record when each is scheduled.
    f.run("""(() => { window.__starts = []; const proto = AudioBufferSourceNode.prototype; const start = proto.start;
        proto.start = function (when, ...rest) { window.__starts.push(when); return start.call(this, when, ...rest); }; })()""")
    # An iPhone's audio session (stubbed here): Start asks for playback, so the silent switch doesn't mute it (the tools review).
    f.run("Object.defineProperty(navigator, 'audioSession', { value: { type: 'auto' }, configurable: true })")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.state === 'playing'", what="playing")
    assert f.js("navigator.audioSession.type") == "playback", "Start sets the audio session to playback"
    f.wait("document.querySelector('[data-dot][data-lit=true]')", what="a beat lights")
    f.wait("/^· Bar \\d+ · Beat \\d of 4$/.test(document.querySelector('[data-status-hint]').textContent)", what="the bar and beat counted")
    assert f.js("document.querySelector('[data-ui=tool-label]').textContent") == "4 beats per bar"
    f.shot("playing")
    # A busy main thread (300 ms of work) doesn't move the clicks: they're on the audio clock.
    f.run("const end = performance.now() + 300; while (performance.now() < end) {}")
    f.wait("window.__starts.length >= 20", what="20 clicks", timeout=30)
    gaps = f.js("window.__starts.slice(1, 20).map((t, i) => t - window.__starts[i])")
    assert all(abs(g - 0.5) < 1e-6 for g in gaps), f"clicks exactly 0.5 s apart at 120: {gaps}"
    # A tempo change lands on the next beat, without a stop.
    f.run("window.__starts = []; document.querySelector('[data-nudge=\"1\"]').click()")
    f.wait("window.__starts.length >= 6", what="clicks after the change")
    gaps = f.js("window.__starts.slice(1, 6).map((t, i) => t - window.__starts[i])")
    assert abs(gaps[-1] - 60 / 121) < 1e-6, f"121 BPM by the next beats: {gaps}"
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.state === 'stopped'", what="stopped")
    # With the timer on, the pill counts down what's left of it.
    f.run("const m = document.querySelector('[data-field=minutes]'); m.value = '1'; m.dispatchEvent(new Event('change', { bubbles: true })); m.dispatchEvent(new Event('input', { bubbles: true }))")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait("/ · (0:5\\d|1:00) left$/.test(document.querySelector('[data-status-hint]').textContent)", what="the time left counted")
    f.run("document.querySelector('[data-ui=tool-primary]').click()")
    f.wait(f"{CARD}.dataset.state === 'stopped'", what="stopped again")
    f.run("const m = document.querySelector('[data-field=minutes]'); m.value = '0'; m.dispatchEvent(new Event('change', { bubbles: true })); m.dispatchEvent(new Event('input', { bubbles: true }))")
    # Space plays and stops when nothing is focused.
    f.run("document.activeElement.blur(); document.body.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true }))")
    f.wait(f"{CARD}.dataset.state === 'playing'", what="space plays")
    f.run("document.body.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true }))")
    f.wait(f"{CARD}.dataset.state === 'stopped'", what="space stops")
    # A dot cycles accent, normal, silent: the third, normal, goes silent.
    f.run("document.querySelector('[data-dot=\"2\"]').click()")
    f.wait("document.querySelector('[data-dot=\"2\"]').dataset.accent === 'silent'", what="silent dot")
    f.run("document.querySelector('[data-dot=\"0\"]').click()")
    f.wait("document.querySelector('[data-dot=\"0\"]').dataset.accent === 'normal'", what="accent to normal")
    f.run("document.querySelector('[data-dot=\"0\"]').click(); document.querySelector('[data-dot=\"0\"]').click()")
    f.wait("document.querySelector('[data-dot=\"0\"]').dataset.accent === 'accent'", what="round to accent")
    # A preset, kept on this device.
    f.run("document.querySelector('[data-ui=tool-more]').click()")
    f.run("const p = document.querySelector('[data-preset-name]'); p.value = 'Warm-up'; document.querySelector('[data-preset-form]').requestSubmit()")
    f.wait("document.querySelector('[data-preset]')?.textContent === 'Warm-up · 121'", what="preset saved")
    f.run("document.querySelector('[data-sheet-done]').click(); window.__old = true; location.reload()")
    f.wait("!window.__old && window.__metronome && document.querySelector('[data-bpm]')", what="reloaded")
    assert f.js(BPM) == 121 and f.js("document.querySelector('[data-dot=\"2\"]').dataset.accent") == "silent"
    f.run("localStorage.removeItem('stackapella.tools.metronome'); localStorage.removeItem('stackapella.tools.metronome.presets')")


if __name__ == "__main__":
    run("tool_metronome", journey)
