// Tune the chord's meter (docs/tune-chord-plan.md, slice 2), shown on your turn while a note held in
// a chord is judged (js/tuning.js): what the note is in the chord and where its pure tuning sits
// ("Third of G: pure is 14 cents under the piano"), then one line from -30 to +30 cents with the
// piano's pitch, the pure mark and you on it, and a word for where you are. Game mode glows on a
// lock (it shows ringing; it never scores it). Off by default: Sing's settings turn it on.

import { t } from '../../i18n.js';

const RANGE = 30; // cents either side shown, wider when the pure mark is further out (a barbershop seventh, 31 under)

export const RingMeter = {
    props: {
        reading: { type: Object, required: true }, // { degree, pure, cents, off, state }
        chord: { type: String, default: '' }, // its name, as the lane shows it ("G", "D7"); none in a lesson, which names it in its own words
        color: { type: String, required: true },
    },
    template: `
        <div class="rounded-2xl border border-line px-4 pb-6 pt-3" data-ring-meter :data-ring-state="reading.state" :data-locked="locked ? '' : null">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1 text-sm">
                <span class="text-cream">{{ what }}</span>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold" :class="locked ? 'text-ink' : 'bg-raised text-cream'" :style="locked ? { background: color } : null" role="status">{{ word }}</span>
            </div>
            <div class="relative mt-3 h-6" aria-hidden="true">
                <div class="absolute inset-x-0 top-1/2 h-px bg-line"></div>
                <div class="absolute top-0 h-6 w-px bg-muted" :style="{ left: at(0) }"></div>
                <div class="absolute top-0 h-6 w-px" :style="{ left: at(reading.pure), background: color, boxShadow: '0 0 0 1px ' + color }"></div>
                <span v-if="!samePlace" class="absolute top-7 -translate-x-1/2 whitespace-nowrap text-xs text-muted" :style="{ left: at(0) }">{{ t('sing.ring.piano') }}</span>
                <span class="absolute top-7 -translate-x-1/2 whitespace-nowrap text-xs font-semibold text-cream" :style="{ left: at(reading.pure) }">{{ samePlace ? t('sing.ring.pianoAndPure') : t('sing.ring.pure') }}</span>
                <div class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-cream" :style="{ left: at(reading.cents), background: locked ? color : 'var(--color-panel, transparent)' }"></div>
            </div>
        </div>`,
    computed: {
        locked: (vm) => vm.reading.state === 'ringing',
        /** The piano's pitch and the pure one too close to label apart (a root, a fifth). */
        samePlace: (vm) => Math.abs(vm.reading.pure) < 5,
        degreeName() {
            return { root: t('sing.ring.degree.root'), third: t('sing.ring.degree.third'), fifth: t('sing.ring.degree.fifth'), seventh: t('sing.ring.degree.seventh'), sixth: t('sing.ring.degree.sixth'), ninth: t('sing.ring.degree.ninth'), fourth: t('sing.ring.degree.fourth') }[this.reading.degree];
        },
        /** A lesson's: the degree alone ("The seventh"), since the step names its chord in solfa. */
        lessonDegree() {
            return { root: t('sing.ring.lessonDegree.root'), third: t('sing.ring.lessonDegree.third'), fifth: t('sing.ring.lessonDegree.fifth'), seventh: t('sing.ring.lessonDegree.seventh'), sixth: t('sing.ring.lessonDegree.sixth'), ninth: t('sing.ring.lessonDegree.ninth'), fourth: t('sing.ring.lessonDegree.fourth') }[this.reading.degree];
        },
        /** "Third of G: pure is 14 cents under the piano." In a lesson, "The third: pure is 14 cents under the piano." */
        what() {
            const cents = Math.round(Math.abs(this.reading.pure));
            const where = cents < 1 ? t('sing.ring.pureSame') : this.reading.pure < 0 ? t('sing.ring.pureUnder', { cents }) : t('sing.ring.pureOver', { cents });
            return this.chord ? t('sing.ring.what', { degree: this.degreeName, chord: this.chord, where }) : t('sing.ring.whatLesson', { degree: this.lessonDegree, where });
        },
        /** Ringing; Close; 12 cents high of pure; Steady the tone to tune it. */
        word() {
            const cents = Math.round(Math.abs(this.reading.off));
            if (this.reading.state === 'ringing') return t('sing.ring.ringing');
            if (this.reading.state === 'waved') return t('sing.ring.steady');
            if (this.reading.state === 'close') return t('sing.ring.close', { cents });
            return this.reading.off > 0 ? t('sing.ring.high', { cents }) : t('sing.ring.low', { cents });
        },
    },
    methods: {
        at(cents) {
            const range = Math.max(RANGE, Math.ceil((Math.abs(this.reading.pure) + 10) / 10) * 10);
            return `${50 + (Math.max(-range, Math.min(range, cents)) / range) * 50}%`;
        },
    },
};
