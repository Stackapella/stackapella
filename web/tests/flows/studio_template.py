"""The studio with a template project, end to end: play, a part's Sing (practising is Sing's: the
studio's practice bar is gone) and the studio mixing every part whatever Sing's mode, recording the
next part over loopback (with Undo), a new key, fewer singers, and the state surviving a reload.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"
GUIDE_AUDIBLE = "[...{a}.engine.parts.entries()].filter(([id, p]) => id.endsWith(':guide') && p.mix.audible).length".format(a=AUDIO)


def parts_in_db(f, project):
    return f.js(f"(await (await import('/js/db.js')).allBy('parts', 'projectId', '{project}')).sort((a, b) => a.order - b.order).map((p) => ({{ key: p.templatePartKey, frames: p.frames }}))")


def project_in_db(f, project):
    return f.js(f"(await (await import('/js/db.js')).get('projects', '{project}'))")


def journey(f):
    f.go("/", "document.querySelector('h1')")
    f.mic_checked()  # the mic check (5.1) has its own journeys
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")

    # Play, then pause where it is.
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.positionInfo()?.position > 1", what="playback past 1 s")
    f.wait(f"{GUIDE_AUDIBLE} === 4", what="all four guides heard with no part in focus")
    f.click_t("studio.pause")
    f.wait(f"!{AUDIO}.engine.playing", what="paused")
    f.shot("played")

    # Practising a part is Sing's: the row opens in place, and Learn it in Sing opens it, focused.
    soprano = f.js("(await import('/js/i18n.js')).t('parts.soprano')")
    f.run(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({soprano!r})).click()")
    f.wait("document.querySelector('button[aria-expanded=true]')", what="the soprano's row open")
    f.shot("row-open")
    f.click_t("studio.row.learn")
    f.wait(f"location.pathname === '/p/{project}/sing' && (await import('/js/stores/session.js')).session.focusPart?.templatePartKey === 'soprano'", what="Sing, on the soprano")
    # Sing's "On your own" doesn't follow into the studio: the studio plays every part.
    f.run("const s = await import('/js/stores/session.js'); s.setMode('own');")
    f.wait(f"(await (await import('/js/db.js')).get('projects', '{project}')).practice.mode === 'own'", what="Sing's mode saved")
    f.run("history.back()")
    f.wait(f"location.pathname === '/p/{project}' && document.querySelector('footer')", what="back in the studio")
    f.click_t("studio.play")
    f.wait(f"{AUDIO}.engine.playing && {GUIDE_AUDIBLE} === 4", what="the studio mixes every part")
    f.click_t("studio.pause")
    # Export's Piano chords underneath adds the song's chords itself, with Sing's chords off (31's catch).
    if f.size == "desktop":
        energy = f.js("""(async () => {
            const s = await import('/js/stores/session.js');
            s.session.practice.chords = false;
            const sum = (r) => r.channels[0].reduce((total, v) => total + v * v, 0);
            return [sum(await s.renderMix({ chords: false })), sum(await s.renderMix({ chords: true }))];
        })()""")
        assert energy[1] > energy[0] * 1.02, f"the chords added nothing: {energy}"

    # Record: a song's next part without a take (the soprano) over loopback, then undo it.
    f.click_t("studio.recordNamed", {"name": soprano})
    f.wait("document.querySelector('[data-headphones]')", what="the headphones question (102.png)")
    f.shot("headphones")
    f.click_t("headphones.start")
    f.wait("document.querySelector('[data-timing]')", what="the timing check (101.png)")
    f.shot("timing")
    f.click_t("timing.skip")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 2", what="2 s recorded")
    f.click_t("studio.stopRecording")
    next_name = f.js("(await import('/js/i18n.js')).t('parts.bass')")
    f.wait_text(f.js(f"(await import('/js/i18n.js')).t('studio.recordedNext', {{ name: '{soprano}', next: '{next_name}' }})"))
    f.wait(f"(await (await import('/js/db.js')).allBy('parts', 'projectId', '{project}')).some((p) => p.templatePartKey === 'soprano' && p.frames > 0)", what="the soprano take saved")
    f.shot("recorded")
    f.click_t("common.undo")
    f.wait(f"(await (await import('/js/db.js')).allBy('parts', 'projectId', '{project}')).every((p) => p.frames === 0)", what="the take undone")

    # A new key, then two singers.
    f.js("document.querySelector('#song-name').nextElementSibling.click()")
    f.wait("document.querySelector('[role=dialog] form')", what="project settings")
    f.click_t("project.upSemitone")
    f.click(f.js("(await import('/js/i18n.js')).t('common.save')"))
    f.wait(f"(await (await import('/js/db.js')).get('projects', '{project}')).key === 'Ab'", what="the key saved as Ab")
    f.js("document.querySelector('#song-name').nextElementSibling.click()")
    f.wait("document.querySelector('[role=dialog] form')", what="project settings")
    f.js("[...document.querySelectorAll('[role=dialog] [role=radio]')].find((b) => b.textContent.trim() === '2').click()")
    f.click(f.js("(await import('/js/i18n.js')).t('common.save')"))
    f.wait(f"(await (await import('/js/db.js')).allBy('parts', 'projectId', '{project}')).length === 2", what="two parts left")
    f.shot("two-singers")

    # The settings, and Sing's practice state, come back after a reload.
    f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 1")
    saved = project_in_db(f, project)
    assert saved["practice"]["focus"] and saved["singers"] == 2 and saved["transpose"] == 1, saved["practice"]
    f.js(f"(async () => {{ const s = await import('/js/stores/projects.js'); await s.loadProjects(); await s.deleteProject('{project}'); }})()")


if __name__ == "__main__":
    run("studio_template", journey)
