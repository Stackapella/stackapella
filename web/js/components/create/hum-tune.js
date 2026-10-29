// Hum a tune (docs/hum-a-tune-plan.md; the music is js/hum.js, the arranger's): hum or sing a tune
// with no words in your own time, up to a minute, and the app writes it down: the notes, the tempo,
// the bars and the key. When it isn't sure of the beat it asks once: "Tap along while it plays".
// Then the builder's own steps check the notes and suggest the harmony. The take stays in memory
// only; what's kept is the notes.

import { audio, engine } from '../../audio.js';
import { click, hzToMidi } from '../../dsp.js';
import { formatNumber, t } from '../../i18n.js';
import { trackFrame, trackInWorker } from '../../pitchtrack.js';
import { noteEvents } from '../../transcribe.js';
import { beatsFromTaps, humToMelody, rehum } from '../../hum.js';
import { readyToSing } from '../../creator-audio.js';
import { renderPart } from '../../synth.js';
import { PART_COLORS } from '../../theme.js';
import { Icon } from '../icon.js';
import { MicPicker, micError } from '../mic.js';
import { Notice } from '../form.js';
import { Stepper, Switch } from '../controls.js';
import { Roll } from './roll.js';
import { count } from '../../usage.js';

const MOST_SECONDS = 60; // Hillel: "32 bars or a minute", in one take
const LOW_HUM = 57; // a hum mostly under A3 is placed for the tune's part an octave up, as Sing it does
const COLOR = PART_COLORS[0];
const CLICK_PITCH = 86; // above D6: the click's ticks, never a hum
const perBar = (ts) => ({ '4/4': 4, '3/4': 3, '2/4': 2, '6/8': 2 })[ts] ?? 4;

