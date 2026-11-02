// Sight-singing's stave (components/read-stave.js; docs/sight-singing-plan.md): a lesson's line as
// one engraved part, its bars from the first note's bar, in the key whose do is the singer's note,
// in the clef its range wants, every note on the stave once, each piece of a tied note knowing its note.
import { readClef, readSong } from '/js/components/read-stave.js';
import { noteLabel } from '/js/notes.js';
import { printLayout } from '/js/print-score.js';
import { assert, test } from './harness.js';

const line = (home, steps, from = 4) => steps.map((s, i) => ({ beat: from + i, length: 1, pitch: home + s }));

test('read stave: the line in the key and clef of the singer\'s do, from its first bar', () => {
    const { template, shift } = readSong(line(62, [0, 2, 4, 2, 0]), { home: 62, tempo: 66 });
    assert(shift === 4 && template.key === 'D' && template.tempo === 66 && template.parts[0].guide.notes[0].beat === 0, JSON.stringify({ shift, key: template.key }));
    assert(readSong(line(63, [0]), { home: 63 }).template.key === 'Eb' && readSong(line(66, [0]), { home: 66 }).template.key === 'F#' && readSong(line(48, [0]), { home: 48 }).template.key === 'C', 'keys by do');
    // The fewest ledger lines for the course's range: bass under G3, treble with an 8 below under E4, else treble.
    assert(readClef(50) === 'bass' && readClef(54) === 'bass' && readClef(55) === 'treble8' && readClef(57) === 'treble8' && readClef(60) === 'treble8' && readClef(64) === 'treble', 'the clef by the singer\'s do');
    // The same singer reads every line in one clef, whatever the line's range (do sits still).
    const clefOf = (steps) => readSong(line(57, steps), { home: 57 }).template.parts[0].key;
    assert(clefOf([0, 2, 4]) === clefOf([-5, 0, 7]) && clefOf([0, 7, 4]) === 'tenor', 'one clef for a reader: do A3 reads treble with an 8 below');
    assert(readSong(line(62, [0, 2], 6), { home: 62 }).shift === 4, 'a line starting mid-bar keeps its place in the bar');
});

test('read stave: every note on the stave once, in order, a tied note\'s pieces all its own, named as do re mi', () => {
    const notes = [{ beat: 0, length: 1, pitch: 60 }, { beat: 1, length: 1, pitch: 62 }, { beat: 2, length: 4, pitch: 64 }, { beat: 6, length: 2, pitch: 60 }];
    const layout = printLayout(readSong(notes, { home: 60 }).template, { names: false });
    const items = layout.systems.flatMap((s) => s.staves[0].items).filter((i) => i.kind === 'note');
    assert(new Set(items.map((i) => i.note)).size === notes.length && items.map((i) => i.note).join() === '0,1,2,2,3', `pieces: ${items.map((i) => i.note)}`);
    assert(items.find((i) => i.note === 2 && i.tieIn), 'the third note tied over the bar');
    assert(layout.edge < 20 && layout.width === null, 'no names column; as wide as the music');
    assert(notes.map((n) => noteLabel(n.pitch, 'solfege', 'C')).join(' ') === 'do re mi do' && noteLabel(64, 'solfege', 'E') === 'do', 'movable do');
});

test('read scorer: pitch alone; with rhythm, a reader on time keeps the score and one late loses timing\'s share', async () => {
    const { PLAY_SCORERS } = await import('/js/lesson-scores.js');
    const { trackTake } = await import('/js/pitchtrack.js');
    const { sing } = await import('./fixtures.js');
    const sr = 48000;
    const notes = [0, 2, 4, 5, 7, 4, 2, 0].map((s, i) => ({ beat: i, length: 1, pitch: 60 + s }));
    // `late`: from the fifth note on, each comes in half a beat late (the fourth held too long, a rhythm misread).
    const args = (late, rhythm) => ({ track: trackTake(sing(sr, { notes: notes.map((n, i) => ({ ...n, beat: n.beat + (i >= 4 ? late : 0), length: i === 3 ? 1 + late : n.length })), bpm: 60 }), sr), notes, beatSeconds: 1, strictness: 'relaxed', exercise: { staff: rhythm ? { rhythm: true } : {} }, timing: 'measured' });
    const plain = PLAY_SCORERS.read(args(0, false));
    const onTime = PLAY_SCORERS.read(args(0, true));
    const late = PLAY_SCORERS.read(args(0.5, true));
    assert(plain.percent >= 90 && !plain.line, `pitch alone: ${plain.percent}`);
    assert(onTime.percent >= 90 && onTime.line.values.timing >= 90, `on time: ${JSON.stringify(onTime)}`);
    assert(late.percent < onTime.percent && late.line.values.timing < onTime.line.values.timing, `late: ${JSON.stringify(late)} vs ${JSON.stringify(onTime)}`);
});

test('read stave: a song\'s line keeps its pickup and its own key, minor included', async () => {
    const { songExercise } = await import('/js/song-course.js');
    const song = await (await fetch('/templates/amazing-grace.json')).json(); // 3/4, a one-beat pickup
    const { exercise } = songExercise(song, { song: { template: song.id, part: 'alto', phrases: [0] }, turn: false, guide: false, staff: { names: 'faint', look: 4 } });
    assert(exercise.staff.time === '3/4' && exercise.staff.pickup === 1 && exercise.staff.names === 'faint', `the song's time and pickup: ${JSON.stringify(exercise.staff)}`);
    const second = songExercise(song, { song: { template: song.id, part: 'alto', phrases: [1] }, turn: false, guide: false, staff: {} }).exercise.staff;
    assert(Number.isFinite(second.pickup) && second.pickup >= 0 && second.pickup < 3, `a later phrase's bar lines: ${second.pickup}`);
    // The stave: a one-beat first bar, then whole bars of three.
    const notes = [{ beat: 0, length: 1, pitch: 57 }, { beat: 1, length: 2, pitch: 62 }, { beat: 3, length: 3, pitch: 64 }];
    const layout = printLayout(readSong(notes, { home: 57, timeSignature: '3/4', pickup: 1 }).template, { names: false });
    const bars = layout.systems[0].bars;
    assert(bars.length === 3 && bars[0].width < bars[1].width, `a pickup bar first: ${bars.map((b) => Math.round(b.width))}`);
    // A minor song in its own key: A minor has no sharps, and its tonic reads la.
    const minor = readSong(notes, { home: 57, key: 'Am' }).template;
    assert(minor.key === 'Am' && printLayout(minor, { names: false }).signature.count === 0 && noteLabel(57, 'solfege', 'Am') === 'la', 'A minor, la-based');
});
