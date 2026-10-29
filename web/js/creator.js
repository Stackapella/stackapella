// The template creator's plain parts (docs/template-creator-plan.md): a new template, each sung
// line placed on the song's bar grid after the one before (keeping where it falls in the bar), the
// melody built from the lines with its pickup found from the first note, the words on their notes,
// a phrase a line, copies, and the file people export and import. Pure, unit-tested; the screens
// are js/views/create.js and js/components/create/.

import { lengthBeats, validateTemplate, TemplateError } from './templates.js';
import { beatsPerBar } from './theme.js';

export const MELODY_KEY = 'soprano';
/** Who wrote the song, as the file's licence keeps it (someone else's stays private). */
export const LICENCE = { mine: 'written by the creator', traditional: 'traditional or public domain', other: 'by someone else: kept private' };
const EPSILON = 1e-6;

/** A new, empty template: a device template's id is a UUID (its link too), and it starts at version 1. */
export function newTemplate({ tempo = 90, timeSignature = '4/4', id = crypto.randomUUID() } = {}) {
    return {
        id,
        version: 1,
        reviewed: false,
        title: '',
        description: '',
        difficulty: 'beginner',
        genre: [],
        tempo,
        key: 'C',
        timeSignature,
        pickupBeats: 0,
        credits: { words: '', tune: '', arrangement: '' },
        licence: { words: '', tune: '', arrangement: 'made in Stackapella', recordings: 'synthesized guides', checked: null },
        sections: [],
        parts: [],
    };
}

/**
 * Where a sung line goes. Each line is sung from a bar before its first downbeat (`local` beats:
 * the lead-in bar is 0 to a bar, so a pickup lands in it). It's moved by whole bars, so it keeps its
 * place in the bar, to the earliest spot that starts at or after `after` (the end of the line
 * before). Returns the notes in the song's beats.
 */
export function placeLine(notes, { after = 0, perBar }) {
    if (!notes.length) return [];
    const first = notes[0].beat;
    const shift = Math.ceil((after - first) / perBar - EPSILON) * perBar;
    return notes.map((n) => ({ ...n, beat: n.beat + shift }));
}

/**
 * The melody from the sung lines, each `{ notes, lyrics }` (local beats; lyrics a syllable or null
 * per note): the notes from beat 0 with their words (a line's first note starts a line, `br`), the
 * pickup (the beats before the first bar line) and a phrase a line.
 */
export function melodyFromLines(lines, timeSignature) {
    const perBar = beatsPerBar(timeSignature);
    const placed = [];
    const unsure = [];
    let after = 0;
    for (const line of lines) {
        if (!line?.notes?.length) continue;
        const moved = placeLine(line.notes, { after, perBar });
        moved.forEach((n, i) => {
            // A note to check: one the transcriber wasn't sure of, or an octave away from both neighbours (a slip).
            const slip = i > 0 && i < moved.length - 1 && Math.abs(n.pitch - moved[i - 1].pitch) >= 9 && Math.abs(n.pitch - moved[i + 1].pitch) >= 9;
            if (n.sure === false || slip) unsure.push(n.beat);
        });
        const notes = moved.map((n, i) => {
            const note = { beat: n.beat, length: n.length, pitch: n.pitch };
            const lyric = line.lyrics?.[i];
            if (lyric) note.lyric = lyric;
            if (i === 0) note.br = true;
            return note;
        });
        placed.push(notes);
        after = notes.at(-1).beat + notes.at(-1).length;
    }
    if (!placed.length) return { notes: [], pickupBeats: 0, phrases: [], unsure: [] };
    // Beat 0 is the first note; the bar lines stay where they were.
    const first = placed[0][0].beat;
    const nextBar = Math.ceil(first / perBar - EPSILON) * perBar;
    const pickupBeats = Math.round((nextBar - first) * 1000) / 1000;
    const notes = placed.flat().map((n) => ({ ...n, beat: round(n.beat - first) }));
    const phrases = placed.map((line) => [round(line[0].beat - first), round(line.at(-1).beat + line.at(-1).length - first)]);
    return { notes, pickupBeats: pickupBeats >= perBar - EPSILON ? 0 : pickupBeats, phrases, unsure: unsure.map((b) => round(b - first)) };
}

