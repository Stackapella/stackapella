// Share your result (js/result-card.js): a button by Play again opens a sheet with the card made on
// the device, as a post or a story. Share hands the image, a line and the link to the device's own
// share sheet; where files can't be shared (most desktops), the image downloads and the line can be
// copied. Nothing goes to us but an anonymous count (js/usage.js). The link opens the lesson itself
// when a newcomer could start it there, so a friend lands right on the challenge; else the app.

import { t } from '../i18n.js';
import { emptyProgress, unitOpen } from '../course.js';
import { learning } from '../stores/learning.js';
import { publicSiteUrl } from '../hosts.js';
import { drawNoteCard, drawResultCard, SIZES } from '../result-card.js';
import { count } from '../usage.js';
import { askShown, askYes, first } from '../funnel.js';
import { game } from '../skin.js';
import { effect } from '../game/effects.js';
import { local } from '../storage.js';
import { account } from '../stores/account.js';

const NAME_KEY = 'stackapella.shareName'; // a name typed for the card, on this device only
const REDRAW_MS = 400; // a timer for redrawing the picture as a name is typed, never for audio
import { Icon } from './icon.js';
import { Modal, toast } from './ui.js';

export const ShareResult = {
    components: { Icon, Modal },
    // result: the take's (stars, percent, word, line, dots, target, cents); entry: the lesson's { unit, unitIndex, lesson }.
    // note: lesson 1's note card instead of a take's ({ name: 'A3', line }), onboarding step 6.
    props: { label: { type: String, default: null }, note: { type: Object, default: null }, result: { type: Object, default: null }, record: Boolean, entry: { type: Object, required: true }, lessonTitle: { type: String, required: true }, unitTitle: { type: String, required: true }, color: { type: String, required: true } },
    data: () => ({ open: false, size: 'post', preview: null, blob: null, busy: false, failed: false, name: '' }),
    setup: () => ({ SIZES }),
    template: `
        <button type="button" class="btn-secondary self-start" @click="show"><Icon name="arrow" :size="18" /> {{ label ?? t('results.share.button') }}</button>
        <Modal v-if="open" :title="t('results.share.title')" @close="close">
            <div class="space-y-4" data-share-result>
                <div class="seg flex" role="radiogroup" :aria-label="t('results.share.size')">
                    <button v-for="s in ['post', 'story']" :key="s" type="button" role="radio" :aria-checked="size === s" class="seg-option flex-1" @click="choose(s)">{{ s === 'post' ? t('results.share.post') : t('results.share.story') }}</button>
                </div>
                <div class="mx-auto overflow-hidden rounded-2xl border border-line bg-panel" :style="{ aspectRatio: SIZES[size].width + ' / ' + SIZES[size].height, maxHeight: '50vh' }">
                    <img v-if="preview" :src="preview" :alt="t('results.share.alt', { word: words.word, stats: words.stats, lesson: words.lesson })" class="block h-full w-full object-contain" />
                    <p v-else class="grid h-full place-items-center p-6 text-sm text-muted" role="status">{{ failed ? t('results.share.failed') : t('results.share.making') }}</p>
                </div>
                <!-- Your name on it, if you like (onboarding step 6): only on this picture, kept on this device. -->
                <label class="block text-sm text-cream" data-share-name>{{ t('results.share.nameLabel') }}
                    <input v-model="name" class="input mt-1" maxlength="40" autocomplete="name" :placeholder="t('results.share.namePlaceholder')" />
                </label>
                <p class="text-sm text-muted">{{ t('results.share.private') }}</p>
                <div class="flex flex-wrap justify-end gap-3">
                    <template v-if="canShareFiles">
                        <button type="button" class="btn-primary" :disabled="!blob || busy" @click="share"><Icon name="arrow" :size="18" /> {{ t('results.share.share') }}</button>
                    </template>
                    <template v-else>
                        <button type="button" class="btn-secondary" @click="copyText">{{ t('results.share.copy') }}</button>
                        <button type="button" class="btn-primary" :disabled="!blob" @click="download"><Icon name="download" :size="18" /> {{ t('results.share.download') }}</button>
                    </template>
                </div>
            </div>
        </Modal>`,
    computed: {
        words() {
            const site = new URL(publicSiteUrl(), location.href).host;
            const by = this.name.trim() ? t('results.share.by', { name: this.name.trim() }) : null;
            if (this.note) return { title: t('results.share.noteTitle'), note: this.note.name, line: this.note.line, word: this.note.name, stats: this.note.line, lesson: '', by: this.name.trim() || null, tagline: t('results.share.tagline'), site };
            const r = this.result;
            const stats = r.lit !== undefined && r.target?.length > 1 ? t('results.share.statsLit', { percent: r.percent, lit: r.lit, count: r.target.length }) : t('results.share.stats', { percent: r.percent });
            // Serious mode (docs/skins-plan.md row 17): the measure, with no stars and no dare.
            if (!game()) return { word: t('results.onPitch', { percent: Math.round(r.percent) }), stats: r.lit !== undefined && r.target?.length > 1 ? t('results.share.litOf', { lit: r.lit, count: r.target.length }) : '', lesson: `${this.unitTitle} · ${this.lessonTitle}`, by, stars: false, challenge: null, tagline: t('results.share.tagline'), site };
            // Game mode's arcade card (Celebrations switch): the same words in the game's type, NEW RECORD when it is one.
            const arcade = effect('celebrations') ? { arcade: true, record: this.record ? t('game.record.title') : null } : {};
            return { word: t(r.word), stats, lesson: `${this.unitTitle} · ${this.lessonTitle}`, by, challenge: t('results.share.challenge'), tagline: t('results.share.tagline'), site, ...arcade };
        },
        /** The lesson itself when a newcomer could start it; else the app. */
        link() {
            const units = learning.course?.units ?? [];
            const fresh = units.length && unitOpen(units, this.entry.unitIndex, emptyProgress()) && !this.entry.unit.plus;
            return new URL(fresh ? `/learn/${this.entry.lesson.id}` : '/', location.origin).href;
        },
        text() {
            if (this.note) return t('results.share.noteText', { note: this.note.name });
            return game() ? t('results.share.text', { stars: this.result.stars, lesson: this.lessonTitle }) : t('results.share.textSerious', { percent: Math.round(this.result.percent), lesson: this.lessonTitle });
        },
        file() {
            return this.blob ? new File([this.blob], `stackapella-${this.entry.lesson.id}-${this.size}.png`, { type: 'image/png' }) : null;
        },
        canShareFiles() {
            return !!this.file && typeof navigator.canShare === 'function' && navigator.canShare({ files: [this.file] });
        },
    },
    watch: {
        name() {
            clearTimeout(this.redraw);
            this.redraw = setTimeout(() => this.open && this.make(), REDRAW_MS);
        },
    },
    unmounted() {
        clearTimeout(this.redraw);
        if (this.preview) URL.revokeObjectURL(this.preview);
    },
    methods: {
        show() {
            // An adult's account name is offered; a guest's own typed name comes back; never filled in for someone under 18.
            this.name = account.me ? (account.me.isAdult ? account.me.name ?? '' : '') : local.get(NAME_KEY) ?? '';
            this.open = true;
            askShown('name');
            this.make();
        },
        /** A card that went out: counted, and lesson 1's note card ("Share my note") on its own too. */
        counted() {
            count('result_shared');
            if (this.note) count('share_note');
        },
        /** A card that went out with a name: counted (the funnel), and a guest's name kept for next time. */
        named() {
            const name = this.name.trim();
            if (!name) return;
            count('card_named');
            if (!account.me) local.set(NAME_KEY, name);
            askYes('name');
            first('f_name');
        },
        close() {
            this.open = false;
        },
        choose(size) {
            this.size = size;
            this.make();
        },
        async make() {
            this.failed = false;
            this.blob = null;
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = null;
            try {
                const blob = this.note ? await drawNoteCard({ size: this.size, words: this.words, color: this.color }) : await drawResultCard({ size: this.size, words: this.words, result: this.result, color: this.color });
                this.blob = blob;
                this.preview = URL.createObjectURL(blob);
            } catch (error) {
                console.error('The result card was not made', error);
                this.failed = true;
            }
        },
        async share() {
            this.busy = true;
            try {
                await navigator.share({ files: [this.file], text: this.text, url: this.link });
                this.counted();
                first('f_share');
                this.named();
                this.close();
            } catch (error) {
                // Closing the share sheet is a choice, not a failure.
                if (error?.name !== 'AbortError') {
                    console.warn('Sharing the result failed', error);
                    toast(t('results.share.failed'), { tone: 'error' });
                }
            } finally {
                this.busy = false;
            }
        },
        download() {
            const url = URL.createObjectURL(this.blob);
            const link = Object.assign(document.createElement('a'), { href: url, download: this.file.name });
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 60000);
            this.counted();
            first('f_share');
            this.named();
        },
        async copyText() {
            try {
                await navigator.clipboard.writeText(`${this.text} ${this.link}`);
                toast(t('results.share.copied'));
            } catch (error) {
                console.warn('Copying the result failed', error);
                toast(t('results.share.copyFailed'), { tone: 'error' });
            }
        },
    },
};
