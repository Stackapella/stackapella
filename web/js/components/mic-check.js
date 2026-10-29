// The mic check (js/mic-check.js): "Using: <mic> · Change", the level with its voice line, and
// "Say something or hum" until a voice is heard, then "Got you, your mic works" and on by itself.
// Nothing over the line for a while: "We can't hear anything from <mic>" and the picker, open, or,
// when it hears something under the line, "We can hear <mic>, but very quietly" (turn its gain up).
// The watch only runs while the audio does (a suspended context reads silence). A mic
// that fails to open goes to "Let's get your mic working". MicCheck sits inside Find your note;
// MicCheckHost is the sheet every other screen opens through readyMic().

import { audio, chooseMic, currentMicId, engine, looksWireless, micChoices } from '../audio.js';
import { t } from '../i18n.js';
import { checkState, closeMicCheck, meterLevel, micCheck, micDb, rememberMic, stepVoice, voiceWatch } from '../mic-check.js';
import { playSound } from '../sounds.js';
import { Icon } from './icon.js';
import { LevelMeter } from './level-meter.js';
import { micError, WirelessNote } from './mic.js';
import { Modal } from './ui.js';

const GO_ON_MS = 1400; // the tick stays this long, then the screen carries on
const HISTORY = 28; // the sheet's level as this many bars, the newest on the right (103.png)
const HISTORY_MS = 90; // a new bar this often

