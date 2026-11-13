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
# Two public pages are filmed as they're served: the site's /open-source (behind a flag the local
# server's shared database keeps off) and GitHub's page for the Stackapella organisation, signed out.
# Both are public and show no one's data; nothing else is read from production.
# the live site's pages (--live= points them at a local server, to film a page before it deploys)
LIVE = str(A.get('live', 'https://stackapella.com')).rstrip('/')
SITE = str(A.get('site', 'http://127.0.0.1:8461')).rstrip('/')
# --lang=es films the app and the site in that language (each language's film is its own render): the app with the
# language chosen on the device, as Settings keeps it, and the site's pages at /<lang>/. Its captures are their own
# (captures/<lang>/, captures.<lang>.json), so English's are never touched.
LANG = str(A['lang']) if 'lang' in A and A['lang'] != 'en' else None
OUT = HERE / 'captures' / LANG if LANG else HERE / 'captures'
# The mock's settings answer (its auth.config example) says English alone, as the server does until the language's
# switch is on; here it says the language is on, as it will be when its film is served.
CHOOSE_LANG = ("""try{localStorage.setItem('stackapella.lang',%s);localStorage.setItem('stackapella.languages',%s)}catch(e){}
(() => { const f = window.fetch; window.fetch = async (u, i) => { const r = await f(u, i);
  if (!String(u && u.url || u).includes('auth.config.200.json')) return r;
  const j = await r.json(); j.data.languages = [%s];
  return new Response(JSON.stringify(j), { status: 200, headers: { 'Content-Type': 'application/json' } }); }; })();"""
               % (json.dumps(LANG), json.dumps(json.dumps(['en', LANG])), json.dumps(LANG))) if LANG else ''

# Phone shots stand tall in the frame (about 860 film px), so twice the phone's own pixels. Desktop
# shots are a 1440-wide window pushed in on the panel that matters (Hillel: "there should be desktop
# shots"), so twice its pixels too.
SIZES = {'phone': (390, 844, True, 2), 'desktop': (1440, 900, False, 2),
         # a desktop the film pushes in on hard (the carols' hero, framed clear of its countdown), at three times its pixels
         'desktop-close': (1440, 900, False, 3)}

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

def sing_steps(part):
    """Sing recording `part` over loopback (SING_STEPS is the alto's): the same steps for any part."""
    return [{**st, 'js': st['js'].replace("'alto'", repr(part))} if 'js' in st else st for st in SING_STEPS]


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
HEARING = [['Nothing plays while you record: your guide and every other part are off.', 'Hearing every other part. Your guide is off.'],
           # the same two lines of the app's in Spanish (web/locales/es.json sing.hear: recordingNone; recording with everyOther, guideOffRecording)
           ['Mientras grabas no suena nada: tu guía y las demás voces están apagadas.', 'Oyes todas las demás voces. Tu guía está apagada.']]
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

