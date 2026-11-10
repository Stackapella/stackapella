// The scoring guard (agreed with the courses lead, 2026-11-08, after the scoring audit): every course's scored play,
// bullseye and wave steps, laid out and scored as the lesson does (components/exercise.js layout and scorePlayTake,
// bullseye.js scoreEchoTake, wave-lane.js scoreWaveTake), sung by a synthetic singer (fixtures/singer.js). A trusted
// take, sung as the words ask, must earn its stars, and on the measures that judge one thing (a release, an entry, a
// steady hold, a move asked for) the take the words say not to sing must not. Every lesson of every course, every
// landing: it takes seconds.
import { echoPlan, scoreEchoTake } from '/js/components/bullseye.js';
import { layout, scorePlayTake } from '/js/components/exercise.js';
import { scoreWaveTake } from '/js/components/wave-lane.js';
import { lessonForPart, partsOf } from '/js/course-parts.js';
import { starsFor, strictnessFor } from '/js/course.js';
import { songExercise } from '/js/song-course.js';
import { loadTemplate, validateTemplate } from '/js/templates.js';
import { targetAt, targetPlan } from '/js/vibrato.js';
import { NATURAL, RATE, sing } from './fixtures/singer.js';
import { test } from './harness.js';

const BASE = 57; // the comfortable note the guard sings from (A3); scoring is relative to it

const ease = (x) => (x <= 0 ? 0 : x >= 1 ? 1 : 0.5 - 0.5 * Math.cos(Math.PI * x));
const easeOut = (x) => (x <= 0 ? 0 : x >= 1 ? 1 : 1 - (1 - x) ** 2);
const shake = (tIn) => 60 * Math.sin(2 * Math.PI * 3 * tIn); // a 3 Hz ±60 shake: a wobble, not a vibrato
const natural = (tIn) => (tIn > NATURAL.after ? NATURAL.cents * Math.sin(2 * Math.PI * NATURAL.hz * (tIn - NATURAL.after)) : 0);

/** Every course with its lessons: the free course, then each Plus course with a file. */
async function courses() {
    const json = (url) => fetch(url).then((r) => (r.ok ? r.json() : Promise.reject(new Error(`${url}: ${r.status}`))));
    const list = [{ id: 'free', free: true, course: await json('/course/course.json') }];
    for (const c of (await json('/course/plus/courses.json')).courses) if (c.file) list.push({ id: c.id, free: false, course: await json(`/course/plus/${c.file}`) });
    return list;
}

/** The lessons the guard sings, bosses too. A course with parts (course-parts.js) is sung once for each, as each singer reads it. */
function lessonsOf({ id, free, course }) {
    const out = [];
    const parts = partsOf(course)?.choose ?? [null];
    course.units.forEach((unit, u) => {
        const strictness = (lesson) => lesson.strictness ?? (free ? strictnessFor(u) : 'standard');
        const lessons = unit.lessons ?? [];
        const picked = [...lessons, ...(unit.boss ? [unit.boss] : [])];
        for (const lesson of picked) {
            for (const part of parts) {
                const read = part ? lessonForPart(lesson, course, part) : lesson;
                out.push({ where: `${id}/${lesson.id}${part ? ` (${part})` : ''}`, steps: read.steps ?? [], strictness: strictness(lesson) });
            }
        }
    });
    return out;
}

/**
 * A song's template: a bundled one as the app loads it, a library song as the server keeps it (tool/serve.php's
 * /tests/templates/, since the tests run without a server).
 */
async function templateOf(id) {
    const bundled = (await fetch('/templates/index.json').then((r) => r.json())).templates;
    if (bundled.includes(id)) return loadTemplate(id);
    const response = await fetch(`/tests/templates/${id}.json`);
    if (!response.ok) throw new Error(`no template ${id}`);
    return validateTemplate(await response.json());
}

/** A play step's exercise and the note it's built on: a song step's from its template, as views/learn.js does. */
async function exerciseOf(raw) {
    if (!raw.song) return { exercise: raw, base: BASE };
    const { exercise, tonic } = songExercise(await templateOf(raw.song.template), raw, { listen: false });
    return { exercise, base: tonic };
}

