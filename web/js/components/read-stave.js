// Sight-singing's stave (docs/sight-singing-plan.md, slice 1): a lesson step with `staff` is drawn as
// music, not on the lane. The line in the key whose do is the singer's own note (so it always matches
// what's sung), in the clef its range wants, engraved as the printable parts are (js/print-score.js,
// components/score-system.js) in the skin's ink, with names under the notes (on, faint or off: the
// step's `staff.names`) in the reader's style (solfege first; letters or numbers by choice), the note
// to sing now lit, and the look count before it starts.

import { formatNumber, t, tk } from '../i18n.js';
import { printLayout } from '../print-score.js';
import { noteLabel } from '../notes.js';
import { prefs } from '../stores/prefs.js';
import { ScoreSystem } from './score-system.js';

// Each do (a pitch class) as the key that puts it at home, the fewer sharps or flats.
const KEYS = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];
export const READ_STYLES = ['solfege', 'letters', 'numbers'];
const STYLE_LABELS = { letters: tk('course.read.style.letters'), solfege: tk('course.read.style.solfege'), numbers: tk('course.read.style.numbers') };
const FAINT = 0.35;

/**
 * The reader's clef, from their do (MIDI), never from a line's range, so do sits in the same place in
 * every lesson (the courses lead), whichever puts the course's whole range (a fourth under do to a
 * fifth over) on the fewest ledger lines: bass for a do under G3, treble with an 8 below under E4,
 * else treble (the ties fall at F#3 and E4, the clean breaks).
 */
export function readClef(home) {
    return home < 55 ? 'bass' : home < 64 ? 'treble8' : 'treble';
}

/**
 * The reading line as a one-part song for the engraver: `notes` ({ beat, length, pitch }, the
 * pitches sung) moved so the bars fall from the first note's bar (or, a song's, from its `pickup`), in
 * the clef of `home` (the singer's do, MIDI) and its key, or a song's own `key` ("Am": la-based, its own
 * signature), 4/4 by default. Returns `{ template, shift }` (`shift` the beats taken off).
 */
export function readSong(notes, { home, tempo = 72, timeSignature = '4/4', key = null, pickup = 0 }) {
    const perBar = { '4/4': 4, '3/4': 3, '2/4': 2, '6/8': 2 }[timeSignature] ?? 4;
    const first = Math.min(...notes.map((n) => n.beat));
    // A song's line (`pickup`: beats before its first bar line) keeps the song's bars; a lesson's line
    // starts its bars from its first note's bar.
    const shift = pickup ? 0 : Math.floor(first / perBar + 1e-9) * perBar;
    const clef = readClef(home);
    const part = { key: clef === 'bass' ? 'bass' : clef === 'treble8' ? 'tenor' : 'soprano', name: '', melody: true, guide: { type: 'notes', notes: notes.map((n) => ({ beat: n.beat - shift, length: n.length, pitch: n.pitch })) } };
    return { template: { key: key ?? KEYS[((home % 12) + 12) % 12], timeSignature, pickupBeats: pickup, tempo, chords: [], parts: [part] }, shift };
}

