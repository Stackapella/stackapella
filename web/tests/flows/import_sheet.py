"""Sheet music in (docs/sheet-music-plan.md, slices 3 and 4; docs/sheet-music-patent-notes.md): in a
singer's first week, New song's Open a file takes only a template. After it, a public-domain chorale
(Old Hundredth, a real MusicXML file written by music21, compressed as .mxl) opens as its four lines,
named as the file names them ("Soprano Alto", voice 1 and 2), and none is given a voice: the singer
picks every one. Make the song waits for the confirmation of the right to use it, and the song is
saved as an import, private, with no template file to export. A MIDI file's tracks named Soprano to
Bass come named.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from app_audit import OPEN_FILE  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def open_file_sheet(f):
    f.wait("(await import('/js/stores/account.js')).account.loaded", what="the account")
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.wait("document.querySelector('[data-open-file]')", what="Open a file")


def journey(f):
    # Until the server's sheetImport switch is on, only staff get it: a member's Open a file takes templates.
    f.go("/?api=mock&as=member", "document.querySelector('[data-new-song]')")
    f.js("(async () => (await (await import('/js/stores/projects.js')).createProject({ title: 'A first song', bpm: 90 })).id)()")
    f.go("/?api=mock&as=member", "document.querySelector('[data-new-song]')")
    open_file_sheet(f)
    assert f.js("document.querySelector('[data-open-file]').accept") == ".json,application/json", "sheet music waits for the server's switch"
    f.press("Escape")
    f.js("(async () => { const p = await import('/js/stores/projects.js'); for (const x of [...p.projects.list]) await p.deleteProject(x.id); })()")

    # A new singer's first week (staff, before the switch): Open a file is for templates only.
    f.go("/?api=mock&as=admin", "document.querySelector('[data-new-song]')")
    open_file_sheet(f)
    assert f.js("document.querySelector('[data-open-file]').accept") == ".json,application/json", "sheet music waits until the first week is over"
    f.press("Escape")

    # A song made ends the first week.
    f.js("(async () => (await (await import('/js/stores/projects.js')).createProject({ title: 'A first song', bpm: 90 })).id)()")
    f.go("/?api=mock&as=admin", "document.querySelector('[data-new-song]')")
    open_file_sheet(f)
    assert ".musicxml" in f.js("document.querySelector('[data-open-file]').accept"), "Open a file takes sheet music after the first week"
    f.wait_text(t(f, "create.home.importHintSheet"))
    f.shot("open-a-file")

    f.run(OPEN_FILE.replace("NAME", "old-hundredth.mxl"))
    f.wait("document.querySelectorAll('[data-sheet-line]').length === 4", what="the chorale's four lines")
    rows = f.js("[...document.querySelectorAll('[data-sheet-line]')].map((r) => ({ name: r.querySelector('.font-semibold').textContent.trim(), as: r.querySelector('select').value }))")
    assert [r["name"] for r in rows] == [t(f, "sheet.lineVoice", {"name": "Soprano Alto", "number": 1}), t(f, "sheet.lineVoice", {"name": "Soprano Alto", "number": 2}), t(f, "sheet.lineVoice", {"name": "Tenor Bass", "number": 1}), t(f, "sheet.lineVoice", {"name": "Tenor Bass", "number": 2})], f"the file's own names: {rows}"
    assert all(r["as"] == "" for r in rows), f"no line is given a voice by the app (the patent design-around): {rows}"
    assert f.js("document.querySelector('[data-sheet-import] button[type=submit]').disabled"), "Make the song waits for the singer's picks"
    f.shot("lines")

    f.run("""const rows = [...document.querySelectorAll('[data-sheet-line] select')];
        ['soprano', 'alto', 'tenor', 'bass'].forEach((v, i) => { rows[i].value = v; rows[i].dispatchEvent(new Event('change')); });""")
    assert f.js("document.querySelector('[data-sheet-import] button[type=submit]').disabled"), "and for the confirmation of the right to use it"
    f.run("document.querySelector('[data-sheet-rights]').click()")
    f.wait("!document.querySelector('[data-sheet-import] button[type=submit]').disabled", what="Make the song")
    f.shot("picked")
    f.run("document.querySelector('[data-sheet-import]').requestSubmit()")
    f.wait("location.pathname.startsWith('/t/')", what="the new song's page")
    record = f.js("""(async () => { const { mine } = await import('/js/stores/templates.js'); const id = location.pathname.split('/')[2];
        const r = mine.list.find((x) => x.id === id); return { source: r.source, visibility: r.visibility, title: r.template.title, parts: r.template.parts.map((p) => p.key), words: r.template.parts[3].guide.notes[0].lyric }; })()""")
    assert record == {"source": "sheet-music", "visibility": "private", "title": "Old Hundredth", "parts": ["soprano", "alto", "tenor", "bass"], "words": "All"}, f"saved as an import: {record}"
    f.wait_text(t(f, "create.save.otherHint"))
    assert not f.js(f"[...document.querySelectorAll('button')].some((b) => b.textContent.includes({json.dumps(t(f, 'template.export'))}))"), "no template file for an import"
    f.shot("song")

    # In the studio, a song made from sheet music has no Invite singers or share link (the account refuses them).
    project = f.js("""(async () => { const { mine } = await import('/js/stores/templates.js'); const id = location.pathname.split('/')[2];
        const { startFromTemplate } = await import('/js/stores/projects.js'); return (await startFromTemplate(mine.list.find((r) => r.id === id).template, { singers: 4 })).id; })()""")
    f.go(f"/p/{project}?api=mock&as=admin", "document.querySelector('#app header')")
    f.wait("(await import('/js/stores/session.js')).session.parts.length === 4", what="the four parts in the studio")
    f.wait(f"!document.body.innerText.includes({json.dumps(t(f, 'studio.inviteSingers'))})", what="no Invite singers for an import")

    # A MIDI file: tracks named for their voices come named, and the words come too.
    f.go("/?api=mock&as=admin", "document.querySelector('[data-new-song]')")
    open_file_sheet(f)
    f.run("""const song = await (await fetch('/templates/amazing-grace.json')).json();
        const bytes = (await import('/js/midi.js')).toMidi(song);
        const files = new DataTransfer(); files.items.add(new File([bytes], 'amazing-grace.mid'));
        const input = document.querySelector('[data-open-file]'); input.files = files.files; input.dispatchEvent(new Event('change'));""")
    f.wait("document.querySelectorAll('[data-sheet-line]').length === 4", what="the MIDI file's tracks")
    named = f.js("[...document.querySelectorAll('[data-sheet-line] select')].map((s) => s.value)")
    assert named == ["soprano", "alto", "tenor", "bass"], f"tracks named for their voices: {named}"
    f.shot("midi")


if __name__ == "__main__":
    run("import_sheet", journey)
