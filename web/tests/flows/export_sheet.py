"""Sheet music out (docs/sheet-music-plan.md, slice 1): Amazing Grace for four, moved up a tone in the
studio. Export's Sheet music gives the score as MusicXML in the key it's sung in (A, three sharps),
every part a staff, the words under the notes and the arrangement's licence in the file; then the
alto alone as MIDI, a tempo track and one part. The Sound choices aren't there for sheet music, and
it's never Plus.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SESSION = "(await import('/js/stores/session.js'))"

# Every download is caught, to read back.
CATCH = """
window.downloads = [];
HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push({ href: this.href, name: this.download }); };
"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_export(f):
    """Export: its own header button on a wide screen, in the More menu on a phone."""
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t("studio.export")
    f.wait("document.querySelector('[data-export]')", what="the export sheet")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    project = f.js("""(async () => {
        const { loadTemplate } = await import('/js/templates.js');
        const { startFromTemplate } = await import('/js/stores/projects.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length === 4", what="the four parts")
    f.run(f"{SESSION}.session.project.transpose = 2")  # G up a tone: A
    f.run(CATCH)

    open_export(f)
    f.shot("export")
    f.click_t("export.sheet")
    f.wait("document.querySelector('[data-sheet-part]')", what="sheet music's parts")
    assert f.js(f"document.querySelector('[data-sheet-part]').closest('label').textContent.includes({json.dumps(t(f, 'export.sheetParts'))})"), "the part list has a visible label"
    assert not f.js(f"document.querySelector('[data-export]').textContent.includes({json.dumps(t(f, 'export.sound'))})"), "no Sound choices for sheet music"
    summary = t(f, "export.sheetSummary", {"format": "MusicXML", "count": 4})
    f.wait_text(summary)
    f.shot("sheet")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 1", timeout=30, what="the MusicXML")
    score = f.js("""(async () => {
        const d = window.downloads[0]; const text = await (await fetch(d.href)).text();
        const doc = new DOMParser().parseFromString(text, 'application/xml');
        return { name: d.name, head: text.slice(0, 60), parses: !doc.querySelector('parsererror'), parts: doc.querySelectorAll('part').length,
            fifths: doc.querySelector('key fifths').textContent, words: doc.querySelectorAll('lyric').length, rights: doc.querySelector('rights')?.textContent ?? '' };
    })()""")
    assert score["name"].endswith(".musicxml") and score["head"].startswith("<?xml") and score["parses"], f"a MusicXML file: {score}"
    assert score["parts"] == 4 and score["fifths"] == "3" and score["words"] > 20, f"four staves in A with the words: {score}"

    # The alto alone, as MIDI.
    open_export(f)
    f.click_t("export.sheet")
    f.wait("document.querySelector('[data-sheet-part]')", what="sheet music's parts")
    f.run("const s = document.querySelector('[data-sheet-part]'); s.value = 'alto'; s.dispatchEvent(new Event('change'))")
    f.run("[...document.querySelectorAll('[data-export] [role=radio]')].find((b) => b.textContent.trim() === 'MIDI').click()")
    f.wait_text(t(f, "export.midiHint"))
    f.wait_text(t(f, "export.sheetSummary", {"format": "MIDI", "count": 1}))
    f.shot("midi")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("window.downloads.length === 2", timeout=30, what="the MIDI file")
    midi = f.js("""(async () => {
        const d = window.downloads[1]; const bytes = new Uint8Array(await (await fetch(d.href)).arrayBuffer());
        return { name: d.name, head: String.fromCharCode(...bytes.slice(0, 4)), tracks: (bytes[10] << 8) | bytes[11] };
    })()""")
    assert midi["name"].endswith(".mid") and midi["head"] == "MThd" and midi["tracks"] == 2, f"a MIDI file of the alto: {midi}"


if __name__ == "__main__":
    run("export_sheet", journey)