/** The joined-ness of a laid note to the next, in beats. */
const joinedNext = (target, i) => target[i + 1] && Math.abs(target[i].beat + target[i].length - target[i + 1].beat) < 0.01;
/** Notes after a rest or a breath: the entries. */
const entries = (target) => target.map((n, i) => i).filter((i) => i > 0 && (target[i].br || !joinedNext(target, i - 1)));

/**
 * A styled line sung with its moves (Pop and country): each note's `style` as the courses lead set the trusted
 * shapes, or `shapes` instead ({ scoop, slide, fall, cry }: a function per move, or null for none).
 */
/** Cents a note sits above the joined note before it (null after a rest, or when that note is higher). */
const fromBelow = (target, i) => (i > 0 && joinedNext(target, i - 1) && target[i - 1].pitch < target[i].pitch ? (target[i].pitch - target[i - 1].pitch) * 100 : null);

function styledTake(target, bs, shapes) {
    const styleOf = (i) => target[i].style ?? null;
    const per = (n, i, tIn, dur, sounding) => {
        let cents = 0;
        const own = styleOf(i);
        if (own && shapes[own]) cents += shapes[own].on?.(tIn, dur, sounding, fromBelow(target, i)) ?? 0;
        // A slide from the note before carries on into this one.
        const before = i > 0 ? styleOf(i - 1) : null;
        if (before === 'slide' && shapes.slide && joinedNext(target, i - 1)) cents += shapes.slide.into?.(tIn, target[i - 1].pitch - n.pitch) ?? 0;
        if (own === 'slide' && shapes.slide && joinedNext(target, i)) cents += shapes.slide.from?.(tIn, dur, target[i + 1].pitch - n.pitch) ?? 0;
        return cents;
    };
    const glideOf = (i) => (styleOf(i) === 'scoop' && shapes.scoop) || (i > 0 && styleOf(i - 1) === 'slide' && shapes.slide) ? 0 : undefined;
    return { per, glideOf };
}

// The trusted shapes (the courses lead): a scoop 300 cents up over 0.35 s; a slide eased over 0.25 s, leaving 0.1 s
// before the next note; a fall 300 cents over the voice's last 0.3 s; a cry as the style audit sang it.
const SLIDE = { lead: 0.1, dur: 0.25 };
const TRUSTED = {
    // From 300 below, or (after a joined note below) from 100 under that note, at most 450, as the guide does
    // (style-scores.js styleBend): a scoop from the note before itself is only legato.
    scoop: { on: (tIn, dur, sounding, below) => (tIn < 0.35 ? -Math.min(450, Math.max(300, (below ?? 0) + 100)) * (1 - easeOut(tIn / 0.35)) : 0) },
    slide: {
        from: (tIn, dur, step) => (tIn > dur - SLIDE.lead ? step * 100 * ease((tIn - (dur - SLIDE.lead)) / SLIDE.dur) : 0),
        into: (tIn, step) => (tIn < SLIDE.dur - SLIDE.lead ? step * 100 * (1 - ease((tIn + SLIDE.lead) / SLIDE.dur)) : 0),
    },
    fall: { on: (tIn, dur, sounding) => (tIn > sounding - 0.3 ? -300 * ease((tIn - (sounding - 0.3)) / 0.3) : 0) },
    cry: {
        on: (tIn, dur) => {
            const a = dur * 0.4;
            const k = tIn < a ? 0 : tIn < a + 0.03 ? (tIn - a) / 0.03 : tIn < a + 0.12 ? 1 : tIn < a + 0.15 ? 1 - (tIn - a - 0.12) / 0.03 : 0;
            return 250 * k;
        },
    },
};
const PLAIN = { scoop: null, slide: null, fall: null, cry: null };

/**
 * The takes for a step: `a` must earn three stars (or, on measures the lead didn't set a bar for, a score at all), `b`
 * must not earn three. Each is { name, o } (the singer's options); a `b` with `most` must score no more than that.
 */
