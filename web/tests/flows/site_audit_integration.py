"""The public site's audit (docs/public-site-plan.md, "Tests"): every page the sitemap lists, at 360,
768 and 1280 wide, in the light and the dark theme. Each page has its language, one main landmark,
headings that never skip a level, a name for every link, button and field, alt on every picture, no
duplicate ids, no positive tabindex, no em dash, no endless animation when motion is reduced, and
no sideways scroll; every visible word passes AA (4.5:1, or 3:1 for large words); every link to a
page of ours answers; and every page stays inside the weight budget (WEIGHT_BUDGET, the first load
before any scroll, as served here uncompressed). Needs the server's pages:
`API=http://127.0.0.1:8000 tool/flows.sh site_audit_integration`, or
`python3 -I tests/flows/site_audit_integration.py http://127.0.0.1:8000`. AUDIT_PAGES=/a,/b
narrows it.
"""
import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
sys.path.insert(0, str(Path(__file__).resolve().parent))
from cdp import Chrome  # noqa: E402
from site_integration import CONTRAST  # noqa: E402

WIDTHS = [360, 768, 1280]
THEMES = ["stage-light", "stage-dark"]
# The first load of a page, before any scroll, in bytes as served here (no compression): the HTML,
# its styles and scripts, the fonts and the pictures above the fold.
WEIGHT_BUDGET = 900 * 1024

AUDIT = r"""(() => {
  const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length) && getComputedStyle(el).visibility !== 'hidden';
  const named = (el) => {
    const label = el.getAttribute('aria-label') || el.getAttribute('title');
    if (label && label.trim()) return true;
    const by = el.getAttribute('aria-labelledby');
    if (by && by.split(/\s+/).some((id) => document.getElementById(id)?.textContent.trim())) return true;
    if (el.textContent.trim()) return true;
    return [...el.querySelectorAll('img[alt]')].some((img) => img.alt.trim());
  };
  const say = (el) => `${el.tagName.toLowerCase()}${el.id ? '#' + el.id : ''}${el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''}`;
  const bad = [];
  if (!document.documentElement.lang) bad.push('no lang on <html>');
  if (document.querySelectorAll('main').length !== 1) bad.push(`${document.querySelectorAll('main').length} <main>`);
  let last = 0;
  for (const h of document.querySelectorAll('h1, h2, h3, h4, h5, h6')) {
    if (!visible(h) && !h.closest('details')) continue;
    const level = +h.tagName[1];
    if (last && level > last + 1) bad.push(`heading skips from h${last} to h${level}: "${h.textContent.trim().slice(0, 40)}"`);
    last = level;
  }
  for (const el of document.querySelectorAll('a[href], button, summary, [role=button]')) if (visible(el) && !named(el)) bad.push(`no name: ${say(el)}`);
  for (const el of document.querySelectorAll('input:not([type=hidden]), select, textarea')) {
    if (el.closest('[aria-hidden="true"]')) continue;
    const labelled = el.labels?.length || el.getAttribute('aria-label') || el.getAttribute('aria-labelledby') || el.title;
    if (!labelled) bad.push(`no label: ${say(el)}`);
  }
  for (const img of document.querySelectorAll('img')) if (!img.hasAttribute('alt')) bad.push(`no alt: ${img.getAttribute('src')}`);
  const ids = {};
  for (const el of document.querySelectorAll('[id]')) ids[el.id] = (ids[el.id] || 0) + 1;
  for (const [id, n] of Object.entries(ids)) if (n > 1) bad.push(`id "${id}" ${n} times`);
  for (const el of document.querySelectorAll('[tabindex]')) if (+el.getAttribute('tabindex') > 0) bad.push(`tabindex ${el.getAttribute('tabindex')}: ${say(el)}`);
  const words = [document.title, document.querySelector('meta[name=description]')?.content ?? '', document.body.innerText].join(' ');
  if (words.includes('—')) bad.push(`an em dash: "${words.slice(Math.max(0, words.indexOf('—') - 30), words.indexOf('—') + 10)}"`);
  if (document.documentElement.scrollWidth > innerWidth) bad.push(`scrolls sideways by ${document.documentElement.scrollWidth - innerWidth}px`);
  return bad;
})()"""

