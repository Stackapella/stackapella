// Hear yourself as a choir: the state the first screens need (Home's panel, a song's sheet, Choirs you sing in) without
// the rest of own-choir.js (the recording, the clip, the choices), so the app's first screen stays in its budget
// (site_integration's PREFETCH_KB). own-choir.js re-exports all of it.

import { reactive } from 'vue';
import { api } from './api.js';
import { account, loadAccount } from './stores/account.js';
import { locale } from './i18n.js';
import { templatePartName } from './templates.js';

const OPEN = ['uploading', 'queued', 'rendering'];

/** Whether it's on for this account: auth.config `ownChoir`, known to be true. */
export function ownChoirOn() {
    return account.config?.ownChoir === true;
}

/** Whether their own songs can be heard in their voice too (auth.config `ownChoirSongs`, the own_choir_songs switch). */
export function ownChoirSongsOn() {
    return ownChoirOn() && account.config?.ownChoirSongs === true;
}

export const CLIP_TEMPLATE = 'voice-capture';

/** The tunes a clip may sing, as the server lists them (the default alone until it has said). */
export function clipSongs() {
    const listed = ownChoir.data?.clipSongs;
    return listed?.length ? listed : [{ templateId: CLIP_TEMPLATE, stopBeat: null }];
}

/** The singer's consent, quota and songs (ownChoir.show), once loaded; null while off or unknown. */
export const ownChoir = reactive({ data: null, loaded: false });

/** Loads (or reloads) where things stand; quietly nothing while it's off. */
export async function loadOwnChoir({ force = false } = {}) {
    // `force`: even with the switch off, so someone with a kept voice or songs can still see and delete them. A guest has
    // no choir: nothing is asked for (it would only answer 401, a page error on every guest page). Who's signed in is
    // known first (the app's own account check, shared, never a second request).
    if (!account.loaded) await loadAccount().catch((error) => console.warn('The account was not checked', error));
    if ((!ownChoirOn() && !force) || !account.me) {
        ownChoir.data = null;
        ownChoir.loaded = true;
        return null;
    }
    try {
        ownChoir.data = (await api.ownChoir.show()).data;
        // Old parts this device kept, cleared by own-choir.js, loaded only for this.
        import('./own-choir.js').then((m) => m.forgetGone()).catch((error) => console.warn('Old choir parts were not cleared', error));
    } catch (error) {
        if (error?.code !== 'not_found') console.warn('Hear yourself as a choir did not load', error);
        if (error?.code === 'not_found') ownChoir.data = null; // off for this person: nothing of theirs to show
    } finally {
        ownChoir.loaded = true;
    }
    return ownChoir.data;
}

/** Whether a choir with friends is on for this account (auth.config `ownChoirTogether`, only when known to be true). */
export function togetherOn() {
    return account.config?.ownChoirTogether === true;
}

/** Part keys as the page's language lists them, lower case: "soprano and alto", "soprano y contralto". */
export function partList(keys, template = null) {
    const names = (keys ?? []).map((key) => {
        const part = template?.parts?.find((p) => p.key === key) ?? { key, name: key };
        return templatePartName(part).toLocaleLowerCase(locale.lang);
    });
    try {
        return new Intl.ListFormat(locale.lang, { style: 'long', type: 'conjunction' }).format(names);
    } catch {
        return names.join(', ');
    }
}

/** Whether a request is still being made. */
export const isOpen = (request) => !!request && OPEN.includes(request.status);

/** The ready songs not yet heard: Home's one quiet notice each. */
export function readyUnseen() {
    return (ownChoir.data?.requests ?? []).filter((r) => r.status === 'ready' && !r.seen);
}

/** What a choir project keeps of its song: the request, its version and parts (to play offline). */
export function choirOf(request, { lead = false } = {}) {
    // `lead`: their whole-song take sings the tune (You + choir; shared as their real voice, `realVoice: ["take"]`).
    const take = lead ? request.leadTake : null;
    const parts = request.parts.map((p) => (take && p.partKey === take.partKey ? { ...take } : { ...p }));
    return { requestId: request.id, templateId: request.templateId, version: request.version, parts, ...(take ? { lead: true } : {}) };
}

/** The voice kept for their next songs (Hillel: "store a model to make it easier"), or null. */
export function savedVoice() {
    return ownChoir.data?.voice ?? null;
}
