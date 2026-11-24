// The choir gallery's row on a ready choir song's page (docs/choir-gallery-plan.md, version 1; js/choir-gallery.js holds
// the rules): one quiet switch, "Add to the gallery", and under it the item's status once there is one. Turning it on
// opens the consent sheet (what goes public, the AI label, only your own voice, a person listens first, how to remove
// it); its button is the claim itself. Adding shares the song unlisted (its mix, AI-marked) and adds that share, with
// the consent's version and the hash of the words shown. Turning it off removes it at once, after a styled confirm.
// While AI features are off the row shows only an item already there (hidden, and still removable).

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { count } from '../usage.js';
import { confirmAction } from './confirm.js';
import { Switch } from './controls.js';
import { Modal, toast } from './ui.js';
import { refusalKey, rowFor, SHEET_KEYS, sheetWords, statusLine, switchedOn, wordsHash } from '../choir-gallery.js';

export const ChoirGalleryRow = {
    components: { Modal, Switch },
    // request: the ready song (ownChoir.show's, with `gallery` and `galleryCan`); song: its title; consentVersion: from
    // auth.config; ai: AI features on; share: async () => the id of an unlisted share of the song as heard now.
    props: { request: { type: Object, required: true }, song: { type: String, required: true }, consentVersion: { type: Number, default: null }, ai: Boolean, share: { type: Function, required: true } },
    emits: ['changed'],
    data: () => ({ sheet: false, adding: false, error: '' }),
    template: `
        <section v-if="row" class="card space-y-2 p-4" data-choir-gallery :data-gallery-status="item?.status ?? 'none'">
            <Switch row :on="on" :label="t('ownChoir.gallery.row')" :hint="t('ownChoir.gallery.rowHint')" :disabled="adding || (!ai && !on)" @change="toggle" />
            <p v-if="line" class="t-hint" role="status" data-gallery-line>{{ line }}</p>
            <button v-if="ai && item?.status === 'hidden' && item.hiddenBecause === 'changed'" type="button" class="btn-secondary btn-sm" data-gallery-resend @click="open">{{ t('ownChoir.gallery.status.resend') }}</button>
        </section>
        <Modal v-if="sheet" sheet :title="t('ownChoir.gallery.sheet.title', { song })" :dismissable="!adding" @close="cancel">
            <ul class="mt-2 space-y-3 text-cream" data-gallery-sheet>
                <li v-for="key in lines" :key="key">{{ t(key) }}</li>
            </ul>
            <p v-if="adding" class="mt-4 t-hint" role="status" data-gallery-adding>{{ t('ownChoir.gallery.sheet.adding', { song }) }}</p>
            <p v-if="error" class="mt-4 text-sm text-record" role="alert" data-gallery-error>{{ error }}</p>
            <div class="mt-6 flex flex-col gap-2.5 sm:flex-row-reverse">
                <button type="button" class="btn-primary justify-center" :disabled="adding" :aria-busy="adding ? 'true' : null" data-gallery-confirm @click="add">{{ t('ownChoir.gallery.sheet.confirm') }}</button>
                <button type="button" class="btn-secondary justify-center" :disabled="adding" data-gallery-cancel @click="cancel">{{ t('ownChoir.gallery.sheet.cancel') }}</button>
            </div>
        </Modal>`,
    setup: () => ({ t, lines: SHEET_KEYS.filter((key) => key !== 'ownChoir.gallery.sheet.confirm') }),
    computed: {
        item() {
            return this.request.gallery ?? null;
        },
        row() {
            return rowFor(this.request, { on: this.consentVersion != null, ai: this.ai });
        },
        on() {
            return switchedOn(this.item);
        },
        line() {
            return this.item && this.item.status !== 'removed' ? statusLine(this.item, this.song) : '';
        },
    },
    methods: {
        toggle(want) {
            if (want) this.open();
            else this.remove();
        },
        open() {
            this.error = '';
            this.sheet = true;
        },
        cancel() {
            if (this.adding) return;
            this.sheet = false;
            count('gallery_cancel');
        },
        async add() {
            this.adding = true;
            this.error = '';
            try {
                const harmonyId = await this.share();
                const { data } = await api.ownChoir.gallery.add(this.request.id, {
                    harmonyId,
                    consentVersion: this.consentVersion,
                    wordsHash: await wordsHash(sheetWords(this.song)),
                    locale: document.documentElement.lang || 'en',
                    ownVoice: true,
                });
                count('gallery_add');
                this.sheet = false;
                this.$emit('changed', data);
            } catch (error) {
                console.warn('The song was not added to the gallery', error);
                this.error = t(refusalKey(error) ?? tk('ownChoir.gallery.error.failed'));
            } finally {
                this.adding = false;
            }
        },
        async remove() {
            const yes = await confirmAction({ title: t('ownChoir.gallery.remove.title', { song: this.song }), body: t('ownChoir.gallery.remove.body'), action: t('ownChoir.gallery.remove.confirm') });
            if (!yes) return;
            try {
                await api.ownChoir.gallery.remove(this.request.id);
                this.$emit('changed', null);
            } catch (error) {
                console.warn('The song was not removed from the gallery', error);
                toast(t('common.didntWork'));
            }
        },
    },
};
