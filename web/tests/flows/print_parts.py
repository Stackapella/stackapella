"""Printable parts (docs/sheet-music-plan.md, slice 2): Amazing Grace for four, moved up a tone in the
studio. Export's Sheet music, Print, opens the score on its own page (in A, three sharps, every part a
staff) and the browser's print at once; on paper it's only the score, black on white. Then the alto
alone, with the tune greyed above it.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SESSION = "(await import('/js/stores/session.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def print_from_export(f, part=None):
    if f.size == "phone":
        f.run("document.querySelector('header details').open = true")
    f.click_t("studio.export")
    f.wait("document.querySelector('[data-export]')", what="the export sheet")
    f.click_t("export.sheet")
    f.wait("document.querySelector('[data-sheet-part]')", what="sheet music's parts")
    if part:
        f.run(f"const s = document.querySelector('[data-sheet-part]'); s.value = {json.dumps(part)}; s.dispatchEvent(new Event('change'))")
    f.run(f"[...document.querySelectorAll('[data-export] [role=radio]')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'export.print'))}).click()")
    f.wait_text(t(f, "export.printHint"))
    assert not f.js("document.querySelector('[data-export] input.input')"), "no file name to give when printing"
    f.shot("export-print" if not part else "export-print-alto")
    f.run("window.printed = 0; window.print = () => { window.printed++; }; document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("location.pathname.endsWith('/print') && document.querySelectorAll('[data-print-system]').length > 1", what="the printable score")
    f.wait("window.printed === 1", what="the browser's print, opened at once")


def journey(f):
    f.go("/", "document.querySelector('#app header')")
    project = f.js("""(async () => {
        const { loadTemplate } = await import('/js/templates.js');
        const { startFromTemplate } = await import('/js/stores/projects.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length === 4", what="the four parts")
    f.run(f"{SESSION}.session.project.transpose = 2; await (await import('/js/stores/projects.js')).saveProject({SESSION}.session.project)")

    print_from_export(f)
    staves = f.js("document.querySelectorAll('[data-print-system]')[0].querySelectorAll(':scope > g').length")
    assert staves == 4, f"every part a staff: {staves}"
    sharps = f.js("[...document.querySelectorAll('[data-print-system]')[0].querySelectorAll('text')].filter((x) => x.textContent === '\\u266F').length")
    assert sharps == 4 * 3, f"in A, three sharps on each staff: {sharps}"
    words = f.js("[...document.querySelectorAll('[data-print-system] text')].map((x) => x.textContent).join(' ')")
    assert "grace," in words and "sweet" in words, "the words under the notes"
    f.shot("score")
    f.chrome.send("Emulation.setEmulatedMedia", {"media": "print"})
    f.wait("![...document.querySelectorAll('[data-no-print]')].some((e) => e.getClientRects().length)", what="the buttons left off the paper")
    f.shot("paper")
    f.chrome.send("Emulation.setEmulatedMedia", {"media": ""})

    # The alto alone, the tune greyed above it.
    f.go(f"/p/{project}", "document.querySelector('#app header')")
    f.wait(f"{SESSION}.session.parts.length === 4", what="the four parts")
    print_from_export(f, "alto")
    staves = f.js("[...document.querySelectorAll('[data-print-system]')[0].querySelectorAll(':scope > g')].map((g) => g.getAttribute('opacity'))")
    assert staves == ["0.55", "1"], f"the tune greyed above the alto: {staves}"
    f.wait_text(t(f, "print.partWithTune", {"part": t(f, "parts.alto")}))
    f.shot("alto")


if __name__ == "__main__":
    run("print_parts", journey)
