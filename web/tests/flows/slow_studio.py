"""A slow phone, part 2 (master plan 12): Sing and the studio on a CPU slowed 4x (SLOW_CPU), after a
first visit (the files in the browser's cache, as a returning singer's are). Prints what it measured,
and fails only past generous budgets:

- Sing opened: from the navigation until its stage can be used (the guides rendered, Play ready);
- Play: from the tap until the engine is playing (the first note's scheduling);
- Record: from the tap until the take is recording (the mic opened in setup, as the rule says);
- the studio opened: until its lanes are drawn and the guides are ready;
- four parts playing in the studio: the time between frames, and the main thread's long tasks.

    PORT=8341 tool/flows.sh slow_studio
"""
import json
import os
import statistics
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, guides_ready, new_song, sung_takes, t  # noqa: E402

CPU = float(os.environ.get("SLOW_CPU", "4"))
BUDGET = {"sing_open_ms": 6000, "play_ms": 1500, "record_ms": 2500, "studio_open_ms": 6000, "studio_p95_ms": 50}
OBSERVE = """window.__long = []; try { new PerformanceObserver((list) => { for (const e of list.getEntries()) window.__long.push([Math.round(e.startTime), Math.round(e.duration)]); }).observe({ type: 'longtask', buffered: true }); } catch (e) {}"""
NOW = "performance.now()"


def cpu(f, rate):
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": rate})


def frames(f, seconds=5):
    gaps = f.js(f"""new Promise((done) => {{ const gaps = []; let last = performance.now(); const end = last + {seconds * 1000};
        const tick = (now) => {{ gaps.push(now - last); last = now; if (now < end) requestAnimationFrame(tick); else done(gaps); }};
        requestAnimationFrame(tick); }})""")[1:]
    gaps.sort()
    return {"median_ms": round(statistics.median(gaps), 1), "p95_ms": round(gaps[int(len(gaps) * 0.95)], 1), "over_50ms": sum(1 for g in gaps if g > 50)}


def long_since(f, since):
    tasks = [d for start, d in (f.js("window.__long") or []) if start >= since]
    return {"long_tasks": len(tasks), "long_ms": sum(tasks), "longest_ms": max(tasks, default=0)}


def tap(name):
    """JS that clicks the visible button named `name` (its words or aria-label, exactly, else containing it)."""
    return f"""(() => {{ const want = {json.dumps(name)}.toLowerCase(); const vis = (e) => !!(e.offsetWidth || e.offsetHeight || e.getClientRects().length);
        const names = (e) => [e.innerText, e.getAttribute('aria-label')].filter(Boolean).map((x) => x.trim().toLowerCase());
        const all = [...document.querySelectorAll('button')].filter((e) => vis(e) && !e.disabled);
        (all.find((e) => names(e).some((n) => n === want)) ?? all.find((e) => names(e).some((n) => n.includes(want)))).click(); }})()"""


def timed(f, start_js, ready, what, timeout=60):
    """Runs `start_js`, then waits for `ready`: the milliseconds between, on the page's own clock."""
    f.run(f"window.__t0 = {NOW}; {start_js}")
    f.wait(ready, timeout=timeout, what=what)
    return round(f.js(f"{NOW} - window.__t0"))


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
    report = {"size": f.size, "cpu": CPU}
    # A first visit at full speed: the files cached, the mic checked, a song with three parts sung.
    f.go("/voice?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    f.mic_checked()
    f.run("sessionStorage.setItem('stackapella.headphones', '1')")
    song = new_song(f, "amazing-grace")
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 3")
    guides_ready(f)
    sung_takes(f, ["soprano", "tenor", "bass"])
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.querySelector('[data-way]')", what="past the chooser")
    f.go("/voice", "document.querySelector('#app header, #app main')")

    # Slowed: Sing opened from Your voice (the router, as a tap would).
    cpu(f, CPU)
    report["sing_open_ms"] = timed(f, f"(await import('/js/router.js')).navigate('/p/{song}/sing')",
                                   f"location.pathname.endsWith('/sing') && {SESSION}.session.parts.length > 3 && !{SESSION}.session.preparing && document.querySelector('#app header')", "Sing ready")
    # The guides ready (rendered as Sing opens): what a quick Play would wait for.
    v0 = f.js(f"{SESSION}.session.guidesVersion")
    report["guides_ready_ms"] = round(f.js(f"""(async () => {{ const s = {SESSION}.session; const t0 = window.__t0;
        return await new Promise((done) => {{ const check = () => (s.guidesVersion > {v0} && !s.preparing ? done(performance.now() - t0) : setTimeout(check, 20)); check(); }}); }})()"""))
    # The way and part, as a returning singer has them (set again: the first visit's may not have been saved yet).
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.querySelector('[data-way], [data-game-select]')", what="past the chooser")
    f.js(f"{AUDIO}.unlock()")
    report["play_ms"] = timed(f, f"{tap(t(f, 'studio.play'))}",
                              f"!!{AUDIO}.engine.playing", "playing")
    f.run(f"await {SESSION}.stopPlayback()")
    report["record_ms"] = timed(f, f"{AUDIO}.engine.setLoopback(true); {tap(t(f, 'sing.recordPart', {'name': t(f, 'parts.alto')}))}",
                                f"!!{AUDIO}.engine.playing?.recording || !!document.querySelector('[role=dialog]')", "recording (or its setup)")
    if f.js("!!document.querySelector('[role=dialog]')"):
        report["record_note"] = "a setup dialog came first"
    f.run(f"if ({AUDIO}.engine.playing?.recording) await {SESSION}.dropRecording(); {AUDIO}.engine.setLoopback(false); await {SESSION}.stopPlayback();")
    f.shot("sing")

    # The studio: opened, then all four parts playing.
    report["studio_open_ms"] = timed(f, f"(await import('/js/router.js')).navigate('/p/{song}')",
                                     f"location.pathname === '/p/{song}' && document.querySelectorAll('canvas').length > 3 && !{SESSION}.session.preparing", "the studio ready")
    f.click_t("studio.play")
    f.wait(f"{SESSION}.session.playing", what="the studio playing")
    since = f.js(f"Math.round({NOW})")
    report["studio_frames"] = {**frames(f), **long_since(f, since)}
    f.run(f"await {SESSION}.stopPlayback()")
    f.shot("studio")
    cpu(f, 1)
    print("    " + json.dumps(report))

    for key, budget in BUDGET.items():
        value = report["studio_frames"]["p95_ms"] if key == "studio_p95_ms" else report.get(key)
        assert value is None or value < budget, f"{key}: {value} (budget {budget})"


if __name__ == "__main__":
    run("slow_studio", journey, sizes=("phone",))
