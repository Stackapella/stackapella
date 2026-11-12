// The Sing lane, embedded in a public page (the arcade landing's hero; Hillel: "an actual running
// app ... As we improve the app it will get better"). It draws with the app's own js/lane-painter.js
// and the app's synth, so it follows the app; this file only feeds them a recorded take and keeps the
// time on the AudioContext clock (no mic, no account, no Vue, no import map: public_imports_check).
//
//     import { mount } from '/js/embed/lane.js';
//     const lane = mount(el, { take: '/templates/takes/amazing-grace-alto.json', zoom: 1, trail: '#ffffff', trailGlow: 'var(--color-part-2)', controls: true });
//     lane.play(); lane.pause(); lane.time(); lane.zoom(2); lane.unmute(); lane.mute(); lane.muted();
//     lane.on('note', ({ index, hit, word }) => ...);  // each of the part's notes, as the take passes it
//     lane.on('time', ({ seconds, duration, note }) => ...);  // every frame while playing
//     lane.on('state', ({ playing, ready, muted }) => ...);  lane.on('ended', () => ...);
//
// It loads nothing until it's scrolled into view; with reduced motion the lane holds still between
// frames as the app's does. It reads the page's theme and skin tokens, so it suits either, and draws
// again by itself when they change (theme-boot's stackapella:theme). `trail` and `trailGlow` colour
// your voice's ribbon and dot (default: the part's colour). `controls: true` adds the lane's own bar
// under it (Play or Pause, "0:01 / 0:18" with a thin progress line, zoom − and +); a page that draws
// its own controls leaves it off and uses the API. `muted: true` plays the picture with no sound and no
// AudioContext (so it can start on its own, as the landing's game mode does); unmute(), from a click or
// tap, brings the sound in from where the picture is. play() inside a click handler starts with sound.
// A song's part instead of a take (`song: { slug, part, url }`, the song part pages): the template comes from the
// public API (or `url`), the part is sung by its real voice where it has one (else the synth), and play(mode)
// takes 'all' (the part with the others, the default), 'part' (the part alone) or 'sing' (the others under you
// and the part quietly as a guide, while the mic, opened on that tap, is followed live with Sing's own light:
// your pitch drawn and each note lit as you hit it). voice('real' | 'synth') chooses the part's voice; hasReal()
// says whether it has a real one. The mic only on the sing tap; nothing is recorded or sent.
// Every lane shares one AudioContext; `unlock()` (exported) makes it inside a click, for a page that
// mounts its lane a moment after the click (Safari). The bar's spoken names come from the page, in its
// language (`words`: { play, pause, zoom, zoomOut, zoomIn }), since the app's words need Vue.

import { LanePainter } from '../lane-painter.js';
import { renderPart } from '../synth.js';
import { hzToMidi } from '../dsp.js';
import { pageNaming, pitchName } from '../notes.js';
import { token } from '../colors.js';
import { trackInWorker, VOICED_CLARITY } from '../pitchtrack.js';
import { follow, voiceFollower } from '../livevoice.js';
import { catalogueGuides, realVoice, songTemplate } from '../public/app-voices.js';
import { expressiveTrack, renderTrack } from './take.js';

/** The keyboard's notes in the page's language (notes.js pageNaming): Do5 on a Spanish page, C5 on an English one. */
const laneNoteName = (midi) => {
    const { name, octave } = pitchName(midi, { naming: pageNaming() });
    return `${name}${octave}`;
};

const ZOOMS = [10, 6, 3.5]; // seconds across: zoomed out, the app's own, zoomed in
const UNDER = 0.22; // the other parts, quietly under the take
const GUIDE_UNDER_YOU = 0.3; // singing along: your part's own voice, quietly, as a guide
const OTHERS_UNDER_YOU = 0.3; // singing along: the other parts, a little fuller than under a take
const WITH_OTHERS = 0.55; // a song's part with the others: the part on top, the others clearly there