export const ReadStave = {
    components: { ScoreSystem },
    props: {
        notes: { type: Array, required: true }, // the line to read: { beat, length, pitch } (pitches as sung)
        home: { type: Number, required: true }, // the singer's do (MIDI)
        tempo: { type: Number, default: 72 },
        names: { type: String, default: 'on' }, // the step's staff.names: on, faint or off
        lit: { type: Number, default: -1 }, // the note being sung now (its index in `notes`)
        look: { type: Number, default: null }, // seconds left to look before it starts, or null
        counting: { type: Number, default: null }, // the count-in's beat now (1, 2, 3...), or null
        time: { type: String, default: '4/4' }, // the line's time signature (staff.time)
        pickup: { type: Number, default: 0 }, // a song's line: beats before its first bar line
        musicKey: { type: String, default: null }, // a song's own key, a minor one with its m; else the key of `home`
        color: { type: String, required: true },
    },
    setup: () => ({ prefs, READ_STYLES, STYLE_LABELS }),
    template: `
        <div class="space-y-3" data-read-stave>
            <!-- A long part (a song's) scrolls in its card, the line being sung kept in view -->
            <div ref="card" class="overflow-y-auto rounded-2xl border border-line px-3 py-4 text-cream" :style="layout.systems.length > 3 ? { maxHeight: '45vh' } : null" :aria-label="label" role="img">
                <ScoreSystem v-for="(sys, s) in layout.systems" :key="s" ref="systems" :sys="sys" :layout="layout" :song-key="song.key" ink="currentColor" :label-of="nameOf" :label-opacity="names === 'faint' ? FAINT : 1" :lit="lit" :lit-color="color" class="mx-auto max-w-xl" :class="s ? 'mt-2' : ''" aria-hidden="true" />
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="min-h-6 text-sm font-semibold text-cream" aria-live="polite" data-read-look>{{ look !== null && look > 0 ? t('course.read.look', { seconds: formatNumber(Math.ceil(look)) }) : counting ? t('course.read.counting', { beat: formatNumber(counting) }) : '' }}</p>
                <div v-if="names !== 'off'" class="flex rounded-full border border-line p-0.5" role="group" :aria-label="t('course.read.names')">
                    <button v-for="s in READ_STYLES" :key="s" type="button" class="rounded-full px-3 py-1 text-xs" :class="style === s ? 'bg-raised font-semibold text-cream' : 'text-muted'" :aria-pressed="style === s ? 'true' : 'false'" @click="prefs.readNames = s">{{ t(STYLE_LABELS[s]) }}</button>
                </div>
            </div>
        </div>`,
    data: () => ({ FAINT, narrow: window.innerWidth < 640 }),
    mounted() {
        this.onResize = () => (this.narrow = window.innerWidth < 640);
        window.addEventListener('resize', this.onResize);
    },
    unmounted() {
        window.removeEventListener('resize', this.onResize);
    },
    watch: {
        /** The line with the lit note scrolled into the card's view (at once with reduced motion). */
        litSystem(index) {
            const card = this.$refs.card;
            const sys = this.$refs.systems?.[index]?.$el;
            if (index < 0 || !card || !sys || card.scrollHeight <= card.clientHeight) return;
            const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
            card.scrollTo({ top: sys.offsetTop - card.offsetTop - 8, behavior: still ? 'auto' : 'smooth' });
        },
    },
    computed: {
        style: () => (READ_STYLES.includes(prefs.readNames) ? prefs.readNames : 'solfege'),
        song() {
            return readSong(this.notes, { home: this.home, tempo: this.tempo, timeSignature: this.time, key: this.musicKey, pickup: this.pickup }).template;
        },
        /** Two bars a line on a phone (the plan: readable at 375 px), the page's four on a wider screen. */
        layout() {
            return printLayout(this.song, { names: false, perSystem: this.narrow ? 2 : null, page: this.narrow ? 460 : 760 });
        },
        litSystem() {
            return this.lit < 0 ? -1 : this.layout.systems.findIndex((s) => s.staves[0].items.some((i) => i.note === this.lit));
        },
        /** For a screen reader: the notes to read, named in the reader's style. */
        label() {
            return t('course.read.label', { notes: this.notes.map((n) => this.styled(n.pitch, null)).join(', ') });
        },
    },
    methods: {
        formatNumber,
        styled(pitch, spelled) {
            if (this.style === 'letters') return (spelled ?? noteLabel(pitch, 'letters', this.song.key)).replace(/-?\d+$/, '');
            return noteLabel(pitch, this.style, this.song.key);
        },
        nameOf(item) {
            return this.names === 'off' ? null : this.styled(item.pitch, item.name);
        },
    },
};
