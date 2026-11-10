"""The public site's speed on a slow phone (the coordinator's ask, 2026-11-02): each page cold, on a CPU slowed
4x and Chrome's Slow 4G, at 390 wide. Prints what it measured: first and largest contentful paint (and the
largest element), layout shift, long tasks (blocking time after first paint), the bytes on the wire (gzipped,
as production's edge sends them; the local server doesn't compress, so they're worked out here), what blocks
the first paint, the fonts, and the images that load eagerly below the fold.

    python3 -I tests/flows/site_speed_integration.py http://127.0.0.1:8371 [/path ...]
"""
import gzip
import json
import sys
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402

PAGES = ["/", "/courses", "/courses/barbershop", "/courses/improvising-harmony", "/features/learn-a-part", "/guides/how-to-sing-harmony"]
LINE = {"offline": False, "latency": 150, "downloadThroughput": 1.6 * 1024 * 1024 / 8, "uploadThroughput": 750 * 1024 / 8}
OBSERVE = """window.__perf = { lcp: null, lcpEl: null, cls: 0, long: [] };
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) { window.__perf.lcp = e.startTime; const el = e.element; window.__perf.lcpEl = el ? (el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.split(' ').slice(0, 3).join('.') : '') + (el.currentSrc ? ' ' + el.currentSrc.split('/').pop() : '')) : null; } }).observe({ type: 'largest-contentful-paint', buffered: true }); } catch (e) {}
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__perf.cls += e.value; }).observe({ type: 'layout-shift', buffered: true }); } catch (e) {}
try { new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__perf.long.push([e.startTime, e.duration]); }).observe({ type: 'longtask', buffered: true }); } catch (e) {}"""
REPORT = """(() => {
    const p = window.__perf; const fcp = performance.getEntriesByType('paint').find((e) => e.name === 'first-contentful-paint')?.startTime ?? null;
    const res = performance.getEntriesByType('resource');
    const blocking = res.filter((r) => r.renderBlockingStatus === 'blocking').map((r) => r.name.replace(location.origin, ''));
    const fonts = res.filter((r) => r.initiatorType === 'css' && /\\.(woff2?|ttf|otf)(\\?|$)|fonts\\.gstatic/.test(r.name)).map((r) => [r.name.split('/').pop().slice(0, 40), Math.round(r.responseEnd)]);
    const below = [...document.images].filter((i) => i.getBoundingClientRect().top > innerHeight * 1.2 && i.loading !== 'lazy').map((i) => (i.currentSrc || i.src).replace(location.origin, '').slice(0, 60));
    const tbt = p.long.filter(([s]) => fcp === null || s > fcp).reduce((sum, [, d]) => sum + Math.max(0, d - 50), 0);
    return { fcp: fcp && Math.round(fcp), lcp: p.lcp && Math.round(p.lcp), lcpEl: p.lcpEl, cls: Math.round(p.cls * 1000) / 1000, tbt: Math.round(tbt), longest: Math.round(Math.max(0, ...p.long.map(([, d]) => d))),
        resources: res.map((r) => r.name), blocking, fonts, eagerBelow: below, images: document.images.length };
})()"""


def gz_size(url, cache={}):
    """The resource's bytes as production sends them: gzip for text, as is for the rest."""
    if url in cache:
        return cache[url]
    try:
        with urllib.request.urlopen(url, timeout=20) as r:
            body = r.read()
            kind = r.headers.get("Content-Type", "")
    except Exception:  # noqa: BLE001 (a resource that won't load again counts as nothing)
        cache[url] = 0
        return 0
    text = any(k in kind for k in ("text", "javascript", "json", "svg", "xml"))
    cache[url] = len(gzip.compress(body, 6)) if text else len(body)
    return cache[url]


def measure(base, path):
    c = Chrome(None, 390, 844, True)
    try:
        c.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
        c.send("Network.enable")
        c.send("Network.setCacheDisabled", {"cacheDisabled": True})
        c.send("Network.emulateNetworkConditions", LINE)
        c.send("Emulation.setCPUThrottlingRate", {"rate": 4})
        c.goto(base + path, "document.readyState === 'complete'", timeout=90)
        time.sleep(2)  # late LCP candidates and shifts
        c.evaluate("window.scrollTo(0, document.body.scrollHeight)", await_promise=False)
        time.sleep(2)
        out = c.evaluate(REPORT, await_promise=False)
    finally:
        c.close()
    html = gz_size(base + path)
    sizes = {u: gz_size(u) for u in out.pop("resources")}
    out["kb"] = round((html + sum(sizes.values())) / 1024)
    out["biggest"] = [(u.split("/")[-1][:40], round(s / 1024)) for u, s in sorted(sizes.items(), key=lambda kv: -kv[1])[:5]]
    return out


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
    pages = sys.argv[2:] or PAGES
    for path in pages:
        try:
            print(path, json.dumps(measure(base, path)))
        except Exception as error:  # noqa: BLE001
            print(path, "FAILED", error)


if __name__ == "__main__":
    main()
