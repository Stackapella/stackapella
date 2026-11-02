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
// Every lane shares one AudioContext; `unlock()` (exported) makes it inside a click, for a page that
// mounts its lane a moment after the click (Safari). The bar's spoken names come from the page, in its
// language (`words`: { play, pause, zoom, zoomOut, zoomIn }), since the app's words need Vue.

import { LanePainter } from '../lane-painter.js';
import { renderPart } from '../synth.js';
import { hzToMidi, midiName } from '../dsp.js';
import { token } from '../colors.js';
import { trackInWorker, VOICED_CLARITY } from '../pitchtrack.js';
import { follow, voiceFollower } from '../livevoice.js';
import { catalogueGuides, realVoice } from '../public/app-voices.js';
import { expressiveTrack, renderTrack } from './take.js';

const ZOOMS = [10, 6, 3.5]; // seconds across: zoomed out, the app's own, zoomed in
const UNDER = 0.22; // the other parts, quietly under the take

// One AudioContext for every lane on the page, made inside a person's tap or click: Safari lets audio
// start only from a gesture, and a context made later (after an await or a timer) may not count.
let shared = null;

/** Makes (or wakes) the page's AudioContext, at once: call it inside the click that wants sound. */
export function unlock() {
    shared ??= new AudioContext();
    shared.resume().catch((error) => console.warn('The audio could not start yet', error));
    return shared;
}

/** Mounts a lane in `el` (sized by the page) for the take file at `take`. */
export function mount(el, { take: takeUrl, zoom = 1, trail = null, trailGlow = null, controls = false, words = {}, muted = false } = {}) {
    const canvas = document.createElement('canvas');
    canvas.style.cssText = 'display:block;width:100%;height:100%;min-height:0;flex:1';
    canvas.setAttribute('aria-hidden', 'true');
    if (controls) el.style.display = 'flex';
    if (controls) el.style.flexDirection = 'column';
    el.append(canvas);
    const painter = new LanePainter(canvas, el).set({ trail, trailGlow });
    const listeners = new Map();
    const emit = (name, detail) => (listeners.get(name) ?? []).forEach((fn) => fn(detail));
    const state = { loading: null, ready: false, playing: false, offset: 0, startedAt: 0, frame: 0, zoom, ctx: null, sources: [], said: new Set(), muted };
    let song = null;

    // Muted, the picture keeps its own time (no AudioContext until someone asks for sound): nothing is
    // heard, so nothing needs the audio clock. With sound, the AudioContext's clock is the time.
    const position = () => (!state.playing ? state.offset : state.muted ? performance.now() / 1000 - state.startedAt + state.offset : Math.max(state.offset, state.ctx.currentTime - state.startedAt + state.offset)); // held until the sources start

    /** The take and its song, read and laid out (once, when first wanted). */
    function load() {
        state.loading ??= (async () => {
            const take = await (await fetch(takeUrl)).json();
            const template = await (await fetch(`/templates/${take.song}.json`)).json();
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
            painter.set({ notes, words: notes.filter((n) => n.lyric).map((n) => ({ beat: n.beat, text: n.lyric })), beatSeconds: 60 / template.tempo, noteName: midiName, keyboard: true, state: 'turn', secondsShown: ZOOMS[state.zoom] });
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
        const voice = song.track.filter((p) => p.seconds <= seconds).map((p) => ({ ...p, alpha: 1 }));
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
    function play_() {
        if (state.starting) return state.starting;
        if (state.playing) return Promise.resolve();
        if (!state.muted) state.ctx ??= unlock(); // before any await: still inside the click
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
        const rate = state.ctx.sampleRate;
        song.buffers ??= await buffers(rate);
        const at = state.ctx.currentTime + 0.05;
        state.sources = song.buffers.map(({ buffer, gain }) => {
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
        emit('state', { playing: true, ready: true, muted: false });
        state.frame = requestAnimationFrame(tick);
    }

    /**
     * Every part's voice as the app serves it now (Hillel: "as people record it the audio should update everywhere"):
     * the song's current guides from the public catalogue (templates.catalogue), a real voice where a part has one
     * for this version of the song, the app's own synth where it doesn't. If the catalogue can't be read, the bundled
     * take sings the part followed. Then the voice that plays the part followed is heard with the app's own tracker and
     * Sing's own light (hear()), so the lane shows what that voice really earns.
     */
    async function buffers(rate) {
        const make = (samples, gain, offsetSeconds = 0) => {
            const lead = Math.max(0, Math.round(offsetSeconds * rate));
            const skip = Math.max(0, Math.round(-offsetSeconds * rate));
            const buffer = state.ctx.createBuffer(1, Math.max(1, lead + samples.length - skip), rate);
            buffer.copyToChannel(samples.subarray(Math.min(skip, samples.length)), 0, lead);
            return { buffer, gain };
        };
        const guides = await catalogueGuides(song.take.song, song.template);
        const synth = (key, i) => renderPart(song.parts[key], { bpm: song.template.tempo, sampleRate: rate, human: 'full', seed: 40 + i });
        const real = (guide) => realVoice(guide, state.ctx);
        const voiceOf = async (key, i) => {
            if (guides?.[key]) {
                try {
                    return await real(guides[key]);
                } catch (error) {
                    console.warn('A real voice could not load: the synth sings it', error);
                }
            }
            if (!guides && key === song.take.part) return song.take.audio ? { samples: (await state.ctx.decodeAudioData(await (await fetch(song.take.audio)).arrayBuffer())).getChannelData(0), offsetSeconds: 0 } : { samples: renderTrack(song.made, rate, { human: 'full' }), offsetSeconds: 0 };
            return { samples: synth(key, i), offsetSeconds: 0 };
        };
        const keys = [song.take.part, ...song.underKeys];
        const voices = await Promise.all(keys.map((key, i) => voiceOf(key, i)));
        await hear(voices[0], rate);
        return voices.map((v, i) => make(v.samples, i === 0 ? 1 : UNDER, v.offsetSeconds));
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
        emit('state', { playing: false, ready: true, muted: state.muted });
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
            load().catch((error) => console.error('The lane could not load', error));
        }
    });
    seen.observe(el);
    const resized = new ResizeObserver(() => draw());
    resized.observe(canvas);

    const api = {
        play: play_,
        pause,
        toggle: () => (state.starting ? state.starting : state.playing ? pause() : play_()),
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
            seen.disconnect();
            resized.disconnect();
            painter.destroy();
            canvas.remove(); // the shared AudioContext stays for the page's other lanes
        },
    };
    return api;
}
