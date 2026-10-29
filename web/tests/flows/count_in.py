"""The count-in counts up (Hillel: "change the count in here (and everywhere) to 1 2 3 4, start"): the
recording stage shows each click's beat of the bar, 1 up to the meter's last, in step with the
clicks, then recording starts, never "Recording in 3". On Amazing Grace (3/4, a one-beat pickup) a
bar of count-in runs on to the pickup, so the clicks' beats are 1 2 3, then 1 2.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, t  # noqa: E402


def journey(f):
    f.go("/voice", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    f.run(f"""const a = {AUDIO}; await a.openMic();
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    # Every frame from Record: the number shown, until recording starts.
    f.run("""window.__shown = []; const watch = () => {
            const el = document.querySelector('[data-count-in]'); const n = el ? +el.dataset.countIn : null;
            if (n && window.__shown.at(-1) !== n) window.__shown.push(n);  // 0: before the first click, nothing shown
            if (!window.__started) requestAnimationFrame(watch); };
        window.__started = false; requestAnimationFrame(watch);""")
    f.press("r")
    f.wait(f"{SESSION}.session.recording && {SESSION}.session.countIn === null && {SESSION}.session.position > {SESSION}.session.recordFrom + 0.2", timeout=30, what="recording after the count-in")
    f.run("window.__started = true;")
    shown = f.js("window.__shown")
    expected = f.js(f"""(async () => {{ const s = {SESSION}.session; const perBar = +s.project.timeSignature.split('/')[0];
        const beats = Math.round(s.recordFrom * (s.project.bpm || 90) / 60); const into = (((beats - s.pickup) % perBar) + perBar) % perBar;
        const n = (s.project.countInBars ?? 1) * perBar + into; const out = [];
        for (let b = beats - n; b < beats; b++) out.push((((b - s.pickup) % perBar) + perBar) % perBar + 1);
        return out; }})()""")
    print(f"    count-in shown {shown}, the clicks' beats {expected}")
    assert shown == expected, f"the count-in showed {shown}, the clicks were on beats {expected}"
    assert not f.js("document.body.innerText.includes('Recording in')"), "a countdown is back"
    f.run("document.querySelector('[data-stop]')?.click()")
    clear_projects(f)


if __name__ == "__main__":
    run("count_in", journey)
