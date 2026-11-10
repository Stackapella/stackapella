// Song courses (js/song-course.js): a song's phrases become the course's own exercise, the notes from
// the song itself (steps from its tonic), the guide or the other parts as partner lines, its chords
// under you otherwise, and the key Sing would choose for your range.
import { isSongLesson, songExercise, songFit, songOf, songTranspose } from '/js/song-course.js';
import { lessonAccess, nextInCourse, songCourseVisible } from '/js/courses.js';
import { emptyProgress } from '/js/course.js';
import { loadTemplate } from '/js/templates.js';
import { assert, test } from './harness.js';

const grace = () => loadTemplate('amazing-grace');
const G4 = 67; // Amazing Grace is in G: its steps are from G4

test('song course: a line is the part\'s own notes, from beat 0, as steps from the song\'s tonic', async () => {
    const template = await grace();
    const { exercise, tonic, minor } = songExercise(template, { song: { template: 'amazing-grace', part: 'soprano', phrases: [0] }, turn: true });
    assert(tonic === G4 && minor === false, `the tonic is G4 (${tonic})`);
    const melody = template.parts.find((p) => p.key === 'soprano').guide.notes.filter((n) => n.beat < 12);
    assert(exercise.notes.length === melody.length, 'every note of line 1, and no more');
    assert(exercise.notes[0].beat === 0 && exercise.notes[0].step === 62 - G4 && exercise.notes[0].lyric === 'A-', 'its pickup, "A-" on D4');
    assert(exercise.tempo === template.tempo && exercise.turn === true && !exercise.song, 'the song\'s tempo, the step\'s turn, and never the raw song');
    assert(!exercise.with, 'hear it and sing it back: no partner lines');
    assert(exercise.chords?.[0].beat === 0 && exercise.chords[0].root === 0, 'the chords from the start, G as the tonic (0)');
});

test('song course: a step may slow the song down (a line read from the stave); else the song\'s own tempo', async () => {
    const template = await grace();
    const own = songExercise(template, { song: { template: 'amazing-grace', part: 'soprano', phrases: [0] }, turn: false }).exercise;
    const slower = songExercise(template, { song: { template: 'amazing-grace', part: 'soprano', phrases: [0] }, turn: false, tempo: 66 }).exercise;
    assert(own.tempo === template.tempo && slower.tempo === 66, `the song's ${own.tempo}, the step's ${slower.tempo}`);
    assert(slower.notes.length === own.notes.length && slower.notes[2].beat === own.notes[2].beat, 'the same notes, in beats');
});

test('song course: a later line starts at 0 and keeps the chord already sounding', async () => {
    const { exercise } = songExercise(await grace(), { song: { template: 'amazing-grace', part: 'soprano', phrases: [1] }, turn: true });
    assert(exercise.notes[0].beat === 0 && exercise.notes[0].lyric === 'that', 'line 2 from its pickup');
    assert(exercise.chords[0].beat === 0, 'a chord at 0, carried over from line 1');
    assert(exercise.notes.every((n) => n.beat < 12), 'only line 2');
});

test('song course: sung with the guide, your own line sounds with you; guide off or in an example, it doesn\'t', async () => {
    const template = await grace();
    const spec = { song: { template: 'amazing-grace', part: 'alto', phrases: [0, 1, 2, 3] } };
    const along = songExercise(template, { ...spec, turn: false }).exercise;
    assert(along.with?.length === 1 && along.with[0].delay === 0, 'the guide, as your line at delay 0');
    const alone = songExercise(template, { ...spec, turn: false, guide: false }).exercise;
    assert(!alone.with && alone.guide === false, 'guide: false: no guide line');
    const example = songExercise(template, { ...spec, turn: false }, { listen: true }).exercise;
    assert(!example.with, 'a learn card\'s example plays the part once, not twice');
});

test('song course: with the others, every other part is a partner line and no chords play', async () => {
    const template = await grace();
    const { exercise } = songExercise(template, { song: { template: 'amazing-grace', part: 'tenor', phrases: [0, 1, 2, 3], with: 'others' }, turn: false, guide: false });
    assert(exercise.with.length === 3 && exercise.with.every((w) => w.notes.length), 'soprano, alto and bass');
    assert(!exercise.chords, 'the parts are the harmony');
    const bass = template.parts.find((p) => p.key === 'bass').guide.notes[0];
    assert(exercise.with.some((w) => w.notes[0].step === bass.pitch - G4), 'the bass, as steps from the tonic too');
});

test('song course: a part that isn\'t there, or phrases it hasn\'t, fail loudly', async () => {
    const template = await grace();
    let threw = 0;
    for (const song of [{ part: 'mezzo', phrases: [0] }, { part: 'soprano', phrases: [9] }]) {
        try {
            songExercise(template, { song: { template: 'amazing-grace', ...song }, turn: true });
        } catch {
            threw++;
        }
    }
    assert(threw === 2, 'both refused');
});

