// A shared song or choir group that's paused (docs/pricing-plan.md, top: when its starter's Plus ends it turns
// read-only for everyone in it; members still listen and keep their takes, record nothing new, and nobody new joins;
// it opens again the moment Plus returns). One calm note, said once where it applies: the words come from the place
// (a choir's or a song's), the state in words and a mark, never colour alone. Members get nothing to buy; only the
// starter, whose Plus it is, gets the quiet way back to Plus (`action`).

import { navigate } from '../router.js';
import { Icon } from './icon.js';

export const PausedNote = {
    components: { Icon },
    props: {
        title: { type: String, required: true },
        text: { type: String, required: true },
        action: { type: String, default: '' }, // the starter's way back to Plus, in words; empty for everyone else
        from: { type: String, default: '' }, // the Plus page's ?from= for that way back
    },
    template: `
        <section class="flex items-start gap-3 rounded-2xl border border-line bg-raised p-4" role="status" data-paused-note>
            <span class="grid size-8 shrink-0 place-items-center rounded-full bg-panel text-muted" aria-hidden="true"><Icon name="pause" :size="16" /></span>
            <div class="min-w-0 space-y-1">
                <p class="font-semibold text-cream">{{ title }}</p>
                <p class="text-sm text-muted">{{ text }}</p>
                <a v-if="action" :href="'/plus?from=' + from" class="link tap-44 inline-flex min-h-6 items-center text-sm font-semibold" data-paused-plus @click.prevent="navigate('/plus?from=' + from)">{{ action }}</a>
            </div>
        </section>`,
    setup: () => ({ navigate }),
};
