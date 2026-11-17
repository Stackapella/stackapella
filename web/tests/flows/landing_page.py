"""The landing, the site's home page at / and /es (pages/landing.blade.php, js/public/landing.js, css/landing.css; Hillel,
2026-11-16: "make next-final the main home page"): a stage that fills the screen for five scenes, the song's four lanes
on it, then a page that scrolls, the site's footer, and a dock that stays. On a desktop and a phone:

- with no light or dark chosen, the calm page is the device's from the first paint, the browser bar's colour too (a song
  page too);
- the first frame is the server's: the headline, the four lanes and every gem are there before any script, and nothing
  sounds until a press (the recorded voices start coming in a quiet moment);
- only the recorded voices sing (they're stubbed here: this machine's database has none): a press waits for their
  download and shows how far it is, a second press cancels, a failure says so and the next press tries again, and the
  app's practice voice is never made; the page is its own document with its own small sheet and the Game
  mode switch's (none of the site's, no game sheet);
- Play plays: the page says so, the time and the song's place move, gems light and the sung line follows; the control
  says Stop (a square, never a pause), and Stop puts the song back at its start;
- the song builds as the showreel does: both passes in the time, only the first lane lit at the start with the others
  waiting, and the stage says who sings;
- a lane heard alone, then all four again, from the same spot (the place never goes back), said to a screen reader; a
  voice alone wins over the joins (no lane waits while one is), and the lanes still to join wait again after it;
- the dock is Play, the time and Start singing (no voice buttons); the Hear scene's button and the Learn links name the
  voice last heard alone and lead to its part;
- down the page, a strip of lanes in view is the song itself while it plays, and a picture again once it stops;
- the top line's Game mode switch is the site's: it turns the skin, the choice is kept, and the page puts on game mode's
  dress (css/landing-game.css, loaded then), or stays calm while there is none;
- the stage's scenes follow the scroll, 0 to 4; after Share the stage lets go and the rest scrolls as a page: nothing
  scrolls inside itself, the lanes end above the dock in every scene, and the last question clears the dock;
- scrolling never stops or moves the song;
- the keyboard: Play is the first control in the page's main part, the lanes are one stop (the arrows move between them,
  Space hears one alone), and focus in a scene of the stage off the screen brings its scene;
- light and dark by the top line's switch;
- reduced motion: nothing is pinned, every scene shows, and the song still plays with its gems lit in place;
- it's the site's home: its canonical address, the site's footer with its links, the Start links marked for the funnel
  (from=start), its counts sent as the landing's (site_visit and its section), and the candidates' old addresses lead
  to it;
- in the game skin it's the same calm page until game mode's sheet for it exists (css/landing-game.css), then its dress;
- Spanish at /es; no console errors anywhere.

Runs against the site (SITE, as landing_audio.py):  SITE=http://127.0.0.1:8412 python3 -I tests/flows/landing_page.py
"""
import os
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import flow  # noqa: E402

SITE = os.environ.get("SITE", "http://127.0.0.1:8412")
READY = "document.querySelector('[data-lnf]')?.dataset.ready !== undefined && document.getElementById('lnf')?.hasAttribute('data-ready')"
# Light or dark, from the top line's Language and appearance (the site header's own pill; Hillel, 2026-11-16: "combine
# the two buttons"): the menu opened, the other look pressed, the menu closed.
FLIP = """(() => { const menu = document.querySelector('.lnf-top [data-look-menu]'); menu.open = true;
    const light = (document.documentElement.dataset.theme ?? '').endsWith('-light');
    menu.querySelector(`[data-look-mode=${light ? 'dark' : 'light'}]`).click(); menu.open = false; })()"""
# Every beacon the page sends, and every request for sound, kept for the checks.
WATCH = """window.__beacons = []; const send = Navigator.prototype.sendBeacon;
    Navigator.prototype.sendBeacon = function (url, data) { try { data?.text?.().then((t) => window.__beacons.push([String(url), t])); } catch (e) { window.__beacons.push([String(url), '']); } return send.apply(this, arguments); };"""
STATE = """(() => { const s = document.getElementById('lnf'); return {
    playing: s.hasAttribute('data-playing'), p: Number.parseFloat(s.style.getPropertyValue('--p')) || 0,
    t: Number.parseFloat(s.style.getPropertyValue('--lnf-t')) || 0,
    lit: s.querySelectorAll('.lnf-g[data-ring]').length, sung: s.querySelectorAll('.lnf-g[data-state=settled]').length,
    line: [...s.querySelectorAll('.lnf-line')].find((l) => !l.hidden)?.textContent.trim() ?? '',
    now: s.querySelectorAll('.lnf-line [data-now]').length,
    alone: [...s.querySelectorAll('[data-voice][data-solo]')].map((l) => l.dataset.voice),
    live: s.querySelector('[data-lnf-live]').textContent, time: s.querySelector('[data-time]').textContent,
    scene: s.dataset.scene, tail: s.hasAttribute('data-tail') }; })()"""
