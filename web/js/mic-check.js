// The mic check (Hillel): the first time someone gets started, and the first time anything needs
// the mic, confirm we have it and that it's the right one. The mic's name with Change, a live level
// with the "voice detected" line, "Say something or hum", then "Got you, your mic works". Flat for a
// while: the picker, right there. Remembered per mic, and asked again when the mics change (a headset
// plugged in). Here: the level maths and the memory (pure, tested in tests/mic-check.test.js), and
// the shared sheet's state (components/mic-check.js draws it).

import { reactive } from 'vue';
import { audio, engine, micChoices, openMic } from './audio.js';
import { decibels } from './pitchtrack.js';
import { local } from './storage.js';

// The level maths are shared with the public mic test (js/mic-level.js).
export { VOICE_DBFS, HEARD_SECONDS, FLAT_SECONDS, SIGNAL_DBFS, meterLevel, VOICE_LINE, voiceWatch, stepVoice, checkState, looksLikeInterface } from './mic-level.js';

/** The mic's level now (RMS dBFS), or null with no mic open. */
export function micDb() {
    const frame = engine.inputFrame();
    return frame ? decibels(frame) : null;
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