def studio_live(stored, live):
    """v5: the studio with `stored` parts sung (as STUDIO_STEPS sings them) and `live` recording now, from
    the start, its wave drawing in as the loopback hears its own notes (the way SING_STEPS records)."""
    store = STUDIO_STEPS[3]['js'].split("await new Promise((r) => setTimeout(r, 600));")[0].replace("['soprano', 'alto', 'tenor']", json.dumps(stored))
    return STUDIO_STEPS[:3] + [
        {'wait': 'window.__s.session.parts.length > 3', 'js': store + """
        const a = await import('/js/audio.js'); a.unlock(); await a.openMic(); a.engine.setLoopback(true);
        s.session.project.countInBars = 0;
        s.session.rerecordId = s.session.parts.find((p) => p.templatePartKey === %s).id;
        await new Promise((r) => setTimeout(r, 800));
        await s.startRecording({ fromStart: true });""" % json.dumps(live)},
        {'wait': 'window.__s.session.recording && window.__s.session.countIn === null && window.__s.session.position > 0.05',
         'js': """const s = window.__s, { engine } = await import('/js/audio.js'), { renderPart } = await import('/js/synth.js');
        const bpm = s.session.project?.tempo ?? 80, b0 = s.session.position * bpm / 60, part = s.session.parts.find((p) => p.templatePartKey === %s);
        const notes = s.templatePart(part).guide.notes.filter((n) => n.beat > b0 + 0.05).map((n) => ({ ...n, beat: n.beat - b0 }));
        engine.preview(renderPart(notes, { bpm, sampleRate: engine.sampleRate, transpose: s.session.project.transpose || 0 }), engine.sampleRate);""" % json.dumps(live)},
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

# A songbook, scrolled so its heading and I sing sit at the very top and each card's Alto row is in the film's frame
BOOK = "const b = document.querySelector('[data-songbook]'); scrollTo(0, b.getBoundingClientRect().top + scrollY + 66); await new Promise((r) => setTimeout(r, 600));"

# ---------------------------------------------------------------- the shots
# id: size, skin, url (APP or SITE), wait (JS, true once it's ready), then (JS run after, awaited),
# and for a recording `clip`: how its frames are made.
# v5, "The Stack" (docs/showreel-v5-plan.md): the parts sung in Sing and the studio, each where the film shows it
# (`from`: the song's position the recording starts at), the tools working, and the components lifted from pages.
COURSE_CARD = "(() => { const r = document.querySelector('section.s-sec').getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; })()"
# GitHub's repo card, above its row of language, stars and forks (no numbers)
GH_CARD = "(() => { const c = document.querySelector('.pinned-item-list-item'), r = c.getBoundingClientRect(), last = [...c.querySelectorAll('p')].at(-1).getBoundingClientRect(); return [r.left, r.top, r.right, last.top - 2]; })()"
V5 = {
    'sing-soprano': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/voice', 'steps': sing_steps('soprano'), 'swap': HEARING, 'clip': {'kind': 'cast', 'seconds': 15.0, 'fps': 30, 'from': 3.9}},
    'flip-arcade': {'size': 'desktop', 'skin': 'game', 'url': 'APP/voice', 'steps': sing_steps('soprano'), 'swap': HEARING, 'clip': {'kind': 'cast', 'seconds': 5.0, 'fps': 30, 'from': 12.9}},
    'sing-tenor-phone': {'size': 'phone', 'skin': 'serious', 'url': 'APP/voice', 'steps': sing_steps('tenor'), 'swap': HEARING, 'clip': {'kind': 'cast', 'seconds': 5.0, 'fps': 30, 'from': 21.9}},
    'studio-alto': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/voice', 'steps': studio_live(['soprano'], 'alto'), 'clip': {'kind': 'cast', 'seconds': 5.0, 'fps': 30, 'from': 12.9}},
    'studio-alto-phone': {'size': 'phone', 'skin': 'serious', 'url': 'APP/voice', 'steps': studio_live(['soprano'], 'alto'), 'clip': {'kind': 'cast', 'seconds': 5.0, 'fps': 30, 'from': 22.4}},
    'studio-bass': {'size': 'desktop', 'skin': 'serious', 'url': 'APP/voice', 'steps': studio_live(['soprano', 'alto', 'tenor'], 'bass'), 'clip': {'kind': 'cast', 'seconds': 5.0, 'fps': 30, 'from': 30.9}},
    'tuner-live': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/tuner', 'wait': "document.querySelector('[data-start]')", 'mic': True,
                   'then': "window.__hz = 440 * 2 ** (-30 / 1200); document.querySelector('[data-start]').click(); await new Promise((r) => setTimeout(r, 1500));",
                   'clip': {'kind': 'clipcast', 'el': '[data-ui="tool"]', 'seconds': 4.0, 'fps': 30,
                            'start': "for (let i = 0; i <= 40; i++) { window.__hz = 440 * 2 ** (-30 * (1 - Math.min(1, i / 24)) / 1200); await new Promise((r) => setTimeout(r, 90)); }"}},
    'drone-live': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/drone', 'wait': "document.querySelector('[data-ui=tool-primary]')",
                   'then': "document.querySelector('[data-ui=tool-primary]').click(); await new Promise((r) => setTimeout(r, 900));",
                   'clip': {'kind': 'clipcast', 'el': '[data-ui="tool"]', 'seconds': 4.0, 'fps': 30}},
    'metro-live': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/metronome', 'wait': "document.querySelector('[data-bpm]')",
                   'then': "const i = document.querySelector('[data-bpm]'); i.value = '80'; i.dispatchEvent(new Event('input', { bubbles: true })); i.dispatchEvent(new Event('change', { bubbles: true })); await new Promise((r) => setTimeout(r, 400));",
                   'clip': {'kind': 'clipcast', 'el': '[data-ui="tool"]', 'seconds': 4.0, 'fps': 30, 'start': "document.querySelector('[data-ui=tool-primary]').click();"}},
    'range-live': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/vocal-range-test', 'wait': "document.querySelector('[data-ui=tool-primary]')", 'mic': True,
                   'clip': {'kind': 'clipcast', 'el': '[data-ui="tool"]', 'seconds': 9.0, 'fps': 30, 'start': RANGE_SUNG}},
}

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
    # THE SEASON'S SLOT (film.js SEASONS): each season is its page's top, a recording, then its songbook
    # with I sing set to Alto. The carols: the lights, the snow falling, the Santa hat on the logo.
    'carols': {'size': 'desktop-close', 'skin': 'serious', 'url': 'SITE/songs/christmas-carols', 'wait': "document.querySelector('[data-snow]')",
               'clip': {'kind': 'anim', 'seconds': 2.8, 'fps': 30}},
    'carols-book': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/songs/christmas-carols', 'wait': "document.querySelector('[data-songbook]')", 'then': BOOK,
                    'clip': {'kind': 'anim', 'seconds': 2.4, 'fps': 30, 'click': '[data-sing="alto"]', 'clickAt': 0.7}},
    # the Hanukkah cut's (render.py --cut=hanukkah): the Jewish songs page as it is served (a public page, no one's
    # data): the nine lights, the heading and the next festival's card
    'hanukkah': {'size': 'desktop-close', 'skin': 'serious', 'url': 'LIVE/songs/jewish-songs', 'wait': "document.querySelector('.s-jcount')"},
    # the Amazing Grace Reddit cut's (render.py --cut=gracer): the song's own page as it is served (public, no one's data)
    'grace': {'size': 'desktop-close', 'skin': 'serious', 'url': 'LIVE/songs/amazing-grace', 'wait': "document.querySelector('h1')"},
    # January's: the hymns (/songs/hymns), or the whole songbook (/songs)
    'hymns': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/songs/hymns', 'wait': "document.querySelector('h1')"},
    'hymns-book': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/songs/hymns', 'wait': "document.querySelector('[data-songbook]')", 'then': BOOK,
                   'clip': {'kind': 'anim', 'seconds': 2.4, 'fps': 30, 'click': '[data-sing="alto"]', 'clickAt': 0.7}},
    'songbook': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/songs', 'wait': "document.querySelector('h1')"},
    'songbook-book': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/songs', 'wait': "document.querySelector('h1')",
                      'clip': {'kind': 'scroll', 'seconds': 2.4, 'fps': 30, 'to': "document.querySelector('#songs-free-h').getBoundingClientRect().top + scrollY - 120"}},
    # every course: the site's page, scrolled through its groups
    'courses': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/courses', 'wait': "document.querySelector('h1')",
                'clip': {'kind': 'scroll', 'seconds': 3.0, 'fps': 30, 'to': "document.querySelector('#group-harmony').getBoundingClientRect().top + scrollY - 140"}},
    # the first lesson as a guest, no account, on a phone (the phone is the point: anyone can start)
    'lesson': {'size': 'phone', 'skin': 'serious', 'url': 'APP/learn/1-comfortable?api=mock&scenario=', 'wait': "document.querySelector('main h1')"},
    # the tools: each one's own panel, which the film shows on its own card (crop: where it is)
    'tuner': {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/tools/tuner', 'wait': "document.querySelector('[data-start]')", 'mic': True, 'crop': '[data-ui="tool"]',
              'then': "window.__hz = 440 * 2 ** (3 / 1200); document.querySelector('[data-start]').click(); for (let i = 0; i < 60 && !/In tune|Afinado/.test(document.querySelector('[data-status-text]')?.textContent ?? ''); i++) await new Promise((r) => setTimeout(r, 200)); await new Promise((r) => setTimeout(r, 2500)); if (!/In tune|Afinado/.test(document.querySelector('[data-status-text]').textContent)) throw new Error('the tuner never heard the note in tune');"},
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
    # Open source: the site's page, the pointer on View on GitHub; then GitHub's page for the organisation
    'open-source': {'size': 'desktop', 'skin': 'serious', 'url': 'LIVE/open-source', 'wait': "document.querySelector('h1')", 'point': 'a.btn-primary[href="https://github.com/stackapella/stackapella"]'},
    'github': {'size': 'desktop-close', 'skin': 'serious', 'url': 'https://github.com/Stackapella', 'wait': "document.querySelector('[itemprop=name], h1')", 'scheme': True},
    # Every day: the daily challenge on Home, in the arcade (daily_challenge.py's seed: day 3 of the course, today's 5 minutes sung)
    'daily': {'size': 'desktop', 'skin': 'game', 'url': 'APP/?api=mock&scenario=&as=member', 'wait': "document.querySelector('#app header')",
              'steps': [{'js': DAILY_SEED}, {'go': '/?api=mock&scenario=&as=member', 'wait': "document.querySelector('[data-lead]') && !document.querySelector('[data-lead] .skeleton')", 'js': DAILY_TODAY}]},
}
SHOTS.update(V5)
# the components v5 lifts out of the pages it already films
SHOTS['courses-card'] = {'size': 'desktop', 'skin': 'serious', 'url': 'SITE/courses', 'wait': "document.querySelector('h1')", 'crops': {'card': COURSE_CARD, 'head': 'section.s-featurehead'}}
SHOTS['github']['crops'] = {'card': GH_CARD}
SHOTS['choir-director']['crops'] = {'song': 'li.card'}
SHOTS['choir-member']['crops'] = {'song': 'section li.card, section .card'}
SHOTS['daily']['crops'] = {'stage': '[data-lead]'}
SHOTS['hanukkah']['crops'] = {'count': '.s-jcount', 'head': '.s-xhero-text h1', 'text': '.s-xhero-text', 'motif': "(() => { const e = document.querySelector('.s-tone-motif'); if (!e) return null; const r = e.getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; })()",
                             'hero': '.s-xhero',
                             # Collections v2's hanukkiah in the festival card (the Reddit cut opens on it); null, so left out, without it
                             'lamp': "(() => { const e = document.querySelector('.s-jcount .s-hk-card'); if (!e) return null; const r = e.getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; })()"}
