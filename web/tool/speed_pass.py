#!/usr/bin/env python3
"""A first visit's speed on a phone, read-only, against the real site (or any host): a fresh browser
profile, throttled as Lighthouse's mobile run is (150 ms round trips, 1.6 Mbps down, 750 kbps up, the
CPU 4 times slower), on a phone-sized screen.

    python3 web/tool/speed_pass.py                      # stackapella.com and app.stackapella.com
    python3 web/tool/speed_pass.py --public beta.stackapella.com --runs 3

It prints, for the landing: first contentful paint, largest contentful paint, the bytes and the
requests; then, from the landing's Start: the time until lesson 1 is shown, and until its note
finder is listening (the first note can be sung). Nothing is signed in, sent or saved.
"""
import argparse
import json
import statistics
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from cdp import Chrome  # noqa: E402

THROTTLE = {"offline": False, "latency": 150, "downloadThroughput": 1.6e6 / 8, "uploadThroughput": 750e3 / 8}

OBSERVE = """(() => { window.__lcp = 0; window.__fcp = 0;
    new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
    new PerformanceObserver((l) => { for (const e of l.getEntries()) if (e.name === 'first-contentful-paint') window.__fcp = e.startTime; }).observe({ type: 'paint', buffered: true });
    return true; })()"""

TOTALS = """(() => { const nav = performance.getEntriesByType('navigation')[0];
    const res = performance.getEntriesByType('resource');
    const bytes = (nav?.transferSize ?? 0) + res.reduce((s, r) => s + (r.transferSize || 0), 0);
    const biggest = res.map((r) => [r.name.replace(/^https?:\\/\\/[^/]+/, '').slice(0, 70), r.transferSize]).sort((a, b) => b[1] - a[1]).slice(0, 8);
    return JSON.stringify({ requests: res.length + 1, bytes, biggest }); })()"""


def fresh(width=390, height=844):
    chrome = Chrome(width=width, height=height, mobile=True)
    chrome.send("Network.enable")
    chrome.send("Network.setCacheDisabled", {"cacheDisabled": True})
    chrome.send("Network.emulateNetworkConditions", THROTTLE)
    chrome.send("Emulation.setCPUThrottlingRate", {"rate": 4})
    return chrome


def landing(public):
    chrome = fresh()
    try:
        chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": OBSERVE})
        started = time.time()
        chrome.goto(f"https://{public}/", wait="document.readyState === 'complete'", timeout=90)
        loaded = time.time() - started
        time.sleep(3)  # late images and the LCP settling
        fcp = chrome.evaluate("window.__fcp")
        lcp = chrome.evaluate("window.__lcp")
        totals = json.loads(chrome.evaluate(TOTALS))
        start = chrome.evaluate("[...document.querySelectorAll('a[href]')].map((a) => a.href).find((h) => /\\/learn\\//.test(h)) ?? null")
        return {"fcp": fcp, "lcp": lcp, "load": loaded * 1000, **totals, "start": start}
    finally:
        chrome.close()


def lesson(start):
    chrome = fresh()
    try:
        began = time.time()
        chrome.goto(start, wait="document.querySelector('main h1') && document.querySelector('[data-go-on]')", timeout=120)
        shown = (time.time() - began) * 1000
        totals = json.loads(chrome.evaluate(TOTALS))
        # The way on (Turn on my mic), then the note finder listening: the first note can be sung.
        tapped = time.time()
        # A tap with the browser's user activation (the mic and the audio need a person's gesture).
        chrome.send("Runtime.evaluate", {"expression": "document.querySelector('[data-go-on]').click()", "userGesture": True})
        deadline = time.time() + 90
        phase = None
        while time.time() < deadline:
            phase = chrome.evaluate("document.querySelector('[data-listening]')?.dataset.phase ?? null")
            if phase in ("listening", "hearing", "holding"):
                break
            if chrome.evaluate("!!document.querySelector('[data-mic-primer] button.btn-primary')"):
                chrome.evaluate("document.querySelector('[data-mic-primer] button.btn-primary').click()")
            time.sleep(0.1)
        listening = (time.time() - tapped) * 1000
        return {"shown": shown, "listening": listening if phase else None, **totals}
    finally:
        chrome.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--public", default="stackapella.com")
    parser.add_argument("--runs", type=int, default=3)
    parser.add_argument("--start", default=None, help="measure only lesson 1 from this address (a local server, say)")
    args = parser.parse_args()
    lands, lessons = [], []
    if args.start:
        for run in range(args.runs):
            path = lesson(args.start)
            lessons.append(path)
            print(f"lesson 1 run {run + 1}: shown {path['shown']:.0f} ms ({path['bytes'] / 1024:.0f} KB, {path['requests']} requests); tap → listening {path['listening'] or 0:.0f} ms", flush=True)
        print("The heaviest on lesson 1:", json.dumps(lessons[-1]["biggest"]))
        return
    for run in range(args.runs):
        result = landing(args.public)
        lands.append(result)
        print(f"landing run {run + 1}: FCP {result['fcp']:.0f} ms, LCP {result['lcp']:.0f} ms, load {result['load']:.0f} ms, {result['bytes'] / 1024:.0f} KB in {result['requests']} requests", flush=True)
        if result["start"]:
            path = lesson(result["start"])
            lessons.append(path)
            print(f"  Start → lesson 1 shown {path['shown']:.0f} ms ({path['bytes'] / 1024:.0f} KB, {path['requests']} requests); tap → listening {path['listening'] or 0:.0f} ms", flush=True)
    med = lambda key, rows: statistics.median(r[key] for r in rows if r.get(key) is not None)
    print("\nMedians (Slow 4G, 4x CPU, a fresh phone):")
    print(f"  landing: FCP {med('fcp', lands):.0f} ms, LCP {med('lcp', lands):.0f} ms, {med('bytes', lands) / 1024:.0f} KB, {med('requests', lands):.0f} requests")
    if lessons:
        print(f"  lesson 1: shown {med('shown', lessons):.0f} ms after Start ({med('bytes', lessons) / 1024:.0f} KB, {med('requests', lessons):.0f} requests), listening {med('listening', lessons):.0f} ms after the tap")
    print("\nThe heaviest on the landing:", json.dumps(lands[-1]["biggest"]))
    if lessons:
        print("The heaviest on lesson 1:", json.dumps(lessons[-1]["biggest"]))


if __name__ == "__main__":
    main()