function takesFor(exercise, target, bs) {
    const kind = exercise.score ?? (exercise.rhythm ? 'rhythm' : 'default');
    const choices = target.some((n) => n.choices);
    // A note with choices sung as any one of them, chosen at random (the same each run): each take its own draw, as
    // sing() asks each note once, in order.
    const chooser = (start) => {
        let seed = start;
        const pick = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
        return (n) => (n.choices ? n.choices[Math.floor(pick() * n.choices.length)] + (n.pitch % 1) : n.pitch);
    };
    let takes = 0;
    const pitchOf = choices ? { get next() { takes++; return chooser(11 + 7 * takes); } } : null;
    const a = [{ name: 'as asked', o: { pitchOf: pitchOf?.next } }];
    const b = [];
    const vibratoOk = !['straight', 'ring', 'partring'].includes(kind);
    if (vibratoOk) a.push({ name: 'with a natural vibrato', o: { pitchOf: pitchOf?.next, vib: NATURAL } });
    if (kind === 'default' || kind === 'rhythm') a.push({ name: '100 ms behind', o: { pitchOf: pitchOf?.next, lag: 0.1 } });
    if (choices && target.some((n) => n.choices && !n.choices.includes(Math.round(n.pitch)))) {
        b.push({ name: 'the tune\'s own note where it isn\'t a choice', o: {} });
    }
    switch (kind) {
        case 'release': {
            b.push({ name: 'let go 0.4 s early', o: { release: 0.4 } }, { name: 'held 0.4 s over', o: { release: -0.4 } });
            break;
        }
        case 'entry': {
            // Every entry: one among many moves the average only a little.
            const back = entries(target);
            if (back.length) {
                b.push({ name: 'never back in after a breath', o: { silent: back } });
                b.push({ name: 'back in a beat late', o: { delay: (i) => (back.includes(i) ? bs : 0) } });
            }
            break;
        }
        case 'hold':
        case 'lock': {
            a.push({ name: 'an octave down', o: { octave: -1 } });
            if (!target.some((n) => n.length * bs >= 0.8)) break; // nothing held long enough to shake
            b.push({ name: 'a 3 Hz shake', o: { per: (n, i, tIn, dur) => (dur >= 0.8 ? shake(tIn) : 0) } });
            // Half a hold shaken is judged only where most notes are holds (0.95 s or more: lock's held() needs 0.5 s after its trims).
            if (target.filter((n) => n.length * bs >= 0.95).length * 2 >= target.length) b.push({ name: 'a natural vibrato, then a shake', o: { per: (n, i, tIn, dur) => (dur >= 0.8 ? (tIn < dur / 2 ? natural(tIn) : shake(tIn)) : 0) } });
            break;
        }
        case 'flipFinder': {
            a.push({ name: 'a blended siren', o: { glide: 10 } });
            break;
        }
        case 'legato': {
            // Joined notes: 100 ms of silence before every change breaks every join (the courses lead's warned-off take).
            b.push({ name: '100 ms gaps at every join', o: { gap: 0.1 } });
            break;
        }
        case 'rhythm': {
            // The tracker's onset noise (25 ms, the courses lead's timing simulation), on time and 80 ms behind, ten
            // singers each: a good take must never fall under three stars on the noise alone (sc-1-swing[3] did, 2026-11-08).
            for (let seed = 1; seed <= 10; seed++) {
                a.push({ name: `on time, 25 ms onset noise (seed ${seed})`, o: { lag: 0, onsetJitter: 0.025, seed } });
                a.push({ name: `80 ms behind, 25 ms onset noise (seed ${seed})`, o: { lag: 0.08, onsetJitter: 0.025, seed } });
            }
            // The first note on time, then 180 ms late and early by turns: uneven however few the notes (a steady lean is
            // forgiven, as it should be: two of three notes 180 ms early is a singer early, not uneven).
            b.push({ name: 'uneven, 180 ms either way', o: { delay: (i) => (i === 0 ? 0 : i % 2 ? 0.18 : -0.18) } });
            break;
        }
        case 'styled': {
            // Your own way (no move asked): pitch and any one move heard. A plain take with a natural vibrato makes no
            // move, so it stays at half (a glide down plus the vibrato's dip once read as a fall: the courses lead).
            if (!target.some((n) => n.style)) {
                b.push({ name: 'plain, with a natural vibrato: no move', o: { vib: NATURAL }, most: 50 });
                break;
            }
            a.length = 0;
            a.push({ name: 'the moves as asked', o: styledTake(target, bs, TRUSTED) });
            a.push({ name: 'the moves, 120 ms behind', o: { ...styledTake(target, bs, TRUSTED), lag: 0.12 } });
            b.push({ name: 'plain legato, no moves', o: styledTake(target, bs, PLAIN) });
            if (target.some((n) => n.style === 'scoop')) {
                // Scoops the words warn against (the courses lead: a 200-cent scoop after a rest is a right one): one that
                // starts on the note before (legato, from a joined note below; 60 cents after a rest), and a flat start too
                // small to be meant (60 cents: "missed, not meant").
                b.push({ name: 'a scoop from the note before', o: styledTake(target, bs, { ...TRUSTED, scoop: { on: (tIn, dur, sounding, below) => (tIn < 0.35 ? -(below ?? 60) * (1 - easeOut(tIn / 0.35)) : 0) } }) });
                b.push({ name: 'a flat start, 60 cents', o: styledTake(target, bs, { ...TRUSTED, scoop: { on: (tIn) => (tIn < 0.35 ? -60 * (1 - easeOut(tIn / 0.35)) : 0) } }) });
                b.push({ name: 'a dip, not a scoop', o: styledTake(target, bs, { ...TRUSTED, scoop: { on: (tIn) => (tIn < 0.3 ? -150 * Math.sin((Math.PI * tIn) / 0.3) : 0) } }) });
            }
            break;
        }
        default:
    }
    // The measures the lead set a bar for: the rest need only score a trusted take. A line with choices under its own
    // measure (species: by the rules, not any choice) has none either.
    const bar = ['default', 'rhythm', 'release', 'entry', 'hold', 'lock', 'flipFinder', 'legato'].includes(kind) || (kind === 'styled' && target.some((n) => n.style));
    return { kind, a, b, bar };
}