# A machine without the song's render (it lives on the media disk): the player says so and its own voices sing, as
# site_keyboard.py allows too. Where the render is there, the flow plays it.
RENDER = "/media/song-audio/"
WAITS = "[...document.querySelectorAll('[data-lnf-lanes] [data-voice][data-wait]')].map((l) => l.dataset.voice)"
NOW = "document.querySelector('[data-lnf-now]').textContent"
# The recorded voices, for a machine whose database has none: the song's own answer (/api/templates/<song>) gains a
# default voice for each part, and each voice's file is a tone made here, served in pieces (window.__voiceDelay ms
# apart, so a download can be watched; window.__voiceFail refuses them). The practice voice's worker is kept if it's
# ever made (js/synth-worker.js sings it: the page must never make it; the worker that softens breaths is another).
VOICES = """(() => {
    const SECONDS = 36.2, RATE = 8000, FRAMES = Math.round(SECONDS * RATE);
    const tones = { soprano: 523, alto: 392, tenor: 330, bass: 196 };
    window.__voiceDelay = /voiceslow/.test(location.search) ? 220 : 0; window.__voiceFail = /voicefail/.test(location.search);
    window.__workers = []; const W = window.Worker;
    window.Worker = class extends W { constructor(url, options) { if (String(url).includes('synth-worker')) window.__workers.push(String(url)); super(url, options); } };
    const wav = (hz) => { const b = new ArrayBuffer(44 + FRAMES * 2), v = new DataView(b); const text = (at, t) => [...t].forEach((c, i) => v.setUint8(at + i, c.charCodeAt(0)));
        text(0, 'RIFF'); v.setUint32(4, 36 + FRAMES * 2, true); text(8, 'WAVEfmt '); v.setUint32(16, 16, true); v.setUint16(20, 1, true); v.setUint16(22, 1, true);
        v.setUint32(24, RATE, true); v.setUint32(28, RATE * 2, true); v.setUint16(32, 2, true); v.setUint16(34, 16, true); text(36, 'data'); v.setUint32(40, FRAMES * 2, true);
        for (let i = 0; i < FRAMES; i++) v.setInt16(44 + i * 2, Math.round(Math.sin(2 * Math.PI * hz * i / RATE) * 6000), true); return new Uint8Array(b); };
    const fetched = window.fetch;
    window.fetch = async function (url, ...rest) {
        const name = String(url?.url ?? url);
        if (name.startsWith('https://voices.test/')) {
            if (window.__voiceFail) return new Response('', { status: 503 });
            const bytes = wav(tones[name.split('/').pop().replace('.wav', '')] ?? 440); let at = 0;
            const body = new ReadableStream({ async pull(controller) {
                if (window.__voiceDelay) await new Promise((r) => setTimeout(r, window.__voiceDelay));
                if (at >= bytes.length) return controller.close();
                controller.enqueue(bytes.slice(at, at + 40000)); at += 40000; } });
            return new Response(body, { status: 200, headers: { 'Content-Type': 'audio/wav', 'Content-Length': String(bytes.length) } });
        }
        const response = await fetched.call(this, url, ...rest);
        if (!name.endsWith('/api/templates/amazing-grace') || !response.ok) return response;
        const answer = await response.json();
        answer.data.voices = Object.keys(tones).map((key) => ({ id: `t-${key}`, partKey: key, sampleRate: RATE, frames: FRAMES, offsetFrames: 0, credit: null, default: true, url: `https://voices.test/${key}.wav` }));
        return new Response(JSON.stringify(answer), { status: 200, headers: { 'Content-Type': 'application/json' } });
    };
})();"""


def scroll_to(f, scene):
    """One of the stage's scenes (0 to 4): a screen of scroll each."""
    f.run(f"window.scrollTo(0, {scene} * innerHeight)")
    f.wait(f"document.querySelector('[data-lnf]').dataset.scene === '{scene}'", timeout=10, what=f"scene {scene}")


def first_frame(f):
    # As the server sends it, before any script: the headline, the lanes, the gems, the first sung line.
    html = f.js("fetch(location.pathname).then((r) => r.text())")
    for piece in ('class="lnf-h1"', 'data-voice="soprano"', 'data-voice="bass"', 'class="lnf-g gem-note"', 'class="lnf-line"', 'data-scene="0"'):
        assert piece in html, f"the page as served has {piece}"
    assert html.count('class="lnf-g gem-note"') > 60, "every note of the song is a gem in the page"
    assert f.js("document.querySelectorAll('h1').length") == 1, "one h1"
    state = f.js(STATE)
    assert not state["playing"] and state["lit"] == 0 and state["scene"] == "0", f"at rest nothing plays or is lit: {state}"
    # The recorded voices start coming in a quiet moment, before any press; the practice voice is never made.
    f.wait("performance.getEntriesByType('resource').some((r) => /\\/api\\/templates\\/amazing-grace/.test(r.name))", timeout=15, what="the recorded voices asked for ahead of a press")
    assert f.js("JSON.parse(document.querySelector('#lnf [data-song]').textContent).realOnly") is True, "the page asks for the recorded voices only"
    f.no_sideways("the first scene")
    # Its own document and its own small sheet: none of the site's kit, and in the calm skin no game sheet.
    sheets = f.js("[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname)")
    assert sheets == ["/css/landing.css", "/css/game-toggle.css"], f"its own sheet, and the Game mode switch's: {sheets}"
    scripts = f.js("performance.getEntriesByType('resource').map((r) => new URL(r.name).pathname).filter((n) => n.endsWith('.js'))")
    assert "/js/public/cabinet.js" not in scripts, f"the old landing's game isn't loaded: {scripts}"
    assert f.js("performance.getEntriesByType('resource').some((r) => r.name.includes('/fonts/figtree-latin.woff2'))"), "the font is asked for at once"


GLASS = """(() => {
    const lin = (c) => ((c /= 255) <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
    const probe = Object.assign(document.createElement('canvas'), { width: 1, height: 1 }).getContext('2d', { willReadFrequently: true });
    const rgb = (colour) => { probe.clearRect(0, 0, 1, 1); probe.fillStyle = colour; probe.fillRect(0, 0, 1, 1); return [...probe.getImageData(0, 0, 1, 1).data].slice(0, 3); };
    const lum = (c) => { const [r, g, b] = rgb(c); return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b); };
    const ratio = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
    const gems = [...document.querySelectorAll('[data-lnf-lanes] .lnf-g')];
    const lanes = [...document.querySelectorAll('[data-lnf-lanes] [data-voice]')].map((lane) => {
        const gem = getComputedStyle(lane.querySelector('.lnf-g'));
        const c = gem.getPropertyValue('--c').trim(), ink = gem.getPropertyValue('--gem-ink').trim();
        const dark = gem.getPropertyValue('--gem-ink-dark').trim(), light = gem.getPropertyValue('--gem-ink-light').trim();
        return { voice: lane.dataset.voice, ink: Math.round(ratio(ink, c) * 100) / 100, best: rgb(ink).join() === rgb(ratio(dark, c) >= ratio(light, c) ? dark : light).join() };
    });
    const ring = document.querySelector('.lnf-g[data-ring]');
    return {
        lanes,
        fitted: gems.filter((g) => g.dataset.fit).length, gems: gems.length,
        inside: gems.filter((g) => g.dataset.fit === 'in').length,
        clipped: gems.filter((g) => g.dataset.fit === 'in' && g.querySelector('.gem-word').getBoundingClientRect().right > g.getBoundingClientRect().right + 0.5).length,
        ring: ring ? getComputedStyle(ring).boxShadow.split(/,(?![^(]*\\))/).length : 0,
        old: gems.some((g) => getComputedStyle(g).outlineStyle !== 'none' || getComputedStyle(g).filter !== 'none'),
    };
})()"""


