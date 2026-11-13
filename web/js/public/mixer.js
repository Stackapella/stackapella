// The public site's players (server/resources/views/components/site/mixer.blade.php, and the
// landing's "your tune" example): a song's line in four voices, sung by the app's own guide voice
// (guides.js, rendered in its worker), each voice switched in and out while it plays. Everything
// starts on the AudioContext clock, and a gain per voice switches it with a short ramp, so the parts
// never drift apart. The page reads without this script: the words and the text alternative are
// the server's.
//
// The markup it works with, inside [data-player]:
// - <script type="application/json" data-song>: Landing::hero()'s shape (the notes and the words);
// - [data-play]: Play and Stop, its words in data-label-play and data-label-stop (its aria-label);
// - [data-voice="<part key>"]: a voice. Either a button, aria-pressed while it sings (the tune), or a
//   row (the hero's mixer) holding [data-pick], a radio that brings that voice forward (louder, and
//   "You": the row gets s-is-you), and [data-toggle], a switch that only turns it on or off;
// - [data-set="<part key>|all"]: a button that leaves only that voice on, or all of them (the mixer's "Hear" row,
//   aria-pressed on the one that says what you hear; switching while it plays carries on from the same spot);
// - optional, for the Studio page's mixer: [data-level="<part key>"], a range input (0 to 1, 1 when
//   missing) setting that voice's level; [data-solo="<part key>"], a button (aria-pressed) that
//   solos it: while any voice is soloed only the soloed ones sound, whatever their switches say, and
//   each voice silenced by a solo gets data-silent; [data-pan="<part key>"], a range input (-1 left
//   to 1 right) placing it. A voice gets a panner only when it has a pan control (a panner at the
//   centre is 3 dB quieter than the plain mono voice, so the other players sound as they always have);
// - [data-beat] (with data-length for a note, each voice's bars): data-lit while it sounds,
//   data-sung once it has; a word (no length) is lit until the next word starts;
// - [data-time]: "0:06 / 0:18".
// The player gets data-playing while it plays, and --p (0 to 1) for its playhead.
// Anywhere on the page, [data-hear="<player id>"] (the hero's "Hear four voices") brings that player
// into view and plays it, unless it's playing already. Without the script it's a link to the player.
// - [data-part-key="<part key>"] (a song card's part rows): data-sounding while that part sings a note.
//
// The whole song (Hillel: "let's always play the full songs. none are that long"): a song whose data says `whole`
// starts at once with its opening line (the notes in the page), while the rest of the song comes from the public
// catalogue (/api/templates/<slug>, as the part pages read it) and is sung by the same worker; it joins on the audio
// clock exactly where the song has got to, so the parts never drift. A press answers at once and shows its loading, a
// slow load and a failure the same way on every player (play-state.js).
//
// The song's render (the showreel agent's stereo mix of every part, `audio` in the data, only where it's of the song's
// current version): while every part sounds as mixed, Play streams that one file, a quarter of the bytes of four
// voices, through the AudioContext (so Stop fades it), its own time the clock for the lights. A part heard alone or
// brought forward needs the voices apart: the player moves to them at the same point, and back to the render when all
// of them sound again. The voices apart are the app's own, made when the player comes into view; a song's recorded
// voices download only once a part is heard on its own (Slow 4G: the four took 14 s, the render under 5).

import { heardTime } from '../../engine/clock.js';
import { renderGuide } from '../guides.js';
import { catalogueGuides, placed, realVoice, songTemplate } from './app-voices.js';
import { playState } from './play-state.js';
import { note } from './report.js';

const START_AHEAD = 0.1; // seconds: room to start every voice on the same frame
const SWITCH = 0.012; // the time constant of a voice's switch, about 50 ms to silence
const FADE_OUT = 0.04;
const LEVEL = 0.6; // four guides together, each about -20 dBFS RMS, kept well under clipping
// The voice brought forward sits 6 dB over the others, which step back so the four together are
// quieter than all four at 1 (1.2 + 3 x 0.6 = 3.0): it never clips.
const FORWARD = 1.2;
const BACK = 0.6;
const BRING = 0.05; // the time constant of bringing a voice forward: about 150 ms to settle
// The voices are made before any press (Hillel: "when I click play ... nothing happens for a second"), at one rate:
// an AudioBuffer plays at its own rate in any context, so nothing waits for the context to exist.
const RATE = 48000;
// A press waits this long for the real voices still on their way, then the app's own voice sings.
const REAL_WAIT = 1500;
// The render plays at its own mastered level (-16 LUFS) through the master, which carries LEVEL for the voices.
const MIX_GAIN = 1 / LEVEL;
const MIX_MOVE = 0.03; // the time constant of moving between the render and the voices apart: about 100 ms

