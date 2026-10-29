"""The studio as Hillel reviews it (his desktop screenshot: a 3-part song, only the melody recorded):
the studio, a track expanded and Settings open, in Warm light at both sizes, through help_shots.py's
atlas mode, so before and after can be laid side by side.

    OUT=~/Code/shots-review/studio/after PORT=8410 tool/flows.sh studio_shots
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402
from help_shots import AUDIO, OUT, SESSION, clear_projects, compress, guides_ready, home_page, light_first, shoot, sung_takes  # noqa: E402


def journey(f):
    assert OUT, "the shots need OUT=<dir>"
    home_page(f, "member")
    light_first(f)
    clear_projects(f)
    song = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 3 })).id;
    })()""")
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 1")
    sung_takes(f, ["soprano"])  # Amazing Grace's melody: one of three parts recorded
    guides_ready(f)
    light_first(f)
    shoot(f, "studio", scroll="window.scrollTo(0, 0)")
    # A track expanded: its name opens pan, timing and more.
    f.run("document.querySelector('[data-lane]')?.closest('[data-row], li, section')?.querySelector('button[aria-expanded]')?.click()")
    f.js("new Promise((r) => setTimeout(r, 400))")
    shoot(f, "studio-track-open")
    f.press("Escape")
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 1")
    light_first(f)
    f.run("[...document.querySelectorAll('header button')].find((b) => /settings/i.test(b.getAttribute('aria-label') ?? ''))?.click()")
    f.wait("document.querySelector('[role=dialog]')", what="the studio's settings")
    shoot(f, "studio-settings")
    # Hillel's case: a song started empty (Just record), its first track recorded, two to come.
    f.press("Escape")
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic(); a.engine.setLoopback(true);
        sessionStorage.setItem('stackapella.headphones', '1');
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    plain = f.js("(async () => (await (await import('/js/stores/projects.js')).createProject({ title: 'Our Song', bpm: 90 })).id)()")
    f.go(f"/p/{plain}", "document.querySelector('#app header')")
    light_first(f)
    f.mic_checked()
    f.press("r")
    heard_while_recording(f, "the first track", seconds=2)
    f.press("r")
    f.wait(f"!{SESSION}.session.recording && {SESSION}.session.parts.length === 1", timeout=20, what="the first track kept")
    f.go(f"/p/{plain}", "document.querySelector('#app header')")
    light_first(f)
    shoot(f, "studio-plain", scroll="window.scrollTo(0, 0)")
    compress()
    f.js(f"{AUDIO}.engine.stop()")
    clear_projects(f)


if __name__ == "__main__":
    run("studio_shots", journey)
