// The one audio engine, and the settings that belong to this device: which mic, how it's used
// (speakers or headphones), and the measured timing for that combination.

import { reactive } from 'vue';
import { first } from './funnel.js';
import { Engine } from '../engine/engine.js';
import { measureClaps, measureLatency } from './timing-check.js';
import { tk } from './i18n.js';
import { local } from './storage.js';
import { countMic, countMicFailure, explainFirst, micPermission } from './mic-help.js';
import { noteSound } from './sound-log.js';
import { pickByLabel, rememberMicLabel, rememberedMicLabel } from './mic-choice.js';

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
    latencyTypical: false, // the estimate is a typical wireless delay (wirelessTypicalMs), not the browser's own
    deviceChanged: false,
    reconnected: false, // a device came or went while listening wirelessly: its timing may have moved (recheckDue)
    micOpenedAt: null, // the audio-clock time the mic last opened, for the settle before a count-in
});

/** How long after the mic opens before the first click: iOS switches its audio session then. */
export const MIC_SETTLE_SECONDS = 0.8;
/** A device change sooner than this after the mic opens is the opening's own, not a reconnect. */
const RECONNECT_AFTER_OPEN = 3;

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
    // A reconnect only with the mic open and settled: granting the mic, and iOS moving AirPods to
    // hands-free as it opens, fire devicechange too (read on the audio clock, never a timer).
    if (wirelessListening() && audio.micOpenedAt !== null && engine.ctx && engine.ctx.currentTime - audio.micOpenedAt > RECONNECT_AFTER_OPEN) audio.reconnected = true;
    refreshMics();
};

/** Listening or singing through something wireless: Bluetooth chosen, or a mic that looks wireless. */
export function wirelessListening() {
    return audio.route === 'bluetooth' || looksWireless(audio.mic?.label);
}

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
    // Counted, anonymous (lesson 1.1's drop-off): the browser about to ask, and how that went.
    const asking = !engine.mic && !['granted', 'denied'].includes(await micPermission());
    if (asking) countMic('mic_prompt');
    let mic;
    try {
        mic = await engine.openMic(deviceId); // it falls back to the default mic, and simpler asks, itself
    } catch (error) {
        if (error?.closedWhileOpening) {
            return null;
        }
        countMicFailure(error);
        noteSound('mic', error); // for Send diagnostics, if the person sends it
        throw error;
    }
    if (asking) countMic('mic_granted');
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
            noteSound(`rate ${engine.sampleRate} to ${micRate} Hz`, error);
        }
    }
    audio.mic = { deviceId: mic.deviceId, groupId: mic.settings.groupId, label: mic.label };
    audio.mics = await engine.listMics();
    // Nothing chosen here yet: the mic chosen on the site (or in the app on another address), by name.
    if (!deviceId) {
        const named = pickByLabel(audio.mics, rememberedMicLabel());
        if (named && named !== mic.deviceId) return openMic(named);
    }
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
    rememberMicLabel(audio.mic?.label); // the site picks the same mic by name (js/mic-choice.js)
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
    audio.latencyTypical = false;
}

/**
 * A fresh timing check, offered (master plan 15b, an r/acappella reader's idea): a wireless timing can
 * move when headphones reconnect, and drift over a long session. Only with a measured timing (an
 * estimate is already offered the check). Returns 'reconnect', 'takes' or null.
 */
export const RECHECK_TAKES = 20;

function takesKey() {
    return latencyKey().replace('stackapella.latency.', 'stackapella.latencyTakes.');
}

function takesSince() {
    return Number.parseInt(local.get(takesKey()) ?? '', 10) || 0;
}

/** A take recorded with this measured timing: counted toward the next offer. */
export function tookTake() {
    if (audio.latencyFrames === null || audio.latencyEstimated || !wirelessListening()) return;
    local.set(takesKey(), String(takesSince() + 1));
}

export function recheckDue() {
    if (audio.latencyFrames === null || audio.latencyEstimated || !wirelessListening()) return null;
    if (audio.reconnected) return 'reconnect';
    return takesSince() >= RECHECK_TAKES ? 'takes' : null;
}

