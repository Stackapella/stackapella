// The one audio engine, and the settings that belong to this device: which mic, how it's used
// (speakers or headphones), and the measured timing for that combination.

import { reactive } from 'vue';
import { first } from './funnel.js';
import { Engine } from '../engine/engine.js';
import { analyzeTrain, clickTrain, median } from './dsp.js';
import { tk } from './i18n.js';
import { local } from './storage.js';
import { explainFirst } from './mic-help.js';

export const engine = new Engine();

/** How the singer listens while recording; the timing is measured for each. Translation keys. */
export const ROUTES = {
    speakers: { label: tk('timing.route.speakers'), hint: tk('timing.route.speakersHint') },
    wired: { label: tk('timing.route.wired'), hint: tk('timing.route.wiredHint') },
    bluetooth: { label: tk('timing.route.bluetooth'), hint: tk('timing.route.bluetoothHint') },
};

export const audio = reactive({
    unlocked: false,
    mic: null, // { deviceId, label }
    mics: [],
    route: local.get('stackapella.route') || 'wired',
    latencyFrames: null,
    latencyEstimated: false,
    deviceChanged: false,
    micOpenedAt: null, // the audio-clock time the mic last opened, for the settle before a count-in
});

/** How long after the mic opens before the first click: iOS switches its audio session then. */
export const MIC_SETTLE_SECONDS = 0.8;

const WIRELESS = /airpods|bluetooth|hands-?free|\bbt\b|wireless|beats|galaxy buds|pixel buds/i;
const CONTINUITY = /iphone|ipad/i;

