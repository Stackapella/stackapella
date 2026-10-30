// A song as a standard MIDI file (docs/sheet-music-plan.md, slice 1): type 1, a track for the tempo,
// metre, key and credits, then a track a part, each on its own channel with its name and its words
// as lyric events. Pure: a template in, the file's bytes out, unit-tested in tests/sheet-music.test.js.

import { keySignature } from './sing.js';
import { metreOf, rightsOf } from './musicxml.js';

export const TICKS = 480; // per quarter note

/** A variable-length quantity, as MIDI writes delta times and lengths. */
export function vlq(value) {
    const bytes = [value & 0x7f];
    let v = value >> 7;
    while (v > 0) {
        bytes.unshift((v & 0x7f) | 0x80);
        v >>= 7;
    }
    return bytes;
}

const utf8 = (text) => [...new TextEncoder().encode(String(text))];
const meta = (type, data) => [0xff, type, ...vlq(data.length), ...data];
const u32 = (n) => [(n >>> 24) & 255, (n >>> 16) & 255, (n >>> 8) & 255, n & 255];

/** A track chunk from timed events ({ at, bytes }, at in ticks), sorted, with its end. */
function track(events) {
    const sorted = [...events].sort((a, b) => a.at - b.at || a.order - b.order);
    const data = [];
    let now = 0;
    for (const e of sorted) {
        data.push(...vlq(e.at - now), ...e.bytes);
        now = e.at;
    }
    data.push(0, ...meta(0x2f, []));
    return [0x4d, 0x54, 0x72, 0x6b, ...u32(data.length), ...data];
}

/**
 * The song as MIDI bytes (Uint8Array). `partKeys`: the parts to write (default every part with
 * notes). Notes are channel voices ("choir aahs"), the words lyric events at each note.
 */
export function toMidi(template, { partKeys = null } = {}) {
    const parts = template.parts.filter((p) => (p.guide?.notes ?? []).length && (!partKeys || partKeys.includes(p.key)));
    if (!parts.length) throw new Error('No written parts to export');
    const metre = metreOf(template.timeSignature);
    const perBeat = Math.round(metre.quarters * TICKS);
    const sig = keySignature(template.key || 'C');
    const fifths = sig.type === 'flat' ? -sig.count : sig.count;
    const microseconds = Math.round(60000000 / (template.tempo * metre.quarters)); // a quarter note's length
    const head = [
        { at: 0, order: 0, bytes: meta(0x03, utf8(template.title ?? '')) },
        { at: 0, order: 1, bytes: meta(0x51, [(microseconds >> 16) & 255, (microseconds >> 8) & 255, microseconds & 255]) },
        { at: 0, order: 2, bytes: meta(0x58, [metre.beats, Math.log2(metre.beatType), metre.beatType === 8 ? 36 : 24, 8]) },
        { at: 0, order: 3, bytes: meta(0x59, [fifths & 255, /m$/.test(template.key ?? '') ? 1 : 0]) },
    ];
    const rights = rightsOf(template);
    if (rights) head.push({ at: 0, order: 4, bytes: meta(0x02, utf8(rights)) });
    const tracks = [track(head)];
    parts.forEach((part, i) => {
        const channel = i >= 9 ? i + 1 : i; // channel 10 (9) is drums
        const events = [
            { at: 0, order: 0, bytes: meta(0x03, utf8(part.name ?? part.key)) },
            { at: 0, order: 1, bytes: [0xc0 | channel, 52] }, // choir aahs
        ];
        for (const n of part.guide.notes) {
            const on = Math.round(n.beat * perBeat);
            const off = Math.max(on + 1, Math.round((n.beat + n.length) * perBeat));
            if (n.lyric) events.push({ at: on, order: 2, bytes: meta(0x05, utf8(n.lyric.replace(/-$/, ''))) });
            events.push({ at: on, order: 3, bytes: [0x90 | channel, n.pitch, 80] });
            events.push({ at: off, order: 1, bytes: [0x80 | channel, n.pitch, 0] }); // offs before the next note's on
        }
        tracks.push(track(events));
    });
    const header = [0x4d, 0x54, 0x68, 0x64, ...u32(6), 0, 1, 0, tracks.length, (TICKS >> 8) & 255, TICKS & 255];
    return new Uint8Array([...header, ...tracks.flat()]);
}
