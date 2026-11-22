// Choir settings, on the ready choir page (the coordinator for Hillel, 2026-11-22; contract: ownChoir.show's
// `settings`, requests.sound, requests.remake). Two sections in the take review's layout (docs/design-rules.md,
// Reference layout): Sound, free and instant (the room and each part's balance, played at once through the app's mix,
// polish.js, and kept with requests.sound), and How it's sung, a remake (feel, voicing, breaths, timing and pitch
// between singers, quality) with Remake my choir saying the exact minutes it will use. The old version plays until the
// new one is ready. Everyone sees it; without Plus every control opens the Plus sheet instead.

import { formatNumber, t, tk } from '../i18n.js';
import { templatePartName } from '../templates.js';
import { Segmented } from './controls.js';
import { count } from '../usage.js';

const ROOMS = ['dry', 'small', 'hall', 'chapel'];
const SUNG = {
    voicing: ['auto', 'low', 'full', 'written'], // "full": both octaves, six parts (a low twin for soprano and alto)
    breaths: ['legato', 'natural'],
    ensembleTiming: ['tight', 'natural', 'loose'],
    pitchSpread: ['off', 'subtle', 'wide'],
    quality: ['standard', 'fine'],
};
const DEFAULT_SOUND = { room: 'dry', balance: [] };
// Today's sound (ai-modal's worker): a clean line, legato, together, in tune, standard.
const DEFAULT_SUNG = { humanize: 0, voicing: 'auto', breaths: 'legato', ensembleTiming: 'tight', pitchSpread: 'off', quality: 'standard' };

// The words for each choice (`off` is said "Exact"), and each row's label.
const WORDS = {
    room: { dry: tk('ownChoir.settings.rooms.dry'), small: tk('ownChoir.settings.rooms.small'), hall: tk('ownChoir.settings.rooms.hall'), chapel: tk('ownChoir.settings.rooms.chapel') },
    voicing: { auto: tk('ownChoir.settings.voicingOptions.auto'), low: tk('ownChoir.settings.voicingOptions.low'), full: tk('ownChoir.settings.voicingOptions.full'), written: tk('ownChoir.settings.voicingOptions.written') },
    breaths: { legato: tk('ownChoir.settings.breathsOptions.smooth'), natural: tk('ownChoir.settings.breathsOptions.natural') },
    ensembleTiming: { tight: tk('ownChoir.settings.timingOptions.tight'), natural: tk('ownChoir.settings.timingOptions.natural'), loose: tk('ownChoir.settings.timingOptions.loose') },
    pitchSpread: { off: tk('ownChoir.settings.pitchOptions.exact'), subtle: tk('ownChoir.settings.pitchOptions.subtle'), wide: tk('ownChoir.settings.pitchOptions.wide') },
    quality: { standard: tk('ownChoir.settings.qualityOptions.standard'), fine: tk('ownChoir.settings.qualityOptions.fine') },
};
const LABEL = { voicing: tk('ownChoir.settings.voicing'), breaths: tk('ownChoir.settings.breaths'), ensembleTiming: tk('ownChoir.settings.timing'), pitchSpread: tk('ownChoir.settings.pitch'), quality: tk('ownChoir.settings.quality') };
// One muted line under each row (the coordinator's review).
const HINT = { voicing: tk('ownChoir.settings.voicingHint'), breaths: tk('ownChoir.settings.breathsHint'), ensembleTiming: tk('ownChoir.settings.timingHint'), pitchSpread: tk('ownChoir.settings.pitchHint'), quality: tk('ownChoir.settings.fineHint') };
const same = (a, b) => Object.keys(DEFAULT_SUNG).every((key) => a[key] === b[key]);

