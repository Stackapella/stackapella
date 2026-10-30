"""The user guide's screenshots (docs/user-guide-shots.md): 22 screens of the real app on the mock
API, each in Warm light and Stage dark, written to icons/help/ as <id>.png, <id>-dark.png and the
same in webp (the guide shows a shot once all four are there). Phone shots are 390 x 844 at 2x,
desktop ones 1440 x 900 at 1x. Singing is a synthesized voice played into the mic (loopback).

    PORT=8410 tool/flows.sh help_shots
    ONLY=studio--trim,settings--page PORT=8410 tool/flows.sh help_shots   # some of them

Each screen is shot in Warm light, then the theme changes in place (the app redraws its canvases
on a change) and it's shot in Stage dark, so a moment that can't be had twice (a held note, a take
recording) is the same in both. Such a moment is held still by holding back the page's animation
frames. FLOW_SKINS=serious shoots serious mode's own set, as <id>-serious and <id>-serious-dark. Pillow compresses the shots at the end, through a plain python3 (this script runs isolated).
"""
import base64
import json
import os
import subprocess
import sys
import tempfile
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

HELP = Path(__file__).resolve().parents[2] / "icons" / "help"
I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
SESSION = "(await import('/js/stores/session.js'))"
ONLY = [s for s in os.environ.get("ONLY", "").split(",") if s]
# Shots that wait for the app.
SKIPPED = {}
# Warm light, then Stage dark: (mode, colour, file suffix, data-theme).
THEMES = (("light", "warm", "", "warm-light"), ("dark", "stage", "-dark", "stage-dark"))
# An atlas for review (OUT=<dir>): Warm light only (and Stage dark too with OUT_DARK=1), every
# screen at both sizes, as <id>-<size>.png there; the guide's own files (icons/help) are left alone.
OUT = Path(os.environ["OUT"]).expanduser() if os.environ.get("OUT") else None
if OUT and not os.environ.get("OUT_DARK"):
    THEMES = THEMES[:1]
VIEWPORT = {"phone": (390, 844), "desktop": (1440, 900)}
PIXELS = {"phone": (780, 1688), "desktop": (1440, 900)}
RAW = []  # (raw png, output base, width, height), compressed at the end

COMPRESS = r"""
import json, os, sys
from PIL import Image
for raw, base, width, height in json.load(sys.stdin):
    image = Image.open(raw).convert("RGB")
    if image.size != (width, height):
        sys.exit(f"{base}: {image.size}, not {width} x {height}")
    # The png is the fallback and the full-size link: a 256-colour palette keeps it a third the size.
    image.convert("RGB").quantize(colors=256, method=Image.Quantize.MEDIANCUT, dither=Image.Dither.NONE).save(base + ".png", optimize=True)
    image.save(base + ".webp", quality=82, method=6)
    os.unlink(raw)
"""

# Holding the page still: animation frames asked for once `window.__freezeWhen()` is true are kept
# back (the audio goes on) until thaw() hands them back.
FREEZE = """if (!window.__heldFrames) {
    window.__heldFrames = []; const raf = window.requestAnimationFrame.bind(window); window.__raf = raf;
    window.requestAnimationFrame = (cb) => {
        if (!window.__frozen && window.__freezeWhen) { try { window.__frozen = !!window.__freezeWhen(); } catch (e) { window.__frozen = false; } }
        if (window.__frozen) { window.__heldFrames.push(cb); return 0; }
        return raf(cb);
    };
}"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def choose_part(f, key):
    """Which part is yours?, when Sing asks; with a range known it picks one itself, then it's set to `key`."""
    question = t(f, "sing.chooser.yourPart")
    f.wait(f"document.body.innerText.includes({question!r}) || {SESSION}.session.focusPart", what="the part question, or a part chosen")
    if f.js(f"document.body.innerText.includes({question!r})"):
        name = t(f, key)
        f.run(f"[...document.querySelectorAll('main button, .card-interactive')].find((b) => b.textContent.includes({name!r})).click()")
    else:
        f.run(f"const s = {SESSION}; s.practise(s.session.parts.find((p) => p.templatePartKey === '{key.split('.')[-1]}'));")


def wanted(shot_id):
    return (not ONLY or shot_id in ONLY) and shot_id not in SKIPPED


def freeze_when(f, condition, timeout=30, what=None):
    """Holds the page still the first frame `condition` (JS) is true; waits until it is."""
    f.run(FREEZE + f"window.__frozen = false; window.__freezeWhen = () => ({condition});")
    f.wait("window.__frozen", timeout, what or condition)


def thaw(f):
    f.run("window.__freezeWhen = null; window.__frozen = false; for (const cb of (window.__heldFrames ?? []).splice(0)) window.__raf(cb);")


def theme(f, mode, colour):
    """The look, as a person's choice (kept here and in the shared cookie), shown at once, in the
    flow's own skin (FLOW_SKINS): game mode keeps its own light or dark (`gameMode`)."""
    skin = getattr(f, "skin", "game")
    # The mock account's skin too: the account's choice wins when it loads.
    f.run(f"sessionStorage.setItem('stackapella.mockAppearance', JSON.stringify({{ ...JSON.parse(sessionStorage.getItem('stackapella.mockAppearance') || '{{}}'), skin: {json.dumps(skin)} }})); window.stackapellaTheme.choose({{ mode: {json.dumps(mode)}, gameMode: {json.dumps(mode)}, colour: {json.dumps(colour)}, accent: null, customAccent: null, skin: {json.dumps(skin)} }});")


