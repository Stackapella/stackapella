// Kept warm-ups (docs/warmup-builder-plan.md, "Add to my warm-ups", slice 4): kept with the course's
// progress (the learning store, on the device), so signed in they follow the account to every device
// with the rest of it (Hillel, 2026-11-01); a guest keeps them on the device. Their link codes and
// names, newest first, at most 20 shown. Removing one keeps a note of it (removedAt), so another
// device's copy can't bring it back. A list kept by an older version (localStorage) is read in once.

import { local } from '../storage.js';
import { mergeWarmups } from '../warmup.js';
import { learning, loadLearning, setWarmups } from './learning.js';

const OLD = 'stackapella.warmups'; // where an older version kept them, on this device
const SHOWN = 20;

function older() {
    try {
        const list = JSON.parse(local.get(OLD) || '[]');
        return Array.isArray(list) ? list.filter((w) => typeof w?.code === 'string') : [];
    } catch (error) {
        console.info('Kept warm-ups could not be read', error?.message ?? error);
        return [];
    }
}

/** Every kept and removed warm-up: the learning store's, with an older version's list. */
const all = () => mergeWarmups(learning.progress?.warmups ?? [], older());

/** The kept warm-ups: [{ code, name, keptAt }], newest first. */
export function keptWarmups() {
    return all().filter((w) => !w.removedAt).slice(0, SHOWN);
}

export function keptWarmup(code) {
    return keptWarmups().find((w) => w.code === code) ?? null;
}

async function keepList(list) {
    await loadLearning();
    await setWarmups(mergeWarmups(list, all()));
    local.remove(OLD); // read in now: the learning store has it
}

/** Keeps a warm-up (again moves it to the top). */
export async function keepWarmup(code, name = '') {
    await keepList([{ code, name: name ?? '', keptAt: Date.now() }]);
}

export async function forgetWarmup(code) {
    const had = all().find((w) => w.code === code);
    if (had) await keepList([{ ...had, removedAt: Date.now() }]);
}

const CHOSEN = 'stackapella.warmups.chosen';

/** The kept warm-up today's workout warms up with (its code), or null for ours. On this device only. */
export function chosenWarmup() {
    const code = local.get(CHOSEN);
    return code && keptWarmup(code) ? code : null;
}

export function chooseWarmup(code) {
    if (code) local.set(CHOSEN, code);
    else local.remove(CHOSEN);
}