def glass(f):
    """The gems are the app's (docs/glass-spec.md): ink by contrast at 4.5:1 or better, every gem's words placed by the
    fit (none clipped), the note sounding ringed, and none of the old outline or filter left."""
    f.wait("document.querySelectorAll('[data-lnf-lanes] .lnf-g[data-fit]').length === document.querySelectorAll('[data-lnf-lanes] .lnf-g').length", timeout=10, what="every gem's words placed")
    g = f.js(GLASS)
    for lane in g["lanes"]:
        assert lane["ink"] >= 4.5 and lane["best"], f"a gem's words are its ink by contrast, 4.5:1 or better: {lane}"
    assert g["inside"] > 20 and g["clipped"] == 0, f"words inside their gems, none clipped: {g}"
    assert g["ring"] >= 6, f"the note sounding has the ring (knockout, ring, keyline): {g}"
    assert not g["old"], f"no gem keeps the old outline or filter: {g}"


def play_and_solo(f):
    f.run("document.querySelector('[data-lnf-play]').click()")
    f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="the song playing")
    time.sleep(2.5)
    a = f.js(STATE)
    assert a["p"] > 0 and a["t"] > 0 and a["lit"] >= 1, f"the song moves and gems light: {a}"
    assert a["now"] == 1 and a["line"], f"the sung line follows, one word at a time: {a}"
    assert "Amazing Grace" in a["live"], f"a screen reader is told it's playing: {a['live']!r}"
    assert f.js("getComputedStyle(document.querySelector('.lnf-play-w .lnf-i-stop')).display") != "none", "the big button says Stop while it plays"
    assert f.js("getComputedStyle(document.querySelector('.lnf-disc .lnf-i-stop')).display") != "none", "the dock's disc shows the square"
    assert f.js("!!document.querySelector('.lnf [data-icon=pause], .lnf svg[class*=pause]')") is False, "never a pause"
    # The song builds: both passes in the time, the first lane alone at the start, the others waiting, and the stage says so.
    assert a["time"].endswith("/ 1:12") and f.js("document.querySelector('.lnf-play-w small').textContent").endswith("1:12"), f"the time is both passes: {a['time']}"
    assert f.js(WAITS) == ["alto", "tenor", "bass"], f"only the first lane is lit at the start: {f.js(WAITS)}"
    assert "soprano" in f.js(NOW).lower(), f"the stage says who sings: {f.js(NOW)!r}"
    # A waiting lane's notes are the app's ghosts (a dashed outline, no glass, no words); the singing lane's are glass.
    edges = f.js("(() => { const g = (v) => getComputedStyle(document.querySelector(`[data-voice=${v}] .lnf-g`)).borderTopStyle; return [g('soprano'), g('bass')]; })()")
    assert edges == ["solid", "dashed"], f"a waiting lane's notes are ghosts, the singing lane's glass: {edges}"
    assert f.js("getComputedStyle(document.querySelector('[data-voice=bass] .lnf-g'), '::before').display") == "none", "a ghost has no gloss"
    glass(f)
    assert f.js("getComputedStyle(document.querySelector('[data-voice=bass] .lnf-mt i')).animationName") == "none", "a waiting lane's meter is still"

    # The alto alone, by its lane; then all four again, and the song never goes back.
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="the alto alone")
    time.sleep(1.5)
    b = f.js(STATE)
    assert b["alone"] == ["alto"] and b["playing"] and b["p"] > a["p"], f"the alto alone, from the same spot: {b}"
    assert f.js("document.querySelector('[data-voice=alto] [data-hear-alone]').getAttribute('aria-pressed')") == "true", "the lane's button says it's pressed"
    assert f.js("getComputedStyle(document.querySelector('[data-voice=alto] .lnf-chip-alone')).display") != "none", "the lane says Heard alone in words"
    assert "alto" in b["live"].lower(), f"a screen reader is told which voice: {b['live']!r}"
    assert f.js(WAITS) == [] and "alto" in f.js(NOW).lower(), f"a voice alone wins over the joins: {f.js(WAITS)}, {f.js(NOW)!r}"
    f.run("document.querySelector('[data-voice=alto] [data-hear-alone]').click()")
    f.wait("!document.querySelector('[data-voice][data-solo]')", timeout=20, what="all four again")
    time.sleep(1.2)
    c = f.js(STATE)
    assert c["playing"] and c["alone"] == [], f"all four again: {c}"
    if c["p"] * 72 < 16:
        assert "bass" in f.js(WAITS) and "tenor" in f.js(WAITS), f"the lanes still to join wait again: {f.js(WAITS)}"
    # The Hear scene's button and the Learn links name the voice last heard alone, and lead to its part.
    learn = f.js("[...document.querySelectorAll('[data-lnf-learn]')].map((a) => [a.textContent.trim(), a.getAttribute('href')])")
    assert len(learn) == 2 and all("alto" in text.lower() and "part=alto" in href for text, href in learn), f"the Learn links follow the alto: {learn}"
    assert "alto" in f.js("document.querySelector('[data-lnf-hear]').textContent").lower(), "the Hear button offers the alto again"
    # The tenor alone by its lane (the dock has no voices), and back by the Hear scene's button.
    assert f.js("document.querySelectorAll('[data-lnf-voice], .lnf-dock [data-hear-alone]').length") == 0, "the dock has no voice buttons"
    if c["playing"]:
        f.run("document.querySelector('[data-voice=tenor] [data-hear-alone]').click()")
        f.wait("document.querySelector('[data-voice=tenor]').hasAttribute('data-solo')", timeout=20, what="the tenor alone, by its lane")
        assert f.js("document.querySelector('[data-lnf-hear]').getAttribute('aria-pressed')") == "true", "the Hear button says a voice is alone"
        f.run("document.querySelector('[data-lnf-hear]').click()")
        f.wait("!document.querySelector('[data-voice][data-solo]')", timeout=20, what="all four again, by the Hear button")
        assert "tenor" in f.js("document.querySelector('[data-lnf-learn]').textContent").lower(), "the Learn link follows the tenor"
        time.sleep(0.8)
        c = f.js(STATE)
    # The voices apart sing the whole play (the render has no voice alone): all four again carries on from the same spot.
    assert c["p"] > b["p"], f"all four again, carrying on: {b['p']} then {c['p']}"
    assert not f.js("performance.getEntriesByType('resource').some((r) => /song-audio/.test(r.name))"), "the song's render is never fetched"
    return c


