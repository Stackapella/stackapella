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
// clock exactly where the song has got to, so the parts never drift. The seam for showreel's rendered full mixes, later:
// the song's data gains a `mix` URL, and a player with one plays that file instead of joining the rest. A press answers
// at once and shows its loading, a slow load and a failure the same way on every player (play-state.js).

import { heardTime } from '../../engine/clock.js';
import { renderGuide } from '../guides.js';
import { catalogueGuides, placed, realVoice, songTemplate } from './app-voices.js';
import { playState } from './play-state.js';

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

let context = null;
const players = [];

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
        return [p.key, toggle ? toggle.getAttribute('aria-checked') !== 'false' : voices.get(p.key)?.getAttribute('aria-pressed') !== 'false'];
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
    // A part with a real voice for this version of the song sings with it (the same voices Sing plays), cut to the line.
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
        const voices = await realVoices();
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
        realVoices();
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

    function tick() {
        if (!play) return;
        show(Math.max(0, heardTime(context) - play.start));
        frame = requestAnimationFrame(tick);
    }

    function stopped() {
        cancelAnimationFrame(frame);
        play = null;
        root.removeAttribute('data-playing');
        label(false);
        show(0);
    }

    function ramp(key, constant) {
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
    }

    function showSets() {
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
        try {
            const voicesOnTheWay = realVoices();
            const rendered = [...(await ready())];
            // The real voices if they're here in time; else the app's own voice sings and they're kept for the next press.
            // A limit on the wait for the network, not audio timing: the voices still start together on the audio clock.
            const voices = await Promise.race([voicesOnTheWay, new Promise((resolve) => setTimeout(() => resolve(null), REAL_WAIT))]);
            song.parts.forEach((part, i) => {
                const voice = voices?.[part.key];
                if (voice) rendered[i] = placed(voice.samples, voice.offsetSeconds, RATE, rendered[i].length);
            });
            await resumed;
            if (pending !== press) return; // stopped, or another player started, while it rendered
            pending = null;
            const master = context.createGain();
            master.gain.value = LEVEL;
            master.connect(context.destination);
            const at = context.currentTime + START_AHEAD;
            const gains = new Map();
            const panners = new Map();
            const sources = song.parts.map((part, i) => {
                const samples = rendered[i];
                const buffer = context.createBuffer(1, Math.max(1, samples.length), RATE);
                buffer.copyToChannel(samples, 0);
                const source = context.createBufferSource();
                source.buffer = buffer;
                const gain = context.createGain();
                gain.gain.value = level(part.key);
                if (pans.has(part.key) && context.createStereoPanner) {
                    const panner = context.createStereoPanner();
                    panner.pan.value = placeOf(part.key);
                    panners.set(part.key, panner);
                    source.connect(gain).connect(panner).connect(master);
                } else {
                    source.connect(gain).connect(master);
                }
                source.start(at);
                gains.set(part.key, gain);
                return source;
            });
            // The whole song: the rest joins where the line ends; until it has, the line's end doesn't end the play.
            // `ending`: which voices' end ends the play, the line's until the rest has joined.
            const current = { start: at, master, gains, panners, sources, stop, joining: whole, ending: 'line', lineEnded: false };
            play = current;
            // The longest voice ending ends the play.
            const ends = (list, which) => {
                const left = (source) => source.buffer.duration - (source.from ?? 0);
                const last = list.reduce((a, b) => (left(b) > left(a) ? b : a));
                last.addEventListener('ended', () => {
                    if (which === 'line') current.lineEnded = true;
                    if (play === current && current.ending === which && !current.joining) stopped();
                });
            };
            ends(sources, 'line');
            root.setAttribute('data-playing', '');
            root.setAttribute('data-played', '');
            loading(false);
            states.playing();
            label(true);
            frame = requestAnimationFrame(tick);
            if (whole) join(current, ends);
            else current.joining = false;
        } finally {
            if (pending === press) pending = null;
            if (pending === null) loading(false);
        }
    }

    // The rest of the song, sung by the worker (or the real voices), started on the audio clock at the line's end, or
    // where the song has got to if it came late, through the same gains (the voices' switches keep working).
    async function join(current, ends) {
        try {
            const rest = await fullVoices();
            fullParts().then((parts) => (following = parts)).catch(() => {});
            if (play !== current) return;
            const { when, offset } = joinAt(current.start, lineLength, context.currentTime + START_AHEAD);
            const more = song.parts.map((part, i) => {
                const samples = rest[i];
                const buffer = context.createBuffer(1, Math.max(1, samples.length), RATE);
                buffer.copyToChannel(samples, 0);
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
                ends(more, 'rest');
            } else if (current.lineEnded) stopped();
        } catch (error) {
            // The rest couldn't come: the line already played says so, and the play ends with it.
            console.warn('The rest of the song could not load', error); // i18n-ignore
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
        const now = context.currentTime;
        master.gain.setTargetAtTime(0, now, FADE_OUT / 4);
        for (const source of sources) source.stop(now + FADE_OUT);
        stopped();
    }

    // The pointer going down is already a press: the AudioContext is made then, so the click itself answers at once.
    button.addEventListener('pointerdown', () => {
        if (!play && !pending) context ??= new AudioContext({ latencyHint: 'playback' });
    });
    button.addEventListener('click', () => {
        // A second press while it renders, or while it plays, stops it.
        if (play || pending) {
            stop();
            return;
        }
        // One player at a time.
        for (const other of players) other.stop();
        start().catch((error) => {
            console.warn('The player could not play', error); // i18n-ignore
            stopped();
            states.failed();
        });
    });

    for (const [key, el] of voices) {
        // A row's switch turns its voice on or off and nothing else; the row brings it forward. The
        // tune's voices are buttons that switch.
        const toggle = toggles.get(key) ?? (picks.size ? null : el);
        toggle?.addEventListener('click', (event) => {
            event.stopPropagation();
            switchTo(key, !on.get(key));
            showSets();
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
    for (const el of sets) {
        el.addEventListener('click', () => {
            for (const key of on.keys()) switchTo(key, el.dataset.set === 'all' || key === el.dataset.set);
            showSets();
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
