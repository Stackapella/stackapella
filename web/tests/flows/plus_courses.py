"""The Plus courses (docs/plus-courses-build-plan.md, section 2): hidden from a member before launch
(no band on /learn, the course page and its lessons not here); shown to staff, with the More courses
band, the course page (Up next, the map) and every lesson open; once the feature reaches a free
account, lesson 1 is open and lesson 2 says it's part of Plus. A finished lesson is kept in the
course's own place (never in the free course's), and Restart this course asks first, then clears
only that course. With OUT=<dir> it also shoots the band, the course page and lesson 1 in Warm
light and Stage dark at both sizes (help_shots.py's atlas mode):

    OUT=~/Code/shots-review/plus-courses PORT=8297 tool/flows.sh plus_courses
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import OUT, capture, compress, light_first, shoot, theme  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
STORE = "(await import('/js/stores/learning.js'))"
ACCOUNT = "(await import('/js/stores/account.js')).account"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def picture(f, shot_id, scroll="window.scrollTo(0, 0)"):
    if OUT:
        light_first(f)
        shoot(f, shot_id, scroll=scroll)  # Warm light (the atlas's one look)
        theme(f, "dark", "stage")
        f.wait("document.documentElement.dataset.theme === 'stage-dark'", what=f"Stage dark for {shot_id}")
        capture(f, shot_id + "-dark", "", scroll)
        light_first(f)
    else:
        f.shot(shot_id)


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")

    # A member, before launch: nothing shows, and the addresses read as not here.
    f.go("/learn", "document.querySelector('[data-course-map]')")
    assert not f.js("!!document.querySelector('[data-more-courses]')"), "a member sees no Plus course before launch"
    f.go("/learn/c/vibrato")
    f.wait_text(t(f, "courses.notHere"))
    f.go("/learn/vib-1-laser")
    f.wait_text(t(f, "course.noLesson"))

    # Staff: the band, with Vibrato and not a course still coming soon (whichever the catalogue has).
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.go("/learn", "document.querySelector('[data-more-courses] [data-course=vibrato]')")
    soon = f.js("(await import('/js/stores/learning.js')).learning.catalogue.find((c) => c.status === 'soon')?.id ?? null")
    assert soon, "the catalogue has a course still coming soon to check"
    assert not f.js(f"!!document.querySelector('[data-course={soon}]')"), f"a course coming soon ({soon}) stays hidden"
    f.wait_text(t(f, "courses.preview"))
    f.no_sideways("the Learn hub with More courses")
    picture(f, "plus-courses-band", "document.querySelector('[data-more-courses]').scrollIntoView({ block: 'start' }); window.scrollBy(0, -16)")
    f.click(t(f, "courses.start"), "[data-more-courses] [data-course=vibrato] a")  # other courses may be live too
    f.wait("location.pathname === '/learn/c/vibrato'", what="the course page")
    f.wait("document.querySelector('[data-up-next]') && document.querySelector('[data-course-map]')", what="Up next and the map")
    # About this course: the course's own page on the site.
    assert f.js("document.querySelector('[data-course-about]')?.getAttribute('href') ?? ''").endswith("/courses/vibrato"), "About this course isn't the site's page"
    f.wait_text("Swing your voice")
    f.no_sideways("the course page")
    picture(f, "plus-course-page")

    # Staff try every lesson before launch.
    f.go("/learn/vib-1-laser", "document.querySelector('h1')")
    f.wait_text("A straight tone is how chords ring")
    f.go("/learn/vib-1-swing", "document.querySelector('h1')")
    f.wait_text("You're about to see your voice draw a wave")
    f.no_sideways("lesson 1")
    picture(f, "plus-course-lesson-1")

    # The trace lane (A calm, straight line): over loopback the mic hears the drone, a perfectly
    # straight note on the learner's own, so the take is scored straight, with Hear yourself.
    f.mic_checked()
    f.go("/learn/vib-1-laser", "document.querySelector('h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")  # after the page loads: a reload starts the engine afresh
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-wave-lane] canvas')", what="the trace lane")
    f.no_sideways("the trace lane")
    picture(f, "plus-course-wave-lane")
    f.click_t("wave.sing")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the take")
    f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=40, what="Hear yourself, with what was measured")
    line = f.js("document.querySelector('[data-wave-line]').textContent")
    assert "Straight for" in line, f"the measured line: {line}"
    assert f.js("!document.querySelector('.sticky button:last-child').disabled"), "Continue once it's sung"
    picture(f, "plus-course-wave-result")
    # Lesson memory: a later step plays an earlier step's take beneath it (`under`), within the visit.
    f.run("""const { learning } = await import('/js/stores/learning.js');
        const lesson = learning.courses.vibrato.units[0].lessons.find((l) => l.id === 'vib-1-laser');
        lesson.steps.splice(2, 0, { kind: 'wave', text: { en: 'With yourself underneath.' }, exercise: { tempo: 60, lead: 2, step: 0, lyric: 'oo', drone: { step: 0 }, under: { step: 1 }, cue: true, score: 'lock', target: [{ seconds: 4, kind: 'straight' }] } });""")
    f.click_t("course.continue")
    f.wait_text("With yourself underneath.")
    f.click_t("wave.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording over the earlier take")
    assert f.js(f"{AUDIO}.engine.parts.has('ex:under')"), "the earlier take plays beneath"
    f.wait("document.querySelector('[data-wave-cue]')", what="the lock cue's line while singing")
    f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=40, what="the second take's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")

    # Listen and choose (Hear the difference): hear a clip, answer each round, a why after each,
    # then the total; the lesson's stars come from the rounds heard right first time.
    f.go("/learn/vib-1-hear", "document.querySelector('h1')")
    f.click_t("course.continue")
    f.wait("document.querySelector('[data-choose] [data-clip]')", what="the first round's clips")
    f.run("document.querySelector('[data-clip=\"0\"] button').click()")  # hear clip A
    f.wait(f"!!{AUDIO}.engine.ctx", what="the audio context")
    f.no_sideways("listen and choose")
    assert f.js("document.querySelector('[data-go-on-hint]')?.textContent") == t(f, "choose.toFinish"), "a listening step's own hint, never 'Sing it once'"
    picture(f, "plus-course-choose")
    answers = f.js("(async () => { const { learning } = await import('/js/stores/learning.js'); const l = learning.courses.vibrato.units[0].lessons.find((x) => x.id === 'vib-1-hear'); return l.steps[1].exercise.rounds.map((r) => ({ answer: r.answer, words: !!r.options })); })()")
    for i, r in enumerate(answers):
        if r["words"]:
            f.run(f"document.querySelectorAll('[data-choose] [role=group] button')[{r['answer']}].click()")
        else:
            f.run(f"document.querySelector('[data-clip=\"{r['answer']}\"] .btn-secondary').click()")
        f.wait("document.querySelector('[data-choose-why]')", what=f"round {i + 1}'s why")
        if i == 0:
            f.wait_text(t(f, "choose.right"))
            picture(f, "plus-course-choose-why")
        f.click_t("choose.next" if i + 1 < len(answers) else "choose.finish")
    f.wait_text(t(f, "choose.score", {"right": len(answers), "total": len(answers)}))
    f.click_t("course.finish")
    f.wait("document.querySelector('#done-title') || document.querySelector('[data-lesson-end]') || location.pathname !== '/learn/vib-1-hear'", what="the lesson's end")
    stars = f.js("(await import('/js/stores/learning.js')).learning.progress.courses?.vibrato?.lessons?.['vib-1-hear']?.stars ?? 0")
    assert stars == 3, f"every round right: three stars ({stars})"

    # Self-check (no score): a lesson of one self-check step, put into the course in memory. Hear the
    # model, record, compare, answer; it says it isn't scored, and the lesson's stars are for practising.
    f.run("""const { learning } = await import('/js/stores/learning.js');
        const lesson = learning.courses.vibrato.units[0].lessons.find((l) => l.id === 'vib-1-easy');
        lesson.steps = [{ kind: 'selfcheck', text: { en: 'Sing it like the model, then check.' }, exercise: { tempo: 60, model: { notes: [{ beat: 0, length: 3, step: 0 }], drone: { step: 0 } }, questions: [{ en: 'Did your jaw stay loose?' }, { en: 'Did it sound like the model?' }] } }];
        (await import('/js/router.js')).navigate('/learn/vib-1-easy');""")
    f.wait("document.querySelector('[data-selfcheck]')", what="the self-check")
    f.wait_text(t(f, "selfcheck.honest"))
    f.click("Record", "[data-selfcheck-record]")
    f.wait("document.querySelector('[data-selfcheck-questions]')", timeout=30, what="the questions, once recorded")
    assert f.js("!document.querySelector('[data-selfcheck-yours]').disabled"), "yours to hear back"
    f.no_sideways("the self-check")
    f.run("document.querySelectorAll('[data-selfcheck-questions] [role=radio]')[0].click(); document.querySelectorAll('[data-selfcheck-questions] [role=radio]')[3].click();")
    f.wait_text(t(f, "selfcheck.notYetLine"))
    picture(f, "plus-course-selfcheck")
    f.click_t("course.finish")
    f.wait(f"({STORE}.learning.progress.courses?.vibrato?.lessons?.['vib-1-easy']?.stars ?? 0) === 3", what="stars for practising")
    f.js(f"{STORE}.restartPlusCourse('vibrato')")  # a clean course for the checks below

    # Play steps share the lesson memory too: the second sings over the first's take (`under`), and a
    # step built on the flip note says so until the course has found it, then uses it.
    f.run("""const { learning } = await import('/js/stores/learning.js');
        const lesson = learning.courses.vibrato.units[0].lessons.find((l) => l.id === 'vib-1-swing');
        const play = (extra) => ({ kind: 'play', text: { en: 'Hold it.' }, exercise: { tempo: 60, notes: [{ beat: 2, length: 3, step: 0, lyric: 'oo' }], turn: false, drone: { step: 0 }, ...extra } });
        lesson.steps = [play({}), play({ under: { step: 0 } }), { kind: 'wave', text: { en: 'From your flip.' }, exercise: { tempo: 60, lead: 2, step: 0, base: 'flip', lyric: 'oo', drone: { step: 0 }, target: [{ seconds: 3, kind: 'straight' }] } }];
        (await import('/js/router.js')).navigate('/learn/vib-1-swing');""")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    for i in range(2):
        f.wait("document.querySelector('.lane-stage')", what=f"play step {i + 1}")
        f.click_t("course.tap.sing")
        f.wait(f"{AUDIO}.engine.playing?.recording", what=f"recording step {i + 1}")
        if i == 1:
            assert f.js(f"{AUDIO}.engine.parts.has('ex:under')"), "a play step sings over the earlier take"
        f.wait(f"!{AUDIO}.engine.playing", timeout=30, what=f"step {i + 1} done")
        f.wait("!document.querySelector('.sticky button:last-child').disabled", timeout=30, what=f"step {i + 1} scored")
        f.click_t("course.continue")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.wait("document.querySelector('[data-needs-flip]')", what="the flip step's notice before a flip is known")
    f.js(f"{STORE}.setCoursePersonal('vibrato', {{ flip: 4 }})")
    f.wait("!document.querySelector('[data-needs-flip]')", what="the notice gone once the flip is kept")
    assert f.js(f"{STORE}.learning.progress.courses.vibrato.personal.flip") == 4, "the flip kept with the course"
    f.js(f"{STORE}.restartPlusCourse('vibrato')")
    f.run("(await import('/js/storage.js')).session.set('stackapella.lesson.vib-1-swing', '');")  # the tab's place in it

    # Then and now: a step with `keep` saves its first take on the device; a later one with
    # `thenAndNow` shows it beside today's (over loopback, both the straight drone).
    f.run("(await import('/js/router.js')).navigate('/learn');")  # off the lesson before changing it
    f.wait("location.pathname === '/learn'", what="away from the lesson")
    f.run("""const db = await import('/js/db.js'); await db.remove('learning', 'course:flow.first'); await db.remove('audio', 'course:flow.first');
        const { learning } = await import('/js/stores/learning.js');
        const lesson = learning.courses.vibrato.units[0].lessons.find((l) => l.id === 'vib-1-swing');
        const lane = (extra) => ({ kind: 'wave', text: { en: 'Hold it.' }, exercise: { tempo: 60, lead: 2, step: 0, lyric: 'oo', drone: { step: 0 }, target: [{ seconds: 3, kind: 'straight' }], ...extra } });
        lesson.steps = [lane({ keep: 'flow.first' }), lane({ thenAndNow: 'flow.first' })];
        (await import('/js/storage.js')).session.set('stackapella.lesson.vib-1-swing', '');""")
    f.run("(await import('/js/router.js')).navigate('/learn/vib-1-swing');")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    for i in range(2):
        f.wait("document.querySelector('[data-wave-lane] canvas')", what=f"lane {i + 1}")
        f.click_t("wave.sing")
        f.wait("document.querySelector('[data-wave-result] [data-wave-line]')", timeout=40, what=f"lane {i + 1}'s result")
        if i == 0:
            f.click_t("course.continue")
    f.wait("document.querySelectorAll('[data-then-and-now] [data-trace]').length === 2", what="then and now, both drawn")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    picture(f, "plus-course-then-and-now", "document.querySelector('[data-then-and-now]').scrollIntoView({ block: 'center' })")
    f.js(f"{STORE}.restartPlusCourse('vibrato')")
    f.run("(await import('/js/storage.js')).session.set('stackapella.lesson.vib-1-swing', '');")

    # A lesson finished is kept in the course's own place, never in the free course's.
    free_before = f.js(f"JSON.stringify({STORE}.learning.progress.lessons)")
    f.js(f"{STORE}.finishLesson('vib-1-swing', {{ percent: 95, stars: 3 }})")
    kept = f.js(f"{STORE}.learning.progress.courses?.vibrato?.lessons?.['vib-1-swing']?.stars ?? 0")
    assert kept == 3, f"kept in progress.courses: {kept}"
    assert f.js(f"JSON.stringify({STORE}.learning.progress.lessons)") == free_before, "the free course's lessons are untouched"
    f.go("/learn/c/vibrato", "document.querySelector('[data-up-next]')")
    f.wait_text("The easy long note")  # Up next moved on
    # The site links a course by its slug, which may differ from its id: perfect-your-pitch is pitch.
    f.go("/learn/c/perfect-your-pitch", "document.querySelector('[data-up-next]') || document.querySelector('main h1')")
    f.wait("document.querySelector('[data-up-next]')", what="the course found by its slug")
    f.go("/learn/c/vibrato", "document.querySelector('[data-up-next]')")

    # Restart this course: a styled confirm naming it, then only its lessons go.
    f.click_t("courses.restart")
    f.wait_text(t(f, "courses.restartTitle", {"course": "Vibrato"}))
    f.click(t(f, "courses.restartAction", {"course": "Vibrato"}))
    f.wait_text(t(f, "courses.restarted", {"course": "Vibrato"}))
    assert f.js(f"!{STORE}.learning.progress.courses?.vibrato"), "the course's lessons went"
    f.wait_text("Swing your voice")

    # After launch, a free account: lesson 1 open, lesson 2 part of Plus.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"!!{ACCOUNT}.me", what="the member's account")
    f.js(f"{ACCOUNT}.me.plusCourses = true")
    f.wait("document.querySelector('[data-more-courses] [data-course=vibrato]')", what="the band once the feature is on")
    f.wait_text(t(f, "courses.tryFree"))
    f.run(f"(await import('/js/router.js')).navigate('/learn/vib-1-swing')")
    f.wait_text("You're about to see your voice draw a wave")
    f.run(f"(await import('/js/router.js')).navigate('/learn/vib-1-easy')")
    f.wait_text(t(f, "courses.plusOnly"))
    compress()


if __name__ == "__main__":
    run("plus_courses", journey)
