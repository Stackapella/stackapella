// Choir settings, on the ready choir page (the coordinator for Hillel, 2026-11-22; contract: ownChoir.show's
// `settings`, requests.sound, requests.remake). Two sections in the take review's layout (docs/design-rules.md,
// Reference layout): Sound, free and instant (the room and each part's balance, played at once through the app's mix,
// polish.js, and kept with requests.sound), and How it's sung, a remake (vibrato, natural variation, voicing, breaths, timing
// and pitch between singers, quality) with Remake my choir saying the exact minutes it will use. The old version plays until the
// new one is ready. Everyone sees it; without Plus every control opens the Plus sheet instead.

import { formatNumber, t, tk } from '../i18n.js';
import { templatePartName } from '../templates.js';
import { Segmented } from './controls.js';
import { Icon } from './icon.js';
import { count } from '../usage.js';

const ROOMS = ['dry', 'small', 'hall', 'chapel'];
const SUNG = {
    // Hillel via the coordinator, 2026-11-22: Vibrato and Natural variation in place of the old Humanize slider.
    vibrato: ['none', 'light', 'natural', 'strong'],
    variation: ['steady', 'natural', 'loose'],
    voicing: ['auto', 'low', 'full', 'written'], // "full": both octaves, six parts (a low twin for soprano and alto)
    breaths: ['legato', 'natural'],
    ensembleTiming: ['tight', 'natural', 'loose'],
    pitchSpread: ['off', 'subtle', 'wide'],
    quality: ['standard', 'fine'],
};
const DEFAULT_SOUND = { room: 'small', balance: [] }; // Small room by default (Hillel, 2026-11-24)
// The server's sound when unset (ai-backend): natural vibrato and variation, a clean line, legato, together, in tune, standard.
const DEFAULT_SUNG = { vibrato: 'natural', variation: 'natural', voicing: 'auto', breaths: 'legato', ensembleTiming: 'tight', pitchSpread: 'off', quality: 'standard' };

// The words for each choice (`off` is said "Exact"), and each row's label.
const WORDS = {
    vibrato: { none: tk('ownChoir.settings.vibratoOptions.none'), light: tk('ownChoir.settings.vibratoOptions.light'), natural: tk('ownChoir.settings.vibratoOptions.natural'), strong: tk('ownChoir.settings.vibratoOptions.strong') },
    variation: { steady: tk('ownChoir.settings.variationOptions.steady'), natural: tk('ownChoir.settings.variationOptions.natural'), loose: tk('ownChoir.settings.variationOptions.loose') },
    room: { dry: tk('ownChoir.settings.rooms.dry'), small: tk('ownChoir.settings.rooms.small'), hall: tk('ownChoir.settings.rooms.hall'), chapel: tk('ownChoir.settings.rooms.chapel') },
    voicing: { auto: tk('ownChoir.settings.voicingOptions.auto'), low: tk('ownChoir.settings.voicingOptions.low'), full: tk('ownChoir.settings.voicingOptions.full'), written: tk('ownChoir.settings.voicingOptions.written') },
    breaths: { legato: tk('ownChoir.settings.breathsOptions.smooth'), natural: tk('ownChoir.settings.breathsOptions.natural') },
    ensembleTiming: { tight: tk('ownChoir.settings.timingOptions.tight'), natural: tk('ownChoir.settings.timingOptions.natural'), loose: tk('ownChoir.settings.timingOptions.loose') },
    pitchSpread: { off: tk('ownChoir.settings.pitchOptions.exact'), subtle: tk('ownChoir.settings.pitchOptions.subtle'), wide: tk('ownChoir.settings.pitchOptions.wide') },
    quality: { standard: tk('ownChoir.settings.qualityOptions.standard'), fine: tk('ownChoir.settings.qualityOptions.fine') },
};
const LABEL = { vibrato: tk('ownChoir.settings.vibrato'), variation: tk('ownChoir.settings.variation'), voicing: tk('ownChoir.settings.voicing'), breaths: tk('ownChoir.settings.breaths'), ensembleTiming: tk('ownChoir.settings.timing'), pitchSpread: tk('ownChoir.settings.pitch'), quality: tk('ownChoir.settings.quality') };
// One muted line under each row (the coordinator's review).
const HINT = { vibrato: tk('ownChoir.settings.vibratoHint'), variation: tk('ownChoir.settings.variationHint'), voicing: tk('ownChoir.settings.voicingHint'), breaths: tk('ownChoir.settings.breathsHint'), ensembleTiming: tk('ownChoir.settings.timingHint'), pitchSpread: tk('ownChoir.settings.pitchHint'), quality: tk('ownChoir.settings.fineHint') };
const same = (a, b) => Object.keys(DEFAULT_SUNG).every((key) => a[key] === b[key]);
// Hillel's Choir Result design (2026-11-24): four rows in view, breaths, timing and pitch under a fold.
const MAIN = ['vibrato', 'variation', 'voicing', 'quality'];
const FINE = ['breaths', 'ensembleTiming', 'pitchSpread'];
// A part's balance as the slider shows it: -12 dB (0%) to +6 dB (100%), today's level about two thirds.
const pct = (db) => Math.round(((db + 12) / 18) * 100);