const round = (beat) => Math.round(beat * 10000) / 10000;

// The parts' English names are the file's (credits, the admin); the app words them from the key.
const PART_NAMES = { soprano: 'Soprano', alto: 'Alto', tenor: 'Tenor', bass: 'Bass' }; // i18n-ignore

/** A part from its notes, its range their lowest and highest. */
export function partOf(key, name, notes, extra = {}) {
    const pitches = notes.map((n) => n.pitch);
    return { key, name, range: { low: Math.min(...pitches), high: Math.max(...pitches) }, ...extra, guide: { type: 'notes', notes } };
}

/** The phrases as one section to the song's end; `name` is the section's name in the person's language. */
export function sectionFrom(phrases, name, endBeat) {
    const end = Math.max(endBeat, ...phrases.map(([, e]) => e));
    return [{ name, startBeat: 0, endBeat: end, phrases: phrases.map(([s, e]) => [s, e]) }];
}

/** Every part's range, fitted to its notes again (after an edit moved one). */
export function fitRanges(template) {
    return { ...template, parts: template.parts.map((p) => (p.guide.notes.length ? partOf(p.key, p.name, p.guide.notes, p.melody ? { melody: true } : {}) : p)) };
}

/**
 * The phrases after an edit: each line (a note marked `br` starts one) of the melody is a phrase;
 * a template whose melody has no words keeps its phrases, clipped to the song.
 */
export function phrasesOf(template) {
    const melody = template.parts.find((p) => p.melody) ?? template.parts[0];
    const notes = melody?.guide.notes ?? [];
    if (!notes.length) return [];
    if (!notes.some((n) => n.br)) return [[notes[0].beat, notes.at(-1).beat + notes.at(-1).length]];
    const out = [];
    for (const n of notes) {
        if (n.br || !out.length) out.push([n.beat, n.beat + n.length]);
        else out.at(-1)[1] = n.beat + n.length;
    }
    return out;
}

/** A copy to change: a new id and version 1, private, the credits kept, marked as a copy. */
export function copyOf(template, title) {
    const copy = structuredClone(template);
    return { ...copy, id: crypto.randomUUID(), version: 1, reviewed: false, title, licence: { ...copy.licence, checked: null } };
}

/** The file to export: the template as JSON (no recordings in this version), and its name. */
export function exportFile(template) {
    const slug = (template.title || 'template').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'template';
    return { name: `${slug}.stackapella.json`, blob: new Blob([JSON.stringify(template, null, 2)], { type: 'application/json' }) };
}

/**
 * An imported file: `{ template }` when it checks out (a new id when it clashes with `taken`), or
 * `{ problems, template }` when it doesn't (the editor can open it to fix), or `{ unreadable }`
 * when it isn't a template at all.
 */
export function readImport(text, { taken = () => false } = {}) {
    let json;
    try {
        json = JSON.parse(text);
    } catch {
        return { unreadable: true };
    }
    if (!json || typeof json !== 'object' || !Array.isArray(json.parts)) return { unreadable: true };
    const template = { ...json, id: typeof json.id === 'string' && /^[a-z0-9-]+$/.test(json.id) && !taken(json.id) ? json.id : crypto.randomUUID() };
    try {
        return { template: validateTemplate(template) };
    } catch (error) {
        if (!(error instanceof TemplateError)) throw error;
        return { problems: error.message.replace(/^Template [^:]*: /, '').split('; '), template };
    }
}

/** Whether a template is finished enough to save: it checks out, with a title and a melody. */
export function problemsOf(template) {
    try {
        validateTemplate(template);
        return [];
    } catch (error) {
        if (!(error instanceof TemplateError)) throw error;
        return error.message.replace(/^Template [^:]*: /, '').split('; ');
    }
}

