"""A director's Make tracks from a score (docs/score-import-plan.md, section 4; slice C): on the group page, beside "Send
us your song", only where sheet_import allows directors (off in production until the DMCA agent is in). A MusicXML file
opens in Bring your music with the director's additions: the tune's line, the proposals from templates.check (none
applied; a problem stops the song), whose music it is (Send us your song's two sentences, word for word) and the group.
Make the tracks saves it private as an import with that rights record and shares it into the group in one step, then
opens it. A PDF goes to Send us your song instead.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

GROUP = "/groups/Gp4rQs?api=mock&scenario=&as=plus"
SWITCH_ON = "(await import('/js/stores/account.js')).account.config.sheetImport = true"
OPEN = """const bytes = await (await fetch('/tests/fixtures/NAME')).arrayBuffer();
    const files = new DataTransfer(); files.items.add(new File([bytes], 'NAME'.split('/').pop(), { type: 'TYPE' }));
    const input = document.querySelector('[data-director-score]'); input.files = files.files; input.dispatchEvent(new Event('change'));"""
# What the save and the share sent (the mock answers both; the flow reads what went).
SPY = """const { api } = await import('/js/api.js');
    window.sent = { shared: [] };
    const share = api.groups.songs.create; api.groups.songs.create = (id, fields) => { window.sent.shared.push([id, fields]); return share(id, fields); };
"""


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def open_score(f, name, kind="application/vnd.recordare.musicxml"):
    f.run(OPEN.replace("NAME", name).replace("TYPE", kind))


def journey(f):
    # Off for directors (production today): only Send us your song.
    f.go(GROUP, "document.querySelector('[data-ask-tracks]')")
    assert not f.js("!!document.querySelector('[data-make-tracks]')"), "Make tracks waits for sheet_import's directors value"

    # On for directors: the second way, on the same line.
    f.run(SWITCH_ON)
    f.wait("document.querySelector('[data-ask-tracks] [data-make-tracks]')", what="Make tracks from a score")
    f.run("document.querySelector('[data-ask-tracks]').scrollIntoView({ block: 'center' })")
    f.no_sideways("the group page with both ways")
    f.shot("both-ways")

    # A PDF isn't read here: Send us your song takes it.
    f.run("""const files = new DataTransfer(); files.items.add(new File(['%PDF-1.4'], 'score.pdf', { type: 'application/pdf' }));
        const input = document.querySelector('[data-director-score]'); input.files = files.files; input.dispatchEvent(new Event('change'));""")
    f.wait_text(t(f, "sheet.director.pdf"))
    f.run("[...document.querySelectorAll('[data-toast-tone] button')].find((b) => b.textContent.trim() === " + json.dumps(t(f, "groups.tracks.askLink")) + ").click()")
    f.wait("document.querySelector('[data-tracks-form]')", what="Send us your song's form")
    f.press("Escape")

    # A MusicXML file: its lines, with the director's additions.
    f.run(SPY)
    open_score(f, "old-hundredth.mxl")
    f.wait("document.querySelectorAll('[data-sheet-line]').length === 4", what="the chorale's four lines")
    f.run("""const rows = [...document.querySelectorAll('[data-sheet-line] select')];
        ['soprano', 'alto', 'tenor', 'bass'].forEach((v, i) => { rows[i].value = v; rows[i].dispatchEvent(new Event('change')); });""")
    f.wait("document.querySelector('[data-sheet-tune] select')", what="the tune's line")
    tune = f.js("(() => { const s = document.querySelector('[data-sheet-tune] select'); return { value: s.value, options: s.options.length }; })()")
    assert tune["options"] == 5, f"each picked line, or none: {tune}"
    # The proposals, listed and never applied.
    f.wait("document.querySelector('[data-sheet-advice]')", what="the proposals")
    assert f.js("document.querySelectorAll('[data-sheet-advice] li').length") == 2
    submit = "document.querySelector('[data-sheet-import] button[type=submit]')"
    assert f.js(f"{submit}.disabled"), "Make the tracks waits for whose music it is"
    for key in ("groups.tracks.publicDomainSentence", "groups.tracks.ownArrangementSentence"):
        f.wait_text(t(f, key))  # Send us your song's two sentences, word for word
    f.wait_text(t(f, "sheet.director.inCopyright"))
    assert f.js("document.querySelector('[data-sheet-group] select').value") == "Gp4rQs", "the group it came from"
    f.run("document.querySelector('[data-sheet-whose-option=own-arrangement]').click()")
    f.wait(f"!{submit}.disabled", what="Make the tracks")
    f.run("document.querySelector('[data-sheet-proposals]').scrollIntoView({ block: 'center' })")
    f.no_sideways("Bring your music, the director's way")
    f.shot("bring-your-music")

    # The alto as the tune: its part carries it.
    f.run("const s = document.querySelector('[data-sheet-tune] select'); s.value = s.options[1].value; s.dispatchEvent(new Event('change'));")
    f.wait("document.querySelector('[data-sheet-advice]')", what="checked again")
    f.run("document.querySelector('[data-sheet-import]').requestSubmit()")
    f.wait("location.pathname.startsWith('/t/')", what="the new song's page")
    sent = f.js("JSON.stringify(window.sent)")
    sent = json.loads(sent)
    song = f.js("location.pathname.split('/')[2]")
    record = f.js("""(async () => { const { mine } = await import('/js/stores/templates.js'); const r = mine.list.find((x) => x.id === location.pathname.split('/')[2]);
        return { source: r.source, rights: r.rights, visibility: r.visibility }; })()""")
    assert record == {"source": "sheet-music", "rights": "own-arrangement", "visibility": "private"}, f"saved private as an import, with whose music it is: {record}"
    assert sent["shared"] == [["Gp4rQs", {"templateId": song}]], sent
    melody = f.js("""(async () => { const { mine } = await import('/js/stores/templates.js'); const id = location.pathname.split('/')[2];
        return mine.list.find((x) => x.id === id).template.parts.filter((p) => p.melody).map((p) => p.key); })()""")
    assert melody == ["alto"], f"the director's tune: {melody}"
    f.shot("made")

    # A problem from the check stops the song, with its bar.
    f.go(GROUP, "document.querySelector('[data-ask-tracks]')")
    f.run(SWITCH_ON)
    f.run("""const { api } = await import('/js/api.js');
        api.templates.check = async () => ({ data: { problems: ['bass bar 3: a note is outside the song'], advice: [] } });""")
    f.wait("document.querySelector('[data-make-tracks]')", what="Make tracks")
    open_score(f, "editors/finale-amazing-grace.musicxml")
    f.wait("document.querySelectorAll('[data-sheet-line]').length === 1", what="the hymn's line")
    f.run("const s = document.querySelector('[data-sheet-line] select'); s.value = 'soprano'; s.dispatchEvent(new Event('change')); document.querySelector('[data-sheet-whose-option=public-domain]').click();")
    f.wait("document.querySelector('[data-sheet-check-problems]')", what="the problem")
    assert f.js(f"{submit}.disabled"), "a song with problems is never made"


if __name__ == "__main__":
    run("director_import", journey)
