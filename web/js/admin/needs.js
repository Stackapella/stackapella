// What needs Hillel now (the coordinator's guide workflow ask; contract admin.beta.needs.show,
// admin.beta.guides.list's per-part `needs`, admin.beta.assignments.again), to get real voices live
// after a deploy. Two pieces:
// - NeedsCard, the top of Admin Home: each song, then its parts with chips (takes to review, Changed,
//   No singer). A song where no part has a singer yet says so once, not part by part.
// - PartNeeds, each part on Admin > Guides: the same chips, the singers asked and where they stand, and
//   Ask again for a singer who recorded an older version: one tap (the server's own note), or with a
//   note of Hillel's.
// The nav's Guides count is the parts that need a voice (changed, or no singer), from the same store;
// takes to review are counted on Review already.

import { reactive } from 'vue';
import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { LoadFailed, refused, when } from './common.js';

// Where a singer stands, as My parts says it.
const STATUS = {
    to_record: tk('admin.needs.status.toRecord'),
    submitted: tk('admin.needs.status.submitted'),
    in_review: tk('admin.needs.status.inReview'),
    retake: tk('admin.needs.status.retake'),
    guide: tk('admin.needs.status.accepted'),
    accepted: tk('admin.needs.status.accepted'),
    rejected: tk('admin.needs.status.rejected'),
};

/** The needs, shared by the card and the nav's count: `data` once loaded, `failed` when it wasn't. */
export const needs = reactive({ data: null, failed: false });

/** The parts that need a voice: changed since their recording, or with no singer. */
export function partsNeeding(data) {
    return (data?.templates ?? []).reduce((sum, song) => sum + song.parts.filter((p) => p.needs.changed || p.needs.unassigned).length, 0);
}

/** A song where not one part has a singer: said once ("No singers yet"), not part by part. */
export const noSingers = (song) => song.parts.length > 0 && song.parts.every((p) => p.needs.unassigned && !p.needs.singers.length && !p.needs.review.length);

/** Loads (or reloads) what needs Hillel; a failure keeps the last answer and says so. */
export async function loadNeeds() {
    try {
        needs.data = (await api.admin.beta.guides.needs()).data;
        needs.failed = false;
    } catch (error) {
        console.warn('What needs you did not load', error);
        needs.failed = true;
    }
    return needs.data;
}

/** A part's chips: takes to review (to the queue), Changed, No singer. */
export const NeedChips = {
    components: { StatusChip },
    props: { needs: { type: Object, required: true } },
    template: `
        <button v-if="needs.review.length" type="button" class="chip tap-44 text-accent-strong" data-needs-takes @click="navigate('/admin/review')">{{ t('admin.needs.takes', { count: needs.review.length }) }}</button>
        <StatusChip v-if="needs.changed" tone="warn" :label="t('admin.needs.changed')" data-needs-changed />
        <StatusChip v-if="needs.unassigned" tone="bad" :label="t('admin.needs.unassigned')" data-needs-unassigned />`,
    methods: { navigate },
};

export const NeedsCard = {
    components: { LoadFailed, NeedChips, StatusChip },
    setup: () => ({ needs, noSingers }),
    template: `
        <section class="card space-y-4 p-6" aria-labelledby="needs-h" data-admin-needs>
            <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                <h2 id="needs-h" class="text-lg font-bold text-cream">{{ t('admin.needs.title') }}</h2>
                <a href="/admin/guides" class="link tap-44 text-sm" @click.prevent="navigate('/admin/guides')">{{ t('admin.needs.toGuides') }}</a>
            </div>
            <LoadFailed v-if="needs.failed && !needs.data" :message="t('admin.needs.failed')" @retry="load" />
            <p v-else-if="!needs.data" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!songs.length" class="text-sm text-muted" data-needs-none>{{ t('admin.needs.none') }}</p>
            <ul v-else class="divide-y divide-line">
                <li v-for="song in songs" :key="song.id" class="flex flex-wrap items-start gap-x-4 gap-y-2 py-3 first:pt-0 last:pb-0" :data-needs-song="song.id">
                    <p class="w-full font-bold text-cream sm:w-48 sm:shrink-0"><span translate="no">{{ song.title }}</span> <span class="text-sm font-normal text-muted">· {{ t('admin.guides.version', { version: song.version }) }}</span></p>
                    <div v-if="noSingers(song)" class="flex flex-wrap gap-2"><StatusChip tone="bad" :label="t('admin.needs.noSingers', { count: song.parts.length })" data-needs-no-singers /></div>
                    <ul v-else class="flex min-w-0 flex-1 flex-col gap-2">
                        <li v-for="part in song.parts" :key="part.key" class="flex flex-wrap items-center gap-2" :data-needs-part="part.key">
                            <span class="w-20 shrink-0 text-sm font-semibold text-cream" translate="no">{{ part.name }}</span>
                            <NeedChips :needs="part.needs" />
                        </li>
                    </ul>
                </li>
            </ul>
        </section>`,
    // Loaded by the admin's shell on every screen (it counts them in the nav): no second ask here.
    computed: {
        songs: () => needs.data?.templates ?? [],
    },
    methods: {
        navigate,
        load: () => loadNeeds(),
    },
};

