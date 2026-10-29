// Share as an idea (docs/ideas-app-plan.md, slice 2; contract/api.md, ideas.create): on the page of a
// song you made and backed up, once Ideas is open. The card says what an idea is, or, once shared,
// opens it with its voices so far. The sheet takes a note (300 characters, no links), how many parts
// (the song's largest by default) and the rights confirmation in the contract's words; then the
// tune's own page. A refusal says what to do: 18 or over, a verified email, ten a day.

import { ApiError, api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { ideas, ideasOpen, myIdeas } from '../stores/ideas.js';
import { singerOptions } from '../templates.js';
import { Icon } from './icon.js';
import { Modal, toast } from './ui.js';
import { Notice } from './form.js';

const NOTE_MAX = 300;
const LINK = /https?:\/\/|www\.|\b[\w-]+\.(?:com|net|org|io|co|app|me)\b/i;
const REFUSED = {
    adult_required: tk('ideas.share.refused.adult'),
    email_unverified: tk('ideas.share.refused.email'),
    ideas_closed: tk('ideas.share.refused.closed'),
    rate_limited: tk('ideas.share.refused.limit'),
    not_found: tk('ideas.share.refused.notFound'),
    offline: tk('common.offline'),
};

const ShareIdeaSheet = {
    components: { Modal, Notice },
    props: { template: { type: Object, required: true }, templateId: { type: String, required: true } },
    emits: ['close'],
    data() {
        const options = singerOptions(this.template);
        return { note: '', voicing: Math.max(...options), options, rights: false, busy: false, error: null };
    },
    setup: () => ({ NOTE_MAX }),
    template: `
        <Modal :title="t('ideas.share.title')" :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-4" data-share-idea @submit.prevent="share">
                <p class="text-cream">{{ t('ideas.share.what') }}</p>
                <label class="block space-y-1.5">
                    <span class="label">{{ t('ideas.share.note') }}</span>
                    <textarea v-model="note" class="input min-h-20 w-full" :maxlength="NOTE_MAX" rows="3" :placeholder="t('ideas.share.notePlaceholder')" :aria-describedby="'idea-note-hint'"></textarea>
                    <span id="idea-note-hint" class="flex justify-between gap-3 text-xs" :class="hasLink ? 'text-bad' : 'text-muted'"><span>{{ hasLink ? t('ideas.share.noLinks') : t('ideas.share.noteHint') }}</span><span class="tabular-nums">{{ note.length }}/{{ NOTE_MAX }}</span></span>
                </label>
                <fieldset v-if="options.length > 1" class="space-y-2">
                    <legend class="label">{{ t('ideas.share.voicing') }}</legend>
                    <div class="seg flex sm:w-fit" role="radiogroup" :aria-label="t('ideas.share.voicing')">
                        <button v-for="n in options" :key="n" type="button" role="radio" :aria-checked="voicing === n ? 'true' : 'false'" class="seg-option flex-1 sm:flex-none" @click="voicing = n">{{ t('ideas.share.parts', { count: n }) }}</button>
                    </div>
                </fieldset>
                <label class="check-row rounded-2xl bg-raised p-3"><input v-model="rights" type="checkbox" class="checkbox" data-idea-rights /> <span>{{ t('ideas.share.rights') }}</span></label>
                <Notice v-if="error" tone="bad">{{ error }}</Notice>
                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || !rights || hasLink">{{ busy ? t('ideas.share.sharing') : t('ideas.share.go') }}</button>
                </div>
            </form>
        </Modal>`,
    computed: {
        hasLink() {
            return LINK.test(this.note);
        },
    },
    methods: {
        async share() {
            this.busy = true;
            this.error = null;
            try {
                const answer = await api.ideas.create({ body: { templateId: this.templateId, note: this.note.trim(), voicing: this.voicing, rightsConfirmed: true } });
                const idea = answer.data;
                toast(idea.status === 'held' ? t('ideas.share.held') : t('ideas.share.done'), { seconds: 8 });
                navigate(`/ideas/${idea.id}`);
            } catch (error) {
                console.warn('The idea was not shared', error);
                const code = error instanceof ApiError ? error.code : error?.code;
                const said = code === 'validation_failed' ? Object.values(error.errors ?? {}).flat()[0] : null; // the server's own words for a field
                this.error = said || t(REFUSED[code] ?? tk('ideas.share.refused.other'));
                this.busy = false;
            }
        },
    },
};

/** The card on your own song's page: Share as an idea, or the idea it already is. */
export const ShareIdea = {
    components: { Icon, ShareIdeaSheet },
    props: { template: { type: Object, required: true }, templateId: { type: String, required: true } },
    data: () => ({ shared: null, open: false }),
    setup: () => ({ ideas }),
    template: `
        <div v-if="ideas.open" class="card flex flex-wrap items-center gap-4 p-5" data-share-idea-card>
            <div class="min-w-52 flex-1">
                <p class="font-bold text-cream">{{ shared ? t('ideas.share.sharedTitle') : t('ideas.share.cardTitle') }}</p>
                <p class="text-sm text-muted">{{ shared ? t('ideas.share.sharedHint', { count: shared.voices }) : t('ideas.share.cardHint') }}</p>
            </div>
            <a v-if="shared" :href="'/ideas/' + shared.id" class="btn-secondary btn-sm" @click.prevent="navigate('/ideas/' + shared.id)">{{ t('ideas.share.see') }}</a>
            <button v-else type="button" class="btn-primary btn-sm" data-share-idea-open @click="open = true"><Icon name="mic" :size="16" /> {{ t('ideas.share.cardButton') }}</button>
            <ShareIdeaSheet v-if="open" :template="template" :template-id="templateId" @close="open = false" />
        </div>`,
    async created() {
        if (!(await ideasOpen())) return;
        try {
            const mine = await myIdeas();
            this.shared = mine.data.find((idea) => idea.template.id === this.templateId) ?? null;
        } catch (error) {
            console.info('Your ideas could not be read', error?.message ?? error);
        }
    },
    methods: { navigate },
};
