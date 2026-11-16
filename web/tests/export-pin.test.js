// The app's Export, pinned (docs/song-sheet-music-plan.md): the MusicXML, the MIDI and the printed layout of three
// songs kept as they were on 2026-11-16 (a hymn with an upbeat in 3/4, a round in 4/4, a 6/8 tune in a minor key),
// by their SHA-256. The song pages' score shares these modules; a change here must be meant, and its pins rewritten
// (the failing message gives the new hash).
import { toMidi } from '/js/midi.js';
import { toMusicXML } from '/js/musicxml.js';
import { printLayout } from '/js/print-score.js';
import { assert, test } from './harness.js';

const SONGS = ['amazing-grace', 'are-you-sleeping', 'greensleeves'];
const DATE = new Date('2026-11-16T00:00:00Z');

async function sha(data) {
    const bytes = typeof data === 'string' ? new TextEncoder().encode(data) : data;
    return [...new Uint8Array(await crypto.subtle.digest('SHA-256', bytes))].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/** A song's three outputs, hashed: what the pins hold. */
export async function outputsOf(song) {
    return {
        musicxml: await sha(toMusicXML(song, { date: DATE })),
        midi: await sha(toMidi(song)),
        print: await sha(JSON.stringify(printLayout(song))),
        alto: await sha(JSON.stringify(printLayout(song, { partKeys: [song.parts[1].key], cue: true }))),
    };
}

test('export: the MusicXML, MIDI and printed layout of the pinned songs are byte for byte what they were', async () => {
    const pins = await (await fetch('/tests/fixtures/export-pin/pins.json')).json();
    for (const id of SONGS) {
        const song = await (await fetch(`/tests/fixtures/export-pin/${id}.json`)).json();
        const now = await outputsOf(song);
        for (const [what, hash] of Object.entries(pins[id])) assert(now[what] === hash, `${id} ${what}: now ${now[what]}, pinned ${hash}`);
        assert(Object.keys(pins[id]).length === 4, `${id}: four pins`);
    }
});