def capture(f, shot_id, suffix, scroll=None, wait=0.6):
    """Fonts in, no caret, no toasts, animations done, scrolled where the list says; then the
    viewport at its exact pixel size."""
    f.js("document.fonts.ready.then(() => true)")
    f.run("""if (!document.querySelector('#help-shot-style')) { const s = document.createElement('style'); s.id = 'help-shot-style';
        s.textContent = '*, *::before, *::after { caret-color: transparent !important; scrollbar-width: none !important; } ::-webkit-scrollbar { display: none !important; }'; document.head.append(s); }
        if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur?.();
        (await import('/js/components/ui.js')).toasts.list.splice(0);""")
    if scroll:
        f.run(scroll)
    time.sleep(wait)
    width, height = VIEWPORT[f.size]
    view = f.chrome.send("Page.getLayoutMetrics")["cssVisualViewport"]
    data = f.chrome.send("Page.captureScreenshot", {"format": "png", "captureBeyondViewport": False,
                                                    "clip": {"x": view["pageX"], "y": view["pageY"], "width": width, "height": height, "scale": 1}})["data"]
    raw = tempfile.NamedTemporaryFile(suffix=".png", delete=False)
    raw.write(base64.b64decode(data))
    raw.close()
    # Serious mode's own pair beside game mode's (FLOW_SKINS=serious): <id>-serious and <id>-serious-dark.
    skin = "-serious" if getattr(f, "skin", "game") == "serious" else ""
    RAW.append([raw.name, str(OUT / f"{shot_id}-{f.size}{skin}{suffix}" if OUT else HELP / f"{shot_id}{skin}{suffix}"), *PIXELS[f.size]])


def shoot(f, shot_id, scroll=None, wait=0.6):
    """The screen as it is, in Warm light and then Stage dark."""
    for mode, colour, suffix, look in THEMES:
        theme(f, mode, colour)
        f.wait(f"document.documentElement.dataset.theme === {json.dumps(look)}", what=f"{look} for {shot_id}")
        capture(f, shot_id, suffix, scroll, wait)
    print(f"     shot {shot_id}")


def compress():
    if not RAW:
        return
    (OUT or HELP).mkdir(parents=True, exist_ok=True)
    done = subprocess.run(["python3", "-c", COMPRESS], input=json.dumps(RAW), capture_output=True, text=True,
                          env={k: v for k, v in os.environ.items() if not k.startswith("PYTHON")})
    if done.returncode:
        raise AssertionError(f"compressing: {done.stderr.strip()[-400:]}")
    RAW.clear()


def light_first(f):
    theme(f, "light", "warm")


# Setting things up -------------------------------------------------------------------------------

def home_page(f, who):
    f.go(f"/?api=mock&scenario=&as={who}", "document.querySelector('#app header')")
    f.wait("document.querySelector('#lead-title') && document.querySelector('section[aria-labelledby^=band-]')", what="the home")


def progress(f, lessons, xp, days=0, extra=""):
    """Course progress on this device, as if earned (read quietly on the next load): `lessons` with
    their stars, `xp`, and `days` practised this week (today and the days before it, in this week)."""
    f.run(f"""const {{ emptyProgress, dayOf, weekOf }} = await import('/js/course.js'); const db = await import('/js/db.js');
        const practice = {{}}; const today = new Date();
        for (let i = 0; Object.keys(practice).length < {days} && i < 7; i++) {{ const d = new Date(today); d.setDate(d.getDate() - i); if (weekOf(dayOf(d.getTime())) === weekOf(dayOf())) practice[dayOf(d.getTime())] = 600; }}
        await db.put('learning', {{ ...emptyProgress(), startedAt: Date.now() - 6 * 86400000, lessons: {json.dumps(lessons)}, xp: {{ total: {xp}, days: {{}} }}, practice {extra} }}, 'progress');""")


def clear_projects(f):
    f.run("const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of [...s.projects.all]) await s.deleteProject(p.id);")


def new_song(f, template="amazing-grace"):
    return f.js(f"""(async () => {{
        const {{ startFromTemplate }} = await import('/js/stores/projects.js');
        const {{ loadTemplate }} = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('{template}'), {{ singers: 4 }})).id;
    }})()""")