/** The render this browser plays: the m4a where it can, else the mp3; null without one. Pure (the tests check it). */
export function renderUrl(audio, canPlay) {
    if (audio?.m4a && canPlay('audio/mp4') !== '') return audio.m4a;
    return audio?.mp3 ?? null;
}

let context = null;
const players = [];
// Each voice's samples as an AudioBuffer, made once (the context is one for the page): a press doesn't copy them again.
const buffers = new WeakMap();

/**
 * Resolves after the next paint, so a tap shows its answer before the voices' samples are copied in (Core Web Vitals,
 * 2026-11-13: the copying ran inside the tap, 160 ms at a slow phone's speed, and the row lit late). A yield of the main
 * thread, not audio timing: every voice still starts together on the audio clock.
 */
function afterPaint() {
    return new Promise((resolve) => requestAnimationFrame(() => {
        const channel = new MessageChannel();
        channel.port1.onmessage = () => resolve();
        channel.port2.postMessage(null);
    }));
}

/** `samples` as a mono AudioBuffer at RATE, in the page's context. */
function bufferOf(samples) {
    let buffer = buffers.get(samples);
    if (!buffer) {
        buffer = context.createBuffer(1, Math.max(1, samples.length), RATE);
        buffer.copyToChannel(samples, 0);
        buffers.set(samples, buffer);
    }
    return buffer;
}

/**
 * A voice's gain: 0 when it isn't heard (switched off, or another voice soloed; a solo wins over the
 * switch, and two solos sound together), else 1, or FORWARD and BACK when one voice is brought
 * forward, times its fader (0 to 1). Pure (the tests check it).
 */
export function voiceLevel(key, { on, soloed, forward, fader = 1 }) {
    const heard = soloed.size ? soloed.has(key) : on;
    if (!heard) return 0;
    return (forward === null ? 1 : key === forward ? FORWARD : BACK) * fader;
}

/**
 * The parts singing a note `at` seconds in: `parts` is [{ key, notes: [{ beat, length }] }], `secondsPerBeat` the
 * song's. Pure (the tests check it).
 */
export function sounding(parts, at, secondsPerBeat) {
    const beat = at / secondsPerBeat;
    return new Set(parts.filter((part) => part.notes.some((n) => beat >= n.beat && beat < n.beat + n.length)).map((part) => part.key));
}

/**
 * Where the rest of a song joins its opening line: `start` (when the line started on the audio clock), `lineSeconds`
 * (its length), `now` (the clock now, plus room to start). It joins at the line's end, or, when the rest came late,
 * now, that far into the song: { when, offset }. Pure (the tests check it).
 */
export function joinAt(start, lineSeconds, now) {
    const when = Math.max(start + lineSeconds, now);
    return { when, offset: when - start };
}

/** The media CDN's connection opened ahead (on a slow network its handshake is most of the wait), once per host. */
function preconnect(url) {
    const origin = new URL(url, location.href).origin;
    if (origin === location.origin || document.querySelector(`link[rel=preconnect][href="${origin}"]`)) return;
    const link = document.createElement('link');
    link.rel = 'preconnect';
    link.href = origin;
    link.crossOrigin = 'anonymous';
    document.head.append(link);
}

for (const root of document.querySelectorAll('[data-player]')) {
    const player = setUp(root);
    if (player) players.push(player);
}

for (const link of document.querySelectorAll('[data-hear]')) {
    link.addEventListener('click', (event) => {
        const root = document.getElementById(link.dataset.hear);
        const play = root?.querySelector('[data-play]');
        if (!play) return;
        event.preventDefault();
        const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
        root.scrollIntoView({ behavior: still ? 'auto' : 'smooth', block: 'center' });
        play.focus({ preventScroll: true });
        // Still inside the press, so the browser lets the audio start.
        if (!root.hasAttribute('data-playing')) play.click();
    });
}

