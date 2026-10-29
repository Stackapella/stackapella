// A lesson's "Did you know?" card on the lesson end (docs/courses-ideas.md, idea 1): one checked fact
// from the course file's lesson `fact` ({ id, text, source, url? }), the same in both skins, with its
// source said quietly underneath, a link when it has a page.

import { localized } from '../i18n.js';
import { Icon } from './icon.js';
import { count } from '../usage.js';

export const FactCard = {
    components: { Icon },
    props: { fact: { type: Object, required: true } },
    setup: () => ({ localized, count }),
    template: `
        <aside class="w-full rounded-2xl border border-line p-4 text-start" :aria-label="t('course.fact.heading')" :data-fact="fact.id">
            <p class="flex items-center gap-2 font-semibold text-cream"><Icon name="info" :size="16" /> {{ t('course.fact.heading') }}</p>
            <p class="mt-1 max-w-prose text-cream">{{ localized(fact.text) }}</p>
            <p class="mt-2 text-xs text-muted">
                <a v-if="fact.url" :href="fact.url" target="_blank" rel="noopener" @click="count('fact_source')" class="underline decoration-line underline-offset-2 hover:text-cream">{{ t('course.fact.source', { source: localized(fact.source) }) }}</a>
                <template v-else>{{ t('course.fact.source', { source: localized(fact.source) }) }}</template>
            </p>
        </aside>`,
};