SHOTS['grace']['crops'] = {'head': 'section.s-head', 'title': 'h1', 'mixer': '.s-mixer', 'actions': '.s-actions'}
SHOTS['carols-book']['crops'] = {'card': '[data-songbook] .s-xcard', 'filter': '.s-xfilter',
                                 'row': "(() => { const c = [...document.querySelectorAll('[data-songbook] .s-xcard')].slice(0, 3).map((e) => e.getBoundingClientRect()), f = document.querySelector('.s-xfilter').getBoundingClientRect(); return [c[0].left, f.top - 8, c[2].right, c[0].bottom]; })()"}


def shoot(cut, sid, spec):
    w, h, mobile, dpr = SIZES[spec['size']]
    mode = 'light' if cut == 'light' else 'dark'
    c = Chrome(None, w, h, mobile)
    try:
        c.send('Emulation.setDeviceMetricsOverride', {'width': w, 'height': h, 'deviceScaleFactor': dpr, 'mobile': mobile})
        c.send('Emulation.setScrollbarsHidden', {'hidden': True})
        for src in [appearance(spec['skin'], mode), f"const SWAP = {json.dumps(spec.get('swap', []))};" + RENAME, spec.get('pre', ''), CHOOSE_LANG] + ([FAKE_MIC] if spec.get('mic') else []):
            c.send('Page.addScriptToEvaluateOnNewDocument', {'source': src})
        if spec.get('scheme'):
            # a page that follows the device's light or dark (GitHub's), shown in the cut's
            c.send('Emulation.setEmulatedMedia', {'features': [{'name': 'prefers-color-scheme', 'value': mode}]})
        url = spec['url'].replace('APP', APP).replace('SITE', SITE + (f'/{LANG}' if LANG else '')).replace('LIVE', LIVE) if not spec['url'].startswith('https://github.com') else spec['url']
        c.goto(url, spec.get('wait') or "document.readyState === 'complete'", timeout=30)
        c.evaluate('document.fonts.ready.then(() => new Promise((r) => setTimeout(r, 900)))')
        if LANG:
            # a language not yet switched on is served locally with a preview note at its top (SiteLanguage::preview);
            # the page is filmed as it will be once the language is on, without it
            c.evaluate("document.querySelector('body > div[role=note]')?.remove()")
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
        if spec.get('point'):
            # where the film's pointer clicks on a still (nothing is clicked here)
            r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(spec['point'])}).getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }})()")
            entry['click'] = {'x': round(r[0] / w, 4), 'y': round(r[1] / h, 4), 'at': 0}
        if spec.get('crops'):
            # v5's lifted components: each named element's rect (a selector, or JS giving [l, t, r, b]), as shares of the screen
            entry['crops'] = {}
            for name, how in spec['crops'].items():
                js = how if how.lstrip().startswith('(') else f"(() => {{ const r = document.querySelector({json.dumps(how)}).getBoundingClientRect(); return [r.left, r.top, r.right, r.bottom]; }})()"
                rect = c.evaluate(js)
                if rect is None:
                    continue
                l, t_, r_, b = rect
                entry['crops'][name] = {'x': round(max(0, l) / w, 4), 'y': round(max(0, t_) / h, 4), 'w': round((min(w, r_) - max(0, l)) / w, 4), 'h': round((min(h, b) - max(0, t_)) / h, 4)}
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
        if clip['kind'] == 'anim':
            # stepped, not cast: every CSS animation and transition held and set to each frame's time, then a
            # full-resolution screenshot (a screencast is only CSS pixels), so the frames are sharp and even
            if 'click' in clip:
                r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(clip['click'])}).getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }})()")
                extra['click'] = {'x': round(r[0] / w, 4), 'y': round(r[1] / h, 4), 'at': clip['clickAt']}
            clicked = False
            for k in range(n):
                ms = k / clip['fps'] * 1000
                if 'click' in clip and not clicked and ms >= clip['clickAt'] * 1000:
                    c.evaluate(f"document.querySelector({json.dumps(clip['click'])}).click()")
                    clicked = True
                c.evaluate(f"(() => {{ for (const a of document.getAnimations()) {{ a.__k0 ??= {ms}; a.__t0 ??= (a.currentTime ?? 0); a.pause(); a.currentTime = a.__t0 + {ms} - a.__k0; }} }})();"
                           " new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)))")
                jpeg(c, d / f'{k:04d}.jpg')
        if clip['kind'] == 'clipcast':
            # a live panel (a tool working), sharp: screenshots clipped to the element at full resolution,
            # as fast as they come (about 20 a second), each with its time, resampled to fps
            r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(clip['el'])}).getBoundingClientRect(); return [r.left, r.top, r.width, r.height]; }})()")
            box = {'x': r[0], 'y': r[1], 'width': r[2], 'height': r[3], 'scale': 1}
            entry.update({'w': round(r[2] * dpr), 'h': round(r[3] * dpr), 'lifted': True})
            if clip.get('start'):
                c.evaluate(f'(async () => {{ {clip["start"]} }})()', await_promise=False)
            got, t0 = [], time.time()
            while time.time() - t0 < clip['seconds'] + 0.2:
                at = time.time() - t0
                got.append((at, c.send('Page.captureScreenshot', {'format': 'jpeg', 'quality': 90, 'clip': box})['data']))
            k = 0
            for i in range(n):
                while k + 1 < len(got) and got[k + 1][0] <= i / clip['fps']:
                    k += 1
                (d / f'{i:04d}.jpg').write_bytes(base64.b64decode(got[k][1]))
            print(f'  {len(got)} clips in {clip["seconds"]}s')
        if clip['kind'] == 'cast':
            if 'from' in clip:
                # in step with the song: start once the take or the playback reaches `from` seconds
                until(c, f"window.__s.session.position >= {clip['from']}", 60)
            if 'click' in clip:
                r = c.evaluate(f"(() => {{ const r = document.querySelector({json.dumps(clip['click'])}).getBoundingClientRect(); return [r.left + r.width / 2, r.top + r.height / 2]; }})()")
                extra['click'] = {'x': r[0] / w, 'y': r[1] / h, 'at': clip['clickAt']}
            at = c.evaluate('window.__s?.session?.position ?? null') if 'from' in clip else None
            cast(c, d, clip['seconds'], clip['fps'], w * dpr, h * dpr, clip.get('click') and (clip['clickAt'], clip['click']), clip.get('sparse', False))
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


def cast(c, d, seconds, fps, w, h, click=None, sparse=False):
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
    # a page that's still between changes sends few frames (`sparse`); a moving one that sends few is broken
    if len(got) < (2 if sparse else seconds * 8):
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
    index = HERE / (f'captures.{LANG}.json' if LANG else 'captures.json')
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