def sung_takes(f, keys, pad_seconds=0.6):
    """Takes for the song's parts `keys`, sung by the test's synthesized singer (tests/fixtures.js)
    with a little vibrato, as a recording keeps them (the session must be open). The guides are
    the practice voice: the mock's real voices are a 3-second fixture."""
    f.js(f"{AUDIO}.unlock()")
    f.run(f"const s = {SESSION}; for (const p of s.session.parts.filter((p) => p.templatePartKey)) await s.setVoice(p, 'synth');")
    f.run(f"""const {{ sing }} = await import('/tests/fixtures.js'); const {{ peaks }} = await import('/js/dsp.js'); const s = {SESSION};
        for (const key of {json.dumps(keys)}) {{
            const part = s.session.parts.find((p) => p.templatePartKey === key); const sr = 48000;
            const sung = sing(sr, {{ notes: s.templatePart(part).guide.notes, bpm: s.session.project.bpm, vibrato: {{ hz: 5.5, cents: 14 }}, level: 0.28, noise: 0.002, seed: key.length }});
            const pad = Math.round({pad_seconds} * sr); const take = new Float32Array(pad + sung.length + pad); take.set(sung, pad);
            await s.storeTake(part, {{ offsetSeconds: -{pad_seconds}, nudgeSeconds: 0, trimStartFrames: 0, trimEndFrames: 0, recordedAt: Date.now() - 3600000, score: null, scoring: null,
                sampleRate: sr, frames: take.length, framesPerPeak: 512, peaks: peaks(take, 512) }}, take);
        }}""")


def into_part_by_part(f, project, part="parts.alto"):
    f.go(f"/p/{project}/sing", "document.querySelector('h1')")
    f.wait_text(t(f, "sing.chooser.title"))
    f.run("document.querySelector(\'[data-way=parts]\').click()")  # the way starts at once
    choose_part(f, part)
    f.wait_text(t(f, "sing.coach.skipToRecord"))


def recording_starts(f):
    """The timing check, if it asks (skipped: an estimate), then recording."""
    f.wait(f"document.querySelector('[role=dialog]') || {AUDIO}.engine.playing?.recording", timeout=20, what="the setup or recording")
    for _ in range(3):
        if f.js(f"!!{AUDIO}.engine.playing?.recording"):
            break
        if f.js(f"document.body.innerText.includes({I18N}.t('headphones.start'))"):
            f.click_t("headphones.start")
        elif f.js("!!document.querySelector('[role=dialog] select')"):
            f.click_t("timing.skip")
        f.wait(f"{AUDIO}.engine.playing?.recording || document.querySelector('[role=dialog] select') || document.body.innerText.includes({I18N}.t('headphones.start'))", timeout=20, what="the next setup step")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")


def celebration_over(f):
    f.wait("!document.querySelector('canvas[data-confetti]')", timeout=15, what="the celebration over")


# The phone's shots --------------------------------------------------------------------------------

def getting_started(f):
    # A first visit: nothing on this device yet, and the browser's own install offer held back.
    home_page(f, "signedOut")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    if wanted("getting-started--home"):
        light_first(f)
        home_page(f, "signedOut")
        f.wait("document.querySelector('#home-songs > li')", what="the songs")
        shoot(f, "getting-started--home")

    if wanted("getting-started--song"):
        light_first(f)
        home_page(f, "signedOut")
        f.wait("document.querySelector('section[aria-labelledby=band-songs] li button:not([data-make])')", what="the songs")
        f.run("[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Amazing Grace')).querySelector('button').click()")
        f.wait("document.querySelector('[role=dialog]')?.innerText.includes('Amazing Grace')", what="the song dialog")
        f.wait_text(t(f, "templates.start"))
        f.run("[...document.querySelectorAll('[role=dialog] [role=radio]')].find((b) => b.textContent.trim() === '4')?.click()")
        f.wait("document.querySelector('[role=dialog] [role=radio][aria-checked=true]')?.textContent.trim() === '4'", what="four singers")
        shoot(f, "getting-started--song")
        f.press("Escape")

    # Lesson 1's note, found by a held A3 sung into the mic (signed out, as a first visit is).
    f.mic_checked()
    if wanted("your-voice--note") or wanted("getting-started--first-note"):
        light_first(f)
        f.go("/learn/1-comfortable?api=mock&as=signedOut", "document.querySelector('main h1')")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.go_on()
        f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening")
        f.js("(await import('/js/creator-audio.js')).hearNotes([{ beat: 0, length: 8, pitch: 57 }], { tempo: 60 })")
        if wanted("your-voice--note"):
            # Holding: zoomed to the octave around it, the ring a little over half way.
            freeze_when(f, """document.querySelector('[data-listening]')?.dataset.phase === 'holding'
                && [...document.querySelectorAll('[data-listening] [translate=no]')].some((s) => s.textContent === 'G3')
                && Number(document.querySelector('[data-listening] circle[stroke-dasharray]')?.getAttribute('stroke-dashoffset')) < 119.4 * 0.5""", what="holding, zoomed in")
            shoot(f, "your-voice--note")
            thaw(f)
        f.wait("document.querySelector('[data-listening]')?.dataset.phase === 'found'", timeout=15, what="the note found")
        assert f.js("document.querySelector('[data-note]').textContent") == "A3", "found another note"
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        if wanted("getting-started--first-note"):
            shoot(f, "getting-started--first-note")


