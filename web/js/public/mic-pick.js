// The site's mic picker, for every course-page demo that listens (first Perfect your pitch's Bullseye):
// a quiet "Mic" select beside the mic button, shown once the mic is allowed and there's more than one
// input, the system default first. The site keeps its own choice by device id (this address's
// storage); without one it looks for the mic chosen last on the site or in the app, by name (the
// sa_mic cookie, js/mic-choice.js). Choosing one remembers it both ways, and the page moves to it, mid-
// round too (listen.js use()).

import { pickByLabel, rememberMicLabel, rememberedMicLabel } from '../mic-choice.js';

const KEY = 'stackapella.site-mic';

/** This site's own choice, or '' (storage blocked, or never chosen). */
export function savedMic() {
    try {
        return localStorage.getItem(KEY) || '';
    } catch (error) {
        console.warn('The chosen mic could not be read', error);
        return '';
    }
}

function save(deviceId, label) {
    try {
        localStorage.setItem(KEY, deviceId);
    } catch (error) {
        console.warn('The chosen mic could not be kept on this device', error);
    }
    rememberMicLabel(label);
}

// The inputs with their names (they have names once the mic is allowed), the system default first and
// Chrome's "Communications" copy left out.
async function inputs() {
    const all = (await navigator.mediaDevices?.enumerateDevices?.()) ?? [];
    const mics = all.filter((d) => d.kind === 'audioinput' && d.label && d.deviceId !== 'communications');
    return [...mics.filter((d) => d.deviceId === 'default'), ...mics.filter((d) => d.deviceId !== 'default')];
}

/**
 * After the mic opens on `openId`: the mic this site should use instead, or null. Its own choice was
 * already asked for; without one, the mic named in sa_mic (chosen on the site or in the app).
 */
export async function preferredMic(openId) {
    if (savedMic()) return null;
    const named = pickByLabel(await inputs(), rememberedMicLabel());
    return named && named !== openId ? named : null;
}

/**
 * Fills the picker (`select`, inside a hidden `label`) with the inputs and shows it when there's more
 * than one, `currentId` selected. On a change it remembers the choice and calls `onPick(deviceId)`.
 */
export async function showPicker(select, currentId) {
    const mics = await inputs();
    const holder = select.closest('label') ?? select;
    holder.hidden = mics.length < 2;
    if (mics.length < 2) return;
    select.replaceChildren(...mics.map((m) => new Option(m.label, m.deviceId, false, m.deviceId === currentId)));
    if (!mics.some((m) => m.deviceId === currentId)) select.value = mics[0].deviceId;
}

/** Wires the picker once: a change is remembered and handed to `onPick(deviceId)`. */
export function onPicked(select, onPick) {
    select.addEventListener('change', () => {
        const option = select.selectedOptions[0];
        if (!option) return;
        save(option.value, option.textContent);
        onPick(option.value);
    });
}