# Animations still running endlessly while motion is reduced.
ENDLESS = "document.getAnimations().filter((a) => a.playState === 'running' && a.effect?.getTiming?.().iterations === Infinity).map((a) => a.animationName || a.constructor.name).slice(0, 3)"

# The first load's weight: the page and every file it fetched before any scroll.
WEIGHT = "(() => { const n = performance.getEntriesByType('navigation')[0]; return [n, ...performance.getEntriesByType('resource')].reduce((sum, e) => sum + (e.transferSize || e.encodedBodySize || 0), 0); })()"

LINKS = "[...new Set([...document.querySelectorAll('a[href]')].map((a) => a.href).filter((h) => h.startsWith(location.origin)).map((h) => h.split('#')[0]))]"


# The public pages allow 120 requests a minute from one address (config/limits.php): the audit keeps
# under it, so it never reads a 429 as the page.
_last = [0.0]


def paced():
    wait = _last[0] + 0.6 - time.monotonic()
    if wait > 0:
        time.sleep(wait)
    _last[0] = time.monotonic()


def status(url):
    paced()
    try:
        with urllib.request.urlopen(urllib.request.Request(url, method="GET"), timeout=20) as response:
            return response.status
    except urllib.error.HTTPError as error:
        return error.code


def pages(base):
    if os.environ.get("AUDIT_PAGES"):
        return os.environ["AUDIT_PAGES"].split(",")
    with urllib.request.urlopen(base + "/sitemap.xml", timeout=20) as response:
        locs = re.findall(r"<loc>([^<]+)</loc>", response.read().decode())
    return sorted({re.sub(r"^https?://[^/]+", "", loc) or "/" for loc in locs})


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8081").rstrip("/")
    failures = []
    paths = pages(base)
    for path in paths:
        if status(base + path) != 200:
            failures.append(f"{path}: in the sitemap but answers {status(base + path)}")
    links = set()
    heaviest = []
    for width in WIDTHS:
        for theme in THEMES:
            chrome = Chrome(None, width, 900, width < 900)
            try:
                chrome.goto(base + "/", "document.readyState === 'complete'")
                family, mode = theme.split("-")
                chrome.evaluate(f"localStorage.setItem('stackapella.appearance', {json.dumps(json.dumps({'mode': mode, 'colour': family, 'at': 1}))})")
                chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
                for path in paths:
                    chrome.send("Network.clearBrowserCookies")
                    paced()
                    chrome.goto(base + path, "document.readyState === 'complete'")
                    where = f"{path} {theme} {width}px"
                    failures += [f"{where}: {problem}" for problem in chrome.evaluate(AUDIT)]
                    failures += [f"{where}: below AA: {low}" for low in chrome.evaluate(CONTRAST)]
                    failures += [f"{where}: runs endlessly with motion reduced: {name}" for name in chrome.evaluate(ENDLESS)]
                    if theme == THEMES[0]:
                        weight = chrome.evaluate(WEIGHT)
                        heaviest.append((weight, f"{path} {width}px"))
                        if weight > WEIGHT_BUDGET:
                            failures.append(f"{where}: weighs {weight // 1024} KB, over {WEIGHT_BUDGET // 1024} KB")
                        if width == WIDTHS[-1]:
                            links.update(chrome.evaluate(LINKS))
                errors = [e for e in chrome.errors if "favicon" not in e]
                if errors:
                    failures.append(f"{theme} {width}px: page errors {errors[:3]}")
            finally:
                chrome.close()
    for link in sorted(links):
        code = status(link)
        if code >= 400:
            failures.append(f"a link to {link.replace(base, '')} answers {code}")
    heaviest.sort(reverse=True)
    print("     heaviest: " + ", ".join(f"{name} {weight // 1024} KB" for weight, name in heaviest[:5]))
    print(f"{'ok  ' if not failures else 'FAIL'} site_audit_integration ({len(paths)} pages)")
    for failure in failures[:200]:
        print("     " + failure)
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
