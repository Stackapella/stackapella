"""Harmonise my song (docs/harmonise-my-song-plan.md, the MVP): an own song with one sung take, the
start of Amazing Grace sung by the test's singer. Its part's panel has Harmonise this, which opens the
creator's Parts step with the alto, tenor and bass suggested under the tune; Try another chord changes
a bar; a piano and drums go under them (on the synth here: no samples); Add to my song puts them all
in the same song as guide parts, the take untouched. A take of only
room noise gets a kind note offering to sing it again or tap the notes in.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import t  # noqa: E402

SESSION = "(await import('/js/stores/session.js'))"
TUNE = [
    {"beat": 0, "length": 1, "pitch": 62}, {"beat": 1, "length": 2, "pitch": 67}, {"beat": 3, "length": 1, "pitch": 71}, {"beat": 4, "length": 2, "pitch": 71},
    {"beat": 6, "length": 1, "pitch": 69}, {"beat": 7, "length": 2, "pitch": 67}, {"beat": 9, "length": 1, "pitch": 64}, {"beat": 10, "length": 2, "pitch": 62},
    {"beat": 12, "length": 1, "pitch": 62}, {"beat": 13, "length": 2, "pitch": 67}, {"beat": 15, "length": 1, "pitch": 71}, {"beat": 16, "length": 2, "pitch": 69},
]


def own_song(f, title, noise_only=False):
    """An own song (no template) with one take: the tune sung at 80 a minute, or only room noise."""
    return f.js(f"""(async () => {{
        const store = await import('/js/stores/projects.js');
        const {{ peaks }} = await import('/js/dsp.js');
        const {{ sing }} = await import('/tests/fixtures.js');
        const project = await store.createProject({{ title: {json.dumps(title)}, bpm: 80, timeSignature: '4/4' }});
        const sr = 48000;
        const samples = {'new Float32Array(sr * 6).map((_, i) => 0.02 * Math.sin(i * 12.9898) * Math.sin(i * 78.233))' if noise_only else f"sing(sr, {{ notes: {json.dumps(TUNE)}, bpm: 80, vibrato: {{ hz: 5.5, cents: 14 }}, level: 0.28, noise: 0.002, seed: 4 }})"};
        await store.savePart({{ id: crypto.randomUUID(), projectId: project.id, name: 'Tune', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: sr, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }}, samples);
        return project.id;
    }})()""")


def harmonise_from_panel(f, project_id):
    f.go(f"/p/{project_id}", "document.querySelector('#app')")
    f.wait(f"{SESSION}.session.parts.length === 1", what="the take")
    f.run("[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes('Tune')).click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[data-part-panel]')", what="the part's panel")
    f.click_t("harmonise.button")
    f.wait("location.pathname.startsWith('/create')", what="the creator")


def journey(f):
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    song = own_song(f, "My tune")
    harmonise_from_panel(f, song)
    another = json.dumps(t(f, "create.parts.another"))
    f.wait(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {another})", timeout=30, what="the parts suggested, with Try another chord")
    f.shot("parts")
    f.run(f"[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === {another} && !b.disabled).click()")
    for key in ("create.parts.instrument.piano", "create.parts.instrument.drums"):
        label = json.dumps(t(f, key))
        f.run(f"[...document.querySelectorAll('fieldset label')].find((l) => l.textContent.trim() === {label}).click()")
    f.wait("document.querySelectorAll('fieldset input:checked').length === 2", what="the piano and drums chosen")
    assert f.js("[...document.querySelectorAll('fieldset input')].filter((i) => i.disabled).length === 2"), "two instruments at most"
    f.shot("instruments")
    f.click_t("harmonise.add")
    f.wait(f"location.pathname === '/p/{song}'", timeout=20, what="back in the song")
    f.wait(f"{SESSION}.session.parts.length === 6", timeout=30, what="the three suggested parts and two instruments added")
    seen = json.loads(f.js(f"""(async () => {{ const s = {SESSION}.session;
        return {{ keys: s.parts.map((p) => p.templatePartKey ?? null), played: s.parts.filter((p) => p.kind === 'instrument').map((p) => p.templatePartKey).sort(), frames: s.parts.map((p) => p.frames), tune: s.parts.find((p) => p.name === 'Tune')?.templatePartKey,
            template: !!s.project.template, key: s.project.key, bpm: s.project.bpm }}; }})().then(JSON.stringify)"""))
    assert seen["template"] and seen["bpm"] == 80, seen
    assert sorted(k for k in seen["keys"] if k) == ["alto", "bass", "drums", "piano", "soprano", "tenor"], seen
    assert seen["played"] == ["drums", "piano"], f"the instruments are played parts: {seen}"
    assert seen["tune"] == "soprano", f"the take is the tune: {seen}"
    assert sum(1 for n in seen["frames"] if n > 0) == 1, f"only the original take is recorded: {seen}"
    assert seen["key"] in ("G", "Em"), f"the key from the tune: {seen}"
    f.shot("studio")

    # Only room noise: said kindly, with a way on.
    noise = own_song(f, "Noisy room", noise_only=True)
    harmonise_from_panel(f, noise)
    f.wait_text(t(f, "harmonise.unclear.title"))
    f.wait_text(t(f, "harmonise.unclear.tap"))
    f.shot("unclear")
    f.click_t("harmonise.unclear.again")
    f.wait(f"location.pathname === '/p/{noise}'", what="back to sing it again")


if __name__ == "__main__":
    run("harmonise", journey, sizes=("desktop",))
