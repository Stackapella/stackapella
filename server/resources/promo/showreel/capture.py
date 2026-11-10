#!/usr/bin/env python3
"""Captures the real app for the film (README.md, "App footage"): stills and short recordings of the
mock app (web/tool/serve.php with the mock API, its demo data) and of the site's own pages (the
Laravel server, for /courses and the tools), in both skins, once for each cut: the dark cut films the
app's dark themes, the light cut its light ones. Driven with the repo's own headless Chrome
(web/tool/cdp.py). Nothing here reads production.

    python3 -I capture.py --app=http://127.0.0.1:8462 --site=http://127.0.0.1:8461            every shot, both cuts
    python3 -I capture.py --app=... --site=... --only=courses,tuner --cut=dark                  some of them

The captures are binaries, so they're gitignored (captures/); captures.json (in git) lists what each
one is, and the film reads it. Only made-up names appear: the mock's people are renamed Maya, Sam and
Leo before every shot (Hillel's names for the film), and a singer's credit is never in a shot.
"""
import base64
import io
import json
import sys
import time
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parents[3]
sys.path.insert(0, str(ROOT / 'web/tool'))
from cdp import Chrome  # noqa: E402

A = {}
for arg in sys.argv[1:]:
    k, _, v = arg.lstrip('-').partition('=')
    A[k] = v if v else True
APP = str(A.get('app', 'http://127.0.0.1:8462')).rstrip('/')
SITE = str(A.get('site', 'http://127.0.0.1:8461')).rstrip('/')
OUT = HERE / 'captures'

# Phone shots stand tall in the frame (about 860 film px), so twice the phone's own pixels. Desktop
# shots are a 1440-wide window pushed in on the panel that matters (Hillel: "there should be desktop
# shots"), so twice its pixels too.
SIZES = {'phone': (390, 844, True, 2), 'desktop': (1440, 900, False, 2)}

# The mock's people, and the one real name its examples carry, become the film's: the singer whose
# phone or group it is is Maya; the others Sam and Leo.
NAMES = [['Ada Lovelace', 'Maya'], ["Ada's iPhone", "Maya's phone"], ['Ana Ruiz', 'Maya'], ['Ana R.', 'Maya'],
         ['Grace Okafor', 'Maya'], ['Ms Okafor', 'Maya'], ['Hillel Coren', 'Maya'], ['Ms Rivera', 'Maya'],
         ['Sam Lee', 'Sam'], ['Kim Ode', 'Leo'], ['Ben Ode', 'Leo'], ['Sarah Kim', 'Sam'], ['Sarah K.', 'Sam']]
INITIALS = {'AL': 'M', 'A': 'M', 'AR': 'M', 'GO': 'M', 'HC': 'M', 'K': 'L', 'KO': 'L', 'B': 'L', 'SL': 'S', 'SK': 'S'}
RENAME = """(() => {
  const NAMES = %s.concat(SWAP), INITIALS = %s;
  const fix = (root) => {
    const walk = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let n = walk.nextNode(); n; n = walk.nextNode()) {
      let s = n.nodeValue, was = s;
      for (const [a, b] of NAMES) if (s.includes(a)) s = s.split(a).join(b);
      const bare = s.trim();
      // an avatar's initials: a node that is only them
      const el = n.parentElement, box = el && el.getBoundingClientRect();
      if (INITIALS[bare] && el.textContent.trim() === bare && box.width > 0 && box.width <= 72 && Math.abs(box.width - box.height) < 6) s = s.replace(bare, INITIALS[bare]);
      if (s !== was) n.nodeValue = s;
    }
    // the install offer is an ask, not the app (hidden through the CSSOM: the app's CSP refuses a style tag)
    for (const el of root.querySelectorAll ? root.querySelectorAll('[data-install],[data-install-row]') : []) el.style.display = 'none';
    for (const el of root.querySelectorAll ? root.querySelectorAll('[aria-label],[title],[alt]') : []) for (const at of ['aria-label', 'title', 'alt']) {
      const v = el.getAttribute(at); if (!v) continue;
      let s = v; for (const [a, b] of NAMES) s = s.split(a).join(b);
      if (s !== v) el.setAttribute(at, s);
    }
  };
  const go = () => { fix(document.body); new MutationObserver((ms) => { for (const m of ms) { for (const x of m.addedNodes) fix(x.nodeType === 3 ? x.parentNode : x); if (m.type === 'characterData') fix(m.target.parentNode); } }).observe(document.body, { childList: true, subtree: true, characterData: true }); };
  if (document.body) go(); else addEventListener('DOMContentLoaded', go);
})();""" % (json.dumps(NAMES), json.dumps(INITIALS))


