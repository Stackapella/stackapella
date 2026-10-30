// Bullseye (docs/courses/perfect-pitch.md, "The fun"): each note you landed is a dart on a target.
// A course step of its own (`bullseye`, registered in js/lesson-steps.js): the guide sings a note or
// a short group, you sing it back, and each of your notes lands on the board as you finish it.
// How far a dart is from the middle is how far the note landed from the target (the gold is ±10
// cents, then ±25, ±50, and the rim a semitone, where anything further pins, never off the board);
// darts above the middle were sharp, below flat. A note that slid in trails a line from where it
// came from. Never red: a dart outside ±50 is glass. Earlier darts (then and now) show faint
// behind. The landings are pitch-skills.js's.

import { audio, engine, leadSeconds, unlock } from '../audio.js';
import { readyMic } from '../mic-check.js';
import { formatNumber, localized, t, tk } from '../i18n.js';
import { trackFrame, trackInWorker } from '../pitchtrack.js';
import { renderBacking, renderPart } from '../synth.js';
import { Icon } from './icon.js';
import { showMicHelp } from '../mic-help.js';
import { LISTEN_CLARITY } from '../listen.js';
import { HeadphonesDialog } from './dialogs.js';
import { KeyControl } from './key-control.js';
import { local, session as tabStorage } from '../storage.js';
import { landing, landingPercent, NAMED_MISS, skillPercent } from '../pitch-skills.js';
import * as db from '../db.js';

// Ring edges as fractions of the board's radius, and the cents each one stands for.
export const RINGS = [
    { cents: 10, r: 0.2 },
    { cents: 25, r: 0.45 },
    { cents: 50, r: 0.7 },
    { cents: 100, r: 0.95 },
];
const PINNED = 0.97; // further than a semitone: pinned just inside the rim
const SPREAD = Math.PI / 3; // darts fan up to 60 degrees either side of straight up or down
const TRAIL = 0.22; // a scoop's trail, as a share of the radius

/** How far from the middle a landing of `cents` sits (0 to 1), through the rings. */
export function dartRadius(cents) {
    const off = Math.abs(cents);
    let inner = { cents: 0, r: 0 };
    for (const ring of RINGS) {
        if (off <= ring.cents) return inner.r + ((off - inner.cents) / (ring.cents - inner.cents)) * (ring.r - inner.r);
        inner = ring;
    }
    return PINNED;
}

/**
 * Where the `index`th of `count` darts lands on a board of radius 1 (y down, as SVG): its distance
 * is how far off it was, sharp above and flat below; darts fan out sideways so each can be seen.
 * A scoop's trail points down (it came from below), a fall's up.
 */
export function dart({ cents, start }, index = 0, count = 1) {
    const radius = dartRadius(cents);
    const fan = count > 1 ? (index / (count - 1) - 0.5) * 2 * SPREAD : 0;
    const up = cents >= 0 ? -1 : 1; // sharp is up
    const x = radius * Math.sin(fan);
    const y = up * radius * Math.cos(fan);
    const ring = RINGS.findIndex((r) => Math.abs(cents) <= r.cents);
    const trail = start === 'scoop' ? { x, y: y + TRAIL } : start === 'fall' ? { x, y: y - TRAIL } : null;
    return { x, y, ring: ring < 0 ? RINGS.length : ring, trail, glass: Math.abs(cents) > 50 };
}

const WORDS = {
    on: tk('bullseye.word.on'),
    hairSharp: tk('bullseye.word.hairSharp'),
    hairFlat: tk('bullseye.word.hairFlat'),
    littleSharp: tk('bullseye.word.littleSharp'),
    littleFlat: tk('bullseye.word.littleFlat'),
    nearSharp: tk('bullseye.word.nearSharp'),
    nearFlat: tk('bullseye.word.nearFlat'),
    farSharp: tk('bullseye.word.farSharp'),
    farFlat: tk('bullseye.word.farFlat'),
};

/** Plain words for a landing, cents small beside them (the game review: "180 cents" means nothing). */
export function landingWord(cents) {
    const off = Math.abs(cents);
    const sharp = cents >= 0;
    if (off <= 10) return WORDS.on;
    if (off <= 25) return sharp ? WORDS.hairSharp : WORDS.hairFlat;
    if (off <= 50) return sharp ? WORDS.littleSharp : WORDS.littleFlat;
    if (off <= 100) return sharp ? WORDS.nearSharp : WORDS.nearFlat;
    return sharp ? WORDS.farSharp : WORDS.farFlat;
}

