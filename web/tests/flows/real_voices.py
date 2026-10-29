"""M2, real voices in the player (on the mock, whose catalogue gives Amazing Grace's alto a real
voice, played from a local fixture): the part shows "Real voice" and a one-time note names its
singer; the practice voice can be chosen instead and back; in another key the practice voice
plays with a kind note, and "Sing in G with the real voice" brings it back.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SESSION = "(await import('/js/stores/session.js')).session"


def alto_voice(f):
    return f.js(f"(async () => {{ const s = {SESSION}; const alto = s.parts.find((p) => p.templatePartKey === 'alto'); return JSON.parse(JSON.stringify(s.guideVoices[alto?.id] ?? null)); }})()")


def journey(f):
    f.go("/?api=mock&scenario=&drafts=1", "document.querySelector('h1')")
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !{SESSION}.preparing", what="the guides ready")
    voice = alto_voice(f)
    assert voice and voice["kind"] == "real" and voice["credit"] == "Sarah K.", voice
    f.wait_text(f.js(f"{I18N}.t('studio.badge.realVoiceBy', {{ name: 'Sarah K.' }})"))
    note = f.js(f"{I18N}.t('studio.realVoicesNew', {{ parts: {I18N}.formatList([{I18N}.t('parts.alto'), {I18N}.t('parts.bass')]), count: 2 }})")
    assert note not in f.js("document.body.innerText"), "a new project shouldn't announce its voices"
    f.shot("real-voice")
    # An existing project that had the synth: one note names the parts that now have real voices.
    f.run(f"const s = {SESSION}; s.project.practice.voicesSeen = []; await (await import('/js/stores/session.js')).persist(async () => {{}});")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.wait_text(note)
    # The practice voice instead, then back, from the part's settings.
    alto_name = f.js(f"{I18N}.t('parts.alto')")
    f.run(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({alto_name!r})).click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[role=dialog] fieldset')", what="the guide voice choice")
    f.shot("voice-choice")
    f.run("[...document.querySelectorAll('[role=dialog] fieldset input')][1].click()")
    f.wait(f"(async () => {{ const s = {SESSION}; const alto = s.parts.find((p) => p.templatePartKey === 'alto'); return s.guideVoices[alto.id].why === 'chosen'; }})()", what="the practice voice chosen")
    f.run("[...document.querySelectorAll('[role=dialog] fieldset input')][0].click()")
    f.wait(f"(async () => {{ const s = {SESSION}; const alto = s.parts.find((p) => p.templatePartKey === 'alto'); return s.guideVoices[alto.id].kind === 'real'; }})()", what="the real voice again")
    f.run("document.querySelector('[role=dialog] header button').click()")
    # Another key: the practice voice, with the kind note, whether G suits the singer's range (a
    # wide one, from a range check), and the way back. The part says it's the practice voice in
    # your key, never "synth".
    f.run(f"await (await import('/js/stores/learning.js')).setRange(45, 76); const s = {SESSION}; s.project.practice.focus = s.parts.find((p) => p.templatePartKey === 'alto').id; s.project.transpose = 2; await (await import('/js/stores/session.js')).changeKey();")
    f.wait_text(f.js(f"{I18N}.t('studio.singInOriginal', {{ key: 'G' }})"))
    f.wait_text(f.js(f"{I18N}.t('studio.originalFits', {{ key: 'G' }})"))
    f.wait_text(f.js(f"{I18N}.t('studio.badge.practiceInKey')"))
    f.shot("other-key")
    # Sing says the same (the whole song, with the alto chosen).
    f.run(f"const s = {SESSION}; s.project.practice.sing = {{ way: 'whole', view: 'words' }}; await (await import('/js/stores/session.js')).persist(async () => {{}});")
    f.go(f"/p/{project}/sing", "document.querySelector('header')")
    f.wait_text(f.js(f"{I18N}.t('studio.singInOriginal', {{ key: 'G' }})"))
    f.shot("other-key-sing")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.wait_text(f.js(f"{I18N}.t('studio.singInOriginal', {{ key: 'G' }})"))
    f.click_t("studio.singInOriginal", {"key": "G"})
    f.wait(f"(async () => {{ const s = {SESSION}; const alto = s.parts.find((p) => p.templatePartKey === 'alto'); return s.project.transpose === 0 && s.guideVoices[alto.id].kind === 'real'; }})()", what="back in G with the real voice")
    f.js(f"(async () => {{ const s = await import('/js/stores/projects.js'); await s.loadProjects(); await s.deleteProject('{project}'); }})()")


if __name__ == "__main__":
    run("real_voices", journey)