# A sung note without a mic: getUserMedia answers with an oscillator whose pitch is window.__hz
# (a voice's light vibrato on it), so the tuner and the lanes hear someone singing in tune.
FAKE_MIC = """(() => {
  window.__hz = 440;
  const real = navigator.mediaDevices && navigator.mediaDevices.getUserMedia.bind(navigator.mediaDevices);
  if (!real) return;
  navigator.mediaDevices.getUserMedia = async (c) => {
    if (!c || !c.audio) return real(c);
    const ctx = new AudioContext(); await ctx.resume();
    const o = ctx.createOscillator(), o2 = ctx.createOscillator(), g2 = ctx.createGain(), lfo = ctx.createOscillator(), depth = ctx.createGain(), g = ctx.createGain();
    o.type = 'sawtooth'; o2.type = 'sine'; lfo.frequency.value = 5.2; depth.gain.value = 2.2; g.gain.value = 0.25; g2.gain.value = 0.6;
    lfo.connect(depth); depth.connect(o.frequency); depth.connect(o2.frequency);
    const f = ctx.createBiquadFilter(); f.type = 'lowpass'; f.frequency.value = 1800;
    o.connect(f).connect(g); o2.connect(g2).connect(g);
    const d = ctx.createMediaStreamDestination(); g.connect(d);
    [o, o2, lfo].forEach((x) => x.start());
    const tick = () => { const hz = window.__hz || 0; o.frequency.setTargetAtTime(hz, ctx.currentTime, 0.03); o2.frequency.setTargetAtTime(hz, ctx.currentTime, 0.03); g.gain.setTargetAtTime(hz ? 0.25 : 0, ctx.currentTime, 0.02); requestAnimationFrame(tick); };
    tick();
    return d.stream;
  };
})();"""


def appearance(skin, mode):
    """The look, chosen before any page script runs (theme-boot.js reads it): the app's and the mock account's."""
    # game mode's own light or dark is left unchosen, so game mode is dark, as it is for anyone who turns it on
    c = {'skin': skin, 'mode': mode, 'gameMode': None, 'colour': None, 'accent': None, 'customAccent': None, 'at': int(time.time() * 1000)}
    j = json.dumps(json.dumps(c))
    return (f"try{{localStorage.setItem('stackapella.appearance',{j});sessionStorage.setItem('stackapella.mockAppearance',"
            f"{json.dumps(json.dumps({'skin': skin, 'mode': mode}))});document.cookie='sa_theme='+encodeURIComponent({j})+'; path=/'}}catch(e){{}}")


# The range test, sung as tool_range.py sings it on its demo page: low notes, then high, then no falsetto.
RANGE_SUNG = """const wait = (s) => new Promise((r) => setTimeout(r, s * 1000)), btn = () => document.querySelector('[data-ui=tool-primary]');
const sing = async (m, s) => { window.__hz = m == null ? 0 : 440 * 2 ** ((m - 69) / 12); await wait(s); };
window.__hz = 0; btn().click(); await wait(0.8);
await sing(48, 1.6); await sing(null, 0.5); await sing(43, 1.6); await sing(null, 0.5);
btn().click(); await wait(0.6); btn().click(); await wait(0.6);
await sing(60, 1.6); await sing(null, 0.4); await sing(64, 1.6); await sing(null, 0.4);
btn().click(); await wait(0.6);
document.querySelector('[data-falsetto-answer="no"]')?.click(); await wait(0.3); btn().click(); await wait(1.2);"""

