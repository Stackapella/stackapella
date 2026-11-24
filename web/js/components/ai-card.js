// AI optional's one way in (docs/ai-optional-plan.md, "The card"): under a take's review, after a singer's first recorded
// part of a song the choir can make, "Hear the other parts in your voice?" with two equal buttons, neither styled as the
// right one: Not for me (AI off, never asked again) and Try it (AI on, and Hear yourself as a choir's start for this
// song). The close button is no answer: it may show once more, on a later recorded part. Its rules live in
// ai-choice.js (cardAllowed); Sing decides and counts. Game mode restyles it as its result card (the shared .card).

import { t } from '../i18n.js';
import { Icon } from './icon.js';

export const AiCard = {
    components: { Icon },
    props: { song: { type: String, required: true }, busy: Boolean },
    emits: ['try', 'not', 'close'],
    template: `
        <section class="card relative space-y-3 p-4 pe-12" aria-labelledby="ai-card-title" data-ai-card>
            <button type="button" class="btn-ghost tap-44 absolute end-1 top-1 grid place-items-center" :aria-label="t('common.close')" data-ai-card-close @click="$emit('close')"><Icon name="close" :size="18" /></button>
            <h2 id="ai-card-title" class="font-bold text-cream">{{ t('ownChoir.aiCard.title') }}</h2>
            <p class="text-sm text-muted">{{ t('ownChoir.aiCard.body', { song }) }}</p>
            <div class="grid gap-2 sm:grid-cols-2">
                <button type="button" class="btn-secondary" :disabled="busy" data-ai-card-not @click="$emit('not')">{{ t('ownChoir.aiCard.not') }}</button>
                <button type="button" class="btn-secondary" :disabled="busy" data-ai-card-try @click="$emit('try')">{{ t('ownChoir.aiCard.try') }}</button>
            </div>
            <p class="t-hint">{{ t('ownChoir.aiCard.note') }}</p>
        </section>`,
};
