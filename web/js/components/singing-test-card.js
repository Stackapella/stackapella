// Your voice's singing test (docs/singing-test-v1.md): "Take the singing test", the latest result and its history,
// hidden in the first week and while the test's switch is off. History compares only within one test version: where a
// new version starts, a marked seam. The lowest band shows its words, never a number.

import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { game } from '../stores/appearance.js';
import { SingingTestBoard } from './singing-test-board.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { loadTests, readyToDecide, singingTestOn, singingTests, syncTests } from '../singing-test/store.js';

const BANDS = { finding: tk('singingTest.band.finding'), closer: tk('singingTest.band.closer'), nearly: tk('singingTest.band.nearly'), inTune: tk('singingTest.band.inTune'), precise: tk('singingTest.band.precise') };
const SHOWN = 6;

export const SingingTestCard = {
    components: { SingingTestBoard },
    setup: () => ({ navigate, formatNumber, game }),
    computed: {
        on: () => singingTestOn(),
        /** Finished tests (practice ones aren't history), newest first, with a seam where the test version changes. */
        rows() {
            const kept = singingTests.tests.filter((x) => x.status === 'counted' || x.status === 'warmup').slice(0, SHOWN);
            return kept.map((x, i) => ({
                id: x.id,
                date: formatDate(x.takenAt, { day: 'numeric', month: 'short' }),
                band: x.band ? t(BANDS[x.band]) : '',
                total: x.band && x.band !== 'finding' ? formatNumber(x.total) : null,
                warmUp: x.status === 'warmup',
                seam: i > 0 && kept[i - 1].testVersion !== x.testVersion,
            }));
        },
        latest: (vm) => vm.rows[0] ?? null,
        ready: (vm) => (vm.on ? account.me?.id ?? 'guest' : null),
    },
    watch: {
        // The history once the card is on and the account is known (either can come last: a page opened straight here).
        ready: {
            immediate: true,
            async handler(now) {
                if (now) await loadTests().then(syncTests);
            },
        },
    },
    created() {
        readyToDecide();
    },
    template: `
        <section v-if="on" class="card space-y-4 p-6" aria-labelledby="st-card-heading" data-st-card>
            <h2 id="st-card-heading" class="t-section">{{ t('singingTest.card.title') }}</h2>
            <p v-if="latest" class="text-cream" data-st-latest><span class="font-semibold">{{ latest.band }}</span><template v-if="latest.total !== null"> · {{ latest.total }}</template><span class="t-hint"> · {{ latest.date }}</span></p>
            <p v-else class="text-muted">{{ t('singingTest.card.none') }}</p>
            <ol v-if="rows.length > 1" class="space-y-1.5" :aria-label="t('singingTest.card.history')" data-st-history>
                <template v-for="row in rows" :key="row.id">
                    <li v-if="row.seam" class="t-hint border-t border-line pt-1.5" data-st-seam>{{ t('singingTest.card.seam') }}</li>
                    <li class="flex items-baseline justify-between gap-3 text-sm">
                        <span class="text-cream">{{ row.band }}<template v-if="row.total !== null"> · {{ row.total }}</template><span v-if="row.warmUp" class="chip-tag ms-2">{{ t('singingTest.card.warmUp') }}</span></span>
                        <span class="t-hint tabular-nums">{{ row.date }}</span>
                    </li>
                </template>
            </ol>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <a href="/voice/test" class="btn-primary btn-sm" data-st-take @click.prevent="navigate('/voice/test')">{{ latest ? t('singingTest.card.again') : t('singingTest.card.take') }}</a>
                <a href="/voice/test/how" class="link inline-flex min-h-11 items-center text-sm" @click.prevent="navigate('/voice/test/how')">{{ t('singingTest.how.link') }}</a>
            </div>
            <!-- Game mode's boards (the part boards' look); calm mode hides them, as it hides badges. -->
            <SingingTestBoard v-if="game()" />
        </section>`,
};
