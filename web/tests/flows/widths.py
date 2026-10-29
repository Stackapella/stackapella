"""Nothing runs off the side (Hillel's report: the home's right column cut off): every main screen, at
widths from 320 to 1920, with a long name, Plus and a course under way, has no sideways scroll and no
element past the right edge (bands that scroll sideways on purpose aside).
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


def journey(f):
    f.go("/?api=mock&as=admin", "document.querySelector('#app header')")
    studio = f.js("(async () => { const { startFromTemplate } = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js'); return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id; })()")
    screens = ["/", "/settings", "/learn", "/plus", "/groups", "/voice", "/storage", "/create", f"/p/{studio}", f"/p/{studio}/sing"]
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
    f.run(f"(await import('/js/stores/projects.js')).deleteProject({json.dumps(studio)})")
    assert not problems, "runs off the side:\n" + "\n".join(problems)


if __name__ == "__main__":
    run("widths", journey, sizes=("desktop",))
