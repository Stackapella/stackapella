// Your part (js/course-parts.js; docs/courses/choir-part-sections-plan.md): a course with `parts` reads
// its lessons for the singer's part ("yours" in song specs and record steps, byPart and textByPart for
// the words, a kept take per part), the pick comes from progress, else the range, else the default, and
// a course without parts is never touched.
import { coursePart, keepIdFor, lessonForPart, partPicked, partsOf } from '/js/course-parts.js';
import { emptyProgress } from '/js/course.js';
import { keptPartOf } from '/js/components/then-and-now.js';
import { assert, test } from './harness.js';

const PARTS = { template: 'o-come-all-ye-faithful', choose: ['soprano', 'alto', 'tenor', 'bass'], default: 'alto' };
const en = (text) => ({ en: text });
const song = (phrases) => ({ template: 'o-come-all-ye-faithful', part: 'yours', phrases });

const lesson = () => ({
    id: 'chp-1-hear',
    steps: [
        {
            kind: 'learn',
            headline: en('The alto on its own'),
            steps: [{ title: en('Its shape'), detail: en('Low, then it climbs') }],
            listen: { song: song([0, 1]), turn: false },
            byPart: { tenor: { headline: en('The tenor on its own'), steps: [{ title: en('Bethlehem'), detail: en('A long held note') }] } },
        },
        { kind: 'try', text: en('Find the notes of line 1.'), textByPart: { bass: en('Find the bass notes of line 1.') }, exercise: { song: song([0]) }, measures: [1, 4] },
        { kind: 'play', text: en('Line 1, kept.'), exercise: { song: song([0]), keep: 'choir-part.first' } },
        { kind: 'play', text: en('Then and now.'), exercise: { song: song([0, 1]), thenAndNow: 'choir-part.first' } },
        { kind: 'record', template: 'o-come-all-ye-faithful', part: 'yours', singers: 4 },
        { kind: 'wave', text: en('Hum.'), exercise: { tempo: 60 } },
    ],
});
const course = (parts = PARTS) => ({ id: 'choir-part', ...(parts ? { parts } : {}), units: [] });
const withPick = (part) => ({ ...emptyProgress(), courses: { 'choir-part': { personal: { part } } } });

test('course parts: "yours" becomes the pick in a listen, an exercise and a record step', () => {
    const read = lessonForPart(lesson(), course(), 'tenor');
    assert(read.steps[0].listen.song.part === 'tenor', 'a learn card\'s example');
    assert(read.steps[1].exercise.song.part === 'tenor' && read.steps[2].exercise.song.part === 'tenor', 'a try and a play');
    assert(read.steps[4].part === 'tenor' && read.steps[4].singers === 4, 'the record step, the rest kept');
    assert(read.steps[0].listen.turn === false && read.steps[0].listen.song.phrases.join() === '0,1', 'everything else as written');
});

test('course parts: byPart replaces the headline and every card, textByPart only the text', () => {
    const tenor = lessonForPart(lesson(), course(), 'tenor');
    assert(tenor.steps[0].headline.en === 'The tenor on its own' && tenor.steps[0].steps.length === 1 && tenor.steps[0].steps[0].title.en === 'Bethlehem', 'the tenor\'s words, never merged');
    assert(tenor.steps[1].text.en === 'Find the notes of line 1.', 'no bass text for the tenor: the step\'s own');
    const bass = lessonForPart(lesson(), course(), 'bass');
    assert(bass.steps[1].text.en === 'Find the bass notes of line 1.' && bass.steps[1].measures.join() === '1,4', 'the bass\'s text, its measures unchanged');
    assert(bass.steps[0].headline.en === 'The alto on its own', 'no bass byPart: the step\'s own (the alto\'s) words');
    const alto = lessonForPart(lesson(), course(), 'alto');
    assert(alto.steps[0].headline.en === 'The alto on its own' && alto.steps[1].text.en === 'Find the notes of line 1.', 'the default part reads the step\'s own words');
});