export const MicCheck = {
    components: { Icon, LevelMeter, WirelessNote },
    // inline: inside a screen (Find your note), with its own heading, and on by itself once it hears you.
    // Otherwise the sheet (103.png): the mic in a card with the level as bars, and Continue is the sheet's.
    props: { inline: Boolean },
    emits: ['done', 'heard'],
    setup: () => ({ audio, micChoices, currentMicId, looksWireless }),
    data: () => ({ db: null, watch: voiceWatch(), picking: false, switching: false, bars: Array(HISTORY).fill(0) }),
    template: `
        <div v-if="!inline" class="space-y-3 rounded-2xl border border-line bg-ink/40 p-4" data-mic-check :data-state="state">
            <div class="relative flex items-center">
                <Icon name="mic" :size="18" class="pointer-events-none absolute start-0 text-muted" />
                <select class="min-h-11 w-full bg-transparent ps-8 font-semibold text-cream" :aria-label="t('studio.menu.mic')" :value="currentMicId()" :disabled="switching" @change="pick($event.target.value)">
                    <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.systemDefault ? t('mic.systemDefaultNamed', { name: m.label }) : m.label }}</option>
                </select>
            </div>
            <div class="flex h-10 items-end gap-1" role="meter" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="levelNow" :aria-label="t('mic.level')">
                <span v-for="(v, i) in bars" :key="i" class="min-w-0 flex-1 rounded-sm transition-[height] duration-75" :class="state === 'ok' ? 'bg-good' : v ? 'bg-muted/60' : 'bg-raised'" :style="{ height: Math.max(12, v * 100) + '%' }"></span>
            </div>
            <p class="flex min-h-6 items-center gap-2 text-sm font-semibold" :class="state === 'ok' ? 'text-good' : 'text-cream'" aria-live="polite" data-mic-check-status>
                <span v-if="state === 'ok'" class="size-2 shrink-0 rounded-full bg-good" aria-hidden="true"></span>
                {{ state === 'ok' ? t('micCheck.soundsGood') : state === 'flat' ? t('micCheck.flat', { name }) : state === 'quiet' ? t('micCheck.quietly', { name }) : t('micCheck.say') }}
            </p>
            <WirelessNote v-if="audio.mic && looksWireless(audio.mic.label)" />
        </div>
        <div v-else class="space-y-4" data-mic-check :data-state="state">
            <p v-if="inline" class="flex items-center gap-2 font-bold text-cream"><Icon name="mic" :size="20" class="text-accent-strong" /> {{ t('micCheck.first') }}</p>
            <div class="flex items-center justify-between gap-3 text-sm text-muted">
                <span class="min-w-0">{{ t('micCheck.using') }} <span class="font-semibold text-cream" translate="no">{{ name }}</span></span>
                <button type="button" class="btn-ghost btn-sm shrink-0" :aria-expanded="pickerShown ? 'true' : 'false'" @click="picking = !picking">{{ t('micCheck.change') }}</button>
            </div>
            <LevelMeter :db="db" />
            <p class="flex min-h-7 items-center gap-2 font-semibold" :class="state === 'ok' ? 'text-accent-strong' : 'text-cream'" aria-live="polite" data-mic-check-status>
                <span v-if="state === 'ok'" class="grid size-7 shrink-0 place-items-center rounded-full bg-accent text-accent-ink" aria-hidden="true"><Icon name="check" :size="16" /></span>
                {{ state === 'ok' ? t('micCheck.gotYou') : state === 'flat' ? t('micCheck.flat', { name }) : state === 'quiet' ? t('micCheck.quietly', { name }) : t('micCheck.say') }}
            </p>
            <label v-if="pickerShown" class="block text-sm text-muted">{{ t('studio.menu.mic') }}
                <select class="input mt-1" :value="currentMicId()" :disabled="switching" @change="pick($event.target.value)">
                    <option v-for="m in micChoices()" :key="m.deviceId" :value="m.deviceId" translate="no">{{ m.systemDefault ? t('mic.systemDefaultNamed', { name: m.label }) : m.label }}</option>
                </select>
            </label>
            <WirelessNote v-if="audio.mic && looksWireless(audio.mic.label)" />
        </div>`,
    computed: {
        state: (vm) => checkState(vm.watch),
        name: (vm) => audio.mic?.label || t('micHelp.yourMic'),
        levelNow: (vm) => Math.round(vm.bars.at(-1) * 100),
        /** The picker: on asking, or when nothing is heard (the wrong mic is the usual reason). */
        pickerShown: (vm) => vm.picking || vm.state === 'flat' || vm.state === 'quiet',
    },
    mounted() {
        let last = performance.now();
        const tick = () => {
            const now = performance.now();
            const dt = Math.min(0.1, (now - last) / 1000);
            last = now;
            const db = micDb();
            // A suspended context reads silence: no time counts toward "can't hear" until it runs.
            if (db !== null && !this.switching && engine.ctx?.state === 'running') {
                this.db = db;
                this.watch = stepVoice(this.watch, db, dt);
            }
            if (!this.inline && now - (this.barAt ?? 0) >= HISTORY_MS) {
                this.barAt = now;
                this.bars = [...this.bars.slice(1), db === null ? 0 : meterLevel(db)];
            }
            if (this.state === 'ok' && !this.heard) {
                this.heard = true;
                rememberMic();
                playSound('micReady');
                this.$emit('heard');
                if (this.inline) {
                    this.going = setTimeout(() => this.$emit('done'), GO_ON_MS);
                    return;
                }
            }
            this.frame = requestAnimationFrame(tick);
        };
        this.frame = requestAnimationFrame(tick);
    },
    unmounted() {
        cancelAnimationFrame(this.frame);
        clearTimeout(this.going);
    },
    methods: {
        async pick(deviceId) {
            this.switching = true;
            try {
                await chooseMic(deviceId);
                this.watch = voiceWatch(); // the new mic gets its own few seconds
                this.heard = false;
                this.picking = false;
            } catch (error) {
                micError(error);
            } finally {
                this.switching = false;
            }
        },
    },
};

/** The sheet readyMic() opens: the check, and Skip the check (on without it; asked again next time). */
export const MicCheckHost = {
    components: { Modal, MicCheck },
    data: () => ({ micCheck, heard: false }),
    // 103.png: Skip (on without it, asked again next time) and Continue once it hears you.
    template: `
        <Modal v-if="micCheck.open" :title="t('micCheck.title')" :subtitle="t('micCheck.hint')" @close="close(false)">
            <div class="space-y-5">
                <MicCheck @heard="heard = true" />
                <div class="-mx-6 flex items-center justify-between gap-3 border-t border-line px-6 pt-5">
                    <button type="button" class="min-h-11 text-sm text-muted underline hover:text-cream" @click="close(true)">{{ t('micCheck.skipShort') }}</button>
                    <button type="button" class="btn-primary" :disabled="!heard" @click="close(true)">{{ t('micCheck.continue') }}</button>
                </div>
            </div>
        </Modal>`,
    watch: {
        'micCheck.open'(open) {
            if (open) this.heard = false;
        },
    },
    methods: {
        close: closeMicCheck,
    },
};
