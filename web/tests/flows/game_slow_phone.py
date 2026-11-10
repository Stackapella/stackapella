"""Game mode on a slow phone (the coordinator, 2026-11-02; frontend's slow_phone setup: CPU 4x, Slow 4G, a cold
start for each page): Home, a lesson (its title card), Sing playing with the HUD's pop-ups and combo, a lesson end's
Streak! and stars burst, the boss round's intro, and a song's board. For each page: first contentful paint, when it's
usable, the main thread's long tasks (over 50 ms) and the fonts it fetched (and whether any landed after the first
paint). For each animation: the frames (rAF gaps), their p95 and how many were dropped (a gap over 25 ms). Prints one
JSON report per size; fails only past generous budgets (a tripwire, like slow_phone).

    PORT=8341 tool/flows.sh game_slow_phone        # SLOW_CPU=6 for an older phone
"""
import json
import os
import statistics
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, new_song, progress  # noqa: E402

CPU = float(os.environ.get("SLOW_CPU", "4"))
LINE = {"offline": False, "latency": 150, "downloadThroughput": 1.6 * 1024 * 1024 / 8, "uploadThroughput": 750 * 1024 / 8}
READY_MS = 12000  # a page usable on the slowed line and CPU (budgets: tripwires, not targets)
ANIMATION_P95_MS = 60
OBSERVE = """window.__long = []; window.__lcp = 0; try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__long.push([Math.round(e.startTime), Math.round(e.duration)]); }).observe({ type: 'longtask', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__lcp = Math.round(e.startTime); }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}"""
FRAMES = """new Promise((done) => { const gaps = []; let last = performance.now(); const end = last + %d;
    const tick = (now) => { gaps.push(now - last); last = now; if (now < end) requestAnimationFrame(tick); else done(gaps); }; requestAnimationFrame(tick); })"""


def slow(f):
    f.chrome.send("Network.enable")
    f.chrome.send("Network.setCacheDisabled", {"cacheDisabled": True})
    f.chrome.send("Network.setBypassServiceWorker", {"bypass": True})
    f.chrome.send("Network.emulateNetworkConditions", LINE)
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": CPU})


def fast(f):
    f.chrome.send("Network.emulateNetworkConditions", {"offline": False, "latency": 0, "downloadThroughput": -1, "uploadThroughput": -1})
    f.chrome.send("Network.setCacheDisabled", {"cacheDisabled": False})
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": 1})


def page(f, path, ready):
    """A page loaded cold on the slow phone: its first paint, LCP, when `ready` holds, long tasks and fonts."""
    slow(f)
    f.go(path, "document.readyState !== 'loading'")
    f.wait(ready, timeout=60, what=f"{path} usable")
    at = f.js("Math.round(performance.now())")
    time.sleep(0.5)
    fcp = f.js("Math.round(performance.getEntriesByName('first-contentful-paint')[0]?.startTime ?? 0)")
    fonts = f.js("performance.getEntriesByType('resource').filter((r) => r.name.includes('/fonts/')).map((r) => [r.name.split('/').pop(), Math.round(r.responseEnd), Math.round(r.decodedBodySize / 1024)])")
    tasks = [d for _, d in (f.js("window.__long") or [])]
    fast(f)
    return {"fcp_ms": fcp, "lcp_ms": f.js("window.__lcp"), "ready_ms": at, "long_tasks": len(tasks), "long_ms": sum(tasks), "longest_ms": max(tasks, default=0),
            "fonts": fonts, "fonts_after_fcp": [n for n, end, _ in fonts if end > fcp]}


def frames(f, ms, start=None):
    """The frames over `ms` on the slowed CPU, while `start` (JS) runs an animation."""
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": CPU})
    since = f.js("Math.round(performance.now())")
    if start:
        f.run(start)
    gaps = sorted(f.js(FRAMES % ms)[1:])
    tasks = [d for s, d in (f.js("window.__long") or []) if s >= since]
    f.chrome.send("Emulation.setCPUThrottlingRate", {"rate": 1})
    return {"frames": len(gaps), "median_ms": round(statistics.median(gaps), 1), "p95_ms": round(gaps[int(len(gaps) * 0.95)], 1),
            "dropped": sum(1 for g in gaps if g > 25), "long_tasks": len(tasks), "long_ms": sum(tasks)}


