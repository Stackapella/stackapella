// The review queue (docs/beta-singers-plan.md, "The review queue"): newest first, filtered by
// status, song and part. One take at a time: play it in the mix (accepted voices or the synth),
// A/B against the synth guide, the pitch chart with the scores and the singer's note, light fixes
// heard as they're set, then accept, ask for another take (pinned to a phrase) or reject. Keys: J
// and K for next and previous, Space to play, B for A/B, A to accept. Editors listen and ask for
// retakes; only an admin fixes and decides.

import { reactive } from 'vue';
import { api, ApiError } from '../api.js';
import { unlock as unlockAudio } from '../audio.js';
import { local } from '../storage.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { trackInWorker } from '../pitchtrack.js';
import { loadTemplate, phrases as phraseList, templatePartName } from '../templates.js';

/** A take's name: the song and part, or for a course clip (no song) its slot. */
function takeTitle(item) {
    return item.kind === 'clip' ? t('admin.review.clipTitle', { slot: item.clip?.id ?? '' }) : `${item.template?.title ?? ''}, ${templatePartName({ key: item.partKey })}`;
}
import { EmptyState, Field, Notice, StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { Modal, toast } from '../components/ui.js';
import { errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';
import { PitchChart } from '../components/pitch-chart.js';
import * as mix from './review-mix.js';
import { singerName } from './singers.js';

export const STATUS = {
    uploading: { tone: 'muted', label: tk('admin.review.status.uploading') },
    processing: { tone: 'muted', label: tk('admin.review.status.processing') },
    submitted: { tone: 'info', label: tk('admin.review.status.submitted') },
    in_review: { tone: 'info', label: tk('admin.review.status.in_review') },
    retake: { tone: 'warn', label: tk('admin.review.status.retake') },
    rejected: { tone: 'muted', label: tk('admin.review.status.rejected') },
    accepted: { tone: 'good', label: tk('admin.review.status.accepted') },
    failed: { tone: 'bad', label: tk('admin.review.status.failed') },
};
const PUBLISH = {
    pending: { tone: 'info', label: tk('admin.review.publish.pending') },
    published: { tone: 'good', label: tk('admin.review.publish.published') },
    failed: { tone: 'bad', label: tk('admin.review.publish.failed') },
};
const FAILURE = {
    not_audio: tk('admin.review.failure.not_audio'),
    wrong_format: tk('admin.review.failure.wrong_format'),
    wrong_length: tk('admin.review.failure.wrong_length'),
    processing_error: tk('admin.review.failure.processing_error'),
};
const TENDENCY = { flat: tk('admin.review.tendency.flat'), sharp: tk('admin.review.tendency.sharp'), mixed: tk('admin.review.tendency.mixed') };
const FILTERS = [
    { value: 'waiting', label: tk('admin.review.filter.waiting') },
    { value: '', label: tk('admin.review.filter.all') },
    { value: 'retake', label: tk('admin.review.status.retake') },
    { value: 'accepted', label: tk('admin.review.status.accepted') },
    { value: 'rejected', label: tk('admin.review.status.rejected') },
    { value: 'failed', label: tk('admin.review.status.failed') },
];
const PARTS = ['soprano', 'alto', 'tenor', 'bass'];
const ADJUST = [
    { field: 'offsetMs', label: tk('admin.review.adjust.offset'), help: tk('admin.review.adjust.offsetHelp'), min: -500, max: 500, step: 1, unit: tk('common.ms') },
    { field: 'trimStartMs', label: tk('admin.review.adjust.trimStart'), help: tk('admin.review.adjust.trimStartHelp'), min: 0, max: 60000, step: 10, unit: tk('common.ms') },
    { field: 'trimEndMs', label: tk('admin.review.adjust.trimEnd'), help: null, min: 0, max: 60000, step: 10, unit: tk('common.ms') },
    { field: 'gainDb', label: tk('admin.review.adjust.gain'), help: tk('admin.review.adjust.gainHelp'), min: -12, max: 12, step: 0.5, unit: tk('admin.review.adjust.db') },
];
/** Where a take can still be decided (accept, retake, reject), as the contract says. */
const DECIDABLE = ['submitted', 'in_review'];
const PUBLISH_CHECKS = 20; // every 3 seconds while a guide is being published: a minute

/** The single-letter keys, which can be turned off (they'd get in the way of a screen reader's own). */
const shortcuts = reactive({ on: local.get('stackapella.admin.shortcuts') !== 'off' });

function setShortcuts(on) {
    shortcuts.on = on;
    local.set('stackapella.admin.shortcuts', on ? '' : 'off');
}

/** The queue as last listed, for J and K on a take. */
const queue = { ids: [], query: '' };

/** The queue: the takes waiting, or any status, by song and part. */
const QueueList = {
    components: { EmptyState, LoadFailed, StatusChip, Icon },
    props: { templates: { type: Array, required: true } },
    data() {
        const q = new URLSearchParams(location.search);
        return { items: null, failed: false, cursor: null, loadingMore: false, status: q.get('status') ?? 'waiting', template: q.get('template') ?? '', part: q.get('part') ?? '', singer: q.get('singer') ?? '' };
    },
    setup: () => ({ FILTERS, PARTS, STATUS, when, templatePartName, takeTitle, singerName, shortcuts, setShortcuts }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.review') }}</h1>
                <div class="flex flex-wrap gap-2">
                    <label class="sr-only" for="review-status">{{ t('admin.review.filter.status') }}</label>
                    <select id="review-status" v-model="status" class="input !w-auto !py-2" @change="load()"><option v-for="f in FILTERS" :key="f.value" :value="f.value">{{ t(f.label) }}</option></select>
                    <label class="sr-only" for="review-song">{{ t('admin.singers.song') }}</label>
                    <select id="review-song" v-model="template" class="input !w-auto !py-2" @change="load()">
                        <option value="">{{ t('admin.review.filter.allSongs') }}</option>
                        <option v-for="tpl in templates" :key="tpl.id" :value="tpl.id" translate="no">{{ tpl.title }}</option>
                    </select>
                    <label class="sr-only" for="review-part">{{ t('admin.singers.part') }}</label>
                    <select id="review-part" v-model="part" class="input !w-auto !py-2" @change="load()">
                        <option value="">{{ t('admin.review.filter.allParts') }}</option>
                        <option v-for="key in PARTS" :key="key" :value="key">{{ templatePartName({ key }) }}</option>
                    </select>
                </div>
            </div>
            <p v-if="singer" class="flex flex-wrap items-center gap-3 text-sm text-cream">
                <span>{{ items?.length ? t('admin.review.oneSinger', { name: singerName(items[0].singer) }) : t('admin.review.oneSingerUnnamed') }}</span>
                <button type="button" class="btn-ghost btn-sm" @click="singer = ''; load()">{{ t('admin.review.everyone') }}</button>
            </p>
            <LoadFailed v-if="failed" :message="failed" @retry="load()" />
            <p v-else-if="!items" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <EmptyState v-else-if="!items.length" icon="mic" :title="status === 'waiting' ? t('admin.review.noneWaiting') : t('admin.review.none')" />
            <ul v-else class="card divide-y divide-line">
                <li v-for="item in items" :key="item.id">
                    <a :href="'/admin/review/' + item.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 hover:bg-raised" @click.prevent="open(item)">
                        <span class="min-w-0 flex-1">
                            <span class="block font-bold text-cream" translate="no">{{ takeTitle(item) }}</span>
                            <span v-if="item.kind === 'clip'" class="block text-sm text-cream" data-clip-brief>{{ item.clip.sing }}</span>
                            <span class="text-sm text-muted"><span translate="no">{{ singerName(item.singer) }}</span> · {{ when(item.createdAt) }}</span>
                        </span>
                        <span v-if="item.scores?.pitchPercent !== null && item.scores?.pitchPercent !== undefined" class="text-sm text-cream">{{ t('admin.review.onPitch', { percent: item.scores.pitchPercent }) }}<template v-if="item.scores.timingPercent !== null && item.scores.timingPercent !== undefined"> · {{ t('admin.review.timingPercent', { percent: item.scores.timingPercent }) }}</template></span>
                        <StatusChip v-if="item.template && item.template.version !== item.template.currentVersion" tone="warn" :label="t('admin.review.olderVersion', { version: item.template.version })" />
                        <StatusChip :tone="STATUS[item.status].tone" :label="t(STATUS[item.status].label)" />
                    </a>
                </li>
            </ul>
            <button v-if="cursor && !failed" type="button" class="btn-secondary" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
            <label class="check-row text-muted"><input type="checkbox" class="checkbox" :checked="shortcuts.on" @change="setShortcuts($event.target.checked)" /> <span>{{ t('admin.review.keys') }}</span></label>
        </div>`,
    created() {
        this.load();
    },
    methods: {
        query() {
            const q = new URLSearchParams();
            if (this.status !== 'waiting') q.set('status', this.status);
            if (this.template) q.set('template', this.template);
            if (this.part) q.set('part', this.part);
            if (this.singer) q.set('singer', this.singer);
            return q.toString();
        },
        async load(more = false) {
            if (!more) history.replaceState({}, '', `/admin/review${this.query() ? `?${this.query()}` : ''}`);
            await loadInto(this, 'items', () => api.admin.beta.submissions.list({ status: this.status || undefined, template: this.template || undefined, part: this.part || undefined, singer: this.singer || undefined, cursor: more ? this.cursor : undefined }), { more });
            queue.ids = (this.items ?? []).map((item) => item.id);
            queue.query = this.query();
        },
        open(item) {
            navigate(`/admin/review/${item.id}${queue.query ? `?${queue.query}` : ''}`);
        },
    },
};

/** Asking for another take: a note the singer sees, pinned to a phrase they can loop. */
const RetakeSheet = {
    components: { Modal, Field, Notice },
    props: { phrases: { type: Array, required: true }, picked: Number, busy: Boolean, errors: Object },
    emits: ['close', 'send'],
    data() {
        return { note: '', phrase: this.picked ?? '' };
    },
    template: `
        <Modal :title="t('admin.review.retakeTitle')" :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-5" @submit.prevent="$emit('send', { note: note.trim(), beat: phrase === '' ? null : phrases[phrase][0] })">
                <Field :label="t('admin.review.retakeNote')" :help="t('admin.review.retakeNoteHelp')" :error="errors?.note" required v-slot="f">
                    <textarea :id="f.id" v-model="note" rows="3" maxlength="1000" required class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                </Field>
                <Field :label="t('admin.review.retakePhrase')" :help="t('admin.review.retakePhraseHelp')" :error="errors?.beat" v-slot="f">
                    <select :id="f.id" v-model="phrase" class="input" :aria-describedby="f.describedby">
                        <option value="">{{ t('admin.review.noPhrase') }}</option>
                        <option v-for="(p, i) in phrases" :key="i" :value="i">{{ t('admin.review.phrase', { number: i + 1 }) }}</option>
                    </select>
                </Field>
                <Notice v-if="errors && !Object.keys(errors).length" tone="bad">{{ t('admin.requestFailed') }}</Notice>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" class="btn-primary" :disabled="busy || !note.trim()">{{ busy ? t('common.saving') : t('admin.review.askRetake') }}</button>
                </div>
            </form>
        </Modal>`,
};

/** Accepting (as the default voice, or kept as an alternate), or rejecting with a kind note. */
const DecideSheet = {
    components: { Modal, Field },
    props: { kind: { type: String, required: true }, busy: Boolean },
    emits: ['close', 'confirm'],
    data: () => ({ makeDefault: true, note: '' }),
    template: `
        <Modal :title="kind === 'accept' ? t('admin.review.acceptTitle') : t('admin.review.rejectTitle')" :dismissable="!busy" @close="$emit('close')">
            <form class="space-y-5" @submit.prevent="$emit('confirm', kind === 'accept' ? { makeDefault } : { note: note.trim() || null })">
                <template v-if="kind === 'accept'">
                    <p class="text-cream">{{ t('admin.review.acceptBody') }}</p>
                    <label class="check-row"><input v-model="makeDefault" type="checkbox" class="checkbox" /> <span>{{ t('admin.review.makeDefault') }}<span class="block text-sm text-muted">{{ t('admin.review.makeDefaultHelp') }}</span></span></label>
                </template>
                <Field v-else :label="t('admin.review.rejectNote')" :help="t('admin.review.rejectNoteHelp')" v-slot="f">
                    <textarea :id="f.id" v-model="note" rows="3" maxlength="1000" class="input" :aria-describedby="f.describedby"></textarea>
                </Field>
                <div class="flex justify-end gap-3">
                    <button type="button" class="btn-secondary" :disabled="busy" @click="$emit('close')">{{ t('common.cancel') }}</button>
                    <button type="submit" :class="kind === 'accept' ? 'btn-primary' : 'btn-danger'" :disabled="busy">{{ busy ? t('common.saving') : kind === 'accept' ? t('admin.review.accept') : t('admin.review.reject') }}</button>
                </div>
            </form>
        </Modal>`,
};

/** One take. */
const ReviewTake = {
    components: { PitchChart, RetakeSheet, DecideSheet, LoadFailed, Notice, StatusChip, Field, Icon },
    props: { id: { type: String, required: true }, me: { type: Object, required: true } },
    data: () => ({
        item: null,
        failed: false,
        template: null,
        master: null,
        track: null,
        voices: null,
        audioFailed: false,
        adjust: null, // as being tried; saved with Save fixes (or before a decision)
        hear: 'take', // or 'synth', the A/B
        others: 'real', // 'real', 'synth' or 'off'
        playing: false,
        position: null,
        picked: null,
        sheet: null,
        busy: false,
        errors: null,
    }),
    setup: () => ({ STATUS, PUBLISH, FAILURE, TENDENCY, ADJUST, templatePartName, takeTitle, singerName, formatDate, formatNumber, when }),
    template: `
        <div class="space-y-6">
            <a :href="backHref" class="btn-ghost btn-sm" @click.prevent="back"><Icon name="back" :size="16" /> {{ t('admin.review.backToQueue') }}</a>
            <LoadFailed v-if="failed" :message="failed" @retry="load" />
            <p v-else-if="!item" class="text-muted" role="status">{{ t('admin.loading') }}</p>
            <template v-else>
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 ref="heading" tabindex="-1" class="text-2xl font-extrabold tracking-tight text-cream outline-none" translate="no">{{ takeTitle(item) }}</h1>
                        <p class="text-sm text-muted">{{ t('admin.review.sungBy', { name: singerName(item.singer) }) }}<template v-if="item.singer.credit"> · {{ t('admin.review.creditAs', { credit: item.singer.credit }) }}</template> · {{ when(item.createdAt) }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <StatusChip :tone="STATUS[item.status].tone" :label="t(STATUS[item.status].label)" />
                        <StatusChip v-if="PUBLISH[item.publish]" :tone="PUBLISH[item.publish].tone" :label="t(PUBLISH[item.publish].label)" />
                    </div>
                </header>
                <!-- A course clip: what they were asked to sing, in place of the song (docs/courses/clips-to-record.md). -->
                <Notice v-if="item.kind === 'clip'" tone="info" data-clip-asked><span class="font-semibold">{{ t('admin.review.clipAsked') }}</span> {{ item.clip.sing }} <span class="text-muted">{{ t('admin.review.clipSeconds', { seconds: formatNumber(item.clip.seconds) }) }}</span></Notice>
                <Notice v-if="item.template && item.template.version !== item.template.currentVersion" tone="warn">{{ t('admin.review.drift', { version: item.template.version, current: item.template.currentVersion }) }}</Notice>
                <Notice v-if="item.failure" tone="bad">{{ t(FAILURE[item.failure]) }}</Notice>
                <Notice v-if="item.singerNote" tone="info"><span class="font-semibold">{{ t('admin.review.singerSays') }}</span> <span translate="no">{{ item.singerNote }}</span></Notice>
                <Notice v-if="item.adminNote" tone="info"><span class="font-semibold">{{ t('admin.review.youSaid') }}</span> <span translate="no">{{ item.adminNote }}</span></Notice>

                <section class="card space-y-4 p-5" :aria-label="t('admin.review.listen')">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn-primary" :disabled="!ready" @click="toggle"><Icon :name="playing ? 'stop' : 'play'" :size="18" /> {{ playing ? t('studio.stop') : t('admin.review.play') }}</button>
                        <div v-if="notesMatch" class="seg" role="group" :aria-label="t('admin.review.thisPart')">
                            <button type="button" class="seg-option" :aria-pressed="hear === 'take'" @click="setHear('take')">{{ t('admin.review.hearTake') }}</button>
                            <button type="button" class="seg-option" :aria-pressed="hear === 'synth'" @click="setHear('synth')">{{ t('admin.review.hearSynth') }}</button>
                        </div>
                        <div v-if="item.kind !== 'clip'" class="w-full min-w-0 sm:w-auto">
                            <label class="sr-only" for="review-others">{{ t('admin.review.others') }}</label>
                            <select id="review-others" v-model="others" class="input !py-2 sm:!w-auto" @change="rearrange">
                                <option value="real">{{ t('admin.review.othersReal') }}</option>
                                <option value="synth">{{ t('admin.review.othersSynth') }}</option>
                                <option value="off">{{ t('admin.review.othersOff') }}</option>
                            </select>
                        </div>
                        <span class="text-sm tabular-nums text-muted" aria-live="off">{{ clock }}</span>
                    </div>
                    <p v-if="!ready && !audioFailed" class="text-sm text-muted" role="status">{{ t('admin.review.loadingAudio') }}</p>
                    <Notice v-if="audioFailed" tone="bad">{{ t('admin.review.audioFailed') }} <button type="button" class="link" @click="load">{{ t('common.tryAgain') }}</button></Notice>
                    <p v-if="voices && voices.real.size" class="text-sm text-muted">{{ t('admin.review.realVoices', { parts: realParts }) }}</p>
                    <Notice v-if="voices?.failed.length" tone="warn">{{ t('admin.review.voiceFailed') }}</Notice>
 <Notice v-if="template && !notesMatch" tone="info">{{ t('admin.review.notesChanged') }}</Notice>
                    <PitchChart v-if="part && notesMatch" :notes="part.guide.notes" :track="track" :beat-seconds="beatSeconds" :offset-seconds="(adjust?.offsetMs ?? 0) / 1000" :phrases="phrases" :picked="picked" :position="playing ? position : null" :label="t('admin.review.chartLabel')" @pick="pickPhrase" />
                    <p v-if="template && !part" class="text-sm text-muted">{{ t('admin.review.noNotes') }}</p>
                    <p v-if="part && notesMatch" class="help">{{ t('admin.review.chartHelp') }}</p>
                </section>

                <section class="grid gap-4 lg:grid-cols-2">
                    <div class="card p-5">
                        <h2 class="font-bold text-cream">{{ t('admin.review.checks') }}</h2>
                        <ul class="mt-3 space-y-1.5 text-sm text-cream">
                            <li v-if="item.scores?.pitchPercent !== null && item.scores?.pitchPercent !== undefined">{{ t('admin.review.onPitch', { percent: item.scores.pitchPercent }) }}</li>
                            <li v-if="item.scores?.timingPercent !== null && item.scores?.timingPercent !== undefined">{{ t('admin.review.timingPercent', { percent: item.scores.timingPercent }) }}</li>
                            <li v-if="item.scores?.tries">{{ t('admin.review.tries', { count: item.scores.tries }) }}</li>
                            <li v-if="item.scores?.timingMs !== null && item.scores?.timingMs !== undefined">{{ item.scores.timingMs >= 0 ? t('admin.review.late', { ms: item.scores.timingMs }) : t('admin.review.early', { ms: -item.scores.timingMs }) }}</li>
                            <li v-if="item.scores?.clipping" class="text-warn">{{ t('admin.review.clipping') }}</li>
                            <li v-if="item.scores && item.scores.noise !== 'low'" class="text-warn">{{ item.scores.noise === 'high' ? t('admin.review.noiseHigh') : t('admin.review.noiseSome') }}</li>
                            <li v-if="item.scores?.bleed" class="text-warn">{{ t('admin.review.bleed') }}</li>
                            <li v-for="p in item.scores?.phrases ?? []" :key="p.index"><button type="button" class="btn-ghost btn-sm" @click="pickPhrase(p.index)">{{ t(TENDENCY[p.tendency] ?? TENDENCY.mixed, { number: p.index + 1, percent: p.pitchPercent }) }}</button></li>
                        </ul>
                        <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-sm">
                            <dt class="text-muted">{{ t('admin.review.device') }}</dt><dd class="text-cream" translate="no">{{ item.device }}</dd>
                            <dt class="text-muted">{{ t('admin.review.mic') }}</dt><dd class="text-cream" translate="no">{{ item.mic || t('admin.review.unknownMic') }}</dd>
                            <dt class="text-muted">{{ t('admin.review.length') }}</dt><dd class="text-cream">{{ t('admin.review.lengthAndRate', { seconds: formatNumber(item.durationSeconds, { maximumFractionDigits: 1 }), rate: formatNumber(item.sampleRate) }) }}</dd>
                            <dt class="text-muted">{{ t('admin.review.loudness') }}</dt><dd class="text-cream">{{ t('admin.review.dbfs', { db: formatNumber(item.loudnessDbfs, { maximumFractionDigits: 1 }) }) }}</dd>
                            <dt class="text-muted">{{ t('admin.review.timing') }}</dt><dd class="text-cream">{{ t('admin.review.latency', { ms: formatNumber((item.latencyFrames / item.sampleRate) * 1000, { maximumFractionDigits: 1 }) }) }}</dd>
                        </dl>
                    </div>
                    <form class="card space-y-4 p-5" @submit.prevent="saveAdjust">
                        <h2 class="font-bold text-cream">{{ t('admin.review.fix') }}</h2>
                        <p class="text-sm text-muted">{{ t('admin.review.fixHelp') }}</p>
                        <Field v-for="a in ADJUST" :key="a.field" :label="t('admin.review.adjust.labelValue', { label: t(a.label), value: t(a.unit, { value: formatNumber(adjust[a.field]), ms: formatNumber(adjust[a.field]) }) })" :help="a.help ? t(a.help) : null" :error="errors?.[a.field]" required v-slot="f">
                            <input v-fill :id="f.id" v-model.number="adjust[a.field]" type="range" :min="a.min" :max="maxOf(a)" :step="a.step" class="range w-full" :disabled="!canFix" :aria-describedby="f.describedby" @change="a.field === 'gainDb' || rearrange()" @input="a.field === 'gainDb' && liveGain()" />
                        </Field>
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="btn-ghost btn-sm" :disabled="!canFix || !dirty" @click="resetAdjust">{{ t('admin.review.undoFixes') }}</button>
                            <button type="submit" class="btn-secondary btn-sm" :disabled="!canFix || !dirty || busy">{{ t('admin.review.saveFixes') }}</button>
                        </div>
                    </form>
                </section>

                <section class="card flex flex-wrap items-center gap-3 p-5" :aria-label="t('admin.review.decide')">
                    <template v-if="decidable">
                        <button v-if="isAdmin" type="button" class="btn-primary" :disabled="busy" @click="ask('accept')"><Icon name="check" :size="16" /> {{ t('admin.review.accept') }}</button>
                        <button type="button" class="btn-secondary" :disabled="busy" @click="ask('retake')">{{ t('admin.review.askRetake') }}</button>
                        <button v-if="isAdmin" type="button" class="btn-ghost" :disabled="busy" @click="ask('reject')">{{ t('admin.review.reject') }}</button>
                    </template>
                    <template v-else-if="item.status === 'accepted'">
                        <p class="flex-1 text-sm text-cream">{{ item.publish === 'published' ? t('admin.review.isGuide') : item.publish === 'failed' ? t('admin.review.publishFailed') : t('admin.review.publishing') }}</p>
                        <button v-if="isAdmin && (item.publish === 'failed' || item.publish === 'published')" type="button" class="btn-secondary" :disabled="busy" @click="publish">{{ t('admin.review.publishAgain') }}</button>
                    </template>
                    <p v-else-if="item.reviewedAt" class="text-sm text-muted">{{ t('admin.review.decided', { when: when(item.reviewedAt) }) }}</p>
                    <span class="ms-auto flex gap-2">
                        <button type="button" class="btn-ghost btn-sm" :disabled="!previousId" @click="go(previousId)">{{ t('admin.review.previous') }}</button>
                        <button type="button" class="btn-ghost btn-sm" :disabled="!nextId" @click="go(nextId)">{{ t('admin.review.next') }}</button>
                    </span>
                </section>
            </template>
            <RetakeSheet v-if="sheet === 'retake'" :phrases="phrases" :picked="picked" :busy="busy" :errors="errors" @close="sheet = null" @send="retake" />
            <DecideSheet v-if="sheet === 'accept' || sheet === 'reject'" :kind="sheet" :busy="busy" @close="sheet = null" @confirm="decide" />
        </div>`,
    computed: {
        isAdmin: (vm) => vm.me.role === 'admin',
        decidable: (vm) => DECIDABLE.includes(vm.item?.status),
        canFix: (vm) => vm.isAdmin && (vm.decidable || vm.item?.status === 'accepted'),
        dirty: (vm) => !!vm.adjust && ADJUST.some((a) => vm.adjust[a.field] !== vm.item.adjust[a.field]),
        ready: (vm) => !!vm.master && !!vm.voices,
        part: (vm) => vm.template?.parts.find((p) => p.key === vm.item.partKey) ?? null,
        /** The app's notes are the ones the take was sung to (not a newer version's). */
        notesMatch: (vm) => !!vm.template && vm.template.version === vm.item?.template?.version,
        peak: (vm) => (vm.master ? mix.peakDbfs(vm.master.samples) : null),
        beatSeconds: (vm) => 60 / (vm.template?.tempo ?? 60),
        phrases: (vm) => (vm.notesMatch ? phraseList(vm.template).map((p) => [p.startBeat, p.endBeat]) : []),
        realParts: (vm) => [...vm.voices.real.keys()].map((key) => templatePartName({ key })).join(', '),
        length: (vm) => Math.max(
            vm.master ? vm.master.samples.length / vm.master.sampleRate + Math.max(0, (vm.adjust?.offsetMs ?? 0) / 1000) : 0,
            ...[...(vm.voices?.synth.values() ?? []), ...(vm.voices?.real.values() ?? [])].map((v) => v.offsetSeconds + v.samples.length / v.sampleRate),
        ),
        clock() {
            const seconds = this.playing ? this.position ?? 0 : 0;
            const format = (s) => `${Math.floor(s / 60)}:${String(Math.floor(s % 60)).padStart(2, '0')}`;
            return `${format(seconds)} / ${format(this.length)}`;
        },
        backHref: () => `/admin/review${queue.query ? `?${queue.query}` : ''}`,
        previousId: (vm) => queue.ids[queue.ids.indexOf(vm.id) - 1] ?? null,
        nextId: (vm) => (queue.ids.indexOf(vm.id) >= 0 ? queue.ids[queue.ids.indexOf(vm.id) + 1] ?? null : null),
    },
    watch: {
        id() {
            this.load();
        },
    },
    created() {
        this.load();
    },
    mounted() {
        this.onKey = (event) => this.key(event);
        document.addEventListener('keydown', this.onKey);
    },
    unmounted() {
        document.removeEventListener('keydown', this.onKey);
        cancelAnimationFrame(this.frame);
        clearTimeout(this.publishTimer);
        mix.clear();
    },
    methods: {
        async load() {
            const id = this.id;
            this.stopPlaying();
            clearTimeout(this.publishTimer);
            Object.assign(this, { item: null, failed: false, template: null, master: null, track: null, voices: null, audioFailed: false, picked: null, sheet: null, errors: null });
            let detail;
            try {
                detail = await api.admin.beta.submissions.show(id);
            } catch (error) {
                if (id !== this.id || refusedHere(error, this)) return;
                return;
            }
            if (id !== this.id) return;
            this.item = detail.data;
            this.adjust = { ...detail.data.adjust };
            if (detail.data.status === 'submitted') this.markOpened(id);
            if (detail.data.status === 'accepted' && detail.data.publish === 'pending') this.watchPublish();
            let template = null;
            // A course clip has no song: no chart, no mix of the other parts.
            if (detail.data.template) {
                try {
                    template = await loadTemplate(detail.data.template.id);
                } catch (error) {
                    console.error('The template failed to load', error);
                }
            }
            if (id !== this.id) return;
            this.template = template;
            this.$nextTick(() => this.$refs.heading?.focus()); // J and K: the new take is announced
            this.loadAudio(id, detail);
        },
        async loadAudio(id, detail) {
            if (!detail.master) {
                this.audioFailed = !['uploading', 'processing'].includes(detail.data.status);
                return;
            }
            try {
                const [master, voices] = await Promise.all([
                    mix.loadMaster(detail.data, detail.master.url),
                    this.template ? mix.loadVoices({ template: this.template, otherParts: detail.otherParts ?? [], version: detail.data.template.version }) : { real: new Map(), synth: new Map(), failed: [] },
                ]);
                if (id !== this.id) return;
                this.master = master;
                this.voices = voices;
                const track = await trackInWorker(master.samples, master.sampleRate);
                if (id === this.id) this.track = track;
            } catch (error) {
                console.error('The take failed to load', error);
                if (id === this.id) this.audioFailed = true;
            }
        },
        async markOpened(id) {
            try {
                const { data } = await api.admin.beta.submissions.open(id);
                if (id === this.id && this.item) this.item.status = data.status;
            } catch (error) {
                console.warn('Marking the take as opened failed', error);
            }
        },
        /** While a guide is published (moments), checks every few seconds, for a minute. */
        watchPublish(checks = 0) {
            clearTimeout(this.publishTimer);
            if (checks >= PUBLISH_CHECKS) return;
            this.publishTimer = setTimeout(async () => {
                try {
                    const { data } = await api.admin.beta.submissions.show(this.id);
                    if (data.id !== this.item?.id) return;
                    this.item = { ...this.item, publish: data.publish, guide: data.guide, status: data.status };
                    if (data.publish === 'pending') this.watchPublish(checks + 1);
                } catch (error) {
                    console.warn('Checking the publish failed', error);
                    this.watchPublish(checks + 1);
                }
            }, 3000);
        },

        // ---- Listening ----

        arrange() {
            mix.arrange({ take: this.master.samples, sampleRate: this.master.sampleRate, adjust: this.adjust, loudnessDbfs: this.item.loudnessDbfs, peak: this.peak, partKey: this.item.partKey, voices: this.voices, others: this.others, hear: this.hear });
        },
        async toggle() {
            if (this.playing) {
                this.stopPlaying();
                return;
            }
            await this.play(this.picked !== null ? this.phrases[this.picked][0] * this.beatSeconds : 0);
        },
        async play(fromSeconds) {
            if (!this.ready) return;
            cancelAnimationFrame(this.frame);
            const ticket = (this.playTicket = (this.playTicket ?? 0) + 1); // a Stop, or another take, meanwhile wins
            try {
                await unlockAudio(); // from the tap, before anything is built
                await mix.stop();
                if (ticket !== this.playTicket || !this.ready) return;
                this.arrange();
                await mix.playFrom(fromSeconds);
            } catch (error) {
                if (ticket !== this.playTicket) return;
                console.error('Playing failed', error);
                toast(t('admin.review.playFailed'), { tone: 'error', seconds: 8 });
                return;
            }
            if (ticket !== this.playTicket) return;
            this.playing = true;
            const tick = () => {
                const at = mix.position();
                this.position = at?.seconds ?? null;
                if (!at || at.seconds >= this.length) {
                    this.stopPlaying();
                    return;
                }
                this.frame = requestAnimationFrame(tick);
            };
            this.frame = requestAnimationFrame(tick);
        },
        stopPlaying() {
            this.playTicket = (this.playTicket ?? 0) + 1;
            cancelAnimationFrame(this.frame);
            mix.stop();
            this.playing = false;
        },
        /** A change that needs the voices placed again: carries on from where it was. */
        async rearrange() {
            if (this.playing) await this.play(this.position ?? 0);
        },
        setHear(hear) {
            this.hear = hear;
            this.rearrange();
        },
        liveGain() {
            if (this.playing && this.hear === 'take') mix.setTakeLevel(mix.takeGain(this.item.loudnessDbfs, this.adjust.gainDb, this.peak));
        },
        /** A trim can't go past the take's length less the second the server keeps. */
        maxOf(a) {
            if (!a.field.startsWith('trim') || !this.item) return a.max;
            const room = Math.max(0, Math.floor((this.item.durationSeconds - 1) * 1000) - (a.field === 'trimStartMs' ? this.adjust.trimEndMs : this.adjust.trimStartMs));
            return Math.min(a.max, room);
        },
        pickPhrase(index) {
            this.picked = this.picked === index ? null : index;
            if (this.playing && this.picked !== null) this.play(this.phrases[index][0] * this.beatSeconds);
        },
        resetAdjust() {
            this.adjust = { ...this.item.adjust };
            this.rearrange();
        },

        // ---- Deciding ----

        ask(kind) {
            this.errors = null;
            this.sheet = kind;
        },
        /** Saves the fixes being tried; false if they were refused. */
        async saveAdjust() {
            if (!this.dirty) return true;
            this.busy = true;
            this.errors = null;
            try {
                const changed = Object.fromEntries(ADJUST.filter((a) => this.adjust[a.field] !== this.item.adjust[a.field]).map((a) => [a.field, this.adjust[a.field]]));
                const { data } = await api.admin.beta.submissions.adjust(this.item.id, changed);
                this.item = data;
                this.adjust = { ...data.adjust };
                toast(data.status === 'accepted' ? t('admin.review.fixesSavedPublish') : t('admin.review.fixesSaved'));
                return true;
            } catch (error) {
                this.errors = errorsFrom(error);
                if (this.errors) toast(t('admin.review.fixesRefused'), { tone: 'error', seconds: 8 });
                else this.refusal(error);
                return false;
            } finally {
                this.busy = false;
            }
        },
        async run(request, done) {
            this.busy = true;
            this.errors = null;
            try {
                const { data } = await request();
                this.item = data;
                this.sheet = null;
                toast(done);
                if (data.status === 'accepted' && data.publish === 'pending') this.watchPublish();
                return true;
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) this.refusal(error);
                return false;
            } finally {
                this.busy = false;
            }
        },
        async decide(choice) {
            if (this.sheet === 'accept') {
                if (!(await this.saveAdjust())) return;
                await this.run(() => api.admin.beta.submissions.accept(this.item.id, choice), t('admin.review.accepted'));
            } else {
                await this.run(() => api.admin.beta.submissions.reject(this.item.id, choice), t('admin.review.rejected'));
            }
        },
        async retake({ note, beat }) {
            await this.run(() => api.admin.beta.submissions.retake(this.item.id, { note, beat }), t('admin.review.retakeSent'));
        },
        async publish() {
            if (!(await this.saveAdjust())) return;
            await this.run(() => api.admin.beta.submissions.publish(this.item.id), t('admin.review.publishingAgain'));
        },
        /** Someone else decided first, or it can't be published: the take is shown as it is now. */
        refusal(error) {
            if (error instanceof ApiError && ['not_reviewable', 'not_publishable'].includes(error.code)) {
                toast(t(error.code === 'not_reviewable' ? tk('admin.review.notReviewable') : tk('admin.review.notPublishable')), { tone: 'error', seconds: 10 });
                this.sheet = null;
                this.load();
                return;
            }
            refused(error);
        },

        // ---- Moving around ----

        /** Leaves for another take or the queue, saving fixes being tried first (or staying if refused). */
        async leave(path) {
            if (this.canFix && this.dirty && !(await this.saveAdjust())) return;
            navigate(path);
        },
        go(id) {
            if (id) this.leave(`/admin/review/${id}${queue.query ? `?${queue.query}` : ''}`);
        },
        back() {
            this.leave(this.backHref);
        },
        key(event) {
            const target = event.target instanceof Element ? event.target : null;
            if (!shortcuts.on || this.sheet || event.metaKey || event.ctrlKey || event.altKey || target?.closest('input, textarea, select, [role=dialog]')) return;
            const key = event.key.toLowerCase();
            if (key === 'j') this.go(this.nextId);
            else if (key === 'k') this.go(this.previousId);
            else if (key === ' ' && !target?.closest('button, a')) {
                event.preventDefault();
                this.toggle();
            } else if (key === 'b') this.setHear(this.hear === 'take' ? 'synth' : 'take');
            else if (key === 'a' && this.decidable && this.isAdmin) this.ask('accept');
            else return;
        },
    },
};

/** Says what a failed load means on the take's page (or goes to sign in); true when handled. */
function refusedHere(error, vm) {
    if (error instanceof ApiError && error.code === 'not_found') {
        vm.failed = t('admin.review.notFound');
        return true;
    }
    refused(error);
    vm.failed = t('admin.requestFailed');
    return true;
}

export const ReviewSection = {
    components: { QueueList, ReviewTake },
    props: { me: { type: Object, required: true } },
    data: () => ({ templates: [] }),
    setup: () => ({ route }),
    template: `
        <ReviewTake v-if="route.params.id" :id="route.params.id" :me="me" />
        <QueueList v-else :templates="templates" />`,
    created() {
        import('../templates.js').then(({ listTemplates }) => listTemplates({ drafts: true })).then(
            (list) => (this.templates = list),
            (error) => console.error('Templates failed to load', error),
        );
    },
};