export const ChoirSettings = {
    props: {
        request: { type: Object, required: true },
        template: { type: Object, default: null },
        plus: Boolean, // without Plus, every control opens the Plus sheet
        busy: Boolean,
        minutesLeft: { type: Number, default: null }, // their AI-singing minutes left (ownChoir.show's quota)
    },
    components: { Segmented },
    emits: ['sound', 'remake', 'plus'],
    data() {
        return { sung: { ...DEFAULT_SUNG, ...(this.request.settings?.sung ?? {}) }, opened: false };
    },
    setup: () => ({ ROOMS, SUNG, WORDS, LABEL, HINT }),
    computed: {
        sound() {
            return { ...DEFAULT_SOUND, ...(this.request.settings?.sound ?? {}) };
        },
        parts() {
            return (this.request.parts ?? []).map((p) => p.partKey);
        },
        /** The exact minutes for the settings chosen, as the server charges them (both octaves: six parts). */
        minutes() {
            const m = this.request.remakeMinutes ?? {};
            if (this.sung.voicing === 'full') return (this.sung.quality === 'fine' ? m.fullFine : m.fullStandard) ?? null;
            return m[this.sung.quality] ?? null;
        },
        /** How it's sung as made now: Remake waits until something here differs from it. */
        made() {
            return { ...DEFAULT_SUNG, ...(this.request.settings?.sung ?? {}) };
        },
        changed() {
            return !same(this.sung, this.made);
        },
        /** Anything away from today's sound (Back to the defaults shows only then). */
        custom() {
            return this.sound.room !== DEFAULT_SOUND.room || this.sound.balance.some((b) => b.db) || !same(this.sung, DEFAULT_SUNG);
        },
        roomOptions() {
            return ROOMS.map((room) => ({ value: room, label: t(WORDS.room[room]) }));
        },
        costLine() {
            if (this.minutes == null) return '';
            return this.minutesLeft == null ? t('ownChoir.settings.remakeCost', { minutes: this.minutes }) : t('ownChoir.settings.remakeOf', { minutes: this.minutes, left: formatNumber(Math.max(0, Math.floor(this.minutesLeft))) });
        },
        remaking() {
            return this.request.remake && this.request.remake.status !== 'failed' ? this.request.remake : null;
        },
    },
    methods: {
        db(key) {
            return this.sound.balance.find((b) => b.partKey === key)?.db ?? 0;
        },
        partLabel(key) {
            // Both octaves' low twins ("soprano-low"): "Soprano (low)", as the server names them.
            const low = key.endsWith('-low');
            const base = low ? key.slice(0, -4) : key;
            const part = this.template?.parts?.find((p) => p.key === base);
            const name = templatePartName(part ?? { key: base, name: base });
            return low ? t('ownChoir.settings.lowTwin', { part: name }) : name;
        },
        gate() {
            if (!this.opened) count(this.plus ? 'choir_settings_opened_plus' : 'choir_settings_opened_locked');
            this.opened = true;
            if (this.plus) return true;
            this.$emit('plus');
            return false;
        },
        setRoom(room) {
            if (!this.gate() || room === this.sound.room) return;
            this.$emit('sound', { ...this.sound, room });
        },
        setBalance(key, event) {
            if (!this.gate()) {
                event.target.value = this.db(key);
                return;
            }
            const db = Number(event.target.value);
            const balance = this.parts.map((partKey) => ({ partKey, db: partKey === key ? db : this.db(partKey) }));
            this.$emit('sound', { ...this.sound, balance });
        },
        sungOptions(name) {
            return SUNG[name].map((value) => ({ value, label: t(WORDS[name][value]) }));
        },
        setSung(name, value) {
            if (!this.gate()) return;
            this.sung = { ...this.sung, [name]: value };
        },
        setHumanize(event) {
            if (!this.gate()) {
                event.target.value = this.sung.humanize;
                return;
            }
            this.sung = { ...this.sung, humanize: Number(event.target.value) };
        },
        reset() {
            if (!this.gate()) return;
            this.$emit('sound', { ...DEFAULT_SOUND, balance: this.parts.map((partKey) => ({ partKey, db: 0 })) });
            this.sung = { ...DEFAULT_SUNG };
        },
        remake() {
            if (!this.gate() || !this.changed) return;
            this.$emit('remake', { ...this.sung });
        },
    },
    template: `
        <section class="space-y-6" aria-labelledby="choir-settings-h" data-choir-settings :data-plus="plus ? 'yes' : 'no'">
            <div class="space-y-1">
                <h2 id="choir-settings-h" class="flex items-center gap-2 text-lg font-bold text-cream">{{ t('ownChoir.settings.title') }}<span v-if="!plus" class="chip-accent" data-settings-plus-badge>{{ t('ownChoir.settings.plusBadge') }}</span></h2>
                <p v-if="!plus" class="text-sm text-muted" data-settings-locked>{{ t('ownChoir.settings.locked') }}</p>
            </div>

            <div class="space-y-3" data-settings-sound>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('ownChoir.settings.sound') }}</p>
                    <p class="text-sm text-muted">{{ t('ownChoir.settings.soundHint') }}</p>
                </div>
                <div class="card divide-y divide-line px-5">
                    <div class="space-y-2 py-4">
                        <div><p class="font-semibold text-cream">{{ t('ownChoir.settings.room') }}</p><p class="text-sm text-muted">{{ t('ownChoir.settings.roomHint') }}</p></div>
                        <Segmented :model-value="sound.room" :options="roomOptions" :label="t('ownChoir.settings.room')" full class="[&_.seg-option]:min-h-11" data-settings-room @update:model-value="setRoom" />
                    </div>
                    <div class="space-y-2 py-4">
                        <div><p class="font-semibold text-cream">{{ t('ownChoir.settings.balance') }}</p><p class="text-sm text-muted">{{ t('ownChoir.settings.balanceHint') }}</p></div>
                        <label v-for="key in parts" :key="key" class="flex min-h-11 items-center justify-between gap-4" :data-balance="key">
                            <span class="w-24 shrink-0 text-sm text-cream" translate="no">{{ partLabel(key) }}</span>
                            <span class="w-full max-w-64">
                                <input type="range" min="-12" max="6" step="1" class="block min-h-11 w-full accent-[var(--color-accent)]" :value="db(key)" :aria-label="partLabel(key)" @change="setBalance(key, $event)" />
                                <span class="flex justify-between text-xs text-muted" aria-hidden="true"><span>{{ t('ownChoir.settings.softer') }}</span><span>{{ t('ownChoir.settings.louder') }}</span></span>
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="space-y-3" data-settings-sung>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('ownChoir.settings.sung') }}</p>
                    <p class="text-sm text-muted">{{ t('ownChoir.settings.sungHint') }}</p>
                </div>
                <div class="card divide-y divide-line px-5">
                    <div class="space-y-1 py-4">
                        <div><p class="font-semibold text-cream">{{ t('ownChoir.settings.humanize') }}</p><p class="text-sm text-muted">{{ t('ownChoir.settings.humanizeHint') }}</p></div>
                        <input type="range" min="0" max="3" step="1" class="block min-h-11 w-full accent-[var(--color-accent)]" :value="sung.humanize" :aria-label="t('ownChoir.settings.humanize')" data-humanize @change="setHumanize" />
                        <span class="flex justify-between px-1" aria-hidden="true"><span v-for="n in 4" :key="n" class="h-1.5 w-px bg-muted"></span></span>
                        <div class="flex justify-between text-xs text-muted"><span>{{ t('ownChoir.settings.clean') }}</span><span>{{ t('ownChoir.settings.real') }}</span></div>
                    </div>
                    <div v-for="(options, name) in SUNG" :key="name" class="space-y-2 py-4" :data-sung="name">
                        <div><p class="font-semibold text-cream">{{ t(LABEL[name]) }}</p><p class="text-sm text-muted">{{ t(HINT[name]) }}</p></div>
                        <Segmented :model-value="sung[name]" :options="sungOptions(name)" :label="t(LABEL[name])" full class="[&_.seg-option]:min-h-11" @update:model-value="setSung(name, $event)" />
                    </div>
                </div>
            </div>

            <!-- The take review's quiet footer: the cost (or the remake under way) on the left, the action on the right. -->
            <div class="space-y-3 border-t border-line pt-4">
                <p v-if="remaking" class="text-sm text-cream" role="status" data-remaking>{{ t('ownChoir.settings.remaking') }}</p>
                <div v-else class="flex flex-wrap items-center justify-between gap-x-5 gap-y-3">
                    <p class="text-sm text-muted" data-remake-cost>{{ changed || !plus ? costLine : t('ownChoir.settings.remakeNeedsChange') }}</p>
                    <button type="button" class="btn-primary max-sm:w-full" :disabled="busy || (plus && !changed)" :aria-busy="busy ? 'true' : null" data-remake @click="remake">{{ t('ownChoir.settings.remake') }}</button>
                </div>
                <button v-if="custom" type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-settings-reset @click="reset">{{ t('ownChoir.settings.reset') }}</button>
            </div>
        </section>`,
};