function setUp(root) {
    const data = root.querySelector('[data-song]');
    const button = root.querySelector('[data-play]');
    if (!data || !button) return null;
    const song = JSON.parse(data.textContent);
    const secondsPerBeat = 60 / song.tempo;
    // The line in the page, and what a press plays: the whole song when the data says so.
    const lineLength = song.beats * secondsPerBeat;
    const whole = song.whole === true && Number(song.songBeats) > song.beats;
    const length = (whole ? Number(song.songBeats) : song.beats) * secondsPerBeat;
    const rows = new Map([...root.querySelectorAll('[data-part-key]')].map((el) => [el.dataset.partKey, el]));
    const voices = new Map([...root.querySelectorAll('[data-voice]')].map((el) => [el.dataset.voice, el]));
    const sets = [...root.querySelectorAll('[data-set]')];
    // A row you tap to hear its part alone, and again for all of them (the hero's mixer, outside the Studio).
    const alone = new Map([...root.querySelectorAll('[data-voice]')].map((el) => [el.dataset.voice, el.querySelector('[data-hear-alone]')]).filter(([, el]) => el));
    let heardAlone = null; // the part heard alone, last said to the page
    const marks = [...root.querySelectorAll('[data-beat]')].map((el) => ({
        el,
        from: Number(el.dataset.beat) * secondsPerBeat,
        to: 'length' in el.dataset ? (Number(el.dataset.beat) + Number(el.dataset.length)) * secondsPerBeat : null,
    }));
    // A word is lit until the next word starts.
    const starts = [...new Set(marks.filter((m) => m.to === null).map((m) => m.from))].sort((a, b) => a - b);
    for (const mark of marks) {
        if (mark.to === null) mark.to = starts.find((s) => s > mark.from) ?? lineLength;
    }
    const time = root.querySelector('[data-time]');
    const toggles = new Map([...voices].map(([key, el]) => [key, el.querySelector('[data-toggle]')]));
    const picks = new Map([...voices].map(([key, el]) => [key, el.querySelector('[data-pick]')]).filter(([, el]) => el));
    const on = new Map(song.parts.map((p) => {
        const toggle = toggles.get(p.key);
        const voice = voices.get(p.key);
        return [p.key, toggle ? toggle.getAttribute('aria-checked') !== 'false' : voice?.getAttribute('aria-pressed') !== 'false' && !voice?.hasAttribute('data-off')];
    }));
    // The voice brought forward, when the player has a choice of one (the hero's mixer).
    let forward = picks.size ? ([...picks].find(([, el]) => el.getAttribute('aria-checked') === 'true')?.[0] ?? null) : null;
    // The Studio's controls, when the player has them.
    const control = (name) => new Map([...root.querySelectorAll(`[data-${name}]`)].map((el) => [el.dataset[name], el]));
    const levels = control('level');
    const solos = control('solo');
    const pans = control('pan');
    const soloed = new Set([...solos].filter(([, el]) => el.getAttribute('aria-pressed') === 'true').map(([key]) => key));
    const fader = (key) => {
        const value = Number(levels.get(key)?.value ?? 1);
        return Number.isFinite(value) ? Math.min(1, Math.max(0, value)) : 1;
    };
    const level = (key) => voiceLevel(key, { on: on.get(key), soloed, forward, fader: fader(key) });

    let play = null; // { start, master, gains, sources }
    let made = null; // the app's voices, made once the player is in view (no network: the worker sings them)
    let real = null; // the real voices, fetched once someone shows they might press (hover, focus, a touch)
    const synthVoices = () => (made ??= Promise.all(song.parts.map((part) => renderGuide({
        templateId: `site:${song.song}`,
        templateVersion: song.version,
        part: { key: part.key, guide: { notes: part.notes } },
        tempo: song.tempo,
        sampleRate: RATE,
        human: 'full', // a demo to listen to: sung as a person sings, every variation (js/synth.js)
    }))).catch((error) => {
        made = null;
        throw error;
    }));
    // The render, while every part sounds as it was mixed: all on, none alone or brought forward, no Studio controls.
    const mixUrl = renderUrl(song.audio, (type) => document.createElement('audio').canPlayType(type));
    let mixFailed = false; // a render that wouldn't play isn't tried again on this page: the voices sing it
    const mixable = () => mixUrl !== null && !mixFailed && !levels.size && !pans.size && !solos.size && forward === null && [...on.values()].every(Boolean);
    // { el, gain, user }: the render's <audio>, made (and loading) once someone might press, routed through the
    // context the first time it plays; `user` is the play that wants it sounding, so a late pause never cuts a new play.
    let media = null;
    const mediaOf = () => {
        if (media) return media;
        const el = new Audio();
        el.crossOrigin = 'anonymous'; // the media CDN answers any origin; without it the context hears silence
        el.preload = 'auto';
        el.src = mixUrl;
        media = { el, gain: null, user: null };
        el.addEventListener('ended', () => {
            if (play && play.mix === media && play.ending === 'mix') stopped();
        });
        return media;
    };
    // A part with a real voice for this version of the song sings with it (the same voices Sing plays), cut to the line.
    let realHere = null; // the real voices once they're here, for whoever doesn't wait on them
    const realVoices = () => (real ??= (async () => {
        const guides = (await catalogueGuides(song.song, { version: song.version })) ?? {};
        const decoder = new OfflineAudioContext(1, 1, RATE);
        const voices = {};
        await Promise.all(song.parts.map(async (part) => {
            if (!guides[part.key]) return;
            try {
                voices[part.key] = await realVoice(guides[part.key], decoder);
            } catch (error) {
                console.warn('A real voice could not load: the synth sings it', error);
            }
        }));
        realHere = voices;
        return voices;
    })());
    const ready = () => synthVoices();
    // The rest of the song: its notes from the catalogue (fetched once someone might press), sung whole by the worker,
    // with the real voices placed over the whole song. Its parts in the line's order.
    let fullNotes = null;
    const fullParts = () => (fullNotes ??= songTemplate(song.song).then((template) => song.parts.map((part) => {
        const own = (template.parts ?? []).find((p) => p.key === part.key);
        return { key: part.key, notes: (own?.guide?.notes ?? []).map((n) => ({ ...n })) };
    })).catch((error) => {
        fullNotes = null;
        throw error;
    }));
    let full = null;
    const fullVoices = () => (full ??= (async () => {
        const parts = await fullParts();
        const rendered = await Promise.all(parts.map((part) => renderGuide({
            templateId: `site:${song.song}:whole`,
            templateVersion: song.version,
            part: { key: part.key, guide: { notes: part.notes } },
            tempo: song.tempo,
            sampleRate: RATE,
            human: 'full',
        })));
        // With a render, the voices apart never wait on a download: the real ones join only if they're here already.
        const voices = mixUrl ? realHere : await realVoices();
        return parts.map((part, i) => {
            const voice = voices?.[part.key];
            return voice ? placed(voice.samples, voice.offsetSeconds, RATE, Math.max(rendered[i].length, Math.round(length * RATE))) : rendered[i];
        });
    })().catch((error) => {
        full = null;
        throw error;
    }));
    // What the part rows follow: the line's notes until the whole song's are here.
    let following = song.parts.map((part) => ({ key: part.key, notes: part.notes }));
    const likely = () => {
        synthVoices().catch(() => {});
        // With a render, that file starts coming (the voices apart wait until a part is heard on its own).
        if (mixUrl) mediaOf();
        else realVoices();
        if (whole) fullParts().catch(() => {});
    };
    let pending = null; // the press whose voices are still rendering; Stop or another player cancels it
    let frame = 0;

    const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

    function label(playing) {
        button.setAttribute('aria-label', playing ? button.dataset.labelStop : button.dataset.labelPlay);
    }

    // Loading: the button busy (its ring turns), the line under the title says the voices are on their way, and a slow
    // load or a failure says so (play-state.js, every player alike).
    const line = root.querySelector('[data-loading-line]');
    const states = playState(button, root);
    function loading(on) {
        if (on) states.loading();
        else if (states.state === 'loading') states.idle();
        if (line) line.hidden = !on;
    }

    function show(at) {
        root.style.setProperty('--p', String(Math.min(1, at / length)));
        if (rows.size) {
            const now = play !== null && at > 0 ? sounding(following, at, secondsPerBeat) : new Set();
            for (const [key, row] of rows) row.toggleAttribute('data-sounding', now.has(key));
        }
        for (const mark of marks) {
            const lit = play !== null && at > 0 && at >= mark.from && at < mark.to;
            mark.el.toggleAttribute('data-lit', lit);
            mark.el.toggleAttribute('data-sung', play !== null && at > 0 && at >= mark.from && !lit);
        }
        if (time) time.textContent = `${clock(at)} / ${clock(length)}`;
    }

    // Where the song has got to: the render's own time while it plays, else the audio clock from the voices' start.
    function position() {
        if (!play) return 0;
        if (play.mix) return play.mix.el.currentTime;
        return Math.max(0, heardTime(context) - play.start);
    }

    function tick() {
        if (!play) return;
        show(position());
        frame = requestAnimationFrame(tick);
    }

    // The render's element pauses once its fade is over, on the audio clock (an empty source's end), unless a play
    // wants it sounding again by then.
    function pauseAfter(m, when) {
        const mark = context.createBufferSource();
        mark.buffer = context.createBuffer(1, 1, context.sampleRate);
        mark.connect(context.destination);
        mark.addEventListener('ended', () => {
            if (m.user === null) m.el.pause();
        });
        mark.start(when);
    }

    // The render into this play's master (made once per element: an element feeds one source node for good).
    function mixInto(master) {
        const m = mediaOf();
        if (!m.gain) {
            m.gain = context.createGain();
            context.createMediaElementSource(m.el).connect(m.gain);
        }
        m.gain.disconnect();
        m.gain.connect(master);
        return m;
    }

    function stopped() {
        cancelAnimationFrame(frame);
        play = null;
        root.removeAttribute('data-playing');
        label(false);
        show(0);
    }

    function ramp(key, constant) {
        if (play?.mix) return; // the render sounds: the voices apart are silent until a move brings them in
        const gain = play?.gains.get(key)?.gain;
        if (!gain) return;
        gain.cancelScheduledValues(context.currentTime);
        gain.setTargetAtTime(level(key), context.currentTime, constant);
    }

    function placeOf(key) {
        const value = Number(pans.get(key)?.value ?? 0);
        return Number.isFinite(value) ? Math.min(1, Math.max(-1, value)) : 0;
    }

    function showSolos() {
        for (const [key, el] of solos) el.setAttribute('aria-pressed', String(soloed.has(key)));
        for (const [key, el] of voices) el.toggleAttribute('data-silent', soloed.size > 0 && !soloed.has(key));
    }

    function solo(key) {
        if (soloed.has(key)) soloed.delete(key);
        else soloed.add(key);
        showSolos();
        for (const other of on.keys()) ramp(other, SWITCH);
    }

    function switchTo(key, now) {
        on.set(key, now);
        const toggle = toggles.get(key);
        if (toggle) toggle.setAttribute('aria-checked', String(now));
        else voices.get(key)?.setAttribute('aria-pressed', String(now));
        voices.get(key)?.toggleAttribute('data-off', !now);
        // Its glass (the disc and the switch) goes quiet while it's off.
        voices.get(key)?.querySelectorAll('.gem').forEach((gem) => gem.classList.toggle('gem-dim', !now));
        ramp(key, SWITCH);
    }

    function bringForward(key) {
        forward = key;
        for (const [other, pick] of picks) {
            const chosen = other === key;
            pick.setAttribute('aria-checked', String(chosen));
            pick.tabIndex = chosen ? 0 : -1;
            pick.setAttribute('aria-label', chosen ? pick.dataset.labelYou : pick.dataset.label);
            voices.get(other).classList.toggle('s-is-you', chosen);
        }
        // Brought forward means heard: a voice switched off comes back on.
        if (!on.get(key)) switchTo(key, true);
        for (const other of picks.keys()) ramp(other, BRING);
        showSets();
        remix();
        // Said to the page, which may follow it (a song page's Learn names the part: js/public/song-page.js).
        root.dispatchEvent(new CustomEvent('mixer-pick', { bubbles: true, detail: { key } }));
    }

    function showSets() {
        // The row heard alone, when one is: [data-solo] on it, its button pressed and saying it brings the others back.
        const heard = [...on].filter(([, value]) => value).map(([key]) => key);
        const only = heard.length === 1 && on.size > 1 ? heard[0] : null;
        // Said to the page when it changes (a song page's Learn follows the part heard alone until one is picked).
        if (only !== heardAlone && alone.size) {
            heardAlone = only;
            root.dispatchEvent(new CustomEvent('mixer-solo', { bubbles: true, detail: { key: only } }));
        }
        for (const [key, button] of alone) {
            const solo = key === only;
            voices.get(key)?.toggleAttribute('data-solo', solo);
            button.setAttribute('aria-pressed', String(solo));
            button.setAttribute('aria-label', solo ? button.dataset.labelBack : button.dataset.label);
        }
        for (const el of sets) {
            const only = el.dataset.set;
            const pressed = only === 'all' ? [...on.values()].every(Boolean) : [...on].every(([key, value]) => value === (key === only));
            el.setAttribute('aria-pressed', String(pressed));
        }
    }

    async function start() {
        const press = {};
        pending = press;
        // On iPhone, sound even with the silent switch on, as a player should.
        if ('audioSession' in navigator) navigator.audioSession.type = 'playback';
        // The press answers at once (every tap answers within 100 ms, docs/design-rules.md): loading, then playing.
        loading(true);
        // Made and resumed inside the press, as Safari asks (made already on the pointer going down, when it can be).
        context ??= new AudioContext({ latencyHint: 'playback' });
        const resumed = context.resume();
        const master = context.createGain();
        master.gain.value = LEVEL;
        master.connect(context.destination);
        // `gen` counts the moves between the render and the voices apart: an end or a join from before a move is ignored.
        const current = { start: 0, master, gains: new Map(), panners: new Map(), sources: [], stop, joining: false, ending: null, lineEnded: false, mix: null, gen: 0, move: null };
        try {
            // The render, started inside the press (as iPhone asks of a media element); the voices sing if it can't.
            if (mixable() && (await fromRender(current, press, resumed))) return;
            if (pending !== press) return master.disconnect();
            const voicesOnTheWay = realVoices();
            const rendered = [...(await ready())];
            // The real voices if they're here in time; else the app's own voice sings and they're kept for the next press.
            // A limit on the wait for the network, not audio timing: the voices still start together on the audio clock.
            const voices = await Promise.race([voicesOnTheWay, new Promise((resolve) => setTimeout(() => resolve(null), REAL_WAIT))]);
            withReal(rendered, voices);
            await resumed;
            await afterPaint();
            if (pending !== press) return master.disconnect(); // stopped, or another player started, while it rendered
            pending = null;
            play = current;
            voicesAt(current, rendered, context.currentTime + START_AHEAD, 0, false);
            playing();
            remix(); // all of them sounding again while it loaded: the render takes over
        } finally {
            if (pending === press) pending = null;
            if (pending === null) loading(false);
        }
    }

    // The render from the start: true once it sounds (or the press was cancelled meanwhile), false if it can't play.
    async function fromRender(current, press, resumed) {
        const m = mixInto(current.master);
        m.user = current;
        m.gain.gain.cancelScheduledValues(0);
        m.gain.gain.value = MIX_GAIN;
        if (m.el.currentTime !== 0) m.el.currentTime = 0;
        const sounding = m.el.play();
        try {
            await Promise.all([sounding, resumed]);
        } catch (error) {
            console.warn('The render could not play: the voices sing it', error); // i18n-ignore
            // A Stop while it started (AbortError) isn't a failure; anything else is told once a page.
            if (error?.name !== 'AbortError') mixFailed = true;
            if (error?.name !== 'AbortError') note('A song render could not play, the voices sang it', { error: error?.name ?? 'Error', format: mixUrl.split('.').pop() }); // i18n-ignore
            m.user = null;
            m.el.pause();
            return false;
        }
        if (pending !== press) {
            m.user = null;
            m.el.pause();
            current.master.disconnect();
            return true;
        }
        pending = null;
        current.mix = m;
        current.ending = 'mix';
        play = current;
        playing();
        // The lights follow the whole song's notes once they're here; the voices apart get ready for a part on its own.
        if (whole) {
            fullParts().then((parts) => (following = parts)).catch(() => {});
            const soon = () => fullVoices().catch(() => {});
            if ('requestIdleCallback' in window) requestIdleCallback(soon, { timeout: 4000 });
            else soon();
        }
        remix(); // a part alone already, while it loaded: the voices apart take over
        return true;
    }

    function playing() {
        root.setAttribute('data-playing', '');
        root.setAttribute('data-played', '');
        loading(false);
        states.playing();
        label(true);
        frame = requestAnimationFrame(tick);
    }

    // The line's voices with the real ones placed in, where they're here.
    function withReal(rendered, voices) {
        song.parts.forEach((part, i) => {
            const voice = voices?.[part.key];
            if (voice) rendered[i] = placed(voice.samples, voice.offsetSeconds, RATE, rendered[i].length);
        });
    }

    // A voice's gain in this play (and its panner, where the Studio places it), made the first time.
    function gainOf(current, key) {
        let gain = current.gains.get(key);
        if (gain) return gain;
        gain = context.createGain();
        gain.gain.value = 0;
        if (pans.has(key) && context.createStereoPanner) {
            const panner = context.createStereoPanner();
            panner.pan.value = placeOf(key);
            current.panners.set(key, panner);
            gain.connect(panner).connect(current.master);
        } else {
            gain.connect(current.master);
        }
        current.gains.set(key, gain);
        return gain;
    }

    // The voices apart from `offset` seconds into the song, starting at `at` on the audio clock: the line's
    // (`rendered`), and the rest joining where the line ends. `fadeIn` when they take over from the render.
    function voicesAt(current, rendered, at, offset, fadeIn) {
        const gen = ++current.gen;
        current.start = at - offset;
        current.ending = 'line';
        current.lineEnded = offset >= lineLength;
        current.joining = whole;
        for (const part of song.parts) {
            const gain = gainOf(current, part.key).gain;
            gain.cancelScheduledValues(context.currentTime);
            if (fadeIn) {
                gain.setValueAtTime(0, context.currentTime);
                gain.setTargetAtTime(level(part.key), at, MIX_MOVE);
            } else {
                gain.value = level(part.key);
            }
        }
        const line = song.parts.map((part, i) => {
            const samples = rendered[i];
            const buffer = bufferOf(samples);
            const source = context.createBufferSource();
            source.buffer = buffer;
            source.from = offset;
            source.connect(current.gains.get(part.key));
            if (offset < buffer.duration) source.start(at, offset);
            return source;
        }).filter((source) => source.from < source.buffer.duration);
        current.sources.push(...line);
        // The whole song: the rest joins where the line ends; until it has, the line's end doesn't end the play.
        if (line.length) ends(current, line, 'line', gen);
        if (whole) join(current, gen);
        else {
            current.joining = false;
            if (!line.length) stopped();
        }
    }

    // The longest of `list` ending ends the play, when `which` (the line's voices or the rest) is what's playing.
    function ends(current, list, which, gen) {
        const left = (source) => source.buffer.duration - (source.from ?? 0);
        const last = list.reduce((a, b) => (left(b) > left(a) ? b : a));
        last.addEventListener('ended', () => {
            if (current.gen !== gen) return;
            if (which === 'line') current.lineEnded = true;
            if (play === current && current.ending === which && !current.joining) stopped();
        });
    }

    // Between the render and the voices apart, after a part is switched, heard alone or brought forward (or all of
    // them again), at the same point in the song. A move still on its way is cancelled by a change back.
    function remix() {
        if (!play || !mixUrl) return;
        const want = mixable();
        if (want === (play.mix !== null)) {
            play.move = null;
            return;
        }
        if (want) toRender(play);
        else toVoices(play);
    }

    // The render carries on until the voices apart are made from where it is; then they fade in as it fades out.
    async function toVoices(current) {
        const move = (current.move = {});
        const m = current.mix;
        realVoices(); // a part on its own: the recorded voices start coming, for the voices apart from now on
        let rendered = null;
        try {
            if (m.el.currentTime < lineLength - 1) rendered = [...(await ready())];
            else await fullVoices();
        } catch (error) {
            console.warn('The voices apart could not be made: the render plays on', error); // i18n-ignore
            return;
        }
        await afterPaint();
        if (play !== current || current.move !== move || current.mix !== m) return;
        current.move = null;
        if (rendered) withReal(rendered, realHere);
        else rendered = song.parts.map(() => new Float32Array(1)); // past the line: the rest joins at once
        const at = context.currentTime + START_AHEAD;
        current.mix = null;
        voicesAt(current, rendered, at, m.el.currentTime + START_AHEAD, true);
        m.gain.gain.cancelScheduledValues(context.currentTime);
        m.gain.gain.setTargetAtTime(0, at, MIX_MOVE);
        m.user = null;
        pauseAfter(m, at + MIX_MOVE * 6);
    }

    // Back to the render (inside the tap, as iPhone asks of a media element): it starts where the voices are, and
    // takes over once it sounds there.
    function toRender(current) {
        const move = (current.move = {});
        const m = mixInto(current.master);
        m.user = current;
        m.gain.gain.cancelScheduledValues(context.currentTime);
        m.gain.gain.value = 0;
        m.el.currentTime = position();
        const gone = () => {
            if (m.user === current && current.mix !== m) {
                m.user = null;
                m.el.pause();
            }
        };
        m.el.play().then(async () => {
            if (play !== current || current.move !== move) return gone();
            // Where the voices have got to while it started (it has that much already, so this seek is quick).
            m.el.currentTime = position() + 0.03;
            await new Promise((resolve) => m.el.addEventListener('seeked', resolve, { once: true }));
            if (play !== current || current.move !== move) return gone();
            current.move = null;
            const now = context.currentTime;
            m.gain.gain.setTargetAtTime(MIX_GAIN, now, MIX_MOVE);
            for (const gain of current.gains.values()) {
                gain.gain.cancelScheduledValues(now);
                gain.gain.setTargetAtTime(0, now, MIX_MOVE);
            }
            for (const source of current.sources) source.stop(now + MIX_MOVE * 6);
            current.sources = [];
            current.gen += 1;
            current.joining = false;
            current.ending = 'mix';
            current.mix = m;
        }).catch((error) => {
            console.warn('The render could not play: the voices carry on', error); // i18n-ignore
            if (error?.name !== 'AbortError') mixFailed = true;
            if (error?.name !== 'AbortError') note('A song render could not take over again, the voices carried on', { error: error?.name ?? 'Error', format: mixUrl.split('.').pop() }); // i18n-ignore
            gone();
        });
    }

    // The rest of the song, sung by the worker (or the real voices), started on the audio clock at the line's end, or
    // where the song has got to if it came late, through the same gains (the voices' switches keep working).
    async function join(current, gen) {
        try {
            const rest = await fullVoices();
            await afterPaint();
            fullParts().then((parts) => (following = parts)).catch(() => {});
            if (play !== current || current.gen !== gen) return;
            const { when, offset } = joinAt(current.start, lineLength, context.currentTime + START_AHEAD);
            const more = song.parts.map((part, i) => {
                const samples = rest[i];
                const buffer = bufferOf(samples);
                const source = context.createBufferSource();
                source.buffer = buffer;
                source.from = offset;
                source.connect(current.gains.get(part.key));
                if (offset < buffer.duration) source.start(when, offset);
                return source;
            }).filter((source) => source.from < source.buffer.duration);
            current.sources.push(...more);
            current.joining = false;
            if (more.length) {
                current.ending = 'rest';
                ends(current, more, 'rest', gen);
            } else if (current.lineEnded) stopped();
        } catch (error) {
            // The rest couldn't come: the line already played says so, and the play ends with it.
            console.warn('The rest of the song could not load', error); // i18n-ignore
            if (current.gen !== gen) return;
            current.joining = false;
            if (play === current && current.lineEnded) stopped();
        }
    }

    function stop() {
        if (pending) {
            pending = null;
            loading(false);
        }
        if (!play) return;
        const { master, sources } = play;
        play.move = null;
        const now = context.currentTime;
        master.gain.setTargetAtTime(0, now, FADE_OUT / 4);
        for (const source of sources) source.stop(now + FADE_OUT);
        // The render (sounding, or on its way back) pauses once the fade is over.
        if (media?.user === play) {
            media.user = null;
            pauseAfter(media, now + FADE_OUT);
        }
        stopped();
    }

    // The pointer going down is already a press: the AudioContext is made then, so the click itself answers at once.
    button.addEventListener('pointerdown', () => {
        if (!play && !pending) context ??= new AudioContext({ latencyHint: 'playback' });
    });
    function begin() {
        // One player at a time.
        for (const other of players) other.stop();
        start().catch((error) => {
            console.warn('The player could not play', error); // i18n-ignore
            stopped();
            states.failed();
        });
    }
    button.addEventListener('click', () => {
        // A second press while it renders, or while it plays, stops it.
        if (play || pending) {
            stop();
            return;
        }
        begin();
    });

    for (const [key, el] of voices) {
        // A row's switch turns its voice on or off and nothing else; the row brings it forward. The
        // tune's voices are buttons that switch.
        const toggle = toggles.get(key) ?? (picks.size ? null : el);
        toggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            switchTo(key, !on.get(key));
            showSets();
            remix();
        });
    }
    const order = [...picks.keys()];
    for (const [key, pick] of picks) {
        pick.addEventListener('click', () => bringForward(key));
        // A radio group: the arrows move to the next voice and bring it forward, Home and End jump.
        pick.addEventListener('keydown', (event) => {
            const at = order.indexOf(key);
            const to = { ArrowDown: at + 1, ArrowRight: at + 1, ArrowUp: at - 1, ArrowLeft: at - 1, Home: 0, End: order.length - 1 }[event.key];
            if (to === undefined) return;
            event.preventDefault();
            const next = order[(to + order.length) % order.length];
            bringForward(next);
            picks.get(next).focus();
        });
    }
    for (const [key, button] of alone) {
        button.addEventListener('click', () => {
            // Alone already: all of them again. Else this one alone; playing carries on from the same spot.
            const solo = button.getAttribute('aria-pressed') === 'true';
            for (const other of on.keys()) switchTo(other, solo || other === key);
            showSets();
            // "Tap a part to hear it on its own": while nothing plays, the tap plays it (it used to only switch).
            if (!play && !pending) begin();
            else remix();
        });
        button.addEventListener('pointerdown', () => {
            if (!play && !pending) context ??= new AudioContext({ latencyHint: 'playback' });
        });
    }
    for (const el of sets) {
        el.addEventListener('click', () => {
            for (const key of on.keys()) switchTo(key, el.dataset.set === 'all' || key === el.dataset.set);
            showSets();
            remix();
        });
    }

    // The Studio's controls may sit inside a voice's row, which switches on a click: theirs stay theirs.
    for (const el of [...levels.values(), ...solos.values(), ...pans.values()]) el.addEventListener('click', (event) => event.stopPropagation());
    for (const [key, el] of levels) el.addEventListener('input', () => ramp(key, SWITCH));
    for (const [key, el] of solos) el.addEventListener('click', () => solo(key));
    for (const [key, el] of pans) {
        el.addEventListener('input', () => {
            const pan = play?.panners.get(key)?.pan;
            if (!pan) return;
            pan.cancelScheduledValues(context.currentTime);
            pan.setTargetAtTime(placeOf(key), context.currentTime, SWITCH);
        });
    }

    // Made ahead: the app's voices once the player is in view, the real ones once someone might press.
    if ('IntersectionObserver' in window) {
        const seen = new IntersectionObserver((entries) => {
            if (!entries.some((e) => e.isIntersecting)) return;
            seen.disconnect();
            if (mixUrl) preconnect(mixUrl);
            const soon = () => synthVoices().catch(() => {});
            if ('requestIdleCallback' in window) requestIdleCallback(soon, { timeout: 2000 });
            else soon();
        });
        seen.observe(root);
    }
    for (const name of ['pointerenter', 'focusin', 'pointerdown']) root.addEventListener(name, likely, { once: true, passive: true });
    for (const link of document.querySelectorAll(`[data-hear="${root.id}"]`)) {
        for (const name of ['pointerenter', 'focus', 'pointerdown']) link.addEventListener(name, likely, { once: true, passive: true });
    }

    root.setAttribute('data-ready', '');
    label(false);
    showSets();
    showSolos();
    show(0);
    return { stop };
}
