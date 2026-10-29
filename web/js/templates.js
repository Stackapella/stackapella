// Harmony templates: ready-made arrangements in web/templates/<id>.json (format in
// docs/templates-plan.md). Times are in beats from beat 0 (the first pickup beat); pitches are
// MIDI note numbers; a part's lyrics ride on its notes (a note without one continues a word).

import { api } from './api.js';
import { t, tk } from './i18n.js';
import { local } from './storage.js';
import { beatsPerBar, TIME_SIGNATURES } from './theme.js';

/** The songs free for everyone, bundled with the app (docs/decisions.md, "Four songs free"). The free course uses only these. */
export const FREE_SONGS = ['amazing-grace', 'are-you-sleeping', 'michael-row', 'happy-birthday'];

// The two official sets: SATB, and barbershop's tenor, lead (the tune), baritone and bass.
const PART_NAMES = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), lead: tk('parts.lead'), baritone: tk('parts.baritone'), bass: tk('parts.bass') };

/** A template part's name in the person's language (part names are keys, not stored English). */
export function templatePartName(part) {
    return PART_NAMES[part?.key] ? t(PART_NAMES[part.key]) : part?.name ?? '';
}

/**
 * A project part's name: a template slot that still has its template's name shows it translated;
 * a part the singer named (or renamed) keeps their name.
 */
export function slotName(slot, template) {
    const part = slot?.templatePartKey ? template?.parts.find((p) => p.key === slot.templatePartKey) : null;
    return part && slot.name === part.name ? templatePartName(part) : slot?.name ?? '';
}

export class TemplateError extends Error {}

/**
 * Every song on the shelf: the four the app carries, then the rest of the library from the
 * catalogue (`library: false` for only the four). Drafts (not yet checked by a musician) show as
 * showDrafts() says.
 */
export async function listTemplates({ drafts = showDrafts(), library = true } = {}) {
    const index = await fetchJson('/templates/index.json');
    const bundled = await Promise.all(index.templates.map((id) => loadBundled(id)));
    if (!library) return bundled.filter((t) => t.reviewed || drafts); // only the songs the app carries
    // The rest of the library (the Plus songs) from the catalogue, as summaries: never their notes.
    const { catalogue, loadCatalogue } = await import('./stores/catalogue.js');
    await loadCatalogue();
    const rest = catalogue.list.filter((entry) => !index.templates.includes(entry.id)).map(songSummary);
    const access = new Map(catalogue.list.map((entry) => [entry.id, entry]));
    const all = [...bundled.map((tpl) => ({ ...tpl, freeUntil: access.get(tpl.id)?.freeUntil ?? null })), ...rest];
    return all.filter((t) => t.reviewed || drafts);
}

/**
 * A song the app doesn't carry, as the catalogue lists it: its title, parts and ranges, its preview
 * clip and whether it's free (`access`, `freeUntil` for the song of the month). `summary: true`:
 * loadTemplate() fetches the song itself.
 */
export function songSummary(entry) {
    return {
        id: entry.id,
        version: entry.version,
        title: entry.title,
        description: entry.description ?? '',
        difficulty: entry.difficulty,
        genre: entry.genre ?? [],
        key: entry.key,
        tempo: entry.tempo,
        timeSignature: entry.timeSignature,
        reviewed: entry.reviewed,
        access: entry.access,
        freeUntil: entry.freeUntil ?? null,
        previewUrl: entry.previewUrl ?? null,
        parts: entry.parts.map((p) => ({ key: p.key, name: p.name, range: p.range })),
        voicings: entry.voicings ?? {}, // who sings for each number of singers: the card's "2 to 4"
        summary: true,
    };
}

/** Whether a song can be started now: free, the song of the month, or on Plus. */
export function canStart(song, plus) {
    return !song.summary || plus || song.access === 'free' || (!!song.freeUntil && Date.parse(song.freeUntil) > Date.now());
}

// Before the launch (the server's `prelaunch`, remembered so the first list after opening is right)
// and for an admin, every draft shows (Hillel's call: the full library while testing).
const drafts = { prelaunch: local.get('stackapella.prelaunch') === '1', admin: false, listeners: new Set() };

/** What the server says (auth.config's `prelaunch`) and whether an admin is signed in; the shelf lists again when it changes. */
export function setDraftsFor({ prelaunch = drafts.prelaunch, admin = drafts.admin } = {}) {
    const changed = drafts.prelaunch !== !!prelaunch || drafts.admin !== !!admin;
    Object.assign(drafts, { prelaunch: !!prelaunch, admin: !!admin });
    local.set('stackapella.prelaunch', drafts.prelaunch ? '1' : '');
    if (changed) drafts.listeners.forEach((listener) => listener());
}