/** An iPhone or iPad (iPadOS says it's a Mac, but has a touch screen). */
export function isAppleMobile() {
    return /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/**
 * True when a mic's label looks wireless: Bluetooth headsets, AirPods, and (on a computer) an
 * iPhone used as a Continuity mic. Their delay can jump while you sing (44 ms in the spike,
 * docs/audio-spike.md). On the iPhone itself, "iPhone Microphone" is its own built-in mic.
 */
export function looksWireless(label, { appleMobile = isAppleMobile() } = {}) {
    const text = label ?? '';
    return WIRELESS.test(text) || (!appleMobile && CONTINUITY.test(text));
}

engine.onDeviceChange = () => {
    audio.deviceChanged = true;
    refreshMics();
};

/** Re-reads the mics (a headset plugged in or out). Labels need mic permission first. */
export async function refreshMics() {
    if (audio.mic) {
        audio.mics = await engine.listMics();
    }
}

// Chrome lists a "Default - <name>" copy of one mic (and "Communications" on Windows).
const ALIASES = new Set(['default', 'communications']);

/** The mics to choose from, each once, with the system default marked. */
export function micChoices() {
    const alias = audio.mics.find((m) => m.deviceId === 'default');
    const defaultLabel = alias?.label.replace(/^Default - /, '');
    const choices = audio.mics
        .filter((m) => !ALIASES.has(m.deviceId))
        .map((m) => ({ ...m, systemDefault: !!alias && (m.groupId === alias.groupId || m.label === defaultLabel) }));
    // A default that isn't a copy of any listed mic is a choice of its own.
    if (alias && !choices.some((m) => m.systemDefault)) {
        choices.unshift({ ...alias, label: defaultLabel, systemDefault: true });
    }
    return choices;
}

/** The chosen mic's id among micChoices(), seeing through Chrome's "default" alias. */
export function currentMicId() {
    if (!audio.mic) {
        return null;
    }
    if (ALIASES.has(audio.mic.deviceId)) {
        const choices = micChoices();
        const same = choices.find((m) => m.deviceId !== audio.mic.deviceId && m.groupId === audio.mic.groupId);
        return (same ?? choices.find((m) => m.systemDefault))?.deviceId ?? null;
    }
    return audio.mic.deviceId;
}

/** Call first thing in a click handler: audio can only start from a user gesture. */
export async function unlock() {
    await engine.unlock();
    audio.unlocked = true;
}

/**
 * Opens the remembered mic (or the default), asking for permission the first time. Returns null
 * if closeMic() was called while the browser was still asking (the person left the screen).
 */
export async function openMic(deviceId = local.get('stackapella.mic') || '') {
    await unlock();
    // The first time, a few words on why before the browser asks (not now: as if closed).
    if (!engine.mic && !(await explainFirst())) return null;
    let mic;
    try {
        mic = await engine.openMic(deviceId); // it falls back to the default mic, and simpler asks, itself
    } catch (error) {
        if (error?.closedWhileOpening) {
            return null;
        }
        throw error;
    }
    first('f_mic'); // the funnel: the mic open for the first time on this device
    const fresh = audio.mic?.deviceId !== mic.deviceId || audio.micOpenedAt === null;
    const hadTiming = audio.latencyFrames !== null;
    // iOS can switch its hardware rate when the mic opens: rebuild at the mic's rate. (Firefox's
    // refusal of a mic at another rate is handled inside the engine.) Timings are per rate.
    const micRate = engine.micSampleRate();
    let rebuilt = false;
    if (isAppleMobile() && micRate && micRate !== engine.sampleRate) {
        try {
            await engine.rebuild(micRate);
            rebuilt = true;
        } catch (error) {
            // The mic is open: at the old rate the browser resamples it, which is better than no mic.
            console.warn(`The audio could not move to the mic's rate (${micRate} Hz)`, error);
        }
    }
    audio.mic = { deviceId: mic.deviceId, groupId: mic.settings.groupId, label: mic.label };
    audio.mics = await engine.listMics();
    if (deviceId) {
        local.set('stackapella.mic', mic.deviceId);
    }
    if (fresh) {
        audio.micOpenedAt = engine.ctx.currentTime;
    }
    loadLatency();
    if (rebuilt && hadTiming && audio.latencyFrames === null) {
        audio.deviceChanged = true; // offer the timing check for the new rate
    }
    return mic;
}

/** Closes the mic when leaving a screen that sings, so the browser's mic light goes out. */
export function closeMic() {
    engine.closeMic();
    audio.mic = null;
    audio.micOpenedAt = null;
}

/**
 * How far ahead to place the first click: the usual lead, or enough to finish the mic's settle
 * time when it has only just opened. Measured on the audio clock, never a timer.
 */
export function leadSeconds() {
    const since = audio.micOpenedAt === null ? Infinity : engine.ctx.currentTime - audio.micOpenedAt;
    return Math.max(0.15, MIC_SETTLE_SECONDS - since);
}

export async function chooseMic(deviceId) {
    local.set('stackapella.mic', deviceId);
    await openMic(deviceId);
}

export function chooseRoute(route) {
    audio.route = route;
    local.set('stackapella.route', route);
    loadLatency();
}

/** Whether this mic has a measured timing for `route` (the headphones choice's Ready). */
export function measuredFor(route) {
    return local.get(`stackapella.latency.${audio.mic?.label ?? 'default'}.${route}.${engine.sampleRate}`) !== null;
}

function latencyKey() {
    return `stackapella.latency.${audio.mic?.label ?? 'default'}.${audio.route}.${engine.sampleRate}`;
}

function loadLatency() {
    const saved = Number.parseInt(local.get(latencyKey()) ?? '', 10);
    audio.latencyFrames = Number.isFinite(saved) ? saved : null;
    audio.latencyEstimated = false;
}

/** Records with the browser's own estimate (often tens of ms off) until a timing check is done. */
export function useEstimate() {
    audio.latencyFrames = engine.estimatedLatencyFrames();
    audio.latencyEstimated = true;
}

/**
 * The timing check: plays chirps and finds them in the recording, `runs` times. Saves the median
 * round trip for this mic and route when the runs agree.
 */
export async function checkTiming({ runs = 3, onProgress = () => {} } = {}) {
    if (!(await openMic())) {
        throw new DOMException('The mic was closed', 'AbortError');
    }
    const sr = engine.sampleRate;
    const train = clickTrain(sr);
    const maxLag = sr; // up to a second, for Bluetooth
    const lags = [];
    for (let run = 0; run < runs; run++) {
        onProgress(run / runs);
        const recording = await engine.measure(train.signal, { captureLength: train.signal.length + maxLag });
        const result = analyzeTrain(recording, train, { maxLag });
        if (result.reliable) {
            lags.push(result.lagFrames);
        }
    }
    onProgress(1);
    if (lags.length === 0) {
        return { ok: false };
    }
    const frames = Math.round(median(lags));
    const spreadMs = ((Math.max(...lags) - Math.min(...lags)) * 1000) / sr;
    local.set(latencyKey(), String(frames));
    audio.latencyFrames = frames;
    audio.latencyEstimated = false;
    audio.deviceChanged = false;
    return {
        ok: true,
        ms: (frames * 1000) / sr,
        spreadMs,
        runs: lags.length,
        estimateMs: (engine.estimatedLatencyFrames() * 1000) / sr,
    };
}
