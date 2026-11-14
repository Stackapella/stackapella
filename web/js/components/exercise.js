// A course exercise (docs/vocal-coaching-plan.md, "A lesson", "The play-along screen"): the notes
// play (with a drone or chords underneath if it has them), then it's your turn; each note lights
// up in your colour while you're on it, never red, with "Nice!" or "Perfect!" and a combo of notes
// in a row; then it's scored by the shared scorer, on pitch (an exercise's timing isn't judged).
// Trying, it's Wait for me (js/waitforme.js): each note plays and holds until you find it, then the
// next is placed on the audio clock. Everything that sounds is scheduled on the audio clock by the
// engine; the screen only reads the position and the mic.

import { addSung, flushSung } from '../voice-day.js';
import { audio, engine, latencyNow, leadSeconds, unlock } from '../audio.js';
import { readyMic } from '../mic-check.js';
import { chartDots, holdPercent, longestHold } from '../results.js';
import { CAMERA, frameFor } from '../camera.js';
import { hzToMidi, midiName } from '../dsp.js';
import { formatNumber, t, tk } from '../i18n.js';
import { isSolfa, naming } from '../note-names.js';
import { rangeFor, trackFrame, trackInWorker, WHISTLE_RANGE } from '../pitchtrack.js';
import { foldLive, foldTrack, sayFoldOnce } from '../whistle.js';
import { scoreTake, STRICTNESS } from '../scoring.js';
import { renderBacking, renderPart } from '../synth.js';
import { Gems, gemInk, solidColor } from './gems.js';
import { mixColor } from '../glass.js';
import { token } from '../colors.js';
import { Icon } from './icon.js';
import { showMicHelp } from '../mic-help.js';
import { LISTEN_CLARITY } from '../listen.js';
import { HeadphonesDialog } from './dialogs.js';
import { KeyControl } from './key-control.js';
import { local, session as tabStorage } from '../storage.js';
import { game } from '../skin.js';
import { courseRules, effect } from '../game/effects.js';
import { PopupSlot } from '../game/hud.js';
import { BONUS_FROM, bonusRate, bonusRoom, comboMilestone, popupQueue, rateRaised } from '../game/popups.js';
import { heardFrame, HINT_AFTER, HOLD_TO_LIGHT, praise, startWaiting } from '../waitforme.js';
import { counted, learning } from '../stores/learning.js';
import { PLAY_SCORERS, rhythmOptions, withRhythm } from '../lesson-scores.js';
import { keepFirstTake } from './then-and-now.js';
import { ReadStave } from '../lesson-steps.js'; // loaded when a staff step needs it
import { chipCountIn } from '../game/modules.js';
import { styleBend } from '../style-scores.js';
import { underMix } from '../under-mix.js';
import { compareLines, openChoices, pitchAt, pulls, resolveChoices, speciesChecks } from '../harmony.js';
import { chordAt, ringReading } from '../tuning.js';
import { RING } from '../ring.js';
import { RingMeter } from './sing/ring-meter.js';