/** Calls `listener` when drafts start or stop showing; returns the way to stop. */
export function onDraftsChange(listener) {
    drafts.listeners.add(listener);
    return () => drafts.listeners.delete(listener);
}

/**
 * Drafts show before the launch and to admins (docs/decisions.md, "drafts before launch"), on this
 * machine (localhost, or a Herd *.test site), on a `beta.` host, and in a browser that once opened
 * the app with ?drafts=1 (remembered, so they survive moving around the app and a tunnel's changing
 * address; ?drafts=0 forgets it). After the launch, on the app's own host, they hide.
 */
export function showDrafts(host = location.hostname) {
    const asked = new URLSearchParams(location.search).get('drafts');
    if (asked !== null) {
        local.set('stackapella.drafts', asked === '0' ? '' : '1');
    }
    const thisMachine = ['localhost', '127.0.0.1'].includes(host) || host.endsWith('.test');
    return drafts.prelaunch || drafts.admin || thisMachine || host.startsWith('beta.') || local.get('stackapella.drafts') === '1';
}

/**
 * A song in full: one the app carries (the four free songs), one of the person's own on this
 * device, else from the account (templates.show: on Plus, the song of the month, or one kept from
 * it; someone's own by its link or a choir group), which refuses with `plus_required`.
 */
export async function loadTemplate(id) {
    const index = await fetchJson('/templates/index.json');
    if (index.templates.includes(id)) return loadBundled(id);
    // One of the person's own, kept on this device (docs/template-creator-plan.md).
    const { ownTemplate } = await import('./stores/templates.js');
    const own = await ownTemplate(id);
    if (own) return validateTemplate(structuredClone(own));
    const { data } = await api.templates.show(id);
    return validateTemplate(data.template);
}

function loadBundled(id) {
    return fetchJson(`/templates/${encodeURIComponent(id)}.json`).then(validateTemplate);
}

async function fetchJson(url) {
    const response = await fetch(url);
    if (!response.ok) {
        throw new TemplateError(`Couldn't load ${url} (${response.status})`);
    }
    return response.json();
}

/** The song's length in beats: to the end of its last note. */
export function lengthBeats(template) {
    return template.parts.reduce((end, part) => Math.max(end, ...part.guide.notes.map((n) => n.beat + n.length)), 0);
}

/** The numbers of singers a template can be sung by (from its voicings, or all its parts). */
export function singerOptions(template) {
    const counts = Object.keys(template.voicings ?? {}).map(Number).sort((a, b) => a - b);
    return counts.length ? counts : [template.parts.length];
}

/** The parts for `singers` singers, in the template's part order. */
export function partsFor(template, singers = Math.max(...singerOptions(template))) {
    const keys = template.voicings?.[String(singers)];
    // An instrument part plays whoever sings: it isn't one of the singers.
    return keys ? template.parts.filter((p) => keys.includes(p.key) || p.kind === 'instrument') : template.parts;
}

/** Every phrase in order, with its section's name. */
export function phrases(template) {
    return template.sections.flatMap((section) =>
        section.phrases.map(([startBeat, endBeat], index) => ({ section: section.name, index, startBeat, endBeat })),
    );
}

/**
 * A part's lyrics as lines of syllables; a note marked `br` starts a new line. With the song's
 * other parts, a part that comes in mid-word (the alto's "maz-" after the melody's pickup "A-")
 * gets the word's start too, marked `borrowed`: shown, never lit, since this part doesn't sing it.
 */
export function lyricLines(part, others = []) {
    const lines = [];
    for (const note of part.guide.notes) {
        if (note.br || !lines.length) {
            lines.push({ startBeat: note.beat, endBeat: note.beat + note.length, syllables: [] });
        }
        const line = lines[lines.length - 1];
        line.endBeat = note.beat + note.length;
        if (note.lyric) {
            const before = line.syllables.length ? null : wordStartBefore(note, part, others);
            if (before) line.syllables.push({ beat: before.beat, text: before.lyric, borrowed: true });
            line.syllables.push({ beat: note.beat, text: note.lyric });
        }
    }
    return lines.filter((line) => line.syllables.length);
}

/** Another part's syllable that starts the word `note` goes on with, sung just before it, if any. */
function wordStartBefore(note, part, others) {
    const own = part.guide.notes.filter((n) => n.lyric && n.beat < note.beat).at(-1);
    if (own?.lyric.endsWith('-')) return null; // its own word, going on
    for (const other of others) {
        if (other === part || other.key === part.key) continue;
        const notes = other.guide.notes;
        const i = notes.findIndex((n) => Math.abs(n.beat - note.beat) < 1e-6 && n.lyric === note.lyric);
        const start = i > 0 ? notes.slice(0, i).filter((n) => n.lyric).at(-1) : null;
        if (start?.lyric.endsWith('-') && start.beat < note.beat && start.beat >= (own ? own.beat + 1e-6 : -Infinity)) return start;
    }
    return null;
}