def strip(f):
    """Down the page, a strip of lanes in view is the song itself while it plays; a picture when it doesn't."""
    f.run("const s = document.querySelector('#lnf-s5 .lnf-floor'); window.scrollTo(0, s.getBoundingClientRect().bottom + scrollY - innerHeight + 100)")
    time.sleep(0.8)
    floors = "[...document.querySelectorAll('.lnf-floor')].map((x) => [x.hasAttribute('data-live'), x.querySelectorAll('b').length])"
    assert f.js("[...document.querySelectorAll('.lnf-floor, .lnf-floor *')].every((el) => el.tabIndex < 0) && [...document.querySelectorAll('.lnf-floor')].every((x) => x.getAttribute('aria-hidden') === 'true')"), "the strips are pictures: hidden from a screen reader, no stops"
    if not f.js(STATE)["playing"]:
        f.run("document.querySelector('[data-play]').click()")
        f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="the song playing, down the page")
    f.wait("document.querySelector('#lnf-s5 .lnf-floor').hasAttribute('data-live')", timeout=10, what="the strip in view shows the song")
    live = f.js(floors)
    notes = f.js("document.querySelectorAll('.lnf-g').length")
    assert live[0] == [True, notes], f"its bars are the song's own notes: {live[0]} of {notes}"
    assert [x[0] for x in live[1:]] == [False] * (len(live) - 1), f"a strip off the screen costs nothing: {live}"
    at = f.js("getComputedStyle(document.querySelector('#lnf-s5 .lnf-floor u')).translate")
    time.sleep(1.2)
    assert f.js("getComputedStyle(document.querySelector('#lnf-s5 .lnf-floor u')).translate") != at, "its notes move with the song"
    assert f.js("document.querySelectorAll('#lnf-s5 .lnf-floor b[data-lit], #lnf-s5 .lnf-floor b[data-sung]').length") >= 1, "and light as they're sung"
    waits = f.js("[...document.querySelectorAll('#lnf-s5 .lnf-floor i')].map((i) => i.hasAttribute('data-wait'))")
    assert waits == f.js("[...document.querySelectorAll('[data-lnf-lanes] [data-voice]')].map((l) => l.hasAttribute('data-wait'))"), f"a band waits while its lane does: {waits}"
    assert f.js("document.documentElement.scrollWidth <= innerWidth"), "the strip adds no sideways scroll"
    f.run("document.querySelector('[data-play]').click()")
    f.wait("!document.getElementById('lnf').hasAttribute('data-playing')", timeout=10, what="stopped, down the page")
    f.wait("!document.querySelector('.lnf-floor[data-live]')", timeout=5, what="the strips are pictures again")
    assert f.js("getComputedStyle(document.querySelector('#lnf-s5 .lnf-floor i')).backgroundImage") != "none", "the picture is back"
    assert f.js(WAITS) == [] and f.js(NOW) == "", "stopped, every lane is lit and the stage says nothing"


def game_switch(f):
    """The top line's Game mode switch is the site's own: it turns the skin, the choice is kept, the page dresses for it.
    On a phone it's in Language and appearance's menu, as on every page (Hillel, 2026-11-16: "combine the two buttons")."""
    f.run("window.scrollTo(0, 0)")
    phone = f.js("innerWidth < 900")
    if phone:
        assert f.js("getComputedStyle(document.querySelector('.lnf-top > .lnf-game')).display") == "none", "no Game mode pill in a phone's top line"
        f.run("document.querySelector('.lnf-top [data-look-menu]').open = true")
        switch = "document.querySelector('.lnf-top .s-look-game [data-game-toggle]')"
    else:
        assert f.js("getComputedStyle(document.querySelector('.lnf-top .s-look-game')).display") == "none", "on a desktop the menu has no second Game mode"
        switch = "document.querySelector('.lnf-top > [data-game-toggle]')"
    assert f.js(f"{switch}.getAttribute('role')") == "switch" and f.js(f"{switch}.getAttribute('aria-checked')") == "false", "the switch, off in the calm skin"
    assert "Game mode" in f.js(f"{switch}.textContent"), "it has its name"
    box = f.js(f"(() => {{ const r = {switch}.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), r.left >= 0 && r.right <= innerWidth]; }})()")
    assert box[0] >= 44 and box[1] >= 40 and box[2], f"big enough to press, inside the top line: {box}"
    f.run(f"{switch}.click()")
    f.wait("document.documentElement.dataset.skin === 'game'", timeout=5, what="game mode on")
    assert f.js(f"{switch}.getAttribute('aria-checked')") == "true", "the switch says on"
    assert "game" in f.js("localStorage.getItem('stackapella.appearance') || ''"), "the choice is kept, as the site keeps it"
    headline = "getComputedStyle(document.querySelector('.lnf-h1')).fontFamily"
    if f.js("!!document.querySelector('meta[name=game-css]')"):
        # Turned on after the first paint, game mode's sheet loads then (theme-boot.js's gameCss) and the page wears it.
        f.wait(f"!{headline}.split(',')[0].includes('Figtree')", timeout=10, what="the page in game mode's dress")
        # The note gems' ring is the arcade's yellow, as in the app's game lane (docs/glass-spec.md 4), with no soft glow;
        # the square Play orb and the disc keep no glass crescent.
        rgb = "((c) => { const p = document.createElement('i'); p.style.color = c; document.body.append(p); const v = getComputedStyle(p).color; p.remove(); return v; })"
        ring = f.js(f"{rgb}(getComputedStyle(document.querySelector('[data-lnf-lanes] .lnf-g')).getPropertyValue('--gem-ring'))")
        yellow = f.js(f"{rgb}(getComputedStyle(document.documentElement).getPropertyValue('--g-yellow'))")
        assert ring == yellow, f"a gem's ring is game mode's yellow: {ring} against {yellow}"
        assert f.js("getComputedStyle(document.querySelector('[data-lnf-lanes] .lnf-g')).getPropertyValue('--gem-glow').trim().startsWith('0 0 0 0')"), "no soft glow in game mode"
        assert f.js("[...document.querySelectorAll('.lnf-play .lnf-orb, .lnf-disc')].every((o) => getComputedStyle(o, '::before').display === 'none')"), "the square orb and disc have no crescent"
    else:
        assert "Figtree" in f.js(headline), "the page stays calm"
    f.no_sideways("game mode on")
    f.run(f"{switch}.click()")
    f.wait("document.documentElement.dataset.skin === 'serious'", timeout=5, what="game mode off again")
    assert f.js(f"{switch}.getAttribute('aria-checked')") == "false", "the switch says off"
    f.wait(f"{headline}.includes('Figtree')", timeout=5, what="the calm page again")
    f.run("document.querySelector('.lnf-top [data-look-menu]').open = false")