# Amazing Grace's Sing, recording the alto over loopback (the way game_hud.py records): the mic opened
# and remembered, the latency zero, the song made from the template, the alto picked, R pressed, and
# filmed once the take is under way.
SING_READY = """const a = await import('/js/audio.js'); await a.openMic(); (await import('/js/mic-check.js')).rememberMic();
a.unlock(); a.engine.setLoopback(true); sessionStorage.setItem('stackapella.headphones', '1');
for (const route of ['speakers', 'wired', 'bluetooth']) localStorage.setItem(`stackapella.latency.${a.audio.mic?.label ?? 'default'}.${route}.${a.engine.sampleRate}`, '0');
a.chooseRoute('wired');
const ps = await import('/js/stores/projects.js'); await ps.loadProjects(); for (const p of [...ps.projects.all]) await ps.deleteProject(p.id);
const { loadTemplate } = await import('/js/templates.js');
return (await ps.startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;"""
SESSION = "(await import('/js/stores/session.js'))"
# The singer hears the other parts while they record, as a singer does (the app's own line for it, as
# the first take shows it); here they're muted only so the loopback hears the alto alone.
HEARING = [['Nothing plays while you record: your guide and every other part are off.', 'Hearing every other part. Your guide is off.']]
SING_STEPS = [
    {'go': '/voice', 'wait': "document.querySelector('#app header, #app main')", 'js': SING_READY},
    {'go': '/p/{ret}/sing', 'wait': "document.querySelector('#app header')"},
    {'wait': "window.__s || import('/js/stores/session.js').then((m) => (window.__s = m)) && false"},
    {'wait': 'window.__s.session.parts.length > 3', 'js': f"""const s = {SESSION}; s.session.practice.sing = {{ ...(s.session.practice.sing ?? {{}}), way: 'whole', fitSaid: true, keyChosen: true }};
        s.practise(s.session.parts.find((p) => p.templatePartKey === 'alto')); s.savePractice();"""},
    {'wait': '!window.__s.session.recording && !window.__s.session.busy', 'js': """const s = window.__s;
        for (const p of s.session.parts) if (p.templatePartKey !== 'alto') s.toggleHearing(p.id);
        const a = await import('/js/audio.js'); a.unlock(); await a.openMic(); a.engine.setLoopback(true); // this page's engine: the one set on /voice went with it
        await new Promise((r) => setTimeout(r, 800));"""},
    {'key': 'r'},
    # the singer: the alto's own notes from where the take is, played into the loopback
    {'wait': 'window.__s.session.recording && window.__s.session.countIn === null && window.__s.session.position > window.__s.session.recordFrom + 0.2',
     'js': """const s = window.__s, { engine } = await import('/js/audio.js'), { renderPart } = await import('/js/synth.js');
        const bpm = s.session.project?.tempo ?? 80, b0 = s.session.position * bpm / 60;
        const notes = s.templatePart(s.session.focusPart).guide.notes.filter((n) => n.beat > b0 + 0.05).map((n) => ({ ...n, beat: n.beat - b0 }));
        engine.preview(renderPart(notes, { bpm, sampleRate: engine.sampleRate, transpose: s.session.project.transpose || 0 }), engine.sampleRate);
        await new Promise((r) => setTimeout(r, 1800));"""},
]

# The studio: Amazing Grace with the soprano, alto and tenor sung (by the tests' synthesized singer,
# help_shots.py's sung_takes) and playing, the playhead crossing every track.
STUDIO_STEPS = [
    {'go': '/voice', 'wait': "document.querySelector('#app header, #app main')", 'js': SING_READY},
    {'go': '/p/{ret}', 'wait': "document.querySelectorAll('canvas').length > 1"},
    {'wait': "window.__s || import('/js/stores/session.js').then((m) => (window.__s = m)) && false"},
    {'wait': 'window.__s.session.parts.length > 3', 'js': """const s = window.__s; await (await import('/js/audio.js')).unlock();
        for (const p of s.session.parts.filter((p) => p.templatePartKey)) await s.setVoice(p, 'synth');
        const { sing } = await import('/tests/fixtures.js'); const { peaks } = await import('/js/dsp.js');
        for (const key of ['soprano', 'alto', 'tenor']) {
            const part = s.session.parts.find((p) => p.templatePartKey === key), sr = 48000;
            const sung = sing(sr, { notes: s.templatePart(part).guide.notes, bpm: s.session.project.bpm, vibrato: { hz: 5.5, cents: 14 }, level: 0.28, noise: 0.002, seed: key.length });
            const pad = Math.round(0.6 * sr), take = new Float32Array(pad + sung.length + pad); take.set(sung, pad);
            await s.storeTake(part, { offsetSeconds: -0.6, nudgeSeconds: 0, trimStartFrames: 0, trimEndFrames: 0, recordedAt: Date.now() - 3600000, score: null, scoring: null,
                sampleRate: sr, frames: take.length, framesPerPeak: 512, peaks: peaks(take, 512) }, take);
        }
        await new Promise((r) => setTimeout(r, 600));
        const play = (await import('/js/i18n.js')).t('studio.play');
        [...document.querySelectorAll('button')].find((b) => b.getAttribute('aria-label') === play || b.textContent.trim() === play)?.click();
        await new Promise((r) => setTimeout(r, 1200));"""},
]

