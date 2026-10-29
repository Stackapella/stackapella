// Practice reminders in Settings (`/settings/reminders`; docs/operations-plan.md, "Reminders"): on
// or off for this device, the days (the weekly goal's by default) and the time, at most one a day.
// The browser asks for permission only when they're turned on, from the tap. The push's link
// "Turn off" lands here.

import { PageHeader } from '../components/page.js';
import { formatDate, t } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { account } from '../stores/account.js';
import { learning, loadLearning } from '../stores/learning.js';
import { changeSchedule, keepWords, loadReminder, needsInstall, pushSupported, turnOffHere, turnOn } from '../reminders.js';
import { Notice } from '../components/form.js';
import { Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { Switch } from '../components/controls.js';
import { askYes, first } from '../funnel.js';

const TIMES = Array.from({ length: 96 }, (_, i) => `${String(Math.floor(i / 4)).padStart(2, '0')}:${String((i % 4) * 15).padStart(2, '0')}`);
// The goal's days, spread through the week (0 is Sunday).
const GOAL_DAYS = { 2: [2, 5], 3: [1, 3, 5], 5: [1, 2, 3, 4, 5] };

export const RemindersView = {
    components: { PageHeader, Notice, Logo, Switch },
    data: () => ({ reminder: null, days: [1, 3, 5], time: '18:30', busy: false, notice: null, failed: false }),
    setup: () => ({ account, navigate, goBack, TIMES, supported: pushSupported(), install: needsInstall() }),
    template: `
        <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8">
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-4 md:gap-6">
                <PageHeader :title="t('reminders.title')" back="/settings"><template #aside><span v-if="onHere" class="text-sm text-muted" role="status">{{ busy ? t('common.saving') : t('common.saved') }}</span></template></PageHeader>
                <Notice v-if="!account.me" tone="info">{{ t('reminders.signIn') }} <button type="button" class="link" @click="navigate('/signin?mode=signup&for=reminders&next=/settings/reminders')">{{ t('wrap.makeAccount') }}</button></Notice>
                <Notice v-else-if="install" tone="info">{{ t('reminders.install') }}</Notice>
                <Notice v-else-if="!supported" tone="info">{{ t('reminders.unsupported') }}</Notice>
                <p v-else-if="!reminder && !failed" class="text-muted" role="status">{{ t('settings.loading') }}</p>
                <Notice v-else-if="failed" tone="bad">{{ failed === 'offline' ? t('common.offline') : t('reminders.notLoaded') }}</Notice>
                <template v-else>
                    <!-- 111.png: one switch, the days as seven buttons with quick picks, the time, and when the next one comes. Changes save as you make them. -->
                    <section class="card overflow-hidden" data-reminders>
                        <div class="flex items-center gap-4 p-5 md:p-6">
                            <div class="min-w-0 flex-1"><p id="reminders-switch" class="text-lg font-bold text-cream">{{ t('reminders.switch') }}</p><p id="reminders-switch-hint" class="text-sm text-muted">{{ t('reminders.switchHint') }}</p></div>
                            <Switch :on="onHere" aria-labelledby="reminders-switch" aria-describedby="reminders-switch-hint" @change="(on) => (on ? save() : off())" />
                        </div>
                        <div class="space-y-5 border-t border-line p-5 md:p-6">
                            <fieldset>
                                <div class="mb-2 flex flex-wrap items-baseline justify-between gap-3">
                                    <legend class="text-sm font-semibold text-muted">{{ t('reminders.days') }}</legend>
                                    <span class="flex gap-4 text-sm font-semibold">
                                        <button type="button" class="min-h-6 text-accent-strong hover:underline" @click="pick([1, 2, 3, 4, 5])">{{ t('reminders.weekdays') }}</button>
                                        <button type="button" class="min-h-6 text-accent-strong hover:underline" @click="pick([1, 2, 3, 4, 5, 6, 0])">{{ t('reminders.everyDay') }}</button>
                                        <button type="button" class="min-h-6 text-accent-strong hover:underline" @click="pick([])">{{ t('reminders.clear') }}</button>
                                    </span>
                                </div>
                                <div class="grid grid-cols-7 gap-1.5 md:gap-2">
                                    <button v-for="day in [1, 2, 3, 4, 5, 6, 0]" :key="day" type="button" class="min-h-11 rounded-xl border text-sm font-semibold transition" :class="days.includes(day) ? 'border-transparent bg-cream text-ink' : 'border-line text-muted hover:text-cream'" :aria-pressed="days.includes(day) ? 'true' : 'false'" @click="toggleDay(day)">{{ dayName(day) }}</button>
                                </div>
                            </fieldset>
                            <label class="flex items-center gap-4 text-sm font-semibold text-muted">{{ t('reminders.time') }}
                                <select v-model="time" class="input w-auto" @change="changed">
                                    <option v-for="value in TIMES" :key="value" :value="value">{{ timeName(value) }}</option>
                                </select>
                            </label>
                            <div v-if="onHere && days.length" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-raised/60 px-4 py-3">
                                <p class="text-cream" role="status">{{ t('reminders.next', { when: nextWhen }) }}</p>
                                <button type="button" class="btn-secondary btn-sm" @click="test">{{ t('reminders.test') }}</button>
                            </div>
                            <p v-else-if="reminder.enabled && !reminder.thisDevice" class="text-sm text-muted">{{ t('reminders.onElsewhere') }}</p>
                            <Notice v-if="notice" :tone="notice.tone">{{ notice.text }}</Notice>
                        </div>
                    </section>
                    <p class="text-sm text-muted">{{ t('reminders.thisDevice') }}</p>
                </template>
            </div>
        </div>`,
    computed: {
        /** "Tomorrow at 6:30 PM", "Today at 6:30 PM" or "Wednesday at 6:30 PM": the next of the chosen days at the time. */
        nextWhen() {
            const [h, m] = this.time.split(':').map(Number);
            const now = new Date();
            for (let ahead = 0; ahead < 8; ahead++) {
                const at = new Date(now.getFullYear(), now.getMonth(), now.getDate() + ahead, h, m);
                if (at <= now || !this.days.includes(at.getDay())) continue;
                const time = this.timeName(this.time);
                if (ahead === 0) return t('reminders.today', { time });
                if (ahead === 1) return t('reminders.tomorrow', { time });
                return t('reminders.onDay', { day: formatDate(at, { weekday: 'long' }), time });
            }
            return '';
        },
        onHere() {
            return !!this.reminder?.enabled && !!this.reminder?.thisDevice;
        },
    },
    watch: {
        'account.me.id': {
            handler(id) {
                if (id && this.supported && !this.install) this.load();
            },
            immediate: true,
        },
    },
    methods: {
        async load() {
            try {
                await loadLearning();
                this.reminder = await loadReminder();
                this.days = this.reminder.days?.length ? [...this.reminder.days] : GOAL_DAYS[learning.progress.goal] ?? [1, 3, 5];
                this.time = this.reminder.time ?? '18:30';
                if (this.onHere) keepWords().catch((error) => console.warn('The reminder words were not kept', error)); // the words follow a change of language
            } catch (error) {
                console.warn('Reminders not loaded', error);
                this.failed = error?.code === 'offline' ? 'offline' : true;
            }
        },
        dayName(day) {
            // 2026-10-25 was a Sunday: day 0.
            return formatDate(Date.UTC(2026, 9, 25 + day, 12), { weekday: 'short', timeZone: 'UTC' });
        },
        timeName(value) {
            const [h, m] = value.split(':').map(Number);
            return formatDate(Date.UTC(2026, 0, 1, h, m), { hour: 'numeric', minute: '2-digit', timeZone: 'UTC' });
        },
        async save({ quiet = false } = {}) {
            if (!this.days.length) {
                this.days = GOAL_DAYS[learning.progress.goal] ?? [1, 3, 5];
            }
            this.busy = true;
            this.notice = null;
            try {
                const days = [...this.days].sort();
                this.reminder = { ...(this.onHere ? await changeSchedule({ enabled: true, days, time: this.time }) : await turnOn({ days, time: this.time }, this.reminder.vapidPublicKey)), thisDevice: true };
                first('f_remind'); // the funnel: reminders on, for this device's first time
                askYes('remind');
                if (!quiet) toast(t('reminders.saved'));
            } catch (error) {
                this.notice = { tone: 'bad', text: error?.code === 'denied' ? t('reminders.denied') : error?.code === 'offline' ? t('common.offline') : error?.code === 'no_worker' ? t('reminders.reload') : t('reminders.failed') };
            } finally {
                this.busy = false;
            }
        },
        toggleDay(day) {
            this.days = this.days.includes(day) ? this.days.filter((d) => d !== day) : [...this.days, day];
            this.changed();
        },
        pick(days) {
            this.days = [...days];
            this.changed();
        },
        /** On here, a change saves at once (with no days left, the reminders go off). */
        changed() {
            if (!this.onHere) return;
            if (this.days.length) this.save({ quiet: true });
            else this.off();
        },
        /** A test: the notification as it will look, shown now by this device (nothing is sent). */
        async test() {
            try {
                const worker = await navigator.serviceWorker.ready;
                await worker.showNotification(t('reminders.push.title'), { body: t('reminders.push.body'), icon: '/icons/icon-192.png', tag: 'reminder-test' });
                toast(t('reminders.testShown'));
            } catch (error) {
                console.warn('The test reminder did not show', error);
                this.notice = { tone: 'bad', text: t('reminders.denied') };
            }
        },
        async off() {
            this.busy = true;
            try {
                // This device only: another device's reminders carry on (the account's switch stays as it is).
                await turnOffHere();
                this.reminder = { ...this.reminder, thisDevice: false };
                toast(t('reminders.turnedOff'));
            } catch (error) {
                this.notice = { tone: 'bad', text: t('reminders.failed') };
            } finally {
                this.busy = false;
            }
        },
    },
};