def your_voice(f):
    if wanted("your-voice--map"):
        # Unit 1 done, stars on each lesson; level 2; two of three days this week.
        light_first(f)
        home_page(f, "member")
        progress(f, {"1-comfortable": {"stars": 3, "bestPercent": 95, "at": 0}, "1-match": {"stars": 3, "bestPercent": 92, "at": 0}, "1-high-low": {"stars": 2, "bestPercent": 84, "at": 0},
                     "1-breath": {"stars": 3, "bestPercent": 100, "at": 0}, "1-challenge": {"stars": 2, "bestPercent": 81, "at": 0}}, 180, days=2,
                 extra=", home: 57, range: { low: 55, high: 76 }")
        f.go("/learn?api=mock&as=member", "document.querySelector('h1')")
        f.wait("document.querySelectorAll('main ol button, ol button').length > 5", what="the map")
        shoot(f, "your-voice--map")

    if wanted("your-voice--voice"):
        # A range found (G3 to E5), A3 the comfortable note, and the first lessons sung.
        light_first(f)
        home_page(f, "member")
        progress(f, {"1-comfortable": {"stars": 3, "bestPercent": 95, "at": 0}, "1-match": {"stars": 2, "bestPercent": 84, "at": 0}}, 90, days=1,
                 extra=", home: 57, range: { low: 55, high: 76 }")
        f.go("/voice?api=mock&as=member", "document.querySelector('h1')")
        f.wait_text(t(f, "voice.suits.alto"))
        # The range big, then the keyboard (a phone scrolls it to the range by itself) and the parts' fit.
        shoot(f, "your-voice--voice", scroll="window.scrollTo(0, 0); window.scrollBy(0, document.querySelector('#range-heading').getBoundingClientRect().top - 72);")


def guides_ready(f):
    """The studio's guides made: its passing notice gone, so it isn't in the picture."""
    words = t(f, "studio.preparingGuides")
    f.wait(f"!document.body.innerText.includes({json.dumps(words)})", timeout=30, what="the guides ready")


