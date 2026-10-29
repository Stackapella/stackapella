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
// - [data-set="<part key>|all"]: a button that leaves only that voice on, or all of them;
// - [data-beat] (with data-length for a note, each voice's bars): data-lit while it sounds,
//   data-sung once it has; a word (no length) is lit until the next word starts;
// - [data-time]: "0:06 / 0:18".
// The player gets data-playing while it plays, and --p (0 to 1) for its playhead.
// Anywhere on the page, [data-hear="<player id>"] (the hero's "Hear four voices") brings that player
// into view and plays it, unless it's playing already. Without the script it's a link to the player.

import { heardTime } from '../../engine/clock.js';
import { renderGuide } from '../guides.js';

const START_AHEAD = 0.1; // seconds: room to start every voice on the same frame
const SWITCH = 0.012; // the time constant of a voice's switch, about 50 ms to silence
const FADE_OUT = 0.04;
const LEVEL = 0.6; // four guides together, each about -20 dBFS RMS, kept well under clipping
// The voice brought forward sits 6 dB over the others, which step back so the four together are
// quieter than all four at 1 (1.2 + 3 x 0.6 = 3.0): it never clips.
const FORWARD = 1.2;
const BACK = 0.6;
const BRING = 0.05; // the time constant of bringing a voice forward: about 150 ms to settle

let context = null;
const players = [];

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
    const length = song.beats * secondsPerBeat;
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
        if (mark.to === null) mark.to = starts.find((s) => s > mark.from) ?? length;
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
    const level = (key) => (!on.get(key) ? 0 : forward === null ? 1 : key === forward ? FORWARD : BACK);

    let play = null; // { start, master, gains, sources }
    let pending = null; // the press whose voices are still rendering; Stop or another player cancels it
    let frame = 0;

    const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;

    function label(playing) {
        button.setAttribute('aria-label', playing ? button.dataset.labelStop : button.dataset.labelPlay);
    }

    function show(at) {
        root.style.setProperty('--p', String(Math.min(1, at / length)));
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
        // Made and resumed inside the press, as Safari asks.
        context ??= new AudioContext({ latencyHint: 'playback' });
        const resumed = context.resume();
        button.setAttribute('aria-busy', 'true');
        try {
            const rendered = await Promise.all(song.parts.map((part) => renderGuide({
                templateId: `site:${song.song}`,
                templateVersion: song.version,
                part: { key: part.key, guide: { notes: part.notes } },
                tempo: song.tempo,
                sampleRate: context.sampleRate,
            })));
            await resumed;
            if (pending !== press) return; // stopped, or another player started, while it rendered
            pending = null;
            const master = context.createGain();
            master.gain.value = LEVEL;
            master.connect(context.destination);
            const at = context.currentTime + START_AHEAD;
            const gains = new Map();
            const sources = song.parts.map((part, i) => {
                const samples = rendered[i];
                const buffer = context.createBuffer(1, Math.max(1, samples.length), context.sampleRate);
                buffer.copyToChannel(samples, 0);
                const source = context.createBufferSource();
                source.buffer = buffer;
                const gain = context.createGain();
                gain.gain.value = level(part.key);
                source.connect(gain).connect(master);
                source.start(at);
                gains.set(part.key, gain);
                return source;
            });
            const current = { start: at, master, gains, sources, stop };
            play = current;
            // The longest voice ending ends the play.
            const last = sources.reduce((a, b) => (b.buffer.length > a.buffer.length ? b : a));
            last.addEventListener('ended', () => play === current && stopped());
            root.setAttribute('data-playing', '');
            label(true);
            frame = requestAnimationFrame(tick);
        } finally {
            if (pending === press) pending = null;
            if (pending === null) button.removeAttribute('aria-busy');
        }
    }

    function stop() {
        if (pending) {
            pending = null;
            button.removeAttribute('aria-busy');
        }
        if (!play) return;
        const { master, sources } = play;
        const now = context.currentTime;
        master.gain.setTargetAtTime(0, now, FADE_OUT / 4);
        for (const source of sources) source.stop(now + FADE_OUT);
        stopped();
    }

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

    root.setAttribute('data-ready', '');
    label(false);
    showSets();
    show(0);
    return { stop };
}
