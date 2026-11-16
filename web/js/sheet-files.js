// A library song's sheet music to take away (docs/song-sheet-music-plan.md): what a song page's download link asks for
// (`?get=pdf|parts|musicxml|midi`, and `&part=` for one part's sheet), which parts a printed sheet holds, and the
// MusicXML or MIDI file itself, by the same writers as the studio's Export. Pure; unit-tested in
// tests/sheet-files.test.js. views/score-sheet.js is the screen.

import { toMidi } from './midi.js';
import { toMusicXML } from './musicxml.js';

export const SHEET_GETS = ['pdf', 'parts', 'musicxml', 'midi'];

/** What the link asks for: `{ get, part }`, each null when it isn't one we know (a part is checked against the song later). */
export function sheetAsk(search) {
    const query = new URLSearchParams(search);
    const get = query.get('get');
    const part = query.get('part');
    return { get: SHEET_GETS.includes(get) ? get : null, part: part && /^[\w-]{1,40}$/.test(part) ? part : null };
}

/** The song's written parts, in score order. */
export function writtenParts(template) {
    return template.parts.filter((p) => (p.guide?.notes ?? []).length);
}

/**
 * The part a "parts" link opens on: the one asked for when the song has it, else the first written part; null for the
 * full score (any other link).
 */
export function sheetPart(template, { get, part }) {
    if (get !== 'parts') return null;
    const written = writtenParts(template);
    return (written.find((p) => p.key === part) ?? written[0])?.key ?? null;
}

/** A file's name without what an address or a disk refuses. */
const safe = (name) => String(name).replace(/[\\/:*?"<>|]+/g, ' ').replace(/\s+/g, ' ').trim() || 'song';

/** The song as a MusicXML or MIDI file: `{ name, type, data }` (text, or bytes). */
export function sheetFile(template, format, title = template.title) {
    if (format === 'midi') return { name: `${safe(title)}.mid`, type: 'audio/midi', data: toMidi(template) };
    if (format === 'musicxml') return { name: `${safe(title)}.musicxml`, type: 'application/vnd.recordare.musicxml+xml', data: toMusicXML(template) };
    throw new Error(`No ${format} file`);
}