/** Checks a template thoroughly and returns it; throws a TemplateError naming every problem. */
/**
 * A template made by the creator, put right before it's kept or sent (Hillel's 422 on a Start from
 * chords song): a note without a whole-number pitch goes (a rest, where a transcription once left a
 * NaN), each part's range fits its notes again, and chords from the song's end on go (each lasts
 * to the next or the end, so they did nothing). Returns `{ template, changed }`.
 */
export function repairTemplate(template) {
    let changed = false;
    const parts = template.parts.map((part) => {
        if (part.guide?.type !== 'notes') return part;
        const notes = part.guide.notes.filter((n) => Number.isFinite(n.pitch)).map((n) => (Number.isInteger(n.pitch) ? n : { ...n, pitch: Math.round(n.pitch) }));
        const moved = notes.length !== part.guide.notes.length || notes.some((n, i) => n !== part.guide.notes[i]);
        const pitches = notes.map((n) => n.pitch);
        const range = pitches.length ? { low: Math.min(...pitches), high: Math.max(...pitches) } : part.range;
        const refit = pitches.length && (moved || !Number.isFinite(part.range?.low) || !Number.isFinite(part.range?.high) || part.range.low > range.low || part.range.high < range.high);
        if (!moved && !refit) return part;
        changed = true;
        return { ...part, ...(refit ? { range: { ...part.range, ...range } } : {}), guide: { ...part.guide, notes } };
    });
    const end = Math.max(0, ...parts.flatMap((p) => (p.guide?.notes ?? []).map((n) => n.beat + n.length)));
    const chords = template.chords?.filter((c, i) => i === 0 || c.beat < end - 1e-6);
    if (chords && chords.length !== template.chords.length) changed = true;
    return { changed, template: changed ? { ...template, parts, ...(chords ? { chords } : {}) } : template };
}

// The server's twin (server/app/Support/Templates/TemplateRules.php) refuses the same files.
const MAX_PARTS = 6;
const PART_KEY = /^[a-z][a-z0-9-]{0,39}$/;

/** The song builder's record (optional): 1 to 64 bars of short numerals, at most 16 sections, swing and mix 0 to 1. */
function builderProblems(builder, need) {
    if (!builder || typeof builder !== 'object' || Array.isArray(builder)) return;
    const barsOk = (bars) => Array.isArray(bars) && bars.length > 0 && bars.length <= 64 && bars.every((bar) => Array.isArray(bar) && bar.length > 0 && bar.every((n) => typeof n === 'string' && n !== '' && [...n].length <= 12));
    const progression = builder.progression;
    const isObject = (v) => v && typeof v === 'object' && !Array.isArray(v);
    need(!isObject(progression) || barsOk(progression.bars), 'builder: the progression needs 1 to 64 bars of numerals (up to 12 characters each)');
    need(!isObject(progression) || progression.swing === undefined || (Number.isFinite(progression.swing) && progression.swing >= 0 && progression.swing <= 1), 'builder: swing must be 0 to 1');
    for (const level of Object.values(builder.mix ?? {})) need(Number.isFinite(level) && level >= 0 && level <= 1, 'builder: each mix level must be 0 to 1');
    const sections = Array.isArray(builder.sections) ? builder.sections : [];
    need(sections.length <= 16, 'builder: at most 16 sections');
    for (const section of sections) need(isObject(section) && barsOk(section.bars), 'builder: each section needs at most 64 bars of numerals (up to 12 characters each)');
}

