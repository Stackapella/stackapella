"""Hillel: once your range is known, a song opens on the part that fits it, said once; the part
picker isn't asked. Melody first (Hillel, 2026-10-29): with a low range (G2 to D4), a new singer's
Amazing Grace opens on the melody, sung an octave lower; once they've a good take, on the low part
that moves most (the bass, never the tenor's held G3s)."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SESSION = "(await import('/js/stores/session.js'))"


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    project = f.js("""(async () => {
        const { setRange } = await import('/js/stores/learning.js');
        await setRange(43, 62);
        const { loadTemplate } = await import('/js/templates.js');
        const { startFromTemplate } = await import('/js/stores/projects.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.practice.focus", what="a part picked for you")
    key = f.js(f"(async () => {{ const s = {SESSION}.session; return s.parts.find((p) => p.id === s.practice.focus).templatePartKey; }})()")
    assert key == "soprano", f"a new singer got {key}, not the melody"
    lower = f.js("(await import('/js/i18n.js')).t('sing.fitsYourRangeLower', { part: '' })").strip(" ,.")
    f.wait(f"document.body.innerText.includes({lower!r})", what="sung an octave lower, said once")
    assert f.js(f"{SESSION}.session.project.transpose || 0") == 0, "the melody an octave down keeps the song's key"
    f.shot("melody")
    assert f.js(f"{SESSION}.session.practice.sing?.fitSaid === true"), "not remembered as said"

    # A good take on another song: no longer new, so the low part that moves most.
    f.go("/", "document.querySelector('#app header')")
    project = f.js("""(async () => {
        const { loadTemplate } = await import('/js/templates.js');
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const db = await import('/js/db.js');
        const other = await startFromTemplate(await loadTemplate('happy-birthday'), { singers: 4 });
        const part = (await db.allBy('parts', 'projectId', other.id))[0];
        await db.put('parts', { ...part, frames: 48000, score: { stars: 2, pitchPercent: 80 } });
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.practice.focus", what="a part picked for you")
    key = f.js(f"(async () => {{ const s = {SESSION}.session; return s.parts.find((p) => p.id === s.practice.focus).templatePartKey; }})()")
    assert key == "bass", f"after a good take a low range got {key}, not the bass that moves most"
    f.wait("document.body.innerText.includes('fits your range')", what="said once")
    f.shot("harmony")


if __name__ == "__main__":
    run("fit_part", journey)