test('song course: the key is Sing\'s for your range; none known is the song\'s own', async () => {
    const template = await grace(); // the melody: D4 to D5
    assert(songTranspose(template, 'soprano', null) === 0, 'no range yet: as written');
    assert(songTranspose(template, 'soprano', { low: 60, high: 76 }) === 0, 'it fits: as written');
    assert(songTranspose(template, 'soprano', { low: 48, high: 64 }) === 0, 'a lower voice: the same key, an octave down (scoring hears any octave)');
    const narrow = songTranspose(template, 'soprano', { low: 64, high: 70 });
    assert(Number.isInteger(narrow) && Math.abs(narrow) <= 7, `a range it can't fit: the closest key (${narrow})`);
    assert(songTranspose(template, 'soprano', { low: 65, high: 79 }) === 3, 'a higher voice: up three, to Bb');
});

test('song course: lessons and steps are known by their song', () => {
    assert(isSongLesson('song-amazing-grace-soprano-1') && !isSongLesson('vib-a'), 'by the song- prefix');
    assert(songOf({ kind: 'play', exercise: { song: { template: 'x' } } })?.template === 'x', 'an exercise\'s song');
    assert(songOf({ kind: 'learn', listen: { song: { template: 'y' } } })?.template === 'y', 'a learn card\'s example');
    assert(songOf({ kind: 'play', exercise: { notes: [] } }) === null, 'an ordinary step has none');
});

const songCourse = {
    id: 'song-amazing-grace',
    units: [
        { id: 'song-amazing-grace-soprano', lessons: [{ id: 'song-amazing-grace-soprano-1' }, { id: 'song-amazing-grace-soprano-2' }] },
        { id: 'song-amazing-grace-alto', lessons: [{ id: 'song-amazing-grace-alto-1' }, { id: 'song-amazing-grace-alto-2' }] },
    ],
};

test('song course: every part\'s unit is open from the start, and no lesson needs an account (the song is the gate)', () => {
    const access = lessonAccess(songCourse, 'song-amazing-grace-alto-2', emptyProgress(), { signedIn: false, staff: false });
    assert(access.open && !access.needsAccount, 'the alto\'s second lesson, with nothing sung, signed out');
});

test('song course: Up next follows the part picked on the song\'s sheet, else the melody first', () => {
    assert(nextInCourse(songCourse, emptyProgress()).lesson.id === 'song-amazing-grace-soprano-1', 'the melody first');
    assert(nextInCourse(songCourse, emptyProgress(), 'song-amazing-grace-alto').lesson.id === 'song-amazing-grace-alto-1', 'the alto, picked');
});

test('song course: staff see one being reviewed; everyone sees one that\'s live', () => {
    assert(!songCourseVisible({ status: 'staff' }, null) && !songCourseVisible({ status: 'staff' }, { staff: false }), 'reviewed: not for everyone');
    assert(songCourseVisible({ status: 'staff' }, { staff: true }), 'reviewed: staff');
    assert(songCourseVisible({ status: 'live' }, null), 'live: everyone, signed out too');
    assert(!songCourseVisible(null, { staff: true }), 'no course: nothing');
});

test('song course: a song step opens in the key that fits, else from the comfortable note (docs/song-keys-plan.md)', () => {
    // A part from D4 to D5 (Amazing Grace's tune), its notes around A4.
    const notes = [{ beat: 0, length: 1, pitch: 62 }, { beat: 1, length: 2, pitch: 67 }, { beat: 3, length: 2, pitch: 71 }, { beat: 5, length: 1, pitch: 74 }];
    const song = { parts: [{ key: 'soprano', range: { low: 62, high: 74 }, guide: { notes } }] };
    // A range that fits it as written: no move.
    const fits = songFit(song, 'soprano', { low: 60, high: 76 });
    assert(fits.transpose === 0 && fits.octave === 0 && fits.fits, JSON.stringify(fits));
    // A man's range: the same key, an octave lower (no transposing).
    const low = songFit(song, 'soprano', { low: 48, high: 64 });
    assert(low.octave === -12 && low.transpose === 0 && low.fits, JSON.stringify(low));
    // A range a little low: the closest key in its own octave.
    const lower = songFit(song, 'soprano', { low: 57, high: 70 });
    assert(lower.transpose < 0 && lower.transpose >= -7, JSON.stringify(lower));
    // Only the comfortable note: the part inside a beginner's range around it (a fourth below to a sixth above).
    for (const home of [57, 50, 43, 64]) {
        const fit = songFit(song, 'soprano', null, home);
        assert(fit && Math.abs(fit.transpose) <= 7, `home ${home}: ${JSON.stringify(fit)}`);
        const low = 62 + fit.transpose + fit.octave;
        const high = 74 + fit.transpose + fit.octave;
        assert(low >= home - 5 && high <= home + 9, `home ${home}: the part from ${low} to ${high}`);
    }
    // Nothing known: no fit, and the song stays as written.
    assert(songFit(song, 'soprano', null, null) === null && songTranspose(song, 'soprano', null) === 0, 'nothing known');
});
