// Songs withheld where the singer is (js/withheld.js, docs/geo-rights-plan.md): the free course read with a withheld list
// sings each withheld song's altSong (its own template, words and exercises), hides what has none, and never lets a
// hidden lesson or boss count or block. The real course is the fixture for the fallbacks the courses lead wrote.
import { emptyProgress, maxStars, nextLesson } from '/js/course.js';
import { keptWithheldList, mayFallBack, notHereLine, withheldSongs, withholdCourse } from '/js/withheld.js';
import { loadLearning, rawLesson } from '/js/stores/learning.js';
import { local } from '/js/storage.js';
import { assert, test } from './harness.js';

const course = () => fetch('/course/course.json').then((r) => r.json());
const lessonOf = (c, id) => c.units.flatMap((u) => [...u.lessons, ...(u.boss ? [u.boss] : [])]).find((l) => l.id === id);

test('withheld: with nothing withheld every course is the same object, never copied', async () => {
    const c = await course();
    assert(withholdCourse(c, []) === c && withholdCourse(c, new Set()) === c, 'an empty list');
    assert(withholdCourse(c, ['a-song-no-course-sings']) === c, 'a list naming nothing in it');
});

test("withheld: Are You Sleeping withheld, 5-round sings Hey Ho Nobody Home's lines and exercises, and tells Hey Ho's fact", async () => {
    const c = await course();
    const before = lessonOf(c, '5-round');
    const out = withholdCourse(c, ['are-you-sleeping']);
    const round = lessonOf(out, '5-round');
    assert(round.steps.length === before.steps.length, `${round.steps.length} steps, ${before.steps.length} before`);
    const step = round.steps[1];
    assert(step.template === 'hey-ho-nobody-home', `step 1 sings ${step.template}`);
    assert(step.text.en === before.steps[1].altSong.text.en && /Hey, Ho/.test(step.text.en), `its words: ${step.text.en}`);
    assert(step.text.es && step.text.de, 'in Spanish and German too');
    const inline = round.steps[2];
    assert(inline.from === 'hey-ho-nobody-home', `the inline step's notes come from ${inline.from}`);
    assert(JSON.stringify(inline.exercise) === JSON.stringify(before.steps[2].altSong.exercise), "the inline step's own exercise is Hey Ho's");
    assert(round.fact?.id === 'heyho-burning-pestle-1613', `the lesson's fact is ${round.fact?.id}`);
    assert(JSON.stringify(round.title) === JSON.stringify(before.title) || round.title === before.altSong?.title, "the title is the lesson's or its altSong's");
    // The rest of unit 5 follows, the boss's song inside its exercise.
    assert(lessonOf(out, '5-challenge').steps[0].template === 'hey-ho-nobody-home', '5-challenge');
    for (const step of lessonOf(out, '5-boss').steps.filter((s) => s.exercise?.song)) {
        assert(step.exercise.song.template === 'hey-ho-nobody-home', `5-boss sings ${step.exercise.song.template}`);
    }
    // Nothing anywhere still sings it.
    const left = JSON.stringify(out.units.map((u) => ({ lessons: u.lessons.map((l) => l.steps.map(({ altSong, ...s }) => s)), boss: u.boss?.steps.map(({ altSong, ...s }) => s), song: u.song })));
    assert(!left.includes('"are-you-sleeping"'), 'no step still names are-you-sleeping');
    // And the course as it came is untouched.
    assert(lessonOf(c, '5-round').steps[1].template === 'are-you-sleeping', 'the original course is not changed');
});