/**
 * A whistle step (`whistle`, js/whistle.js), sung by a whistler: every take two octaves up, as whistles sit, and a
 * whistle's natural 80 ms scoop into each note trusted too (`scoop`: a play or bullseye step); a line that slips an
 * octave halfway (`count` notes, two or more) is a take warned against.
 */
export function whistled(check, exercise, { count = 0, scoop = true } = {}) {
    if (!exercise.whistle || !check) return check;
    const up = (take) => ({ ...take, name: `${take.name}, whistled two octaves up`, o: { ...take.o, octave: (take.o.octave ?? 0) + 2 } });
    const a = check.a.map(up);
    if (scoop) a.push(up({ name: 'a whistle\'s 80 ms scoop', o: { glideOf: () => 0, per: (n, i, tIn) => (tIn < 0.08 ? -150 * (1 - tIn / 0.08) : 0) } }));
    const b = check.b.map(up);
    if (count >= 2) b.push(up({ name: 'an octave slip halfway', o: { pitchOf: (n, i) => n.pitch + (i >= count / 2 ? 12 : 0) } }));
    return { ...check, kind: `${check.kind}, whistled`, a, b, bar: check.bar || count >= 2 };
}

/** A play step: laid out and scored as the lesson does, its takes by its measure (takesFor). */
async function playStep(raw, strictness) {
    const built = await exerciseOf(raw);
    const exercise = built.exercise;
    const laid = layout(exercise, built.base);
    const bs = 60 / exercise.tempo;
    const score = (o) => {
        const r = scorePlayTake({ track: sing(laid.target, bs, laid.end * bs + 0.5, o), laid, exercise, beatSeconds: bs, strictness });
        return { percent: r.percent, minStars: r.minStars };
    };
    return whistled({ ...takesFor(exercise, laid.target, bs), score }, exercise, { count: laid.target.length });
}

/**
 * A bullseye step (components/bullseye.js): each answer sung at its note. A trusted take earns three stars, with a
 * natural vibrato and 100 ms behind too; a 200-cent scoop into every answer doesn't (a step sung from memory or cold
 * stars how close a note came, never its start).
 */
