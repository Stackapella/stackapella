// Part leaderboards (docs/leaderboards-plan.md; contract: boards.*), the least to build on the
// backend's foundation. After a recorded take of a song's part: "Your best", where you stand among
// friends and worldwide, or one line on why it didn't count (the server judges), and Challenge a
// friend. On the song page: a part's board, Friends or Everyone, the top ten and your place. The
// worldwide board is opt-in, 18 and over, asked once after a first counted score. Course lessons
// never have boards; guests see "Make an account to compete" on the song page only. Someone else's
// name on a board can be reported (reports.create's boardEntryId): two people's reports hold it off
// the worldwide board until an admin looks.

import { APP_VERSION, ApiError, call } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { account } from '../stores/account.js';
import { audio } from '../audio.js';
import { local } from '../storage.js';
import { navigate } from '../router.js';
import { count } from '../usage.js';
import { Modal, toast } from './ui.js';
// What the scores were made with (a new scorer or pitch detector starts new boards).
import { SCORING_VERSION } from '../scoring.js';
import { PITCH_DETECTOR_VERSION } from '../pitchtrack.js';
import { NewRecord } from '../game/record.js';
import { InitialsPicker, boardName, byInitials } from '../game/initials.js';
import { dailyFor } from '../game/daily.js';
import { count as countDaily } from '../usage.js';
import { effect } from '../game/effects.js';

const ASKED = 'stackapella.boards.asked'; // the worldwide question, asked once on this device
const OUTPUT = { speakers: 'speaker', wired: 'wired', bluetooth: 'bluetooth' };
// Why a take didn't count, as the server says.
const WHY = {
    mode: tk('boards.why.mode'),
    key: tk('boards.why.key'),
    speed: tk('boards.why.speed'),
    guide: tk('boards.why.guide'),
    timing: tk('boards.why.timing'),
    bluetooth: tk('boards.why.bluetooth'),
    take: tk('boards.why.take'),
};

/** A recorded take's score, sent for the server to judge: `{ counted, why, best, improved }`. */
export async function submitScore({ templateId, templateVersion, partKey, score, keyOffset = 0, guideMuted, takeId = null }) {
    const body = {
        templateId,
        templateVersion,
        partKey,
        scoringVersion: SCORING_VERSION,
        pitchDetectorVersion: PITCH_DETECTOR_VERSION,
        score: Math.round(score),
        mode: 'record', // only a recording is scored
        keyOffset,
        speed: 1, // takes are recorded at full speed, whatever the practice speed
        guideMuted: !!guideMuted,
        timingChecked: audio.latencyFrames !== null && !audio.latencyEstimated,
        output: OUTPUT[audio.route] ?? 'unknown',
        appVersion: APP_VERSION,
        ...(takeId ? { takeId } : {}),
    };
    // Game mode's daily challenge: a take of the day's song part, started from it, goes on its board too.
    const day = dailyFor(templateId, partKey);
    if (day) {
        body.daily = day;
        countDaily('daily_sung');
    }
    return (await call('boards.scores.submit', { body })).data;
}

/**
 * A part's board: `{ entries, you, onGlobal, current, olderVersions, ... }`; scope 'friends',
 * 'global' or (game mode) 'month', everyone's bests this month; `version` an earlier arrangement's
 * board (read only), else the current one.
 */
export async function boardOf(templateId, partKey, scope, version = null) {
    // Game mode's "This month" (game/effects.js, monthly): everyone's bests set this month.
    const query = scope === 'month' ? { scope: 'global', period: 'month' } : { scope, ...(version ? { version } : {}) };
    return (await call('boards.show', { params: { template: templateId, part: partKey }, query })).data;
}

/** Shows your best score on the worldwide boards, or stops (18 and over only). */
export async function setGlobal(on) {
    return (await call('boards.global.update', { body: { on } })).data;
}