test("withheld: an exercise song's altSong may carry its step's words (8-boss names Danny Boy)", async () => {
    const c = await course();
    const before = lessonOf(c, '8-boss');
    const out = lessonOf(withholdCourse(c, ['danny-boy']), '8-boss');
    const i = before.steps.findIndex((s) => s.exercise?.song?.altSong?.text);
    assert(i >= 0, 'the fixture has a song altSong with text');
    assert(out.steps[i].exercise.song.template === 'annie-laurie', `sings ${out.steps[i].exercise.song.template}`);
    assert(out.steps[i].text.en === before.steps[i].exercise.song.altSong.text.en, `the step's words: ${out.steps[i].text.en}`);
    assert(!('text' in out.steps[i].exercise.song), "the words aren't left inside the song");
    assert(JSON.stringify(out.title) === JSON.stringify(before.altSong.title), "the boss's own title is Annie Laurie's");
});

/** Every step's song, a boss's exercise song and each unit's song, as JSON, without the altSongs themselves. */
function sungSongs(c) {
    const bare = ({ altSong, ...rest }) => (rest.exercise?.song ? { ...rest, exercise: { ...rest.exercise, song: bare(rest.exercise.song) } } : rest);
    return JSON.stringify(c.units.map((u) => ({ lessons: u.lessons.map((l) => l.steps.map(bare)), boss: u.boss?.steps.map(bare), song: u.song ? bare(u.song) : null })));
}

/** The fields of `alt` that `out` must carry, equal. */
function carries(out, alt, fields, what) {
    for (const field of fields) {
        assert(JSON.stringify(out?.[field]) === JSON.stringify(alt[field]), `${what}: ${field} is ${JSON.stringify(out?.[field])?.slice(0, 80)}`);
    }
}

test('withheld: Ode to Joy withheld, unit 3 sings Joy to the World: its song, its challenge and its boss', async () => {
    const c = await course();
    const unit = c.units.find((u) => u.song?.template === 'ode-to-joy');
    assert(unit, 'the fixture changed: no unit sings ode-to-joy');
    const out = withholdCourse(c, ['ode-to-joy']);
    const after = out.units.find((u) => u.id === unit.id);
    assert(after.song.template === 'joy-to-the-world', `the unit song is ${after.song.template}`);
    carries(after.song, unit.song.altSong, ['template', 'title', 'text', 'part', 'syllable', 'game'], 'the unit song');
    const challenge = lessonOf(c, '3-challenge');
    const challengeOut = lessonOf(out, '3-challenge');
    carries(challengeOut, challenge.altSong, ['title', 'fact'], '3-challenge');
    [0, 1, 2].forEach((i) => carries(challengeOut.steps[i], challenge.steps[i].altSong, ['exercise', 'from', 'measures', 'text'], `3-challenge step ${i}`));
    const boss = lessonOf(c, '3-boss');
    const bossOut = lessonOf(out, '3-boss');
    carries(bossOut, boss.altSong, ['title'], '3-boss');
    [0, 1].forEach((i) => carries(bossOut.steps[i], boss.steps[i].altSong, ['exercise', 'from', 'text'], `3-boss step ${i}`));
    assert(!sungSongs(out).includes('"ode-to-joy"'), 'no step or song still names ode-to-joy');
});

test('withheld: Danny Boy withheld, 8-challenge sings Annie Laurie with her title and fact', async () => {
    const c = await course();
    const before = lessonOf(c, '8-challenge');
    const out = withholdCourse(c, ['danny-boy']);
    const after = lessonOf(out, '8-challenge');
    carries(after, before.altSong, ['title', 'fact'], '8-challenge');
    assert(after.steps[0].template === 'annie-laurie', `step 0 sings ${after.steps[0].template}`);
    carries(after.steps[0], before.steps[0].altSong, ['template', 'part', 'measures', 'singers', 'text'], '8-challenge step 0');
    assert(!sungSongs(out).includes('"danny-boy"'), 'no step or song still names danny-boy');
});

