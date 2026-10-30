"""A learner's QA pass over the first week (docs/launch-qa-journey.md): a new device arriving from the
landing's Start, lesson 1's mic moment and chord moment (a tone over loopback stands in for the
voice), lessons 2 and 3 sung for real with Hear it together, the first session's wrap and its asks,
Home, a library song (its sheet, Play, Start, the chooser, Sing, a take, Just recorded), the studio,
Share as a guest, signing up, Share signed in, Settings with Game mode and its switches, and the
next day's Home. Each screen is shot and checked for sideways scroll, raw translation keys,
"undefined" or "NaN", page errors, a step that takes long, and (in the serious skin) star, XP,
combo or streak words. It records what it finds in $QA_OUT (one JSON file per run) instead of
stopping at the first, and shoots to $QA_SHOTS. Each size and skin runs in its own theme, so the
four runs cover every pair of skin, size and theme:

    FLOW_SKINS=game,serious QA_OUT=/tmp/jq QA_SHOTS=~/Code/shots-review/launch-qa-journey PORT=8300 tool/flows.sh journey_qa

A QA pass, not a test (it records rather than fails): without QA_SHOTS it skips, so the landing
gate doesn't run it.
"""
import json
import os
import re
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import theme  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
STORE = "(await import('/js/stores/learning.js'))"
SESSION = "(await import('/js/stores/session.js'))"
ROUTER = "(await import('/js/router.js'))"
SHOTS = Path(os.environ.get("QA_SHOTS", str(Path(__file__).parent / "shots" / "journey"))).expanduser()
# Each run's theme: every pair of skin, size and theme is covered once.
THEMES = {("phone", "game"): "light", ("phone", "serious"): "dark", ("desktop", "serious"): "light", ("desktop", "game"): "dark"}
SLOW = 4.0  # seconds a screen may take to be ready before it's noted as slow
BEAT = 60 / 72

CHECK = r"""(() => {
    const text = document.body.innerText;
    const raw = (text.match(/\b(course|courses|results|wrap|home|sing|studio|share|settings|auth|install|chordMoment|micCheck|library|template|together|appearance|game|report|reminders|boards|plus|choose|exercise|headphones|timing|parts)\.[a-z][A-Za-z]+(\.[A-Za-z]+)*\b/g) || []).filter((k) => !/\.(com|json|js|md|app)$/.test(k));
    const odd = (text.match(/\bundefined\b|\bNaN\b|\[object Object\]|\{[a-z]+\}/g) || []);
    const stars = (text.match(/\b\d+\s*stars?\b|\bstars?\b|★|\bXP\b|\bcombo\b|\bstreak\b/gi) || []);
    const over = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    return JSON.stringify({ raw: [...new Set(raw)].slice(0, 3), odd: [...new Set(odd)], stars: [...new Set(stars)].slice(0, 4), over });
})()"""


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def tone(f, notes, tempo=60):
    f.run(f"const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js'); engine.preview(renderPart({json.dumps(notes)}, {{ bpm: {tempo}, sampleRate: engine.sampleRate }}), engine.sampleRate);")


