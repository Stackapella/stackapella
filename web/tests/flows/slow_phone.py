"""A slow phone (master plan 12): the first lesson and Sing on a CPU slowed 4x (a budget Android) and a
slow 4G line, from a cold start. Prints what it measured and fails only past generous budgets, so a
change that makes either much slower shows here first.

    PORT=8340 tool/flows.sh slow_phone          # or SLOW_CPU=6 for an older phone

The first lesson: until its first step can be used (the way on), the bytes and requests it took, and
the main thread's long tasks (over 50 ms) on the way. Sing: the frames while the whole song plays
(time between animation frames), and the long tasks meanwhile.
"""
import json
import os
import statistics
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, new_song, t  # noqa: E402

CPU = float(os.environ.get("SLOW_CPU", "4"))
# Chrome's "Slow 4G": 150 ms round trips, about 1.6 Mbit/s down and 750 kbit/s up.
LINE = {"offline": False, "latency": 150, "downloadThroughput": 1.6 * 1024 * 1024 / 8, "uploadThroughput": 750 * 1024 / 8}
# Budgets (a slowed headless Chrome on this Mac; a real budget phone is in the same range). Generous on
# purpose: this is a tripwire for a big regression, not a benchmark.
LESSON_READY_MS = 9000
SING_FRAME_P95_MS = 50
LONG_TASKS_LIMIT_MS = 1500

# Long tasks from the very start of each page (registered before any page script).
OBSERVE = """window.__long = []; try { new PerformanceObserver((list) => { for (const e of list.getEntries()) window.__long.push([Math.round(e.startTime), Math.round(e.duration)]); }).observe({ type: 'longtask', buffered: true }); } catch (e) {}"""


def slow(f):
    f.chrome.send("Network.enable")
    f.chrome.send("Network.emulateNetworkConditions", LINE)
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": CPU})


def fast(f):
    f.chrome.send("Network.emulateNetworkConditions", {"offline": False, "latency": 0, "downloadThroughput": -1, "uploadThroughput": -1})
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": 1})


def long_tasks(f, since=0):
    tasks = [d for start, d in (f.js("window.__long") or []) if start >= since]
    return len(tasks), sum(tasks), max(tasks, default=0)


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
    report = {"size": f.size, "cpu": CPU}

    # The first lesson, cold: a new browser (no cache, no service worker yet) on the slow line.
    slow(f)
    f.go("/learn/1-comfortable", "document.readyState !== 'loading'")  # no mock: it reads the contract, which production never fetches
    f.wait("document.querySelector('[data-go-on]:not(:disabled)')", timeout=60, what="the first lesson usable")
    ready = f.js("Math.round(performance.now())")
    resources = f.js("performance.getEntriesByType('resource').map((r) => [r.name, r.transferSize, r.decodedBodySize])")
    count, total, longest = long_tasks(f)
    report["lesson"] = {
        "ready_ms": ready,
        "requests": len(resources) + 1,
        "kb_decoded": round(sum(r[2] for r in resources) / 1024),
        "long_tasks": count, "long_ms": total, "longest_ms": longest,
        "biggest": [(r[0].split("/", 3)[-1][:60], round(r[2] / 1024)) for r in sorted(resources, key=lambda r: -r[2])[:6]],
    }
    f.shot("lesson-ready")

    # Sing, the whole song playing (the lane drawn every frame), on the slowed CPU.
    fast(f)
    f.js(f"{AUDIO}.unlock()")
    song = new_song(f, "amazing-grace")
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("!document.body.innerText.includes(" + json.dumps(t(f, "sing.chooser.yourPart")) + ")", what="past the chooser")
    f.wait("document.querySelector('canvas, [data-stage-view]')", what="the stage")
    slow(f)
    time.sleep(1)
    f.click_t("studio.play")
    f.wait(f"{SESSION}.session.playing", what="Sing playing")
    since = f.js("Math.round(performance.now())")
    frames = f.js("""new Promise((done) => { const gaps = []; let last = performance.now(); const end = last + 6000;
        const tick = (now) => { gaps.push(now - last); last = now; if (now < end) requestAnimationFrame(tick); else done(gaps); };
        requestAnimationFrame(tick); })""")
    count, total, longest = long_tasks(f, since)
    gaps = sorted(frames[1:])
    report["sing"] = {
        "frames": len(gaps),
        "median_ms": round(statistics.median(gaps), 1),
        "p95_ms": round(gaps[int(len(gaps) * 0.95)], 1),
        "over_50ms": sum(1 for g in gaps if g > 50),
        "long_tasks": count, "long_ms": total, "longest_ms": longest,
    }
    f.shot("sing-playing")
    f.run(f"await {SESSION}.stopPlayback()")
    fast(f)
    print("    " + json.dumps(report))

    assert report["lesson"]["ready_ms"] < LESSON_READY_MS, f"the first lesson took {ready} ms to be usable"
    assert report["lesson"]["long_ms"] < LONG_TASKS_LIMIT_MS, f"the first lesson blocked the page for {report['lesson']['long_ms']} ms"
    assert report["sing"]["p95_ms"] < SING_FRAME_P95_MS, f"Sing's frames: p95 {report['sing']['p95_ms']} ms"


if __name__ == "__main__":
    run("slow_phone", journey, sizes=("phone",))
