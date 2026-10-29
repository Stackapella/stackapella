"""Trimming (feature 17): a project with two recorded parts, each with room noise before and after
its singing. In the studio, a part's Trim opens the sheet; each end's suggestion trims around the
singing, the lane shows it, and closing offers Undo, which puts the take back whole. Same end lines
up both parts, and its Undo puts them back.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SESSION = "(await import('/js/stores/session.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_part(f, name):
    """The part's row, opened in place (its Trim is there)."""
    f.run(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({json.dumps(name)})).click()")
    f.wait("document.querySelector('button[aria-expanded=true]')", what="the open row")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    project_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const { peaks } = await import('/js/dsp.js');
        const project = await store.createProject({ title: 'Trim test', bpm: 120 });
        const sr = 48000;
        // Noise, then singing (from 1.1 s to 3.3 s, or 1.6 s to 4.1 s), then noise to 5 s.
        const take = (from, to, hz) => new Float32Array(sr * 5).map((_, i) => {
            const t = i / sr;
            return 0.003 * Math.sin(i * 12.9898) + (t >= from && t <= to ? 0.3 * Math.min(1, (t - from) / 0.04, (to - t) / 0.06) * Math.sin(2 * Math.PI * hz * t) : 0);
        });
        for (const [n, [from, to, hz]] of [[0, [1.1, 3.3, 220]], [1, [1.6, 4.1, 277]]]) {
            const samples = take(from, to, hz);
            await store.savePart({ id: crypto.randomUUID(), projectId: project.id, name: n ? 'Harmony' : 'Lead', color: n ? '#93c26e' : '#f2a06b', order: n, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
                offsetSeconds: 0, nudgeSeconds: 0, sampleRate: sr, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }, samples);
        }
        return project.id;
    })()""")
    f.go(f"/p/{project_id}", "document.querySelector('#app')")
    f.wait(f"{SESSION}.session.parts.length === 2", what="the two parts")
    open_part(f, "Lead")
    f.shot("part")
    # Voice and more: the part's panel (107.png).
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[data-part-panel]')", what="the part's panel")
    f.shot("panel")
    f.press("Escape")
    f.wait("!document.querySelector('[data-part-panel]')", what="the panel closed")
    f.click_t("trim.button")
    f.wait_text(t(f, "trim.title", {"part": "Lead"}))
    f.shot("sheet")
    # Each end's suggestion (the silence finder's) trims around the singing.
    lead = f"{SESSION}.session.parts.find((p) => p.name === 'Lead')"
    f.run("document.querySelector('[data-edge=start] button.border-dashed').click()")
    f.wait(f"{lead}.trimStartFrames > 0", what="the start trimmed")
    f.run("document.querySelector('[data-edge=end] button.border-dashed').click()")
    f.wait(f"{lead}.trimEndFrames > 0", what="the end trimmed")
    trims = f.js(f"(async () => {{ const p = {lead}; return [p.trimStartFrames / 48000, (p.frames - p.trimEndFrames) / 48000]; }})()")
    assert abs(trims[0] - 1.0) < 0.03 and abs(trims[1] - 3.4) < 0.03, f"trimmed around the singing: {trims}"
    f.shot("trimmed")
    f.click_t("common.done")
    f.wait_text(t(f, "trim.done", {"part": "Lead"}))
    f.shot("lane")
    f.click_t("common.undo")
    f.wait(f"{lead}.trimStartFrames === 0 && {lead}.trimEndFrames === 0", what="Undo put the take back whole")

    # Same end: both parts end together, where the last singing ends (on the beat after it).
    open_part(f, "Harmony")
    f.click_t("trim.button")
    f.click_t("trim.sameEnd")
    f.wait_text(t(f, "trim.linedEnd"))
    ends = f.js(f"{SESSION}.session.parts.map((p) => (p.frames - p.trimEndFrames) / p.sampleRate)")
    assert all(abs(e - 4.5) < 0.001 for e in ends), f"both end on the beat after the singing: {ends}"
    f.shot("lined-up")
    f.click_t("common.undo")
    f.wait(f"{SESSION}.session.parts.every((p) => !p.trimEndFrames)", what="Undo put both back")


if __name__ == "__main__":
    run("trim", journey)
