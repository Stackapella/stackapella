// How to sing (docs/singing-ux-plan.md, "Components"; Hillel: "make all 3 equal weighted options"):
// Part by part, The whole song and Open in the studio, three equal large buttons, each with a small
// picture, its line and how long it takes; a tap starts that way at once. The one chosen last time
// is marked "Last time", never pre-picked. Use this every time (kept on this device: opened by itself
// with your part known, it goes straight in). With no part chosen yet, your part follows, with its
// range and the range check to suggest one. On an iPhone in Safari, installing the app is suggested.

import { midiName } from '../../dsp.js';
import { formatNumber, t, tk } from '../../i18n.js';
import { paint } from '../../colors.js';
import { prefs } from '../../stores/prefs.js';
import { Gems } from '../gems.js';
import { Icon } from '../icon.js';
import { PartSelect } from '../../game/part-select.js';
import { effect } from '../../game/effects.js';

const WAYS = ['parts', 'whole', 'studio'];
const LABELS = { parts: tk('sing.chooser.parts'), whole: tk('sing.chooser.whole'), studio: tk('sing.chooser.studio') };
const HINTS = { parts: tk('sing.chooser.partsHint'), whole: tk('sing.chooser.wholeHint'), studio: tk('sing.chooser.studioHint') };

export const Chooser = {
    components: { Gems, Icon, PartSelect },
    props: {
        parts: { type: Array, required: true }, // [{ slot, name, range: { low, high }, recorded }]
        transpose: { type: Number, default: 0 },
        way: { type: String, default: null }, // the way chosen before, when choosing again
        suggested: { type: String, default: null }, // a slot id the range check suggests
        title: { type: String, default: '' },
        focus: { type: Object, default: null }, // the part already yours: { slot, name, range }
        bpm: { type: Number, default: null },
        phrases: { type: Number, default: 0 },
        length: { type: Number, default: 0 }, // seconds
        auto: Boolean, // opened by itself (not to choose again): Use this every time may go straight in
    },
    emits: ['choose', 'range', 'back', 'studio'],
    data() {
        const remembered = prefs.singWay;
        // The way chosen last time is marked, never picked for you (Hillel).
        return { picked: null, last: this.way ?? prefs.singLast ?? null, partStep: false, always: !!remembered, installHint: /iPhone|iPad/.test(navigator.userAgent) && !(navigator.standalone || matchMedia?.('(display-mode: standalone)').matches) };
    },
    template: `
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-6 md:py-10">
            <header class="flex items-center gap-3">
                <button type="button" class="glass-bubble grid size-11 shrink-0 place-items-center rounded-full text-cream" :aria-label="t('common.back')" @click="partStep ? back() : $emit('back')"><Icon name="back" :size="20" /></button>
                <div class="min-w-0 flex-1">
                    <p v-if="title" class="truncate text-sm text-muted" translate="no">{{ title }}</p>
                    <p v-if="focus" class="flex items-center gap-2 truncate font-bold text-cream" translate="no"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(focus.slot.color) }" aria-hidden="true"></span>{{ t('sing.chooser.yourPartName', { part: focus.name }) }}</p>
                </div>
                <p v-if="facts" class="shrink-0 tabular-nums text-xs text-muted sm:text-sm">{{ facts }}</p>
            </header>

            <template v-if="!partStep">
                <div>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none md:text-4xl">{{ t('sing.chooser.title') }}</h1>
                    <p class="mt-1 text-muted">{{ t('sing.chooser.intro') }}</p>
                </div>
                <!-- Hillel: three equal choices, each a large button that starts that way at once. -->
                <div class="grid gap-4 md:grid-cols-3">
                    <button v-for="w in WAYS" :key="w" type="button" class="card-interactive flex flex-col gap-4 p-5 text-start" :data-way="w" @click="choose(w)">
                        <span class="flex items-center gap-3">
                            <span class="flex-1 text-lg font-bold text-cream">{{ t(LABELS[w]) }}</span>
                            <span v-if="last === w" class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-muted" data-last-time>{{ t('sing.chooser.lastTime') }}</span>
                        </span>
                        <!-- How it goes, drawn: a phrase at a time, every part straight through, or the studio's lanes. -->
                        <span class="relative block space-y-1.5 rounded-2xl border border-line bg-ink/40 p-3" aria-hidden="true">
                            <Gems measure />
                            <template v-if="w === 'parts'">
                                <span class="flex gap-1"><span v-for="i in 6" :key="i" class="h-2.5 flex-1" :data-gem="barColor" :data-gem-state="i % 2 ? 'dim' : null"></span></span>
                                <span class="flex gap-1"><span class="h-2.5 flex-1" data-gem="var(--color-part-4)"></span><span v-for="i in 3" :key="i" class="h-2.5 flex-1" :data-gem="barColor"></span><span class="h-2.5 flex-[2] rounded-full bg-raised"></span></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                            <template v-else-if="w === 'whole'">
                                <span class="block h-2.5" data-gem="var(--color-part-3)"></span>
                                <span class="block h-2.5" data-gem="var(--color-part-4)"></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                            <template v-else>
                                <span class="flex gap-1"><span class="h-2.5 w-1/3" data-gem="var(--color-part-3)"></span><span class="h-2.5 flex-1 rounded-full bg-raised"></span></span>
                                <span class="flex gap-1"><span class="h-2.5 flex-1 rounded-full bg-raised"></span><span class="h-2.5 w-1/2" data-gem="var(--color-part-4)"></span></span>
                                <span class="block h-2.5" :data-gem="barColor"></span>
                            </template>
                        </span>
                        <span class="text-muted">{{ t(HINTS[w]) }}</span>
                        <span class="mt-auto tabular-nums text-xs text-muted">{{ w === 'parts' ? partsTime : w === 'whole' ? wholeTime : t('sing.chooser.studioTime') }}</span>
                    </button>
                </div>
                <p v-if="installHint" class="rounded-2xl border border-line p-4 text-sm text-muted">{{ t('sing.chooser.install') }}</p>
                <label class="check-row"><input v-model="always" type="checkbox" class="checkbox" /> {{ t('sing.chooser.always') }}</label>
            </template>
            <template v-else>
                <div>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('sing.chooser.yourPart') }}</h1>
                    <p class="mt-1 text-muted">{{ t('sing.chooser.yourPartHint') }}</p>
                </div>
                <!-- Game mode's character select (docs/game-elements-plan.md): the same parts and the same choice -->
                <PartSelect v-if="effect('select')" :parts="parts" :transpose="transpose" :suggested="suggested" @choose="(slotId) => $emit('choose', { way: picked, slotId })" />
                <ul v-else class="grid gap-3 md:grid-cols-2">
                    <li v-for="part in parts" :key="part.slot.id">
                        <button type="button" class="card-interactive flex w-full items-center gap-4 p-4 text-start" @click="$emit('choose', { way: picked, slotId: part.slot.id })">
                            <span class="gem size-4 shrink-0 rounded-full" :style="{ '--gem': paint(part.slot.color) }" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-bold text-cream" translate="no">{{ part.name }}<span v-if="part.slot.id === suggested" class="ms-2 text-xs font-semibold text-part-ink-4">{{ t('range.bestForYou') }}</span></span>
                                <span class="tabular-nums text-xs text-muted">{{ part.range ? t('sing.chooser.range', { low: midiName(part.range.low + transpose), high: midiName(part.range.high + transpose) }) : '' }}<template v-if="part.recorded"> · {{ t('sing.chooser.recorded') }}</template></span>
                            </span>
                            <Icon name="arrow" :size="20" class="shrink-0 text-muted" />
                        </button>
                    </li>
                </ul>
                <button type="button" class="btn-secondary self-start" @click="$emit('range')"><Icon name="mic" :size="16" /> {{ t('sing.chooser.checkRange') }}</button>
            </template>
        </div>`,
    setup: () => ({ midiName, WAYS, LABELS, HINTS, effect }),
    computed: {
        /** "B♭2–E♭3 · 90 BPM": your part's range in this key, and the tempo. */
        facts() {
            const r = this.focus?.range;
            const range = r ? t('sing.chooser.rangeShort', { low: midiName(r.low + this.transpose), high: midiName(r.high + this.transpose) }) : '';
            return [range, this.bpm ? t('studio.meta.bpm', { bpm: this.bpm }) : ''].filter(Boolean).join(' · ');
        },
        barColor() {
            return this.focus ? paint(this.focus.slot.color) : 'var(--color-part-1)';
        },
        /** About three minutes a phrase: hear it, sing along, sing it on your own, then record. */
        partsTime() {
            const n = Math.max(1, this.phrases);
            return t('sing.chooser.partsTime', { minutes: formatNumber(n * 3), count: n });
        },
        /** The song once through, and a minute to settle. */
        wholeTime() {
            return t('sing.chooser.wholeTime', { minutes: formatNumber(Math.max(1, Math.ceil(this.length / 60) + 1)) });
        },
    },
    mounted() {
        // Use this every time (Settings' How you sing): opened by itself with your part known, straight in.
        const remembered = prefs.singWay;
        if (this.auto && !this.way && this.focus && remembered) {
            this.$emit('choose', { way: remembered, slotId: this.focus.slot.id });
            return;
        }
        this.$refs.heading?.focus();
    },
    methods: {
        /** A way's button: that way at once (the studio, or your part first when it isn't chosen yet). */
        choose(way) {
            prefs.singLast = way;
            if (way === 'studio') {
                this.$emit('studio');
                return;
            }
            this.picked = way;
            prefs.singWay = this.always ? way : null;
            if (this.focus) {
                this.$emit('choose', { way: this.picked, slotId: this.focus.slot.id });
                return;
            }
            this.partStep = true;
            this.$nextTick(() => this.$refs.heading?.focus());
        },
        back() {
            this.partStep = false;
            this.$nextTick(() => this.$refs.heading?.focus());
        },
    },
};
