// What's next (docs/more-features-plan.md, feature 13; js/whatsnext.js picks them): after a finished
// harmony, up to three songs, each with why, and from our own nightly counts how many people have
// finished this one and what they learned next ("people who finished this also learned..."). A tap
// opens the song on the home (`/?song=<id>`), where it can be previewed and started.

import { api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { learning, loadLearning } from '../stores/learning.js';
import { canStart, listTemplates, templatePartName } from '../templates.js';
import { account } from '../stores/account.js';
import { loadProjects, projects } from '../stores/projects.js';
import { whatsNext } from '../whatsnext.js';
import { Icon } from './icon.js';

const WHY = { fits: tk('next.why.fits'), harder: tk('next.why.harder'), month: tk('next.why.month'), alsoLearned: tk('next.why.alsoLearned') };

export const WhatsNext = {
    components: { Icon },
    props: { current: { type: Object, default: null } }, // the song just finished: { id, difficulty }
    data: () => ({ items: [], finishers: null }),
    setup: () => ({ WHY, navigate }),
    template: `
        <section v-if="items.length" class="w-full rounded-3xl border border-edge bg-panel p-4 text-start" aria-labelledby="next-title">
            <h3 id="next-title" class="font-bold text-cream">{{ t('next.title') }}</h3>
            <p v-if="finishers" class="text-sm text-muted">{{ t('next.finishers', { count: formatNumber(finishers) }) }}</p>
            <ul class="mt-3 space-y-2">
                <li v-for="item in items" :key="item.song.id">
                    <a :href="'/?song=' + item.song.id" class="flex items-center gap-3 rounded-2xl p-2 hover:bg-raised" @click.prevent="navigate('/?song=' + item.song.id)">
                        <span class="min-w-0 flex-1"><span class="block font-semibold text-cream" translate="no">{{ item.song.title }}</span><span class="block text-sm text-muted">{{ reason(item) }}</span></span>
                        <Icon name="arrow" :size="16" class="text-muted" />
                    </a>
                </li>
            </ul>
        </section>`,
    async created() {
        try {
            await loadLearning();
            const songs = await listTemplates();
            const counts = this.current ? await api.templates.finishers(this.current.id).then((r) => r.data, () => null) : null;
            this.finishers = counts?.finishers ?? null;
            await loadProjects();
            const finished = projects.list.filter((p) => p.template && p.everyPart).map((p) => p.template.id);
            const plus = account.me?.plan === 'plus';
            const picked = whatsNext({ songs, current: this.current, range: learning.progress.range, finished, startable: (s) => canStart(s, plus) });
            // "People who finished this also learned...": first, when the counts have it.
            const also = (counts?.alsoLearned ?? []).map((a) => songs.find((s) => s.id === a.id)).find((s) => s && s.id !== this.current?.id && !picked.some((p) => p.song.id === s.id));
            this.items = also ? [{ kind: 'alsoLearned', song: also }, ...picked].slice(0, 3) : picked;
        } catch (error) {
            console.warn("What's next not worked out", error);
        }
    },
    methods: {
        formatNumber,
        reason(item) {
            if (item.kind === 'fits' && item.part) return t('next.why.fitsPart', { part: templatePartName(item.part) });
            return t(WHY[item.kind]);
        },
    },
};
