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
    // A row of its own in a tool's settings sheet (micRow) goes with it, so no empty row waits there before the mic
    // opens; a picker inside a bigger row (the latency test's mic check) leaves that row alone.
    const row = select.closest('[data-mic-row]');
    if (row) row.hidden = holder.hidden;
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

/**
 * A tool's mic, just opened (a live-mic.js handle: deviceId(), use()): when this site has no choice of its own, the mic
 * chosen last here or in the app, by name (sa_mic), else the default; then the picker (`select`, may be null) filled,
 * showing once the mic is allowed and there's more than one input. Every tool that listens does this the same way
 * (Hillel, 2026-11-22: "all of the tool pages with mics need to include a mic chooser").
 */
export async function settleMic(mic, select) {
    // A stand-in mic (a test's) may have neither: then there's nothing to move or show.
    const current = () => mic?.deviceId?.() ?? '';
    const named = await preferredMic(current()).catch((error) => {
        console.warn('The mics could not be listed', error);
        return null;
    });
    if (named && mic?.use) await mic.use(named).catch((error) => console.warn('The remembered mic could not be opened', error));
    if (select) await showPicker(select, current()).catch((error) => console.warn('The mic picker could not be filled', error));
}

/**
 * The picker's row for a tool's More sheet: `micRow(ui.sheet)` puts it first (tool-layout.js sheetRow), hidden until
 * showPicker shows it. Its label word is the tool's own (WORDS `micLabel`).
 */
export function micRow(sheet, sheetRow) {
    const row = sheetRow(sheet);
    row.body.innerHTML = PICKER_ROW;
    row.element.dataset.micRow = '';
    row.element.hidden = true;
    sheet.prepend(row.element);
    return row;
}

/** The picker's markup (a hidden label holding its select). */
export const PICKER_ROW = '<label class="grid gap-1 text-sm" hidden data-picker-label><span class="font-semibold text-cream" data-w="micLabel"></span><select class="input" data-ui="mic-picker" data-picker></select></label>';