// The synth sings the parts with no real voice in a worker (js/synth-worker.js, as the app's guides do): rendered here,
// a song's parts held the page for half a second after Play on a slow phone, so a Pause or a scroll waited on them.
let synthWorker = null;
let synthId = 0;
const synthWaiting = new Map();
function renderOff(notes, options) {
    try {
        if (!synthWorker) {
            synthWorker = new Worker(new URL('../synth-worker.js', import.meta.url), { type: 'module' });
            synthWorker.onmessage = ({ data }) => {
                synthWaiting.get(data.id)?.(data.samples);
                synthWaiting.delete(data.id);
            };
        }
    } catch (error) {
        console.warn('No worker for the synth: it sings on the page', error);
        return Promise.resolve(renderPart(notes, options));
    }
    const id = ++synthId;
    return new Promise((resolve) => {
        synthWaiting.set(id, resolve);
        synthWorker.postMessage({ id, notes, options });
    });
}

// One AudioContext for every lane on the page, made inside a person's tap or click: Safari lets audio
// start only from a gesture, and a context made later (after an await or a timer) may not count.
let shared = null;
// The voices are made ahead (prepare(): a page calls it when someone might press), at one rate: an AudioBuffer plays
// at its own rate in any context, so they're ready before the AudioContext exists.
const PREP_RATE = 48000;

/** Makes (or wakes) the page's AudioContext, at once: call it inside the click that wants sound. */
export function unlock() {
    shared ??= new AudioContext();
    shared.resume().catch((error) => console.warn('The audio could not start yet', error));
    return shared;
}

