"""Hillel's studio asks: a song's tracks are "Track 1", "Track 2" (never Melody or Harmony), renamed
in the row; Record always starts at 0:00, wherever the playhead was ("In the studio we should always
rewind to the start before recording"); and the first track sets the song's length, so a later take
stops by itself at its end, is kept, and goes back to 0:00.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO, SESSION, clear_projects, home_page  # noqa: E402
from record_mix import heard_while_recording  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
PARTS = f"{SESSION}.session.parts"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    home_page(f, "member")
    clear_projects(f)
    f.run(f"""const a = {AUDIO}; await a.unlock(); await a.openMic(); a.engine.setLoopback(true);
        sessionStorage.setItem('stackapella.headphones', '1');
        for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${{a.audio.mic?.label ?? 'default'}}.${{route}}.${{a.engine.sampleRate}}`, '0');
        a.chooseRoute('wired');""")
    song = f.js("(async () => (await (await import('/js/stores/projects.js')).createProject({ title: 'Our Song', bpm: 120 })).id)()")
    f.go(f"/p/{song}", "document.querySelector('#app header')")
    f.mic_checked()

    # The first track: "Track 1", and it sets the length.
    f.press("r")
    heard_while_recording(f, "the first track", seconds=2)
    f.press("r")
    f.wait(f"!{SESSION}.session.recording && {PARTS}.length === 1", timeout=20, what="the first track kept")
    assert f.js(f"{PARTS}[0].name") == t(f, "parts.default.track", {"number": 1}), f.js(f"{PARTS}[0].name")
    first_end = f.js(f"(async () => {{ const {{ heardSpan }} = await import('/js/trim.js'); return heardSpan({PARTS}[0]).to; }})()")

    # Play into the middle, stop, record: it starts from 0:00, and stops by itself at the first track's end.
    f.run(f"{SESSION}.session.position = {first_end / 2}")
    f.press("r")
    f.wait(f"{SESSION}.session.recording", timeout=20, what="recording again")
    assert f.js(f"{SESSION}.session.recordFrom") == 0, f"recording from {f.js(f'{SESSION}.session.recordFrom')}, not 0:00"
    f.wait(f"!{SESSION}.session.recording && {PARTS}.length === 2", timeout=first_end + 20, what="the take stopped by itself at the first track's end")
    f.wait(f"{SESSION}.session.position === 0", what="back to 0:00")
    second = {"name": f.js(f"{PARTS}[1].name")}
    assert second["name"] == t(f, "parts.default.track", {"number": 2}), second["name"]
    to = f.js(f"(async () => {{ const {{ heardSpan }} = await import('/js/trim.js'); return heardSpan({PARTS}[1]).to; }})()")
    assert to <= first_end + 1, f"the second take ran to {to:.2f}s, past the first track's {first_end:.2f}s"
    f.shot("two-tracks")

    # Rename in the row: Enter saves, Escape keeps the old name.
    row = f"[...document.querySelectorAll('[data-tracks] section')].find((s) => s.innerText.includes({json.dumps(second['name'])}))"
    row_first = "document.querySelector('[data-tracks] section')"
    f.run(f"{row}.querySelector('button[aria-expanded]').click()")
    f.click_t("studio.row.rename")
    f.wait("document.querySelector('[data-rename-input]')", what="the name field")
    f.run("const i = document.querySelector('[data-rename-input]'); i.value = 'Ignored'; i.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))")
    f.wait("!document.querySelector('[data-rename-input]')", what="Escape closes it")
    assert f.js(f"{PARTS}[1].name") == second["name"], "Escape kept the old name"
    f.click_t("studio.row.rename")
    f.wait("document.querySelector('[data-rename-input]')", what="the name field again")
    f.run("const i = document.querySelector('[data-rename-input]'); i.value = 'Alto line'; i.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))")
    f.wait(f"{PARTS}[1].name === 'Alto line'", what="renamed")
    f.wait(f"(await import('/js/db.js')).get('parts', {PARTS}[1].id).then((p) => p.name === 'Alto line')", what="the new name kept on the device")
    f.shot("renamed")

    # Recording over Track 1 keeps the take it replaces; Undo uses it again; the row lists it.
    f.run(f"{row_first}.querySelector('[data-pick]').click()")
    f.wait(f"document.querySelector('[data-ready]')", what="Track 1 picked to record")
    first_take = f.js(f"{PARTS}[0].takeId") or f.js(f"(async () => (await (await import('/js/db.js')).get('parts', {PARTS}[0].id)).takeId)()")
    f.press("r")
    heard_while_recording(f, "over Track 1", seconds=1)
    f.press("r")
    f.wait(f"!{SESSION}.session.recording && ({PARTS}[0].keptTakes ?? []).length === 1", timeout=20, what="the take recorded over, kept")
    f.wait_text(t(f, "studio.takes.undoPrevious"))
    assert f.js(f"{PARTS}[0].keptTakes[0].takeId") == first_take, "the kept take is the one recorded over"
    f.run(f"{row_first}.querySelector('button[aria-expanded]').click()")
    f.wait(f"{row_first}.querySelectorAll('[data-take]').length === 1", what="the row's takes: the kept one")
    f.shot("takes")
    f.run(f"{row_first}.querySelector('[data-take]').querySelector('.btn-secondary').click()")
    f.wait(f"{PARTS}[0].takeId === {json.dumps(first_take)}", what="the earlier take in use again")
    assert f.js(f"{PARTS}[0].keptTakes.length") == 1, "the take it replaced is kept in turn"
    f.wait(f"(await import('/js/db.js')).get('audio', 'take:' + {PARTS}[0].keptTakes[0].takeId).then((a) => !!a)", what="its audio kept on the device")
    # Delete the kept one: a styled question, then gone.
    f.run(f"[...{row_first}.querySelectorAll('[data-take] button')].at(-1).click()")
    f.wait("document.querySelector('[role=dialog]')", what="the delete question")
    f.click_t("studio.takes.delete")
    f.wait(f"({PARTS}[0].keptTakes ?? []).length === 0", what="the kept take deleted")
    clear_projects(f)


if __name__ == "__main__":
    run("studio_record", journey)