def lesson_end(f):
    """A unit 2 lesson just finished with 2 stars. Hold a note scores its one note as all or half
    (100% or 50%), so 2 stars can't be sung there: Vowels' three notes can, two on the note and the
    last a little sharp (83%)."""
    if not wanted("your-voice--stars"):
        return
    light_first(f)
    home_page(f, "member")
    unit1 = {lesson: {"stars": 3, "bestPercent": 95, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge")}
    progress(f, {**unit1, "2-hold": {"stars": 3, "bestPercent": 100, "at": 0}, "2-drone": {"stars": 2, "bestPercent": 80, "at": 0}}, 230, days=1,
             extra=", home: 57, range: { low: 55, high: 76 }")
    f.run("sessionStorage.setItem('stackapella.headphones', '1'); sessionStorage.removeItem('stackapella.lesson.2-vowels');")
    f.go("/learn/2-vowels?api=mock&as=member", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait_text(t(f, "course.tap.sing"))
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="the take")
    # The voice comes in on the turn: after the three notes and a beat's gap (7 beats at 72 BPM).
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ sing }} = await import('/tests/fixtures.js'); const sr = engine.sampleRate; const beat = 60 / 72;
        const notes = [0, 2, 4].map((b) => ({{ beat: b, length: 2, pitch: 57 }}));
        const sung = sing(sr, {{ notes, bpm: 72, vibrato: {{ hz: 5.5, cents: 10 }}, cents: (n) => (n.beat === 4 ? 75 : 4) }});
        const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime) + 7 * beat;
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr)); engine.preview(voice, sr);""")
    finish = t(f, "course.finish")
    f.wait(f"[...document.querySelectorAll('main button')].some((b) => b.textContent.trim() === {finish!r} && !b.disabled)", timeout=40, what="the take scored")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.finish")
    f.wait("document.querySelector('[data-lesson-end]')", what="the end screen")
    print(f"     Vowels: {f.js('document.querySelector(\'[data-lesson-end] h1\').textContent')}, {f.js('(await import(\'/js/stores/learning.js\')).learning.progress.lessons[\'2-vowels\'].stars')} stars")
    celebration_over(f)
    time.sleep(1.5)  # the stars land one after another
    # Scrolled so Up next's buttons show, the stars still in view as far as the phone allows.
    shoot(f, "your-voice--stars", scroll="""window.scrollTo(0, 0); const end = document.querySelector('[data-lesson-end] aside').getBoundingClientRect().bottom;
        const stars = document.querySelector('[data-lesson-end]').getBoundingClientRect().top; window.scrollBy(0, Math.max(0, Math.min(end - innerHeight + 16, stars - 8)));""")


def fact_card(f):
    """A lesson's "Did you know?" card on its end (Steps up, unit 3), scrolled to it: Do, Re, Mi sung
    back on the note after the guide's four beats and a beat's gap."""
    if not wanted("your-voice--fact"):
        return
    light_first(f)
    home_page(f, "member")
    done = {lesson: {"stars": 3, "bestPercent": 95, "at": 0} for lesson in ("1-comfortable", "1-match", "1-high-low", "1-breath", "1-challenge", "2-hold", "2-drone", "2-vowels", "2-challenge")}
    progress(f, done, 420, days=2, extra=", home: 57, range: { low: 55, high: 76 }")
    f.run("sessionStorage.setItem('stackapella.headphones', '1'); sessionStorage.removeItem('stackapella.lesson.3-steps-up');")
    # A device that arrived a few days ago: no first session's wrap under Up next.
    f.run("const k = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}'); localStorage.setItem('stackapella.funnel', JSON.stringify({ ...k, firsts: k.firsts ?? {}, arrived: new Date(Date.now() - 3 * 86400000).toISOString().slice(0, 10) }));")
    f.go("/learn/3-steps-up?api=mock&as=member", "document.querySelector('main h1')")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.continue")
    f.wait_text(t(f, "course.tap.sing"))
    f.click_t("course.tap.sing")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="the take")
    f.run(f"""const {{ engine }} = {AUDIO}; const {{ sing }} = await import('/tests/fixtures.js'); const sr = engine.sampleRate; const beat = 60 / 72;
        const notes = [[0, 1, 0], [1, 1, 2], [2, 2, 4]].map(([b, length, step]) => ({{ beat: b, length, pitch: 57 + step }}));
        const sung = sing(sr, {{ notes, bpm: 72, vibrato: {{ hz: 5.5, cents: 10 }}, cents: 4 }});
        const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime) + 5 * beat;
        const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr)); engine.preview(voice, sr);""")
    finish = t(f, "course.finish")
    f.wait(f"[...document.querySelectorAll('main button')].some((b) => b.textContent.trim() === {finish!r} && !b.disabled)", timeout=40, what="the take scored")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.finish")
    f.wait("document.querySelector('[data-lesson-end] [data-fact]') || document.querySelector('[data-fact]')", what="the fact card")
    celebration_over(f)
    time.sleep(1.5)
    # Down to the fact card, but never past the stars: every gem stays whole, with room above it.
    shoot(f, "your-voice--fact", scroll="""window.scrollTo(0, 0); const card = document.querySelector('[data-fact]').getBoundingClientRect();
        const gems = document.querySelector('[data-lesson-end] .gem').parentElement.getBoundingClientRect();
        window.scrollBy(0, Math.max(0, Math.min(card.bottom - innerHeight + 24, gems.top - 24)));""")
    f.run("const k = JSON.parse(localStorage.getItem('stackapella.funnel') || '{}'); delete k.arrived; localStorage.setItem('stackapella.funnel', JSON.stringify(k));")


def learn_a_part(f):
    clear_projects(f)
    if wanted("learn-a-part--steps"):
        # Learning the Alto: Sing along, phrase 1 sung along with, phrase 2 next.
        light_first(f)
        grace = new_song(f)
        into_part_by_part(f, grace)
        f.run(f"""const s = {SESSION}; s.session.practice.progress = {{ alto: {{ step: 'singAlong', phrases: {{ 0: 1 }} }} }}; s.setMode('singAlong'); await s.savePractice();""")
        f.wait_text(t(f, "sing.coach.action.singAlong", {"number": 2}))
        shoot(f, "learn-a-part--steps")

    if wanted("getting-started--recorded") or wanted("learn-a-part--report") or wanted("learn-a-part--recording"):
        # The Alto recorded (a synthesized voice into the mic, a little off on some notes), and
        # How it went worked out: two stars.
        light_first(f)
        clear_projects(f)
        grace = new_song(f)
        f.run("localStorage.setItem('stackapella.noTips', '1'); sessionStorage.setItem('stackapella.headphones', '1');")
        into_part_by_part(f, grace)
        f.click_t("sing.coach.skipToRecord")
        alto = t(f, "parts.alto")
        f.wait_text(t(f, "sing.coach.action.record", {"part": alto}))
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        # The others off in What you hear, so the loopback carries only the voice.
        f.run(f"const s = {SESSION}; s.session.parts.filter((p) => p.templatePartKey !== 'alto').forEach((p) => s.toggleHearing(p.id));")
        f.click_t("sing.coach.action.record", {"part": alto})
        recording_starts(f)
        f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ sing }} = await import('/tests/fixtures.js');
            const alto = s.session.parts.find((p) => p.templatePartKey === 'alto'); const notes = s.templatePart(alto).guide.notes.filter((n) => n.beat < 24);
            const sr = engine.sampleRate; const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime - 0.03);
            const sung = sing(sr, {{ notes, bpm: s.session.project.bpm, vibrato: {{ hz: 5.5, cents: 12 }}, cents: (n) => (notes.indexOf(n) % 4 === 2 ? -75 : -22) }});
            const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr));
            engine.preview(voice, sr);""")
        if wanted("learn-a-part--recording"):
            # The recording stage mid-phrase: everything else stepped aside.
            f.run(f"window.__engine = {AUDIO}.engine;")
            freeze_when(f, "window.__engine.playing && window.__engine.ctx.currentTime > window.__engine.playing.startTime + 9.5", timeout=30, what="the second phrase")
            shoot(f, "learn-a-part--recording")
            thaw(f)
        f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 17.5", timeout=40, what="two phrases sung")
        f.wait("document.querySelector('[data-stop]:not([disabled])')", what="Stop and keep, ready")
        f.run("document.querySelector('[data-stop]').click()")
        f.wait_text(t(f, "sing.recorded.title", {"part": alto}))
        f.wait(f"document.querySelector('[data-take-review] ul[aria-label=\"{t(f, 'report.phrases')}\"] li')", timeout=40, what="the phrases worked out")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        f.run(f"const s = {SESSION}; s.session.parts.forEach((p) => s.updatePart(p, {{ muted: false }}));")
        celebration_over(f)
        if wanted("getting-started--recorded"):
            # Under the header: the tick, the heading, the stack, How it went and the two buttons.
            shoot(f, "getting-started--recorded", scroll="""const top = document.querySelector('section[aria-labelledby=recorded-title]'); top.style.scrollMarginTop = (document.querySelector('header').getBoundingClientRect().bottom + 4) + 'px';
                top.scrollIntoView({ block: 'start' });""")
        if wanted("learn-a-part--report"):
            # The review: the one thing to try, the phrases, and the picked phrase's pitch line under them.
            f.wait("document.querySelector('[data-take-review] svg polyline')", what="the singer's line on the chart")
            shoot(f, "learn-a-part--report", scroll=f"document.querySelector('[data-take-review] ul[aria-label=\"{t(f, 'report.phrases')}\"]').closest('.card').scrollIntoView({{ block: 'start' }})")

    if wanted("learn-a-part--stage"):
        light_first(f)
        sing_whole(f, view="words")
        shoot(f, "learn-a-part--stage")
        thaw(f)
        f.click_t("studio.pause")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
    clear_projects(f)