function bullseyeStep(exercise) {
    const plan = echoPlan(exercise);
    const bs = 60 / (exercise.tempo ?? 72);
    const notes = plan.answers.map((n) => ({ beat: n.beat, length: n.length, pitch: BASE + n.step }));
    const score = (o) => {
        const percent = scoreEchoTake({ track: sing(notes, bs, plan.end * bs + 0.5, o), plan, exercise, base: BASE }).percent;
        return { percent, minStars: exercise.generous && percent !== null ? 2 : null };
    };
    const a = [{ name: 'as asked', o: {} }, { name: 'with a natural vibrato', o: { vib: NATURAL } }, { name: '100 ms behind', o: { lag: 0.1 } }];
    const fromMemory = exercise.score === 'memory' || exercise.score === 'cold';
    const b = fromMemory ? [] : [{ name: 'a 200-cent scoop into every note', o: { glideOf: () => 0, per: (n, i, tIn) => (tIn < 0.3 ? -200 * (1 - tIn / 0.3) : 0) } }];
    return whistled({ kind: `bullseye/${exercise.score ?? 'landing'}`, a, b, bar: true, score }, exercise, { count: fromMemory ? 0 : notes.length });
}

/**
 * A wave step sung as its lane asks (components/wave-lane.js): the ribbon's own line (targetAt: a wave's sine, a
 * contour), a bloom straight then a 5.5 Hz vibrato, a natural hold with one after 0.4 s; the singer's lag, offset and
 * wander on top, and `extra` cents (seg, tIn) => a shake. A loudness line as the tracker gives (-14 dB sounding, a
 * room's -45 dB hum otherwise): `unread(t)` frames sound but have no reading (a lip trill under the clarity bar), and
 * `stop` ([from, to] seconds) is a stop down to the hum.
 */
function singWave(plan, pitch, { lag = 0.06, offset = -6, jitter = 3, octave = 0, extra = null, unread = null, stop = null } = {}) {
    const hz = new Float32Array(Math.round((plan.end + 0.5) * RATE));
    const loudness = new Float32Array(hz.length).fill(-45);
    for (let f = 0; f < hz.length; f++) {
        const t = f / RATE - lag;
        const target = targetAt(plan, t);
        if (!target || (stop && t >= stop[0] && t < stop[1])) continue;
        loudness[f] = -14;
        if (unread?.(t)) continue;
        const seg = plan.segments.find((x) => t >= x.from && t < x.to);
        const tIn = t - seg.from;
        let cents = target.cents + offset + jitter * Math.sin(t * 37.1);
        if (seg.kind === 'bloom' && tIn > (seg.straight ?? 1.5)) cents += 30 * Math.sin(2 * Math.PI * 5.5 * (tIn - (seg.straight ?? 1.5)));
        if (seg.kind === 'free' && tIn > 0.4) cents += 28 * Math.sin(2 * Math.PI * 5.5 * (tIn - 0.4));
        if (extra) cents += extra(seg, tIn) ?? 0;
        hz[f] = 440 * 2 ** ((pitch + 12 * octave + cents / 100 - 69) / 12);
    }
    return { rate: RATE, hz, loudness };
}

/**
 * A wave step (components/wave-lane.js): the ribbon sung as asked (singWave). A trusted take earns three stars, 100
 * ms behind and an octave down too; a 3 Hz shake doesn't, where a segment is anything but a contour (a contour's band
 * is wide on purpose). A step whose every segment is unscored (`scored: false`: "just listen to how it feels") is left out.
 * One unbroken sound (`unbroken`, Humming and lip trills): a trill the tracker reads only in part, runs of 300 ms
 * unread while it sounds, keeps its bubble; a 300 ms stop in the middle doesn't.
 */
export function waveStep(exercise, strictness) {
    const plan = targetPlan(exercise);
    const scored = plan.segments.filter((seg) => seg.scored !== false);
    if (!scored.length) return null;
    const pitch = BASE + (exercise.step ?? 0);
    const score = (o) => {
        const percent = scoreWaveTake({ track: singWave(plan, pitch, o), plan, pitch, exercise, strictness }).percent;
        return { percent, minStars: exercise.generous && percent !== null ? 2 : null };
    };
    const a = [{ name: 'as asked', o: {} }, { name: '100 ms behind', o: { lag: 0.1 } }, { name: 'an octave down', o: { octave: -1 } }];
    const b = scored.some((seg) => seg.kind !== 'path') ? [{ name: 'a 3 Hz shake', o: { extra: (seg, tIn) => shake(tIn) } }] : [];
    if (exercise.score === 'unbroken') {
        const middle = (scored[0].from + scored[0].to) / 2;
        a.push({ name: 'a trill read in part (300 ms of every 1.2 s unread)', o: { unread: (t) => t % 1.2 >= 0.9 } });
        b.push({ name: 'a 300 ms stop', o: { stop: [middle, middle + 0.3] } });
    }
    return whistled({ kind: `wave/${exercise.score ?? scored.map((seg) => seg.kind).join('+')}`, a, b, bar: true, score }, exercise, { scoop: false });
}

