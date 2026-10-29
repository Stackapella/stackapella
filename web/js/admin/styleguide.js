// The styleguide (/styleguide), after iaw's: every token and component of forms and the admin, in
// every state, so the two agents' screens look like one product (docs/conventions.md). Shown on
// this machine, or to a signed-in admin.

import { api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { PART_COLORS } from '../theme.js';
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { BirthDate } from '../components/birth.js';
import { Checkbox, Range, Segmented, Stepper, Switch } from '../components/controls.js';
import { Icon, Logo } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { confirmAction } from '../components/confirm.js';
import { sendToSignIn } from './auth.js';

const TOKENS = ['bg-ink', 'bg-panel', 'bg-raised', 'bg-line', 'bg-cream', 'bg-muted', 'bg-accent', 'bg-good', 'bg-warn', 'bg-bad'];
const KINDS = { primary: tk('styleguide.kind.primary'), secondary: tk('styleguide.kind.secondary'), ghost: tk('styleguide.kind.ghost'), danger: tk('styleguide.kind.danger') };
const VIEWS = { words: tk('styleguide.view.words'), notes: tk('styleguide.view.notes'), staff: tk('styleguide.view.staff') };
const CHIPS = { soprano: tk('parts.soprano'), alto: tk('parts.alto'), tenor: tk('parts.tenor'), bass: tk('parts.bass') };
const ICONS = ['play', 'pause', 'stop', 'record', 'mic', 'headphones', 'metronome', 'timing', 'note', 'loop', 'repeat', 'check', 'close', 'back', 'more', 'plus', 'trash', 'download', 'lock', 'arrow', 'alert', 'info', 'shield', 'home', 'list'];

export const StyleguideView = {
    components: { BirthDate, Checkbox, EmptyState, Field, Icon, Logo, Modal, Notice, Range, Segmented, StatusChip, Stepper, Switch },
    // Sample content, as people would type it (not our words). i18n-ignore
    data: () => ({ allowed: false, sheet: false, name: 'Amazing Grace', email: 'not an email', about: 'I sing alto in a community choir.', handle: 'sam.sings', link: 'https://stackapella.com/s/k3v9qa', choice: 'two', songs: ['Amazing Grace', 'Silent Night'], sounds: true, others: false, view: 'notes', singers: 4, bpm: 96, level: 70, adult: true, beats: 4, chips: ['alto'], month: 3, year: new Date().getFullYear() - 9 }), // i18n-ignore
    setup: () => ({ TOKENS, ICONS, PART_COLORS, KINDS, CHIPS, formatNumber }),
    template: `
        <div class="min-h-dvh bg-ink">
            <div v-if="allowed" class="container-page space-y-14 py-10">
                <header>
                    <Logo />
                    <p class="eyebrow mt-8">{{ t('styleguide.eyebrow') }}</p>
                    <h1 class="mt-2 text-4xl font-extrabold tracking-tight text-cream sm:text-5xl">{{ t('styleguide.title') }}</h1>
                    <p class="mt-3 max-w-prose text-muted">{{ t('styleguide.intro') }}</p>
                </header>

                <section aria-labelledby="sg-colour">
                    <h2 id="sg-colour" class="text-2xl font-bold text-cream">{{ t('styleguide.colour') }}</h2>
                    <div class="mt-6 grid grid-cols-8 overflow-hidden rounded-2xl border border-line" translate="no">
                        <div v-for="(c, i) in PART_COLORS" :key="c" class="h-14" :style="{ background: c }" :title="'part-' + (i + 1)"><span class="sr-only">{{ 'part-' + (i + 1) }}</span></div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                        <div v-for="token in TOKENS" :key="token" class="card overflow-hidden"><div class="h-12" :class="token"></div><p class="px-3 py-2 tabular-nums text-xs text-muted" translate="no">{{ token.slice(3) }}</p></div>
                    </div>
                </section>

                <section aria-labelledby="sg-type">
                    <h2 id="sg-type" class="text-2xl font-bold text-cream">{{ t('styleguide.type') }}</h2>
                    <div class="card mt-6 space-y-4 p-6">
                        <p class="text-5xl font-extrabold tracking-tight text-cream">{{ t('styleguide.display') }}</p>
                        <p class="text-3xl font-extrabold tracking-tight text-cream">{{ t('styleguide.pageHeading') }}</p>
                        <p class="text-xl font-bold text-cream">{{ t('styleguide.sectionHeading') }}</p>
                        <p class="max-w-prose text-cream">{{ t('styleguide.body') }}</p>
                        <p class="text-sm text-muted">{{ t('styleguide.secondary') }}</p>
                        <p class="eyebrow">{{ t('styleguide.eyebrowLabel') }}</p>
                        <p><a href="#sg-type" class="link">{{ t('styleguide.link') }}</a> · <span class="kbd">J</span> <span class="kbd">K</span></p>
                    </div>
                </section>

                <section aria-labelledby="sg-buttons">
                    <h2 id="sg-buttons" class="text-2xl font-bold text-cream">{{ t('styleguide.buttons') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguide.buttonsHelp') }}</p>
                    <div class="card mt-6 space-y-5 p-5" data-sg-buttons>
                        <div v-for="(word, kind) in KINDS" :key="kind" class="flex flex-wrap items-center gap-3">
                            <span class="w-24 text-sm font-semibold text-muted">{{ t(word) }}</span>
                            <button type="button" :class="'btn-' + kind">{{ t('styleguide.save') }}</button>
                            <button type="button" :class="'btn-' + kind" disabled>{{ t('styleguide.disabled') }}</button>
                            <button type="button" :class="'btn-' + kind" aria-busy="true"><span class="spinner"></span>{{ t('styleguide.sending') }}</button>
                            <button type="button" :class="['btn-' + kind, 'btn-sm', 'tap-44']"><Icon name="plus" :size="16" /> {{ t('styleguide.small') }}</button>
                            <button type="button" :class="['btn-' + kind, 'btn-lg']">{{ t('styleguide.large') }}</button>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 border-t border-edge pt-5">
                            <span class="w-24 text-sm font-semibold text-muted">{{ t('styleguide.chips') }}</span>
                            <button v-for="(word, chip) in CHIPS" :key="chip" type="button" class="filter-chip" :aria-pressed="chips.includes(chip) ? 'true' : 'false'" @click="toggleChip(chip)"><Icon v-if="chips.includes(chip)" name="check" :size="14" />{{ t(word) }}</button>
                            <span class="chip-accent">{{ t('styleguide.accentChip') }}</span>
                        </div>
                    </div>
                    <div class="card mt-4 flex flex-wrap gap-2 p-5">
                        <StatusChip tone="good" :label="t('styleguide.statusGood')" />
                        <StatusChip tone="muted" :label="t('styleguide.statusMuted')" />
                        <StatusChip tone="warn" :label="t('styleguide.statusWarn')" />
                        <StatusChip tone="bad" :label="t('styleguide.statusBad')" />
                        <StatusChip tone="info" :label="t('styleguide.statusInfo')" />
                    </div>
                </section>

                <section aria-labelledby="sg-controls">
                    <h2 id="sg-controls" class="text-2xl font-bold text-cream">{{ t('styleguide.controls') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguide.controlsHelp') }}</p>
                    <div class="mt-6 grid gap-4 md:grid-cols-2" data-sg-controls>
                        <div class="card divide-y divide-edge px-5">
                            <Switch :on="sounds" :label="t('styleguide.switchOne')" :hint="t('styleguide.switchOneHint')" class="py-3" @change="sounds = $event" />
                            <Switch :on="others" :label="t('styleguide.switchTwo')" :tag="t('styleguide.switchTag')" class="py-3" @change="others = $event" />
                            <Switch :on="false" :label="t('styleguide.switchOff')" :hint="t('styleguide.switchOffHint')" disabled class="py-3" />
                        </div>
                        <div class="card space-y-4 p-5">
                            <div class="space-y-1.5"><p id="sg-view" class="label">{{ t('styleguide.segLabel') }}</p><Segmented v-model="view" :options="views" labelledby="sg-view" /></div>
                            <div class="space-y-1.5"><p id="sg-singers" class="label">{{ t('styleguide.segFull') }}</p><Segmented v-model="singers" :options="singerOptions" labelledby="sg-singers" full /></div>
                            <div class="flex flex-wrap items-end gap-4">
                                <div class="space-y-1.5"><p class="label">{{ t('styleguide.stepper') }}</p><Stepper v-model="bpm" :min="40" :max="200" :label="t('styleguide.stepper')" :unit="t('styleguide.bpm')" :decrease="t('styleguide.slower')" :increase="t('styleguide.faster')" /></div>
                                <div class="min-w-48 flex-1 space-y-1.5"><label for="sg-range" class="label flex justify-between"><span>{{ t('styleguide.slider') }}</span><span class="font-medium text-muted tabular-nums">{{ formatNumber(level) }}</span></label><Range id="sg-range" v-model="level" /></div>
                            </div>
                        </div>
                        <fieldset class="card p-5">
                            <legend class="sr-only">{{ t('styleguide.choices') }}</legend>
                            <p class="label">{{ t('styleguide.choices') }}</p>
                            <Checkbox v-model="adult" :label="t('styleguide.checkbox')" :hint="t('styleguide.checkboxHint')" />
                            <label class="check-row"><input ref="partly" type="checkbox" class="checkbox" /> {{ t('styleguide.checkboxPartly') }}</label>
                            <label class="check-row"><input type="checkbox" class="checkbox" aria-invalid="true" /> {{ t('styleguide.checkboxError') }}</label>
                            <label class="check-row"><input type="checkbox" class="checkbox" disabled checked /> {{ t('styleguide.checkboxDisabled') }}</label>
                            <div class="mt-2 border-t border-edge pt-2" role="radiogroup" :aria-label="t('styleguide.credit')">
                                <label class="check-row"><input v-model="choice" type="radio" value="two" class="radio" /> {{ t('styleguide.radioOne') }}</label>
                                <label class="check-row"><input v-model="choice" type="radio" value="three" class="radio" /> {{ t('styleguide.radioTwo') }}</label>
                            </div>
                        </fieldset>
                        <div class="card p-5">
                            <p id="sg-cards" class="label">{{ t('styleguide.radioCards') }}</p>
                            <div class="mt-3 grid gap-2.5 sm:grid-cols-2" role="radiogroup" aria-labelledby="sg-cards">
                                <label v-for="n in [3, 4]" :key="n" class="radio-card">
                                    <input v-model="beats" type="radio" :value="n" class="radio mt-px" />
                                    <span><span class="block font-[650]">{{ t('styleguide.beats', { n: formatNumber(n) }) }}</span><span class="mt-0.5 block text-[0.8125rem] text-muted">{{ n === 3 ? t('styleguide.beatsWaltz') : t('styleguide.beatsMost') }}</span></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </section>

                <section aria-labelledby="sg-forms">
                    <h2 id="sg-forms" class="text-2xl font-bold text-cream">{{ t('styleguide.forms') }}</h2>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguide.formsHelp') }}</p>
                    <form class="card mt-6 grid gap-6 p-6 md:grid-cols-2" data-sg-forms @submit.prevent>
                        <Field :label="t('styleguide.fieldTitle')" :help="t('styleguide.fieldTitleHelp')" required v-slot="f">
                            <input :id="f.id" v-model="name" class="input" :aria-describedby="f.describedby" />
                        </Field>
                        <Field :label="t('styleguide.fieldEmail')" :error="t('styleguide.fieldEmailError')" required v-slot="f">
                            <input :id="f.id" v-model="email" type="email" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid" />
                        </Field>
                        <Field :label="t('styleguide.fieldAbout')" :count="formatNumber(about.length) + '/' + formatNumber(300)" v-slot="f">
                            <textarea :id="f.id" v-model="about" rows="3" maxlength="300" class="input" :placeholder="t('styleguide.fieldAboutPlaceholder')" :aria-describedby="f.describedby"></textarea>
                        </Field>
                        <Field :label="t('styleguide.fieldHandle')" :help="t('styleguide.fieldHandleHelp')" required v-slot="f">
                            <div class="relative"><input :id="f.id" v-model="handle" class="input pe-[46px]" autocomplete="off" :aria-describedby="f.describedby" /><span class="spinner input-icon end-[18px]" role="status" :aria-label="t('styleguide.checking')"></span></div>
                        </Field>
                        <Field :label="t('styleguide.fieldSelect')" required v-slot="f">
                            <select :id="f.id" class="input"><option>{{ t('parts.soprano') }}</option><option>{{ t('parts.alto') }}</option></select>
                        </Field>
                        <Field :label="t('styleguide.fieldSelectLong')" :help="t('styleguide.fieldSelectHelp')" required v-slot="f">
                            <select :id="f.id" class="input" :aria-describedby="f.describedby"><option>{{ t('styleguide.fieldSelectLongValue') }}</option></select>
                        </Field>
                        <BirthDate v-model:month="month" v-model:year="year" :hint="t('auth.born.why')" :error="tooYoung ? t('styleguide.bornError') : ''" />
                        <Field :label="t('styleguide.fieldLink')" :help="t('styleguide.fieldLinkHelp')" required v-slot="f">
                            <div class="flex gap-2"><div class="relative min-w-0 flex-1"><input :id="f.id" class="input pe-[46px]" readonly :value="link" :aria-describedby="f.describedby" /><Icon name="lock" :size="18" class="input-icon end-4" /></div><button type="button" class="btn-secondary">{{ t('styleguide.copy') }}</button></div>
                        </Field>
                        <Field :label="t('styleguide.fieldDisabled')" :help="t('styleguide.fieldDisabledHelp')" required v-slot="f">
                            <input :id="f.id" class="input" disabled :value="t('styleguide.fieldDisabledValue')" :aria-describedby="f.describedby" />
                        </Field>
                        <Field :label="t('styleguide.fieldSelectDisabled')" required v-slot="f">
                            <select :id="f.id" class="input" disabled><option>{{ t('parts.tenor') }}</option></select>
                        </Field>
                    </form>
                </section>

                <section aria-labelledby="sg-messages">
                    <h2 id="sg-messages" class="text-2xl font-bold text-cream">{{ t('styleguide.messages') }}</h2>
                    <div class="mt-6 grid gap-3 md:grid-cols-2">
                        <Notice tone="info">{{ t('styleguide.noticeInfo') }}</Notice>
                        <Notice tone="good">{{ t('styleguide.noticeGood') }}</Notice>
                        <Notice tone="warn">{{ t('styleguide.noticeWarn') }}</Notice>
                        <Notice tone="bad">{{ t('styleguide.noticeBad') }}</Notice>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <button type="button" class="btn-secondary" @click="sheet = true">{{ t('styleguide.openSheet') }}</button>
                        <button type="button" class="btn-secondary" @click="showToast">{{ t('styleguide.showToast') }}</button>
                        <button type="button" class="btn-secondary" @click="showConfirm">{{ t('styleguide.showConfirm') }}</button>
                    </div>
                    <p class="mt-2 max-w-prose text-sm text-muted">{{ t('styleguide.confirmHelp') }}</p>
                </section>

                <section aria-labelledby="sg-tables">
                    <h2 id="sg-tables" class="text-2xl font-bold text-cream">{{ t('styleguide.tables') }}</h2>
                    <div class="card mt-6 overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>{{ t('styleguide.colSong') }}</th><th>{{ t('styleguide.colPart') }}</th><th>{{ t('styleguide.colStatus') }}</th><th><span class="sr-only">{{ t('styleguide.colActions') }}</span></th></tr></thead>
                            <tbody>
                                <tr><td class="text-cream" translate="no">{{ songs[0] }}</td><td class="text-muted">{{ t('parts.alto') }}</td><td><StatusChip tone="good" :label="t('styleguide.statusGood')" /></td><td class="text-end"><button type="button" class="btn-secondary btn-sm">{{ t('styleguide.open') }}</button></td></tr>
                                <tr><td class="text-cream" translate="no">{{ songs[1] }}</td><td class="text-muted">{{ t('parts.tenor') }}</td><td><StatusChip tone="warn" :label="t('styleguide.statusWarn')" /></td><td class="text-end"><button type="button" class="btn-secondary btn-sm">{{ t('styleguide.open') }}</button></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 grid gap-4 md:grid-cols-2">
                        <EmptyState icon="list" :title="t('styleguide.emptyTitle')" :body="t('styleguide.emptyBody')"><button type="button" class="btn-secondary">{{ t('styleguide.emptyAction') }}</button></EmptyState>
                        <div class="card space-y-3 p-5" aria-hidden="true"><div class="skeleton h-4 w-2/3"></div><div class="skeleton h-3 w-full"></div><div class="skeleton h-3 w-5/6"></div></div>
                    </div>
                </section>

                <section aria-labelledby="sg-icons">
                    <h2 id="sg-icons" class="text-2xl font-bold text-cream">{{ t('styleguide.icons') }}</h2>
                    <ul class="mt-6 grid grid-cols-4 gap-2 sm:grid-cols-8 lg:grid-cols-12">
                        <li v-for="icon in ICONS" :key="icon" class="card flex flex-col items-center gap-2 p-3 text-cream"><Icon :name="icon" /><span class="tabular-nums text-[0.65rem] text-muted" translate="no">{{ icon }}</span></li>
                    </ul>
                </section>

                <Modal v-if="sheet" :title="t('styleguide.sheetTitle')" @close="sheet = false">
                    <p class="text-cream">{{ t('styleguide.sheetBody') }}</p>
                    <div class="mt-5 flex justify-end gap-3"><button type="button" class="btn-secondary" @click="sheet = false">{{ t('common.cancel') }}</button><button type="button" class="btn-primary primary" @click="sheet = false">{{ t('common.done') }}</button></div>
                </Modal>
            </div>
        </div>`,
    async created() {
        const thisMachine = ['localhost', '127.0.0.1'].includes(location.hostname) || location.hostname.endsWith('.test');
        if (thisMachine) {
            this.allowed = true;
            return;
        }
        try {
            await api.admin.me();
            this.allowed = true;
        } catch (error) {
            if (!sendToSignIn(error)) console.error('admin.me failed', error);
        }
    },
    computed: {
        views: () => Object.entries(VIEWS).map(([value, word]) => ({ value, label: t(word) })),
        singerOptions: () => [1, 2, 3, 4].map((value) => ({ value, label: formatNumber(value) })),
        /** The birth date's error shows only once an answer fails (under 13), never at rest. */
        tooYoung() {
            return !!(this.month && this.year) && new Date().getFullYear() - this.year < 13;
        },
    },
    mounted() {
        this.markPartly();
    },
    updated() {
        this.markPartly();
    },
    methods: {
        /** The partly checked box (only script can set it). */
        markPartly() {
            if (this.$refs.partly) this.$refs.partly.indeterminate = true;
        },
        toggleChip(chip) {
            this.chips = this.chips.includes(chip) ? this.chips.filter((c) => c !== chip) : [...this.chips, chip];
        },
        /** The shared confirmation (components/confirm.js), never the browser's own. */
        async showConfirm() {
            const sure = await confirmAction({ title: t('styleguide.confirmTitle'), body: t('styleguide.confirmBody'), action: t('styleguide.confirmAction') });
            toast(sure ? t('styleguide.confirmed') : t('styleguide.cancelled'));
        },
        showToast() {
            toast(t('styleguide.toast'), { actions: [{ label: t('common.undo'), run: () => {} }] });
        },
    },
};
