// The audio engine. Everything that has to line up is scheduled in frames on the AudioContext
// clock: playback with AudioBufferSourceNode.start(), recording by the frame stamps the worklet
// sees. Never with timers. Proven in the audio spike (docs/audio-spike.md).
//
// A take's first sample is the project position recording started from, because the recorder
// starts `latencyFrames` (the measured round trip) after the frame that position plays on.
//
// Interruptions (a call, the screen locking, the mic unplugged, another app taking the audio) end
// a recording early, never lose it: `onInterrupt` tells the app, and stop() returns the take up to
// that moment, even when the context can no longer run (docs/operations-plan.md, Devices).

import { heardTime } from './clock.js';
import { click } from '../js/dsp.js';

const workletUrl = new URL('./recorder-worklet.js', import.meta.url).href;
// Failures another way of asking won't fix: the person (or the browser) said no, or the page can't ask.
const FINAL_MIC_ERRORS = new Set(['NotAllowedError', 'PermissionDeniedError', 'SecurityError']);
const LEAD_SECONDS = 0.15;

export class Engine {
    constructor() {
        this.ctx = null;
        this.playBus = null; // everything we play goes through here to the speakers
        this.voiceBus = null; // the parts (not the clicks or the timing check), into playBus
        this.room = null; // Hear it polished's room: { channels, wet, nodes }
        this.inputBus = null; // what the recorder hears: the mic, or playBus in loopback mode
        this.recorder = null;
        this.mic = null; // { stream, source, analyser, label, deviceId, settings }
        this.loopback = false;
        this.parts = new Map(); // id -> { buffer, offsetSeconds, gain, pan }
        this.scheduled = [];
        this.startFrames = new WeakMap(); // each scheduled source's first frame, so a later start can cancel only what comes after it
        this.capture = null;
        this.captureId = 0;
        this.playing = null; // { startTime, fromSeconds }
        this.timeline = null; // the segments being played (see play)
        this.clicks = null;
        this.onDeviceChange = null;
        this.onInterrupt = null; // (reason) => void: 'audio' (the context stopped) or 'mic' (the mic ended)
        this.micPending = null;
        this.micGeneration = 0;
        this.listening = false;
    }

    get sampleRate() {
        return this.ctx ? this.ctx.sampleRate : 48000;
    }

    get unlocked() {
        return !!this.ctx && this.ctx.state === 'running';
    }

    /** Must run from a tap: browsers (iOS above all) only start audio after a user gesture. */
    async unlock() {
        if (!this.ctx) {
            if (navigator.audioSession) {
                try {
                    navigator.audioSession.type = 'play-and-record';
                } catch (error) {
                    console.warn('audioSession.type could not be set', error);
                }
            }
            await this.build();
            if (!this.listening) {
                navigator.mediaDevices?.addEventListener('devicechange', () => this.onDeviceChange?.());
                this.listening = true;
                this.setUpMediaSession();
            }
        }
        await this.ctx.resume();
    }

