"""The room each tool page holds for its tool before the script mounts it (css/input.css, `.s-tool:not([data-state])`):
on the real site, every live tool's and warm-up's mounted height is checked against it, on a desktop (its --tool-room) and a phone
(the screen under the header and tool bar, or its own phone room), so the guide below never jumps when the tool arrives (CLS). A tool that grows or
shrinks by more than 24 px fails here with its new height to copy into input.css. It needs the server's own pages:

    python3 -I tests/flows/tool_room_integration.py http://127.0.0.1:8000
"""
import re
import sys
import time
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "tool"))
from cdp import Chrome  # noqa: E402



def pages(base):
    """Every tool page the hub links, every warm-up page /warmups links, and the tool variants the sitemap lists
    (SitePages::TOOL_VARIANTS: not on the hub)."""
    found = []
    for hub, pattern in (("/tools", r'href="(?:https?://[^/"]+)?(/tools/[a-z0-9-]+)"'), ("/warmups", r'href="(?:https?://[^/"]+)?(/warmups/[a-z0-9-]+)"'), ("/sitemap.xml", r"<loc>https?://[^/<]+(/tools/[a-z0-9-]+)</loc>")):
        html = urllib.request.urlopen(base + hub).read().decode()
        found += [p for p in dict.fromkeys(re.findall(pattern, html)) if p not in found]
    # Only pages that hold a tool's room (the sitemap also lists /tools/embed, which has none).
    return [p for p in found if 'class="s-tool"' in urllib.request.urlopen(base + p).read().decode()]
SLACK = 24

MEASURE = """(() => { const s = document.querySelector('.s-tool'); const css = getComputedStyle(s);
    const wide = matchMedia('(min-width: 768px)').matches;
    // --tool-top may be a calc() (the brand row and trail, with the phone's top inset): measured, not parsed.
    const probe = document.createElement('div');
    probe.style.cssText = 'position:absolute;visibility:hidden;height:var(--tool-top)';
    s.appendChild(probe);
    const top = probe.getBoundingClientRect().height;
    probe.remove();
    // On a phone, the screen under the header and tool bar, or the tool's own phone room when it's taller (--tool-room-phone).
    const room = wide ? parseFloat(css.getPropertyValue('--tool-room')) || 600 : Math.max(innerHeight - top, parseFloat(css.getPropertyValue('--tool-room-phone')) || 0);
    return [Math.round(s.getBoundingClientRect().height), Math.round(room)]; })()"""


def main():
    base = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000").rstrip("/")
    failures = []
    paths = pages(base)
    for width, height, mobile in ((1440, 900, False), (390, 844, True)):
        c = Chrome(None, width, height, mobile)
        try:
            for tool in paths:
                c.goto(f"{base}{tool}", "document.querySelector('.s-tool')?.dataset.state === 'ready'", timeout=60)
                time.sleep(0.5)
                actual, room = c.evaluate(MEASURE, await_promise=False)
                ok = abs(actual - room) <= SLACK
                print(f"{'ok  ' if ok else 'FAIL'} {width} {tool}: mounted {actual}px, room {room}px")
                if not ok:
                    failures.append(f"{tool} at {width}: --tool-room {actual}px")
        finally:
            c.close()
    if failures:
        print("Update css/input.css:", "; ".join(failures))
        sys.exit(1)


if __name__ == "__main__":
    main()
