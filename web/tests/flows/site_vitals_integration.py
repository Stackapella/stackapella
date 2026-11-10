"""The public pages' Core Web Vitals (the coordinator's ask, 2026-11-02: outreach and search bring first-time
visitors there): each page cold, as a slow phone (CPU 4x slower, Slow 4G, 390 wide) and as a desktop (1440
wide, no throttling). Prints LCP and its element, CLS, the bytes before the first paint, what blocks it, the
total bytes, and INP's stand-in: the slowest interaction when the page's first Play, Start, Listen or Try is
pressed (the Event Timing entry, and the long tasks in the second after it).

Run it against a server that compresses as production's edge does (artisan serve doesn't), or the bytes and
the throttled timings come out too big:

    python3 -I tests/flows/site_vitals_integration.py http://127.0.0.1:8372 [phone|desktop] [/path ...]
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402

PAGES = ["/", "/for/music-teachers", "/for/choir-directors", "/courses/barbershop", "/features/course", "/pricing"]
SLOW_4G = {"offline": False, "latency": 150, "downloadThroughput": 1.6 * 1024 * 1024 / 8, "uploadThroughput": 750 * 1024 / 8}
OBSERVE = """window.__v = { lcp: null, lcpEl: null, cls: 0, long: [], events: [] };
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) { window.__v.lcp = e.startTime; const el = e.element; window.__v.lcpEl = el ? el.tagName.toLowerCase() + (typeof el.className === 'string' && el.className ? '.' + el.className.split(' ').slice(0, 2).join('.') : '') + (el.currentSrc ? ' ' + el.currentSrc.split('/').pop() : '') : null; } }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__v.cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {}
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__v.long.push([e.startTime, e.duration]); }).observe({ type: 'longtask', buffered: true }); } catch (e) {}
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__v.events.push([e.name, e.startTime, e.duration]); }).observe({ type: 'event', durationThreshold: 16, buffered: true }); } catch (e) {}"""
REPORT = """(() => {
    const v = window.__v; const fcp = performance.getEntriesByType('paint').find((e) => e.name === 'first-contentful-paint')?.startTime ?? null;
    const res = performance.getEntriesByType('resource');
    const nav = performance.getEntriesByType('navigation')[0];
    const before = res.filter((r) => fcp !== null && r.responseEnd <= fcp).reduce((s, r) => s + r.transferSize, nav.transferSize);
    return { fcp: fcp && Math.round(fcp), lcp: v.lcp && Math.round(v.lcp), lcpEl: v.lcpEl, cls: Math.round(v.cls * 1000) / 1000,
        kbBeforePaint: Math.round(before / 1024), kb: Math.round(res.reduce((s, r) => s + r.transferSize, nav.transferSize) / 1024), requests: res.length + 1,
        blocking: res.filter((r) => r.renderBlockingStatus === 'blocking').map((r) => r.name.replace(location.origin, '') + ' ' + Math.round(r.transferSize / 1024) + 'KB') };
})()"""
# The page's first control that starts something, as a first-time visitor would press it.
PRESS = """(() => {
    const words = /^(\\s*▶?\\s*)(play|start|listen|try|hear|sing)/i;
    const el = [...document.querySelectorAll('main button, main [role=button]')].find((b) => b.offsetParent && words.test((b.innerText || b.getAttribute('aria-label') || '').trim()));
    if (!el) return null;
    el.scrollIntoView({ block: 'center' });
    return (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 30);
})()"""


def press(c):
    label = c.evaluate(PRESS, await_promise=False)
    if not label:
        return None
    time.sleep(0.6)
    box = c.evaluate("""(() => { const words = /^(\\s*▶?\\s*)(play|start|listen|try|hear|sing)/i;
        const el = [...document.querySelectorAll('main button, main [role=button]')].find((b) => b.offsetParent && words.test((b.innerText || b.getAttribute('aria-label') || '').trim()));
        const r = el.getBoundingClientRect(); return JSON.stringify([r.left + r.width / 2, r.top + r.height / 2]); })()""", await_promise=False)
    x, y = json.loads(box)
    at = c.evaluate("performance.now()", await_promise=False)
    for kind in ("mousePressed", "mouseReleased"):  # a real pointer press, so Event Timing sees it
        c.send("Input.dispatchMouseEvent", {"type": kind, "x": x, "y": y, "button": "left", "clickCount": 1})
    time.sleep(1.5)
    events = c.evaluate(f"JSON.stringify(window.__v.events.filter(([n, s]) => s >= {at} - 5 && /pointer|mouse|click/.test(n)))", await_promise=False)
    long = c.evaluate(f"JSON.stringify(window.__v.long.filter(([s]) => s >= {at} - 5 && s < {at} + 1000))", await_promise=False)
    events, long = json.loads(events), json.loads(long)
    return {"pressed": label, "inp": round(max([d for _, _, d in events], default=0)), "longAfter": [round(d) for _, d in long]}


def measure(base, path, mode):
    phone = mode == "phone"
    c = Chrome(None, 390 if phone else 1440, 844 if phone else 900, phone)
    try:
        c.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
        c.send("Network.enable")
        c.send("Network.setCacheDisabled", {"cacheDisabled": True})
        if phone:
            c.send("Network.emulateNetworkConditions", SLOW_4G)
            c.send("Emulation.setCPUThrottlingRate", {"rate": 4})
        c.goto(base + path, "document.readyState === 'complete'", timeout=120)
        time.sleep(2.5)  # late LCP candidates, late fonts and their shifts
        out = c.evaluate(REPORT, await_promise=False)
        out["interaction"] = press(c)
        c.evaluate("window.scrollTo(0, document.body.scrollHeight)", await_promise=False)
        time.sleep(1.5)
        out["clsAfterScroll"] = round(c.evaluate("window.__v.cls", await_promise=False) * 1000) / 1000
    finally:
        c.close()
    return out


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8372").rstrip("/")
    modes = [sys.argv[2]] if len(sys.argv) > 2 and sys.argv[2] in ("phone", "desktop") else ["phone", "desktop"]
    pages = [a for a in sys.argv[2:] if a.startswith("/")] or PAGES
    for mode in modes:
        for path in pages:
            try:
                print(mode, path, json.dumps(measure(base, path, mode)), flush=True)
            except Exception as error:  # noqa: BLE001
                print(mode, path, "FAILED", error, flush=True)


if __name__ == "__main__":
    main()
