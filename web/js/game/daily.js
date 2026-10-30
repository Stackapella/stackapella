// The daily challenge (docs/game-elements-plan.md; docs/game-server-spec.md, section 1): one song part
// a day, the same for everyone, picked by the server from the date (UTC) among the free songs. In game
// mode, Home's own card for that song wears a "Today's challenge" tag (a reskin, not a new row); the tag
// opens the day's board and Take the challenge, which starts the song on that part; a take of it that
// counts goes on the day's board too. The day's totals are counted (daily_viewed, daily_sung) through
// the anonymous daily counts. The days you took it are stamped on Home's week bulbs (a reskin): from
// this device (its last 14 dates), and from the server's `days` when signed in.

import { reactive } from 'vue';
import { call } from '../api.js';
import { formatNumber, t } from '../i18n.js';
import { navigate } from '../router.js';
import { local, session as tabStorage } from '../storage.js';
import { account } from '../stores/account.js';
import { startFromTemplate } from '../stores/projects.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { count } from '../usage.js';
import { Modal } from '../components/ui.js';
import { boardName, byInitials } from './initials.js';
import { effect } from './effects.js';

const STARTED = 'stackapella.daily'; // this tab's challenge taken: { date, templateId, partKey }

/** Today in UTC, as the server dates the challenge: "2026-10-30". */
export const utcDay = (at = Date.now()) => new Date(at).toISOString().slice(0, 10);

/** Today's challenge, loaded once a visit: { date, templateId, partKey, ... } or null. */
export const daily = reactive({ today: null, loading: false, asked: null });

const STAMPS = 'stackapella.dailyDays'; // the challenge dates you took on this device
const STAMPS_KEPT = 14;

function keptStamps() {
    try {
        const days = JSON.parse(local.get(STAMPS) || '[]');
        return Array.isArray(days) ? days.filter((d) => typeof d === 'string') : [];
    } catch (error) {
        console.warn('The challenge days kept here are unreadable; starting again', error);
        return [];
    }
}

/** The challenge dates you took ("YYYY-MM-DD"): this device's, and the server's when signed in. */
export const dailyDays = reactive(new Set(keptStamps()));

function stamp(days, keep = false) {
    for (const day of days) dailyDays.add(day);
    if (keep) local.set(STAMPS, JSON.stringify([...dailyDays].sort().slice(-STAMPS_KEPT)));
}

/** A scored take of this song part, started from today's challenge: today is stamped. */
export function stampDaily(templateId, partKey) {
    const day = effect('daily') ? dailyFor(templateId, partKey) : null;
    if (day) stamp([day], true);
}

/** Whether Home's bulb for `day` wears the challenge's stamp (game mode, Title screen and Daily challenge on). */
export const stamped = (day) => effect('daily') && effect('title') && dailyDays.has(day);

/**
 * Asks the server once a day a session, and only in game mode with the Daily challenge switch on (so
 * serious mode never asks); a closed or missing challenge isn't asked again until tomorrow.
 */
export async function loadDaily() {
    if (daily.asked === utcDay() || daily.loading || !effect('daily')) return daily.today;
    daily.asked = utcDay();
    daily.loading = true;
    try {
        daily.today = (await call('boards.daily.show', { query: { scope: 'global' } })).data;
        stamp(daily.today?.days ?? []);
    } catch (error) {
        console.info('No challenge today', error?.message ?? error);
        daily.today = null;
    } finally {
        daily.loading = false;
    }
    return daily.today;
}

/** The challenge date a take of this song part sings for, if it was started from today's challenge; else null. */
export function dailyFor(templateId, partKey) {
    try {
        const started = JSON.parse(tabStorage.get(STARTED) || 'null');
        return started && started.templateId === templateId && started.partKey === partKey && started.date === utcDay() ? started.date : null;
    } catch {
        return null;
    }
}

/** Hours and minutes until the challenge ends (the next UTC midnight), as words. */
export function endsIn(endsAt, now = Date.now()) {
    const minutes = Math.max(0, Math.round((Date.parse(endsAt) - now) / 60000));
    return minutes >= 60 ? t('game.daily.endsHours', { hours: formatNumber(Math.floor(minutes / 60)) }) : t('game.daily.endsMinutes', { minutes: formatNumber(minutes) });
}

/** The day's board and Take the challenge, in a sheet. */
export const DailySheet = {
    components: { Modal },
    props: { challenge: { type: Object, required: true } },
    emits: ['close'],
    data: () => ({ board: null, song: null, starting: false, failed: false }),
    setup: () => ({ account, boardName, byInitials, endsIn }),
    template: `
        <Modal :title="t('game.daily.title')" @close="$emit('close')">
            <div class="space-y-4" data-game-daily-sheet>
                <p v-if="song" class="ge-daily-what"><span translate="no">{{ song.title }}</span> · <span translate="no">{{ partName }}</span></p>
                <p v-else class="ge-daily-what text-muted" aria-hidden="true">···</p>
                <p class="text-sm text-muted">{{ endsIn(challenge.endsAt) }}</p>
                <ol v-if="board?.entries?.length" class="space-y-1" :aria-label="t('game.daily.board')">
                    <li v-for="e in board.entries" :key="e.id ?? e.rank" class="flex items-center gap-3 rounded-xl px-2 py-1.5" :class="e.you ? 'bg-accent/10' : ''">
                        <span class="w-6 text-end tabular-nums text-muted">{{ e.rank }}</span>
                        <span class="min-w-0 flex-1 truncate text-cream" translate="no" :data-initials="byInitials(e) ? '' : null">{{ boardName(e) }}</span>
                        <span class="tabular-nums font-semibold text-cream">{{ e.score }}</span>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted">{{ account.me ? t('game.daily.first') : t('game.daily.signIn') }}</p>
                <p v-if="failed" class="text-sm text-bad" role="alert">{{ t('game.daily.failed') }}</p>
                <button type="button" class="btn-primary w-full" :disabled="starting || !song" data-daily-take @click="take">{{ t('game.daily.take') }}</button>
            </div>
        </Modal>`,
    computed: {
        partName() {
            const part = this.song?.parts.find((p) => p.key === this.challenge.partKey);
            return part ? templatePartName(part) : '';
        },
    },
    async created() {
        count('daily_viewed');
        this.board = this.challenge;
        try {
            this.song = await loadTemplate(this.challenge.templateId);
        } catch (error) {
            console.warn('The challenge song did not load', error);
            this.failed = true;
        }
    },
    methods: {
        /** Starts the song on the day's part; a take of it then goes on the day's board too. */
        async take() {
            this.starting = true;
            try {
                tabStorage.set(STARTED, JSON.stringify({ date: this.challenge.date, templateId: this.challenge.templateId, partKey: this.challenge.partKey }));
                const project = await startFromTemplate(this.song, { focusKey: this.challenge.partKey });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.warn('The challenge could not start', error);
                this.failed = true;
                this.starting = false;
            }
        },
    },
};

/** Home's reskin: the day's song card wears this tag, which opens the sheet. */
export const DailyTag = {
    components: { DailySheet },
    props: { challenge: { type: Object, required: true } },
    data: () => ({ open: false }),
    template: `
        <button type="button" class="ge-daily-tag tap-44" :aria-label="t('game.daily.title')" data-game-daily @click.stop="open = true">{{ t('game.daily.tag') }}</button>
        <DailySheet v-if="open" :challenge="challenge" @close="open = false" />`,
};
