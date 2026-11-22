"""The count-in counts up (Hillel: "change the count in here (and everywhere) to 1 2 3 4, start"; 2026-11-22: "count to 4
and show 1 2 3 4 on the screen in large nice fading numbers", "or count to 3 if it's a waltz", for every recording): one
bar of the song's metre, counted 1 to N, ending exactly where the singing starts, pickup or not, each number also large
in the middle; then recording starts, never "Recording in 3". Amazing Grace (3/4, a pickup) counts 1 2 3; Are You
Sleeping (4/4, on the downbeat) counts 1 2 3 4.
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
    for template, expected in (("amazing-grace", [1, 2, 3]), ("are-you-sleeping", [1, 2, 3, 4])):
        count_in(f, template, expected)
    clear_projects(f)


def count_in(f, template, expected):
    clear_projects(f)
    song = new_song(f, template)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + repr(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    # Every frame from Record: the number shown, until recording starts.
    f.run("""window.__shown = []; const watch = () => {
            const el = document.querySelector('[data-count-in]'); const n = el ? +el.dataset.countIn : null;
            if (n && window.__shown.at(-1) !== n) window.__shown.push(n);  // 0: before the first click, nothing shown
            window.__big ||= !!document.querySelector('[data-count-big]');
            if (!window.__started) requestAnimationFrame(watch); };
        window.__started = false; requestAnimationFrame(watch);""")
    # What a screen reader hears (the view's polite line): the count-in once, then Recording, never each beat.
    f.run("""window.__said = []; const line = document.querySelector('#main p.sr-only[aria-live=polite]');
        new MutationObserver(() => { const s = line.textContent.trim(); if (s && window.__said.at(-1) !== s) window.__said.push(s); }).observe(line, { childList: true, characterData: true, subtree: true });""")
    f.press("r")
    f.wait(f"{SESSION}.session.recording && {SESSION}.session.countIn === null && {SESSION}.session.position > {SESSION}.session.recordFrom + 0.2", timeout=30, what="recording after the count-in")
    f.run("window.__started = true;")
    assert f.js("window.__big"), "no large count-in number"
    shown = f.js("window.__shown")
    print(f"    {template}: count-in shown {shown}")
    assert shown == expected, f"the count-in showed {shown}, the clicks were on beats {expected}"
    assert not f.js("document.body.innerText.includes('Recording in')"), "a countdown is back"
    said = f.js("window.__said")
    assert said[-2:] == [t(f, "sing.say.countIn"), t(f, "sing.say.recording")], f"a screen reader heard {said}"
    assert not any(s.strip().isdigit() for s in said), f"each beat was said: {said}"
    f.run("document.querySelector('[data-stop]')?.click()")
    f.wait(f"!{SESSION}.session.recording", timeout=20, what="stopped")


if __name__ == "__main__":
    run("count_in", journey)
