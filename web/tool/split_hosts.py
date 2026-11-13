#!/usr/bin/env python3
"""The site and the app on two hosts, as in production (stackapella.com and app.stackapella.com): local flows run
both on one host, which hid two breaks on 2026-11-13 (story pictures answered the site's "moving" page, and the
app prefetch's cross-host fetches were blocked by CORS, logging an error each). Against a real server started
with the two hosts (server/tool/split-hosts.sh does it), the landing and a long song page on the site's host,
scrolled to their ends, log no console errors and show every picture; and the app's prefetched files answer
the site's origin.

    python3 -I web/tool/split_hosts.py --site http://localhost:8476 --app http://127.0.0.1:8476
"""
import argparse
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from cdp import Chrome  # noqa: E402

PAGES = ["/", "/songs/amazing-grace"]
results = []


def say(ok, what, detail=""):
    results.append(ok)
    print(f"{'PASS' if ok else 'FAIL'}  {what}{('  (' + detail + ')') if detail else ''}", flush=True)


def visit(chrome, site, app, path):
    chrome.errors.clear()
    url = site + path
    try:
        chrome.goto(url, wait="document.querySelector('h1') && document.readyState === 'complete'", timeout=60)
    except Exception as error:  # a page that doesn't load is a FAIL, said
        say(False, f"{url} loads", str(error)[:200])
        return
    time.sleep(1.5)
    chrome.evaluate("window.scrollTo(0, document.body.scrollHeight)")
    time.sleep(3)  # every lazy picture and the prefetch's idle fetches
    # A guest's account check answers 401 by design; the browser logs any non-2xx response itself.
    errors = [e for e in chrome.errors if "favicon" not in e and not ("401" in e and "/api/account" in e)]
    say(not errors, f"{url} logs no console errors, scrolled to its end", "; ".join(errors[:3]))
    broken = chrome.evaluate("[...document.images].filter((i) => i.complete && i.currentSrc && i.naturalWidth === 0).map((i) => i.currentSrc)")
    say(not broken, f"{url} shows every picture", "; ".join(broken[:3]))
    prefetched = chrome.evaluate(f"performance.getEntriesByType('resource').filter((r) => r.name.startsWith({app!r} + '/') && r.initiatorType === 'link').length")
    print(f"      {prefetched} of the app's files prefetched from {app}", flush=True)


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument("--site", required=True, help="the site's origin, e.g. http://localhost:8476")
    parser.add_argument("--app", required=True, help="the app's origin, e.g. http://127.0.0.1:8476")
    args = parser.parse_args()
    site, app = args.site.rstrip("/"), args.app.rstrip("/")

    chrome = Chrome()
    try:
        for path in PAGES:
            visit(chrome, site, app, path)
    finally:
        chrome.close()

    failed = results.count(False)
    print(f"\n{len(results) - failed} passed, {failed} failed", flush=True)
    sys.exit(1 if failed else 0)


if __name__ == "__main__":
    main()
