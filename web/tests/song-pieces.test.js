// Song pieces in lessons (js/song-pieces.js, docs/song-snippets-plan.md): a real song's line at a lesson's end. Its switch
// off, the course is read without it; it never sets the stars or starts Sharpen up (lastScoredAt passes over it); where
// its song is withheld, the lesson ends cleanly without it (Hillel, 2026-11-26: pieces may use songs withheld somewhere).
import { lastScoredAt } from '/js/lesson-steps.js';
import { sharpenStart } from '/js/sharpen.js';
import { songPieces } from '/js/song-pieces.js';
import { withholdCourse } from '/js/withheld.js';
import { assert, test } from './harness.js';

const drill = { kind: 'play', text: { en: 'Sing the jumps.' }, exercise: { tempo: 72, notes: [{ beat: 0, length: 1, step: 0 }], turn: true } };
const piece = { kind: 'play', songPiece: true, text: { en: 'Now in a real song.' }, exercise: { song: { template: 'row-row-row', part: 'soprano', phrases: [3] } } };
const course = () => ({ id: 'free', units: [{ lessons: [{ id: '3-steps-down', steps: [{ kind: 'learn' }, drill, piece] }, { id: '3-jumps', steps: [drill] }] }] });

test('song pieces: off, the course is read without them; on, it is the same object', () => {
    const c = course();
    assert(songPieces(c, true) === c, 'on: unchanged');
    const off = songPieces(c, false);
    assert(off !== c && off.units[0].lessons[0].steps.length === 2 && !off.units[0].lessons[0].steps.some((s) => s.songPiece), 'off: the piece gone');
    assert(off.units[0].lessons[1] === c.units[0].lessons[1], 'a lesson without one is the same object');
    const plain = { id: 'x', units: [{ lessons: [{ id: 'a', steps: [drill] }] }] };
    assert(songPieces(plain, false) === plain, 'nothing to drop: never copied');
});

test('song pieces: the stars and Sharpen up come from the drill, never the song piece after it', () => {
    const steps = [{ kind: 'learn' }, drill, piece];
    assert(lastScoredAt(steps) === 1, `last scored: ${lastScoredAt(steps)}`);
    assert(sharpenStart({ steps }) === 1, `Sharpen up starts: ${sharpenStart({ steps })}`);
    assert(lastScoredAt([{ kind: 'learn' }, piece]) === -1, 'a piece alone sets no stars');
});

test('song pieces: where the song is withheld, the lesson ends cleanly without the piece', () => {
    const lesson = withholdCourse(songPieces(course(), true), ['row-row-row']).units[0].lessons[0];
    assert(lesson && lesson.steps.length === 2 && lesson.steps.at(-1) === drill, JSON.stringify(lesson?.steps.map((s) => s.kind)));
    assert(lastScoredAt(lesson.steps) === 1, 'the drill still sets the stars');
});
