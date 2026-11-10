// The daily challenge (docs/game-elements-plan.md; docs/game-server-spec.md, section 1): one song part
// a day, the same for everyone, picked by the server from the date (UTC) among the free songs. In game
// mode, Home's own card for that song wears a "Today's challenge" tag (a reskin, not a new row); the tag
// opens the day's board and Take the challenge, which starts the song on that part; a take of it that
// counts goes on the day's board too. The day's totals are counted (daily_viewed, daily_sung) through
// the anonymous daily counts. The days you took it are stamped on Home's week bulbs (a reskin): from
// this device (its last 14 dates), and from the server's `days` when signed in. The pick, taking it and
// its counts are shared with serious mode's calm line (js/daily-pick.js, docs/daily-challenge-plan.md).

import { AccountWall } from '../components/account-wall.js';
import { reactive } from 'vue';
import { formatNumber, t } from '../i18n.js';
import { local } from '../storage.js';
import { account } from '../stores/account.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { dailyFor, dailyPick, loadPick, takeChallenge, utcDay } from '../daily-pick.js';
import { count } from '../usage.js';
import { Modal } from '../components/ui.js';
import { boardName, byInitials } from './initials.js';
import { effect } from './effects.js';

export { dailyFor, utcDay };

/** Today's challenge, loaded once a visit: { date, templateId, partKey, ... } or null (the pick both skins share). */
export const daily = dailyPick;

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
    if (!effect('daily')) return daily.today;
    const today = await loadPick();
    stamp(today?.days ?? []);
    return today;
}

/** Days, hours or minutes until the challenge (or the week's cup) ends, as words. */
export function endsIn(endsAt, now = Date.now()) {
    const minutes = Math.max(0, Math.round((Date.parse(endsAt) - now) / 60000));
    if (minutes >= 48 * 60) return t('game.daily.endsDays', { days: Math.floor(minutes / (24 * 60)) });
    return minutes >= 60 ? t('game.daily.endsHours', { hours: formatNumber(Math.floor(minutes / 60)) }) : t('game.daily.endsMinutes', { minutes: formatNumber(minutes) });
}

/** The day's board and Take the challenge, in a sheet; with the week's cup (its days added up) a choice inside it. */
export const DailySheet = {
    components: { AccountWall, Modal },
    props: { challenge: { type: Object, required: true } },
    emits: ['close'],
    data: () => ({ board: null, song: null, starting: false, failed: false, view: 'today' }),
    setup: () => ({ account, boardName, byInitials, endsIn }),
    template: `
        <Modal :title="t('game.daily.title')" @close="$emit('close')">
            <div class="space-y-4" data-game-daily-sheet>
                <p v-if="song" class="ge-daily-what"><span translate="no">{{ song.title }}</span> · <span translate="no">{{ partName }}</span></p>
                <p v-else class="ge-daily-what text-muted" aria-hidden="true">···</p>
                <!-- The week's cup (its days added up), inside the day's board: a choice here, never a new Home row. -->
                <div v-if="challenge.cup" class="seg flex" role="radiogroup" :aria-label="t('game.daily.which')">
                    <button v-for="v in ['today', 'cup']" :key="v" type="button" role="radio" :aria-checked="view === v" class="seg-option flex-1" :data-daily-view="v" @click="view = v">{{ v === 'today' ? t('game.daily.today') : t('game.daily.cup') }}</button>
                </div>
                <template v-if="view === 'cup' && challenge.cup">
                    <p class="text-sm text-muted">{{ t('game.daily.cupHow') }} {{ endsIn(challenge.cup.endsAt) }}</p>
                    <ol v-if="challenge.cup.entries.length" class="space-y-1" :aria-label="t('game.daily.cup')" data-daily-cup>
                        <li v-for="e in challenge.cup.entries" :key="e.id ?? e.rank" class="flex items-center gap-3 rounded-xl px-2 py-1.5" :class="e.you ? 'bg-accent/10' : ''">
                            <span class="w-6 text-end tabular-nums text-muted">{{ e.rank }}</span>
                            <span class="min-w-0 flex-1 truncate text-cream" translate="no" :data-initials="byInitials(e) ? '' : null">{{ boardName(e) }}</span>
                            <span class="text-xs text-muted">{{ t('game.daily.cupDays', { count: e.days }) }}</span>
                            <span class="tabular-nums font-semibold text-cream">{{ e.points }}</span>
                        </li>
                    </ol>
                    <p v-else-if="account.me" class="text-sm text-muted">{{ t('game.daily.cupEmpty') }}</p>
                    <AccountWall v-else reason="daily" bare />
                    <p v-if="challenge.cup.you && !challenge.cup.entries.some((e) => e.you)" class="text-sm text-muted">{{ challenge.cup.you.rank ? t('game.daily.cupYou', { rank: challenge.cup.you.rank, points: challenge.cup.you.points }) : t('game.daily.cupYours', { points: challenge.cup.you.points }) }}</p>
                </template>
                <template v-else>
                    <p class="text-sm text-muted">{{ endsIn(challenge.endsAt) }}</p>
                    <ol v-if="board?.entries?.length" class="space-y-1" :aria-label="t('game.daily.board')">
                        <li v-for="e in board.entries" :key="e.id ?? e.rank" class="flex items-center gap-3 rounded-xl px-2 py-1.5" :class="e.you ? 'bg-accent/10' : ''">
                            <span class="w-6 text-end tabular-nums text-muted">{{ e.rank }}</span>
                            <span class="min-w-0 flex-1 truncate text-cream" translate="no" :data-initials="byInitials(e) ? '' : null">{{ boardName(e) }}</span>
                            <span class="tabular-nums font-semibold text-cream">{{ e.score }}</span>
                        </li>
                    </ol>
                    <p v-else-if="account.me" class="text-sm text-muted">{{ t('game.daily.first') }}</p>
                    <AccountWall v-else reason="daily" bare />
                </template>
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
        /** Starts the song on the day's part (your copy of it, if you have one); a take of it then goes on the day's board too. */
        async take() {
            this.starting = true;
            try {
                await takeChallenge(this.challenge, this.song);
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