class Journey:
    def __init__(self, f):
        self.f = f
        self.found = []
        self.times = []
        # QA_LOOK=light|dark: one theme for every run (the UI guide lays the skins side by side).
        self.look = os.environ.get("QA_LOOK") or THEMES.get((f.size, f.skin), "light")
        self.n = 0
        self.seen_errors = 0

    def note(self, where, what):
        self.found.append({"where": where, "what": what})
        print(f"     ! {where}: {what}")

    def look_at(self, where, label=None):
        """Checks the screen as it is and shoots it."""
        f = self.f
        try:
            seen = json.loads(f.js(CHECK))
        except Exception as error:  # noqa: BLE001 (a page mid-navigation)
            self.note(where, f"couldn't be read: {error}")
            return
        if seen["over"] > 1:
            self.note(where, f"scrolls sideways by {seen['over']}px")
        if seen["raw"]:
            self.note(where, f"raw words on screen: {seen['raw']}")
        if seen["odd"]:
            self.note(where, f"shows {seen['odd']}")
        if f.js("document.documentElement.dataset.skin") == "serious" and seen["stars"]:
            self.note(where, f"serious skin shows game words: {seen['stars']}")
        errors = [e for e in f.chrome.errors[self.seen_errors:] if "favicon" not in e and not any(x in e for x in f.expected_errors)]
        self.seen_errors = len(f.chrome.errors)
        for e in errors[:3]:
            self.note(where, f"page error: {e[:200]}")
        if label:
            SHOTS.mkdir(parents=True, exist_ok=True)
            self.n += 1
            time.sleep(0.4)
            f.chrome.screenshot(str(SHOTS / f"{self.n:02d}-{label}-{f.size}-{f.skin}-{self.look}.png"))

    def ready(self, where, condition, timeout=20, slow=SLOW):
        """Waits for `condition`, noting it if it's slow; False (and noted) if it never comes."""
        start = time.time()
        try:
            self.f.wait(condition, timeout=timeout, what=where)
        except AssertionError:
            self.note(where, f"never came (waited {timeout} s)")
            self.look_at(where, "stuck-" + re.sub(r"[^a-z0-9]+", "-", where.lower())[:30])
            return False
        took = time.time() - start
        self.times.append((where, round(took, 1)))
        if took > slow:
            self.note(where, f"took {took:.1f} s")
        return True

    def stage(self, name, fn):
        try:
            fn()
        except Exception as error:  # noqa: BLE001 (record it and go on)
            self.note(name, f"broke: {str(error)[:300]}")
            self.look_at(name, "broke-" + re.sub(r"[^a-z0-9]+", "-", name.lower())[:30])

    def save(self):
        out = os.environ.get("QA_OUT")
        if out:
            Path(out).mkdir(parents=True, exist_ok=True)
            (Path(out) / f"{self.f.size}-{self.f.skin}-{self.look}.json").write_text(json.dumps({"found": self.found, "times": self.times}, indent=1))


MIC_PROMPT = """(() => {
    const query = navigator.permissions?.query?.bind(navigator.permissions);
    if (query) navigator.permissions.query = (d) => (d?.name === 'microphone' && !sessionStorage.getItem('qa.micAsked')
        ? Promise.resolve({ state: 'prompt', onchange: null, addEventListener() {}, removeEventListener() {} }) : query(d));
    const ask = navigator.mediaDevices?.getUserMedia?.bind(navigator.mediaDevices);
    if (ask) navigator.mediaDevices.getUserMedia = (c) => { sessionStorage.setItem('qa.micAsked', '1'); return ask(c); };
})();"""