export const Bullseye = {
    props: {
        landings: { type: Array, required: true }, // pitch-skills.js landing() results, null for unheard
        before: { type: Array, default: () => [] }, // an earlier round's, shown faint (then and now)
        color: { type: String, default: 'var(--color-part-2)' },
    },
    setup: () => ({ RINGS, t }),
    template: `
        <figure class="mx-auto w-full max-w-xs" data-bullseye>
            <svg viewBox="-110 -118 220 236" class="w-full" role="img" :aria-label="label">
                <circle v-for="(ring, i) in [...RINGS].reverse()" :key="ring.cents" :r="ring.r * 100" fill="var(--color-raised)" :fill-opacity="0.35 + i * 0.15" stroke="var(--color-line)" stroke-width="1" />
                <circle :r="RINGS[0].r * 100" fill="var(--color-accent)" fill-opacity="0.45" stroke="none" />
                <text x="0" y="-104" text-anchor="middle" fill="var(--color-muted)" font-size="9" font-weight="600" aria-hidden="true">{{ t('bullseye.sharp') }}</text>
                <text x="0" y="114" text-anchor="middle" fill="var(--color-muted)" font-size="9" font-weight="600" aria-hidden="true">{{ t('bullseye.flat') }}</text>
                <g v-for="(d, i) in ghosts" :key="'b' + i" opacity="0.3">
                    <circle :cx="d.x * 100" :cy="d.y * 100" r="4.5" fill="var(--color-muted)" />
                </g>
                <g v-for="(d, i) in darts" :key="i">
                    <line v-if="d.trail" :x1="d.trail.x * 100" :y1="d.trail.y * 100" :x2="d.x * 100" :y2="d.y * 100" :stroke="color" stroke-width="2" stroke-linecap="round" stroke-opacity="0.5" />
                    <circle :cx="d.x * 100" :cy="d.y * 100" r="6.5" :fill="color" :fill-opacity="d.glass ? 0.2 : 0.95" :stroke="d.glass ? color : 'var(--color-cream)'" stroke-width="1.5" />
                </g>
            </svg>
        </figure>`,
    computed: {
        heard: (vm) => vm.landings.filter(Boolean),
        darts: (vm) => vm.heard.map((l, i) => dart(l, i, vm.heard.length)),
        ghosts: (vm) => vm.before.filter(Boolean).map((l, i, all) => dart(l, i, all.length)),
        label: (vm) => t('bullseye.label', { gold: vm.heard.filter((l) => Math.abs(l.cents) <= 10).length, inside: vm.heard.filter((l) => Math.abs(l.cents) <= 25).length, count: vm.heard.length }),
    },
};

// ---- The step: echo the guide, one note or group at a time ----

const THINK = 0.5; // beats of quiet between the guide's call and your answer (the exercise's `think`)
const AFTER = 1; // beats after your answer before the next call
const CUE_BEATS = 2; // a key's I chord before a call, a chord heard alone, a drone revealing a note from memory

/** Live readings a second, from their own times (one a frame: 60 a second, 120 on a fast screen, fewer in a busy tab). */
function liveRate(readings) {
    const span = readings.at(-1).at - readings[0].at;
    return span > 0 ? (readings.length - 1) / span : 60;
}

/** Headphones on (this visit, or "don't remind me"), the speaker chosen, or not asked yet (null). */
function listening() {
    if (tabStorage.get('stackapella.headphones') === '1' || local.get('stackapella.headphones.never') === '1') return 'headphones';
    return tabStorage.get('stackapella.speaker') === '1' ? 'speaker' : null;
}

