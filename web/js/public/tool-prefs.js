// Every free tool remembers its settings on this device (Hillel, 2026-11-03: "make sure all of the
// tools remember the settings locally"): one store per tool, kept in localStorage under
// stackapella.tools.<slug>. The one read and write path for tool settings, made so syncing them to
// the account later is a small step (docs/tools-plan.md, "Later: sync tool settings to the account"):
// - each tool's record is one serialisable object, `{ v, updatedAt, settings }`: `v` its schema
//   version, `updatedAt` (ms) for last-writer-wins, `settings` its values;
// - every write goes through save() or reset() and announces itself (onToolPrefs, and a
//   `stackapella:tool-prefs` event on window), where a sync layer can listen;
// - settings only: never recordings, names or anything personal.
// Bad, unknown or old values are dropped safely: each value must match its default's type (or pass
// the tool's own check), and storage that's blocked or full just means the tool forgets. A value from
// the link (?bpm=, ?a4=) can win for the visit without being saved, unless the person changes it.
// Tested in tests/public-tool-prefs.test.js.

const PREFIX = 'stackapella.tools.';
const EVENT = 'stackapella:tool-prefs';
const listeners = new Set();

const isPlain = (x) => x !== null && typeof x === 'object' && !Array.isArray(x);

/**
 * `stored` cleaned against `defaults`: each key keeps its stored value when `valid[key]` (if given)
 * accepts it, or else when it has its default's type (arrays stay arrays, objects are cleaned key by
 * key); anything else, and any key the defaults don't have, falls back or is dropped.
 */
export function cleanSettings(defaults, stored, valid = {}) {
    const out = structuredClone(defaults);
    if (!isPlain(stored)) return out;
    for (const [key, fallback] of Object.entries(defaults)) {
        if (!(key in stored)) continue;
        const value = stored[key];
        if (typeof valid[key] === 'function') {
            if (valid[key](value)) out[key] = structuredClone(value);
        } else if (isPlain(fallback)) {
            out[key] = cleanSettings(fallback, value);
        } else if (Array.isArray(fallback)) {
            if (Array.isArray(value)) out[key] = structuredClone(value);
        } else if (fallback === null ? value === null || typeof value !== 'object' : typeof value === typeof fallback && (typeof value !== 'number' || Number.isFinite(value))) {
            out[key] = value;
        }
    }
    return out;
}

function readRecord(slug) {
    try {
        const raw = JSON.parse(localStorage.getItem(PREFIX + slug) ?? 'null');
        return isPlain(raw) ? raw : null;
    } catch {
        return null;
    }
}

function writeRecord(slug, record) {
    try {
        if (record) localStorage.setItem(PREFIX + slug, JSON.stringify(record));
        else localStorage.removeItem(PREFIX + slug);
    } catch (error) {
        console.info('The tool settings last only for this visit', error?.name); // private window, full storage
    }
    for (const fn of listeners) fn(slug, record);
    window.dispatchEvent(new CustomEvent(EVENT, { detail: { slug, record } }));
}

/**
 * A tool's settings. `defaults` names every setting and its type; `valid` holds checks for values a
 * type can't judge (a mode from a list, a number in a range); `clean(settings)` mends what depends on
 * more than one value (a bar's accents to its beats); `version` is the record's schema (an older one,
 * or the bare settings object a tool kept before this store, is read through the same cleaning).
 * Returns { value, save(), reset(), visit(change) }: change `value` in place and save(); visit() sets
 * values for this visit only (from the link), kept out of storage until the person changes them.
 */
export function toolPrefs(slug, { defaults, valid = {}, clean = (s) => s, version = 1 }) {
    const record = readRecord(slug);
    // A record from this store has { v, settings }; before it, a tool kept its settings bare.
    const stored = record && 'settings' in record && 'v' in record ? record.settings : record;
    let saved = clean(cleanSettings(defaults, stored, valid));
    const value = structuredClone(saved);
    const visiting = new Map(); // key -> the value the link gave
    return {
        value,
        /** Writes `value`, leaving out what the link set and the person hasn't changed since. */
        save() {
            const next = structuredClone(value);
            for (const [key, given] of visiting) {
                if (JSON.stringify(value[key]) === JSON.stringify(given)) next[key] = saved[key];
                else visiting.delete(key);
            }
            saved = next;
            writeRecord(slug, { v: version, updatedAt: Date.now(), settings: next });
        },
        /** Back to the defaults, and forgotten (More's Reset). */
        reset() {
            for (const key of Object.keys(value)) delete value[key];
            Object.assign(value, structuredClone(defaults));
            saved = structuredClone(defaults);
            visiting.clear();
            writeRecord(slug, null);
        },
        /** Values for this visit only (?bpm=, ?a4=), cleaned like stored ones. */
        visit(change) {
            const cleaned = cleanSettings(defaults, { ...value, ...change }, valid);
            for (const key of Object.keys(change)) {
                // Only a value the cleaning kept: a bad one from the link changes nothing.
                if (!(key in defaults) || JSON.stringify(cleaned[key]) !== JSON.stringify(change[key])) continue;
                value[key] = cleaned[key];
                visiting.set(key, structuredClone(cleaned[key]));
            }
        },
    };
}

/** Hears every tool's settings being saved or reset: (slug, record or null). Returns the unsubscribe. */
export function onToolPrefs(fn) {
    listeners.add(fn);
    return () => listeners.delete(fn);
}

/** Every tool's record on this device, `{ slug: { v, updatedAt, settings } }`: what a sync would send. */
export function allToolPrefs() {
    const out = {};
    try {
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (!key?.startsWith(PREFIX)) continue;
            const slug = key.slice(PREFIX.length);
            const record = readRecord(slug);
            if (record && 'settings' in record) out[slug] = record;
        }
    } catch {
        // storage blocked: nothing to send
    }
    return out;
}