export const HumTuneStep = {
    components: { Icon, MicPicker, Notice, Roll, Stepper, Switch },
    props: { draft: { type: Object, required: true } },
    emits: ['change', 'tune'],
    data: () => ({ state: 'idle', seconds: 0, live: [], problem: null, micProblem: null, melody: null, playing: false, taps: [], again: null, useClick: false, clickBpm: 80, audio }),
    setup: () => ({ formatNumber, MOST_SECONDS, COLOR }),
    template: `
        <section class="space-y-6" data-hum-tune :data-state="state">
            <div>
                <h2 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('create.hum.title') }}</h2>
                <p class="mt-1 text-muted">{{ t('create.hum.hint', { seconds: formatNumber(MOST_SECONDS) }) }}</p>
            </div>
            <div class="card space-y-5 p-5 md:p-8">
                <template v-if="state === 'recording'">
                    <p class="text-lg font-bold text-cream" role="status">{{ again !== null ? t('create.hum.recordingLine', { number: formatNumber(again + 1), seconds: formatNumber(Math.floor(seconds)) }) : t('create.hum.recording', { seconds: formatNumber(Math.floor(seconds)) }) }}</p>
                    <Roll :parts="[]" :live="live" :beats-per-bar="4" :pickup-beats="0" :end-beat="liveEnd" :zoom="20" :playhead="livePlayhead" :label="t('create.sing.liveLabel')" />
                </template>
                <p v-else-if="state === 'working'" class="text-muted" role="status">{{ t('create.sing.working') }}</p>
                <template v-else-if="melody">
                    <!-- Here's your tune: what was heard, played back by the guide voice. -->
                    <p class="font-bold text-cream" data-hum-found>{{ t('create.hum.found', { bpm: formatNumber(melody.tempo), signature: melody.timeSignature, key: melody.key, count: formatNumber(melody.notes.length) }) }}</p>
                    <Roll :parts="[{ key: 'tune', color: paint(COLOR), notes: melody.notes }]" :beats-per-bar="perBar" :pickup-beats="melody.pickupBeats" :end-beat="endBeat" :zoom="20" :label="t('create.sing.notesLabel', { count: melody.notes.length })" />
                    <!-- Again for one line: hum just that line, and it takes its place (the tempo, bars and key stay). -->
                    <ol v-if="melody.phrases?.length > 1" class="flex flex-wrap gap-2" :aria-label="t('create.hum.lines')" data-hum-lines>
                        <li v-for="(phrase, i) in melody.phrases" :key="i"><button type="button" class="btn-ghost btn-sm" :disabled="state !== 'idle'" @click="record(i)">{{ t('create.hum.lineAgain', { number: formatNumber(i + 1) }) }}</button></li>
                    </ol>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn-secondary" :disabled="state === 'tapping'" data-hum-play @click="playTune"><Icon :name="playing ? 'stop' : 'play'" :size="16" /> {{ playing ? t('studio.stop') : t('create.hum.play') }}</button>
                    </div>
                </template>
                <p v-else class="text-muted">{{ t('create.hum.ready') }}</p>
                <!-- Free time, with a click one tap away (Hillel: "free time, click optional"). -->
                <div v-if="state === 'idle' && again === null" class="flex flex-wrap items-center gap-4 border-t border-line pt-4" data-hum-click>
                    <Switch :on="useClick" :label="t('create.hum.useClick')" :hint="t('create.hum.useClickHint')" @change="useClick = $event" />
                    <Stepper v-if="useClick" v-model="clickBpm" :min="50" :max="160" :label="t('create.hum.bpm')" :unit="t('create.hum.bpmUnit')" :decrease="t('create.hum.slower')" :increase="t('create.hum.faster')" />
                </div>
            </div>
            <!-- Not sure of the beat: once, Tap along while your take plays back. -->
            <Notice v-if="melody && unsure" tone="info" data-hum-unsure>
                <p>{{ t('create.hum.unsure') }}</p>
                <button type="button" class="btn-secondary btn-sm mt-3" :disabled="state === 'tapping'" data-hum-tap-start @click="tapAlong">{{ state === 'tapping' ? t('create.hum.tapping', { count: formatNumber(taps.length) }) : t('create.hum.tapAlong') }}</button>
            </Notice>
            <button v-if="state === 'tapping'" type="button" class="btn-primary btn-lg w-full" data-hum-tap @pointerdown.prevent="tap">{{ t('create.hum.tap') }}</button>
            <Notice v-if="problem" :tone="problem.tone"><p>{{ problem.text }}</p><MicPicker v-if="problem.kind === 'silent' && audio.mic" class="mt-3" /></Notice>
            <p v-if="micProblem" class="text-sm text-bad" role="alert">{{ micProblem }}</p>
            <div class="flex flex-wrap items-center gap-3">
                <button v-if="state === 'recording'" type="button" class="btn-primary btn-lg" data-hum-stop @click="finish"><Icon name="stop" :size="18" /> {{ t('create.sing.done') }}</button>
                <button v-else type="button" :class="melody ? 'btn-secondary' : 'btn-primary btn-lg'" :disabled="state === 'working' || state === 'tapping'" data-hum-record @click="record"><Icon name="record" :size="18" /> {{ melody ? t('create.hum.again') : t('create.hum.start') }}</button>
            </div>
        </section>`,
    computed: {
        perBar: (vm) => perBar(vm.melody?.timeSignature),
        // While humming there's no beat yet: the roll runs at two "beats" a second.
        livePlayhead: (vm) => vm.seconds * 2,
        liveEnd: (vm) => Math.max(8, vm.seconds * 2),
        endBeat: (vm) => (vm.melody ? Math.max(...vm.melody.notes.map((n) => n.beat + n.length)) : 8),
        /** Unsure of the beat or the bars, and not yet tapped along. */
        unsure: (vm) => !!vm.melody && !vm.tapped && (vm.melody.sure?.beat === false || vm.melody.sure?.metre === false),
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        if (this.state === 'recording') engine.cancelCapture();
        this.source?.stop();
        this.clickSource?.stop();
    },
    methods: {
        /** Records until Done, or a minute; the mic's pitch drawn live (the screen only reads the mic). */
        /** Records the whole tune, or (`line`, a phrase's index) that line again. */
        async record(line = null) {
            this.again = Number.isInteger(line) ? line : null;
            if (this.again === null) count('hum_start'); // a tune hummed from the start, not a line again
            if (this.again === null && this.useClick) count('hum_click');
            this.problem = null;
            this.micProblem = null;
            try {
                if (!(await readyToSing())) return;
            } catch (error) {
                this.micProblem = micError(error, () => this.record());
                return;
            }
            const sampleRate = engine.ctx.sampleRate;
            const startedAt = engine.ctx.currentTime;
            this.live = [];
            this.seconds = 0;
            if (this.again === null) this.tapped = false;
            this.state = 'recording';
            this.capture = engine.startCapture(Math.ceil(startedAt * sampleRate), null, null);
            this.clickBeats = this.useClick && this.again === null ? this.startClick(startedAt) : null;
            const follow = () => {
                if (this.state !== 'recording') return;
                this.seconds = engine.ctx.currentTime - startedAt;
                const input = engine.inputFrame();
                const reading = input ? trackFrame(input, engine.sampleRate) : null;
                if (reading?.hz > 0) this.live.push({ beat: this.seconds * 2, midi: hzToMidi(reading.hz) });
                if (this.seconds >= MOST_SECONDS) return this.finish();
                this.frame = requestAnimationFrame(follow);
            };
            this.frame = requestAnimationFrame(follow);
        },
        /**
         * The click under a take: a minute of beats at `clickBpm` (the bar's first a higher tick),
         * one buffer on the audio clock from just after the capture starts. Returns each beat's
         * time from the capture's start: the beat, known, for humToMelody.
         */
        startClick(startedAt) {
            const sr = engine.sampleRate;
            const lead = 0.25;
            const beat = 60 / this.clickBpm;
            const count = Math.ceil(MOST_SECONDS / beat) + 1;
            const samples = new Float32Array(Math.ceil((lead + count * beat) * sr));
            const high = click(sr, { hz: 2000 });
            const low = click(sr, { hz: 1500, level: 0.5 });
            for (let k = 0; k < count; k++) {
                const tick = k % 4 === 0 ? high : low;
                samples.set(tick.subarray(0, Math.max(0, Math.min(tick.length, samples.length - Math.round((lead + k * beat) * sr)))), Math.round((lead + k * beat) * sr));
            }
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sr);
            source.connect(engine.playBus);
            source.start(startedAt);
            this.clickSource = source;
            return Array.from({ length: count }, (_, k) => lead + k * beat);
        },
        async finish() {
            cancelAnimationFrame(this.frame);
            if (this.state !== 'recording') return;
            this.clickSource?.stop();
            this.clickSource = null;
            const take = engine.keepCapture({ interrupted: false });
            this.state = 'working';
            try {
                if (!take || take.samples.length < take.sampleRate / 2) return;
                const track = await trackInWorker(take.samples, take.sampleRate);
                if (this.again !== null) {
                    // One line again: in the same octave as the tune, in that line's place.
                    const heard = noteEvents(track, { octave: this.octave });
                    if (heard.empty) {
                        this.problem = { kind: 'silent', tone: 'bad', text: t('create.sing.silent') };
                        return;
                    }
                    this.melody = rehum(this.melody, this.again, heard.events);
                    this.$emit('tune', { melody: this.melody, octave: this.octave });
                    return;
                }
                this.take = take;
                let heard = noteEvents(track);
                if (heard.empty) {
                    this.problem = { kind: 'silent', tone: 'bad', text: t('create.sing.silent') };
                    return;
                }
                // A low hum: the tune's part an octave up (it's written for the top voice).
                const pitches = heard.events.map((e) => e.pitch).sort((a, b) => a - b);
                const octave = pitches[Math.floor(pitches.length / 2)] < LOW_HUM ? 12 : 0;
                if (octave) heard = noteEvents(track, { octave });
                // Through a speaker the mic hears the click too: its ticks (1500 and 2000 Hz, short) are no hummed notes.
                if (this.clickBeats) heard = { ...heard, events: heard.events.filter((e) => !(e.pitch - octave > CLICK_PITCH && e.end - e.start < 0.15)) };
                this.events = heard.events;
                this.octave = octave;
                // With the click, the beat is known: only the metre and the bars are found.
                const beats = this.clickBeats?.filter((at) => at <= take.samples.length / take.sampleRate + 1);
                this.melody = humToMelody(heard.events, beats?.length >= 2 ? { beats } : {});
                this.tapped = !!beats?.length;
                this.$emit('tune', { melody: this.melody, octave });
                this.problem = heard.noisy ? { kind: 'noisy', tone: 'warn', text: t('create.sing.noisy') } : null;
            } catch (error) {
                console.error('The tune was not written down', error);
                this.problem = { kind: 'failed', tone: 'bad', text: t('create.sing.failed') };
            } finally {
                if (this.state === 'working') this.state = 'idle';
                this.again = null;
            }
        },
        /** The tune as written, by the guide voice (on the audio clock). */
        playTune() {
            if (this.playing) return this.source?.stop();
            const notes = this.melody.notes.map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch - this.octave }));
            this.play(renderPart(notes, { bpm: this.melody.tempo, sampleRate: engine.sampleRate }), engine.sampleRate);
        },
        play(samples, sampleRate, onEnd = null) {
            const source = engine.ctx.createBufferSource();
            source.buffer = engine.toBuffer(samples, sampleRate);
            source.connect(engine.playBus);
            source.onended = () => {
                this.playing = false;
                this.source = null;
                onEnd?.();
            };
            this.source = source;
            this.playStart = engine.ctx.currentTime + 0.05;
            source.start(this.playStart);
            this.playing = true;
        },
        /** Your take plays back; a tap on each beat (the button, or Space) gives the beat, and the bars follow. */
        tapAlong() {
            count('hum_tap');
            this.taps = [];
            this.state = 'tapping';
            this.onKey = (event) => {
                if (event.code !== 'Space') return;
                event.preventDefault();
                this.tap();
            };
            document.addEventListener('keydown', this.onKey);
            this.play(this.take.samples, this.take.sampleRate, () => this.tapsDone());
        },
        tap() {
            if (this.state !== 'tapping') return;
            this.taps.push(engine.ctx.currentTime - this.playStart); // on the audio clock, from the take's start
        },
        tapsDone() {
            document.removeEventListener('keydown', this.onKey);
            this.state = 'idle';
            const beats = beatsFromTaps(this.taps);
            if (!beats) return;
            this.tapped = true;
            this.melody = humToMelody(this.events, { beats });
            this.$emit('tune', { melody: this.melody, octave: this.octave });
        },
    },
};