/**
 * An echo exercise laid out in beats: `{ calls, answers, groups, end }`. `echoes` is a list of
 * groups in steps from your note: a number is one note, an array a few notes you sing back as they
 * were, and `{ call, sing, chord?, key? }` a call answered by different notes (a leap from the
 * guide's note, the third of a chord, home after a phrase). `chord` (`{ root, quality }`, the root
 * in steps from your note) sounds under the call and your answer; with no `call` notes it sounds
 * alone for two beats. Each note lasts `lengths[i]` beats (default 1, a group's last 1.5; a group's
 * own `lengths` set its call's rhythm). The
 * guide sings each call, `think` beats of quiet, then you sing your answer, then a beat's rest.
 * With the exercise's `home`, a group starts with two beats of its key's I chord (`key`, in steps,
 * default 0), so every leap is heard inside a key; with `reveal`, a drone on the key's do sounds
 * for two beats after your answer (`reveal: "last"`: after the last answer only, so every try is cold) (a note sung from memory, shown how close it was). `groups`
 * holds each group's spans: `home`, `call`, `answer`, `reveal`, and its `key`, `chord` and `label`
 * (words shown while it plays, such as the leap to sing: `{ "en": "Up a leap of four" }`).
 */
export function echoPlan(exercise) {
    const think = exercise.think ?? THINK;
    const calls = [];
    const answers = [];
    const groups = [];
    const lengthsOf = (steps, own = null) => steps.map((_, i) => own?.[i] ?? exercise.lengths?.[i] ?? (i === steps.length - 1 ? 1.5 : 1));
    let beat = 1;
    exercise.echoes.forEach((echo, group) => {
        const call = typeof echo === 'number' ? [echo] : Array.isArray(echo) ? echo : echo.call;
        const sing = typeof echo === 'object' && !Array.isArray(echo) ? echo.sing : call;
        const key = echo.key ?? 0;
        const chord = echo.chord ?? null;
        const home = exercise.home ? [beat, beat + CUE_BEATS] : null;
        if (home) beat = home[1];
        const callLengths = lengthsOf(call, echo.lengths); // a group's own rhythm for its call (a tune's phrase)
        const callSpan = call.length ? callLengths.reduce((a, b) => a + b, 0) : chord ? CUE_BEATS : 0;
        let at = beat;
        call.forEach((step, i) => {
            calls.push({ beat: at, length: callLengths[i], step, group });
            at += callLengths[i];
        });
        const singLengths = lengthsOf(sing);
        at = beat + callSpan + think;
        sing.forEach((step, i) => {
            answers.push({ beat: at, length: singLengths[i], step, group });
            at += singLengths[i];
        });
        const reveal = exercise.reveal === true || (exercise.reveal === 'last' && group === exercise.echoes.length - 1) ? [at, at + CUE_BEATS] : null;
        groups.push({ home, call: [beat, beat + callSpan], answer: [beat + callSpan + think, at], reveal, key, chord, label: echo.label ?? null });
        beat = (reveal ? reveal[1] : at) + AFTER;
    });
    return { calls, answers, groups, end: beat };
}

/** Several rendered layers summed into one buffer, as long as the longest. */
function mixed(layers) {
    const out = new Float32Array(Math.max(...layers.map((l) => l.length)));
    for (const layer of layers) for (let i = 0; i < layer.length; i++) out[i] += layer[i];
    return out;
}

// A leap's plain name by its size in semitones (en.json `bullseye.leap`).
const LEAPS = [
    tk('bullseye.leap.0'), tk('bullseye.leap.1'), tk('bullseye.leap.2'), tk('bullseye.leap.3'), tk('bullseye.leap.4'), tk('bullseye.leap.5'), tk('bullseye.leap.6'),
    tk('bullseye.leap.7'), tk('bullseye.leap.8'), tk('bullseye.leap.9'), tk('bullseye.leap.10'), tk('bullseye.leap.11'), tk('bullseye.leap.12'),
];

/**
 * The leap you sang into answer `index`, landing `cents` from its target: its size in semitones and
 * whether it went up, measured from the note before it in your answer (or the call's last note).
 * Null when there's nothing before it to measure from.
 */
export function sungLeap(plan, index, cents) {
    const note = plan.answers[index];
    const before = plan.answers[index - 1]?.group === note.group ? plan.answers[index - 1] : plan.calls.filter((n) => n.group === note.group).at(-1);
    if (!before) return null;
    const leap = note.step + Math.round(cents / 100) - before.step;
    return { semitones: Math.min(12, Math.abs(leap)), up: leap > 0 };
}