def recorded_only(base, size):
    """Only the recorded voices sing: a press waits for their download and shows how far it is, a second press cancels,
    a failure says so and the next press tries again, and the practice voice is never made."""
    root = "document.getElementById('lnf')"
    load = "document.querySelector('.lnf-play-w [data-lnf-load]').textContent"
    f = flow.Flow(base, size, "landing_voices", skin="serious")
    f.expected_errors.append("voices.test")
    f.expected_errors.append("The recorded voices could not load")
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
    try:
        # A slow download: the press says it's loading at once, in words, and the share grows on the button.
        f.go("/?voiceslow", READY)
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait(f"{root}.hasAttribute('data-loading')", timeout=5, what="the press says it's loading")
        assert f.js("document.querySelector('[data-play]').getAttribute('aria-busy')") == "true", "the button is busy, for a screen reader"
        f.wait(f"{root}.dataset.load !== undefined && Number({root}.dataset.load) > 0", timeout=20, what="the download's share shows")
        first = f.js(f"[Number({root}.dataset.load), {load}, {root}.hasAttribute('data-playing'), Number.parseFloat({root}.style.getPropertyValue('--lnf-load'))]")
        assert "Loading the voices" in first[1] and f"{first[0]}%" in first[1], f"the words say how far: {first}"
        assert not first[2] and 0 < first[3] < 1, f"nothing sounds while it loads: {first}"
        assert "Loading the voices" in f.js("document.querySelector('[data-play-status]')?.textContent ?? ''"), "a screen reader hears it's loading"
        assert f.js("getComputedStyle(document.querySelector('.lnf-play .lnf-orb'), '::after').backgroundImage").startswith("conic-gradient"), "the ring fills with the share"
        assert f.js("getComputedStyle(document.querySelector('.lnf-meta')).display") == "none", "the words take the place of the length"
        f.wait(f"Number({root}.dataset.load) > {first[0]}", timeout=20, what="the share grows")
        # It starts by itself the moment the voices are in, with nothing more pressed.
        f.wait(f"{root}.hasAttribute('data-playing')", timeout=60, what="the song starting once the voices are in")
        assert f.js(load) == "" and f.js(f"{root}.dataset.load") is None, "the loading words are gone once it plays"
        assert f.js("window.__workers") == [], f"the practice voice is never made: {f.js('window.__workers')}"
        assert f.js("performance.getEntriesByType('resource').some((r) => r.name.includes('/js/soften-breaths.js'))"), "the voices' breaths are ducked before they sing"
        # (The page clears its live line and writes it on the next frame, so a screen reader hears a repeat: waited for.)
        f.wait("document.querySelector('[data-lnf-live]').textContent.includes('Amazing Grace')", timeout=5, what="a screen reader hears it play")
        time.sleep(1.5)
        assert f.js(STATE)["p"] > 0, "and it moves"
        f.run("document.querySelector('[data-play]').click()")

        # A second press while it loads cancels: nothing plays, then or later.
        f.go("/?voiceslow&again", READY)
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait(f"{root}.hasAttribute('data-loading')", timeout=5, what="loading again")
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait(f"!{root}.hasAttribute('data-loading')", timeout=5, what="the second press cancels")
        time.sleep(6)
        assert not f.js(f"{root}.hasAttribute('data-playing')") and f.js(load) == "", "cancelled: nothing plays once the voices are in"

        # The voices can't be had: an honest message, never the practice voice; the next press tries again and plays.
        f.go("/?voicefail", READY)
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait(f"{root}.hasAttribute('data-failed')", timeout=20, what="the failure said")
        assert "Couldn't load the voices" in f.js(load), f"in words under Play: {f.js(load)!r}"
        assert "Couldn't load the voices" in f.js("document.querySelector('[data-play]').getAttribute('aria-label')"), "and on the button, for a screen reader"
        assert not f.js(f"{root}.hasAttribute('data-playing')") and f.js("window.__workers") == [], "nothing sounds, and the practice voice isn't made"
        f.no_sideways("the failure")
        f.run("window.__voiceFail = false; document.querySelector('[data-lnf-play]').click()")
        f.wait(f"{root}.hasAttribute('data-playing')", timeout=40, what="the next press tries again and plays")
        assert not f.js(f"{root}.hasAttribute('data-failed')") and f.js(load) == "", "the message is gone"
        f.run("document.querySelector('[data-play]').click()")
    finally:
        f.close()


def over(f, what):
    """Nothing of `what` lies under the dock: its bottom is above the dock's top (the phone's dock starts at its pill)."""
    return f.js(f"""(() => {{ const el = document.querySelector({what!r}); const dock = document.querySelector('.lnf-dock');
        const top = innerWidth < 900 ? document.querySelector('.lnf-tp').getBoundingClientRect().top : dock.getBoundingClientRect().top;
        return Math.round(el.getBoundingClientRect().bottom - top); }})()""")


