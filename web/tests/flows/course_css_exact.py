"""A course page's own stylesheet looks exactly as courses.css whole (docs/css-split-plan.md, step 1; App\\Support\\Site\\CourseCss):
on every page that shows a course, in both skins, at a phone's and a desktop's width, each element's computed style with
the page's /course-css/ sheet is compared with the same page given the whole css/courses.css instead, and so is its box
(where it sits and its size; horizontal margins aren't compared, as Chrome reads an auto margin as 0 until a fresh layout).
Not one element may differ. Needs the server's pages:

    python3 -I tests/flows/course_css_exact.py http://127.0.0.1:8000
"""
import re
import sys
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import Flow  # noqa: E402

PROPS = ["display", "position", "margin-top", "margin-bottom", "padding-top", "padding-bottom", "padding-left", "padding-right",
         "font-family", "font-size", "font-weight", "line-height", "letter-spacing", "text-transform", "gap", "grid-template-columns", "flex-direction",
         "justify-content", "align-items", "max-width", "color", "background-color", "background-image", "border-top-width", "border-top-color",
         "border-radius", "box-shadow", "opacity", "visibility", "transform"]
# Animations cancelled first (a running one is at a different moment each time, and the swap restarts them), and every
# section laid out (content-visibility: auto skips an offscreen one, whose auto margins then read 0).
SNAP = """(() => { for (const a of document.getAnimations()) a.cancel();
    if (!document.getElementById('cv-all')) document.head.append(Object.assign(document.createElement('style'), { id: 'cv-all', textContent: '*{content-visibility:visible!important}' }));
    document.body.getBoundingClientRect();
    return [...document.querySelectorAll('main *')].map((e) => { const c = getComputedStyle(e);
    const r = e.getBoundingClientRect(); const box = [r.left, r.top + scrollY, r.width, r.height].map((n) => Math.round(n)).join(',');
    return [e.tagName + '.' + [...e.classList].join('.'), PROPS.map((p) => c.getPropertyValue(p)).join('|') + '|' + box]; }); })()""".replace("PROPS", repr(PROPS))
WHOLE = """for (const l of [...document.querySelectorAll('link[rel=stylesheet]')]) if ((l.getAttribute('href') ?? '').startsWith('/course-css/')) l.remove();
    await new Promise((done) => { const l = Object.assign(document.createElement('link'), { rel: 'stylesheet', href: '/css/courses.css' }); l.onload = done; l.onerror = done; document.head.append(l); });"""


def pages(site):
    """Every page the site's course index, guides and features link that shows a course."""
    found = set()
    for index in ("/courses", "/guides", "/features"):
        try:
            html = urllib.request.urlopen(site + index, timeout=30).read().decode()
        except OSError:
            continue
        found |= set(re.findall(r'href="(/(?:courses|guides|features)/[a-z0-9-]+)"', html))
    keep = []
    for path in sorted(found):
        html = urllib.request.urlopen(site + path, timeout=30).read().decode()
        if "/course-css/" in html:
            keep.append(path)
    return keep


def main():
    site = sys.argv[1].rstrip("/")
    paths = pages(site)
    if len(paths) < 10:
        raise SystemExit(f"course css: only {len(paths)} pages show a course; is this the server's site?")
    failed = []
    for skin, (colour, mode) in (("serious", ("warm", "light")), ("game", ("stage", "dark"))):
        for size, width in (("phone", 390), ("desktop", 1440)):
            f = Flow(site, size, "course-css", skin)
            try:
                f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
                for path in paths:
                    f.go(path, "document.querySelector('main')")
                    time.sleep(2)  # the demos mount once their scripts and sounds arrive (a screen hidden till then reads margin 0)
                    f.run(f"window.stackapellaTheme.choose({{ colour: '{colour}', mode: '{mode}', skin: '{skin}' }})")
                    time.sleep(1)  # past the skin switch's own transition (about 650 ms), which narrows the page as it plays
                    own = f.js(SNAP)
                    f.run(WHOLE)
                    time.sleep(0.3)
                    whole = f.js(SNAP)
                    for (tag, a), (_, b) in zip(own, whole):
                        if a != b:
                            changed = [f"{([*PROPS, 'box'])[i]} {x} (whole: {y})" for i, (x, y) in enumerate(zip(a.split("|"), b.split("|"))) if x != y]
                            failed.append(f"{skin} {width} {path} {tag[:60]}: {', '.join(changed[:3])}")
            finally:
                f.chrome.close()
    if failed:
        print("course css: a page's own sheet styles these differently from courses.css whole:")
        print("\n".join(failed[:40]))
        raise SystemExit(1)
    print(f"course css: {len(paths)} pages, both skins, 390 and 1440: every element as with courses.css whole")


if __name__ == "__main__":
    main()