/**
 * Stars for notes sung from memory: with `kind` 'memory' every note on how close it came; with
 * 'cold' only each answer's first note (sung with nothing before it), the rest on where they landed
 * from it: a cold do 60 cents off with mi and so true to it is right about mi and so.
 */
export function coldPercent(plan, landings, memories, kind, options = {}) {
    let anchor = null; // where this group's cold note landed: the notes after it are judged from it
    const credits = plan.answers.map((n, i) => {
        const first = i === 0 || plan.answers[i - 1].group !== n.group;
        if (first) anchor = landings[i]?.cents ?? null;
        if (kind === 'memory' || first) return memories[i]?.percent ?? (kind === 'cold' ? 0 : null);
        if (!landings[i]) return 0; // a note after the cold one, not sung: a miss
        const landed = anchor !== null ? { ...landings[i], cents: fold(landings[i].cents - anchor) } : landings[i];
        return landingPercent([landed], options);
    }).filter((c) => c !== null);
    if (!landings.some(Boolean)) return null;
    return credits.length ? Math.round(credits.reduce((a, b) => a + b, 0) / credits.length) : null;
}

/** Cents folded to the nearest octave (a difference near a tritone mustn't wrap to an octave off). */
const fold = (c) => c - 1200 * Math.round(c / 1200);

const DIRECTION = { up: tk('bullseye.up'), down: tk('bullseye.down') };
const STEP_WORDS = { listen: tk('bullseye.listen'), turn: tk('bullseye.turn'), lead: tk('bullseye.lead') };

