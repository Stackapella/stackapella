"""AI voices (docs/ai-voices-build-plan.md, phase 1; js/ai-voices.js): Silent Night's four parts are AI guides in the
mock's catalogue (the alto based on Ada E.'s singing). With the ai_voices switch on for the tab (stackapella.mockAiVoices),
they play and every place that credits or chooses a voice says AI in words: Home's song sheet (with Be the voice once the
call is open and the singer is past the first week), the studio's rows and part panel, Sing's part picker (the calm tag,
or game mode's arcade label on the portraits) and its part sheet, and a lesson's song in Sing (the label, never Be the
voice inside a lesson). Off, nothing of it shows and the parts play the practice voice.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects, new_song, t  # noqa: E402

SESSION = "(await import('/js/stores/session.js')).session"
LEARNING = "(await import('/js/stores/learning.js')).learning"
WAITING = "AI voice, waiting for a real singer"
BASED = "AI voice based on Ada E.'s singing, with permission"


def switch(f, on):
    f.run(f"sessionStorage.setItem('stackapella.mockAiVoices', '{'1' if on else '0'}'); sessionStorage.setItem('stackapella.mockLendVoice', '1');")


def past_first_week(f):
    lessons = {f"l{i}": {"stars": 2} for i in range(3)}
    f.run(f"{LEARNING}.progress.lessons = {json.dumps(lessons)};")


def song_sheet(f):
    card = "[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Silent Night'))"
    f.run("document.querySelector('[data-browse-all]')?.click()")
    f.wait(card, what="Silent Night's card")
    f.run(f"{card}.querySelector('button').click()")
    f.wait("document.querySelector('[role=dialog]')?.innerText.includes('Silent Night')", what="the song's sheet")


def voices_ready(f):
    f.wait(f"{SESSION}.guidesVersion > 0 && !{SESSION}.preparing", timeout=30, what="the guides ready")
    f.wait(f"!Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=30, what="every voice arrived")


def ai_entries(f):
    return f.js(f"Object.values({SESSION}.guideVoices).filter((v) => v?.kind === 'real' && v.ai).length")


def journey(f):
    # ---- On ----
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    switch(f, True)
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    clear_projects(f)
    f.wait("(await import('/js/stores/account.js')).account.config?.aiVoices === true", what="the switch on")
    past_first_week(f)

    # Home's song card: its Play sings the AI parts, so the card says so with the AI tag; Amazing Grace's doesn't.
    f.run("document.querySelector('[data-browse-all]')?.click()")
    f.wait("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Silent Night'))?.querySelector('[data-card-ai]')", what="the AI tag on Silent Night's card")
    assert not f.js("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace'))?.querySelector('[data-card-ai]')"), "no AI tag on a song without AI parts"

    # Home's song sheet: one line names the four AI parts, Be the voice beside it; no "no voice yet" line as well.
    song_sheet(f)
    f.wait("document.querySelector('[data-song-ai-voices] [data-ai-voice=line]')", what="the AI parts line")
    line = f.js("document.querySelector('[data-song-ai-voices]').textContent")
    for part in ("soprano", "alto", "tenor", "bass"):
        assert t(f, f"parts.{part}").lower() in line.lower(), f"the sheet names the {part}: {line}"
    assert "AI voices" in line, line
    assert f.js("!!document.querySelector('[data-song-ai-voices] [data-ai-voice-go]')"), "Be the voice beside it"
    assert not f.js("document.querySelector('[data-song-no-voice]')"), "the AI parts aren't said twice as having no voice"
    # What you'll hear, before playback (the UX review, item 4): every part an AI voice, so one chip says so.
    assert f.js("[...document.querySelectorAll('[data-song-guides] [data-guide]')].map((c) => c.dataset.guide)") == ["ai"], "not one AI chip"
    f.no_sideways("the song's sheet")
    f.shot("sheet-on")
    f.press("Escape")

    # The studio: each row's line leads with the AI tag, and the alto's panel says whose singing it's based on.
    song = new_song(f, "silent-night")
    f.go(f"/p/{song}", "document.querySelectorAll('canvas').length > 3")
    voices_ready(f)
    assert ai_entries(f) == 4, f"four AI voices play: {ai_entries(f)}"
    f.wait("document.querySelectorAll('[data-status] [data-ai-voice=tag]').length === 4", what="four rows tagged AI")
    assert "Sung by" not in f.js("document.querySelector('#app').innerText"), "never a singer credit on an AI part"
    f.shot("studio-rows")
    alto = t(f, "parts.alto")
    f.run(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({alto!r})).click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[data-part-panel] [data-ai-voice=line]')", what="the alto's panel")
    words = f.js("document.querySelector('[data-part-panel] [data-ai-voice-words]').textContent")
    assert words == BASED, words
    assert f.js("!!document.querySelector('[data-part-panel] [data-ai-voice-go]')"), "Be the voice in the panel"
    f.shot("studio-panel")
    f.run("document.querySelector('[data-part-panel] [data-ai-voice-go]').click()")
    f.wait("location.pathname.endsWith('/sing')", what="Be the voice opens Sing on the part")

    # Sing: the part picker tags every part AI (the calm list's tag, or the arcade's portrait label in game mode). A fresh
    # song: the one above has its alto chosen now (Be the voice), so the calm skin would go straight past the part list.
    picker = new_song(f, "silent-night")
    f.go(f"/p/{picker}/sing", "document.querySelector('[data-way]')")
    f.run("document.querySelector('[data-way=parts]').click()")
    f.wait("document.querySelectorAll('[data-ai-voice=tag]').length === 4", what="four parts tagged AI in the picker")
    assert not f.js("document.querySelector('[data-no-voice]')"), "an AI part isn't also 'No voice yet'"
    f.no_sideways("Sing's part picker")
    f.shot("sing-picker")

    # A lesson's song (Sing opened from a lesson, project.course): the label is there, Be the voice never is. The way is
    # set as a lesson's step sets it, so Sing opens on the song itself.
    lesson = f.js("""(async () => {
        const s = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js');
        return (await s.startFromTemplate(await loadTemplate('silent-night'), { singers: 4, focusKey: 'alto', keyChosen: true, course: { lessonId: 'song-silent-night-alto-5' } })).id;
    })()""")
    f.go(f"/p/{lesson}", "document.querySelectorAll('canvas').length > 3")
    voices_ready(f)
    f.run(f"const s = {SESSION}; s.project.practice.sing = {{ way: 'whole', view: 'words', fitSaid: true, keyChosen: true }}; await (await import('/js/stores/session.js')).persist(async () => {{}});")
    f.go(f"/p/{lesson}/sing", "document.querySelector('#app header')")
    voices_ready(f)
    f.wait_text(BASED, timeout=30)
    assert not f.js("document.querySelector('[data-ai-voice-go]')"), "no Be the voice inside a lesson"
    f.shot("lesson-sing")

    # ---- Off: nothing of it shows, and the parts play the practice voice ----
    switch(f, False)
    f.go(f"/p/{song}?api=mock&scenario=&as=member", "document.querySelectorAll('canvas').length > 3")
    f.wait("(await import('/js/stores/account.js')).account.config?.aiVoices === false", what="the switch off")
    voices_ready(f)
    assert ai_entries(f) == 0, "no AI voice plays with the switch off"
    assert not f.js("document.querySelector('[data-ai-voice]')"), "no AI label in the studio"
    picker = new_song(f, "silent-night")
    f.go(f"/p/{picker}/sing", "document.querySelector('[data-way]')")
    f.run("document.querySelector('[data-way=parts]').click()")
    f.wait("document.querySelectorAll('[data-no-voice]').length === 4", what="the parts back to 'No voice yet'")
    assert not f.js("document.querySelector('[data-ai-voice]')"), "no AI label in Sing"
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    past_first_week(f)
    song_sheet(f)
    assert not f.js("document.querySelector('[data-song-ai-voices]') || document.querySelector('[data-ai-voice]')"), "no AI line on the sheet, nor a tag on any card"
    assert "ai" not in f.js("[...document.querySelectorAll('[data-song-guides] [data-guide]')].map((c) => c.dataset.guide)"), "an AI chip while AI voices are off"
    f.wait("document.querySelector('[data-song-no-voice]')", what="the sheet's usual no-voice line")
    f.shot("sheet-off")
    f.press("Escape")
    clear_projects(f)

    # ---- Admin, whatever the switch says ----
    # AI voices: who each is based on and their consent; Withdraw names exactly what it retires, in a styled sheet.
    f.go("/admin/ai-voices?api=mock&scenario=&as=admin", "document.querySelector('[data-ai-voices]')")
    rows = f.js("document.querySelectorAll('[data-ai-voice-row]').length")
    assert rows == 2, f"two AI voices listed: {rows}"
    assert f.js("!!document.querySelector('[data-ai-voice-no-consent]')"), "a voice with no consent says it's Admin only"
    f.no_sideways("Admin's AI voices")
    f.shot("admin-ai-voices")
    f.run("document.querySelector('[data-ai-voice-withdraw]').click()")
    f.wait("document.querySelector('[data-ai-voice-removes]')", what="the withdraw sheet")
    removes = f.js("document.querySelector('[data-ai-voice-removes]').textContent")
    assert "Silent Night" in removes and t(f, "parts.alto") in removes and "CDN" in removes, removes
    button = f.js("document.querySelector('[data-ai-voice-removes]').closest('form').querySelector('button[type=submit]').textContent.trim()")
    assert button == "Withdraw Alto A", button
    f.shot("admin-withdraw")
    f.run("document.querySelector('[data-ai-voice-removes]').closest('form').requestSubmit()")
    f.wait("!document.querySelector('[data-ai-voice-removes]') && [...document.querySelectorAll('[data-ai-voice-status]')].some((c) => c.textContent.includes('Withdrawn'))", what="withdrawn")
    # The guide list: the AI guide carries the AI tag and its voice's name, never counted as a real voice, with Retire.
    f.go("/admin/guides?api=mock&scenario=&as=admin", "document.querySelector('[data-guide-ai]')")
    assert f.js("!!document.querySelector('[data-guide-retire]')"), "Retire on the AI guide"
    f.run("document.querySelector('[data-guide-retire]').click()")
    f.wait("document.querySelector('[data-guide-retire-sheet]')", what="the retire sheet")
    assert not f.js("document.querySelector('[data-remove-abuse]')"), "no 'for abuse' box for an AI guide"
    f.shot("admin-retire")
    f.press("Escape")


if __name__ == "__main__":
    run("ai_voices", journey)