/** The song's end in beats, or 0 for an empty template. */
export const endOf = (template) => (template.parts.some((p) => p.guide.notes.length) ? lengthBeats(template) : 0);

// ---- Editing notes (the editor and Check the notes) -----------------------------------------------

const MIN_LENGTH = 0.125;

/**
 * Notes in order, one at a time (as a template needs): sorted, none before beat 0, each at least a
 * thirty-second, and a note that runs into the next is cut where the next begins (dropped when
 * nothing of it is left).
 */
export function tidy(notes) {
    const sorted = notes.map((n) => ({ ...n, beat: Math.max(0, round(n.beat)), length: Math.max(MIN_LENGTH, round(n.length)) })).sort((a, b) => a.beat - b.beat);
    const out = [];
    for (const note of sorted) {
        const prev = out.at(-1);
        if (prev && prev.beat + prev.length > note.beat + EPSILON) {
            prev.length = round(note.beat - prev.beat);
            if (prev.length < MIN_LENGTH - EPSILON) {
                // Two notes on one beat: the later one written wins, keeping the words.
                out.pop();
                if (prev.lyric && !note.lyric) note.lyric = prev.lyric;
                if (prev.br) note.br = true;
            }
        }
        out.push(note);
    }
    return out;
}

const MAJOR_SCALE = [0, 2, 4, 5, 7, 9, 11];
const MINOR_SCALE = [0, 2, 3, 5, 7, 8, 10, 11]; // natural minor, and the raised seventh a tune leans on

/**
 * The safe fix for a melody note worth a listen (Hillel: "offer to auto fix notes"), or null when
 * there's none and only an ear can say: an octave slip (an octave or so from both neighbours) moves
 * an octave toward them; a note outside the key (`{ tonic, minor }`, as parseKey gives) moves to the
 * nearest note in it, toward the next note on a tie. Returns `{ pitch, reason: 'octave' | 'key' }`.
 */
export function fixFor(melody, i, key) {
    const n = melody[i];
    if (!n) return null;
    const prev = melody[i - 1];
    const next = melody[i + 1];
    if (prev && next && Math.abs(n.pitch - prev.pitch) >= 9 && Math.abs(n.pitch - next.pitch) >= 9) {
        const middle = (prev.pitch + next.pitch) / 2;
        const pitch = n.pitch + (n.pitch > middle ? -12 : 12);
        if (Math.abs(pitch - middle) < Math.abs(n.pitch - middle)) return { pitch, reason: 'octave' };
    }
    const scale = key.minor ? MINOR_SCALE : MAJOR_SCALE;
    const inKey = (pitch) => scale.includes((((pitch - key.tonic) % 12) + 12) % 12);
    if (inKey(n.pitch)) return null;
    const up = n.pitch + 1;
    const down = n.pitch - 1;
    if (inKey(up) && inKey(down)) return { pitch: next && next.pitch < n.pitch ? down : up, reason: 'key' };
    if (inKey(up)) return { pitch: up, reason: 'key' };
    if (inKey(down)) return { pitch: down, reason: 'key' };
    return null;
}

/** Splits note `i` in two halves (on the grid when it can); the second half holds the vowel. */
export function splitNote(notes, i, grid = 0.25) {
    const note = notes[i];
    let half = Math.round(note.length / 2 / grid) * grid;
    if (half <= 0 || half >= note.length) half = note.length / 2;
    const first = { ...note, length: round(half) };
    const second = { beat: round(note.beat + half), length: round(note.length - half), pitch: note.pitch };
    return [...notes.slice(0, i), first, second, ...notes.slice(i + 1)];
}