/** The worldwide question (18 and over), once: Not now is the safe answer. */
export const GlobalSheet = {
    components: { Modal },
    emits: ['close'],
    data: () => ({ busy: false }),
    template: `
        <Modal :title="t('boards.optIn.title')" @close="$emit('close')">
            <div class="space-y-4" data-boards-optin>
                <p class="text-cream">{{ t('boards.optIn.body') }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <button ref="safe" type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('boards.optIn.notNow') }}</button>
                    <button type="button" class="btn-primary" :disabled="busy" @click="yes">{{ t('boards.optIn.yes') }}</button>
                </div>
            </div>
        </Modal>`,
    mounted() {
        this.$nextTick(() => this.$refs.safe?.focus());
    },
    methods: {
        async yes() {
            this.busy = true;
            try {
                await setGlobal(true);
                toast(t('boards.optIn.done'));
                this.$emit('close');
            } catch (error) {
                console.warn('The worldwide board choice was not saved', error);
                toast(t('boards.optIn.failed'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};

// The reasons a name can be reported (reports.create's reason), the likely one first.
const REASONS = {
    offensive: tk('boards.report.reasons.offensive'),
    personal: tk('boards.report.reasons.personal'),
    spam: tk('boards.report.reasons.spam'),
    other: tk('boards.report.reasons.other'),
};

/** Report someone's name on a part's board: a reason, an optional note, then thanks. */
export const ReportName = {
    components: { Modal },
    props: { entry: { type: Object, required: true }, partName: { type: String, required: true } },
    emits: ['close'],
    data: () => ({ reason: 'offensive', note: '', website: '', busy: false }),
    setup: () => ({ REASONS, boardName }),
    template: `
        <Modal :title="t('boards.report.title')" @close="$emit('close')">
            <form class="space-y-4" data-board-report @submit.prevent="send">
                <p class="text-cream">{{ t('boards.report.who', { name: boardName(entry), part: partName }) }}</p>
                <fieldset class="space-y-3">
                    <legend class="label">{{ t('boards.report.why') }}</legend>
                    <label v-for="(label, r) in REASONS" :key="r" class="check-row"><input v-model="reason" type="radio" :value="r" class="radio" /> {{ t(label) }}</label>
                </fieldset>
                <label class="block space-y-1.5"><span class="label">{{ t('boards.report.note') }}</span><textarea v-model="note" class="input min-h-20 w-full" maxlength="500" rows="3"></textarea></label>
                <input v-model="website" type="text" name="website" class="hidden" tabindex="-1" autocomplete="off" aria-hidden="true" />
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy">{{ t('boards.report.send') }}</button>
                </div>
            </form>
        </Modal>`,
    methods: {
        async send() {
            this.busy = true;
            try {
                await call('reports.create', { body: { boardEntryId: this.entry.id, reason: this.reason, note: this.note.trim(), website: this.website } });
                toast(t('boards.report.thanks'));
                this.$emit('close');
            } catch (error) {
                if (error instanceof ApiError && error.status === 404) {
                    toast(t('boards.report.gone'));
                    this.$emit('close');
                } else {
                    console.warn('The report was not sent', error);
                    toast(t('boards.report.failed'), { tone: 'error' });
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

/** After a recorded take of a song's part: your best and where you stand, or why it didn't count. */
export const TakeBest = {
    components: { GlobalSheet, NewRecord, InitialsPicker },
    props: { templateId: { type: String, required: true }, templateVersion: { type: Number, required: true }, partKey: { type: String, required: true }, partName: { type: String, required: true }, songTitle: { type: String, required: true }, score: { type: Number, required: true }, keyOffset: { type: Number, default: 0 }, guideMuted: Boolean, takeId: { type: String, default: null } },
    emits: ['result'], // the board's answer (game mode's ghost keeps a new best's line)
    data: () => ({ result: null, friends: null, global: null, asking: false, challenging: false, naming: false }),
    setup: () => ({ WHY, account }),
    template: `
        <div v-if="result" class="space-y-1 text-sm" data-take-best>
            <p v-if="result.counted" class="text-cream"><b data-best>{{ t('boards.best', { score: result.best }) }}</b><!-- Game mode's NEW RECORD in place of "a new best" (docs/game-elements-plan.md): only on a best the board counted --><NewRecord v-if="recordShown" :best="result.best" :before="result.before ?? null" /><span v-else-if="result.improved" class="text-muted"> · {{ t('boards.newBest') }}</span><span v-if="rankLine" class="text-muted"> · {{ rankLine }}</span><span v-if="result.daily?.counted" class="text-muted" data-daily-rank> · {{ t('game.daily.rank', { rank: result.daily.rank }) }}</span>
                <button type="button" class="link ms-2" :disabled="challenging" @click="challenge">{{ t('boards.challenge.button') }}</button></p>
            <p v-else class="text-muted">{{ WHY[result.why] ? t(WHY[result.why]) : t('boards.why.other') }}</p>
            <!-- Game mode's arcade initials, offered on a new record (docs/game-elements-plan.md) -->
            <button v-if="recordShown && account.me && account.me.boardsName !== 'initials'" type="button" class="link" data-enter-initials @click="naming = true">{{ t('game.initials.enter') }}</button>
            <InitialsPicker v-if="naming" @close="naming = false" />
            <GlobalSheet v-if="asking" @close="asking = false" />
        </div>`,
    computed: {
        /** Game mode's NEW RECORD, on a best the board counted. */
        recordShown() {
            return !!(this.result?.counted && this.result.improved && effect('celebrations'));
        },
        rankLine() {
            const parts = [];
            if (this.friends?.you?.rank) parts.push(t('boards.rankFriends', { rank: this.friends.you.rank }));
            if (this.global?.onGlobal && this.global?.you?.rank) parts.push(t('boards.rankWorld', { rank: this.global.you.rank }));
            return parts.join(' · ');
        },
    },
    async created() {
        if (!account.me) return; // a guest's takes aren't scored on the boards
        try {
            this.result = await submitScore(this);
        } catch (error) {
            if (!(error instanceof ApiError)) console.warn('The score was not sent', error);
            return; // an old app or offline: the take's own result says enough
        }
        this.$emit('result', this.result);
        if (!this.result.counted) return;
        const [friends, global] = await Promise.allSettled([boardOf(this.templateId, this.partKey, 'friends'), boardOf(this.templateId, this.partKey, 'global')]);
        this.friends = friends.status === 'fulfilled' ? friends.value : null;
        this.global = global.status === 'fulfilled' ? global.value : null;
        // The worldwide question, once, after a first counted score (18 and over, not already on it).
        if (account.me.isAdult && this.global && !this.global.onGlobal && !local.get(ASKED)) {
            local.set(ASKED, '1');
            this.asking = true;
        }
    },
    methods: {
        /** A challenge link for this part, to the share sheet (or copied). */
        async challenge() {
            this.challenging = true;
            try {
                const { url } = (await call('boards.challenges.create', { body: { templateId: this.templateId, partKey: this.partKey } })).data;
                const text = t('boards.challenge.text', { score: this.result.best, part: this.partName, song: this.songTitle });
                if (navigator.share) {
                    await navigator.share({ text, url }).catch((error) => {
                        if (error?.name !== 'AbortError') throw error;
                    });
                } else {
                    await navigator.clipboard.writeText(`${text} ${url}`);
                    toast(t('boards.challenge.copied'));
                }
            } catch (error) {
                console.warn('The challenge was not made', error);
                toast(t('boards.challenge.failed'), { tone: 'error' });
            } finally {
                this.challenging = false;
            }
        },
    },
};

/**
 * A part's board on the song page: Friends or Everyone, the top ten and your place. A song whose
 * arrangement changed lists its earlier arrangements' boards, each read only.
 */
const SCOPE_WORDS = { friends: tk('boards.friends'), global: tk('boards.everyone'), month: tk('boards.month') };

export const PartBoard = {
    components: { ReportName },
    props: { templateId: { type: String, required: true }, partKey: { type: String, required: true }, partName: { type: String, required: true }, color: { type: String, default: null } },
    data: () => ({ open: false, scope: 'friends', board: null, failed: false, viewed: false, reporting: null, version: null }),
    setup: () => ({ account, boardName, byInitials, SCOPE_WORDS }),
    // Hillel's mockup 1a: one card, "Leaderboard" with the part as a tag and a small switch to its
    // right, then a table (#, singer, score, date) with your row marked YOU, the earlier arrangements at its foot.
    template: `
        <div class="text-sm" data-part-board>
            <button v-if="!open" type="button" class="link inline-flex min-h-6 items-center" aria-expanded="false" @click="toggle">{{ t('boards.title', { part: partName }) }}</button>
            <section v-else class="ge-board mt-1 space-y-3 rounded-2xl border border-line bg-panel p-4" :aria-label="t('boards.title', { part: partName })">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                    <button type="button" class="flex items-center gap-2 text-start" aria-expanded="true" :aria-label="t('boards.hide', { part: partName })" @click="toggle">
                        <span class="text-base font-bold text-cream">{{ t('boards.heading') }}</span>
                        <span class="ge-board-part"><span class="size-2 rounded-full" :style="{ background: color ?? 'currentColor' }" aria-hidden="true"></span>{{ partName }}</span>
                    </button>
                    <div v-if="account.me" class="seg ge-board-scope" role="radiogroup" :aria-label="t('boards.scope')">
                        <button v-for="s in scopes" :key="s" type="button" role="radio" :aria-checked="scope === s" class="seg-option tap-44" :data-board-scope="s" @click="choose(s)">{{ t(SCOPE_WORDS[s]) }}</button>
                    </div>
                </div>
                <p v-if="!account.me" class="text-muted">{{ t('boards.guest') }} <a href="/signin" class="link" @click.prevent="signIn">{{ t('boards.makeAccount') }}</a></p>
                <template v-else>
                    <p v-if="failed" class="text-muted" role="status">{{ t('boards.failed') }}</p>
                    <p v-else-if="!board" class="text-muted" role="status">{{ t('boards.loading') }}</p>
                    <p v-else-if="!board.entries.length" class="text-cream">{{ scope === 'month' ? t('boards.monthEmpty') : t('boards.empty') }}</p>
                    <table v-else class="w-full table-fixed border-separate border-spacing-y-1">
                        <thead>
                            <tr class="text-[11px] font-semibold uppercase tracking-wider text-muted">
                                <th scope="col" class="w-10 px-1 text-start font-semibold">{{ t('boards.col.rank') }}</th>
                                <th scope="col" class="px-2 text-start font-semibold">{{ t('boards.col.singer') }}</th>
                                <th scope="col" class="w-14 px-2 text-end font-semibold">{{ t('boards.col.score') }}</th>
                                <th scope="col" class="hidden w-20 px-2 text-end font-semibold sm:table-cell">{{ t('boards.col.date') }}</th>
                                <th scope="col" class="w-14 p-0"><span class="sr-only">{{ t('boards.report.short') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in board.entries" :key="e.id ?? e.rank + e.name" class="group" :class="e.you ? 'ge-board-you' : ''" data-board-entry :data-you="e.you ? '' : null">
                                <td class="rounded-s-xl px-1 py-2"><span class="ge-board-rank" :class="e.rank === 1 ? 'is-first' : ''">{{ e.rank }}</span></td>
                                <td class="overflow-hidden px-2 py-2">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="truncate font-semibold text-cream" translate="no" :data-initials="byInitials(e) ? '' : null">{{ boardName(e) }}</span>
                                        <span v-if="e.you" class="ge-board-you-tag">{{ t('boards.youTag') }}</span>
                                    </span>
                                </td>
                                <td class="px-2 py-2 text-end text-base font-bold tabular-nums text-cream">{{ e.score }}</td>
                                <td class="hidden whitespace-nowrap px-2 py-2 text-end text-muted sm:table-cell">{{ day(e.at) }}</td>
                                <td class="rounded-e-xl py-2 pe-2 text-end">
                                    <button v-if="!e.you && e.id" type="button" class="tap-44 inline-flex min-h-6 items-center px-1 text-xs text-muted hover:text-cream [@media(hover:hover)]:opacity-0 group-hover:opacity-100 focus-visible:opacity-100" :aria-label="t('boards.report.button', { name: boardName(e) })" data-report-entry @click="reporting = e">{{ t('boards.report.short') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="board && board.you && !board.entries.some((e) => e.you)" class="text-muted">{{ board.you.rank ? t('boards.yourPlace', { rank: board.you.rank, score: board.you.score }) : t('boards.yourBest', { score: board.you.score }) }}</p>
                    <p v-if="board && scope !== 'friends' && !board.onGlobal && account.me.isAdult" class="text-muted">{{ t('boards.notOnGlobal') }}</p>
                    <p v-if="version" class="border-t border-line pt-3 text-muted" data-board-version>{{ t('boards.versions.readOnly', { version }) }} <button type="button" class="link inline-flex min-h-6 items-center" @click="showVersion(null)">{{ t('boards.versions.back') }}</button></p>
                    <p v-else-if="board?.olderVersions?.length && scope !== 'month'" class="border-t border-line pt-3 text-muted" data-board-versions>{{ t('boards.versions.earlier') }}
                        <template v-for="(v, i) in board.olderVersions" :key="v"><template v-if="i">, </template><button type="button" class="link inline-flex min-h-6 items-center" @click="showVersion(v)">{{ t('boards.versions.version', { version: v }) }}</button></template></p>
                    <ReportName v-if="reporting" :entry="reporting" :part-name="partName" @close="reporting = null" />
                </template>
            </section>
        </div>`,
    computed: {
        /** Friends and Everyone; in game mode with Monthly boards on, This month too. */
        scopes: () => (effect('monthly') ? ['friends', 'global', 'month'] : ['friends', 'global']),
    },
    methods: {
        day(at) {
            return formatDate(at, { month: 'short', day: 'numeric' });
        },
        signIn() {
            navigate(`/signin?mode=signup&for=boards&next=${encodeURIComponent(location.pathname)}`);
        },
        toggle() {
            this.open = !this.open;
            if (this.open && account.me && !this.board) this.load();
        },
        choose(scope) {
            this.scope = scope;
            if (scope === 'month') this.version = null; // a month is the current arrangement's
            this.load();
        },
        /** An earlier arrangement's board, or (null) back to the current one. */
        showVersion(version) {
            this.version = version;
            this.load();
        },
        async load() {
            this.board = null;
            this.failed = false;
            try {
                this.board = await boardOf(this.templateId, this.partKey, this.scope, this.version);
                if (!this.viewed) {
                    this.viewed = true;
                    count('leaderboard_viewed');
                }
            } catch (error) {
                if (this.version && error instanceof ApiError && error.status === 404) {
                    // An earlier arrangement with no one on it in this scope.
                    this.board = { entries: [], you: null, onGlobal: false, current: false, olderVersions: [] };
                    return;
                }
                console.warn('The board did not load', error);
                this.failed = true;
            }
        },
    },
};