export const PartNeeds = {
    components: { NeedChips },
    props: { needs: { type: Object, required: true }, me: { type: Object, required: true } },
    emits: ['asked'],
    data: () => ({ busy: null, noting: null, note: '' }),
    template: `
        <div v-if="shown" class="mt-2 space-y-2" data-part-needs>
            <div v-if="needs.review.length || needs.changed || needs.unassigned" class="flex flex-wrap items-center gap-2">
                <NeedChips :needs="needs" />
                <button v-if="needs.unassigned && isAdmin" type="button" class="link tap-44 text-sm" @click="navigate('/admin/singers')">{{ t('admin.needs.findSinger') }}</button>
            </div>
            <ul v-if="needs.singers.length" class="space-y-1.5 text-sm">
                <li v-for="s in needs.singers" :key="s.assignment" class="space-y-2" :data-needs-singer="s.assignment">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="min-w-0 basis-full text-cream sm:basis-auto sm:flex-1"><span translate="no">{{ s.singer.name }}</span> <span class="text-muted">· {{ said(s) }}</span></span>
                        <template v-if="s.previous && !s.askedAgainAt && isAdmin && noting !== s.assignment">
                            <button type="button" class="btn-secondary btn-sm" :disabled="!!busy" data-ask-again @click="again(s)">{{ busy === s.assignment ? t('common.saving') : t('admin.needs.askAgain') }}</button>
                            <button type="button" class="btn-ghost btn-sm" :disabled="!!busy" data-ask-note @click="noting = s.assignment; note = ''">{{ t('admin.needs.withNote') }}</button>
                        </template>
                    </div>
                    <form v-if="noting === s.assignment" class="space-y-2" @submit.prevent="again(s, note.trim())">
                        <label class="block space-y-1.5"><span class="label">{{ t('admin.needs.noteLabel', { name: s.singer.name }) }}</span><textarea v-model="note" class="input min-h-16 w-full" maxlength="500" rows="2" :placeholder="t('admin.needs.notePlaceholder')" data-ask-note-text></textarea></label>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="btn-primary btn-sm" :disabled="!!busy">{{ busy === s.assignment ? t('common.saving') : t('admin.needs.send') }}</button>
                            <button type="button" class="btn-secondary btn-sm" @click="noting = null">{{ t('common.cancel') }}</button>
                        </div>
                    </form>
                </li>
            </ul>
        </div>`,
    computed: {
        isAdmin: (vm) => vm.me.role === 'admin',
        /** Something to say: a need, or the singers asked for it. */
        shown: (vm) => vm.needs.review.length || vm.needs.changed || vm.needs.unassigned || vm.needs.singers.length,
    },
    methods: {
        navigate,
        /** Where a singer stands: asked again (and when), recorded an older version, or as My parts says. */
        said(s) {
            if (s.askedAgainAt) return t('admin.needs.askedAt', { when: when(s.askedAgainAt) });
            if (s.previous && !s.open) return t('admin.needs.previous');
            return t(STATUS[s.status] ?? STATUS.to_record);
        },
        /** Asks a singer for the part again: the server's note, or Hillel's. */
        async again(s, note = '') {
            this.busy = s.assignment;
            try {
                await api.admin.beta.assignments.again(s.assignment, { note });
                toast(t('admin.needs.asked', { name: s.singer.name }));
                this.noting = null;
                this.$emit('asked');
                loadNeeds();
            } catch (error) {
                refused(error);
            } finally {
                this.busy = null;
            }
        },
    },
};
