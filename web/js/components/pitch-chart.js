// The pitch chart (docs/vocal-coaching-plan.md: the singer's report, and the admin's review): the
// part's notes as bars, the singer's line over them, the phrases, and where playback is. Clicking
// picks a phrase (the review pins a retake note to it). The scores and checks are shown beside it
// in words, so nothing here rests on seeing the chart.

import { hzToMidi } from '../dsp.js';

const BEAT_WIDTH = 24;
const SEMITONE = 7;
const LINE_RATE = 25; // points a second on the singer's line

export const PitchChart = {
    props: {
        notes: { type: Array, required: true }, // { beat, length, pitch }
        track: Object, // { hz, rate } from trackTake, or null while it's worked out
        beatSeconds: { type: Number, required: true },
        offsetSeconds: { type: Number, default: 0 }, // the nudge being tried
        phrases: { type: Array, default: () => [] }, // [startBeat, endBeat]
        picked: { type: Number, default: null }, // a phrase index
        position: { type: Number, default: null }, // seconds
        label: { type: String, required: true },
    },
    emits: ['pick'],
    template: `
        <div class="overflow-x-auto rounded-2xl border border-line bg-ink">
            <svg :viewBox="'0 0 ' + width + ' ' + height" :width="width" :height="height" role="img" :aria-label="label" class="block cursor-crosshair" @click="pick">
                <rect v-for="(p, i) in phrases" :key="'p' + i" :x="p[0] * BEAT_WIDTH" y="0" :width="(p[1] - p[0]) * BEAT_WIDTH" :height="height" :class="i === picked ? 'fill-accent/15' : i % 2 ? 'fill-raised/40' : 'fill-transparent'" />
                <line v-for="(p, i) in phrases" :key="'l' + i" :x1="p[0] * BEAT_WIDTH" :x2="p[0] * BEAT_WIDTH" y1="0" :y2="height" class="stroke-line" stroke-width="1" />
                <text v-for="(p, i) in phrases" :key="'t' + i" :x="p[0] * BEAT_WIDTH + 4" y="12" class="fill-muted text-[10px]">{{ i + 1 }}</text>
                <rect v-for="(n, i) in notes" :key="'n' + i" :x="n.beat * BEAT_WIDTH" :y="y(n.pitch) - SEMITONE / 2" :width="noteWidth(n)" :height="SEMITONE" rx="3" class="fill-part-2/50" />
                <polyline v-for="(points, i) in line" :key="'s' + i" :points="points" fill="none" class="stroke-accent" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                <line v-if="position !== null" :x1="x(position)" :x2="x(position)" y1="0" :y2="height" class="stroke-cream" stroke-width="1.5" />
            </svg>
        </div>`,
    setup: () => ({ BEAT_WIDTH, SEMITONE }),
    computed: {
        range() {
            const pitches = this.notes.map((n) => n.pitch);
            return { low: Math.min(...pitches) - 5, high: Math.max(...pitches) + 5 };
        },
        lengthBeats() {
            return this.notes.reduce((end, n) => Math.max(end, n.beat + n.length), this.phrases.at(-1)?.[1] ?? 0) + 1;
        },
        width() {
            return Math.ceil(this.lengthBeats * BEAT_WIDTH);
        },
        height() {
            return (this.range.high - this.range.low) * SEMITONE + 16;
        },
        /** The singer's line, as polylines broken where there's no pitch. */
        line() {
            const track = this.track;
            if (!track) return [];
            const step = Math.max(1, Math.round(track.rate / LINE_RATE));
            const runs = [];
            let run = [];
            for (let f = 0; f < track.hz.length; f += step) {
                const hz = track.hz[f];
                const midi = hz ? hzToMidi(hz) : null;
                if (midi === null || midi < this.range.low - 2 || midi > this.range.high + 2) {
                    if (run.length > 1) runs.push(run.join(' '));
                    run = [];
                    continue;
                }
                run.push(`${this.x(f / track.rate + this.offsetSeconds).toFixed(1)},${this.y(midi).toFixed(1)}`);
            }
            if (run.length > 1) runs.push(run.join(' '));
            return runs;
        },
    },
    methods: {
        noteWidth(note) {
            return Math.max(2, note.length * BEAT_WIDTH - 2);
        },
        x(seconds) {
            return (seconds / this.beatSeconds) * BEAT_WIDTH;
        },
        y(midi) {
            return (this.range.high - midi) * SEMITONE + 8;
        },
        pick(event) {
            const svg = event.currentTarget;
            const beat = ((event.clientX - svg.getBoundingClientRect().left) / svg.getBoundingClientRect().width) * (this.width / BEAT_WIDTH);
            const index = this.phrases.findIndex(([start, end]) => beat >= start && beat < end);
            if (index >= 0) this.$emit('pick', index);
        },
    },
};