export const ChoirSettings = {
    props: {
        request: { type: Object, required: true },
        template: { type: Object, default: null },
        plus: Boolean, // without Plus, every control opens the Plus sheet
        busy: Boolean,
        minutesLeft: { type: Number, default: null }, // their AI-singing minutes left (ownChoir.show's quota)
        monthly: { type: Number, default: null }, // Plus's AI-singing minutes a month, said once (How it's sung's hint)
        hasLead: Boolean, // a whole-song take as the lead: the Lead blend
        hasRecording: Boolean, // their clip was sung to this song: it can be mixed in
        soundNow: { type: Object, default: null }, // the sound as heard (the choir's own, or the remembered room): views/own-choir.js
        who: { type: Array, default: () => [] }, // each part's singer (views/own-choir.js whoSings): label, ai, note, friend
        invitable: { type: Array, default: () => [] }, // the parts a friend may be invited to sing (Invite a friend on their rows)
    },
    components: { Segmented, Icon },
    emits: ['sound', 'remake', 'plus', 'invite'],
    data() {
        // Only the fields the app sends (an older song's `humanize` is read by the server as vibrato and variation).
        const saved = this.request.settings?.sung ?? {};
        return { sung: Object.fromEntries(Object.keys(DEFAULT_SUNG).map((key) => [key, saved[key] ?? DEFAULT_SUNG[key]])), opened: false, fine: false };
    },
    setup: () => ({ ROOMS, SUNG, WORDS, LABEL, HINT, MAIN, FINE }),
    computed: {
        sound() {
            return { ...DEFAULT_SOUND, ...(this.soundNow ?? this.request.settings?.sound ?? {}) };
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
        /** Their recording's level as the slider shows it: off, else -25 dB (0%) to 0 dB (100%). */
        recordingPct() {
            const db = this.sound.recordingLevel;
            return db == null || db <= -25 ? 0 : Math.round((db + 25) * 4);
        },
        /** How many settings differ from the choir as made (the remake bar counts them). */
        changes() {
            return Object.keys(DEFAULT_SUNG).filter((key) => this.sung[key] !== this.made[key]).length;
        },
        /** Fine-tuning changed from the defaults: said on the fold while it's closed. */
        fineChanged() {
            return FINE.filter((key) => this.sung[key] !== DEFAULT_SUNG[key]).length;
        },
        /** Each part's mix row: its name, whose voice, its note, and whether a friend may be asked to sing it. */
        rows() {
            return this.parts.map((key) => {
                const w = this.who.find((r) => r.part?.key === key) ?? null;
                return { key, name: this.partLabel(key), whose: w?.friend ? w.label : t('ownChoir.settings.yourVoiceAi'), note: w?.note ?? '', invite: this.invitable.includes(key), pct: pct(this.db(key)) };
            });
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
        /** Lead: 0 their take, 1 the AI's (both in between, the lead doubled). */
        setBlend(event) {
            if (!this.gate()) {
                event.target.value = this.sound.leadBlend ?? 0;
                return;
            }
            this.$emit('sound', { ...this.sound, leadBlend: Number(event.target.value) });
        },
        /** Their recording mixed under the choir: -24 to 0 dB, the left end off. */
        setRecording(event) {
            if (!this.gate()) {
                event.target.value = this.recordingPct;
                return;
            }
            const pct = Number(event.target.value);
            this.$emit('sound', { ...this.sound, recordingLevel: pct <= 0 ? null : -25 + pct / 4 });
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
        reset() {
            if (!this.gate()) return;
            this.$emit('sound', { ...DEFAULT_SOUND, balance: this.parts.map((partKey) => ({ partKey, db: 0 })) });
            this.sung = { ...DEFAULT_SUNG };
        },
        /** Undo: how it's sung back as made. */
        undo() {
            this.sung = { ...this.made };
        },
        remake() {
            if (!this.gate() || !this.changed) return;
            this.$emit('remake', { ...this.sung });
        },
    },
    template: `
        <section class="space-y-9" :aria-label="t('ownChoir.settings.title')" data-choir-settings :data-plus="plus ? 'yes' : 'no'">
            <p v-if="!plus" class="flex items-center gap-2 text-sm text-muted" data-settings-locked><span class="chip-accent gap-1" data-settings-plus-badge><Icon name="lock" :size="12" />{{ t('ownChoir.settings.plusBadge') }}</span>{{ t('ownChoir.settings.locked') }}</p>

            <!-- The mix: instant, no minutes. A row per part (whose voice, its note, Invite a friend where it applies), then the room. -->
            <div class="space-y-3" :class="plus ? '' : 'opacity-60'" data-settings-sound>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <h2 class="text-[19px] font-semibold text-cream">{{ t('ownChoir.settings.mix') }}</h2>
                    <p class="text-[13px] text-subtle">{{ t('ownChoir.settings.mixHint') }}</p>
                </div>
                <div class="card divide-y divide-line px-5">
                    <!-- You, your own recording under the choir (off by default), whenever it was sung to this song (Hillel, 2026-11-24). -->
                    <div v-if="hasRecording" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3.5" data-settings-recording>
                        <div class="min-w-0 flex-[1_1_200px]">
                            <p class="text-base font-semibold text-cream">{{ t('ownChoir.settings.youRecording') }}</p>
                            <p class="text-[13px] text-subtle">{{ t('ownChoir.settings.youRecordingHint') }}</p>
                        </div>
                        <div class="flex min-h-11 flex-[1_1_240px] items-center gap-3">
                            <input type="range" min="0" max="100" step="4" class="block min-w-0 flex-1 accent-[var(--color-accent)]" :value="recordingPct" :aria-label="t('ownChoir.settings.youRecording')" :aria-valuetext="recordingPct ? recordingPct + '%' : t('ownChoir.settings.off')" @change="setRecording" />
                            <span class="min-w-10 shrink-0 text-end text-[13px] text-subtle tabular-nums" aria-hidden="true">{{ recordingPct ? recordingPct + '%' : t('ownChoir.settings.off') }}</span>
                        </div>
                    </div>
                    <div v-for="row in rows" :key="row.key" class="flex flex-wrap items-center gap-x-4 gap-y-2 py-3.5" :data-balance="row.key">
                        <div class="min-w-0 flex-[1_1_200px]">
                            <p class="text-base font-semibold text-cream" translate="no">{{ row.name }}</p>
                            <p class="flex flex-wrap items-center gap-x-1.5 text-[13px] text-subtle"><span>{{ row.whose }}</span><span v-if="row.note">· {{ row.note }}</span>
                                <button v-if="row.invite" type="button" class="link inline-flex min-h-6 items-center font-medium" data-mix-invite @click="$emit('invite')">{{ t('ownChoir.settings.inviteFriend') }}</button></p>
                        </div>
                        <div class="flex min-h-11 flex-[1_1_240px] items-center gap-3">
                            <input type="range" min="-12" max="6" step="1" class="block min-w-0 flex-1 accent-[var(--color-accent)]" :value="db(row.key)" :aria-label="t('ownChoir.settings.volume', { part: row.name })" :aria-valuetext="row.pct + '%'" @change="setBalance(row.key, $event)" />
                            <span class="min-w-10 shrink-0 text-end text-[13px] text-subtle tabular-nums" aria-hidden="true">{{ row.pct }}%</span>
                        </div>
                    </div>
                    <div v-if="hasLead" class="space-y-1 py-3.5" data-settings-blend>
                        <div><p class="text-base font-semibold text-cream">{{ t('ownChoir.settings.leadBlend') }}</p><p class="text-[13px] text-subtle">{{ t('ownChoir.settings.leadBlendHint') }}</p></div>
                        <input type="range" min="0" max="1" step="0.1" class="block min-h-11 w-full accent-[var(--color-accent)]" :value="sound.leadBlend ?? 0" :aria-label="t('ownChoir.settings.leadBlend')" @change="setBlend" />
                        <div class="flex justify-between text-xs text-subtle"><span>{{ t('ownChoir.settings.you') }}</span><span>{{ t('ownChoir.settings.ai') }}</span></div>
                    </div>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2.5 py-3.5">
                        <div class="min-w-0 flex-[1_1_200px]"><p class="text-base font-semibold text-cream">{{ t('ownChoir.settings.room') }}</p><p class="text-[13px] text-subtle">{{ t('ownChoir.settings.roomHint') }}</p></div>
                        <Segmented :model-value="sound.room" :options="roomOptions" :label="t('ownChoir.settings.room')" full class="flex-[1_1_320px] [&_.seg-option]:min-h-11" data-settings-room @update:model-value="setRoom" />
                    </div>
                </div>
            </div>

            <!-- How it's sung: a remake. Four rows, then breaths, timing and pitch under a fold (Back to the defaults in it). -->
            <div class="space-y-3" :class="plus ? '' : 'opacity-60'" data-settings-sung>
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <h2 class="text-[19px] font-semibold text-cream">{{ t('ownChoir.settings.sung') }}</h2>
                    <p class="text-[13px] text-subtle">{{ monthly ? t('ownChoir.settings.sungHintMonthly', { n: monthly }) : t('ownChoir.settings.sungHint') }}</p>
                </div>
                <div class="card divide-y divide-line px-5">
                    <div v-for="name in (fine ? [...MAIN, ...FINE] : MAIN)" :key="name" class="flex flex-wrap items-center gap-x-4 gap-y-2.5 py-3.5" :data-sung="name">
                        <div class="min-w-0 flex-[1_1_200px]">
                            <p class="flex items-center gap-2 text-base font-semibold text-cream">{{ t(LABEL[name]) }}<span v-if="sung[name] !== made[name]" class="size-2 rounded-full bg-accent" :title="t('ownChoir.settings.changed')"><span class="sr-only">{{ t('ownChoir.settings.changed') }}</span></span></p>
                            <p class="text-[13px] text-subtle">{{ t(HINT[name]) }}</p>
                        </div>
                        <Segmented :model-value="sung[name]" :options="sungOptions(name)" :label="t(LABEL[name])" full class="flex-[1_1_320px] [&_.seg-option]:min-h-11" @update:model-value="setSung(name, $event)" />
                    </div>
                    <button type="button" class="flex min-h-[52px] w-full items-center justify-between gap-3 text-start font-semibold text-cream" :aria-expanded="fine ? 'true' : 'false'" data-settings-fine @click="fine = !fine">
                        <span>{{ t('ownChoir.settings.fineTune') }}</span>
                        <span class="text-sm font-medium text-subtle">{{ fine ? t('ownChoir.settings.hide') : fineChanged ? t('ownChoir.settings.changedShow', { n: fineChanged }) : t('ownChoir.settings.show') }}</span>
                    </button>
                    <div v-if="fine && custom" class="py-3">
                        <button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-settings-reset @click="reset">{{ t('ownChoir.settings.reset') }}</button>
                    </div>
                </div>
            </div>

            <!-- The remake bar: a sticky bar at the foot of the screen while something has changed (Undo, Remake), else one quiet line. -->
            <p v-if="remaking" class="text-sm text-cream" role="status" data-remaking>{{ t('ownChoir.settings.remaking') }}</p>
            <div v-else :class="changed ? 'sticky bottom-0 z-10 border-t border-line bg-ink py-3' : 'border-t border-line pt-4'" data-remake-bar :data-changed="changed ? '' : null">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2.5">
                    <div class="min-w-0 flex-[1_1_220px]">
                        <p class="font-semibold text-cream">{{ changed ? t('ownChoir.settings.changes', { count: changes }) : t('ownChoir.settings.nothingToRemake') }}</p>
                        <p class="text-[13px] text-subtle" data-remake-cost>{{ changed || !plus ? costLine : t('ownChoir.settings.remakeNeedsChange') }}</p>
                    </div>
                    <button v-if="changed" type="button" class="btn-secondary min-h-[46px]" data-remake-undo @click="undo">{{ t('ownChoir.settings.undo') }}</button>
                    <button type="button" class="btn-primary min-h-[46px] px-6" :disabled="busy || (plus && !changed)" :aria-busy="busy ? 'true' : null" data-remake @click="remake">{{ t('ownChoir.settings.remake') }}</button>
                </div>
            </div>
        </section>`,
};