    /**
     * The system's "now playing" controls (iOS shows them once a page plays audio): Stackapella by
     * name, and a pause that really stops (as a call would: the screen keeps what was sung).
     */
    setUpMediaSession() {
        const session = navigator.mediaSession;
        if (!session) return;
        try {
            session.metadata = new MediaMetadata({ title: 'Stackapella', artwork: [{ src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' }] });
            const pause = () => {
                if (!this.playing) return;
                if (this.onInterrupt) this.onInterrupt('audio');
                else this.stop();
            };
            session.setActionHandler('pause', pause);
            session.setActionHandler('stop', pause);
            for (const action of ['play', 'seekbackward', 'seekforward', 'previoustrack', 'nexttrack']) session.setActionHandler(action, null);
            session.playbackState = 'none';
        } catch (error) {
            console.warn('The media session could not be set up', error);
        }
    }

    nowPlaying(state) {
        if (navigator.mediaSession) navigator.mediaSession.playbackState = state;
    }

    /**
     * Makes the context and its fixed graph (the play bus, and the recorder fed by the input bus),
     * then swaps it in. Nothing changes if it fails (an unsupported rate, say).
     */
    async build(sampleRate = null) {
        const ctx = new AudioContext(sampleRate ? { latencyHint: 'interactive', sampleRate } : { latencyHint: 'interactive' });
        try {
            await ctx.audioWorklet.addModule(workletUrl);
        } catch (error) {
            ctx.close().catch(() => {});
            throw error;
        }
        ctx.onstatechange = () => ctx === this.ctx && this.onStateChange();
        const playBus = ctx.createGain();
        playBus.connect(ctx.destination);
        const voiceBus = ctx.createGain();
        voiceBus.connect(playBus);
        const inputBus = ctx.createGain();
        const recorder = new AudioWorkletNode(ctx, 'recorder', {
            numberOfInputs: 1,
            numberOfOutputs: 1,
            outputChannelCount: [1],
        });
        recorder.port.onmessage = (event) => this.onRecorderMessage(event.data);
        inputBus.connect(recorder);
        // Some browsers only run a worklet that leads to the destination: give it a silent path.
        const sink = ctx.createGain();
        sink.gain.value = 0;
        recorder.connect(sink).connect(ctx.destination);
        const sr = ctx.sampleRate;
        Object.assign(this, { ctx, playBus, voiceBus, inputBus, recorder });
        this.clicks = {
            accent: this.toBuffer(click(sr, { hz: 2500 }), sr),
            normal: this.toBuffer(click(sr), sr),
        };
    }

    /**
     * A new context at `sampleRate`, keeping every part (its audio, level, pan and offset), the
     * open mic and loopback. On iOS the hardware rate can switch when the mic opens, and a context
     * left at the old rate then plays and records wrongly; Firefox refuses a mic at another rate
     * than the context. Saved timings are per sample rate, so the timing check is offered again.
     * If the new context can't be made, the old one stays as it was.
     */
    async rebuild(sampleRate) {
        this.projectId = null; // callers that cache what they loaded (guides at a rate) reload
        if (this.playing) {
            await this.stop();
        }
        this.cancelCapture();
        const old = this.ctx;
        const oldMicSource = this.mic?.source;
        const parts = [...this.parts.entries()];
        await this.build(sampleRate);
        this.parts = new Map();
        for (const [id, part] of parts) {
            this.addPart(id, part.buffer, part.mix, part.offsetSeconds);
        }
        if (this.room) this.setRoom(this.room.channels, this.room.wet);
        // The old context goes first: some WebKit versions give silence to a second source on a stream.
        oldMicSource?.disconnect();
        await old.close().catch(() => {});
        if (this.mic) {
            this.attachMic(this.mic);
            this.connectInput();
        }
        await this.ctx.resume();
    }

    /** The context stopped (a call, the screen locked, the app in the background): stop recording. */
    onStateChange() {
        if (this.ctx.state === 'running') {
            return;
        }
        // A capture waiting on the recorder would wait forever: keep what has arrived.
        this.keepCapture({ interrupted: true });
        if (this.playing) {
            this.onInterrupt?.('audio');
        }
    }

    info() {
        const ctx = this.ctx;
        return {
            sampleRate: ctx?.sampleRate ?? 0,
            baseLatency: typeof ctx?.baseLatency === 'number' ? ctx.baseLatency : null,
            outputLatency: typeof ctx?.outputLatency === 'number' ? ctx.outputLatency : null,
            inputLatency: typeof this.mic?.settings?.latency === 'number' ? this.mic.settings.latency : null,
            state: ctx?.state ?? 'none',
        };
    }

    /** What the browser believes the round trip is. Often wrong (by 38 ms wired in the spike). */
    estimatedLatencyFrames() {
        const { baseLatency, outputLatency, inputLatency, sampleRate } = this.info();
        return Math.round(((baseLatency ?? 0) + (outputLatency ?? 0) + (inputLatency ?? 0)) * sampleRate);
    }

    // ---- Mic ----------------------------------------------------------------------------------

    /** The mics on offer. Labels are only filled in once mic permission is granted. */
    async listMics() {
        const devices = await navigator.mediaDevices.enumerateDevices();
        return devices
            .filter((device) => device.kind === 'audioinput')
            .map((device) => ({ deviceId: device.deviceId, groupId: device.groupId, label: device.label || 'Microphone' }));
    }

    /**
     * Opens `deviceId` (or the default), closing any other mic first. One open at a time: a second
     * call waits for the first. A closeMic() while the browser is still asking closes the mic as
     * soon as it arrives, and the open fails with an AbortError.
     */
    async openMic(deviceId = '') {
        while (this.micPending) {
            await this.micPending.catch(() => {});
        }
        if (this.mic && (!deviceId || deviceId === this.mic.deviceId)) {
            return this.mic;
        }
        this.micPending = this.startMic(deviceId);
        try {
            return await this.micPending;
        } finally {
            this.micPending = null;
        }
    }

    async startMic(deviceId) {
        this.closeMic();
        const generation = this.micGeneration;
        const stream = await this.askForMic(deviceId);
        const stopAll = () => stream.getTracks().forEach((track) => track.stop());
        if (generation !== this.micGeneration) {
            stopAll(); // closed while the browser was asking
            const closed = new DOMException('The mic was closed while opening', 'AbortError');
            closed.closedWhileOpening = true;
            throw closed;
        }
        const track = stream.getAudioTracks()[0];
        const settings = track.getSettings();
        const mic = { stream, source: null, analyser: null, label: track.label, deviceId: settings.deviceId, settings };
        try {
            this.attachMic(mic);
        } catch (error) {
            if (error?.name !== 'NotSupportedError') {
                stopAll();
                throw error;
            }
            // Firefox: the mic runs at another rate than the context. Try the usual rates.
            await this.attachAtAnotherRate(mic, settings.sampleRate).catch((failed) => {
                stopAll();
                throw failed;
            });
        }
        this.mic = mic;
        // The mic unplugged, AirPods put away, or another app taking the mic. ('mute' isn't used:
        // iOS mutes briefly while it switches routes.)
        track.addEventListener('ended', () => {
            if (this.mic?.stream === stream && this.playing?.recording) {
                this.onInterrupt?.('mic');
            }
        });
        this.connectInput();
        return mic;
    }

    /**
     * getUserMedia, with what to try when it fails: the remembered mic may be gone (the default
     * instead), and some browsers refuse our raw-audio settings or fail to start capture while
     * their audio session is set up for playing (iOS WebKit): then plain `audio: true`, and on iOS
     * once more with the session left to the browser. A refusal (NotAllowedError) or no mic at all
     * isn't retried. Each try is kept in `micAttempts` for the mic help's Details.
     */
    async askForMic(deviceId) {
        const raw = { echoCancellation: false, noiseSuppression: false, autoGainControl: false, channelCount: 1 };
        const tries = [
            ...(deviceId ? [{ label: 'chosen', audio: { ...raw, deviceId: { exact: deviceId } } }] : []),
            { label: 'default', audio: raw },
            { label: 'plain', audio: true },
            ...(navigator.audioSession ? [{ label: 'session', audio: true, session: 'auto' }] : []),
        ];
        this.micAttempts = [];
        let last = null;
        for (const attempt of tries) {
            if (last && FINAL_MIC_ERRORS.has(last.name)) break;
            const before = navigator.audioSession?.type;
            try {
                if (attempt.session) navigator.audioSession.type = attempt.session;
                const stream = await navigator.mediaDevices.getUserMedia({ audio: attempt.audio });
                this.micAttempts.push({ try: attempt.label, ok: true });
                return stream;
            } catch (error) {
                last = error;
                this.micAttempts.push({ try: attempt.label, error: error?.name ?? String(error), message: error?.message ?? '' });
            } finally {
                if (attempt.session && before) {
                    try {
                        navigator.audioSession.type = before;
                    } catch (error) {
                        console.warn('audioSession.type could not be put back', error);
                    }
                }
            }
        }
        throw last;
    }

    attachMic(mic) {
        mic.source = this.ctx.createMediaStreamSource(mic.stream);
        mic.analyser = this.ctx.createAnalyser();
        // About 43 ms at any rate (2048 at 48 kHz, 4096 at 96 kHz): long enough for a low bass note's pitch.
        mic.analyser.fftSize = 2048 * 2 ** Math.max(0, Math.round(Math.log2(this.ctx.sampleRate / 48000)));
        // The meter and live pitch hear what the recorder hears: the mic, or what plays in loopback.
        this.inputBus.connect(mic.analyser);
    }

    async attachAtAnotherRate(mic, reported) {
        for (const rate of [reported, 48000, 44100].filter((r) => r && r !== this.ctx.sampleRate)) {
            try {
                await this.rebuild(rate);
                this.attachMic(mic);
                return;
            } catch {
                // Not this rate: try the next.
            }
        }
        throw new DOMException("The mic runs at a sample rate the browser can't mix", 'NotSupportedError');
    }

    /** The open mic's own sample rate, when the browser says (Safari and Chrome do). */
    micSampleRate() {
        return this.mic?.settings?.sampleRate ?? null;
    }

    closeMic() {
        this.micGeneration = (this.micGeneration ?? 0) + 1;
        if (!this.mic) {
            return;
        }
        this.mic.stream.getTracks().forEach((track) => track.stop());
        this.mic.source.disconnect();
        try {
            this.inputBus.disconnect(this.mic.analyser);
        } catch {
            // Not connected (a rebuilt context): nothing to undo.
        }
        this.mic = null;
    }

    /** The mic's current peak level, 0 to 1, for a live meter. */
    inputLevel() {
        if (!this.mic) {
            return 0;
        }
        const data = new Float32Array(this.mic.analyser.fftSize);
        this.mic.analyser.getFloatTimeDomainData(data);
        let peak = 0;
        for (const v of data) {
            peak = Math.max(peak, Math.abs(v));
        }
        return peak;
    }

    /** The latest samples from the mic (for pitch detection), or null with no mic open. */
    inputFrame() {
        if (!this.mic) {
            return null;
        }
        const data = new Float32Array(this.mic.analyser.fftSize);
        this.mic.analyser.getFloatTimeDomainData(data);
        return data;
    }

    /** Loopback feeds what we play straight into the recorder: the round trip is exactly zero. */
    setLoopback(enabled) {
        this.loopback = enabled;
        this.connectInput();
    }

    connectInput() {
        try {
            this.playBus.disconnect(this.inputBus);
        } catch {
            // Not connected: nothing to undo.
        }
        try {
            this.mic?.source.disconnect(this.inputBus);
        } catch {
            // Not connected: nothing to undo.
        }
        if (this.loopback) {
            this.playBus.connect(this.inputBus);
        } else if (this.mic) {
            this.mic.source.connect(this.inputBus);
        }
    }

    // ---- Parts --------------------------------------------------------------------------------

    toBuffer(samples, sampleRate) {
        const buffer = new AudioBuffer({ length: Math.max(samples.length, 1), numberOfChannels: 1, sampleRate });
        buffer.copyToChannel(samples, 0);
        return buffer;
    }

    setPart(id, samples, sampleRate, mix) {
        this.addPart(id, this.toBuffer(samples, sampleRate), mix, 0);
    }

    /**
     * A new recording or render for a part, keeping its gain and pan (and so whatever is playing
     * through them now): sources scheduled from here on use it, those already scheduled keep theirs.
     * The tempo ladder's next speed, ready for the loop's next pass.
     */
    replaceBuffer(id, samples, sampleRate) {
        const part = this.parts.get(id);
        if (part) part.buffer = this.toBuffer(samples, sampleRate);
        return !!part;
    }

    addPart(id, buffer, mix, offsetSeconds) {
        this.removePart(id);
        const gain = this.ctx.createGain();
        const pan = this.ctx.createStereoPanner();
        gain.connect(pan).connect(this.voiceBus);
        this.parts.set(id, { buffer, offsetSeconds, gain, pan, mix: null });
        this.updatePart(id, { offsetSeconds, ...mix });
    }

    /**
     * A room under the parts (Hear it polished): `channels` an impulse response (one per side), at
     * `wet` under the dry parts; null takes it away. Clicks and the timing check never go through it.
     */
    setRoom(channels, wet = 0) {
        const [oldSend, oldConvolver] = this.room?.nodes ?? [];
        if (oldSend?.context === this.ctx) this.voiceBus.disconnect(oldSend); // after a rebuild, the old room went with its context
        oldConvolver?.disconnect();
        this.room = null;
        if (!channels) return;
        const buffer = new AudioBuffer({ length: channels[0].length, numberOfChannels: channels.length, sampleRate: this.ctx.sampleRate });
        channels.forEach((c, k) => buffer.copyToChannel(c, k));
        const send = this.ctx.createGain();
        send.gain.value = wet;
        const convolver = this.ctx.createConvolver();
        convolver.normalize = false;
        convolver.buffer = buffer;
        this.voiceBus.connect(send).connect(convolver).connect(this.playBus);
        this.room = { channels, wet, nodes: [send, convolver] };
    }

    /** `audible` is decided by the caller (mute and solo); changes apply at once, even mid-play. */
    updatePart(id, { volume, pan, audible, offsetSeconds }) {
        const part = this.parts.get(id);
        if (!part) {
            return;
        }
        const now = this.ctx.currentTime;
        part.gain.gain.setTargetAtTime(audible ? volume : 0, now, 0.01);
        part.pan.pan.setTargetAtTime(pan, now, 0.01);
        part.offsetSeconds = offsetSeconds;
        part.mix = { volume, pan, audible };
    }

    removePart(id) {
        const part = this.parts.get(id);
        if (part) {
            part.pan.disconnect();
            this.parts.delete(id);
        }
    }

    // ---- Transport ----------------------------------------------------------------------------

    /**
     * Plays the project as a stream of segments on the audio clock, after `countInBeats` clicks:
     *
     * - plain: from `fromSeconds` to the end (and on, while recording), or to `toSeconds`;
     * - `loop: { startSeconds, endSeconds }`: that stretch, end to end, until stop();
     * - `repeat: { phrases: [{ startSeconds, endSeconds }], mute: [voice ids], cueBeats, cycle }`:
     *   each phrase, then a cue click, then the same phrase again with `mute` silent (the singer's
     *   turn). With `cycle` it goes round the phrases until stop().
     *
     * With `record`, it also records from the frame `fromSeconds` plays on plus `latencyFrames`,
     * until stop(); `onChunk` receives the recording as it arrives (for a live waveform). Beats count
     * from beat 0, with `pickupBeats` before bar 1, so accents fall on the bar lines.
     * `countInSounds` (`{ accent, normal }` AudioBuffers) replaces the count-in's two clicks (game mode's
     * chip blips); the metronome and cue clicks after it are always the plain ones.
     *
     * `leadSeconds` is how far ahead of the audio clock the first sound is placed. After the mic
     * has just opened, a longer lead lets iOS finish switching its audio session before the first
     * click, instead of on it.
     */
    play({ fromSeconds = 0, toSeconds = null, bpm = null, beatsPerBar = 4, pickupBeats = 0, countInBeats = 0, countInSounds = null, metronome = false, loop = null, repeat = null, record = false, latencyFrames = 0, onChunk = null, leadSeconds = LEAD_SECONDS, startFrame = null }) {
        // `startFrame` (a context frame ahead of now, such as passEnd()): this run begins there,
        // seamlessly after what is playing, which carries on until then (the tempo ladder's next
        // speed at a loop's end). Only what was scheduled from that frame on is cancelled.
        if (startFrame === null) this.stopSources();
        else this.stopSourcesFrom(startFrame);
        this.nowPlaying('playing');
        const sr = this.ctx.sampleRate;
        const first = startFrame ?? Math.ceil((this.ctx.currentTime + leadSeconds) * sr);
        const beat = bpm ? Math.round((sr * 60) / bpm) : 0;
        const start = first + (beat ? countInBeats * beat : 0);
        const grid = { beatFrames: bpm ? (sr * 60) / bpm : 0, beatsPerBar, pickupBeats };
        if (beat) {
            const startSeconds = loop?.startSeconds ?? repeat?.phrases[0]?.startSeconds ?? fromSeconds;
            const fromBeat = Math.round((startSeconds * sr) / grid.beatFrames);
            const sounds = countInSounds ?? this.clicks; // game mode's chip blips, for the count-in only
            for (let i = 0; i < countInBeats; i++) {
                this.scheduleBuffer(this.isBarLine(fromBeat - countInBeats + i, grid) ? sounds.accent : sounds.normal, first + i * beat, 1, this.playBus, first);
            }
        }
        this.timeline = {
            segments: this.segments({ fromSeconds, toSeconds, loop, repeat, cueFrames: beat * (repeat?.cueBeats ?? 1), start, sr }),
            pending: null,
            scheduled: [],
            grid,
            metronome: metronome && beat > 0,
            open: null,
            finished: false,
        };
        this.playing = { startTime: start / sr, fromSeconds, countInStart: first / sr, recording: record };
        this.scheduleAhead();
        if (record) {
            this.startCapture(start + Math.round(latencyFrames), Infinity, onChunk);
            this.playing.latencyFrames = Math.round(latencyFrames);
        }
        return this.playing;
    }

    /** The segments to play, in project frames (`from`, `to`) placed at context frames (`atFrame`). */
    *segments({ fromSeconds, toSeconds = null, loop, repeat, cueFrames, start, sr }) {
        const frames = (seconds) => Math.round(seconds * sr);
        let at = start;
        if (loop) {
            const from = frames(loop.startSeconds);
            const to = frames(loop.endSeconds);
            for (;;) {
                yield { from, to, atFrame: at, phase: 'loop', mute: null, cueFrames: 0 };
                at += to - from;
            }
        }
        if (repeat) {
            do {
                for (const phrase of repeat.phrases) {
                    const from = frames(phrase.startSeconds);
                    const to = frames(phrase.endSeconds);
                    yield { from, to, atFrame: at, phase: 'listen', mute: null, cueFrames: 0 };
                    at += to - from + cueFrames;
                    yield { from, to, atFrame: at, phase: 'turn', mute: new Set(repeat.mute ?? []), cueFrames };
                    at += to - from;
                }
            } while (repeat.cycle);
            return;
        }
        yield { from: frames(fromSeconds), to: toSeconds === null ? Infinity : frames(toSeconds), atFrame: at, phase: 'play', mute: null, cueFrames: 0 };
    }

    isBarLine(beatIndex, grid) {
        const n = grid.beatsPerBar;
        return (((beatIndex - grid.pickupBeats) % n) + n) % n === 0;
    }

    /**
     * Queues segments (and clicks) a few seconds ahead of the audio clock. Called from play() and
     * from the page's animation frame (positionSeconds), so everything is scheduled on the audio
     * clock; the frame callback only decides when to queue more.
     */
    scheduleAhead() {
        const t = this.timeline;
        if (!t || !this.playing) {
            return;
        }
        const horizon = Math.ceil((this.ctx.currentTime + 3) * this.ctx.sampleRate);
        while (!t.finished) {
            const next = t.pending ?? t.segments.next();
            if (next.done) {
                t.finished = true;
                break;
            }
            if (next.value.atFrame - next.value.cueFrames > horizon) {
                t.pending = next;
                break;
            }
            t.pending = null;
            this.scheduleSegment(next.value);
            t.scheduled.push(next.value);
            if (t.scheduled.length > 8) t.scheduled.shift();
        }
        // An open-ended segment's clicks are queued as it goes.
        if (t.open) {
            const { seg, grid } = t.open;
            for (;;) {
                const frame = seg.atFrame + Math.round(t.open.next * grid.beatFrames) - seg.from;
                if (frame > horizon) break;
                this.click(t.open.next, frame, grid, seg.atFrame);
                t.open.next++;
            }
        }
    }

    scheduleSegment(seg) {
        const t = this.timeline;
        const sr = this.ctx.sampleRate;
        const finite = Number.isFinite(seg.to);
        this.parts.forEach((part, id) => {
            if (seg.mute?.has(id)) return;
            const partFrom = Math.round(part.offsetSeconds * sr);
            const partTo = partFrom + Math.round(part.buffer.duration * sr);
            const from = Math.max(seg.from, partFrom);
            const to = Math.min(seg.to, partTo);
            if (to <= from) return;
            const source = this.ctx.createBufferSource();
            source.buffer = part.buffer;
            source.connect(part.gain);
            const when = (seg.atFrame + from - seg.from) / sr;
            if (finite) {
                source.start(when, (from - partFrom) / sr, (to - from) / sr);
            } else {
                source.start(when, (from - partFrom) / sr);
            }
            this.scheduled.push(source);
            this.startFrames.set(source, seg.atFrame + from - seg.from);
        });
        if (seg.cueFrames) {
            // The cue before your turn: one accented click, so you know when to come in.
            this.scheduleBuffer(this.clicks.accent, seg.atFrame - seg.cueFrames, 1, this.playBus, 0);
        }
        if (t.metronome) {
            const grid = t.grid;
            const firstBeat = Math.ceil(seg.from / grid.beatFrames - 1e-9);
            if (finite) {
                for (let k = firstBeat; Math.round(k * grid.beatFrames) < seg.to; k++) {
                    this.click(k, seg.atFrame + Math.round(k * grid.beatFrames) - seg.from, grid, seg.atFrame);
                }
            } else {
                t.open = { seg, grid, next: firstBeat };
            }
        }
    }

    click(beatIndex, frame, grid, earliest) {
        this.scheduleBuffer(this.isBarLine(beatIndex, grid) ? this.clicks.accent : this.clicks.normal, frame, 0.6, this.playBus, earliest);
    }

    /**
     * What is being heard now: the project position, and the phase (count-in, play, loop, listen,
     * cue, turn). `done` once a finite run (listen, then repeat) has played out. `heard` is the
     * context time heard (clock.js), less the output delay; a test passes a rendered frame's time.
     */
    positionInfo(heard = heardTime(this.ctx)) {
        if (!this.playing) {
            return null;
        }
        this.scheduleAhead();
        const sr = this.ctx.sampleRate;
        const frame = heard * sr;
        const t = this.timeline;
        for (let i = t.scheduled.length - 1; i >= 0; i--) {
            const seg = t.scheduled[i];
            if (frame < seg.atFrame - seg.cueFrames) continue;
            if (frame < seg.atFrame) return { position: seg.from / sr, phase: 'cue' };
            const end = seg.atFrame + seg.to - seg.from;
            if (frame < end) return { position: (seg.from + frame - seg.atFrame) / sr, phase: seg.phase };
            const last = t.finished && !t.pending && i === t.scheduled.length - 1;
            return { position: seg.to / sr, phase: seg.phase, done: last };
        }
        return { position: this.playing.fromSeconds + (heard - this.playing.startTime), phase: 'count-in' };
    }

    /** The project position being heard now (negative during the count-in). */
    positionSeconds() {
        return this.positionInfo()?.position ?? null;
    }

    /** Plays `samples` at once, over whatever is playing: the starting note. */
    preview(samples, sampleRate) {
        const source = this.ctx.createBufferSource();
        source.buffer = this.toBuffer(samples, sampleRate);
        source.connect(this.playBus);
        source.start(this.ctx.currentTime + 0.03);
        this.scheduled.push(source);
    }

    /**
     * Stops playback. When recording, resolves with the take (null otherwise). If the context has
     * stopped running (an interruption), the recorder can't finish its window, so the take is what
     * arrived before the interruption, marked `interrupted`.
     */
    async stop() {
        const playing = this.playing;
        this.playing = null;
        this.nowPlaying('none');
        this.timeline = null;
        if (playing?.kept) {
            // Already ended by an interruption (onStateChange).
            this.stopSources();
            return playing.kept;
        }
        if (playing?.recording && this.capture) {
            if (this.ctx.state !== 'running') {
                const take = this.keepCapture({ interrupted: true });
                this.stopSources();
                return take;
            }
            // The take covers everything heard until now: it ends a round trip after this frame.
            const atFrame = Math.ceil(this.ctx.currentTime * this.ctx.sampleRate) + playing.latencyFrames;
            this.recorder.port.postMessage({ cmd: 'end', id: this.capture.id, atFrame });
            const take = await this.capture.promise;
            this.stopSources();
            return take;
        }
        this.stopSources();
        return null;
    }

    /** Stops only the sources scheduled to start at `frame` or later; the rest play on. */
    stopSourcesFrom(frame) {
        this.scheduled = this.scheduled.filter((source) => {
            const at = this.startFrames.get(source);
            if (at === undefined || at < frame) return true;
            try {
                source.stop();
            } catch (error) {
                console.debug('A source was already stopped', error?.message ?? error);
            }
            return false;
        });
    }

    /**
     * The context frame where the stretch heard now ends: a loop's pass, a listen or a turn (null
     * when nothing finite plays). The tempo ladder starts the next speed there (play's startFrame).
     */
    passEnd(heard = heardTime(this.ctx)) {
        const t = this.timeline;
        if (!t || !this.playing) return null;
        const frame = heard * this.ctx.sampleRate;
        for (const seg of t.scheduled) {
            const end = seg.atFrame + seg.to - seg.from;
            if (frame >= seg.atFrame - seg.cueFrames && frame < end) return Number.isFinite(end) ? end : null;
        }
        return null;
    }

    stopSources() {
        this.scheduled.forEach((source) => {
            try {
                source.stop();
            } catch {
                // Already finished.
            }
        });
        this.scheduled = [];
    }

    /** Plays `buffer` so its first sample leaves at `frame`; a frame before `earliest` skips in. */
    scheduleBuffer(buffer, frame, gain, destination, earliest) {
        const source = this.ctx.createBufferSource();
        source.buffer = buffer;
        let target = destination;
        if (gain !== 1) {
            target = this.ctx.createGain();
            target.gain.value = gain;
            target.connect(destination);
        }
        source.connect(target);
        const sr = this.ctx.sampleRate;
        if (frame >= earliest) {
            source.start(frame / sr);
        } else {
            const skip = (earliest - frame) / sr;
            if (skip >= buffer.duration) {
                return;
            }
            source.start(earliest / sr, skip);
        }
        this.scheduled.push(source);
        this.startFrames.set(source, Math.max(frame, earliest));
    }

    // ---- Capture ------------------------------------------------------------------------------

    startCapture(fromFrame, toFrame, onChunk) {
        this.capture?.reject(new Error('Replaced by a new recording'));
        const id = ++this.captureId;
        const chunks = [];
        let resolve;
        let reject;
        const promise = new Promise((res, rej) => {
            resolve = res;
            reject = rej;
        });
        promise.catch(() => {});
        this.capture = { id, chunks, onChunk, resolve, reject, promise };
        this.recorder.port.postMessage({ cmd: 'start', id, fromFrame, toFrame: Number.isFinite(toFrame) ? toFrame : null });
        return promise;
    }

    onRecorderMessage(message) {
        const capture = this.capture;
        if (!capture || message.id !== capture.id) {
            return;
        }
        if (message.type === 'chunk') {
            capture.chunks.push(message.data);
            capture.onChunk?.(message.data);
        } else if (message.type === 'ended') {
            this.capture = null;
            capture.resolve({ samples: joinChunks(capture.chunks), sampleRate: this.ctx.sampleRate, emptyQuanta: message.emptyQuanta, lateStart: message.lateStart, interrupted: false });
        }
    }

    /** Ends the current capture with what has arrived so far (the recorder may never answer). */
    keepCapture({ interrupted }) {
        const capture = this.capture;
        if (!capture) {
            return null;
        }
        this.capture = null;
        this.recorder.port.postMessage({ cmd: 'cancel' });
        const take = { samples: joinChunks(capture.chunks), sampleRate: this.ctx.sampleRate, emptyQuanta: 0, lateStart: false, interrupted };
        capture.resolve(take);
        if (this.playing?.recording) {
            this.playing.kept = take;
        }
        return take;
    }

    cancelCapture() {
        if (this.capture) {
            this.recorder.port.postMessage({ cmd: 'cancel' });
            this.capture.reject(new Error('Cancelled'));
            this.capture = null;
        }
    }

    /** Plays `signal` and records `captureLength` frames from `latencyFrames - preFrames` after it. */
    async measure(signal, { captureLength, latencyFrames = 0, preFrames = 0 }) {
        this.stopSources();
        if (this.ctx.state !== 'running') {
            await this.ctx.resume();
        }
        const sr = this.ctx.sampleRate;
        const start = Math.ceil((this.ctx.currentTime + 0.3) * sr);
        this.scheduleBuffer(this.toBuffer(signal, sr), start, 1, this.playBus, start);
        const from = start + Math.round(latencyFrames) - preFrames;
        const result = await this.startCapture(from, from + captureLength, null);
        if (result.interrupted) {
            throw new DOMException('The measurement was interrupted', 'AbortError');
        }
        return result.samples;
    }

    // ---- Export -------------------------------------------------------------------------------

    /** Renders the audible parts to stereo, as heard, for export. */
    async render(parts, lengthSeconds, sampleRate = this.sampleRate) {
        const frames = Math.max(1, Math.ceil(lengthSeconds * sampleRate));
        const offline = new OfflineAudioContext(2, frames, sampleRate);
        for (const mix of parts) {
            // A part of its own for this render (Export's piano chords): `samples` at `sampleRate`.
            const part = mix.samples ? { buffer: monoBuffer(mix.samples, mix.sampleRate), offsetSeconds: mix.offsetSeconds ?? 0 } : this.parts.get(mix.id);
            if (!part || !mix.audible) {
                continue;
            }
            const source = offline.createBufferSource();
            source.buffer = part.buffer;
            const gain = offline.createGain();
            gain.gain.value = mix.volume;
            const pan = offline.createStereoPanner();
            pan.pan.value = mix.pan;
            source.connect(gain).connect(pan).connect(offline.destination);
            if (part.offsetSeconds >= 0) {
                source.start(part.offsetSeconds);
            } else if (-part.offsetSeconds < part.buffer.duration) {
                source.start(0, -part.offsetSeconds);
            }
        }
        const rendered = await offline.startRendering();
        return { channels: [rendered.getChannelData(0), rendered.getChannelData(1)], sampleRate };
    }
}

function monoBuffer(samples, sampleRate) {
    const buffer = new AudioBuffer({ length: Math.max(1, samples.length), numberOfChannels: 1, sampleRate });
    buffer.copyToChannel(samples, 0);
    return buffer;
}

function joinChunks(chunks) {
    const samples = new Float32Array(chunks.reduce((sum, chunk) => sum + chunk.length, 0));
    let at = 0;
    for (const chunk of chunks) {
        samples.set(chunk, at);
        at += chunk.length;
    }
    return samples;
}
