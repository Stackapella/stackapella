// Voice care's day (docs/voice-care-plan.md, slices 2 and 3): the seconds actually sung today on this
// device (voiced, from the pitch tracker: listening and silence don't count), and the two quiet notes
// at 20 and 40 minutes, each said once a day. Kept only here (stackapella.voiceDay), never sent,
// never synced, never in the usage counts, and gone with the browser's data: no tracking. A Settings
// switch turns the notes off; the cool-down stays.

import { reactive } from 'vue';
import { local } from './storage.js';

const KEY = 'stackapella.voiceDay'; // { day, sungSeconds, shown: [minutes] }
export const NOTES_OFF = 'stackapella.voiceNotesOff'; // '1': Settings' Voice-care notes off
export const THRESHOLDS = [20, 40]; // minutes sung today (Hillel, 2026-10-30), a cautious reading of practice guidance
const FLUSH_SECONDS = 5; // kept to the device every few seconds of singing, not every frame

const today = (at = Date.now()) => new Date(at).toDateString(); // local midnight starts a new day

export const voiceCare = reactive({ notesOn: local.get(NOTES_OFF) !== '1' });

let pending = 0;

function read(at = Date.now()) {
    try {
        const kept = JSON.parse(local.get(KEY) || 'null');
        if (kept && kept.day === today(at)) return { day: kept.day, sungSeconds: Number(kept.sungSeconds) || 0, shown: Array.isArray(kept.shown) ? kept.shown : [] };
    } catch (error) {
        console.info('The voice-care day was unreadable', error?.message ?? error);
    }
    return { day: today(at), sungSeconds: 0, shown: [] };
}

function write(day) {
    local.set(KEY, JSON.stringify(day));
}

/** Seconds of voice heard: counted here, kept every few seconds (and at a take's end, flushSung). */
export function addSung(seconds) {
    if (!(seconds > 0)) return;
    pending += seconds;
    if (pending >= FLUSH_SECONDS) flushSung();
}

export function flushSung(at = Date.now()) {
    if (pending <= 0) return;
    const day = read(at);
    day.sungSeconds += pending;
    pending = 0;
    write(day);
}

/** Today's sung seconds (including what's not kept yet). */
export function sungToday(at = Date.now()) {
    return read(at).sungSeconds + pending;
}

/** The note due now: the highest threshold crossed today and not yet said, or null (and null with the notes off). */
export function noteDue(at = Date.now()) {
    if (!voiceCare.notesOn) return null;
    const day = read(at);
    const minutes = (day.sungSeconds + pending) / 60;
    const crossed = THRESHOLDS.filter((m) => minutes >= m && !day.shown.includes(m));
    return crossed.length ? crossed.at(-1) : null;
}

/** The note was shown: never again today (and a 40 said also covers 20). */
export function markShown(minutes, at = Date.now()) {
    flushSung(at);
    const day = read(at);
    day.shown = [...new Set([...day.shown, ...THRESHOLDS.filter((m) => m <= minutes)])];
    write(day);
}

/** Settings' Voice-care notes. */
export function setNotes(on) {
    voiceCare.notesOn = on;
    if (on) local.remove(NOTES_OFF);
    else local.set(NOTES_OFF, '1');
}
