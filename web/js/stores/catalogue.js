// The song catalogue from the server (templates.catalogue): each song's current version and, for a
// free song or the song of the month, its parts' real voices. Kept on the device for offline use;
// with neither, the bundled templates play with the synth (docs/beta-singers-plan.md, "Where the
// player finds it"). The notes always come from the templates themselves (web/templates).

import { reactive } from 'vue';
import { api } from '../api.js';
import { local } from '../storage.js';

export const catalogue = reactive({ list: [], loaded: false, source: 'none' });

let loading = null;

/** Loads the catalogue once (again with `fresh`): the server's, else the device's last copy. */
export function loadCatalogue({ fresh = false } = {}) {
    if (loading && !fresh) return loading;
    loading = (async () => {
        try {
            const { data } = await api.templates.catalogue();
            catalogue.list = data;
            catalogue.source = 'server';
            local.set('stackapella.catalogue', JSON.stringify(data));
        } catch (error) {
            const kept = parse(local.get('stackapella.catalogue'));
            catalogue.list = kept ?? [];
            catalogue.source = kept ? 'device' : 'none';
            if (!kept) console.warn('No catalogue: the synth plays every guide', error);
        } finally {
            catalogue.loaded = true;
        }
        return catalogue;
    })();
    return loading;
}

function parse(text) {
    try {
        return text ? JSON.parse(text) : null;
    } catch {
        return null;
    }
}

/**
 * A part's real voice, if the catalogue has one: `{ recording, version, credit }`, where `version`
 * is the template version it was recorded for.
 */
export function recordingFor(templateId, partKey) {
    const entry = catalogue.list.find((song) => song.id === templateId);
    const recording = entry?.parts.find((part) => part.key === partKey)?.guide ?? null;
    return recording ? { recording, version: entry.version, credit: recording.credit } : null;
}

/**
 * A part's published voices (docs/decisions.md, "several versions per part"): `{ versions,
 * version }`, the default first, or null when the part has none. `version` is the template
 * version they were recorded for.
 */
export function versionsFor(templateId, partKey) {
    const entry = catalogue.list.find((song) => song.id === templateId);
    const part = entry?.parts.find((p) => p.key === partKey);
    const versions = part?.versions?.length ? part.versions : part?.guide ? [{ ...part.guide, default: true }] : [];
    return versions.length ? { versions, version: entry.version } : null;
}

/** "Sung by Sarah K. and Ben T." credits for a template's real voices (anonymous ones left out). */
export function creditsFor(templateId) {
    const entry = catalogue.list.find((song) => song.id === templateId);
    return [...new Set((entry?.parts ?? []).map((part) => part.guide?.credit).filter(Boolean))];
}
