"""css/site.css scanned only from what the public pages load (docs/css-split-plan.md, step 2; tool/app_css.py public_js)
looks exactly as the build scanned from all of web/: on every public page site_integration visits, a song's part pages, the
reference pages and the tool embeds, in both skins, at a phone's and a desktop's width, each element's computed style and
box with the served site.css is compared with the same page given the full build instead. Not one element may differ.
Needs the server's pages and the full build somewhere the server serves it:

    python3 -I tests/flows/site_css_exact.py http://127.0.0.1:8000 /css/.site-full.css
"""
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from course_css_exact import PROPS, SNAP  # noqa: E402
from flow import Flow  # noqa: E402
from site_integration import PAGES  # noqa: E402

EXTRA = ["/songs/amazing-grace/soprano", "/songs/amazing-grace/bass", "/intervals", "/intervals/perfect-fifth", "/chords/major", "/voice-types/alto",
         "/courses/vibrato/smaller-waves", "/tools/drone/embed", "/tools/pitch-pipe/embed", "/for/organizations"]


def swap(full):
    # In site.css's own place, so it cascades exactly where the served one did.
    return f"""const old = [...document.querySelectorAll('link[rel=stylesheet]')].find((l) => (l.getAttribute('href') ?? '').split('?')[0] === '/css/site.css');
    await new Promise((done) => {{ const l = Object.assign(document.createElement('link'), {{ rel: 'stylesheet', href: {full!r} }}); l.onload = done; l.onerror = done; old.after(l); }});
    old.remove();"""


def main():
    site, full = sys.argv[1].rstrip("/"), sys.argv[2]
    failed, checked, skipped = [], 0, set()
    for skin, (colour, mode) in (("serious", ("warm", "light")), ("game", ("stage", "dark"))):
        for size, width in (("phone", 390), ("desktop", 1440)):
            f = Flow(site, size, "site-css", skin)
            try:
                f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
                for path in dict.fromkeys(PAGES + EXTRA):
                    f.go(path, "document.querySelector('body')")
                    time.sleep(1.5)
                    f.run(f"window.stackapellaTheme?.choose({{ colour: '{colour}', mode: '{mode}', skin: '{skin}' }})")
                    time.sleep(1)
                    if not f.js("!!document.querySelector('main') && [...document.querySelectorAll('link[rel=stylesheet]')].some((l) => (l.getAttribute('href') ?? '').split('?')[0] === '/css/site.css')"):
                        skipped.add(path)  # a page without site.css (an embed has its own): nothing to compare
                        continue
                    # Both snapshots after one swap each (the served sheet for itself, then the full build): a page that
                    # sizes itself from its layout (the landing's cabinet canvas) re-measures on any swap, CSS or not.
                    f.run(swap("/css/site.css?again=1"))
                    time.sleep(0.4)
                    own = f.js(SNAP)
                    f.run(swap(full))
                    time.sleep(0.4)
                    whole = f.js(SNAP)
                    checked += 1
                    for (tag, a), (_, b) in zip(own, whole):
                        if a != b:
                            changed = [f"{([*PROPS, 'box'])[i]} {x} (full: {y})" for i, (x, y) in enumerate(zip(a.split("|"), b.split("|"))) if x != y]
                            failed.append(f"{skin} {width} {path} {tag[:60]}: {', '.join(changed[:3])}")
            finally:
                f.chrome.close()
    if failed:
        print("site css: the served site.css styles these differently from the full build:")
        print("\n".join(failed[:40]))
        raise SystemExit(1)
    print(f"site css: {checked} page views, both skins, 390 and 1440: every element as with the full build (no site.css, skipped: {sorted(skipped)})")


if __name__ == "__main__":
    main()