export function validateTemplate(t) {
    const problems = [];
    const need = (ok, message) => ok || problems.push(message);
    need(typeof t?.id === 'string' && /^[a-z0-9-]+$/.test(t.id), 'id must be lowercase letters, digits and dashes');
    need(Number.isInteger(t?.version) && t.version >= 1, 'version must be a whole number from 1');
    need(typeof t?.title === 'string' && t.title.trim(), 'title is missing');
    need(typeof t?.reviewed === 'boolean', 'reviewed must be true or false');
    need(Number.isFinite(t?.tempo) && t.tempo >= 30 && t.tempo <= 300, 'tempo must be 30 to 300');
    need(typeof t?.key === 'string', 'key is missing');
    need(TIME_SIGNATURES.includes(t?.timeSignature), `timeSignature must be one of ${TIME_SIGNATURES.join(', ')}`);
    // How it's sung (docs/vibrato-plan.md): the song's, and a part's own; free when unsaid. Metadata, no version.
    const STYLES = ['straight', 'free', 'vibrato'];
    need(t?.style === undefined || STYLES.includes(t.style), `style must be one of ${STYLES.join(', ')}`);
    need((t?.parts ?? []).every((p) => p.style === undefined || STYLES.includes(p.style)), `a part's style must be one of ${STYLES.join(', ')}`);
    need(t?.licence && typeof t.licence === 'object', 'licence is missing');
    const perBar = beatsPerBar(t?.timeSignature);
    need(Number.isFinite(t?.pickupBeats) && t.pickupBeats >= 0 && t.pickupBeats < perBar, 'pickupBeats must be 0 or more, and less than a bar');
    need(Array.isArray(t?.parts) && t.parts.length > 0, 'parts are missing');
    need((t?.parts?.length ?? 0) <= MAX_PARTS, `parts: at most ${MAX_PARTS}`);
    const keys = new Set();
    for (const part of t?.parts ?? []) {
        const name = `part "${part?.key}"`;
        need(typeof part?.key === 'string' && !keys.has(part.key), `${name}: key missing or repeated`);
        need(typeof part?.key === 'string' && PART_KEY.test(part.key), `${name}: key must be lower-case letters, digits and dashes, starting with a letter (up to 40)`);
        need(part?.kind === undefined || ['voice', 'instrument'].includes(part.kind), `${name}: kind must be voice or instrument`);
        keys.add(part?.key);
        need(typeof part?.name === 'string' && part.name !== '' && [...part.name].length <= 40, `${name}: name is missing or over 40 characters`);
        const range = part?.range;
        need(range && range.low <= range.high, `${name}: range needs low <= high`);
        need(part?.guide?.type === 'notes' && Array.isArray(part.guide.notes) && part.guide.notes.length, `${name}: guide needs notes`);
        let end = -Infinity;
        for (const [i, note] of (part?.guide?.notes ?? []).entries()) {
            const at = `${name}, note ${i + 1}`;
            need(Number.isFinite(note.beat) && note.beat >= 0, `${at}: beat must be 0 or more`);
            need(Number.isFinite(note.length) && note.length > 0, `${at}: length must be more than 0`);
            need(Number.isInteger(note.pitch), `${at}: pitch must be a MIDI note number`);
            need(note.beat >= end - 1e-9, `${at}: starts before the previous note ends (one note at a time, in order)`);
            if (range) need(note.pitch >= range.low && note.pitch <= range.high, `${at}: pitch ${note.pitch} is outside the part's range`);
            end = note.beat + note.length;
        }
    }
    for (const key of t?.order ?? []) {
        need(keys.has(key), `order names a part that doesn't exist: ${key}`);
    }
    // One set of voices, never a mix (the server's TemplateRules): SATB, or barbershop (a lead or a baritone).
    need(!((keys.has('lead') || keys.has('baritone')) && (keys.has('soprano') || keys.has('alto'))), 'parts: SATB (soprano, alto, tenor, bass) or barbershop (tenor, lead, baritone, bass), never a mix');
    const melody = (t?.parts ?? []).find((p) => p.melody)?.key;
    for (const [count, list] of Object.entries(t?.voicings ?? {})) {
        need(/^[1-8]$/.test(count) && Array.isArray(list) && list.length === Number(count), `voicings: "${count}" must list exactly ${count} parts`);
        need(new Set(list).size === list.length && list.every((key) => keys.has(key)), `voicings: "${count}" names a missing or repeated part`);
        need(!melody || list.includes(melody), `voicings: "${count}" must include the melody (${melody})`);
    }
    const total = problems.length ? 0 : lengthBeats(t);
    for (const section of t?.sections ?? []) {
        const name = `section "${section?.name}"`;
        need(section.startBeat >= 0 && section.endBeat > section.startBeat && (!total || section.endBeat <= total + 1e-9), `${name}: must lie inside the song`);
        let previous = section.startBeat;
        for (const [start, stop] of section.phrases ?? []) {
            need(start >= previous - 1e-9 && stop > start && stop <= section.endBeat + 1e-9, `${name}: phrases must be in order and inside the section`);
            previous = stop;
        }
    }
    // Chords underneath (optional): each lasts to the next one's beat, so in order and inside the song.
    let previousChord = -1;
    for (const [i, chord] of (t?.chords ?? []).entries()) {
        const beat = Number.isFinite(chord?.beat) ? chord.beat : -1;
        need(beat >= 0 && beat > previousChord && (!total || beat < total), `chord ${i}: beats must be ascending, from 0, inside the song`);
        previousChord = beat;
    }
    builderProblems(t?.builder, need);
    if (problems.length) {
        throw new TemplateError(`Template ${t?.id ?? '(no id)'}: ${problems.join('; ')}`);
    }
    return t;
}