export const BullseyeStep = {
    components: { Bullseye, HeadphonesDialog, Icon, KeyControl },
    props: {
        exercise: { type: Object, required: true },
        base: { type: Number, required: true }, // the learner's comfortable note (MIDI)
        strictness: { type: String, default: 'relaxed' },
        color: { type: String, default: 'var(--color-part-2)' },
        musicKey: { type: String, default: null },
        canLower: { type: Boolean, default: true },
        canHigher: { type: Boolean, default: true },
        earlier: { type: Object, default: () => ({ results: {}, takes: {} }) }, // this visit's earlier steps (the lesson view's)
        personal: { type: Object, default: () => ({}) }, // the course's personal values (not used here yet)
    },
    emits: ['done', 'practised', 'lower', 'higher'],
    data: () => ({ said: '', thenKept: null, stacking: false, running: null, scoring: false, position: 0, live: [], landings: [], result: null, take: null, asking: false, speaker: listening() === 'speaker', playing: false, error: null }),
    template: `
        <div class="space-y-4" data-bullseye-step>
            <Bullseye :landings="landings" :before="before" :color="color" />
            <!-- Each dart said as it lands, for a screen reader (no dart on a note from memory until the reveal). -->
            <span class="sr-only" role="status" data-bullseye-said>{{ said }}</span>
            <p class="min-h-6 text-center text-sm font-semibold" :style="{ color: phase === 'turn' ? color : null }" :class="phase === 'turn' ? '' : 'text-muted'" aria-live="polite">{{ phaseWords }}</p>

            <!-- Hear yourself: your take, and where each note landed, in words. -->
            <section v-if="result && !running" class="space-y-3 rounded-2xl border border-edge bg-panel p-4" data-bullseye-result>
                <div class="flex items-center gap-3">
                    <button v-if="take" type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble-solid" :aria-label="playing ? t('studio.stop') : t('bullseye.hearYourself')" @click="listenBack()"><Icon :name="playing ? 'stop' : 'play'" :size="18" /></button>
                    <div class="min-w-0">
                        <p class="font-bold text-cream">{{ result.percent === null ? t('course.notHeard') : t('bullseye.hearYourself') }}</p>
                        <p v-if="result.percent !== null" class="text-sm text-cream" data-bullseye-line>{{ t('bullseye.landed', { inside, count: heard.length }) }}</p>
                        <p v-if="result.percent !== null" class="text-sm text-muted">{{ slid ? t('bullseye.slid', { count: slid }) : t('bullseye.clean') }}</p>
                    </div>
                </div>
                <ol class="flex flex-wrap gap-2 text-xs" data-bullseye-words>
                    <template v-for="(l, i) in landings" :key="i"><li v-if="l" class="rounded-full bg-raised px-2.5 py-1 text-cream">{{ chipWord(l, i) }} <span class="text-muted">{{ cents(l.cents) }}</span></li></template>
                </ol>
                <div class="flex flex-wrap gap-2">
                    <button v-if="exercise.named" type="button" class="btn-ghost btn-sm" @click="hearRight"><Icon name="play" :size="14" /> {{ t('bullseye.hearRight') }}</button>
                    <button v-if="exercise.stack && take" type="button" class="btn-ghost btn-sm" data-bullseye-stack @click="listenBack(true)"><Icon name="play" :size="14" /> {{ t('bullseye.stacked') }}</button>
                </div>
                <p v-if="stacking" class="text-sm text-cream">{{ t('bullseye.harmony') }}</p>
                <p v-if="closer" class="text-sm font-semibold" :style="{ color }" data-bullseye-closer>{{ t('bullseye.closer', { cents: formatNumber(closer) }) }}</p>
                <!-- Then and now (exercise.thenAndNow): your first round, kept on this device, faint behind today's. -->
                <div v-if="exercise.thenAndNow" class="flex flex-wrap items-center gap-3" data-then-and-now>
                    <template v-if="thenKept">
                        <p class="min-w-0 flex-1 text-sm text-cream">{{ t('bullseye.thenNow', { then: insideOf(thenKept.landings), now: inside, count: heard.length }) }}</p>
                        <button type="button" class="btn-ghost btn-sm" @click="hearThen"><Icon name="play" :size="14" /> {{ t('bullseye.hearThen') }}</button>
                    </template>
                    <p v-else class="text-sm text-muted">{{ t('bullseye.thenNone') }}</p>
                </div>
            </section>

            <p v-if="error" class="text-center text-sm text-bad" role="alert">{{ error }}</p>
            <p v-if="speaker && exercise.drone" class="text-center text-sm text-muted">{{ t('course.speakerUnder') }}</p>
            <HeadphonesDialog v-if="asking" speaker-option simple :action="t('headphones.on')" @ok="headphonesOn" @speaker="useSpeaker" @close="asking = false" />
            <div class="flex flex-col items-center gap-5">
                <KeyControl v-if="musicKey && !running && !scoring" class="w-full" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="$emit('lower')" @higher="$emit('higher')" />
                <button type="button" class="group flex flex-col items-center gap-2 disabled:opacity-50" :disabled="scoring" @click="running ? stop() : start()">
                    <span class="grid size-20 place-items-center rounded-full glass-bubble-solid ring-8 ring-raised/70 transition group-hover:scale-105"><Icon :name="running ? 'stop' : 'mic'" :size="30" /></span>
                    <span class="text-sm font-semibold text-cream">{{ running ? t('studio.stop') : result ? t('bullseye.again') : t('bullseye.start') }}</span>
                </button>
            </div>
        </div>`,
    setup: () => ({ landingWord, formatNumber }),
    computed: {
        plan: (vm) => echoPlan(vm.exercise),
        beatSeconds: (vm) => 60 / (vm.exercise.tempo ?? 72),
        /** The note sounding or being sung now: `{ kind: 'call' | 'answer', index }`, or null between. */
        now() {
            const beat = this.position / this.beatSeconds;
            const answer = this.plan.answers.findIndex((n) => beat >= n.beat && beat < n.beat + n.length);
            if (answer >= 0) return { kind: 'answer', index: answer };
            const call = this.plan.calls.findIndex((n) => beat >= n.beat && beat < n.beat + n.length);
            return call >= 0 ? { kind: 'call', index: call } : null;
        },
        phase() {
            if (!this.running) return null;
            if (this.position < (this.plan.groups[0].home ?? this.plan.groups[0].call)[0] * this.beatSeconds) return 'lead';
            return this.now?.kind === 'answer' ? 'turn' : 'listen';
        },
        phaseWords() {
            if (this.scoring) return t('course.scoring');
            if (!this.phase) return this.result ? '' : t('bullseye.ready', { count: this.plan.answers.length });
            const group = (this.now ? (this.now.kind === 'answer' ? this.plan.answers : this.plan.calls)[this.now.index].group : null) ?? this.lastGroup ?? 0;
            const words = t(STEP_WORDS[this.phase], { n: group + 1, count: this.exercise.echoes.length });
            const label = this.plan.groups[group]?.label;
            return label ? t('bullseye.labelled', { words, label: localized(label) }) : words;
        },
        heard: (vm) => vm.landings.filter(Boolean),
        cold: (vm) => vm.exercise.score === 'memory' || vm.exercise.score === 'cold',
        /** An earlier round's darts this visit (`compare: { step }`), faint behind this round's. */
        before: (vm) => (vm.thenKept ? vm.thenKept.landings : vm.exercise.compare ? vm.earlier?.results?.[vm.exercise.compare.step]?.landings ?? [] : []),
        /** How many cents closer to the middle this round landed, on average, than the one compared: shown from 3. */
        closer() {
            const off = (ls) => ls.filter(Boolean).reduce((sum, l, _, all) => sum + Math.abs(l.cents) / all.length, 0);
            if (!this.result || !this.before.some(Boolean) || !this.heard.length) return 0;
            const gain = Math.round(off(this.before) - off(this.heard));
            return gain >= 3 ? gain : 0;
        },
        inside: (vm) => vm.heard.filter((l) => Math.abs(l.cents) <= 25).length,
        slid: (vm) => vm.heard.filter((l) => l.start !== 'clean').length,
    },
    watch: {
        now(value) {
            if (value) this.lastGroup = (value.kind === 'answer' ? this.plan.answers : this.plan.calls)[value.index].group;
        },
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        this.source?.stop();
        if (this.running) engine.stop().catch(() => {});
        [...engine.parts.keys()].filter((id) => id.startsWith('ex:')).forEach((id) => engine.removePart(id));
    },
    methods: {
        cents: (c) => t('bullseye.cents', { cents: formatNumber(Math.abs(Math.round(c))), sign: Math.round(c) > 0 ? '+' : Math.round(c) < 0 ? '-' : '' }),
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
        /** The guide's calls and the drone, on the audio clock; your answers recorded. */
        async start() {
            this.error = null;
            if (!listening()) {
                this.asking = true;
                return;
            }
            this.speaker = listening() === 'speaker';
            try {
                await unlock();
                if (!(await readyMic())) return;
            } catch (error) {
                showMicHelp(error, () => this.start());
                return;
            }
            this.source?.stop();
            const sr = engine.sampleRate;
            const bpm = this.exercise.tempo ?? 72;
            [...engine.parts.keys()].forEach((id) => engine.removePart(id));
            engine.projectId = null;
            const pitch = (n) => this.base + n.step;
            // A clean, straight guide: it models landing right on the note.
            engine.setPart('ex:guide', renderPart(this.plan.calls.map((n) => ({ beat: n.beat, length: n.length, pitch: pitch(n), vibrato: { cents: 0 } })), { bpm, sampleRate: sr }), sr, { volume: 0.9, pan: 0, audible: true, offsetSeconds: 0 });
            // The drone: under everything with headphones; through a speaker, only before your first answer.
            if (this.exercise.drone) {
                const to = this.speaker ? this.plan.answers[0].beat : this.plan.end;
                engine.setPart('ex:drone', renderPart([{ beat: 0, length: to, pitch: this.base + this.exercise.drone.step, vibrato: { cents: 0 } }], { bpm, sampleRate: sr }), sr, { volume: 0.4, pan: 0, audible: true, offsetSeconds: 0 });
            }
            // The cues, mixed as one part: a key's I chord before a call (home), a group's chord (under
            // its call and your answer with headphones; through a speaker, under the call only), and a
            // drone on the key's do after a note sung from memory (reveal).
            const keyClass = ((this.base % 12) + 12) % 12;
            const chordAt = (root, quality, from, to) => renderBacking([{ beat: from, root: (root + 120) % 12, quality, bass: null }], { bpm, sampleRate: sr, endBeat: to, transpose: keyClass });
            const cues = this.plan.groups.flatMap((g) => [
                g.home && chordAt(g.key, 'maj', g.home[0], g.home[1]),
                g.chord && chordAt(g.chord.root, g.chord.quality ?? 'maj', g.call[0], this.speaker ? g.call[1] : g.answer[1]),
                g.reveal && renderPart([{ beat: g.reveal[0], length: g.reveal[1] - g.reveal[0], pitch: this.base + g.key, vibrato: { cents: 0 } }], { bpm, sampleRate: sr }).map((v) => v * 0.6),
            ]).filter(Boolean);
            if (cues.length) engine.setPart('ex:cues', mixed(cues), sr, { volume: 0.6, pan: 0, audible: true, offsetSeconds: 0 });
            // An earlier step's take beneath this one (`under: { step }`): never through a speaker while recording.
            const under = this.exercise.under ? this.earlier?.takes?.[this.exercise.under.step] : null;
            if (under && !this.speaker) engine.setPart('ex:under', under.samples, under.sampleRate, { volume: 0.7, pan: 0, audible: true, offsetSeconds: 0 });
            Object.assign(this, { result: null, take: null, landings: this.plan.answers.map(() => null), live: this.plan.answers.map(() => []), running: 'sing', lastGroup: 0 });
            this.startedAt = performance.now();
            const latencyFrames = audio.latencyFrames ?? engine.estimatedLatencyFrames();
            this.lag = 1024 / sr + latencyFrames / sr / 2; // the live reading's lag, taken off
            engine.play({ fromSeconds: 0, bpm, record: true, latencyFrames, leadSeconds: leadSeconds() });
            const tick = () => {
                const position = engine.positionSeconds();
                if (position === null || !this.running) return;
                this.position = Math.max(0, position);
                if (this.position >= this.plan.end * this.beatSeconds) {
                    this.stop();
                    return;
                }
                this.hear();
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        /** One live reading into its answer's window; an answer that has just ended lands as a dart. */
        hear() {
            const at = this.position - this.lag;
            const frame = engine.inputFrame();
            const reading = frame ? trackFrame(frame, engine.sampleRate, { minClarity: LISTEN_CLARITY }) : null;
            this.plan.answers.forEach((n, i) => {
                const before = this.plan.answers[i - 1];
                // From a little before the note, but never inside the note before it (a group's notes join).
                const from = Math.max(n.beat * this.beatSeconds - 0.25, before ? (before.beat + before.length) * this.beatSeconds : -Infinity);
                const to = (n.beat + n.length) * this.beatSeconds;
                if (at >= from && at < to) this.live[i].push({ at, cents: reading?.hz ? 1200 * Math.log2(reading.hz / (440 * 2 ** ((this.base + n.step - 69) / 12))) : null });
                // A note sung from memory shows no dart until the reveal: a dart would warm the next cold try.
                else if (!this.cold && at >= to && this.landings[i] === null && this.live[i].length > 2) {
                    this.landings[i] = landing(this.live[i].map((r) => r.cents), liveRate(this.live[i]), { exact: !!this.exercise.named });
                    if (this.landings[i]) this.said = t('bullseye.saidOne', { n: i + 1, word: t(landingWord(this.landings[i].cents)), cents: this.cents(this.landings[i].cents) });
                }
            });
        },
        async stop() {
            cancelAnimationFrame(this.frame);
            const finished = this.position >= this.plan.end * this.beatSeconds - 0.05;
            this.running = null;
            let take = null;
            try {
                take = await engine.stop();
            } catch (error) {
                console.warn('Stopped before the recording began', error);
            }
            this.$emit('practised', (performance.now() - this.startedAt) / 1000);
            if (!take || !finished) return;
            this.scoring = true;
            try {
                const track = await trackInWorker(take.samples, take.sampleRate);
                const notes = this.plan.answers.map((n) => ({ beat: n.beat, length: n.length, pitch: this.base + n.step }));
                // The darts again from the whole take (the live ones were a first look). A note sung
                // from memory (`score: 'memory'`) is starred on how close it came, never on its start;
                // `score: 'cold'` stars each answer's first note that way and the rest on landing.
                const options = { generous: !!this.exercise.generous, named: !!this.exercise.named };
                const score = skillPercent('landing', track, notes, this.beatSeconds, options);
                const percent = this.exercise.score === 'memory' || this.exercise.score === 'cold' ? coldPercent(this.plan, score.notes, skillPercent('memory', track, notes, this.beatSeconds).notes, this.exercise.score, options) : score.percent;
                this.landings = score.notes;
                this.take = { samples: take.samples, sampleRate: take.sampleRate };
                const slid = score.notes.filter((l) => l && l.start !== 'clean').length;
                this.result = { percent };
                await this.keepOrCompare(score.notes);
                this.$emit('done', { percent, tip: slid >= Math.max(2, score.notes.length * 0.3) ? 'onsets' : null, landings: score.notes, sung: notes.filter((_, i) => score.notes[i] && Math.abs(score.notes[i].cents) <= 50).map((n) => n.pitch), insights: [], take: this.take });
            } catch (error) {
                console.error('Scoring failed', error);
                this.result = { percent: null };
                this.$emit('done', { percent: null, sung: [] });
            } finally {
                this.scoring = false;
            }
        },
        /** Hear yourself: the take as it was recorded, on the audio clock. */
        /** A landing's words: plain ("A hair flat"), or, where a leap was asked for by name and you sang another, which one. */
        chipWord(l, i) {
            if (!this.exercise.named || Math.abs(l.cents) <= NAMED_MISS) return t(landingWord(l.cents));
            const leap = sungLeap(this.plan, i, l.cents);
            if (!leap) return t(landingWord(l.cents));
            return leap.semitones === 0 ? t('bullseye.sangSame') : t('bullseye.sang', { leap: t(LEAPS[leap.semitones]), direction: t(leap.up ? DIRECTION.up : DIRECTION.down) });
        },
        insideOf: (landings) => landings.filter((l) => l && Math.abs(l.cents) <= 25).length,
        /**
         * Then and now: a step with `keep` saves its first take on this device (never synced: the
         * audio stays here), and one with `thenAndNow` loads it to show faint behind today's.
         */
        async keepOrCompare(landings) {
            try {
                if (this.exercise.keep) {
                    const key = `course:${this.exercise.keep}`;
                    if (!(await db.get('learning', key))) {
                        await db.putAudio(key, this.take.samples);
                        await db.put('learning', { sampleRate: this.take.sampleRate, landings, at: Date.now() }, key);
                    }
                }
                if (this.exercise.thenAndNow) {
                    const key = `course:${this.exercise.thenAndNow}`;
                    const kept = await db.get('learning', key);
                    const samples = kept ? await db.getAudio(key) : null;
                    this.thenKept = kept && samples ? { ...kept, samples } : null;
                }
            } catch (error) {
                console.error('Then and now not kept', error);
            }
        },
        hearThen() {
            this.playBuffer(this.thenKept.samples, this.thenKept.sampleRate);
        },
        /** Plays a buffer on the audio clock, stopping whatever this step was playing. */
        playBuffer(samples, sampleRate, { stacking = false } = {}) {
            this.source?.stop();
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                if (this.source !== source) return;
                this.playing = false;
                this.stacking = false;
                this.source = null;
            };
            source.start(engine.ctx.currentTime + 0.03);
            this.source = source;
            this.playing = true;
            this.stacking = stacking;
        },
        /**
         * Hear yourself: the take as it was recorded. `stacked`: with the guide holding each call's
         * note under your answer, so you hear the two notes together (that's harmony).
         */
        listenBack(stacked = false) {
            if (this.playing && !stacked) {
                this.source?.stop();
                return;
            }
            const { samples, sampleRate } = this.take;
            if (!stacked) return this.playBuffer(samples, sampleRate);
            const held = this.plan.groups.map((g, i) => ({ beat: g.answer[0], length: g.answer[1] - g.answer[0], pitch: this.base + (this.plan.calls.find((n) => n.group === i)?.step ?? g.key), vibrato: { cents: 0 } }));
            this.playBuffer(mixed([samples, renderPart(held, { bpm: this.exercise.tempo ?? 72, sampleRate }).map((v) => v * 0.6)]), sampleRate, { stacking: true });
        },
        /** Hear it right: the guide sings the answers you were asked for, at their places. */
        hearRight() {
            const sr = engine.sampleRate;
            const notes = this.plan.answers.map((n) => ({ beat: n.beat, length: n.length, pitch: this.base + n.step, vibrato: { cents: 0 } }));
            this.playBuffer(renderPart(notes, { bpm: this.exercise.tempo ?? 72, sampleRate: sr }), sr);
        },
    },
};