const PHASE = { listen: tk('course.phase.listen'), turn: tk('course.phase.turn'), wait: tk('course.phase.wait') };
const PRAISE = { nice: tk('course.praise.nice'), perfect: tk('course.praise.perfect') };
const HELD_BEATS = 24; // a note in Wait for me holds this long, at most (with headphones)
const COMBO_SHOWN = 3; // the combo shows from this many in a row
const WEAK_TAKE = 70; // a take under this percent (or unheard) is weak: after two, Sing it with the guide
const MIN_BEAT_PX = 48; // a beat is never narrower: a brick stays its note's length
const LABELLED_BEAT_PX = 22; // a labelled lane's beat (the warm-up's pattern), scrolling past this
const LABELLED_SQUEEZE_PX = 16; // narrower still, only when the whole pass then fits
const LABEL_CHAR_PX = 9; // a syllable or name shows only when all of it fits its brick (never a cut-off "l…"), as Sing's lane
const LABEL_PAD_PX = 10;
const PLAYHEAD_AT = 0.35; // a longer exercise scrolls with the playhead here, across
const BRICK_GAP = 3; // between one brick and the next
const PARTNER_COLOR = 'var(--color-muted)'; // another voice's notes on the lane: faint, never yours
const MIX_PEAK = 0.95; // Hear it together never clips
const GIVEN_NOTE_LEVEL = 0.35; // Give me my note: quietly, under your voice
const GENEROUS_STARS = 2; // a first lesson's fair, finished go
const HEARD_FIRST_MOST = 89; // a take heard first (sight-singing): just under the top score (js/course.js starsFor's 90)
const DRONE_UNDER = 0.45; // the drone, under your take in Hear it together
const PARTNER_VOLUME = 0.5; // partner lines about 6 dB under the guide: listen more, never sing louder to hear yourself
const reducedMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * The exercise laid out in beats from 0, for a learner whose comfortable note is `base`: what plays
 * (`guide`, then `target` is what's sung and scored), and where it ends. `turn`: the notes play,
 * then a beat's gap, then the learner sings them back.
 */
export function layout(exercise, base) {
    const notes = styledNotes(exercise.notes.map((n) => laidNote(n, base)), exercise);
    const span = Math.max(...notes.map((n) => n.beat + n.length));
    if (exercise.turn === false) return withPartners({ guide: notes, target: sungNotes(notes), turnFrom: Math.min(...notes.map((n) => n.beat)), end: span + 0.5 }, exercise, base);
    const gap = 1;
    // Call and response (`answer`): what you sing back differs from the call.
    const sung = exercise.answer ? styledNotes(exercise.answer.map((n) => laidNote(n, base)), exercise) : sungNotes(notes);
    const sungSpan = exercise.answer ? Math.max(...sung.map((n) => n.beat + n.length)) : span;
    const target = sung.map((n) => ({ ...n, beat: n.beat + span + gap }));
    return withPartners({ guide: notes, target, turnFrom: span + gap, end: span + gap + sungSpan + 0.5 }, exercise, base);
}

/**
 * A note laid out for the learner: in beats, its pitch `step` semitones from `base`. A per-note
 * `cents` tunes it off the piano's (a pure third sits at -14); `listenOnly` is heard, never sung;
 * `choices` are steps any of which is right.
 */
function laidNote(n, base) {
    const note = { beat: n.beat, length: n.length, pitch: base + n.step + (n.cents ?? 0) / 100, lyric: n.lyric ?? null, vibrato: n.vibrato ?? null };
    if (n.listenOnly) note.listenOnly = true;
    if (n.choices) note.choices = n.choices.map((step) => base + step);
    if (n.bend) note.bend = n.bend;
    if (n.voice) note.voice = n.voice;
    if (n.style) note.style = n.style;
    return note;
}

/**
 * A note's `style` (a scoop, a fall-off, a slide into the next note, a cry: the Pop and country
 * course) sung by the guide: its bend from styleBend (js/style-scores.js), the same numbers that
 * star it. A note's own `bend` wins.
 */
function styledNotes(notes, exercise) {
    return notes.map((n, i) => (n.style && !n.bend ? { ...n, bend: styleBend(n, notes[i + 1], exercise, notes[i - 1] ?? null) ?? undefined } : n));
}

/**
 * A drone that fades away (Folk's "On your own"): its rendered samples brought down to silence from
 * `fromSeconds` over `overSeconds`, in the buffer itself, so the fade rides the audio clock with
 * the rest of the take.
 */
export function fadeOut(samples, sampleRate, fromSeconds, overSeconds) {
    const start = Math.max(0, Math.round(fromSeconds * sampleRate));
    const length = Math.max(1, Math.round(overSeconds * sampleRate));
    for (let i = start; i < samples.length; i++) samples[i] *= Math.max(0, 1 - (i - start) / length);
    return samples;
}

/** Give me my note: the note being sung now, or the next one (`target` in beats), or null when the line is over. */
export function noteToGive(target, beatNow) {
    return target.find((n) => n.beat + n.length > beatNow) ?? null;
}

/** A ring reading against another pure target (`pure`, cents from the piano's pitch): judged again as ringReading does. */
export function judged(reading) {
    const off = reading.cents - reading.pure;
    const state = reading.state === 'waved' ? 'waved' : Math.abs(off) <= RING.locked ? 'ringing' : Math.abs(off) <= RING.close ? 'close' : 'off';
    return { ...reading, off, state };
}

/** The notes the learner sings: all but the listen-only ones. */
function sungNotes(notes) {
    return notes.some((n) => n.listenOnly) ? notes.filter((n) => !n.listenOnly) : notes;
}

// Through a speaker, a partner's call in a scored turn sounds only in your rests, clear of your notes by these margins:
// before your next note, the entry look-back (scoring.js ENTRY_WINDOW, 0.25 s), the synth's release and the room's tail;
// after your last, a singer a little late.
export const CALL_CLEAR_BEFORE = 0.4;
export const CALL_CLEAR_AFTER = 0.15;

/**
 * Through a speaker, what of a partner line sounds (`notes`, laid, in beats): all of it before `until` (the scored
 * turn), cut there; after it, each run of joined notes (a call) that lies wholly in one of your rests, clear of the
 * `target` notes you sing by CALL_CLEAR_BEFORE and CALL_CLEAR_AFTER, kept whole, never cut (the courses lead's
 * Sea shanties review: the crew's calls between your answers fell silent). `calls`: the kept calls' spans, in beats.
 */
export function speakerPartnerNotes(notes, target, until, beatSeconds) {
    const before = notes.filter((n) => n.beat < until).map((n) => ({ ...n, length: Math.min(n.length, until - n.beat) }));
    const runs = [];
    for (const n of [...notes].filter((x) => x.beat >= until).sort((a, b) => a.beat - b.beat)) {
        const run = runs.at(-1);
        if (run && Math.abs(run.at(-1).beat + run.at(-1).length - n.beat) < 1e-6) run.push(n);
        else runs.push([n]);
    }
    const kept = runs.filter((run) => {
        const from = run[0].beat * beatSeconds;
        const to = (run.at(-1).beat + run.at(-1).length) * beatSeconds;
        return target.every((t) => to <= t.beat * beatSeconds - CALL_CLEAR_BEFORE || from >= (t.beat + t.length) * beatSeconds + CALL_CLEAR_AFTER);
    });
    return { notes: [...before, ...kept.flat()], calls: kept.map((run) => [run[0].beat, run.at(-1).beat + run.at(-1).length]) };
}

/** A take's pitch track with the kept calls' spans (beats, from speakerPartnerNotes) silenced, and a tail after each: the mic heard them, not you. */
export function withoutCalls(track, calls, beatSeconds, tail = CALL_CLEAR_AFTER) {
    if (!calls.length) return track;
    const hz = Float32Array.from(track.hz);
    for (const [from, to] of calls) for (let k = Math.max(0, Math.floor(from * beatSeconds * track.rate)); k < Math.min(hz.length, Math.ceil((to * beatSeconds + tail) * track.rate)); k++) hz[k] = 0;
    return { ...track, hz };
}

/**
 * Partner lines (`with`, the Counterpoint course): other voices that sound while you sing, never
 * scored. Each is `{ notes, volume? }` in steps and beats on the exercise's own timeline, or
 * `{ delay, steps? }`: the sung line itself, `delay` beats later and `steps` higher (a round, a
 * canon). The exercise ends when the last voice does.
 */
function withPartners(laid, exercise, base) {
    if (!exercise.with?.length) return laid;
    const partners = exercise.with.map((line) => ({
        volume: line.volume ?? PARTNER_VOLUME,
        notes: line.notes ? line.notes.map((n) => laidNote(n, base)) : laid.target.map(({ choices, ...n }) => ({ ...n, beat: n.beat + line.delay, pitch: n.pitch + (line.steps ?? 0) })),
    }));
    const last = Math.max(...partners.flatMap((p) => p.notes.map((n) => n.beat + n.length)));
    return { ...laid, partners, end: Math.max(laid.end, last + 0.5) };
}

/**
 * What the guide sings in a run: the notes to listen to (for a turn, the call; over a drone or with
 * partners, nothing while you sing), and always any listen-only note. With `guide: false` (a cold
 * start: sing before you hear it), only those.
 */
export function heardNotes(laid, exercise, mode) {
    const listenOnly = laid.guide.filter((n) => n.listenOnly);
    if (exercise.guide === false || (mode === 'play' && exercise.turn === false)) return listenOnly;
    return laid.guide;
}

/**
 * Where the drone sounds, in beats, up to `under`: from the start, or with `guide: false`, before
 * the turn only, or from `droneAfter` beats into it (revealing how close you were).
 */
export function droneSpan(laid, exercise, under, leadBeats = 0) {
    if (exercise.guide !== false) return { from: 0, to: under };
    // A line with no guide whose drone starts with it (`droneAfter: 0` on a line that's all yours): do sounds
    // through the lead before beat 0 too (`leadBeats`: the look and any count-in), so a reader hears the key
    // while reading the stave, then sings over the same unbroken drone (the courses lead's teacher reviewer).
    if (exercise.droneAfter === 0 && laid.turnFrom === 0) return { from: -leadBeats, to: under };
    if (exercise.droneAfter != null) return { from: laid.turnFrom + exercise.droneAfter, to: under };
    return { from: 0, to: Math.min(under, laid.turnFrom) };
}

/** Whether a sung pitch (MIDI) is on `target` within `cents`, in any octave (a man singing an alto line). */
export function onNote(midi, target, cents) {
    const off = ((((midi - target) % 12) + 18) % 12) - 6; // the nearest octave, in semitones
    return Math.abs(off * 100) <= cents;
}

const HEADPHONES_ASKED = 'stackapella.headphonesAsked'; // the lesson that needs headphones last asked about this visit

/** Headphones on (this visit, or "don't remind me"), the speaker chosen, or not asked yet (null). */
function listening() {
    if (tabStorage.get('stackapella.headphones') === '1' || local.get('stackapella.headphones.never') === '1') return 'headphones';
    return tabStorage.get('stackapella.speaker') === '1' ? 'speaker' : null;
}

/**
 * A play step's score from a take's pitch track (`laid`: layout()'s output): the default measure (scoreTake), a
 * course's own (PLAY_SCORERS, by the step's `score`), a held note's ('hold') or the beat's (`rhythm`), and a generous
 * first lesson's floor. Pure, so the scoring guard (tests/score-guard.test.js) walks every course's steps through the
 * same path a singer's take takes.
 */
export function scorePlayTake({ track: heard, laid, exercise, beatSeconds, strictness, personal = {}, speaker = false, latencyMeasured = true }) {
    // A whistle (`whistle`, js/whistle.js) is moved down onto the written line by whole octaves first, so every measure
    // reads it as sung there; a course's own measure gets the take as heard too (`heard`: the whistle note's finder).
    const { track, octaves: whistled } = exercise.whistle ? foldTrack(heard, laid.target.map((n) => ({ start: n.beat * beatSeconds, end: (n.beat + n.length) * beatSeconds, pitch: n.pitch }))) : { track: heard, octaves: 0 };
    // A note with `choices` is judged as the one you sang (js/harmony.js), then scored as any note.
    const notes = laid.target.some((n) => n.choices) ? resolveChoices(track, laid.target, beatSeconds) : laid.target;
    // `rhythm` (true or "swing", default-scored steps only): the timing is measured too, and counts 30%.
    const rhythm = exercise.rhythm && !exercise.score ? exercise.rhythm : null;
    const score = scoreTake(track, notes, { beatSeconds, startSeconds: 0, strictness, timing: rhythm ? (latencyMeasured ? 'measured' : 'estimated') : 'none', ...rhythmOptions(rhythm, beatSeconds), crossing: true }); // a quick joined note read from where you crossed onto it
    const timed = withRhythm(score, { rhythm, speaker, measured: latencyMeasured });
    const cents = STRICTNESS[strictness] ?? STRICTNESS.relaxed;
    const target = notes.map((n) => ({ start: n.beat * beatSeconds, end: (n.beat + n.length) * beatSeconds, pitch: n.pitch }));
    const dots = chartDots(track, target, { cents }).filter((d) => d.t >= target[0].start - 0.5 && d.t <= target.at(-1).end + 0.5);
    // A held note (score: 'hold'): stars for how long and how steadily, not just its middle.
    // A course's own measure (js/lesson-scores.js) has the first say when the step names one.
    const scorer = PLAY_SCORERS[exercise.score];
    const own = scorer && score.status === 'scored' ? scorer({ track, heard, dots, target, notes, beatSeconds, strictness, exercise, personal, speaker, timing: latencyMeasured ? 'measured' : 'estimated' }) : null;
    const percent = score.status !== 'scored' ? null : own ? own.percent : exercise.score === 'hold' ? holdPercent(dots, target) : timed ? timed.percent : score.percent;
    // A first lesson's kinder stars (`generous`) on the default measure: a fair, finished go earns at least two stars,
    // while the percent stays as measured (the serious skin says it plainly). A registered scorer reads `generous` its
    // own way. A generous first lesson's floor holds whoever scores it: the default, or a course's own measure.
    const minStars = exercise.generous && percent !== null ? Math.max(GENEROUS_STARS, own?.minStars ?? 0) : own?.minStars ?? null;
    return { notes, score, timed, target, cents, dots, own, percent, minStars, whistled };
}

export const Exercise = {
    components: { Gems, Icon, HeadphonesDialog, KeyControl, PopupSlot, ReadStave, RingMeter },
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true }, // the learner's comfortable note (MIDI)
        mode: { type: String, default: 'play' }, // listen (only hear it), try (the guide sings along), play (scored)
        strictness: { type: String, default: 'relaxed' },
        color: { type: String, default: 'var(--color-part-4)' },
        // The key (128.png): shown with Lower and Higher above the play button, when the lesson gives one.
        musicKey: { type: String, default: null },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
        higherShown: { type: Boolean, default: true }, // Higher at all (a lower-only lesson: `keyRange.up: 0`)
        // The workout (85.png) draws its own Start, Hear it first and starting note, and calls start()
        // and stop(): without the round play button and the key here.
        bare: { type: Boolean, default: false },
        // Every lane (Hillel's warm-up design, then the lessons too): each row named down the side with a
        // line through it, no drifting specks, room top and bottom so no brick is cut off, and a turn
        // drawn once with the playhead crossing it twice. Off only to see the old lane.
        labelled: { type: Boolean, default: true },
        // Beats of rest the exercise starts with (the warm-up's get-ready and Breathe between rounds),
        // and what the status line says through them: on the audio clock, like the notes.
        restBeats: { type: Number, default: 0 },
        restLabel: { type: String, default: '' },
        // A Plus course's lesson (views/learn.js): an earlier step's take to sing over (`exercise.under`),
        // and the course's own values for a registered scorer (js/lesson-scores.js).
        // Earlier takes of this visit beneath this one (`exercise.under`, js/under-mix.js underLayers): each
        // `{ samples, sampleRate, loopBeats, volume }`, looped or once, quieter in the order listed.
        underTakes: { type: Array, default: () => [] },
        // A lesson that needs headphones (`headphones: "needed"`, its own or its course's): its id, so the ask comes
        // again there, once a lesson, even when the speaker was picked earlier in this visit.
        headphonesFor: { type: String, default: '' },
        personal: { type: Object, default: () => ({}) },
        // A lesson's own scored step (views/learn.js): its runs of 10 or more earn the combo bonus; the
        // workout's rounds and anything else don't (game mode's pop-ups never promise it there).
        earnsBonus: { type: Boolean, default: false },
    },
    // done: { percent, word, tips, sung } after a run (sung: the target notes lit, for the range);
    // practised: the seconds of a run with the mic, for the weekly goal.
    emits: ['done', 'practised', 'lower', 'higher', 'lit'],
    data: () => ({ width: 0, running: false, scoring: false, position: 0, live: null, lit: new Set(), hitAt: {}, waitFoundAll: false, error: null, asking: false, speaker: listening() === 'speaker', waiting: null, cheer: null, combo: 0, bonus: 0, hud: popupQueue(), chosen: {}, revealed: false, weakTakes: 0, withGuide: false, lead: 0, heardFirst: false, hearing: false, ring: null }),
    template: `
        <div class="space-y-4">
            <!-- The lane (Hillel's arcade pass, ux-spec 5.7c): the exercise framed tight and centred, its
                 notes as glass gems on a stage; the next breathes, the note now glows with a rim, a hit
                 pops and sparkles and keeps a tick, a miss dims. Notes only to listen to are dashed. -->
            <!-- Wait for me (Hillel's 80.png): one note at a time. Its pills across the top (found, now,
                 coming); the note as a long glass gem that fills only while you're on it; your voice as a
                 bead with a dashed guide to it; the waiting chip and Slide up or Come down when
                 you're off; a big line under it; Hear the note and Start over. -->
            <template v-if="mode === 'try'">
                <ol class="flex flex-wrap gap-2" :aria-label="t('course.wait.notes')" data-wait-pills>
                    <li v-for="(n, i) in laid.guide" :key="i" class="flex items-center gap-1 rounded-lg border px-2.5 py-1 text-xs font-extrabold" :class="pillDone(i) ? 'border-transparent' : i === waitIndex && running ? 'border-accent text-cream' : 'border-edge text-muted'" :style="pillDone(i) ? { background: color, color: paintText(color) } : null" translate="no">
                        <Icon v-if="pillDone(i)" name="check" :size="12" />{{ name(n.pitch) }}
                    </li>
                </ol>
                <div class="lane-stage relative overflow-hidden rounded-2xl border border-line bg-ink" style="height: 200px" role="img" :aria-label="waitLabel" data-wait-lane>
                    <template v-if="waitNote && !waitDone">
                        <!-- The dashed guide from your voice to the note. -->
                        <svg v-if="running && liveWait !== null" class="pointer-events-none absolute inset-0 size-full" aria-hidden="true"><line :x1="beadX + '%'" :x2="beadX + '%'" :y1="waitY(liveWait)" :y2="waitY(waitNote.pitch)" stroke="var(--color-cream)" stroke-opacity="0.6" stroke-width="2" stroke-dasharray="4 4" /></svg>
                        <Gems :bricks="waitBricks" />
                        <div class="absolute flex items-center px-4 text-base font-extrabold" :style="{ left: '8%', width: '84%', top: (waitY(waitNote.pitch) - 24) + 'px', height: '48px', color: fill > 0.12 ? gemInk(color) : 'var(--color-cream)' }">
                            <span class="relative" translate="no">{{ name(waitNote.pitch) }}</span>
                        </div>
                        <div v-if="running && liveWait !== null" class="gem absolute size-5 -translate-x-1/2 -translate-y-1/2 rounded-full" :style="{ left: beadX + '%', top: waitY(liveWait) + 'px', '--gem': 'var(--color-cream)' }" aria-hidden="true"></div>
                        <p v-if="running && !onNote" data-ui="facts" class="absolute start-3 top-3 flex items-center gap-2 rounded-full bg-raised px-3 py-1 text-xs font-bold text-cream" role="status">
                            {{ t('course.wait.waiting') }}<span v-if="waiting?.direction" class="text-accent-strong">· {{ waiting.direction === 'higher' ? t('course.wait.slideUp') : t('course.wait.comeDown') }}</span>
                        </p>
                    </template>
                    <div v-else-if="waitDone" class="absolute inset-0 grid place-items-center text-center" role="status" data-wait-done>
                        <div><p class="text-2xl font-extrabold text-cream">{{ t('course.wait.allMatched', { count: laid.guide.length }) }}</p><p class="mt-1 text-sm text-muted">{{ t('course.wait.next', { count: laid.guide.length }) }}</p></div>
                    </div>
                </div>
                <!-- Matched, the stage above says so: the result said once (design rules). -->
                <div v-if="!waitDone" class="text-center" aria-live="polite">
                    <p class="text-xl font-extrabold text-cream">{{ waitHeadline }}</p>
                    <p class="text-sm text-muted">{{ waitLine }}</p>
                </div>
            </template>
            <!-- Sight-singing (docs/sight-singing-plan.md): a step with \`staff\` is read from a stave, not the lane -->
            <ReadStave v-else-if="staffStep" :notes="laid.target" :home="base" :tempo="exercise.tempo ?? 72" :time="exercise.staff.time ?? '4/4'" :pickup="exercise.staff.pickup ?? 0" :music-key="exercise.song || exercise.staff.pickup !== undefined ? musicKey : null" :names="exercise.staff.names ?? 'on'" :lit="staffLit" :look="running && lookLeft > 0 ? lookLeft : null" :counting="running && lookLeft <= 0 && lead > 0 ? countBeat : null" :color="color" />
            <div v-else class="lane-stage relative overflow-hidden rounded-2xl border border-line bg-ink" :style="{ height: height + 'px' }" role="img" :aria-label="label">
                <template v-if="labelled">
                    <template v-for="p in pitchesUsed" :key="'l' + p">
                        <span class="absolute end-0 h-px bg-cream/10" :style="{ top: y(p) + 'px', left: edge - 6 + 'px' }" aria-hidden="true"></span>
                        <span class="absolute start-2 -translate-y-1/2 text-[11px] font-semibold tabular-nums text-muted" :style="{ top: y(p) + 'px' }" aria-hidden="true" translate="no">{{ name(p) }}</span>
                    </template>
                </template>
                <span v-for="p in labelled ? [] : rows" :key="'r' + p" class="absolute inset-x-0 h-px" :class="p % 12 === 0 ? 'bg-cream/10' : 'bg-cream/[0.04]'" :style="{ top: y(p - 0.5) + 'px' }" aria-hidden="true"></span>
                <span v-for="(s, i) in specks" :key="'s' + i" class="lane-speck" :style="s" aria-hidden="true"></span>
                <Gems :bricks="laneBricks" />
                <template v-for="(n, i) in shown" :key="i"><div v-if="drawn(n, i) === 'full'" class="absolute flex items-center gap-1 text-sm font-extrabold" :class="wide(n) ? 'px-3' : labelled ? 'justify-center px-0 text-xs' : 'justify-center px-1'" :style="bar(n, i)" :data-lane-note="brickState(n, i).ghost ? 'guide' : 'sung'">
                    <span v-if="labelFits(n)" class="truncate" translate="no">{{ noteWord(n) }}</span>
                    <Icon v-if="litNow(i) && wide(n)" name="check" :size="14" class="ms-auto shrink-0" />
                </div></template>
                <!-- More of the exercise to the right (it scrolls into view as it plays): the edge fades, so nothing looks cut off. -->
                <span v-if="moreRight" class="pointer-events-none absolute inset-y-0 end-0 w-20" style="background: linear-gradient(to left, var(--color-ink) 15%, transparent)" aria-hidden="true" data-lane-more></span>
                <div v-if="running" class="lane-beam absolute inset-y-0 w-[3px] -translate-x-1/2" :style="{ left: x(beatNow) + 'px', '--beam': phaseKey === 'listen' ? 'var(--color-cream)' : color }"></div>
                <span v-if="cheer" :key="cheer.at" class="pointer-events-none absolute -translate-x-1/2 rounded-full bg-cream px-2 py-0.5 text-xs font-bold text-ink" :class="cheer.still ? '' : 'cheer-pop'" :style="{ left: cheer.x + 'px', top: cheer.y + 'px' }">{{ t(PRAISE[cheer.word]) }}</span>
                <div v-if="running && live !== null && !coldTurn" class="gem absolute size-4 -translate-x-1/2 -translate-y-1/2 rounded-full" :style="{ left: x(beatNow) + 'px', top: y(liveShown) + 'px', '--gem': color }"></div>
            </div>
            <!-- Tune the chord (exercise.tuning, ux-spec 5.10c): a held chord note against its pure tuning, a view only, never scored; hidden through a speaker -->
            <RingMeter v-if="running && ring" class="mx-auto w-full max-w-md" :reading="ring" :color="color" data-lesson-ring />
            <!-- Bare (the host has its own Start): at rest the empty line takes no room, so no gap sits under the lane (the finish-line audit); it stays a live region. -->
            <p v-if="mode !== 'try'" class="text-center text-sm font-semibold" :style="{ color: phaseKey === 'listen' ? null : color }" :class="[phaseKey === 'listen' ? 'text-muted' : '', bare && !running && !scoring ? 'sr-only' : 'min-h-6']" aria-live="polite" data-lane-status>{{ running ? (restLabel && beatNow < restBeats ? restLabel : t(PHASE[phaseKey])) : scoring ? t('course.scoring') : '' }}</p>
            <div v-if="running && combo >= COMBO_SHOWN && game() && courseRules.combo" class="flex items-center justify-center gap-4 text-sm">
                <span class="whitespace-nowrap rounded-full bg-raised px-3 py-1 font-semibold text-cream" data-combo>{{ t('course.combo', { count: combo }) }}</span>
                <span v-if="earnsBonus && bonusRate(combo) >= 2" class="ge-rate whitespace-nowrap" data-bonus-rate><span aria-hidden="true">{{ t('game.popup.rateChip', { count: bonusRate(combo) }) }}</span><span class="sr-only">{{ t('game.popup.rateChipLabel', { count: bonusRate(combo) }) }}</span></span>
                <!-- Game mode's bonus pop-ups beside the combo, never on the lane (docs/game-elements-plan.md) -->
                <PopupSlot v-if="effect('popups')" :queue="hud" />
            </div>
            <div v-if="running && waiting" class="flex flex-wrap justify-center gap-3">
                <button v-if="waiting.searching > 2 * HINT_AFTER" type="button" class="btn-ghost btn-sm" @click="skipNote">{{ t('course.skipNote') }}</button>
            </div>
            <p v-if="error" class="text-center text-sm text-bad" role="alert">{{ error }}</p>
            <p v-if="speaker && soundsUnder && (mode !== 'try' || answered())" class="text-center text-sm text-muted">{{ mode === 'try' ? t('course.speakerTry') : exercise.turn === false ? (laid.turnFrom > 0 ? t('course.speakerUnder') : t('course.speakerUnderAtOnce')) : t('course.speakerPlay') }}</p>
            <HeadphonesDialog v-if="asking" speaker-option simple :needed="!!headphonesFor" :action="t('headphones.on')" @ok="headphonesOn" @speaker="useSpeaker" @close="asking = false" />
            <!-- Waiting for you needs no Stop: Hear the note again, or Start over. -->
            <div v-if="mode === 'try' && (running || waitDone)" class="flex flex-wrap justify-center gap-3">
                <button v-if="waitNote && !waitDone" type="button" class="btn-secondary" @click="playNote(waitIndex)"><Icon name="play" :size="16" /> {{ t('course.wait.hear', { note: name(waitNote.pitch) }) }}</button>
                <button type="button" :class="waitDone ? 'link-quiet min-h-11 text-sm' : 'btn-secondary'" @click="startOver">{{ t('course.wait.startOver') }}</button>
            </div>
            <!-- 128.png: the key with Lower and Higher, then the big round play with what it does under it. -->
            <div v-else-if="!bare" class="flex flex-col items-center gap-5">
                <KeyControl v-if="musicKey && !running && !scoring" class="w-full" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :higher-shown="higherShown" @lower="$emit('lower')" @higher="$emit('higher')" />
                <button type="button" class="group flex flex-col items-center gap-2 disabled:opacity-50" :disabled="scoring" @click="running ? stop() : start()">
                    <span class="grid size-20 place-items-center transition group-hover:scale-105 bubble bubble-lg bubble-primary bubble-ringed" data-play-disc><Icon :name="running ? 'stop' : startIcon" :size="30" /></span>
                    <span class="text-sm font-semibold text-cream">{{ running ? t('studio.stop') : startLabel }}</span>
                </button>
                <!-- After two weak takes, a take with the guide singing too, unscored (the pitch course's review). -->
                <button v-if="mode === 'play' && weakTakes >= 2 && !running && !scoring" type="button" class="btn-ghost btn-sm" data-sing-with-guide @click="singWithGuide">{{ t('course.withGuide') }}</button>
                <!-- Hear it first (sight-singing): the line played as written before you read it; the take then can't take the top score. -->
                <button v-if="mode === 'play' && exercise.hearFirst && !running && !scoring" type="button" class="btn-ghost btn-sm" :disabled="hearing" data-hear-first @click="hearItFirst">{{ hearing ? t('course.read.hearing') : t('course.read.hearFirst') }}</button>
                <p v-if="mode === 'play' && exercise.hearFirst && heardFirst && !running" class="text-sm text-muted" role="status">{{ t('course.read.heardFirst') }}</p>
                <button v-if="mode === 'play' && exercise.giveNote && running && phaseKey === 'turn' && !speaker" type="button" class="btn-ghost btn-sm" data-give-note @click="giveNote">{{ t('course.giveNote') }}</button>
                <p v-if="withGuide && running" class="text-sm text-muted" role="status">{{ t('course.withGuideNote') }}</p>
            </div>
        </div>`,
    setup: () => ({ PHASE, PRAISE, COMBO_SHOWN, HINT_AFTER, gemInk, game, effect, bonusRate, courseRules }),
    computed: {
        /** What the live reading listens for: a whistle's range (pitchtrack.js WHISTLE_RANGE), else a voice's, at the lane's bar. */
        liveRange: (vm) => ({ ...(vm.exercise.whistle ? WHISTLE_RANGE : {}), minClarity: LISTEN_CLARITY }),
        /** The lane's notes as bricks (js/glass.js), one canvas for all of them. */
        laneBricks() {
            const bricks = this.shown.flatMap((n, i) => {
                const drawn = this.drawn(n, i);
                if (drawn === 'hidden') return [];
                const own = n.choices ? this.choiceBricks(n, i) : [{ ...this.place(n), color: this.color, ...this.brickState(n, i) }];
                return drawn === 'faint' ? own.map((b) => ({ ...b, ghost: true, glow: 0 })) : own;
            });
            return this.partnerBricks.length ? [...this.partnerBricks, ...bricks] : bricks;
        },
        /**
         * Partner lines (`with`): another voice's notes, faint and flat, drawn in your line's octave
         * (as your own voice is folded), so a voice an octave away never shrinks your notes.
         */
        partnerBricks() {
            return this.partnerNotes.map((n) => ({ ...this.place(n), color: PARTNER_COLOR, ghost: true }));
        },
        /** Wait for me's note: a quiet brick that fills with your colour while you're on it. */
        waitBricks() {
            const note = this.waitNote;
            const y = note ? this.waitY(note.pitch) - 24 : 0;
            const color = mixColor(solidColor(this.color), token('raised', '#2a2a2a'), 0.65);
            const fill = this.fill;
            const on = this.onNote;
            return ({ width }) => (note && !this.waitDone ? [{ x: width * 0.08, y, w: width * 0.84, h: 48, color, rim: on, glow: on ? 0.8 : 0, fill, fillColor: this.color }] : []);
        },
        laid: (vm) => layout(vm.exercise, vm.base),
        /**
         * With fixed-Do names (Spanish), the pitches this exercise sings on a solfège syllable over the home note, each
         * with its syllable: those notes are named by it ("mi"), never by a fixed name ("Fa#4") that would contradict it.
         */
        solfaAt: (vm) => (naming() === 'fixed' ? new Map([...(vm.laid.guide ?? []), ...vm.laid.target].filter((n) => isSolfa(n.lyric)).map((n) => [Math.round(n.pitch), n.lyric.replace(/-$/, '')])) : new Map()),
        /** The notes sung in tune so far this take (the lit ones past the lead-in): a boss round's bar reads it live. */
        litTargets: (vm) => [...vm.lit].filter((i) => i >= vm.shown.length - vm.laid.target.length).length,
        beatSeconds: (vm) => 60 / (vm.exercise.tempo ?? 72),
        beatNow: (vm) => vm.position / vm.beatSeconds,
        /** A sight-singing step (`staff`): read from a stave instead of the lane. */
        staffStep: (vm) => !!vm.exercise.staff && vm.mode !== 'try',
        /** A reading line's count-in (`staff.count`, in the time's own beats), clicked on the audio clock after the look. */
        countBeats: (vm) => (vm.staffStep ? Math.max(0, Math.min(8, vm.exercise.staff.count ?? 0)) : 0),
        /** The look still left before the count-in starts (seconds). */
        lookLeft: (vm) => vm.lead - vm.countBeats * vm.beatSeconds,
        /** The count-in's beat being clicked now: 1, 2, 3, 4. */
        countBeat: (vm) => Math.min(vm.countBeats, vm.countBeats - Math.ceil(vm.lead / vm.beatSeconds - 1e-6) + 1),
        /**
         * On a stave, the note sounding or to sing now, lit as the clock reaches it (not by pitch: a cold
         * read is judged after): the guide's while it plays the line (hear it, then sing it), then yours.
         */
        staffLit() {
            if (!this.running || this.lead > 0) return -1;
            const at = (notes) => notes.findIndex((n) => this.beatNow >= n.beat && this.beatNow < n.beat + n.length);
            if (this.phaseKey === 'listen') {
                const heard = this.laid.guide.filter((n) => !n.listenOnly);
                return heard.length === this.laid.target.length ? at(heard) : -1;
            }
            return at(this.laid.target);
        },
        /** What's drawn: the notes heard, and (for a turn) the notes to sing back; trying, just the notes. */
        shown() {
            if (this.mode === 'try') return this.laid.guide;
            if (this.onePass) return this.laid.target;
            if (this.exercise.turn !== false) return [...this.laid.guide, ...this.laid.target];
            // Over a drone, the listen-only notes (an echo's call) sit faint at their own beats.
            const heard = this.laid.guide.filter((n) => n.listenOnly);
            return heard.length ? [...heard, ...this.laid.target] : this.laid.target;
        },
        firstTarget: (vm) => vm.shown.length - vm.laid.target.length,
        /** The beats where a chord starts, for `lights: "changes"`. */
        changeBeats: (vm) => new Set((vm.exercise.chords ?? []).map((c) => c.beat)),
        /** `lights: "off"`: nothing drawn or lit while you sing; the notes are still scored. */
        lightsOff: (vm) => vm.exercise.lights === 'off' && vm.mode === 'play',
        /** A cold start's turn (`guide: false`): no pitch shown and nothing lit until it's over. */
        coldTurn: (vm) => vm.exercise.guide === false && vm.running && vm.phaseKey === 'turn',
        /** The camera's frame (js/camera.js): the exercise's notes, tight and centred, never under a fifth and a bit. */
        camFrame() {
            if (!this.laid.partners) return this.ownFrame;
            const notes = [...this.shown, ...this.partnerNotes].filter((n) => Number.isFinite(n.pitch)).map((n) => ({ start: n.beat, end: n.beat + n.length, pitch: n.pitch }));
            return frameFor(notes, 0, { from: -Infinity, to: Infinity, maxSpan: 48 }) ?? this.ownFrame;
        },
        /** Your own notes' frame (and any choices'), before partner lines are fitted in. */
        ownFrame() {
            const choices = this.shown.filter((n) => n.choices).flatMap((n) => n.choices.map((pitch) => ({ ...n, pitch })));
            const notes = [...this.shown, ...choices].filter((n) => Number.isFinite(n.pitch)).map((n) => ({ start: n.beat, end: n.beat + n.length, pitch: n.pitch }));
            return frameFor(notes, 0, { from: -Infinity, to: Infinity, maxSpan: 48 }) ?? { centre: 60, span: CAMERA.minSpan };
        },
        /**
         * Partner lines' notes as drawn: folded into the octave of your own line (as your voice is), so
         * a voice an octave away sits beside your notes and never spreads the lane over three octaves.
         * The one-pass lane folds the turn back onto the notes heard: only the partners under your turn.
         */
        partnerNotes() {
            const centre = this.ownFrame.centre;
            const from = this.onePass ? this.laid.turnFrom : -Infinity;
            return (this.laid.partners ?? []).flatMap((line) =>
                line.notes
                    .filter((n) => n.beat + n.length > from + 1e-6)
                    .map((n) => {
                        const beat = Math.max(n.beat, from);
                        return { ...n, beat, length: n.beat + n.length - beat, pitch: n.pitch + 12 * Math.round((centre - n.pitch) / 12) };
                    }),
            );
        },
        low: (vm) => vm.camFrame.centre - vm.camFrame.span / 2,
        high: (vm) => vm.camFrame.centre + vm.camFrame.span / 2,
        height: (vm) => Math.round(Math.min(300, Math.max(200, vm.camFrame.span * 26))),
        rowH: (vm) => (vm.height - 2 * vm.pad) / vm.camFrame.span,
        /** Room above and below the rows (labelled: half a brick, so none is cut off), and before them (the names). */
        pad: (vm) => (vm.labelled ? 26 : 8),
        /** Labelled, a turn draws its notes once and the playhead crosses them twice: listening, then singing back (Hillel's warm-up design). */
        onePass: (vm) => vm.labelled && vm.exercise.turn !== false && vm.mode !== 'try',
        /** The beats across the lane: the whole run, or one pass. */
        span: (vm) => (vm.onePass ? vm.laid.end - vm.laid.turnFrom : vm.laid.end),
        edge: (vm) => (vm.labelled ? 44 : 8),
        /** The pitches the exercise uses, for the labelled rows. */
        pitchesUsed() {
            return [...new Set(this.shown.map((n) => n.pitch).filter(Number.isFinite))];
        },
        /** The bricks: big and chunky, as in the arcade lanes (Hillel), taller as the frame zooms in. */
        barH: (vm) => Math.round(Math.max(34, Math.min(48, vm.rowH * 1.5))),
        /**
         * A beat across: never under 48 px, so a brick is exactly its note's length and never covers
         * the next (31's finding: at a phone's width a beat had 20 px, or 8 for a long tune). A longer
         * exercise scrolls, the playhead held at 35% across.
         */
        // Labelled (the warm-up's short pattern), down to 22 px a beat so the whole pass fits, a phone's too; and to 16 when
        // that's what fits it whole (the five-note scale's held "oo" ran off a 390 px phone: the finish-line audit). A pass
        // too long even at 16 keeps 22 and scrolls, as before.
        pxPerBeat(vm) {
            const fit = ((vm.width || 320) - vm.edge - 8) / vm.span;
            if (!vm.labelled) return Math.max(MIN_BEAT_PX, fit);
            return fit >= LABELLED_SQUEEZE_PX ? fit : LABELLED_BEAT_PX;
        },
        scrollX() {
            const width = this.width || 320;
            const content = this.edge + 8 + this.pxPerBeat * this.span;
            if (content <= width || !this.running) return 0;
            return Math.max(0, Math.min(content - width, this.edge + this.passBeat(this.beatNow) * this.pxPerBeat - width * PLAYHEAD_AT));
        },
        /** The lane runs on past its right edge (a longer exercise, not yet scrolled to its end). */
        moreRight() {
            const width = this.width || 320;
            return this.edge + 8 + this.pxPerBeat * this.span - this.scrollX > width + 4;
        },
        rows: (vm) => Array.from({ length: Math.floor(vm.high + 0.5) - Math.ceil(vm.low - 0.5) + 1 }, (_, i) => Math.ceil(vm.low - 0.5) + i),
        /** The specks drifting on the stage (none with reduced motion: the CSS stops them). */
        specks: (vm) => (vm.labelled ? [] : Array.from({ length: 9 }, (_, i) => ({ left: `${(i * 37 + 11) % 100}%`, top: `${(i * 53 + 7) % 100}%`, width: `${2 + (i % 3)}px`, height: `${2 + (i % 3)}px`, animationDelay: `${-i * 2}s` }))),
        // ---- Wait for me's view ----
        waitIndex: (vm) => vm.waiting?.index ?? 0,
        waitNote: (vm) => vm.laid.guide[vm.waiting?.index ?? 0] ?? null,
        waitDone: (vm) => vm.waitFoundAll || !!vm.waiting?.done,
        waitFound: (vm) => (vm.waiting?.found ?? []).map((f) => f.index),
        /** Your voice in the note's octave (a man on an alto line sits on its note). */
        liveWait() {
            if (this.live === null || !this.waitNote) return null;
            return this.live + 12 * Math.round((this.waitNote.pitch - this.live) / 12);
        },
        onNote() {
            return this.running && this.liveWait !== null && Math.abs(this.liveWait - this.waitNote.pitch) * 100 <= (STRICTNESS[this.strictness] ?? STRICTNESS.relaxed);
        },
        /** The bar fills only while you're on the note. */
        fill: (vm) => (vm.waiting ? Math.min(1, vm.waiting.held / HOLD_TO_LIGHT) : vm.waitDone ? 1 : 0),
        beadX: () => 50,
        waitHeadline() {
            if (this.waitDone) return t('course.wait.done');
            if (!this.running) return t('course.wait.ready');
            return this.onNote ? t('course.wait.hold') : t('course.wait.find', { note: this.name(this.waitNote.pitch) });
        },
        waitLine() {
            const count = this.laid.guide.length;
            if (this.waitDone) return t('course.wait.doneLine', { count });
            if (!this.running) return t('course.wait.readyLine', { count });
            if (this.onNote) return t('course.wait.holdLine', { note: this.name(this.waitNote.pitch) });
            // The note's own sound when it has one ("oo", a word), never an "ah" the guide doesn't sing (the courses lead's
            // first-week read, 2026-11-14: lesson 2's guide sang "oo" under "Hum or sing 'ah'").
            const word = this.waitNote?.lyric?.trim();
            return word ? t('course.wait.findLine', { word }) : t('course.wait.findLineAny');
        },
        waitLabel() {
            return this.waitNote ? t('course.wait.label', { note: this.name(this.waitNote.pitch), percent: Math.round(this.fill * 100) }) : '';
        },
        /** The live voice, folded into the frame's octave (a man on a treble line sits on its notes). */
        liveShown() {
            if (this.live === null) return null;
            const c = this.camFrame.centre;
            const folded = this.live + 12 * Math.round((c - this.live) / 12);
            return Math.max(this.low + 0.3, Math.min(this.high - 0.3, folded)); // kept in the frame: at its edge when far off
        },
        phaseKey() {
            if (this.mode === 'try') return 'wait';
            return this.beatNow < this.laid.turnFrom - 1e-6 ? 'listen' : 'turn';
        },
        /** Something plays while the learner sings: through a speaker, the mic would hear it too. */
        /** The ring meter shows on a scored take of a `tuning` step, never through a speaker (the mic hears the chord too). */
        tuning: (vm) => !!vm.exercise.tuning && vm.mode === 'play' && !vm.speaker,
        soundsUnder: (vm) => vm.mode === 'try' || !!vm.exercise.drone || !!vm.exercise.chords || !!vm.exercise.with?.length || vm.underTakes.length > 0,
        /** The round button's icon says what it does (Hillel): Play to hear, the mic to sing. */
        startIcon: (vm) => (vm.mode === 'listen' ? 'play' : 'mic'),
        startLabel: (vm) => (vm.mode === 'listen' ? t('course.tap.listen') : vm.mode === 'try' ? t('course.tap.try') : t('course.tap.sing')),
        /**
         * The lane for a screen reader: the notes to sing, as before. With hidden lights (`off`,
         * `late`, `changes`) it never names what the lane hides, only how many notes to find by ear;
         * a note with `choices` names every choice; partner voices are counted.
         */
        label() {
            const lights = this.exercise.lights ?? 'on';
            const voices = this.laid.partners?.length ? t('harmony.laneVoices', { count: formatNumber(this.laid.partners.length) }) : '';
            if (this.mode === 'play' && ['off', 'late', 'changes'].includes(lights)) return [t('harmony.laneByEar', { count: formatNumber(this.laid.target.length) }), voices].filter(Boolean).join(' ');
            const notes = this.laid.target.map((n) => (n.choices ? t('harmony.laneChoice', { notes: n.choices.map((c) => this.name(c)).join(t('harmony.laneOr')) }) : this.name(n.pitch))).join(', ');
            return [t('course.notesLabel', { notes }), voices].filter(Boolean).join(' ');
        },
    },
    watch: {
        litTargets(count) {
            this.$emit('lit', count);
        },
    },
    created() {
        // The staff reader loads on demand (lesson-steps.js): fetched now, so it's there before a count-in, in any screen.
        if (this.exercise?.staff) ReadStave.prefetch();
    },
    mounted() {
        // The lane's width, kept as it changes: read once at the first render it was 0 (the box not
        // laid out yet), so the notes sat squashed in a corner until something redrew them.
        const measure = () => (this.width = this.$el?.clientWidth ?? 0);
        this.resize = new ResizeObserver(measure);
        this.resize.observe(this.$el);
        measure();
        document.fonts?.ready.then(measure, () => {});
    },
    unmounted() {
        this.resize?.disconnect();
        cancelAnimationFrame(this.frame);
        if (this.running) engine.stop();
        [...engine.parts.keys()].filter((id) => id.startsWith('ex:')).forEach((id) => engine.removePart(id));
    },
    methods: {
        /** Asked about headphones this visit (or told not to ask): the speaker's line is said only then (storage, so read each time). */
        answered: () => listening() !== null,
        /** Whether a brick's whole word (its syllable or its note's name) fits it. */
        labelFits(n) {
            return n.length * this.pxPerBeat - 2 >= String(this.noteWord(n)).length * (this.labelled ? 7 : LABEL_CHAR_PX) + LABEL_PAD_PX;
        },
        /** A note's name as the singer reads it; a solfège step's own syllable in a fixed-Do language (solfaAt). */
        name(pitch) {
            return this.solfaAt.get(Math.round(pitch)) ?? midiName(pitch);
        },
        headphonesOn(never) {
            tabStorage.set('stackapella.headphones', '1');
            if (never) local.set('stackapella.headphones.never', '1');
            this.asking = false;
            this.start();
        },
        useSpeaker() {
            tabStorage.set('stackapella.speaker', '1');
            this.asking = false;
            this.start();
        },
        x(beat) {
            return this.edge + this.passBeat(beat) * this.pxPerBeat - this.scrollX;
        },
        /** A beat's place in the pass drawn: one pass, the turn's beats fold back onto the notes heard. */
        passBeat(beat) {
            return this.onePass && beat >= this.laid.turnFrom - 1e-6 ? beat - this.laid.turnFrom : beat;
        },
        y(pitch) {
            return this.pad + (this.high - pitch) * this.rowH;
        },
        /** A note's place, for its brick (js/glass.js, drawn by Gems) and the words over it. */
        place(n) {
            return { x: this.x(n.beat), y: this.y(n.pitch) - this.barH / 2, w: this.x(n.beat + n.length) - this.x(n.beat) - BRICK_GAP, h: this.barH };
        },
        bar(n, i) {
            const { x, y, w, h } = this.place(n);
            const state = this.brickState(n, i);
            const ink = state.ghost ? 'color-mix(in oklab, var(--color-cream) 80%, transparent)' : state.dim ? 'var(--color-muted)' : gemInk(this.color);
            return { left: `${x}px`, width: `${w}px`, top: `${y}px`, height: `${h}px`, color: ink };
        },
        /** A note only to listen to (the guide before your turn) is dashed; one to sing is a gem, alive as it comes, is sung, or is missed. */
        brickState(n, i) {
            if (n.listenOnly) return { ghost: true };
            const toSing = this.mode === 'listen' || this.mode === 'try' || this.exercise.turn === false || i >= this.shown.length - this.laid.target.length;
            if (!toSing) return { ghost: true };
            const now = this.running && this.beatNow >= n.beat && this.beatNow < n.beat + n.length;
            const past = this.running && this.beatNow >= n.beat + n.length;
            const next = this.running && !now && n.beat > this.beatNow && n.beat - this.beatNow < 1.5 && !this.shown.some((m, k) => k !== i && m.beat > this.beatNow && m.beat < n.beat);
            const lit = this.litNow(i);
            return { dim: !lit && past && this.mode !== 'listen', rim: now, glow: now ? 0.8 : lit ? 0.25 : 0, next, hitAt: lit ? this.hitAt[i] : null };
        },
        /**
         * How a note is drawn under the exercise's `lights` (Improvising a harmony): "full", "faint"
         * (an outline with no word) or "hidden". Every note is still scored; a note you've lit is
         * always drawn in full. "fade": the second half of your notes faint; "changes": only the notes
         * that start on a chord change; "late": a note shows once you've been off it for a beat;
         * "off": none. Notes to listen to, and every note when only listening, are always drawn.
         */
        drawn(n, i) {
            const lights = this.exercise.lights ?? 'on';
            if (lights === 'on' || this.mode !== 'play' || i < this.firstTarget || n.listenOnly || this.litNow(i)) return 'full';
            const k = i - this.firstTarget;
            if (lights === 'fade') return k >= this.laid.target.length / 2 ? 'faint' : 'full';
            if (lights === 'changes') return this.changeBeats.has(n.beat) ? 'full' : 'hidden';
            if (lights === 'late') return this.running && this.beatNow >= n.beat + 1 ? 'full' : 'hidden';
            return 'hidden';
        },
        /** A note's word: its lyric or its name; none for a choice (its pitches are drawn) or during a cold start. */
        noteWord(n) {
            if (n.choices || (this.exercise.guide === false && !this.revealed && !n.listenOnly)) return n.lyric || '';
            return n.lyric || this.name(n.pitch);
        },
        /** A note with `choices`: a brick per pitch, the one you sang lit, any that would make parallel fifths or octaves dimmed. */
        choiceBricks(n, i) {
            const state = this.brickState(n, i);
            const open = this.openChoicesAt(i);
            const past = this.running && this.beatNow >= n.beat + n.length;
            return n.choices.map((pitch) => {
                const chosen = this.chosen[i] === pitch;
                return { ...this.place({ ...n, pitch }), color: this.color, ...state, dim: !open.includes(pitch) || (past && !chosen), glow: chosen ? 0.8 : state.rim ? 0.25 : 0, hitAt: chosen ? state.hitAt : null };
            });
        },
        /** The choices still open at shown note `i`, after the note you sang before it (js/harmony.js), against the first partner line. */
        openChoicesAt(i) {
            const n = this.shown[i];
            const k = i - this.firstTarget;
            const cantus = this.laid.partners?.[0]?.notes;
            // Only Counterpoint's choice steps dim (no chords, no drone): elsewhere the partner is the tune, and the words say
            // every choice works (the courses audit, harmony.md F9).
            if (!cantus || k <= 0 || this.exercise.chords || this.exercise.drone) return n.choices;
            const before = this.laid.target[k - 1];
            const now = pitchAt(cantus, n.beat + n.length / 2);
            const then = pitchAt(cantus, before.beat + before.length / 2);
            if (now === null || then === null) return n.choices;
            return openChoices(n.choices, now, { sung: this.chosen[i - 1] ?? null, cantus: then });
        },
        /** The pitch the live voice is on for target note `k` (any open choice), or null. */
        landedOn(k, cents) {
            const n = this.laid.target[k];
            const options = n.choices ? this.openChoicesAt(this.firstTarget + k) : [n.pitch];
            // Of the options the voice is on, the octave nearest it (low do and high do are both on do: harmony.md F6).
            const on = options.filter((p) => onNote(this.live, p, cents));
            return on.length ? on.reduce((best, p) => (Math.abs(this.live - p) < Math.abs(this.live - best) ? p : best)) : null;
        },
        /** Room for the word and a tick (a short note shows its word alone, centred). */
        wide(n) {
            return this.x(n.beat + n.length) - this.x(n.beat) >= 72;
        },
        litNow(i) {
            return this.lit.has(i);
        },
        /** Wait for me's pills: a note found (or every note, once done). */
        pillDone(i) {
            return this.waitDone || this.waitFound.includes(i);
        },
        /** Wait for me's lane: a pitch's height, the note in the middle and six semitones either way. */
        waitY(midi) {
            const off = Math.max(-6, Math.min(6, midi - this.waitNote.pitch));
            return 100 - off * 13;
        },
        /** Wait for me again, from the first note. */
        async startOver() {
            if (this.running) await this.stop();
            this.waitFoundAll = false;
            this.start();
        },
        /** A note lit just now: its sparkles' start. */
        markHit(i) {
            this.hitAt = { ...this.hitAt, [i]: performance.now() };
        },
        /** "Nice!" or "Perfect!" over a note just found (still, with reduced motion). */
        /**
         * The combo bonus (docs/game-elements-plan.md; the coordinator's rule; docs/game-feel-plan.md): each
         * note lit while a run of 10 or more holds adds the bonus's rate in XP to this exercise's award (1,
         * then 2 from 20 in a row and 3 from 30), in either skin (XP is the same in both); game mode's
         * pop-ups say "10 in a row!", "Bonus x2!" as the rate goes up, and "Combo bonus +N". `at`: seconds in.
         */
        comboStep(at) {
            const milestone = effect('popups') && comboMilestone(this.combo);
            // Where the bonus is paid, its rate going up says "Bonus x2!" in the combo's place (20 and 30 in a row).
            const raised = this.earnsBonus && rateRaised(this.combo);
            if (milestone) this.hud.offer(raised ? { kind: 'rate', count: bonusRate(this.combo) } : { kind: 'combo', count: this.combo }, at);
            // Only where the bonus is paid (a lesson's own step, `earnsBonus`), never the workout's rounds.
            if (!this.earnsBonus || this.combo < BONUS_FROM) return;
            this.bonus += bonusRate(this.combo);
            // Said only as far as today's cap still pays it; seen, not announced (its combo was).
            const shown = Math.min(this.bonus, bonusRoom(learning.progress));
            if (milestone && shown > 0) this.hud.offer({ kind: 'bonus', count: shown, quiet: true }, at);
        },
        cheerAt(i, cents) {
            if (!game()) return; // praise is game mode's (docs/skins-plan.md); the lit note says it
            const n = this.shown[i];
            this.cheer = { at: performance.now(), word: praise(cents), x: this.x(n.beat + n.length / 2), y: Math.max(0, this.y(n.pitch) - 30), still: reducedMotion() };
        },
        // ---- Wait for me: one note at a time, each placed on the audio clock when the last is found ----
        /** Give me my note (`giveNote` on a step sung on your own): the note now or next, quietly, once, over the take; it never costs stars. */
        giveNote() {
            const note = noteToGive(this.laid.target, this.beatNow);
            if (!note) return;
            const samples = renderPart([{ beat: 0, length: 1, pitch: note.pitch }], { bpm: 60, sampleRate: engine.sampleRate });
            for (let i = 0; i < samples.length; i++) samples[i] *= GIVEN_NOTE_LEVEL;
            engine.preview(samples, engine.sampleRate);
        },
        playNote(index) {
            const sr = engine.sampleRate;
            const bpm = this.exercise.tempo ?? 72;
            const note = this.laid.guide[index];
            // Through a speaker, the note plays for a beat and goes quiet, so the mic hears only you.
            const length = this.speaker ? Math.min(note.length, 1) : HELD_BEATS;
            engine.setPart('ex:guide', renderPart([{ beat: 0, length, pitch: note.pitch }], { bpm, sampleRate: sr, human: 'guide' }), sr, { volume: 0.7, pan: 0, audible: true, offsetSeconds: 0 });
            if (this.exercise.drone && !this.speaker) {
                engine.setPart('ex:drone', renderPart([{ beat: 0, length: HELD_BEATS, pitch: this.base + this.exercise.drone.step }], { bpm, sampleRate: sr }), sr, { volume: 0.45, pan: 0, audible: true, offsetSeconds: 0 });
            }
            engine.play({ fromSeconds: 0, bpm, leadSeconds: leadSeconds() });
            this.position = note.beat * this.beatSeconds;
        },
        skipNote() {
            if (this.waiting.index === this.laid.guide.length - 1) {
                this.stop();
                return;
            }
            this.waiting = { ...startWaiting(), index: this.waiting.index + 1, best: this.waiting.best, found: this.waiting.found };
            this.combo = 0;
            this.playNote(this.waiting.index);
        },
        /**
         * Partner lines (`with`) sound on the engine's clock, like the drone; through a speaker they rest from `until` (the
         * scored turn), but for calls in your rests (speakerPartnerNotes), kept in `callSpans` so the mic's hearing of them
         * is never taken for you.
         */
        setPartners(until, { bpm, sampleRate }) {
            this.callSpans = [];
            (this.laid.partners ?? []).forEach((line, i) => {
                const { notes, calls } = speakerPartnerNotes(line.notes, this.laid.target, until, this.beatSeconds);
                this.callSpans.push(...calls);
                if (notes.length) engine.setPart(`ex:with${i}`, renderPart(notes, { bpm, sampleRate, human: 'guide', seed: 20 + i }), sampleRate, { volume: line.volume, pan: 0, audible: true, offsetSeconds: 0 });
            });
        },
        /**
         * Hear it together: your take with every partner line, an earlier take you sang over
         * (`under`), and the drone you sang against (onboarding step 7: "you and one other note,
         * ringing"), mixed in on the same timeline (frame 0 is beat 0); a take at another sample rate
         * is converted, never assumed.
         */
        together(take) {
            const bpm = this.exercise.tempo ?? 72;
            const samples = Float32Array.from(take.samples);
            const span = this.exercise.drone ? droneSpan(this.laid, this.exercise, this.laid.end) : null;
            const drone = span ? { from: Math.max(0, span.from), to: span.to } : null; // the take is from beat 0: the lead's drone isn't in it
            if (drone && drone.to > drone.from) {
                const voice = renderPart([{ beat: drone.from, length: drone.to - drone.from, pitch: this.base + this.exercise.drone.step }], { bpm, sampleRate: take.sampleRate });
                const { fadeAfter, fadeOver } = this.exercise.drone;
                if (fadeAfter != null) fadeOut(voice, take.sampleRate, fadeAfter * this.beatSeconds, (fadeOver ?? 4) * this.beatSeconds);
                for (let i = 0; i < Math.min(samples.length, voice.length); i++) samples[i] += voice[i] * DRONE_UNDER;
            }
            (this.laid.partners ?? []).forEach((line) => {
                const voice = renderPart(line.notes, { bpm, sampleRate: take.sampleRate });
                for (let i = 0; i < Math.min(samples.length, voice.length); i++) samples[i] += voice[i] * line.volume;
            });
            if (this.underTakes.length) {
                // Every layer, looped as it played (frame 0 is beat 0 for the take and the layers alike).
                const under = underMix(this.underTakes, { beatSeconds: this.beatSeconds, sampleRate: take.sampleRate, seconds: samples.length / take.sampleRate });
                for (let i = 0; i < Math.min(samples.length, under.length); i++) samples[i] += under[i];
            }
            // Several voices on top of yours can pass full scale: brought down together, never clipped.
            const peak = samples.reduce((max, v) => Math.max(max, Math.abs(v)), 0);
            if (peak > MIX_PEAK) for (let i = 0; i < samples.length; i++) samples[i] *= MIX_PEAK / peak;
            return { samples, sampleRate: take.sampleRate };
        },
        /**
         * Two lines, measured (js/harmony.js): the notes you were pulled onto a partner's (only when
         * you could hear them: never on a speaker), the line you chose, its checklist against the
         * first partner, and your longest run of notes on pitch.
         */
        harmonyOf(track, target, ownLine) {
            const partners = (this.laid.partners ?? []).map((line) => line.notes);
            const chose = target.some((n) => n.choices);
            if (!partners.length && !chose) return null;
            const pulled = partners.length && !this.speaker ? pulls(track, target, partners, { beatSeconds: this.beatSeconds, cents: STRICTNESS[this.strictness] ?? STRICTNESS.relaxed }) : null;
            const result = { pulls: pulled, ownLine, withPartners: partners.length > 0, heardPartners: partners.length > 0 && !this.speaker };
            if (chose && partners.length) {
                const line = target.map((n) => n.pitch);
                const cantus = target.map((n) => pitchAt(partners[0], n.beat + n.length / 2));
                if (cantus.every((p) => p !== null)) result.checks = { ...speciesChecks(line, cantus), motion: compareLines(line, cantus).counts };
            }
            return result;
        },
        /** A live reading's note, moved for a whistle to the octave nearest `around` (js/whistle.js foldLive). */
        foldLive(midi, around) {
            return this.exercise.whistle ? foldLive(midi, around) : midi;
        },
        startWaiting() {
            this.waiting = startWaiting();
            this.combo = 0;
            this.playNote(0);
            const cents = STRICTNESS[this.strictness] ?? STRICTNESS.relaxed;
            let last = performance.now();
            const tick = () => {
                if (!this.waiting) return; // stopped, or left: nothing to wait for
                const now = performance.now();
                const dt = Math.min(0.1, (now - last) / 1000);
                last = now;
                const frame = engine.inputFrame();
                const reading = frame ? trackFrame(frame, engine.sampleRate, this.liveRange) : null;
                this.live = reading?.hz ? this.foldLive(hzToMidi(reading.hz), this.laid.guide[this.waiting.index]?.pitch) : null;
                this.voiced(reading);
                const { state, event } = heardFrame(this.waiting, this.laid.guide, this.live, dt, cents);
                if (event) {
                    const found = state.found.at(-1);
                    this.lit = new Set([...this.lit, found.index]);
                    this.markHit(found.index);
                    this.cheerAt(found.index, found.cents);
                    this.combo = state.combo;
                    this.bestCombo = Math.max(this.bestCombo ?? 0, this.combo);
                    this.comboStep((performance.now() - this.startedAt) / 1000);
                }
                this.waiting = state;
                if (event === 'done') {
                    this.stop();
                    return;
                }
                if (event === 'lit') this.playNote(state.index);
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        async start({ guided = false } = {}) {
            this.error = null;
            this.cheer = null;
            this.combo = 0;
            this.bonus = 0;
            this.hud.clear(0);
            this.withGuide = false;
            // Wait for me (try) is never asked: unanswered, it goes as through a speaker, each note playing
            // and then quiet while you sing, which suits any setup (onboarding review 2, 8: lesson 2 asked
            // with nothing playing under you).
            const askAgain = !!this.headphonesFor && listening() === 'speaker' && tabStorage.get(HEADPHONES_ASKED) !== this.headphonesFor;
            if (this.mode !== 'listen' && this.mode !== 'try' && this.soundsUnder && (!listening() || askAgain)) {
                if (this.headphonesFor) tabStorage.set(HEADPHONES_ASKED, this.headphonesFor);
                this.asking = true;
                return;
            }
            this.speaker = listening() === 'speaker' || (this.mode === 'try' && !listening());
            try {
                await unlock();
                if (this.mode !== 'listen' && !(await readyMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.start());
                return;
            }
            // Singing with the guide only once nothing can stop the take: a blocked start never leaves the next one guided.
            this.withGuide = guided && this.mode === 'play';
            const sr = engine.sampleRate;
            const bpm = this.exercise.tempo ?? 72;
            const { guide, target, end } = this.laid;
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null; // a project opened later loads its own parts again
            if (this.mode === 'try') {
                this.lit = new Set();
                this.hitAt = {};
                this.running = true;
                this.startedAt = performance.now();
                this.startWaiting();
                return;
            }
            // The guide sings the notes to listen to, and any listen-only ones (heardNotes).
            const heard = this.withGuide ? [...heardNotes(this.laid, this.exercise, this.mode), ...target] : heardNotes(this.laid, this.exercise, this.mode);
            if (heard.length) engine.setPart('ex:guide', renderPart(heard, { bpm, sampleRate: sr, human: 'guide' }), sr, { volume: this.mode === 'try' ? 0.6 : 1, pan: 0, audible: true, offsetSeconds: 0 });
            // Through a speaker, the drone and chords rest while a scored turn is sung: the mic must hear only the voice.
            const under = this.speaker && this.mode === 'play' ? this.laid.turnFrom : end;
            // A reading line shows still for its `look` seconds first, on the audio clock (the lead before beat 0).
            const look = this.staffStep ? Math.max(0, Math.min(10, this.exercise.staff.look ?? 4)) : 0;
            const drone = droneSpan(this.laid, this.exercise, under, look / this.beatSeconds + this.countBeats);
            if (this.exercise.drone && drone.to > drone.from) {
                const lead = Math.max(0, -drone.from); // beats of it before beat 0, in the lead
                const samples = lead
                    ? renderPart([{ beat: 0, length: drone.to - drone.from, pitch: this.base + this.exercise.drone.step }], { bpm, sampleRate: sr })
                    : renderPart([{ beat: drone.from, length: drone.to - drone.from, pitch: this.base + this.exercise.drone.step }], { bpm, sampleRate: sr });
                // `fadeAfter` and `fadeOver` (beats from beat 0): the drone fades away while you sing on.
                const { fadeAfter, fadeOver } = this.exercise.drone;
                if (fadeAfter != null) fadeOut(samples, sr, (fadeAfter + lead) * this.beatSeconds, (fadeOver ?? 4) * this.beatSeconds);
                engine.setPart('ex:drone', samples, sr, { volume: 0.55, pan: 0, audible: true, offsetSeconds: -lead * this.beatSeconds, lead: lead > 0 });
            }
            this.setPartners(under, { bpm, sampleRate: sr });
            // Earlier takes of yours beneath this one (Live looping's layers), to `under`: through a speaker they play
            // until the turn, then rest, as the drone does, so the loop is heard first (the scoring audit, 2026-11-08:
            // they used to play nothing at all through a speaker).
            if (this.underTakes.length && under > 0) {
                const mixed = underMix(this.underTakes, { beatSeconds: this.beatSeconds, sampleRate: sr, seconds: under * this.beatSeconds });
                const layers = mixed.subarray(0, Math.min(mixed.length, Math.round(under * this.beatSeconds * sr))); // a loop longer than the wait stops at the turn
                if (layers.length) engine.setPart('ex:under', layers, sr, { volume: 1, pan: 0, audible: true, offsetSeconds: 0 }); // each layer's own volume is in the mix
            }
            if (this.exercise.chords && under > 0) {
                engine.setPart('ex:chords', renderBacking(this.exercise.chords, { bpm, sampleRate: sr, endBeat: under, transpose: ((this.base % 12) + 12) % 12 }), sr, { volume: 0.8, pan: 0, audible: true, offsetSeconds: 0 });
            }
            this.lit = new Set();
            this.hitAt = {};
            this.chosen = {};
            this.revealed = false;
            this.ring = null;
            this.ringPoints = [];
            this.running = true;
            this.startedAt = performance.now();
            const record = this.mode === 'play' && !this.withGuide; // trying (or singing with the guide) is unscored: the live line reads the mic itself
            // Trying along through a speaker, the mic hears the guide as well: no notes light then.
            // Singing with the guide lights nothing: no combo, cheers or badges from a guided run.
            const live = this.mode !== 'listen' && !(this.speaker && this.mode === 'try') && !this.withGuide;
            // Rhythm (`staff.count`): a count-in after the look, clicks (game mode's blips) on the audio clock before beat 0.
            const time = String(this.exercise.staff?.time ?? '4/4').split('/').map(Number);
            const beatsPerBar = time[1] === 8 ? time[0] / 3 : time[0];
            engine.play({ fromSeconds: 0, bpm, record, latencyFrames: latencyNow(), leadSeconds: leadSeconds() + look, countInBeats: this.countBeats, countInSounds: this.countBeats ? chipCountIn() : null, beatsPerBar });
            const cents = STRICTNESS[this.strictness] ?? STRICTNESS.relaxed;
            const firstTarget = this.shown.length - target.length;
            const counted = new Set(); // the target notes already gone by
            const tick = () => {
                const position = engine.positionSeconds();
                if (position === null) return;
                this.position = Math.max(0, position);
                this.lead = position < 0 ? -position : 0; // the look before a reading line starts
                this.hud.tick(this.position);
                if (this.position >= end * this.beatSeconds) {
                    this.stop();
                    return;
                }
                // Live: the pitch heard now, and the note it lights (your turn only).
                const frame = live ? engine.inputFrame() : null;
                // A partner's call through the speaker, in your rest, is the mic hearing the call: not you.
                const inCall = this.callSpans?.some(([a, b]) => this.beatNow >= a && this.beatNow * this.beatSeconds < b * this.beatSeconds + CALL_CLEAR_AFTER);
                const reading = frame && !inCall ? trackFrame(frame, engine.sampleRate, this.liveRange) : null;
                const at = target.findIndex((n) => this.beatNow >= n.beat && this.beatNow < n.beat + n.length);
                // A whistle's dot is moved to the octave of the note now (or next): its take is folded the same way.
                const around = (at >= 0 ? target[at] : target.find((n) => n.beat >= this.beatNow) ?? target.at(-1))?.pitch;
                this.live = reading?.hz ? this.foldLive(hzToMidi(reading.hz), around) : null;
                this.voiced(reading);
                if (this.tuning) this.readRing(at >= 0 ? target[at] : null);
                const landed = at >= 0 && this.live !== null && !this.coldTurn && !this.lightsOff && !this.lit.has(firstTarget + at) ? this.landedOn(at, cents) : null;
                if (landed !== null) {
                    this.lit = new Set([...this.lit, firstTarget + at]);
                    if (target[at].choices) this.chosen = { ...this.chosen, [firstTarget + at]: landed };
                    this.markHit(firstTarget + at);
                    this.combo++;
                    this.bestCombo = Math.max(this.bestCombo ?? 0, this.combo);
                    this.comboStep(this.position);
                    this.cheerAt(firstTarget + at, (this.live - landed) * 100);
                }
                // A note gone by without lighting starts the combo again, quietly.
                target.forEach((n, k) => {
                    if (counted.has(k) || this.beatNow < n.beat + n.length) return;
                    counted.add(k);
                    if (!this.lit.has(firstTarget + k)) this.combo = 0;
                });
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        /**
         * Hear it first (sight-singing's `hearFirst`): the line played as written, once, on the audio
         * clock. The next take can't take the top score (said on its result), then it's back to cold.
         */
        async hearItFirst() {
            if (this.running || this.hearing) return;
            try {
                await unlock();
            } catch (error) {
                console.warn('Audio not ready', error);
                return;
            }
            const sr = engine.sampleRate;
            const bpm = this.exercise.tempo ?? 72;
            const target = this.laid.target;
            const from = target[0]?.beat ?? 0;
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null;
            engine.setPart('ex:hear', renderPart(target.map((n) => ({ ...n, beat: n.beat - from })), { bpm, sampleRate: sr }), sr, { volume: 1, pan: 0, audible: true, offsetSeconds: 0 });
            this.heardFirst = true;
            this.hearing = true;
            const end = (target.at(-1).beat + target.at(-1).length - from) * (60 / bpm);
            engine.play({ fromSeconds: 0, bpm, leadSeconds: leadSeconds() });
            const watch = async () => {
                const at = engine.positionSeconds();
                if (at === null || at >= end + 0.3) {
                    this.hearing = false;
                    engine.removePart('ex:hear');
                    try {
                        await engine.stop();
                    } catch (error) {
                        console.warn('Hear it first stopped early', error);
                    }
                    return;
                }
                this.frame = requestAnimationFrame(watch);
            };
            this.frame = requestAnimationFrame(watch);
        },
        /** Sing it with the guide: the next take has the guide singing your notes too, and isn't scored. */
        singWithGuide() {
            this.start({ guided: true });
        },
        /**
         * Tune the chord in a lesson (exercise.tuning): the held note (a beat or more) against its pure
         * target in the step's chord, from the last second of your voice, as Sing's meter reads it.
         * A note's own `cents` is its target when it has one, since that's what the step scores.
         */
        readRing(note) {
            const seconds = this.position;
            this.ringPoints.push({ seconds, midi: this.live ?? 0, alpha: this.live === null ? 0 : 1 });
            while (this.ringPoints[0].seconds < seconds - 1) this.ringPoints.shift();
            const chord = note && !note.listenOnly && note.length >= RING.held ? this.chordAt(note.beat) : null;
            if (!chord) {
                this.ring = null;
                return;
            }
            const piano = Math.round(note.pitch);
            const bs = this.beatSeconds;
            const reading = ringReading(this.ringPoints, { now: seconds, note: { start: note.beat * bs, end: (note.beat + note.length) * bs, pitch: piano }, chord });
            const own = Math.round((note.pitch - piano) * 100);
            this.ring = reading && own !== 0 ? judged({ ...reading, pure: own }) : reading;
        },
        /**
         * The chord under `beat` (pitch classes): the step's `chords` (written from C, sung in the
         * learner's key), else what the partner lines and your note sing there, else the drone's note
         * as a root (so a held fifth or octave reads against it).
         */
        chordAt(beat) {
            const key = ((Math.round(this.base) % 12) + 12) % 12;
            const chords = this.exercise.chords ?? [];
            if (chords.length) {
                const c = chords.filter((ch) => ch.beat <= beat + 1e-6).at(-1);
                return c ? { root: (c.root + key) % 12, quality: c.quality, bass: c.bass == null ? null : (c.bass + key) % 12 } : null;
            }
            if (this.laid.partners?.length) {
                // The partner lines and your own note together (a barbershop seventh is yours: without it, a triad).
                const lines = [...this.laid.partners.map((p) => p.notes), this.laid.target];
                return chordAt({ parts: lines.map((notes, i) => ({ key: `line-${i}`, guide: { notes: notes.map((n) => ({ ...n, pitch: Math.round(n.pitch) })) } })) }, beat);
            }
            if (this.exercise.drone) return { root: (((Math.round(this.base) + this.exercise.drone.step) % 12) + 12) % 12, quality: 'maj', bass: null };
            return null;
        },
        /** Voice care's day (js/voice-day.js): the time between readings counts while a voice is heard. */
        voiced(reading) {
            const now = performance.now();
            if (reading?.hz && this.voicedAt) addSung(Math.min(0.1, (now - this.voicedAt) / 1000));
            this.voicedAt = now;
        },
        async stop() {
            this.voicedAt = null;
            flushSung();
            cancelAnimationFrame(this.frame);
            const finished = this.mode === 'try' ? !!this.waiting?.done : this.position >= this.laid.end * this.beatSeconds - 0.6;
            if (this.mode === 'try') this.waitFoundAll = finished;
            this.running = false;
            this.revealed = true; // a cold start's notes show their names once the turn is over
            this.waiting = null;
            // For the badges: the notes found, and the longest run on pitch.
            if (this.mode !== 'listen' && this.lit.size) {
                counted('notesLit', { add: this.lit.size })
                    .then(() => counted('bestCombo', { best: this.bestCombo ?? 0 }))
                    .catch((error) => console.error('Not counted', error));
            }
            const ownLine = this.bestCombo ?? 0;
            this.bestCombo = 0;
            let take = null;
            try {
                take = await engine.stop();
            } catch (error) {
                console.warn('Stopped before the recording began', error);
            }
            this.live = null;
            const seconds = (performance.now() - this.startedAt) / 1000;
            if (this.mode !== 'listen') this.$emit('practised', seconds);
            if (this.withGuide) {
                this.withGuide = false; // sung with the guide: practice, never scored
                return;
            }
            const firstTarget = this.shown.length - this.laid.target.length;
            const sung = [...this.lit].filter((i) => i >= firstTarget).map((i) => this.chosen[i] ?? this.shown[i].pitch);
            if (this.mode !== 'play' || !take || !finished) {
                if (this.mode !== 'play' || finished) this.$emit('done', { percent: null, sung });
                return;
            }
            this.scoring = true;
            try {
                const track = withoutCalls(await trackInWorker(take.samples, take.sampleRate, rangeFor(this.exercise)), this.callSpans ?? [], this.beatSeconds);
                const { notes, score, timed, target, cents, dots, own, percent, minStars, whistled } = scorePlayTake({ track, laid: this.laid, exercise: this.exercise, beatSeconds: this.beatSeconds, strictness: this.strictness, personal: this.personal, speaker: this.speaker, latencyMeasured: audio.latencyFrames != null });
                // For the result (ux-spec 5.8): the turn's notes in seconds, your pitch as dots against
                // them, the longest hold, the notes lit, and the take to listen back to.
                const chart = { target, cents, dots, hold: longestHold(dots), lit: (score.notes ?? []).filter((n) => n.level === 'lit').length, take: this.laid.partners || this.underTakes.length || this.exercise.drone ? this.together(take) : { samples: take.samples, sampleRate: take.sampleRate }, together: !!(this.laid.partners || this.underTakes.length || this.exercise.drone) };
                const vibrato = (score.notes ?? []).map((n) => n.vibrato).find(Boolean) ?? null;
                this.weakTakes = percent === null || percent < WEAK_TAKE ? this.weakTakes + 1 : 0;
                // Then and now: lesson 1's first heard take, kept on this device for the course's finale.
                if (this.exercise.keep && percent !== null) keepFirstTake(this.exercise.keep, take).catch((error) => console.error('Then and now not kept', error));
                // Heard first: this take can't take the top score, and its result says why (no "stars": both skins).
                const heardFirst = this.heardFirst;
                this.heardFirst = false;
                // The percent itself is held under the top score, so the number and the score agree in both skins.
                const shownPercent = heardFirst && percent !== null ? Math.min(percent, HEARD_FIRST_MOST) : percent;
                this.$emit('done', { maxStars: heardFirst ? 2 : null, heardFirst, offerGuide: this.weakTakes >= 2, percent: shownPercent, word: score.word, tips: own?.tip ? [own.tip, ...(score.tips ?? [])] : score.tips ?? [], insights: score.insights ?? [], tendency: score.tendency ?? null, comboBonus: this.bonus, sung, chart, vibrato, harmony: this.harmonyOf(track, notes, ownLine), take: { samples: take.samples, sampleRate: take.sampleRate }, personal: own?.personal ?? null, line: own?.line ?? timed?.line ?? null, label: own?.label ?? timed?.label ?? null, minStars, foldSaid: sayFoldOnce(whistled) });
            } catch (error) {
                console.error('Scoring failed', error);
                this.$emit('done', { percent: null, sung });
            } finally {
                this.scoring = false;
            }
        },
    },
};