test('withheld: a step with no altSong is hidden, a lesson left empty is skipped, a hidden boss never blocks its unit', () => {
    const steps = (template, alt) => [{ kind: 'song', template, text: { en: template }, ...(alt ? { altSong: { kind: 'song', template: alt, text: { en: alt } } } : {}) }];
    const c = {
        units: [
            {
                id: 'u1',
                lessons: [
                    { id: 'l1', steps: [{ kind: 'learn', text: { en: 'hello' } }, ...steps('gone')] },
                    { id: 'l2', steps: steps('gone') },
                    { id: 'l3', steps: steps('gone', 'kept') },
                ],
                boss: { id: 'u1-boss', steps: steps('gone') },
                song: { template: 'gone', part: 'melody' },
            },
            { id: 'u2', lessons: [{ id: 'l4', steps: steps('fine') }] },
        ],
    };
    const out = withholdCourse(c, ['gone']);
    const u1 = out.units[0];
    assert(u1.lessons.map((l) => l.id).join() === 'l1,l3', `lessons ${u1.lessons.map((l) => l.id)}`);
    assert(u1.lessons[0].steps.length === 1 && u1.lessons[0].steps[0].kind === 'learn', 'l1 keeps its other step');
    assert(u1.lessons[1].steps[0].template === 'kept', 'l3 sings its altSong');
    assert(!('boss' in u1) && !('song' in u1), 'the boss and the unit song are hidden');
    assert(maxStars(u1) === 6, `the unit gives ${maxStars(u1)} stars, from its shown lessons`);
    // Three stars on both shown lessons: the next lesson is in unit 2, never the hidden l2.
    const progress = emptyProgress();
    progress.lessons = { l1: { stars: 3 }, l3: { stars: 3 } };
    assert(nextLesson(out.units, progress)?.lesson.id === 'l4', `next is ${nextLesson(out.units, progress)?.lesson.id}`);
    assert(out.units[1] === c.units[1], 'a unit with nothing withheld is the same object');
});

test('withheld: the list is read from auth.config, strings only, and a 451 is a calm line with its year', () => {
    assert([...withheldSongs({ withheldSongs: ['a', 3, 'b'] })].join() === 'a,b', 'strings only');
    assert(withheldSongs({ withheldSongs: [] }).size === 0, 'empty while the switch is off');
    const refusal = (freeFrom) => Object.assign(new Error('451'), { code: 'not_available_here', status: 451, body: { code: 'not_available_here', message: '', freeFrom } });
    const year = notHereLine(refusal(2031));
    assert(year && year.includes('2031'), `with a year: ${year}`);
    const none = notHereLine(refusal(null));
    assert(none && !/\d{4}/.test(none), `without one: ${none}`);
    assert(notHereLine({ code: 'offline' }) === null && notHereLine(null) === null, 'any other failure is not this');
});

test("withheld: a first visit waits for the list only on a lesson that could swap a song; lesson 1 never does", async () => {
    await loadLearning();
    const one = rawLesson('1-comfortable');
    assert(one && !mayFallBack(one), 'lesson 1 has no altSong: it never waits');
    assert(mayFallBack(rawLesson('5-round')), '5-round could sing Hey Ho instead');
    assert(mayFallBack(rawLesson('3-challenge')) && mayFallBack(rawLesson('3-boss')), "unit 3's challenge and its boss");
    const c = await course();
    const unitOne = c.units[0].lessons.map((l) => l.id);
    assert(unitOne.every((id) => !mayFallBack(rawLesson(id))), `a unit 1 lesson would wait: ${unitOne.filter((id) => mayFallBack(rawLesson(id)))}`);
    assert(!mayFallBack(null), 'no lesson');
    // A list kept from a visit before (an empty one too) means the list is known: no wait.
    const before = local.get('stackapella.withheldSongs');
    try {
        local.remove('stackapella.withheldSongs');
        assert(!keptWithheldList(), 'nothing kept');
        local.set('stackapella.withheldSongs', '[]');
        assert(keptWithheldList(), 'an empty list kept');
    } finally {
        if (before === null) local.remove('stackapella.withheldSongs');
        else local.set('stackapella.withheldSongs', before);
    }
});