// What the guard found on its first run (2026-11-08), sent to the courses lead to decide: a fix to a scorer or a step
// takes its line out (the guard fails while a line here no longer happens, so the list only shrinks). Keyed by the
// step and the take.
const VIBRATO_LOCK = 'a natural vibrato (5.5 Hz, ±50 cents) reads as off the lock';
const VIBRATO_RELEASE = 'a natural vibrato reads as an unsteady release';
const SHAKE_LOCK = 'a 3 Hz shake still locks';
const LATE_FAST = '100 ms behind misses notes on a fast line';
const SLIDE_SHORT = 'an eased 0.25 s slide over a whole step is under SLIDE_SECONDS\' 0.12 s between the two notes';
const SCOOP_FROM_BEFORE = 'a 300-cent scoop that starts on the note before reads as legato, and a fall into a joined note sung late isn\'t heard';
const SCOOP_200 = 'a 200-cent scoop counts as the scoop asked for';
export const EXCEPTIONS = {};

// A step's own time limit: a template the test page can't fetch, or anything else that never answers, fails the guard
// naming the step, never the whole page's run (the coordinator, 2026-11-08: a content pin's run timed out unexplained).
const STEP_MS = 20000;
const within = (promise, what) => Promise.race([promise, new Promise((_, fail) => setTimeout(() => fail(new Error(`${what} took over ${STEP_MS / 1000} s`)), STEP_MS))]);

test('scoring guard: a path on one unbroken sound keeps its bubble through a trill read in part, and a 300 ms stop breaks it', () => {
    const exercise = { score: 'unbroken', trill: true, target: [{ seconds: 6, kind: 'path', band: 150, points: [[0, 0], [1.5, 5], [3, 0], [4.5, 5], [6, 0]] }] };
    for (const generous of [false, true]) {
        const { a, b, score } = waveStep({ ...exercise, generous }, 'relaxed');
        for (const take of a) {
            const r = score(take.o);
            if (!(r.percent >= 90)) throw new Error(`${take.name}${generous ? ', generous' : ''}: ${r.percent}%`);
        }
        const stopped = score(b.find((take) => take.name === 'a 300 ms stop').o);
        if (!(stopped.percent < 90)) throw new Error(`a 300 ms stop${generous ? ', generous' : ''}: ${stopped.percent}%, the bubble kept`);
    }
});

// Whistle steps the guard sings until a whistling course is on main (docs/courses/whistling.md's measures): every take
// whistled two octaves up must earn its stars, a whistle's 80 ms scoop too, and a line that slips an octave halfway
// must not.
const WHISTLE_STEPS = [
    { kind: 'play', exercise: { tempo: 80, whistle: true, notes: [0, 2, 4, 5, 4, 2, 0].map((step, i) => ({ beat: i, length: 1, step })) } },
    { kind: 'play', exercise: { tempo: 80, whistle: true, score: 'landing', notes: [0, 4, 7, 4, 0].map((step, i) => ({ beat: i * 2, length: 1, step })) } },
    { kind: 'play', exercise: { tempo: 72, whistle: true, score: 'legato', notes: [0, 2, 4, 2, 0].map((step, i) => ({ beat: i, length: 1, step })) } },
    { kind: 'bullseye', exercise: { tempo: 72, whistle: true, echoes: [0, 4, 7, 12] } },
    { kind: 'wave', exercise: { tempo: 60, whistle: true, target: [{ seconds: 4, kind: 'straight' }] } },
    { kind: 'wave', exercise: { tempo: 60, whistle: true, score: 'whistleNote', generous: true, target: [{ seconds: 4, kind: 'straight' }] } },
    { kind: 'wave', exercise: { tempo: 60, whistle: true, target: [{ seconds: 4, kind: 'path', band: 60, points: [[0, 0], [2, 5], [4, 0]] }] } },
];

