// The mic check (Hillel): the first time someone gets started, and the first time anything needs
// the mic, confirm we have it and that it's the right one. The mic's name with Change, a live level
// with the "voice detected" line, "Say something or hum", then "Got you, your mic works". Flat for a
// while: the picker, right there. Remembered per mic, and asked again when the mics change (a headset
// plugged in). Here: the level maths and the memory (pure, tested in tests/mic-check.test.js), and
// the shared sheet's state (components/mic-check.js draws it).

import { reactive } from 'vue';
import { audio, engine, micChoices, openMic } from './audio.js';
import { QUIET_DBFS } from './listen.js';
import { decibels } from './pitchtrack.js';
import { local } from './storage.js';

/** The voice line: louder than this is a voice (the same line listen.js counts a note from). */
export const VOICE_DBFS = QUIET_DBFS;
const FLOOR_DBFS = -72; // the meter's empty end: a quiet room
const CEIL_DBFS = -12; // its full end: singing up, close
const RELEASE_SECONDS = 0.25; // the meter stays lit this long after the voice dips under the line
export const HEARD_SECONDS = 0.4; // this much voice in all: the mic works
export const FLAT_SECONDS = 5; // this long with nothing over the line: suggest another mic
export const SIGNAL_DBFS = -66; // over this but under the voice line: the mic hears something, quietly (an interface at low gain)

/** Where `db` (RMS dBFS) fills the meter, 0 to 1. */
export function meterLevel(db) {
    if (db === null || db === undefined || !Number.isFinite(db)) return 0;
    return Math.max(0, Math.min(1, (db - FLOOR_DBFS) / (CEIL_DBFS - FLOOR_DBFS)));
}

/** The voice line's place on the meter, 0 to 1. */
export const VOICE_LINE = meterLevel(VOICE_DBFS);

/** The mic's level now (RMS dBFS), or null with no mic open. */
export function micDb() {
    const frame = engine.inputFrame();
    return frame ? decibels(frame) : null;
}

/** A fresh voice watch: nothing heard yet. */
export function voiceWatch() {
    return { hearing: false, since: Infinity, voiced: 0, quiet: 0, total: 0 };
}

/**
 * The watch after a reading of `db`, `dt` seconds on: `hearing` (over the line, held a moment so it
 * doesn't flicker), `voiced` (seconds over the line in all) and `total` (seconds watched). Silence
 * counts for nothing: only a voice moves it on.
 */
export function stepVoice(state, db, dt) {
    const over = Number.isFinite(db) && db > VOICE_DBFS;
    const quiet = !over && Number.isFinite(db) && db > SIGNAL_DBFS;
    const since = over ? 0 : state.since + dt;
    return { hearing: since <= RELEASE_SECONDS, since, voiced: state.voiced + (over ? dt : 0), quiet: (state.quiet ?? 0) + (quiet ? dt : 0), total: state.total + dt };
}

/**
 * Where a check stands: 'ok' once a voice has been heard; after a while with none, 'quiet' when
 * the mic heard something under the line (turn its gain up), else 'flat' (try another mic).
 */
export function checkState(state) {
    if (state.voiced >= HEARD_SECONDS) return 'ok';
    if (state.total < FLAT_SECONDS || state.voiced > 0) return 'listening';
    return (state.quiet ?? 0) >= HEARD_SECONDS ? 'quiet' : 'flat';
}

/** An audio interface, by the name it gives (Things to check speaks of inputs, gain and 48V). */
export function looksLikeInterface(label) {
    return /scarlett|focusrite|clarett|audient|\bevo\b|\bid\d{1,2}\b|motu|apollo|universal audio|presonus|studio ?\d{2}|behringer|umc\d|u-phoria|steinberg|\bur\d{2}|tascam|\bzoom\b|\bssl ?\d|\brme\b|babyface|fireface|m-audio|komplete audio|arturia|minifuse|apogee|rubix|volt \d|audio interface/i.test(label ?? '');
}

/** The mics as one string, so a change (one plugged in or out) is seen. */
export function micsSignature(mics) {
    return mics.map((m) => m.deviceId || m.label).sort().join('|');
}

/** Whether `deviceId` needs a check, from what was kept (`{ mics, ok: [ids] }`) and the mics now. */
export function needsCheck(kept, deviceId, mics) {
    if (!kept || kept.mics !== micsSignature(mics)) return true;
    return !kept.ok.includes(deviceId);
}

/** What to keep once `deviceId` was heard: the list starts again when the mics changed. */
export function checked(kept, deviceId, mics) {
    const signature = micsSignature(mics);
    const ok = kept?.mics === signature ? kept.ok : [];
    return { mics: signature, ok: ok.includes(deviceId) ? ok : [...ok, deviceId] };
}

const KEY = 'stackapella.micCheck';

function kept() {
    try {
        return JSON.parse(local.get(KEY) ?? 'null');
    } catch (error) {
        console.warn('The mic check could not be read', error);
        return null;
    }
}

const micId = () => audio.mic?.deviceId || audio.mic?.label || '';

/** Whether the open mic needs checking (never heard on this device, or the mics changed since). */
export function micNeedsCheck() {
    return !!audio.mic && needsCheck(kept(), micId(), micChoices());
}

/** Keeps that the open mic was heard. */
export function rememberMic() {
    if (audio.mic) local.set(KEY, JSON.stringify(checked(kept(), micId(), micChoices())));
}

/** The shared sheet: open while a screen waits on a check (`resolve` hears how it went). */
export const micCheck = reactive({ open: false, resolve: null });

/** Shows the mic check sheet; resolves true once a voice was heard, false if it was closed. */
export function showMicCheck() {
    micCheck.resolve?.(false);
    return new Promise((resolve) => Object.assign(micCheck, { open: true, resolve }));
}

/** Closes the sheet, telling the screen that asked whether the mic was heard. */
export function closeMicCheck(ok) {
    const resolve = micCheck.resolve;
    Object.assign(micCheck, { open: false, resolve: null });
    resolve?.(ok);
}

/**
 * Opens the mic for singing, with the check first when this mic hasn't been heard here yet. Resolves
 * the mic, or null (the browser's question or the check was closed). Throws as openMic does.
 */
export async function readyMic() {
    const mic = await openMic();
    if (!mic) return null;
    if (!micNeedsCheck()) return mic;
    return (await showMicCheck()) ? mic : null;
}