def journey(f):
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
    report = {"size": f.size, "cpu": CPU}
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); localStorage.removeItem('stackapella.titlesSeen')")
    progress(f, ["1-comfortable", "1-match", "1-high-low"], 150)

    report["home"] = page(f, "/?api=mock&scenario=&as=member", "document.querySelector('#lead-title') && document.querySelector('[data-lead-eyebrow]')")
    report["lesson"] = page(f, "/learn/1-breath?api=mock&scenario=&as=member", "document.querySelector('[data-game-title]') || document.querySelector('[data-go-on]')")
    f.run("localStorage.removeItem('stackapella.titlesSeen')")
    f.go("/learn/1-breath?api=mock&scenario=&as=member", "document.querySelector('#app main')")
    report["title_card"] = frames(f, 1400)
    report["boards"] = page(f, "/t/amazing-grace?api=mock&scenario=&as=member", "document.querySelector('[data-part-board] button')")

    # Sing playing with the HUD: the lane every frame, the combo and a pop-up every 600 ms.
    f.js(f"{AUDIO}.unlock()")
    clear_projects(f)
    song = new_song(f, "amazing-grace")
    f.go(f"/p/{song}/sing?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true, view: 'notes' }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("document.querySelector('[data-play]')", what="Sing")
    f.run("""const { createApp, h } = await import('vue'); const { t } = await import('/js/i18n.js'); const { PopupSlot } = await import('/js/game/hud.js'); const { popupQueue } = await import('/js/game/popups.js');
        window.hudQueue = popupQueue(); window.hudQueue.clear(0); const box = Object.assign(document.createElement('div'), { id: 'hud-test', className: 'fixed inset-x-0 top-24 z-30 flex justify-center' }); document.body.append(box);
        const app = createApp({ render: () => h(PopupSlot, { queue: window.hudQueue }) }); app.config.globalProperties.t = t; app.mount(box);""")
    f.run("document.querySelector('[data-play]').click()")
    f.wait(f"{SESSION}.session.playing", what="playing")
    report["sing_hud"] = frames(f, 5000, """let n = 10; window.__pops = setInterval(() => { n += 10; window.hudQueue.tick(n); window.hudQueue.offer({ kind: 'combo', count: n }, n); }, 600);""")
    f.run(f"clearInterval(window.__pops); await {SESSION}.stopPlayback()")

    # A lesson end's Streak! with its stars, and the full celebration over them.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#app main')")
    report["result_bursts"] = frames(f, 2500, """const { createApp, h } = await import('vue'); const { t } = await import('/js/i18n.js'); const { LessonEnd } = await import('/js/components/results.js');
        const { levelFor } = await import('/js/course.js'); const { levelWord, endLine } = await import('/js/results.js'); const { celebrate } = await import('/js/celebrate.js');
        const box = Object.assign(document.createElement('main'), { id: 'end-test', className: 'fixed inset-0 z-40 overflow-auto bg-ink p-6' }); document.body.append(box);
        const done = { stars: 3, xp: 30, level: levelFor(150), levelledUp: false, word: levelWord(3, 96), line: endLine({ stars: 3, before: 0, scored: true }), fresh: true, percent: 96, streak: 3, streakNew: true, place: 3, total: 5, unit: 2 };
        const app = createApp({ render: () => h(LessonEnd, { done, path: [], next: null }) }); app.config.globalProperties.t = t; app.mount(box);
        setTimeout(() => celebrate(3, document.querySelector('[data-lesson-end]')), 300);""")

    # The boss round's intro (its banner slides, flashes, the card and foot come in).
    import game_boss
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": game_boss.PATCH})
    game_boss.seed(f, 8)
    f.go("/learn/2-boss?api=mock&scenario=&as=member", "document.querySelector('#app main')")
    f.wait("document.querySelector('[data-boss-intro]')", timeout=20, what="the boss intro")
    f.go("/learn/2-boss?api=mock&scenario=&as=member", "document.querySelector('#app main')")
    report["boss_intro"] = frames(f, 1800, "void 0")

    print("    " + json.dumps(report))
    for name in ("home", "lesson", "boards"):
        assert report[name]["ready_ms"] < READY_MS, f"{name} took {report[name]['ready_ms']} ms to be usable"
    for name in ("title_card", "sing_hud", "result_bursts", "boss_intro"):
        assert report[name]["p95_ms"] < ANIMATION_P95_MS, f"{name}: frames p95 {report[name]['p95_ms']} ms"


if __name__ == "__main__":
    run("game_slow_phone", journey, sizes=("phone",))
