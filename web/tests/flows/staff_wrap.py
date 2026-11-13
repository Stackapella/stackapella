"""Sing's staff on a phone (components/sing/staff.js, `wrap`): the busiest real lines (the widest phrase of the songs whose
lines are longest on a staff: Oy Chanukah, Ave verum corpus, We Three Kings, and the busiest 6/8, Rozhinkes mit Mandlen)
never draw their words over each other, and a line too long to read at the screen's width wraps at its bar lines into
rows drawn at a readable size, two at a time (the row being sung and the next). At 360 and 390 in both skins, and desktop
as the check that a line that fits stays one row. The printed parts of each, on desktop, never run words together either.
Against the mock; the songs are read from the server's copy.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SESSION = "(await import('/js/stores/session.js'))"
SONGS = Path(__file__).resolve().parents[3] / "server" / "database" / "templates"
# (song, the start beat of its widest line)
LINES = [("oy-chanukah", 32), ("ave-verum-corpus", 130), ("we-three-kings", 24), ("rozhinkes-mit-mandlen", 8)]

CHECK = """(() => {
    const rows = [...document.querySelectorAll('svg.max-h-56')]; // the staff's rows (one svg before rows existed)
    const box = document.querySelector('[data-staff]');
    const out = { rows: Number(box?.dataset.rows ?? 1), shown: rows.length, smallest: 9, overlaps: [] };
    for (const svg of rows) {
        const box = svg.viewBox.baseVal;
        out.smallest = Math.min(out.smallest, svg.getBoundingClientRect().width / box.width);
        const words = [...svg.querySelectorAll('text[font-size="13"]')].map((t) => t.getBoundingClientRect()).sort((a, b) => a.left - b.left);
        for (let i = 1; i < words.length; i++) if (words[i].left < words[i - 1].right - 0.5) out.overlaps.push(Math.round(words[i - 1].right - words[i].left));
    }
    return out;
})()"""

# The printed parts: each line of words (texts at one height in one system), left to right, never overlapping.
PRINT_CHECK = """(() => {
    const lines = new Map();
    for (const t of document.querySelectorAll('svg text[font-size="15"]')) {
        const r = t.getBoundingClientRect();
        if (!r.width) continue;
        const key = Math.round(r.top) + ':' + t.closest('svg').dataset.i;
        if (!lines.has(key)) lines.set(key, []);
        lines.get(key).push(r);
    }
    const overlaps = [];
    for (const words of lines.values()) {
        words.sort((a, b) => a.left - b.left);
        for (let i = 1; i < words.length; i++) if (words[i].left < words[i - 1].right - 0.5) overlaps.push(Math.round(words[i - 1].right - words[i].left));
    }
    return { lines: lines.size, overlaps };
})()"""


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    for song, start in LINES:
        template = json.loads((SONGS / f"{song}.json").read_text())
        pid = f.js(f"""(async () => {{
            const {{ startFromTemplate }} = await import('/js/stores/projects.js');
            const {{ validateTemplate }} = await import('/js/templates.js');
            return (await startFromTemplate(validateTemplate({json.dumps(template)}), {{ singers: 4, renderGuides: false }})).id;
        }})()""")
        f.go(f"/p/{pid}/sing", "document.querySelector('#app header')")
        f.wait(f"{SESSION}.session.parts.length >= 2", what=f"{song} open")
        f.run(f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', view: 'staff', fitSaid: true, keyChosen: true }};
            s.practise(s.session.parts.find((p) => p.templatePartKey && (s.session.project.template.parts.find((t) => t.key === p.templatePartKey)?.melody)) ?? s.session.parts[0]); s.savePractice();""")
        f.run(f"const s = {SESSION}; s.selectPhrase(s.session.phraseList.findIndex((p) => Math.abs(p.startBeat - {start}) < 0.01))")
        f.wait("document.querySelector('svg.max-h-56 text[font-size=\"13\"]')", what=f"{song}'s staff with its words")
        import time
        time.sleep(0.5)  # the row split follows the measured width
        got = f.js(CHECK)
        print(f"    {song} at {f.size}: {got['rows']} row(s), {got['shown']} shown, drawn at {got['smallest']:.2f}, overlaps {got['overlaps']}")
        assert not got["overlaps"], f"{song}: words overlap by {got['overlaps']} px"
        assert got["shown"] <= 2, f"{song}: {got['shown']} rows shown at once"
        if f.size != "desktop":
            assert got["rows"] == 1 or got["smallest"] >= 0.8, f"{song}: a wrapped row drawn at {got['smallest']:.2f}"
        f.shot(song)
        # The same song's printed parts (desktop: the page is the print's own width).
        if f.size == "desktop":
            f.go(f"/p/{pid}/print", "document.querySelector('[data-score-note]')")
            f.run("document.querySelectorAll('svg').forEach((svg, i) => (svg.dataset.i = i))")
            printed = f.js(PRINT_CHECK)
            print(f"    {song} printed: {printed['lines']} lines of words, overlaps {printed['overlaps']}")
            assert not printed["overlaps"], f"{song} printed: words overlap by {printed['overlaps']} px"
            f.shot(f"{song}-print")


if __name__ == "__main__":
    run("staff_wrap", journey, sizes=("narrow", "phone", "desktop"))
