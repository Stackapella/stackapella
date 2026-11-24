// The singing test's boards (docs/singing-test-v1.md, "The boards"): game mode only, a plain reskin of the part boards
// (components/boards.js: its ge-board classes, so game mode dresses both alike). Most improved first, then Best; friends,
// or everyone for those who opted in through the existing board switch (adults). A month's season per test version: on
// it after 3 counted tests on 2 days; until then it says what's still needed. Scores within 8 points share a place.

import { api } from '../api.js';
import { account } from '../stores/account.js';
import { formatNumber, t, tk } from '../i18n.js';

const BOARDS = [
    { key: 'improved', label: tk('singingTest.boards.improved') },
    { key: 'best', label: tk('singingTest.boards.best') },
];
const SCOPES = { friends: tk('singingTest.boards.friends'), global: tk('singingTest.boards.global') };

export const SingingTestBoard = {
    data: () => ({ board: 'improved', scope: 'friends', data: null, failed: false }),
    setup: () => ({ BOARDS, SCOPES, formatNumber }),
    computed: {
        on: () => !!account.me && account.config?.singingTestBoards === true,
        scopes: (vm) => (vm.data?.onGlobal ? ['friends', 'global'] : ['friends']),
        needs() {
            const n = this.data?.you?.needs;
            if (!n || (!n.tests && !n.days)) return '';
            return n.tests ? t('singingTest.boards.needsTests', { count: n.tests }) : t('singingTest.boards.needsDays', { count: n.days });
        },
    },
    watch: {
        on: { immediate: true, handler(now) { if (now) this.load(); } },
    },
    methods: {
        async load() {
            this.failed = false;
            try {
                this.data = (await api.singingTests.boards.show({ query: { board: this.board, scope: this.scope } })).data;
            } catch (error) {
                console.info('The singing test board did not load', error?.code ?? error);
                this.failed = true;
            }
        },
        pick(board, scope) {
            this.board = board;
            this.scope = scope;
            this.data = null;
            this.load();
        },
        shown(e) {
            return this.board === 'improved' ? `+${formatNumber(e.score)}` : formatNumber(e.score);
        },
    },
    template: `
        <section v-if="on" class="ge-board space-y-3 border-t border-line pt-3 text-sm" :aria-label="t('singingTest.boards.title')" data-st-board>
            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
                <div class="seg flex" role="radiogroup" :aria-label="t('singingTest.boards.title')">
                    <button v-for="b in BOARDS" :key="b.key" type="button" role="radio" :aria-checked="board === b.key" class="seg-option tap-44" @click="pick(b.key, scope)">{{ t(b.label) }}</button>
                </div>
                <div v-if="scopes.length > 1" class="seg ge-board-scope flex" role="radiogroup" :aria-label="t('singingTest.boards.scope')">
                    <button v-for="s in scopes" :key="s" type="button" role="radio" :aria-checked="scope === s" class="seg-option tap-44" @click="pick(board, s)">{{ t(SCOPES[s]) }}</button>
                </div>
            </div>
            <p v-if="failed" class="text-muted" role="status">{{ t('singingTest.boards.failed') }}</p>
            <p v-else-if="!data" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <template v-else>
                <p v-if="needs" class="t-hint" data-st-board-needs>{{ needs }}</p>
                <p v-if="!data.entries.length" class="ge-board-empty rounded-xl border border-dashed border-line px-4 py-4 text-center text-muted">{{ t('singingTest.boards.empty') }}</p>
                <ol v-else class="space-y-1">
                    <li v-for="e in data.entries" :key="e.id" class="flex items-center gap-3 rounded-xl px-2 py-2" :class="e.you ? 'ge-board-you' : ''" data-board-entry :data-you="e.you ? '' : null">
                        <span class="ge-board-rank w-8 shrink-0" :class="e.rank === 1 ? 'is-first' : ''">{{ e.rank }}</span>
                        <span class="min-w-0 flex-1 truncate font-semibold text-cream">{{ e.name ?? e.initials }}</span>
                        <span class="shrink-0 text-base font-bold tabular-nums text-cream" data-ui="board-score">{{ shown(e) }}</span>
                    </li>
                </ol>
            </template>
        </section>`,
};