/** Offered once: again only after another RECHECK_TAKES takes or the next reconnect. */
export function recheckShown() {
    audio.reconnected = false;
    local.set(takesKey(), '0');
}

// A typical wireless round trip, before any check (docs/wireless-timing-plan.md, 1; Hillel chose "Default +
// clap-along"): AirPods add 43 to 95 ms over a speaker on Stephen Coyle's rig, on top of a browser's own 55 to 67 ms
// speaker-to-mic round trip, so about 140 on Apple; Android's Bluetooth is slower and its own figure untrustworthy.
export const WIRELESS_TYPICAL_MS = { apple: 140, android: 280, other: 250 };
const MIC_INPUT_MS = 15; // the mic's own share, added to the browser's output figure

/**
 * The wireless round trip to assume (ms): on Apple the browser's outputLatency when it reads like Bluetooth (100 to
 * 400 ms; Chrome includes the device's own delay, Safari 18.4+ AVAudioSession's) plus the mic's input, else the typical
 * value for the platform. Android's figure is never used (its Bluetooth layer can report a fixed 200 ms).
 */
export function wirelessTypicalMs({ ua = navigator.userAgent, outputLatency = engine.ctx?.outputLatency } = {}) {
    const android = /Android/i.test(ua);
    const apple = !android && /iPhone|iPad|iPod|Macintosh|Mac OS X/.test(ua);
    if (apple && Number.isFinite(outputLatency) && outputLatency >= 0.1 && outputLatency <= 0.4) return Math.round(outputLatency * 1000 + MIC_INPUT_MS);
    return android ? WIRELESS_TYPICAL_MS.android : apple ? WIRELESS_TYPICAL_MS.apple : WIRELESS_TYPICAL_MS.other;
}

/** The delay takes are lined up by now: the one measured, else (listening wirelessly) a typical wireless one, else the browser's estimate. */
export function latencyNow() {
    if (audio.latencyFrames !== null) return audio.latencyFrames;
    return wirelessListening() ? Math.round((wirelessTypicalMs() * engine.sampleRate) / 1000) : engine.estimatedLatencyFrames();
}

/** Records with an estimate until a timing check is done: a typical wireless delay when listening wirelessly, else the browser's own (often tens of ms off). */
export function useEstimate() {
    audio.latencyTypical = wirelessListening();
    audio.latencyFrames = audio.latencyTypical ? Math.round((wirelessTypicalMs() * engine.sampleRate) / 1000) : engine.estimatedLatencyFrames();
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
    const found = await measureLatency({ measure: (signal, captureLength) => engine.measure(signal, { captureLength }), sampleRate: sr, runs, onProgress });
    if (!found) {
        return { ok: false };
    }
    keepTiming(found.frames);
    return {
        ok: true,
        ms: found.ms,
        spreadMs: found.spreadMs,
        runs: found.runs,
        estimateMs: (engine.estimatedLatencyFrames() * 1000) / sr,
    };
}

/** A measured round trip, kept for this mic and route. */
function keepTiming(frames) {
    local.set(latencyKey(), String(frames));
    audio.latencyFrames = frames;
    audio.latencyEstimated = false;
    audio.latencyTypical = false;
    audio.deviceChanged = false;
    recheckShown(); // a fresh timing: the count starts again
}

/**
 * Clap along (wireless): the singer claps on steady clicks heard in the earbuds, and the device's own mic hears the
 * claps (js/timing-check.js measureClaps). Saved as the click test's is. `{ ok: false, reason }` when too few claps were
 * heard ('few') or they were too uneven ('uneven').
 */
export async function checkClaps() {
    if (!(await openMic())) {
        throw new DOMException('The mic was closed', 'AbortError');
    }
    const sr = engine.sampleRate;
    const found = await measureClaps({ measure: (signal, captureLength) => engine.measure(signal, { captureLength }), sampleRate: sr });
    if (found.frames === null) return { ok: false, reason: found.reason };
    keepTiming(found.frames);
    return { ok: true, claps: found.claps, ms: found.ms, spreadMs: found.spreadMs, estimateMs: wirelessTypicalMs() };
}