def scenes(f, before):
    # Down through the stage's scenes while the song plays: the scroll moves the scene and nothing else.
    for scene in (1, 2, 3, 4):
        scroll_to(f, scene)
        assert f.js("document.querySelector('[data-lnf]').dataset.top") == "small", "the top line is small on the way down"
        f.wait(f"Number(getComputedStyle(document.querySelector('[data-s=\"{scene}\"]')).opacity) > 0.9", timeout=5, what=f"scene {scene} shown")
        f.no_sideways(f"scene {scene}")
        time.sleep(1.0)
        assert over(f, ".lnf-world") <= 1, f"scene {scene}: the lanes end above the dock ({over(f, '.lnf-world')} px under it)"
        assert not f.js("document.getElementById('lnf').hasAttribute('data-tail')"), f"scene {scene} is the stage's"
    state = f.js(STATE)
    if state["playing"]:
        assert state["p"] > before["p"], f"scrolling never stops or rewinds the song: {before['p']} then {state['p']}"
    else:
        assert state["p"] == 0, f"the song ended by itself while the scenes went by, never stopped part way: {state}"
    # After Share the stage lets go: the rest is a page that scrolls, each section in the flow, nothing scrolling inside.
    for scene in (5, 6, 7, 8):
        f.run(f"window.scrollTo(0, document.getElementById('lnf-s{scene}').getBoundingClientRect().top + scrollY)")
        f.wait("document.getElementById('lnf').hasAttribute('data-tail')", timeout=10, what=f"scene {scene} is the page that scrolls")
        f.no_sideways(f"scene {scene}")
    assert f.js("document.querySelector('.lnf-stage').getBoundingClientRect().bottom") <= 1, "the stage has scrolled away"
    strip(f)
    inside = f.js("[...document.querySelectorAll('.lnf-tail, .lnf-tail *')].filter((el) => /auto|scroll/.test(getComputedStyle(el).overflowY)).map((el) => el.className)")
    assert inside == [], f"nothing scrolls inside itself: {inside}"
    places = f.js("[...document.querySelectorAll('.lnf-tail .lnf-sc, .lnf-tail .lnf-cap, .lnf-faq, .lnf-plans, .lnf-slabs, .lnf-choir')].map((el) => getComputedStyle(el).position)")
    assert all(place in ("static", "relative") for place in places), f"the page's pieces are in its flow: {places}"
    # The dock stays; at the very end the last question and the last links clear it.
    f.run("window.scrollTo(0, document.documentElement.scrollHeight)")
    time.sleep(0.6)
    assert f.js("getComputedStyle(document.querySelector('.lnf-dock')).position") == "fixed", "the dock stays"
    assert over(f, ".lnf-foot-wrap .s-foot-legal") <= 0, f"the footer's last line clears the dock ({over(f, '.lnf-foot-wrap .s-foot-legal')} px under it)"
    f.run("document.querySelector('.lnf-faq details:last-child').scrollIntoView({ block: 'center' })")
    time.sleep(0.4)
    assert over(f, ".lnf-faq details:last-child") <= 0, "the last question can be read clear of the dock"
    scroll_to(f, 3)
    assert f.js("document.querySelector('[data-lnf]').dataset.top") is None, "the top line is back on the way up"
    scroll_to(f, 0)
    # Stop is a stop: the song is back at its start, and the page shows it.
    f.run("const b = document.querySelector('[data-play]'); if (document.getElementById('lnf').hasAttribute('data-playing')) b.click();")
    f.wait("!document.getElementById('lnf').hasAttribute('data-playing')", timeout=10, what="stopped")
    time.sleep(0.6)
    stopped = f.js(STATE)
    assert stopped["time"].startswith("0:00") and stopped["lit"] == 0 and stopped["t"] == 0, f"stopped, the song is back at its start: {stopped}"
    assert f.js("getComputedStyle(document.querySelector('.lnf-disc .lnf-i-play')).display") != "none", "the disc says Play again"


FIRST_PAINT = "new MutationObserver((_, o) => { if (document.body) { window.__firstPaint = [document.documentElement.dataset.theme, document.querySelector('meta[name=theme-color]')?.content]; o.disconnect(); } }).observe(document, { childList: true, subtree: true });"


def unchosen(f):
    """Nothing chosen yet, the calm skin follows the device from the first paint, here and on a song page (Hillel,
    2026-11-17: "Auto everywhere, calm"): light on a light phone or laptop, dark on a dark one, the browser bar's colour too."""
    first = f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": FIRST_PAINT})["identifier"]
    for device, want in (("light", ["warm-light", "#faf5ee"]), ("dark", ["stage-dark", "#000000"])):
        f.scheme(device)
        for path in ("/", "/songs/amazing-grace"):
            f.go(path, "document.readyState !== 'loading'")
            assert f.js("JSON.parse(localStorage.getItem('stackapella.appearance') || '{}').mode ?? null") is None, "no light or dark chosen"
            assert f.js("window.__firstPaint") == want, f"{path} on a {device} device: {f.js('window.__firstPaint')}, not {want}"
    f.chrome.send("Page.removeScriptToEvaluateOnNewDocument", {"identifier": first})
    f.scheme(os.environ.get("FLOW_SCHEME", "dark"))


def theme(f):
    """Light and dark by the top line's Language and appearance, the site's own stored choice."""
    was = f.js("document.documentElement.dataset.theme")
    ground = f.js("getComputedStyle(document.querySelector('.lnf')).backgroundColor")
    pill = f.js("(() => { const r = document.querySelector('.lnf-top .s-look-btn').getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), r.left >= 0 && r.right <= innerWidth]; })()")
    assert pill[0] >= 44 and pill[1] >= 44 and pill[2], f"the pill is big enough to press, inside the top line: {pill}"
    f.run(FLIP)
    f.wait(f"document.documentElement.dataset.theme !== {was!r}", timeout=5, what="the other look")
    now = f.js("document.documentElement.dataset.theme")
    assert was.endswith("-light") != now.endswith("-light"), f"the switch changes light and dark: {was} then {now}"
    assert f.js("getComputedStyle(document.querySelector('.lnf')).backgroundColor") != ground, "the page's ground follows"
    f.run(FLIP)
    f.wait(f"document.documentElement.dataset.theme === {was!r}", timeout=5, what="the first look again")