# The day's challenge (daily_challenge.py): the course begun three days ago with today's five minutes
# sung, and the example's challenge dated today.
DAILY_SEED = """const db = await import('/js/db.js'); const { emptyProgress, dayOf } = await import('/js/course.js');
    await db.put('learning', { ...emptyProgress(), startedAt: Date.now() - 3 * 86400000, home: 57, range: { low: 50, high: 67 },
        practice: { [dayOf(Date.now())]: 300 }, lessons: { '1-comfortable': { stars: 3, bestPercent: 95, at: Date.now() - 86400000 } } }, 'progress');"""
DAILY_TODAY = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    const today = new Date().toISOString().slice(0, 10);
    api.useTransport(async (url, init = {}) => {
        const answer = await mockFetch(url, init);
        if (init.operation !== 'boards.daily.show') return answer;
        const json = await answer.json(); json.data.date = today; delete json.data.cup;
        return new Response(JSON.stringify(json), { status: 200, headers: { 'Content-Type': 'application/json' } });
    });
    const p = await import('/js/daily-pick.js'); p.dailyPick.today = null; p.dailyPick.asked = null;
    if (document.documentElement.dataset.skin === 'game') await (await import('/js/game/daily.js')).loadDaily(); else await p.loadPick();
    await new Promise((r) => setTimeout(r, 500));
    document.querySelector('[data-today]')?.scrollIntoView({ block: 'center' }); await new Promise((r) => setTimeout(r, 300));"""

# ---------------------------------------------------------------- the shots
# id: size, skin, url (APP or SITE), wait (JS, true once it's ready), then (JS run after, awaited),
# and for a recording `clip`: how its frames are made.
SHOTS = {
    # Learn: Sing on a desktop, recording the alto, in step with the song from its first line
    'sing': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/voice', 'steps': SING_STEPS, 'swap': HEARING, 'clip': {'kind': 'cast', 'seconds': 5.6, 'fps': 30, 'from': 4.0}},
    # Stack: the studio, three parts sung and playing, the bass next
    'studio': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/voice', 'steps': STUDIO_STEPS, 'clip': {'kind': 'cast', 'seconds': 5.6, 'fps': 30, 'from': 8.5}},
    # Game mode: the landing's own switch clicked, the page turning arcade
    'landing-toggle': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/', 'wait': "document.querySelector('.gt--hero')",
                       'clip': {'kind': 'cast', 'seconds': 3.2, 'fps': 30, 'click': '.gt--hero', 'clickAt': 1.2}},
    # the same take in the arcade, in step with the song where the film shows it
    'arcade': {'size': 'desktop', 'skin': 'game', 'url': 'APP/voice', 'steps': SING_STEPS, 'swap': HEARING, 'clip': {'kind': 'cast', 'seconds': 4.4, 'fps': 30, 'from': 19.9}},
    # the arcade's Home: the stage card
    'home-game': {'size': 'desktop', 'skin': 'game', 'url': 'APP/?api=mock&scenario=&as=member', 'wait': "document.querySelector('#app header')"},
    # every course: the site's page, scrolled through its groups
    'courses': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/courses', 'wait': "document.querySelector('h1')",
                'clip': {'kind': 'scroll', 'seconds': 4.5, 'fps': 30, 'to': "document.querySelector('#group-harmony').getBoundingClientRect().top + scrollY - 140"}},
    # the first lesson as a guest, no account, on a phone (the phone is the point: anyone can start)
    'lesson': {'size': 'phone', 'skin': 'serious', 'url': 'APP/learn/1-comfortable?api=mock&scenario=', 'wait': "document.querySelector('main h1')"},
    # the tools: each one's own panel, which the film shows on its own card (crop: where it is)
    'tuner': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/tuner', 'wait': "document.querySelector('[data-start]')", 'mic': True, 'crop': '[data-ui="tool"]',
              'then': "window.__hz = 440 * 2 ** (3 / 1200); document.querySelector('[data-start]').click(); for (let i = 0; i < 60 && !/In tune/.test(document.querySelector('[data-status-text]')?.textContent ?? ''); i++) await new Promise((r) => setTimeout(r, 200)); await new Promise((r) => setTimeout(r, 2500)); if (!/In tune/.test(document.querySelector('[data-status-text]').textContent)) throw new Error('the tuner never heard the note in tune');"},
    'drone': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/drone', 'wait': "document.querySelector('[data-ui=tool-primary]')", 'crop': '[data-ui="tool"]',
              'then': "document.querySelector('[data-ui=tool-primary]').click(); await new Promise((r) => setTimeout(r, 1500));"},
    'metronome': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/metronome', 'wait': "document.querySelector('[data-ui=tool-primary]')", 'crop': '[data-ui="tool"]',
                  'then': "document.querySelector('[data-ui=tool-primary]').click(); await new Promise((r) => setTimeout(r, 1300));"},
    'range': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/vocal-range-test', 'wait': "document.querySelector('[data-ui=tool-primary]')", 'mic': True, 'crop': '[data-ui="tool"]',
              'then': RANGE_SUNG},
    # Choirs: the director's group, and a member's
    'choir-director': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/groups/Gp4rQs?api=mock&scenario=&as=plus', 'wait': "document.querySelector('h1')"},
    'choir-member': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/groups/Gp4rQs?api=mock&as=member', 'wait': "document.querySelector('[data-my-part]')",
                     'pre': "sessionStorage.setItem('stackapella.mockChoirMember', '1')"},
    # Every day: today's song on Home (daily_challenge.py's seed: day 3 of the course, today's 5 minutes sung)
    'daily': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/?api=mock&scenario=&as=member', 'wait': "document.querySelector('#app header')",
              'steps': [{'js': DAILY_SEED}, {'go': '/?api=mock&scenario=&as=member', 'wait': "document.querySelector('[data-lead]') && !document.querySelector('[data-lead] .skeleton')", 'js': DAILY_TODAY}]},
}


def shoot(cut, sid, spec):
    w, h, mobile, dpr = SIZES[spec['size']]
    mode = 'light' if cut == 'light' else 'dark'
    c = Chrome(None, w, h, mobile)
    try:
        c.send('Emulation.setDeviceMetricsOverride', {'width': w, 'height': h, 'deviceScaleFactor': dpr, 'mobile': mobile})
        c.send('Emulation.setScrollbarsHidden', {'hidden': True})
        for src in [appearance(spec['skin'], mode), f"const SWAP = {json.dumps(spec.get('swap', []))};" + RENAME, spec.get('pre', '')] + ([FAKE_MIC] if spec.get('mic') else []):
            c.send('Page.addScriptToEvaluateOnNewDocument', {'source': src})
        url = spec['url'].replace('APP', APP).replace('SITE', SITE)
        c.goto(url, spec.get('wait') or "document.readyState === 'complete'", timeout=30)
        c.evaluate('document.fonts.ready.then(() => new Promise((r) => setTimeout(r, 900)))')
        if spec.get('then'):
            c.evaluate(f'(async () => {{ {spec["then"]} }})()')
        ret = None
        for step in spec.get('steps', []):
            if 'go' in step:
                c.goto(APP + step['go'].replace('{ret}', str(ret)), step.get('wait'), timeout=40)
            elif 'wait' in step:
                until(c, step['wait'], step.get('timeout', 40))
            if 'key' in step:
                for kind in ('keyDown', 'keyUp'):
                    c.send('Input.dispatchKeyEvent', {'type': kind, 'key': step['key'], 'code': 'Key' + step['key'].upper(), **({'text': step['key']} if kind == 'keyDown' else {})})
            if 'js' in step:
                ret = c.evaluate(f'(async () => {{ {step["js"]} }})()')
        out = OUT / cut
        out.mkdir(parents=True, exist_ok=True)
        clip = spec.get('clip')
        entry = {'id': sid, 'size': spec['size'], 'skin': spec['skin'], 'w': w * dpr, 'h': h * dpr}
        if spec.get('crop'):
            # where the thing itself is (a tool's own panel), as shares of the screen, inside the screen
            r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(spec['crop'])}).getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; }})()")
            x0, y0, x1, y1 = max(0, r[0]) / w, max(0, r[1]) / h, min(w, r[2]) / w, min(h, r[3]) / h
            entry['crop'] = {'x': round(x0, 4), 'y': round(y0, 4), 'w': round(x1 - x0, 4), 'h': round(y1 - y0, 4)}
        if not clip:
            jpeg(c, out / f'{sid}.jpg')
            return entry
        d = out / sid
        d.mkdir(exist_ok=True)
        for old in d.glob('*.jpg'):
            old.unlink()
        n = round(clip['seconds'] * clip['fps'])
        if clip['kind'] == 'scroll':
            end = c.evaluate(clip['to'])
            for k in range(n):
                u = k / (n - 1)
                eased = u * u * (3 - 2 * u)  # smooth start and stop, like a flick that settles
                c.evaluate(f'window.scrollTo(0, {end * eased:.1f}); new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))')
                jpeg(c, d / f'{k:04d}.jpg')
        extra = {}
        if clip['kind'] == 'cast':
            if 'from' in clip:
                # in step with the song: start once the take or the playback reaches `from` seconds
                until(c, f"window.__s.session.position >= {clip['from']}", 60)
            if 'click' in clip:
                r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(clip['click'])}).getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }})()")
                extra['click'] = {'x': r[0] / w, 'y': r[1] / h, 'at': clip['clickAt']}
            at = c.evaluate('window.__s?.session?.position ?? null') if 'from' in clip else None
            cast(c, d, clip['seconds'], clip['fps'], w * dpr, h * dpr, clip.get('click') and (clip['clickAt'], clip['click']))
            if at is not None:
                extra['songAt'] = round(at, 3)
        return {**entry, 'frames': n, 'fps': clip['fps'], **extra}
    finally:
        c.close()


def until(c, condition, timeout):
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            if c.evaluate(f'!!({condition})', await_promise=False):
                return
        except RuntimeError:
            pass
        time.sleep(0.2)
    raise TimeoutError(f'Timed out waiting for: {condition}')


def cast(c, d, seconds, fps, w, h, click=None):
    """A real-time recording: Chrome's screencast for `seconds`, its frames (each with its own time)
    resampled to `fps` by taking the newest frame at each tick."""
    got = []
    plain = c.on_event

    def on_event(message):
        if message.get('method') == 'Page.screencastFrame':
            p = message['params']
            got.append((p['metadata']['timestamp'], p['data']))
            c.send('Page.screencastFrameAck', {'sessionId': p['sessionId']})
            return
        plain(message)
    c.on_event = on_event
    c.send('Page.startScreencast', {'format': 'jpeg', 'quality': 90, 'maxWidth': w, 'maxHeight': h, 'everyNthFrame': 1})
    start = time.time()
    end = start + seconds + 0.5
    while time.time() < end:
        if click and time.time() - start >= click[0]:
            c.evaluate(f'document.querySelector({json.dumps(click[1])}).click()')
            click = None
        c.evaluate('new Promise((r) => setTimeout(r, 15))')
    c.send('Page.stopScreencast')
    c.on_event = plain
    if len(got) < seconds * 8:
        raise RuntimeError(f'only {len(got)} frames in {seconds}s')
    t0 = got[0][0]
    k = 0
    for i in range(round(seconds * fps)):
        at = t0 + i / fps
        while k + 1 < len(got) and got[k + 1][0] <= at:
            k += 1
        (d / f'{i:04d}.jpg').write_bytes(base64.b64decode(got[k][1]))
    print(f'  {len(got)} frames cast in {seconds}s')


def jpeg(c, path):
    data = base64.b64decode(c.send('Page.captureScreenshot', {'format': 'jpeg', 'quality': 90})['data'])
    path.write_bytes(data)


def main():
    only = str(A['only']).split(',') if 'only' in A else list(SHOTS)
    cuts = [A['cut']] if 'cut' in A else ['dark', 'light']
    index = HERE / 'captures.json'
    listed = json.loads(index.read_text()) if index.exists() else {'shots': {}}
    for cut in cuts:
        for sid in only:
            entry = shoot(cut, sid, SHOTS[sid])
            listed['shots'][sid] = entry
            print(cut, sid, 'ok')
    listed['shots'] = dict(sorted(listed['shots'].items()))
    index.write_text(json.dumps(listed, indent=2) + '\n')


if __name__ == '__main__':
    main()