test('course parts: a kept take is kept per part, the default keeping the plain id', () => {
    const alto = lessonForPart(lesson(), course(), 'alto');
    assert(alto.steps[2].exercise.keep === 'choir-part.first' && alto.steps[3].exercise.thenAndNow === 'choir-part.first', 'the alto keeps the takes kept before parts');
    const soprano = lessonForPart(lesson(), course(), 'soprano');
    assert(soprano.steps[2].exercise.keep === 'choir-part.first:soprano' && soprano.steps[3].exercise.thenAndNow === 'choir-part.first:soprano', 'the soprano has her own');
    const others = soprano.steps[3].exercise.thenAndNowOthers;
    assert(others.map((o) => o.part).join() === 'alto,tenor,bass' && others[0].id === 'choir-part.first' && others[1].id === 'choir-part.first:tenor', 'the other parts\' takes, in score order, to say which lesson 1 was');
    assert(keepIdFor('x', 'bass', null) === 'x' && keepIdFor('', 'bass', PARTS) === '', 'no parts, or no id: unchanged');
});

test('course parts: a course without parts is untouched, and nothing is copied twice', () => {
    const plain = lesson();
    assert(lessonForPart(plain, course(null), 'tenor') === plain, 'no parts: the same lesson');
    assert(lessonForPart(plain, course(), 'baritone') === plain, 'a part the course doesn\'t offer: the same lesson');
    assert(partsOf(course({ choose: ['alto'], default: 'tenor' })) === null, 'a default outside choose is no parts at all');
    const once = lessonForPart(plain, course(), 'tenor');
    assert(lessonForPart(plain, course(), 'tenor') === once, 'the same part: the same object (Vue\'s caches hold)');
    assert(lessonForPart(plain, course(), 'bass') !== once, 'another part: read again');
    assert(plain.steps[0].listen.song.part === 'yours' && plain.steps[0].headline.en === 'The alto on its own', 'the course file itself never changes');
});

test('course parts: the pick, else the range\'s voice, else the default', () => {
    assert(coursePart(course(), withPick('bass'), { low: 60, high: 81 }) === 'bass', 'a pick wins over the range');
    assert(partPicked(course(), withPick('bass')) && !partPicked(course(), emptyProgress()), 'picked, or not');
    assert(coursePart(course(), emptyProgress(), { low: 60, high: 81 }) === 'soprano', 'a soprano\'s range suggests the soprano');
    assert(coursePart(course(), emptyProgress(), { low: 40, high: 64 }) === 'bass', 'a bass\'s range, the bass');
    assert(coursePart(course(), emptyProgress(), null) === 'alto', 'no range: the default');
    assert(coursePart(course(), withPick('baritone'), null) === 'alto', 'a pick the course doesn\'t offer: as if none');
    assert(coursePart(course(null), withPick('bass'), null) === null, 'a course without parts has no part');
});

test('course parts: a finale with only another part\'s take names that part, never compares across', async () => {
    const kept = { 'course:choir-part.first': { sampleRate: 48000, at: 1 }, 'course:choir-part.first:tenor': { sampleRate: 48000, at: 2 } };
    const store = { get: async (_, key) => kept[key] ?? null, getAudio: async (key) => (kept[key] ? new Float32Array(8) : null) };
    const soprano = lessonForPart(lesson(), course(), 'soprano').steps[3].exercise.thenAndNowOthers;
    assert((await keptPartOf(soprano, store)) === 'alto', 'the alto\'s take (the plain id) is found first, in score order');
    const bass = lessonForPart(lesson(), course(), 'bass').steps[3].exercise.thenAndNowOthers;
    assert((await keptPartOf(bass.filter((o) => o.part !== 'alto'), store)) === 'tenor', 'else the tenor\'s');
    assert((await keptPartOf([{ part: 'bass', id: 'choir-part.first:bass' }], store)) === null, 'none kept: nothing to say');
});