def sing_whole(f, view):
    """The whole song, On your own, the Alto's turn mid-line: a synthesized alto sung into the mic
    (the others quietened so the mic hears only it), held still once its notes light."""
    clear_projects(f)
    grace = new_song(f)
    f.go(f"/p/{grace}/sing", "document.querySelector('h1')")
    f.run("document.querySelector(\'[data-way=whole]\').click()")  # the way starts at once
    choose_part(f, "parts.alto")
    f.wait("document.querySelector('main')", what="the stage")
    f.wait_text(t(f, "practice.mode.own"))  # the mode chips come once the part is set
    f.click_t("practice.mode.own")
    if view == "notes":
        f.click_t("sing.settings.open")
        f.wait("document.querySelector('[role=dialog]')", what="Settings")
        f.sing_view("notes")
        f.press("Escape")
        f.wait("document.querySelector('main canvas')", what="the notes lane")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    start_beat = 12
    f.run(f"""const s = {SESSION}; for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.updatePart(p, {{ volume: 0.02 }});
        await (await import('/js/audio.js')).openMic(); s.seek({start_beat} * 60 / s.session.project.bpm);""")
    f.click_t("studio.play")
    f.wait_text(t(f, "sing.turn.band"), timeout=10)
    f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ sing }} = await import('/tests/fixtures.js');
        const notes = s.templatePart(s.session.focusPart).guide.notes.filter((n) => n.beat >= {start_beat}).map((n) => ({{ ...n, beat: n.beat - {start_beat} }}));
        const position = s.session.position ?? 0; const ahead = Math.max(0, {start_beat} * 60 / s.session.project.bpm - position);
        const sr = engine.sampleRate; const sung = sing(sr, {{ notes, bpm: s.session.project.bpm, vibrato: {{ hz: 5.5, cents: 10 }}, cents: 4 }});
        const voice = new Float32Array(Math.round(ahead * sr) + sung.length); voice.set(sung, Math.round(ahead * sr)); engine.preview(voice, sr);""")
    if view == "notes":
        freeze_when(f, "Number(document.querySelector('[data-voice]')?.dataset.voice) > 40 && Number(document.querySelector('[data-lit]')?.dataset.lit) >= 3", timeout=15, what="the live line, and notes lit")
    else:
        on = t(f, "sing.tuning.on")
        freeze_when(f, f"document.body.innerText.includes({on!r})", timeout=15, what="on the note")


def studio_phone(f):
    clear_projects(f)
    if wanted("studio--recording"):
        # The Soprano and Alto recorded; recording the Tenor, 12 seconds in.
        light_first(f)
        home_page(f, "member")
        f.mic_checked()  # the mic check waits for Continue now: this journey is about recording
        grace = new_song(f)
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        sung_takes(f, ["soprano", "alto"])
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        f.run("sessionStorage.setItem('stackapella.headphones', '1');")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        tenor = t(f, "parts.tenor")
        # The pick follows the song's order (the Bass next): pick the Tenor, then record it.
        f.click_t("studio.pickNamed", {"name": tenor})
        f.wait(f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {json.dumps(t(f, 'studio.recordNamed', {'name': tenor}))})", what="Record Tenor")
        f.click_t("studio.recordNamed", {"name": tenor})
        recording_starts(f)
        f.run(f"""const {{ engine }} = {AUDIO}; const s = {SESSION}; const {{ sing }} = await import('/tests/fixtures.js');
            const tenor = s.session.parts.find((p) => p.templatePartKey === 'tenor'); const notes = s.templatePart(tenor).guide.notes;
            const sr = engine.sampleRate; const lead = Math.max(0, engine.playing.startTime - engine.ctx.currentTime);
            const sung = sing(sr, {{ notes, bpm: s.session.project.bpm, vibrato: {{ hz: 5.5, cents: 12 }}, level: 0.5 }});
            const voice = new Float32Array(Math.round(lead * sr) + sung.length); voice.set(sung, Math.round(lead * sr)); engine.preview(voice, sr);""")
        f.run(f"window.__engine = {AUDIO}.engine;")
        freeze_when(f, "window.__engine.playing && window.__engine.ctx.currentTime > window.__engine.playing.startTime + 12.2", timeout=30, what="12 seconds recorded")
        shoot(f, "studio--recording")
        thaw(f)
        f.click_t("studio.stopRecording")
        f.wait(f"!{AUDIO}.engine.playing", what="stopped")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        clear_projects(f)

    if wanted("studio--trim"):
        # The Alto's take, a little trimmed at each end; two takes, so Line up every part shows.
        light_first(f)
        home_page(f, "member")
        grace = new_song(f)
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        sung_takes(f, ["soprano", "alto"], pad_seconds=1.2)
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        alto = t(f, "parts.alto")
        f.run(f"[...document.querySelectorAll('section[aria-labelledby] button[aria-expanded]')].find((b) => b.textContent.includes({alto!r})).click()")
        f.wait("document.querySelector('button[aria-expanded=true]')", what="the Alto's row open")
        f.click_t("trim.button")
        f.wait_text(t(f, "trim.title", {"part": alto}))
        f.run(f"const s = {SESSION}; const a = s.session.parts.find((p) => p.templatePartKey === 'alto'); await s.setTrims(a, {{ start: Math.round(1.6 * a.sampleRate), end: Math.round(1.4 * a.sampleRate) }});")
        f.wait(f"{SESSION}.session.parts.find((p) => p.templatePartKey === 'alto').trimStartFrames > 0", what="trimmed")
        # The sheet from its top: its title and the whole take with its grips.
        shoot(f, "studio--trim", scroll="document.querySelectorAll('[role=dialog], [role=dialog] *').forEach((e) => { e.scrollTop = 0; })")
        f.press("Escape")
        clear_projects(f)


def settings(f):
    if wanted("settings--theme"):
        # Match my device, with the theme's own accent: Warm on a light device, Stage on a dark one.
        for index, (mode, colour, suffix, look) in enumerate(THEMES):
            f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-color-scheme", "value": mode}]})
            f.run(f"window.stackapellaTheme.choose({{ mode: 'system', gameMode: 'system', colour: {json.dumps(colour)}, accent: null, customAccent: null }});")
            f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('main, #app h1')")
            f.wait_text(t(f, "settings.nav.account"))
            f.run("(await import('/js/stores/account.js')).account.me.avatarUrl = null;")
            f.wait(f"document.documentElement.dataset.theme === {json.dumps(look)}", what=look)
            f.run("document.querySelector('#app button[aria-haspopup=dialog]').click()")
            f.wait("document.querySelector('[role=dialog]')", what="the theme sheet")
            capture(f, "settings--theme", suffix)
        f.chrome.send("Emulation.setEmulatedMedia", {"features": []})
        print("     shot settings--theme")

    if wanted("settings--storage"):
        # Three projects, biggest first; one replaced Alto take in Recently deleted.
        light_first(f)
        home_page(f, "member")
        clear_projects(f)
        for title, keys, seconds in (("Amazing Grace", ["soprano", "alto", "tenor"], None), ("Silent Night for Mum", None, 60), ("Our first harmony", None, 25)):
            if keys:
                project = new_song(f)
                f.go(f"/p/{project}", "document.querySelectorAll('canvas').length > 3")
                sung_takes(f, keys)
            else:
                f.run(f"""const s = await import('/js/stores/projects.js'); const {{ peaks }} = await import('/js/dsp.js'); const p = await s.createProject({{ title: {title!r} }});
                    const take = new Float32Array(48000 * {seconds}).map((_, i) => 0.25 * Math.sin(i / 20) * Math.abs(Math.sin(i / 30000)));
                    for (const [n, name] of [[0, 'Lead'], [1, 'Harmony']]) await s.savePart({{ id: crypto.randomUUID(), projectId: p.id, name, color: n ? '#93c26e' : '#f2a06b', order: n, volume: 1, pan: 0, muted: false, solo: false,
                        offsetSeconds: 0, nudgeSeconds: 0, frames: take.length, sampleRate: 48000, framesPerPeak: 512, peaks: peaks(take, 512), createdAt: Date.now() }}, take);""")
        f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('main, #app h1')")
        f.wait_text(t(f, "settings.nav.account"))
        # The mock's bin and usage, as this account's would be: the replaced take only, the three
        # projects counted, and everything saved to the account.
        f.run("""const m = await import('/js/api.js'); const list = m.api.bin.list.bind(m.api.bin); const usage = m.api.usage.show.bind(m.api.usage);
            m.api.bin.list = async (...args) => { const r = await list(...args); return { ...r, data: r.data.filter((item) => item.kind === 'take') }; };
            m.api.usage.show = async (...args) => { const r = await usage(...args); return { ...r, data: { ...r.data, projects: { ...r.data.projects, used: 3 } } }; };
            (await import('/js/stores/cloud.js')).cloud.lastSynced = Date.now();
            (await import('/js/router.js')).navigate('/storage');""")
        f.wait("location.pathname === '/storage'", what="Storage")
        f.wait(f"document.body.innerText.includes({I18N}.t('cloud.bin.restore'))", what="Recently deleted")
        shoot(f, "settings--storage", scroll="window.scrollTo(0, 0)")
        clear_projects(f)


def hum_a_tune(f):
    """Making your own song, Hum a tune: two lines of Amazing Grace hummed in free time (the
    arranger's fixture, played into the mic over loopback), written down with Line 1 again, Line 2
    again, Play it and Use a click. Staff until it's switched on, so the mock account is an admin."""
    if not wanted("create--hum"):
        return
    light_first(f)
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('[data-new-song]')")
    f.mic_checked()
    f.run("document.querySelector('[data-new-song]').click()")
    f.click(t(f, "home.new.make"), "[data-new-sheet] button")
    f.wait("document.querySelector('[data-new-hum]')", what="Hum a tune")
    f.run("document.querySelector('[data-new-hum]').click()")
    f.wait("location.pathname.startsWith('/create') && document.querySelector('[data-hum-tune]')", what="the hum step")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run("document.querySelector('[data-hum-record]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'recording'", what="humming")
    f.run(f"""const {{ engine }} = {AUDIO}; const bytes = await (await fetch('/tests/fixtures/hum-two-lines.wav')).arrayBuffer();
        const decoded = await engine.ctx.decodeAudioData(bytes); engine.preview(decoded.getChannelData(0), decoded.sampleRate);""")
    f.js("new Promise((r) => setTimeout(r, 20500))")
    f.run("document.querySelector('[data-hum-stop]').click()")
    f.wait("document.querySelector('[data-hum-tune]')?.dataset.state === 'idle' && document.querySelector('[data-hum-found]')", timeout=60, what="the tune written down")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    shoot(f, "create--hum", scroll="window.scrollTo(0, 0)")
    clear_projects(f)


def phone(f):
    getting_started(f)
    your_voice(f)
    learn_a_part(f)
    lesson_end(f)
    fact_card(f)
    studio_phone(f)
    settings(f)
    hum_a_tune(f)
    compress()


# The desktop's shots ------------------------------------------------------------------------------

def desktop(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.mic_checked()
    clear_projects(f)
    if wanted("settings--page"):
        # The sidebar of sections shows on a wide screen (87.png).
        light_first(f)
        f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('main, #app h1')")
        f.wait_text(t(f, "settings.nav.account"))
        f.run("(await import('/js/stores/account.js')).account.me.avatarUrl = null;")
        shoot(f, "settings--page", scroll="window.scrollTo(0, 0)")

    if wanted("learn-a-part--notes"):
        light_first(f)
        sing_whole(f, view="notes")
        shoot(f, "learn-a-part--notes")
        thaw(f)
        f.click_t("studio.pause")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        clear_projects(f)

    if any(wanted(shot) for shot in ("studio--overview", "studio--mixer", "studio--export", "studio--share")):
        light_first(f)
        home_page(f, "member")
        grace = new_song(f)
        f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
        sung_takes(f, ["soprano", "alto"])
        if wanted("studio--overview"):
            f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
            guides_ready(f)
            shoot(f, "studio--overview")
        if wanted("studio--mixer"):
            sung_takes(f, ["tenor", "bass"])
            f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
            guides_ready(f)
            f.run(f"const s = {SESSION}; s.updatePart(s.session.parts.find((p) => p.templatePartKey === 'tenor'), {{ solo: true }});")
            f.wait("document.querySelector('[data-implied-mute]')", what="the others silent under the solo")
            shoot(f, "studio--mixer")
        if wanted("studio--export"):
            # Every part in, nothing soloed: Export from the header (83.png).
            if not wanted("studio--mixer"):
                sung_takes(f, ["tenor", "bass"])
            f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
            guides_ready(f)
            f.run(f"const s = {SESSION}; for (const p of s.session.parts) s.updatePart(p, {{ solo: false, muted: false }});")
            f.click_t("studio.export")
            f.wait("document.querySelector('[role=dialog]')", what="the Export sheet")
            shoot(f, "studio--export")
        if wanted("studio--share"):
            # Share from the header (84.png): the preview, all the parts, Polished, Create link.
            if not wanted("studio--export"):
                if not wanted("studio--mixer"):
                    sung_takes(f, ["tenor", "bass"])
                f.go(f"/p/{grace}", "document.querySelectorAll('canvas').length > 3")
                guides_ready(f)
            else:
                f.press("Escape")
                f.wait("!document.querySelector('[role=dialog]')", what="Export closed")
            f.wait(f"!document.body.innerText.includes({I18N}.t('studio.preparingGuides'))", timeout=30, what="the guides ready")
            f.click_t("share.title")
            f.wait(f"document.querySelector('[role=dialog]')?.innerText.includes({I18N}.t('share.createLink'))", what="the Share sheet")
            shoot(f, "studio--share")
        clear_projects(f)
    compress()


def journey(f):
    if not OUT:
        return (phone if f.size == "phone" else desktop)(f)
    for part in (phone, desktop):  # the atlas: every screen at this size
        try:
            part(f)
        except Exception as error:  # noqa: BLE001 (the rest of the atlas still comes)
            print(f"     atlas: {part.__name__}'s screens at {f.size} stopped: {str(error)[:160]}")
            compress()


if __name__ == "__main__":
    for shot_id, why in SKIPPED.items():
        print(f"skip {shot_id} ({why})")
    run("help_shots", journey)