/** Joins note `i` with the next: one note to the next one's end, on note `i`'s pitch and syllable. */
export function joinNotes(notes, i) {
    if (i < 0 || i >= notes.length - 1) return notes;
    const next = notes[i + 1];
    const joined = { ...notes[i], length: round(next.beat + next.length - notes[i].beat) };
    if (!joined.lyric && next.lyric) joined.lyric = next.lyric;
    return [...notes.slice(0, i), joined, ...notes.slice(i + 2)];
}

/** Puts `syllables` on the notes from `i` on (one each; notes past them keep theirs). */
export function wordsFrom(notes, i, sylls) {
    return notes.map((n, k) => {
        if (k < i || k >= i + sylls.length) return n;
        const { lyric, ...rest } = n;
        return sylls[k - i] ? { ...rest, lyric: sylls[k - i] } : rest;
    });
}

/** The template with one part's notes replaced (tidied), its range fitted, and the phrases following the melody's lines. */
export function withNotes(template, key, notes) {
    // A part not there yet (Write it, the first note drawn): added, the tune if there's none yet.
    const have = template.parts.some((p) => p.key === key);
    const added = have || !notes.length ? [] : [partOf(key, PART_NAMES[key] ?? key, notes, template.parts.some((p) => p.melody) ? {} : { melody: true })];
    const parts = [...template.parts, ...added].map((p) => (p.key === key ? { ...p, guide: { ...p.guide, notes: tidy(notes) } } : p));
    const next = fitRanges({ ...template, parts });
    const notesLeft = next.parts.flatMap((p) => p.guide.notes);
    if (!notesLeft.length) return next;
    const end = Math.max(...notesLeft.map((n) => n.beat + n.length));
    const melody = next.parts.find((p) => p.melody);
    if (template.sections.length <= 1 && melody?.guide.notes.length) {
        // One section (as the creator makes): its phrases follow the melody's lines.
        next.sections = sectionFrom(phrasesOf(next), template.sections[0]?.name ?? '', end);
    } else {
        // A song with its own sections (a copy): kept, cut back if the song got shorter.
        next.sections = template.sections
            .filter((s) => s.startBeat < end - EPSILON)
            .map((s) => ({ ...s, endBeat: Math.min(s.endBeat, end), phrases: s.phrases.filter(([a]) => a < end - EPSILON).map(([a, b]) => [a, Math.min(b, end)]) }));
    }
    return next;
}

const PART_ORDER = ['soprano', 'alto', 'tenor', 'bass'];
// Who joins the tune first as singers are added.
const JOINS = ['alto', 'bass', 'tenor', 'soprano'];

/**
 * The parts in their usual order, the order to record them (the tune first, then the bass), and the
 * versions for 2, 3 and 4 singers, each with the tune. Empty parts are left out.
 */
export function finishParts(template) {
    const parts = template.parts.filter((p) => p.guide.notes.length).sort((a, b) => PART_ORDER.indexOf(a.key) - PART_ORDER.indexOf(b.key));
    const melody = parts.find((p) => p.melody) ?? parts[0];
    if (!melody) return { ...template, parts };
    const others = JOINS.filter((k) => k !== melody.key && parts.some((p) => p.key === k));
    const voicings = {};
    for (let n = 2; n <= Math.min(4, parts.length); n++) voicings[n] = parts.filter((p) => p === melody || others.slice(0, n - 1).includes(p.key)).map((p) => p.key);
    const { voicings: _, order: __, ...rest } = template;
    return {
        ...rest,
        parts: parts.map((p) => (p === melody ? { ...p, melody: true } : (({ melody: m, ...q }) => q)(p))),
        ...(parts.length > 1 ? { order: [melody.key, ...['bass', 'alto', 'tenor', 'soprano'].filter((k) => k !== melody.key && parts.some((p) => p.key === k))], voicings } : {}),
    };
}

/** The snap for a time signature: an eighth (a third of a beat in 6/8, whose beat is a dotted quarter). */
export const gridFor = (timeSignature) => (timeSignature === '6/8' ? 1 / 3 : 0.5);