/** Mounts a lane in `el` (sized by the page) for the take file at `take`. */
export function mount(el, { take: takeUrl = null, song: songSource = null, zoom = 1, trail = null, trailGlow = null, controls = false, words = {}, muted = false, open = null } = {}) {
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'display:block;width:100%;height:100%;min-height:0;flex:1';
    canvas.setAttribute('aria-hidden', 'true');
    if (controls) el.style.display = 'flex';
    if (controls) el.style.flexDirection = 'column';
    el.append(canvas);
    const painter = new LanePainter(canvas, el).set({ trail, trailGlow });
    const listeners = new Map();
    const emit = (name, detail) => (listeners.get(name) ?? []).forEach((fn) => fn(detail));
    const state = { loading: null, ready: false, playing: false, offset: 0, startedAt: 0, frame: 0, zoom, ctx: null, sources: [], said: new Set(), muted, mode: 'all', voice: 'real', mic: null, live: null };
    let song = null;

    // Muted, the picture keeps its own time (no AudioContext until someone asks for sound): nothing is
    // heard, so nothing needs the audio clock. With sound, the AudioContext's clock is the time.
    const position = () => (!state.playing ? state.offset : state.muted ? performance.now() / 1000 - state.startedAt + state.offset : Math.max(state.offset, state.ctx.currentTime - state.startedAt + state.offset)); // held until the sources start

    /** The take and its song, read and laid out (once, when first wanted). */
    function load() {
        state.loading ??= (async () => {
            // A recorded take (the landing's hero), or a song's part (the song part pages: the API's template, as the app reads it).
            const json = async (url) => {
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error(`${url} answered ${response.status}`);
                return response.json();
            };
            const take = takeUrl ? await json(takeUrl) : { song: songSource.slug, part: songSource.part };
            const template = takeUrl ? await json(`/templates/${take.song}.json`) : songSource.url ? await json(songSource.url) : await songTemplate(songSource.slug);
            const toBeat = take.toBeat ?? Infinity;
            const within = (n) => n.beat < toBeat && Number.isFinite(n.pitch);
            const part = template.parts.find((p) => p.key === take.part);
            const notes = part.guide.notes.filter(within);
            // The bundled take's own voice, for when the app's voices can't load; what is drawn and scored is what plays.
            const made = take.track ?? expressiveTrack(notes, { tempo: template.tempo, flat: take.flat ?? [], human: 'full' });
            const results = notes.map((n) => ({ beat: n.beat, hit: false, inTune: [] })); // scored once its voice is heard (hear())
            const others = template.parts.filter((p) => p.key !== take.part && p.kind !== 'instrument');
            // Top to bottom as the song lists them (SATB, or barbershop's tenor, lead, baritone and bass).
            const voices = template.parts.filter((p) => p.kind !== 'instrument').map((p) => p.key);
            const mine = voices.indexOf(take.part);
            song = {
                take,
                template,
                notes,
                made,
                track: [],
                results,
                duration: Math.max(...notes.map((n) => n.beat + n.length)) * (60 / template.tempo) + 0.6,
                // The parts either side of yours, faint, with their names at the edge (▲ Soprano, ▼ Tenor).
                others: others
                    .filter((p) => Math.abs(voices.indexOf(p.key) - mine) === 1)
                    .map((p) => ({ key: p.key, name: p.name, notes: p.guide.notes.filter(within), above: voices.indexOf(p.key) < mine })),
                voices,
                underKeys: others.map((p) => p.key),
                parts: Object.fromEntries(template.parts.map((p) => [p.key, p.guide.notes.filter(within)])),
            };
            painter.set({ notes, words: notes.filter((n) => n.lyric).map((n) => ({ beat: n.beat, text: n.lyric })), beatSeconds: 60 / template.tempo, noteName: laneNoteName, keyboard: true, state: 'turn', secondsShown: ZOOMS[state.zoom] });
            colour();
            state.ready = true;
            emit('state', { playing: false, ready: true });
            draw();
        })();
        return state.loading;
    }

    /** The parts' colours in the page's theme and skin now (the app's part tokens, css/themes.css). */
    function colour() {
        if (!song) return;
        // Each part in its place's colour, top to bottom, as the app colours a song's parts.
        const colorOf = (key) => token(`part-${Math.max(0, song.voices.indexOf(key)) + 1}`, '#c9b456');
        painter.set({ color: colorOf(song.take.part), others: song.others.map((o) => ({ ...o, color: colorOf(o.key) })) });
    }

    /** The voice heard so far, the notes passed (hit or missed), and the lane drawn at the take's time. */
    function draw() {
        if (!song) return;
        const seconds = position();
        // Muted, nothing is heard, so the picture draws the take's own voice as planned (never scored: hear() scores).
        const shown = state.muted && !song.track.length ? song.made : song.track;
        const voice = shown.filter((p) => p.seconds <= seconds).map((p) => ({ ...p, alpha: 1 }));
        const lit = new Set();
        const hits = new Map();
        song.notes.forEach((n, index) => {
            const end = (n.beat + n.length) * (60 / song.template.tempo);
            const result = song.results[index];
            hits.set(n.beat, result.inTune.filter(([from]) => from <= seconds).map(([from, to]) => [from, Math.min(to, seconds)]));
            if (seconds < end) return;
            if (result.hit) lit.add(n.beat);
            if (!state.said.has(index)) {
                state.said.add(index);
                emit('note', { index, hit: result.hit, word: n.lyric ?? '', beat: n.beat });
            }
        });
        painter.set({ position: seconds, voice, lit, hits }).draw();
        const now = song.notes.findIndex((n) => seconds >= n.beat * (60 / song.template.tempo) && seconds < (n.beat + n.length) * (60 / song.template.tempo));
        emit('time', { seconds, duration: song.duration, note: now });
    }

    function tick() {
        draw();
        if (position() >= song.duration) {
            pause();
            state.offset = 0;
            state.said.clear();
            emit('ended');
            draw();
            return;
        }
        if (state.playing) state.frame = requestAnimationFrame(tick);
    }

    /**
     * The take and the parts under it, from `offset`, all started on the audio clock together; muted,
     * only the picture plays (call it from anywhere: no sound, so no click needed).
     */
    /**
     * Starts once (Hillel: "i clicked again and ended up with 2 demos playing"): a press while the take is still
     * loading or starting waits for that same start, never a second set of voices.
     */
    function play_(asked) {
        const mode = typeof asked === 'string' ? asked : state.mode; // a click handler's event is not a mode
        if (state.starting) return state.starting;
        if (state.playing && mode === state.mode) return Promise.resolve();
        if (state.playing) pause();
        if (mode !== state.mode) {
            // Another way to play it starts the song again.
            state.offset = 0;
            state.said.clear();
        }
        state.mode = mode;
        if (!state.muted) state.ctx ??= unlock(); // before any await: still inside the click
        // Singing along: the mic, asked for on this tap (live-mic.js, the site's opt-in mic; nothing kept).
        if (mode === 'sing' && !state.mic) {
            // The mic reads on the site's shared clock (voices.js), not the lane's: its lag is measured against that one.
            const opener = open ?? ((options) => Promise.all([import('../public/live-mic.js'), import('../public/voices.js')]).then(([m, v]) => {
                state.micClock = () => v.audio().currentTime;
                return m.listen(options);
            }));
            state.mic = opener({ onFrame: liveFrame }).catch((error) => {
                state.mic = null;
                emit('mic', { error: error?.reason ?? 'unsupported' });
                return null;
            });
        }
        emit('state', { playing: false, ready: state.ready, muted: state.muted, starting: true });
        state.starting = start_().finally(() => {
            state.starting = null;
            if (!state.playing) emit('state', { playing: false, ready: state.ready, muted: state.muted, starting: false });
        });
        return state.starting;
    }

    async function start_() {
        await load();
        if (state.playing) return;
        if (state.muted) {
            state.startedAt = performance.now() / 1000;
            state.playing = true;
            if (state.offset === 0) state.said.clear();
            emit('state', { playing: true, ready: true, muted: true });
            state.frame = requestAnimationFrame(tick);
            return;
        }
        state.ctx ??= unlock();
        await state.ctx.resume();
        song.buffers ??= await prepare();
        if (state.mode === 'sing') {
            if (!(await state.mic)) return; // no mic: nothing to follow (the 'mic' event said why)
            if (state.offset === 0 || !state.live) {
                state.live = voiceFollower();
                song.track = [];
                song.results = song.notes.map((n) => ({ beat: n.beat, hit: false, inTune: [] }));
            }
        } else if (song.heard) {
            song.track = song.heard.track;
            song.results = song.heard.results;
        }
        // The part (first) and the others: together, the part alone, or the others under you with the part as a guide.
        const gainOf = (i, gain) => (state.mode === 'part' ? (i === 0 ? 1 : 0) : state.mode === 'sing' ? (i === 0 ? GUIDE_UNDER_YOU : OTHERS_UNDER_YOU) : songSource && i > 0 ? WITH_OTHERS : gain);
        const at = state.ctx.currentTime + 0.05;
        state.sources = song.buffers.map(({ buffer, gain }, i) => ({ buffer, gain: gainOf(i, gain) })).filter(({ gain }) => gain > 0).map(({ buffer, gain }) => {
            const source = state.ctx.createBufferSource();
            source.buffer = buffer;
            const level = state.ctx.createGain();
            level.gain.value = gain;
            source.connect(level).connect(state.ctx.destination);
            source.start(at, state.offset);
            return source;
        });
        state.startedAt = at;
        state.playing = true;
        if (state.offset === 0) state.said.clear();
        emit('state', { playing: true, ready: true, muted: false, mode: state.mode });
        state.frame = requestAnimationFrame(tick);
    }

    /**
     * Every part's voice as the app serves it now (Hillel: "as people record it the audio should update everywhere"):
     * the song's current guides from the public catalogue (templates.catalogue), a real voice where a part has one
     * for this version of the song, the app's own synth where it doesn't. If the catalogue can't be read, the bundled
     * take sings the part followed. Then the voice that plays the part followed is heard with the app's own tracker and
     * Sing's own light (hear()), so the lane shows what that voice really earns.
     */
    async function buffers(rate, decoder) {
        const make = (samples, gain, offsetSeconds = 0) => {
            const lead = Math.max(0, Math.round(offsetSeconds * rate));
            const skip = Math.max(0, Math.round(-offsetSeconds * rate));
            const buffer = new AudioBuffer({ numberOfChannels: 1, length: Math.max(1, lead + samples.length - skip), sampleRate: rate });
            buffer.copyToChannel(samples.subarray(Math.min(skip, samples.length)), 0, lead);
            return { buffer, gain };
        };
        const guides = await catalogueGuides(song.take.song, song.template);
        song.hasReal = Boolean(guides?.[song.take.part]);
        const synth = (key, i) => renderOff(song.parts[key], { bpm: song.template.tempo, sampleRate: rate, human: 'full', seed: 40 + i });
        const real = (guide) => realVoice(guide, decoder);
        const voiceOf = async (key, i) => {
            if (guides?.[key] && !(key === song.take.part && state.voice === 'synth')) {
                try {
                    return await real(guides[key]);
                } catch (error) {
                    console.warn('A real voice could not load: the synth sings it', error);
                }
            }
            if (!guides && key === song.take.part) return song.take.audio ? { samples: (await decoder.decodeAudioData(await (await fetch(song.take.audio)).arrayBuffer())).getChannelData(0), offsetSeconds: 0 } : { samples: renderTrack(song.made, rate, { human: 'full' }), offsetSeconds: 0 };
            return { samples: await synth(key, i), offsetSeconds: 0 };
        };
        const keys = [song.take.part, ...song.underKeys];
        const voices = await Promise.all(keys.map((key, i) => voiceOf(key, i)));
        await hear(voices[0], rate);
        return voices.map((v, i) => make(v.samples, i === 0 ? 1 : UNDER, v.offsetSeconds));
    }

    /** Every voice made and heard, once: before a press when the page asks (no AudioContext needed), else on the press. */
    function prepare() {
        state.prepared ??= (async () => {
            await load();
            return buffers(PREP_RATE, new OfflineAudioContext(1, 1, PREP_RATE));
        })().catch((error) => {
            state.prepared = null;
            throw error;
        });
        return state.prepared;
    }

    /** The part followed, heard as Sing hears a singer: the tracker's readings through Sing's own light (livevoice.js). */
    async function hear({ samples, offsetSeconds }, rate) {
        const tracked = await trackInWorker(samples, rate);
        const beat = 60 / song.template.tempo;
        let voice = voiceFollower();
        const track = [];
        for (let i = 0; i < tracked.hz.length; i++) {
            const seconds = offsetSeconds + i / tracked.rate;
            const hz = tracked.hz[i];
            const clear = hz > 0 && tracked.clarity[i] >= VOICED_CLARITY ? hz : 0;
            const note = song.notes.find((n) => seconds >= n.beat * beat && seconds < (n.beat + n.length) * beat);
            voice = follow(voice, { seconds, hz: clear, shownHz: hz > 0 ? hz : 0, loudness: tracked.loudness[i] }, note ? { beat: note.beat, pitch: note.pitch } : null);
            if (clear) track.push({ seconds, midi: hzToMidi(clear), level: Math.max(0.15, Math.min(1, (tracked.loudness[i] + 50) / 40)) });
        }
        song.track = track;
        song.results = song.notes.map((n) => ({ beat: n.beat, hit: voice.lit.has(n.beat), inTune: voice.hits.get(n.beat) ?? [] }));
        song.heard = { track: song.track, results: song.results }; // the voice's own, back after a sing-along
    }

    /** The mic's reading now, followed as Sing follows a singer: your pitch drawn, each note lit as you hit it. */
    function liveFrame(frame) {
        if (!state.playing || state.mode !== 'sing' || !state.live || !state.ctx) return;
        // Where the song was when the mic heard it: now, less how long ago the reading was taken.
        const seconds = position() - Math.max(0, (state.micClock?.() ?? frame.at) - frame.at);
        if (seconds < 0) return;
        const beat = 60 / song.template.tempo;
        const note = song.notes.find((n) => seconds >= n.beat * beat && seconds < (n.beat + n.length) * beat);
        const hz = frame.hz > 0 ? frame.hz : 0;
        state.live = follow(state.live, { seconds, hz, shownHz: hz, loudness: frame.loudness ?? -30 }, note ? { beat: note.beat, pitch: note.pitch } : null);
        if (hz) song.track.push({ seconds, midi: hzToMidi(hz), level: Math.max(0.15, Math.min(1, ((frame.loudness ?? -30) + 50) / 40)) });
        song.results = song.notes.map((n) => ({ beat: n.beat, hit: state.live.lit.has(n.beat), inTune: state.live.hits.get(n.beat) ?? [] }));
    }

    function closeMic() {
        state.mic?.then((m) => m?.stop()).catch(() => {});
        state.mic = null;
    }

    function pause() {
        if (!state.playing) return;
        state.offset = position();
        state.playing = false;
        cancelAnimationFrame(state.frame);
        state.sources.forEach((s) => {
            try {
                s.stop();
            } catch (error) {
                console.warn('A source had already stopped', error);
            }
        });
        state.sources = [];
        if (state.mode === 'sing') closeMic();
        emit('state', { playing: false, ready: true, muted: state.muted, mode: state.mode });
    }

    /**
     * Sound on, from a person's tap or click (a browser lets audio start only then): playing, the take
     * carries on from where the picture is, now on the audio clock; paused, the next play has sound.
     */
    async function unmute() {
        if (!state.muted) return;
        const wasPlaying = state.playing;
        if (wasPlaying) pause();
        state.muted = false;
        state.ctx ??= unlock(); // inside the gesture, so it may start
        if (wasPlaying) await play_();
        else emit('state', { playing: false, ready: state.ready, muted: false });
    }

    /** Sound off: the picture carries on from where it is, on its own time. */
    function mute() {
        if (state.muted) return;
        const wasPlaying = state.playing;
        if (wasPlaying) pause();
        state.muted = true;
        if (wasPlaying) play_();
        else emit('state', { playing: false, ready: state.ready, muted: true });
    }

    // The lane's own control bar (controls: true): Play or Pause as an icon, the time as "0:01 / 0:18"
    // with a thin progress line, and zoom − and + as one control. Plain DOM in the page's tokens.
    const bar = controls ? controlBar() : null;
    function controlBar() {
        const icon = { play: '<path d="M8 5.5v13l11-6.5z" fill="currentColor"/>', pause: '<rect x="7" y="5.5" width="3.5" height="13" rx="1" fill="currentColor"/><rect x="13.5" y="5.5" width="3.5" height="13" rx="1" fill="currentColor"/>', minus: '<path d="M6 12h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>', plus: '<path d="M12 6v12M6 12h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>' };
        const svg = (name) => `<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">${icon[name]}</svg>`;
        const round = 'display:grid;place-items:center;width:40px;height:40px;border-radius:999px;cursor:pointer;';
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 12px;color:var(--color-cream)';
        row.innerHTML = `<button type="button" data-lane-play aria-label="${words.play ?? ''}" style="${round}border:0;background:var(--color-cream);color:var(--color-ink)">${svg('play')}</button>
            <div style="flex:1;min-width:0;display:flex;align-items:center;gap:10px">
                <span data-lane-time style="font-variant-numeric:tabular-nums;font-size:14px;font-weight:600;white-space:nowrap">0:00 <span style="color:var(--color-muted)">/ 0:00</span></span>
                <span style="flex:1;height:3px;border-radius:999px;background:var(--color-line);overflow:hidden"><span data-lane-progress style="display:block;height:100%;width:0;background:var(--color-cream)"></span></span>
            </div>
            <div role="group" aria-label="${words.zoom ?? ''}" style="display:flex;align-items:center;gap:2px;padding:2px;border:1px solid var(--color-line);border-radius:999px">
                <button type="button" data-lane-zoom-out aria-label="${words.zoomOut ?? ''}" style="${round}width:36px;height:36px;border:0;background:transparent;color:var(--color-cream)">${svg('minus')}</button>
                <button type="button" data-lane-zoom-in aria-label="${words.zoomIn ?? ''}" style="${round}width:36px;height:36px;border:0;background:transparent;color:var(--color-cream)">${svg('plus')}</button>
            </div>`;
        el.append(row);
        const clock = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
        const play = row.querySelector('[data-lane-play]');
        const time = row.querySelector('[data-lane-time]');
        const progress = row.querySelector('[data-lane-progress]');
        const zoomButtons = () => {
            row.querySelector('[data-lane-zoom-out]').disabled = state.zoom <= 0;
            row.querySelector('[data-lane-zoom-in]').disabled = state.zoom >= ZOOMS.length - 1;
        };
        play.onclick = () => (state.playing ? pause() : play_());
        row.querySelector('[data-lane-zoom-out]').onclick = () => (api.zoom(state.zoom - 1), zoomButtons());
        row.querySelector('[data-lane-zoom-in]').onclick = () => (api.zoom(state.zoom + 1), zoomButtons());
        zoomButtons();
        const show = ({ seconds, duration }) => {
            time.firstChild.textContent = `${clock(seconds)} `;
            time.lastElementChild.textContent = `/ ${clock(duration)}`;
            progress.style.width = `${Math.min(100, (100 * seconds) / Math.max(1, duration))}%`;
        };
        listeners.set('time', [...(listeners.get('time') ?? []), show]);
        listeners.set('state', [...(listeners.get('state') ?? []), ({ playing }) => {
            play.innerHTML = svg(playing ? 'pause' : 'play');
            play.setAttribute('aria-label', (playing ? words.pause : words.play) ?? '');
            if (song) show({ seconds: position(), duration: song.duration });
        }]);
        return row;
    }

    function restyle() {
        painter.readTokens();
        colour();
        draw();
    }
    document.addEventListener('stackapella:theme', restyle);

    // Loaded when scrolled into view, and drawn again as the page resizes.
    const seen = new IntersectionObserver((entries) => {
        if (entries.some((e) => e.isIntersecting)) {
            seen.disconnect();
            load().catch((error) => {
                emit('error', { error });
                console.error('The lane could not load', error);
            });
        }
    });
    seen.observe(el);
    const resized = new ResizeObserver(() => draw());
    resized.observe(canvas);

    const api = {
        play: play_,
        pause,
        toggle: () => (state.starting ? state.starting : state.playing ? pause() : play_()),
        /** Makes the voices now, before a press (someone hovering or touching near Play): the press then starts at once. */
        prepare: () => {
            if (!state.muted) prepare().catch((error) => console.warn('The voices could not be made ahead: the press makes them', error));
        },
        /** The take's time now, in seconds (read off the audio clock while playing). */
        time: () => position(),
        duration: () => song?.duration ?? 0,
        playing: () => state.playing,
        /** Sound on (call it from a click or tap), or off; muted() says which. */
        unmute,
        mute,
        muted: () => state.muted,
        /** Zoom: 0 (out), 1 (the app's), 2 (in); returns the zoom now. */
        zoom(level) {
            if (level !== undefined) {
                state.zoom = Math.max(0, Math.min(ZOOMS.length - 1, level));
                painter.set({ secondsShown: ZOOMS[state.zoom] });
                draw();
            }
            return state.zoom;
        },
        /** The current play mode: 'all', 'part' or 'sing' (set by play(mode)). */
        mode: () => state.mode,
        /** Whether the part has a real singer's voice for this version (known once its voices are made). */
        hasReal: () => Boolean(song?.hasReal),
        /** The part's voice: 'real' (its singer, where there is one) or 'synth'; the next play uses it. */
        voice(kind) {
            if (kind && kind !== state.voice) {
                state.voice = kind;
                pause();
                if (song) song.buffers = null;
                state.prepared = null;
            }
            return state.voice;
        },
        /** The theme or skin changed: the lane's colours again (it does this itself on theme-boot's event). */
        restyle,
        on(name, fn) {
            listeners.set(name, [...(listeners.get(name) ?? []), fn]);
            return () => listeners.set(name, (listeners.get(name) ?? []).filter((f) => f !== fn));
        },
        destroy() {
            document.removeEventListener('stackapella:theme', restyle);
            bar?.remove();
            pause();
            closeMic();
            seen.disconnect();
            resized.disconnect();
            painter.destroy();
            canvas.remove(); // the shared AudioContext stays for the page's other lanes
        },
    };
    return api;
}
