"""A segmented control never wraps (Hillel's 84.png: the studio's Room, "Chapel" alone on a second row
inside the pill). At 320 px wide, the narrowest phone, every segmented control on the screens that
have them sits on one row, or, when its options don't fit, becomes a grid of equal segments
(seg-stack), with nothing cut off; at 390 px Export's Room is a grid of two by two.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import SESSION, clear_projects, new_song  # noqa: E402

# Every visible segmented control: on one row with nothing overflowing, or stacked as a grid.
CHECK = """(() => [...document.querySelectorAll('.seg')].filter((s) => s.getClientRects().length).map((s) => {
    const tops = new Set([...s.children].map((c) => Math.round(c.getBoundingClientRect().top)));
    const box = s.getBoundingClientRect(); const room = s.parentElement.getBoundingClientRect();
    const overflowing = s.scrollWidth > s.clientWidth + 1 || [...s.children].some((c) => c.scrollWidth > c.clientWidth + 1) || box.right > room.right + 1 || box.left < room.left - 1;
    const stacked = s.classList.contains('seg-stack');
    return { label: s.getAttribute('aria-label') || s.textContent.trim().slice(0, 30), rows: tops.size, stacked, overflowing };
}).filter((s) => s.overflowing || (s.rows > 1 && !s.stacked)))()"""


def width(f, px):
    f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": px, "height": 760, "deviceScaleFactor": 2, "mobile": True})
    f.js("new Promise((r) => setTimeout(r, 300))")


def check(f, where):
    f.js("new Promise((r) => setTimeout(r, 250))")
    bad = f.js(CHECK)
    assert not bad, f"{where}: segmented controls that wrap or overflow: {json.dumps(bad)}"
    sideways = f.js("document.documentElement.scrollWidth - innerWidth")
    assert sideways <= 1, f"{where}: the page scrolls sideways by {sideways} px"


def journey(f):
    if f.size != "phone":
        return  # the narrow widths are the point
    width(f, 320)
    for path, ready in [
        ("/styleguide?api=mock&scenario=&as=admin", "document.querySelector('.seg')"),
        ("/settings?api=mock&scenario=", "document.querySelector('form')"),
        ("/admin/usage?api=mock&as=admin", "document.querySelector('[data-usage-updated]')"),
        ("/admin/funnel?api=mock&as=admin", "document.querySelector('.seg')"),
        ("/admin/posts?api=mock&as=admin", "document.querySelector('.seg')"),
        ("/admin/support?api=mock&as=admin", "document.querySelector('.seg')"),
        ("/admin/reports?api=mock&as=admin", "document.querySelector('.seg')"),
    ]:
        f.go(path, ready)
        check(f, path.split("?")[0])

    # A song: Sing's settings, then the studio's Export with Polished's Room.
    f.go("/voice?api=mock&scenario=", "document.querySelector('#app header, #app main')")
    clear_projects(f)
    song = new_song(f)
    f.go(f"/p/{song}/sing", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length > 3", what="the parts")
    f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'parts', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();""")
    f.wait("document.querySelector('header [data-settings-open]')", what="Sing's header")
    f.run("document.querySelector('[data-settings-open]').click()")
    f.wait("document.querySelector('[data-session-settings]')", what="Sing's settings")
    check(f, "Sing's settings")
    f.press("Escape")
    f.go(f"/p/{song}", "document.querySelector('#app header')")
    f.run("const d = document.querySelector('header details'); if (d) d.open = true;")
    f.click(f.js("(await import('/js/i18n.js')).t('studio.export')"))
    f.wait("document.querySelector('[data-export]')", what="Export")
    room = "[...document.querySelectorAll('[data-export] .seg')].find((s) => s.getAttribute('aria-label') === " + json.dumps(f.js("(await import('/js/i18n.js')).t('polish.room.label')")) + ")"
    f.wait(f"{room}", what="the Room choices")
    check(f, "Export at 320 px")
    f.run(f"{room}.scrollIntoView({{ block: 'center' }})")
    f.shot("room-320")
    assert f.js(f"{room}.classList.contains('seg-stack') && !{room}.classList.contains('seg-stack-1')"), "Room's four, two by two at 320 px"
    width(f, 390)
    check(f, "Export at 390 px")
    f.run(f"{room}.scrollIntoView({{ block: 'center' }})")
    f.shot("room-390")
    clear_projects(f)


if __name__ == "__main__":
    run("segments_fit", journey)
