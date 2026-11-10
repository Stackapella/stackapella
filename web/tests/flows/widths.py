"""Nothing runs off the side (Hillel's report: the home's right column cut off): every main screen, at
widths from 320 to 1920, with a long name, Plus and a course under way, has no sideways scroll and no
element past the right edge (bands that scroll sideways on purpose aside).
Its page header (components/page.js) never overlaps: the title, the back button and the page's action stay apart, and the
title's words stay in its box ("Choir groups" ran into Start a group at 360 in game mode until the action dropped under it).
Every screen's bar is on the site's grid (Hillel, 2026-11-10: "can we make the app and admin header and page width match the
marketing site"; docs/design-rules.md, "One grid"): Back sits where the site's logo does, 16 px in (40 from 900 px) from
the 1200 px row's edge, centred in the 60 px bar (80 from 900 px). Home's logo is logo_match's. The studio, Sing and a
lesson have bars as wide as the window: their Back may sit further out, toward the window's edge, never further in than
the site's logo (Hillel, 2026-11-10: "The logo can be further to the sides but shouldn't be further in").
"""
import json
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

WIDTHS = [320, 360, 390, 768, 1024, 1100, 1233, 1280, 1440, 1920]
OVER = """JSON.stringify((() => {
    const edge = document.documentElement.clientWidth;
    const wide = [...document.querySelectorAll('body *')].filter((e) => {
        const box = e.getBoundingClientRect();
        return box.width && box.right > edge + 1 && !e.closest('[class*=overflow-x-auto], [class*=overflow-hidden]') && getComputedStyle(e).position !== 'fixed';
    });
    return { scroll: document.documentElement.scrollWidth - edge, wide: wide.slice(0, 4).map((e) => e.tagName + '.' + String(e.className).slice(0, 60)) };
})())"""
LONG = "const { account } = await import('/js/stores/account.js'); if (account.me) account.me = { ...account.me, name: 'Hillel Coren-Montgomery', plan: 'plus' }; const { learning } = await import('/js/stores/learning.js'); learning.progress.startedAt = Date.now(); learning.progress.xp = { ...learning.progress.xp, total: 40123456 };"


HEADER = """(() => {
  const h = document.querySelector('[data-ui=page-header]');
  if (!h) return [];
  const bad = [];
  const kids = [...h.children].filter((k) => k.getClientRects().length);
  const rects = kids.map((k) => k.getBoundingClientRect());
  for (let i = 0; i < rects.length; i++) for (let j = i + 1; j < rects.length; j++) {
    const a = rects[i], b = rects[j];
    if (a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1) bad.push(kids[i].tagName + ' overlaps ' + kids[j].tagName);
  }
  const title = h.querySelector('h1');
  if (title && title.scrollWidth > title.clientWidth + 1) bad.push('the title runs past its box (' + title.scrollWidth + ' > ' + title.clientWidth + ')');
  return bad;
})()"""


# Where the bar's first control sits against the site's grid (css/input.css app-wrap, app-bar): its left edge on the
# row's inset, and, when the bar is one row, centred in the bar's height. `outward`: a workspace's, which may sit further
# out than the inset, never further in.
GRID = """((outward) => {
  const vw = document.getElementById('app').getBoundingClientRect().width; // the width laid out (a scrollbar's gutter aside)
  const wide = window.innerWidth >= 900; // the media query's width
  const bar = document.querySelector('#app header');
  const first = bar && [...bar.querySelectorAll('a, button')].find((e) => e.getClientRects().length);
  if (!first) return 'no bar or no control in it';
  const inset = Math.max(0, (vw - 1200) / 2) + (wide ? 40 : 16);
  const height = wide ? 80 : 60;
  const b = first.getBoundingClientRect(), h = bar.getBoundingClientRect();
  const bad = [];
  if (outward) return b.left > inset + 1 ? `its first control at ${Math.round(b.left)} px, further in than the site's logo at ${inset}` : '';
  if (Math.abs(b.left - inset) > 1) bad.push(`its first control at ${Math.round(b.left)} px, the grid's ${inset}`);
  if (Math.round(h.height) <= height + 1 && Math.abs(b.top - h.top - (height - b.height) / 2) > 1) bad.push(`its first control ${Math.round(b.top - h.top)} px down a ${Math.round(h.height)} px bar`);
  return bad.join('; ');
})"""
WORKSPACES = ("/p/", "/learn/")  # the studio, Sing and a lesson: their bars the window's width, Back never further in than the site's logo


def journey(f):
    f.go("/?api=mock&as=admin", "document.querySelector('#app header')")
    studio = f.js("(async () => { const { startFromTemplate } = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js'); return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id; })()")
    screens = ["/", "/settings", "/learn", "/learn/1-match", "/plus", "/groups", "/voice", "/storage", "/create", f"/p/{studio}", f"/p/{studio}/sing"]
    problems = []
    for path in screens:
        for width in WIDTHS:
            f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": width, "height": 900, "deviceScaleFactor": 1, "mobile": width < 768})
            f.go(path, "document.querySelector('#app header, #app h1, #app main')")
            f.run(LONG)
            time.sleep(0.6)
            found = json.loads(f.js(OVER))
            if found["scroll"] > 0 or found["wide"]:
                problems.append(f"{path} at {width}px: {found}")
            for clash in f.js(HEADER):
                problems.append(f"{path} at {width}px, its header: {clash}")
            if path != "/":
                off = f.js(f"{GRID}({'true' if path.startswith(WORKSPACES) else 'false'})")
                if off:
                    problems.append(f"{path} at {width}px, off the site's grid: {off}")
    f.run(f"(await import('/js/stores/projects.js')).deleteProject({json.dumps(studio)})")
    assert not problems, "runs off the side:\n" + "\n".join(problems)


if __name__ == "__main__":
    run("widths", journey, sizes=("desktop",))