test('scoring guard: whistle steps star a take whistled two octaves up, a whistle\'s scoop too, and not a line that slips an octave', async () => {
    const problems = [];
    const starsOf = ({ percent, minStars }) => (percent === null ? 0 : Math.max(starsFor({ percent, timing: 'none' }), minStars ?? 0));
    for (const { kind, exercise } of WHISTLE_STEPS) {
        const check = kind === 'play' ? await playStep(exercise, 'relaxed') : kind === 'bullseye' ? bullseyeStep(exercise) : waveStep(exercise, 'relaxed');
        for (const take of check.a) if (starsOf(check.score(take.o)) < 3) problems.push(`${check.kind}: ${take.name} earned ${starsOf(check.score(take.o))} stars (${check.score(take.o).percent}%)`);
        for (const take of check.b) if (starsOf(check.score(take.o)) >= 3) problems.push(`${check.kind}: ${take.name} earned 3 stars (${check.score(take.o).percent}%)`);
        if (kind !== 'wave' && !check.b.some((take) => take.name.startsWith('an octave slip'))) problems.push(`${check.kind}: no octave slip sung`);
    }
    if (problems.length) throw new Error(problems.join('\n'));
});

test('scoring guard: every course\'s scored steps star a take sung as asked, and not the one the words warn against', async () => {
    const problems = [];
    const excepted = new Set();
    let steps = 0;
    const problem = (key, text) => (key in EXCEPTIONS ? excepted.add(key) : problems.push(text));
    const starsOf = ({ percent, minStars }) => (percent === null ? 0 : Math.max(starsFor({ percent, timing: 'none' }), minStars ?? 0));
    for (const c of await within(courses(), 'loading the courses')) {
        for (const lesson of lessonsOf(c)) {
            for (const [si, step] of lesson.steps.entries()) {
                if (!step.exercise || !['play', 'bullseye', 'wave'].includes(step.kind)) continue;
                const where = `${lesson.where}[${si}]`;
                document.title = `Running: scoring guard, ${where}`; // read by tool/cdp.py if the page stops answering
                let check;
                try {
                    check = step.kind === 'play' ? await within(playStep(step.exercise, lesson.strictness), where) : step.kind === 'bullseye' ? bullseyeStep(step.exercise) : waveStep(step.exercise, lesson.strictness);
                } catch (error) {
                    problems.push(`${where}: couldn't lay out (${error.message})`);
                    continue;
                }
                if (!check) continue;
                steps++;
                const { kind, a, b, bar } = check;
                // A take a scorer throws on fails as that step and take, and the guard goes on with the rest.
                const score = (o, name) => {
                    try {
                        return check.score(o);
                    } catch (error) {
                        problems.push(`${where} ${kind}: ${name} threw (${error.message})`);
                        return null;
                    }
                };
                const trusted = [];
                for (const take of a) {
                    const r = score(take.o, take.name);
                    if (!r) continue;
                    trusted.push(r);
                    if (r.percent === null) problem(`${where} ${take.name}`, `${where} ${kind}: ${take.name} wasn't scored`);
                    else if (bar && starsOf(r) < 3) problem(`${where} ${take.name}`, `${where} ${kind} (${lesson.strictness}): ${take.name} earned ${starsOf(r)} stars (${r.percent}%)`);
                }
                for (const take of b) {
                    const r = score(take.o, take.name);
                    if (!r) continue;
                    if (take.most !== undefined && r.percent > take.most) problem(`${where} ${take.name}`, `${where} ${kind} (${lesson.strictness}): ${take.name} scored ${r.percent}%, over ${take.most}`);
                    else if (starsOf(r) >= 3) problem(`${where} ${take.name}`, `${where} ${kind} (${lesson.strictness}): ${take.name} earned 3 stars (${r.percent}%), as the trusted take did (${trusted[0]?.percent}%)`);
                }
            }
        }
    }
    for (const key of Object.keys(EXCEPTIONS)) if (!excepted.has(key)) problems.push(`${key}: no longer happens; take it out of EXCEPTIONS`);
    if (problems.length) throw new Error(`${problems.length} problems over ${steps} steps:\n${problems.join('\n')}`);
});