def keyboard(f):
    f.go("/?keys", READY)
    first = f.js("(() => { const el = document.querySelector('main').querySelector('a[href], button, summary, [tabindex=\"0\"]'); return el?.hasAttribute('data-lnf-play'); })()")
    assert first, "Play is the first control in the page's main part"
    f.run("document.querySelector('[data-lnf-play]').focus()")
    f.press("Enter")
    f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="Enter on Play plays")
    # The lanes are one stop: only one is in the tab order, and the arrows move between them.
    assert f.js("document.querySelectorAll('[data-lnf-lanes] [data-hear-alone][tabindex=\"0\"]').length") == 1, "the lanes are one tab stop"
    f.run("document.querySelector('[data-voice=soprano] [data-hear-alone]').focus()")
    f.press("ArrowDown")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "alto", "the down arrow moves to the alto"
    f.press(" ")
    f.wait("document.querySelector('[data-voice=alto]').hasAttribute('data-solo')", timeout=20, what="Space hears the alto alone")
    f.press("ArrowUp")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "soprano", "the up arrow moves back"
    ring = f.js("(() => { const cs = getComputedStyle(document.activeElement); return cs.outlineStyle !== 'none' && Number.parseFloat(cs.outlineWidth) >= 2; })()")
    assert ring, "the focused lane shows its focus"
    f.press("ArrowUp")
    assert f.js("document.activeElement.closest('[data-voice]')?.dataset.voice") == "bass", "up from the first voice goes round to the last"
    # Focus that lands in a scene of the stage off the screen brings its scene.
    f.run("document.querySelector('#lnf-s2 [data-lnf-learn]').focus()")
    f.wait("document.querySelector('[data-lnf]').dataset.scene === '2'", timeout=10, what="focus in the Sing along scene shows it")
    # The dock's controls are real buttons with names, each at least 40 px across and 44 px tall.
    sizes = f.js("[...document.querySelectorAll('.lnf-dock button, .lnf-dock a')].map((el) => { const r = el.getBoundingClientRect(); return [el.getAttribute('aria-label') || el.textContent.trim(), Math.round(r.width), Math.round(r.height)]; })")
    assert len(sizes) == 2 and all(name and w >= 44 and h >= 44 for name, w, h in sizes), f"the dock's two controls (Play, Start singing), named and big enough: {sizes}"
    f.run("document.querySelector('[data-play]').click()")


def home(f):
    """It's the site's home: its address for search, the site's footer, the funnel's Start links, the landing's counts."""
    f.go("/?home", READY)
    assert f.js("document.querySelector('link[rel=canonical]')?.getAttribute('href') ?? ''").endswith("/"), "its canonical address is the site's root"
    assert f.js("document.querySelectorAll('script[type=\"application/ld+json\"]').length") >= 1, "its structured data"
    assert "Stackapella" in f.js("document.title") and f.js("!!document.querySelector('meta[property=\"og:image\"]')"), "its title and its link card"
    foot = f.js("[...document.querySelectorAll('.lnf-foot-wrap footer a')].map((a) => [a.textContent.trim() || a.getAttribute('aria-label'), Math.round(a.getBoundingClientRect().height)])")
    assert len(foot) >= 20 and all(name for name, _ in foot), f"the site's footer, with its links: {len(foot)}"
    assert f.js("getComputedStyle(document.querySelector('.lnf-foot-wrap .s-foot-cols')).display") == "grid", "the footer is dressed by the page's sheet"
    assert f.js("(() => { const hat = document.querySelector('.lnf-logo .s-hat'); return !!hat && hat.hasAttribute('hidden') && getComputedStyle(hat).display === 'none'; })()"), "the mark's hat waits for Christmas"
    # The Start links are the funnel's: each says it was a Start press.
    starts = f.js("[...document.querySelectorAll('[data-funnel-start]')].map((a) => a.getAttribute('href'))")
    assert len(starts) == 4 and all("1-comfortable" in href and "from=start" in href for href in starts), f"the Start links are marked: {starts}"
    # Its counts are the landing's: the visit with its source's place, and its section, once a person is there.
    f.run("document.body.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })); document.body.click();")
    time.sleep(1.5)
    beacons = f.js("window.__beacons")
    counts = " ".join(body for url, body in beacons if url.endswith("/api/counts"))
    assert '"site_visit"' in counts and '"sv_home"' in counts, f"the landing's own counts are sent: {beacons}"
    assert "sv_next" not in counts, f"no candidate's count: {counts}"
    assert any(url.endswith("/api/live/beat") for url, _ in beacons), f"Admin > Live's heartbeat: {beacons}"
    # The candidates' old addresses lead here.
    for gone in ("/next-final", "/next-claude", "/next-grok", "/next-gemini", "/next-chatgpt"):
        landed = f.js(f"fetch('{gone}').then((r) => [r.redirected, new URL(r.url).pathname])")
        assert landed == [True, "/"], f"{gone} leads home: {landed}"
    assert f.js("fetch('/es/next-final').then((r) => new URL(r.url).pathname)") == "/es", "the Spanish one to the Spanish home"
    # A mudeo arrival and an outreach partner's visitor get their line, and the top line sits under it.
    f.go("/?ref=mudeo", READY)
    assert f.js("!!document.querySelector('[data-lnf-note]') && !!document.getElementById('mudeo')"), "a mudeo arrival sees the line and the letter"
    under = f.js("(() => { const note = document.querySelector('[data-lnf-note]').getBoundingClientRect(); return document.querySelector('.lnf-top').getBoundingClientRect().top - note.bottom; })()")
    assert -1 <= under <= 1, f"the top line sits under the line above the page: {under}"
    f.no_sideways("a mudeo arrival")
    assert f.js("[...document.querySelectorAll('[data-funnel-start]')].every((a) => a.getAttribute('href').includes('ref=mudeo'))"), "its links carry where it came from"