def journey(f):
    j = Journey(f)
    q = j.ready
    start_url = "/learn/1-comfortable?from=start&v=onb1-b&api=mock&scenario=&as=signedOut"

    def arrive():
        # A new device: nothing kept, then the landing's Start.
        f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('#app header')")
        f.run("localStorage.clear(); sessionStorage.clear(); await new Promise((r) => { const q = indexedDB.deleteDatabase('stackapella'); q.onsuccess = q.onerror = q.onblocked = r; });")
        f.go(start_url, "document.querySelector('main h1')")
        theme(f, j.look, "warm" if j.look == "light" else "stage")
        # Headless Chrome allows the mic already; a new visitor's browser hasn't been asked, so the
        # permission reads "prompt" until the app asks (then the fake mic answers as usual).
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": MIC_PROMPT})
        f.go(start_url, "document.querySelector('main h1')")
        q("lesson 1's first step", "document.querySelector('main h1')")
        if f.js(f"!document.body.innerText.includes({I18N}.t('chordMoment.promise'))"):
            j.note("lesson 1's first step", "no chord promise for the onb1-b arm from the landing")
        j.look_at("lesson 1's first step", "arrival")

    def lesson1():
        # The way on before the mic: "Turn on my mic" and why (headless Chrome has the mic allowed
        # already, so it may read Continue here: noted, not a finding).
        if f.js(f"document.body.innerText.includes({I18N}.t('course.mic.turnOn'))"):
            if f.js(f"!document.body.innerText.includes({I18N}.t('course.mic.why'))"):
                j.note("Turn on my mic", "the why line isn't shown")
            j.look_at("Turn on my mic", "mic-ask")
        else:
            j.times.append(("Turn on my mic", "not shown: the mic is already allowed in headless Chrome"))
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.go_on()
        if not q("listening at once", "['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)"):
            return
        tone(f, [{"beat": 0, "length": 6, "pitch": 57}])
        if q("the mic check's Got you", f"document.body.innerText.includes({I18N}.t('micCheck.gotYou'))", timeout=8):
            j.look_at("finding your note", "got-you")
        if not q("the note found", "document.querySelector('[data-note]')?.textContent === 'A3'"):
            return
        finish = t(f, "course.finish")
        q("Finish, once the note is kept", f"[...document.querySelectorAll('button')].some((b) => b.textContent.trim() === {finish!r} && !b.disabled)")
        f.click_t("course.finish")
        if not q("lesson 1's result", "document.querySelector('[data-note-result]')"):
            return
        time.sleep(1.4)
        j.look_at("lesson 1's result", "note-result")
        if q("the chord moment playing by itself", "document.querySelector('[data-chord-moment]')?.dataset.playing === 'true'", timeout=8):
            j.look_at("the chord moment", "chord-playing")
            if q("the chord moment's line", "document.querySelector('[data-chord-line]')", timeout=12):
                f.run("document.querySelector('[data-chord-line]').scrollIntoView({ block: 'center' })")
                j.look_at("the chord moment", "chord-line")

    def sing_turn(lesson, pitches, listen_beats):
        f.run("sessionStorage.setItem('stackapella.headphones', '1');")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.click_t("course.tap.sing")
        if not q(f"{lesson}: recording the turn", f"{AUDIO}.engine.playing?.recording"):
            return False
        notes = [{"beat": 3 * i, "length": 2, "pitch": p} for i, p in enumerate(pitches)]
        f.run(f"""const {{ engine }} = {AUDIO}; const {{ renderPart }} = await import('/js/synth.js');
            while ((engine.positionSeconds() ?? 0) < {listen_beats * BEAT - 0.12}) await new Promise((r) => requestAnimationFrame(r));
            engine.preview(renderPart({json.dumps(notes)}, {{ bpm: 72, sampleRate: engine.sampleRate }}), engine.sampleRate);""")
        start = time.time()
        if not q(f"{lesson}: the take's result", "document.querySelector('[data-take-result]')", timeout=40, slow=99):
            return False
        sung = 3 * len(pitches) * BEAT  # the turn, from the listen's end
        if time.time() - start - sung > SLOW:
            j.note(f"{lesson}: the take's result", f"came {time.time() - start - sung:.1f} s after the turn's end")
        time.sleep(1.4)
        j.look_at(f"{lesson}: the take's result", f"{lesson}-take")
        return True

    def lesson2():
        f.click_t("course.next")
        if not q("lesson 2 opens", "location.pathname === '/learn/1-match' && document.querySelector('main h1')"):
            return
        j.look_at("lesson 2's Learn", "l2-learn")
        f.click_t("course.continue")
        # Try: Wait for me (over loopback the guide's own note is what's heard, so it moves on).
        if q("lesson 2's Try", f"document.body.innerText.includes({I18N}.t('course.tap.try'))", timeout=10):
            f.js(f"{AUDIO}.unlock()")
            f.js(f"{AUDIO}.engine.setLoopback(true)")
            f.click_t("course.tap.try")
            if q("the headphones question", f"document.body.innerText.includes({I18N}.t('headphones.title'))", timeout=8):
                j.look_at("the headphones question", "headphones")
                f.click_t("headphones.on")
            if q("Wait for me ends by itself", f"document.body.innerText.includes({I18N}.t('course.wait.done'))", timeout=30):
                j.look_at("Wait for me", "l2-try-done")
            f.click_t("course.continue")
        if not sing_turn("1-match", [57, 59, 54], 9):
            return
        if f.js(f"!document.body.innerText.includes({I18N}.t('results.hearTogether'))"):
            j.note("1-match: the take's result", "no Hear it together in unit 1")
        else:
            # The round play button: "Hear it together" with the guide on, else "Listen back".
            f.run(f"const i = {I18N}; const labels = [i.t('results.hearTogether'), i.t('results.listenBack')]; [...document.querySelectorAll('[data-take-result] button[aria-label]')].find((b) => labels.includes(b.getAttribute('aria-label')))?.click()")
            # Playing: the round button no longer offers to listen (it offers to stop).
            listen = json.dumps([t(f, "results.hearTogether"), t(f, "results.listenBack")])
            q("Hear it together plays", f"![...document.querySelectorAll('[data-take-result] button[aria-label]')].some((b) => {listen}.includes(b.getAttribute('aria-label')))", timeout=6)
            time.sleep(1.0)
            j.look_at("Hear it together", "hear-together")
            f.run(f"{AUDIO}.engine.stop?.()")
        f.click_t("course.finish")
        if q("lesson 2's end", "document.querySelector('[data-lesson-end]')"):
            time.sleep(1.4)
            j.look_at("lesson 2's end", "l2-end")
            share = t(f, "boards.challenge.button") if f.skin == "game" else t(f, "results.share.result")
            if f.js(f"!document.body.innerText.includes({share!r})"):
                j.note("lesson 2's end", f"no {share!r} on a starred lesson end")

    def lesson3():
        f.click_t("course.next")
        if not q("lesson 3 opens", "location.pathname === '/learn/1-high-low' && document.querySelector('main h1')"):
            return
        j.look_at("lesson 3's Learn", "l3-learn")
        f.click_t("course.continue")
        if not sing_turn("1-high-low", [57, 61, 57, 54], 12):
            return
        f.click_t("course.finish")
        if not q("lesson 3's end", "document.querySelector('[data-lesson-end]')"):
            return
        time.sleep(1.4)
        j.look_at("lesson 3's end", "l3-end")
        if q("the session wrap", "document.querySelector('[data-session-wrap]')", timeout=8):
            f.run("document.querySelector('[data-session-wrap]').scrollIntoView({ block: 'start' })")
            j.look_at("the session wrap", "wrap")
            ask = f.js("document.querySelector('[data-wrap-keep]')?.dataset.ask ?? 'none'")
            j.times.append(("the wrap's keep-it ask", ask))
            f.run("[...document.querySelectorAll('[data-wrap-goal] [role=radio]')][1]?.click()")
            if q("the goal kept", f"{STORE}.learning.progress.goal === 3", timeout=5):
                j.look_at("the wrap's goal chosen", "wrap-goal")

    def home():
        f.run(f"{ROUTER}.navigate('/')")
        if not q("Home after session 1", "document.querySelector('#lead-title')"):
            return
        j.look_at("Home after session 1", "home")
        if f.js("/Get Plus/.test(document.body.innerText)"):
            j.note("Home after session 1", "Get Plus shows in the first week")
        f.run("document.querySelector('#band-songs')?.scrollIntoView({ block: 'start' })")
        j.look_at("Home's songs", "home-songs")

    def library_song():
        cards = "[...document.querySelectorAll('#home-songs [data-song-card]')]"
        if not q("a library song card", f"{cards}.length > 0"):
            return
        f.run(f"{cards}[0].querySelector('button').click()")
        play = "document.querySelector('[role=dialog] [data-song-play]')"
        if not q("the song's sheet", f"{play} && document.querySelector('[role=dialog] [data-song-start]')"):
            return
        j.look_at("the song's sheet", "song-sheet")
        f.js(f"{AUDIO}.unlock()")
        f.run(f"{play}.click()")
        if q("the sheet's Play", f"{AUDIO}.engine.playing", timeout=10):
            time.sleep(1.0)
            j.look_at("the sheet playing", "song-playing")
            f.run(f"{play}.click()")
        f.run("document.querySelector('[role=dialog] [data-song-start]').click()")
        if not q("Sing's chooser", f"document.body.innerText.includes({I18N}.t('sing.chooser.title'))"):
            return
        j.look_at("Sing's chooser", "chooser")
        f.run("document.querySelector('[data-way=parts]').click()")
        time.sleep(1.0)
        if f.js(f"document.body.innerText.includes({I18N}.t('sing.chooser.yourPart'))"):
            j.look_at("the part picker", "part-picker")
            f.run("document.querySelector('main .card-interactive, main [data-part]')?.click()")
        if not q("Sing's coach", "document.querySelector('[data-coach]')"):
            return
        j.look_at("Sing's coach", "coach")
        f.click_t("sing.coach.skipToRecord")
        f.js(f"{AUDIO}.unlock()")
        f.js(f"{AUDIO}.engine.setLoopback(true)")
        f.run("sessionStorage.setItem('stackapella.headphones', '1')")
        # The record action names the part ("Record the alto"): any button that starts that way.
        prefix = t(f, "sing.coach.action.record", {"part": "QQPARTQQ"}).split("QQPARTQQ")[0]
        f.run(f"[...document.querySelectorAll('button')].find((b) => b.getClientRects().length && b.textContent.trim().startsWith({json.dumps(prefix)}))?.click()")
        if not q("the mic check, timing check or recording", f"document.querySelector('[role=dialog]') || {AUDIO}.engine.playing?.recording"):
            return
        if f.js(f"document.querySelector('[role=dialog]')?.innerText.includes({I18N}.t('micCheck.title'))"):
            j.look_at("Sing's mic check", "sing-mic-check")
            cont = t(f, "micCheck.continue")
            q("the mic check hears", f"[...document.querySelectorAll('[role=dialog] button')].some((b) => b.textContent.trim() === {cont!r} && !b.disabled)", timeout=10)
            f.click(cont, "[role=dialog] button")
            q("the timing check or recording", f"document.querySelector('[role=dialog] select') || {AUDIO}.engine.playing?.recording")
        if f.js("!!document.querySelector('[role=dialog] select')"):
            j.look_at("the timing check", "timing")
            f.click_t("timing.start")
            q("the timing measured", f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, slow=15)
            f.click_t("timing.startRecording")
        if not q("recording", f"{AUDIO}.engine.playing?.recording"):
            return
        f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 3", what="3 s recorded")
        j.look_at("recording", "recording")
        f.press("Escape")
        if not q("Just recorded", "document.querySelector('[data-take-review]')"):
            return
        q("How it went", f"document.body.innerText.includes({I18N}.t('report.unheard')) || document.body.innerText.includes({I18N}.t('report.unheardSerious')) || document.querySelector('[data-take-review] svg[role=img]')", timeout=30)
        j.look_at("Just recorded", "just-recorded")
        f.js(f"{AUDIO}.engine.setLoopback(false)")
        f.click_t("sing.openStudio")
        if q("the studio", "/^\\/p\\/[^/]+$/.test(location.pathname) && document.querySelectorAll('canvas').length > 3"):
            time.sleep(0.8)
            j.look_at("the studio", "studio")

    def share_guest():
        share = json.dumps(t(f, "share.title"))
        button = f"[...document.querySelectorAll('#app header button, main button')].find((b) => b.getClientRects().length && b.textContent.trim() === {share})"
        if not q("Share in the studio", f"!!{button}", timeout=8):
            return
        f.run(f"{button}.click()")
        if q("Share asks a guest to sign in", f"document.body.innerText.includes({I18N}.t('share.signIn'))", timeout=8):
            j.look_at("Share as a guest", "share-guest")
        f.press("Escape")

    def sign_up():
        f.run(f"{ROUTER}.navigate('/signin?mode=signup')")
        if not q("the sign-up form", "document.querySelector('form input[autocomplete=name]')"):
            return
        j.look_at("Sign up", "signup")
        f.type("form input[autocomplete=name]", "Robin Vale")
        f.type("form input[type=email]", "robin@example.com")
        f.type("form input[autocomplete=new-password]", "a long enough password")
        f.run("""const [month, year] = document.querySelectorAll('form select');
            month.value = month.options[3].value; month.dispatchEvent(new Event('change', { bubbles: true }));
            year.value = [...year.options].map((o) => o.value).find((v) => +v && +v < 2000); year.dispatchEvent(new Event('change', { bubbles: true }));""")
        f.run("document.querySelector('form button[type=submit]').click()")
        # An email account is made from the link in its email: the mock's member stands in for it.
        if q("Check your email", f"document.body.innerText.includes({I18N}.t('auth.sent.title'))", timeout=10):
            j.look_at("Check your email", "check-email")
        f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
        if not q("signed in", "(await import('/js/stores/account.js')).account.me", timeout=15):
            return
        time.sleep(1.0)
        j.look_at("Home, signed in", "signed-in")
        kept = f.js(f"Object.keys({STORE}.learning.progress.lessons).length")
        if kept < 3:
            j.note("after signing up", f"the guest's lessons aren't kept: {kept} left")
        songs = f.js("(await import('/js/stores/projects.js')).projects.all.length")
        if songs < 1:
            j.note("after signing up", "the guest's song isn't kept")

    def share_signed_in():
        pid = f.js("(await import('/js/stores/projects.js')).projects.all[0]?.id ?? null")
        if not pid:
            return
        f.run(f"{ROUTER}.navigate('/p/{pid}')")
        if not q("the studio again", "document.querySelectorAll('canvas').length > 3"):
            return
        share = json.dumps(t(f, "share.title"))
        button = f"[...document.querySelectorAll('#app header button, main button')].find((b) => b.getClientRects().length && b.textContent.trim() === {share})"
        if not q("Share signed in", f"!!{button}", timeout=8):
            return
        f.run(f"{button}.click()")
        if q("the share sheet", "document.querySelector('[role=dialog]')", timeout=8):
            time.sleep(1.2)
            j.look_at("Share signed in", "share-signed-in")
        f.press("Escape")

    def settings():
        f.run(f"{ROUTER}.navigate('/settings')")
        if not q("Settings", "document.querySelector('#game-heading, [data-restart]')"):
            return
        j.look_at("Settings", "settings")
        f.run("document.getElementById('game')?.scrollIntoView()")
        j.look_at("Settings' Game mode", "settings-game")
        rows = f.js("document.querySelectorAll('[data-game-effect]').length")
        if f.skin == "game" and rows == 0:
            j.note("Settings' Game mode", "no element switches in game mode")
        if f.skin == "serious" and rows:
            j.note("Settings' Game mode", f"{rows} game switches in serious mode")
        # Game mode's toggle: the other skin, then back.
        before = f.js("document.documentElement.dataset.skin")
        toggle = "document.querySelector('#game [role=switch], #game-heading ~ * [role=switch]')"
        if not f.js(f"!!{toggle}"):
            j.note("Settings' Game mode", "no Game mode switch found")
            return
        f.run(f"{toggle}.click()")
        if q("Game mode switched", f"document.documentElement.dataset.skin !== {json.dumps(before)}", timeout=6):
            time.sleep(0.8)
            j.look_at("Settings, the other skin", "settings-other-skin")
            f.run(f"{toggle}.click()")
            q("Game mode switched back", f"document.documentElement.dataset.skin === {json.dumps(before)}", timeout=6)
            # The quick off and on must leave the run's own skin, after a full load too (fixed in
            # 2193bbb4: the account took an older look over the device's newer choice).

    def day_two():
        # The next day: the device arrived and sang yesterday.
        f.run(f"""const day = 86400000; const {{ learning }} = {STORE};
            const f = JSON.parse(localStorage.getItem('stackapella.funnel') || '{{}}');
            const d = new Date(Date.now() - day); f.arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            localStorage.setItem('stackapella.funnel', JSON.stringify(f));
            const p = learning.progress; const lessons = Object.fromEntries(Object.entries(p.lessons).map(([k, v]) => [k, {{ ...v, at: v.at - day }}]));
            const moved = {{ ...p, lessons, startedAt: (p.startedAt ?? Date.now()) - day }};
            const store = await import('/js/db.js');
            for (const project of await store.all('projects')) await store.put('projects', {{ ...project, updatedAt: (project.updatedAt ?? Date.now()) - day }});
            const plain = JSON.parse(JSON.stringify(moved)); learning.progress = plain;
            const db = await import('/js/db.js'); await db.put('learning', plain, 'progress');""")
        f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
        j.times.append(("day 2's progress", f.js(f"JSON.stringify({{ arrived: JSON.parse(localStorage.getItem('stackapella.funnel') || '{{}}').arrived, at: Object.values({STORE}.learning.progress.lessons).map((l) => new Date(l.at).toISOString().slice(0, 10)), home: {STORE}.learning.progress.home }})")))
        if not q("day 2's Home", "document.querySelector('#lead-title')"):
            return
        if not q("day 2's welcome back", "document.querySelector('[data-welcome]')", timeout=8):
            pass
        j.look_at("day 2's Home", "day2-home")
        remind = t(f, "wrap.remindTitle")
        if f.js(f"document.body.innerText.includes({remind!r})"):
            f.run(f"[...document.querySelectorAll('h2, h3, p')].find((e) => e.textContent.includes({remind!r}))?.scrollIntoView({{ block: 'center' }})")
            j.look_at("day 2's reminders offer", "day2-remind")
        else:
            install = f.js("document.body.innerText.includes('Install')")
            j.times.append(("day 2's reminders offer", "waits while Home offers install (expected)" if install else "not shown"))
        f.click_t("course.continue")
        if q("day 2's next lesson", "location.pathname.startsWith('/learn/') && document.querySelector('main h1')"):
            j.look_at("day 2's next lesson", "day2-lesson")

    def day_seven():
        # A week on: the device arrived seven days ago, and Home shows the first week's card.
        f.run(f"""const day = 86400000; const {{ learning }} = {STORE};
            const f = JSON.parse(localStorage.getItem('stackapella.funnel') || '{{}}');
            const d = new Date(Date.now() - 7 * day); f.arrived = new Date(d - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
            localStorage.setItem('stackapella.funnel', JSON.stringify(f));
            const p = learning.progress; const moved = {{ ...p, startedAt: Date.now() - 7 * day }};
            const store = await import('/js/db.js');
            for (const project of await store.all('projects')) await store.put('projects', {{ ...project, updatedAt: (project.updatedAt ?? Date.now()) - 6 * day }});
            const plain = JSON.parse(JSON.stringify(moved)); learning.progress = plain;
            const db = await import('/js/db.js'); await db.put('learning', plain, 'progress');""")
        f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
        if not q("day 7's Home", "document.querySelector('#lead-title')"):
            return
        if q("day 7's first-week card", "document.querySelector('[data-first-week]')", timeout=8):
            f.run("document.querySelector('[data-first-week]').scrollIntoView({ block: 'center' })")
        j.look_at("day 7's Home", "day7-week")

    skip = set(os.environ.get("QA_SKIP", "").split(","))
    for name, fn in [("arrival", arrive), ("lesson 1", lesson1), ("lesson 2", lesson2), ("lesson 3", lesson3), ("home", home),
                     ("library song", library_song), ("share as a guest", share_guest), ("sign up", sign_up),
                     ("share signed in", share_signed_in), ("settings", settings), ("day 2", day_two), ("day 7", day_seven)]:
        if name in skip:
            continue
        print(f"     {name}")
        j.stage(name, fn)
        j.save()
    print(f"     {len(j.found)} findings ({f.size}, {f.skin}, {j.look})")
    f.chrome.errors.clear()


if __name__ == "__main__":
    if not os.environ.get("QA_SHOTS"):
        print("skip journey_qa (a QA pass: set QA_SHOTS)")
        sys.exit(0)
    run("journey_qa", journey)