def spanish(f):
    f.go("/es", READY)
    assert f.js("document.documentElement.lang") == "es", "the Spanish page says its language"
    assert "Armonía" in f.js("document.querySelector('h1').textContent"), "the headline is in Spanish"
    assert f.js("document.querySelector('link[rel=canonical]')?.getAttribute('href') ?? ''").endswith("/es"), "the Spanish page's own address"
    for scene in (0, 1, 2, 3, 4):
        scroll_to(f, scene)
        f.no_sideways(f"the Spanish scene {scene}")
    for scene in (5, 6, 7, 8):
        f.run(f"window.scrollTo(0, document.getElementById('lnf-s{scene}').getBoundingClientRect().top + scrollY)")
        time.sleep(0.3)
        f.no_sideways(f"the Spanish scene {scene}")
    assert "soprano" in f.js("document.querySelector('[data-lnf-learn]').textContent").lower() and "Aprende" in f.js("document.querySelector('[data-lnf-learn]').textContent"), "the Learn link is in Spanish"


def still(base, size):
    """Reduced motion: nothing pinned, every scene in the page, and the song plays with its gems lit in place."""
    f = flow.Flow(base, size, "landing_still", skin="serious")
    f.expected_errors.append(RENDER)
    f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
    f.chrome.send("Emulation.setEmulatedMedia", {"features": [{"name": "prefers-reduced-motion", "value": "reduce"}]})
    try:
        f.go("/?still", READY)
        assert f.js("getComputedStyle(document.querySelector('.lnf-stage')).position") != "sticky", "nothing is pinned"
        shown = f.js("[...document.querySelectorAll('[data-s]')].every((s) => getComputedStyle(s).opacity === '1' && s.getBoundingClientRect().height > 40)")
        assert shown, "every scene shows, in the page's flow"
        tops = f.js("[...document.querySelectorAll('[data-s]')].map((s) => Math.round(s.getBoundingClientRect().top + scrollY))")
        assert tops == sorted(tops) and len(set(tops)) == len(tops), f"the scenes come one after another: {tops}"
        f.no_sideways("the still page")
        f.run("document.querySelector('[data-lnf-play]').click()")
        f.wait("document.getElementById('lnf').hasAttribute('data-playing')", timeout=40, what="the song playing, still")
        time.sleep(2.5)
        state = f.js(STATE)
        assert state["lit"] >= 1 and state["now"] == 1, f"gems and words light in place: {state}"
        moved = f.js("getComputedStyle(document.querySelector('.lnf-tr')).translate")
        assert moved in ("none", "0px", "0px 0px"), f"the lanes stand still on the first line: {moved}"
        # The joins, still: the lanes switch state at once, and the strips down the page stay pictures.
        assert "bass" in f.js(WAITS), f"a lane still to join waits, with reduced motion too: {f.js(WAITS)}"
        assert f.js("getComputedStyle(document.querySelector('[data-voice=bass]')).transitionDuration.split(',').every((d) => parseFloat(d) === 0)"), "a lane changes state with no animation"
        assert f.js("document.querySelectorAll('.lnf-floor[data-live], .lnf-floor b').length") == 0, "the strips stay still"
        f.run("document.querySelector('[data-voice=tenor] [data-hear-alone]').click()")
        f.wait("document.querySelector('[data-voice=tenor]').hasAttribute('data-solo')", timeout=20, what="a lane alone, still")
        f.run("document.querySelector('[data-play]').click()")
    finally:
        f.close()


def in_game(base):
    """In the game skin: the same calm page until game mode's sheet for it exists (css/landing-game.css), then its dress."""
    f = flow.Flow(base, "desktop", "landing_game", skin="game")
    try:
        f.go("/?game", READY)
        assert f.js("document.documentElement.hasAttribute('data-gs')"), "the home page is on the arcade screen in game mode"
        assert f.js("document.querySelector('.lnf-top [data-game-toggle]').getAttribute('aria-checked')") == "true", "the switch says game mode is on"
        sheets = f.js("[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => new URL(l.href).pathname)")
        font = f.js("getComputedStyle(document.querySelector('.lnf-h1')).fontFamily")
        dressed = f.js("!!document.querySelector('meta[name=game-css]')")
        if dressed:
            assert "/css/landing-game.css" in sheets and len(sheets) == 3, f"game mode's sheet joins the page's own: {sheets}"
            assert "Figtree" not in font.split(",")[0], f"the headline wears game mode's type: {font}"
            assert f.js("getComputedStyle(document.querySelector('.lnf-play')).borderRadius") == "0px", "Play is PRESS START, a square key"
            assert f.js("getComputedStyle(document.querySelector('.lnf-play .s-skin-s')).display") == "none" and f.js("getComputedStyle(document.querySelector('.lnf-play .s-skin-g')).display") != "none", "Play says game mode's words, once"
        else:
            assert sheets == ["/css/landing.css", "/css/game-toggle.css"], f"no game sheet yet: {sheets}"
            assert "Figtree" in font, f"the headline keeps its own type: {font}"
            assert f.js("getComputedStyle(document.querySelector('.lnf-play')).borderRadius") != "0px", "its buttons keep their own shape"
        f.no_sideways("game mode")
    finally:
        f.close()


def main():
    failures = []
    for size in ("desktop", "phone"):
        f = flow.Flow(SITE, size, "landing_page", skin="serious")
        f.expected_errors.append(RENDER)
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": WATCH})
        f.chrome.send("Page.addScriptToEvaluateOnNewDocument", {"source": VOICES})
        try:
            unchosen(f)
            f.go("/", READY)
            first_frame(f)
            scenes(f, play_and_solo(f))
            theme(f)
            game_switch(f)
            keyboard(f)
            home(f)
            spanish(f)
        except AssertionError as error:
            failures.append(f"{size}: {error}")
        finally:
            try:
                f.close()
            except AssertionError as error:
                failures.append(str(error))
        try:
            still(SITE, size)
        except AssertionError as error:
            failures.append(f"{size}, still: {error}")
        try:
            recorded_only(SITE, size)
        except AssertionError as error:
            failures.append(f"{size}, recorded voices: {error}")
    try:
        in_game(SITE)
    except AssertionError as error:
        failures.append(f"game skin: {error}")
    print("FAIL landing_page\n     " + "\n     ".join(failures) if failures else "ok   landing_page")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
