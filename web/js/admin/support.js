// The support inbox (contract/api.md, admin.support.*): what people sent from Contact us, and beta singers' notes, new
// ones first, then one in full: the message, where it was opened from and what came with it (docs/feedback-plan.md),
// the answers sent, and a reply (from a saved reply, or written) that's emailed to them, closing it if done; with no
// reply address, only Close. Close and Reopen. Maybe spam (a guest's message full of links) stays listed, marked quietly.
// A director's learning tracks request (category `tracks`, docs/choir-tracks-plan.md) shows its piece, its score's files,
// the rights they confirmed, and Give this song: the arranger's file goes to their account, shared into their group,
// with their confirmation as the rights record, and the request closes (admin.people.songs.create with trackRequest).

import { api, ApiError } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, screen, when } from './common.js';
import { readSheetFile } from '../sheet-import.js';
import { SheetImport } from '../components/create/sheet-import.js';

// The wire's open, answered and closed, worded New, Replied and Closed (docs/feedback-plan.md).
const FILTERS = { open: tk('admin.support.filter.open'), answered: tk('admin.support.filter.answered'), closed: tk('admin.support.filter.closed'), all: tk('admin.support.filter.all') };
const STATUS = { open: { tone: 'info', label: tk('admin.support.status.open') }, answered: { tone: 'good', label: tk('admin.support.status.answered') }, closed: { tone: 'muted', label: tk('admin.support.status.closed') } };
const CATEGORY = { tracks: tk('admin.support.tracks.category'), beta: tk('admin.support.beta'), recording: tk('contact.category.recording'), account: tk('contact.category.account'), payments: tk('contact.category.payments'), coaching: tk('contact.category.coaching'), other: tk('contact.category.other'), bug: tk('contact.category.bug'), idea: tk('contact.category.idea'), diagnostics: tk('admin.support.diagnostics') };
/** The category chips: one category at a time (admin.support.list ?category=). */
const ONLY = { tracks: tk('admin.support.tracks.only'), beta: tk('admin.support.betaOnly') };
const MOMENT = { settings: tk('admin.support.moment.settings'), help: tk('admin.support.moment.help'), error: tk('admin.support.moment.error'), choir_ask: tk('admin.support.moment.choir_ask'), mic: tk('admin.support.moment.mic'), own_choir: tk('admin.support.moment.own_choir'), boot: tk('admin.support.moment.boot'), site_404: tk('admin.support.moment.site_404') };
const DEVICE = { phone: tk('admin.support.device.phone'), tablet: tk('admin.support.device.tablet'), computer: tk('admin.support.device.computer') };
const BROWSER = { chrome: 'Chrome', safari: 'Safari', firefox: 'Firefox', edge: 'Edge', samsung: 'Samsung Internet', opera: 'Opera' }; // i18n-ignore: browser names
const SKIN = { game: tk('admin.support.skin.game'), serious: tk('admin.support.skin.serious') };
const CHOIR_ISSUE = { voice: tk('admin.support.choirIssue.voice'), timing: tk('admin.support.choirIssue.timing'), notes: tk('admin.support.choirIssue.notes'), other: tk('admin.support.choirIssue.other') };
/** The context keys worded below; any other (an older app's) is shown as it came. */
const WORDED = ['device', 'browser', 'language', 'skin', 'templateId', 'partKey', 'beat', 'choirRequestId', 'errorCode', 'choirIssue', 'timingCheck', 'audioErrors', 'appVersion', 'page', 'betaSinger'];

const worded = (map, value) => (map[value] ? t(map[value]) : value);

/** A language code as its name ("es" is Spanish), or the code when the browser can't say. */
function languageName(code) {
    try {
        return new Intl.DisplayNames(['en'], { type: 'language' }).of(code) ?? code;
    } catch {
        return code;
    }
}

/**
 * What came with a message (docs/feedback-plan.md, Admin > Support): where it was opened from, then the device, the
 * language and the look, the song and part (a beta note's beat), the choir song and what was wrong with it, an error's
 * code, and the sound checks; `{ key, label, value, code }`, `code` for ids shown as they are.
 */
export function attached(message) {
    const c = message.context ?? {};
    const rows = [];
    const add = (key, label, value, code = false) => value !== undefined && value !== null && value !== '' && rows.push({ key, label, value: String(value), code });
    if (message.moment) add('moment', t('admin.support.with.moment'), worded(MOMENT, message.moment));
    // "another browser" begins the line when no device kind came with it; an older app's "iPhone" stays as it came.
    const other = t('admin.support.device.otherBrowser');
    const browser = c.browser && (BROWSER[c.browser] ?? (c.browser === 'other' ? (c.device ? other : other.charAt(0).toUpperCase() + other.slice(1)) : c.browser));
    add('device', t('admin.support.with.device'), [c.device && worded(DEVICE, c.device), browser].filter(Boolean).join(', '));
    if (c.language) add('language', t('admin.support.with.language'), languageName(c.language));
    if (c.skin) add('skin', t('admin.support.with.skin'), worded(SKIN, c.skin));
    if (c.templateId || c.partKey) {
        const song = [c.templateId, c.partKey].filter(Boolean).join(', ');
        add('song', t('admin.support.with.song'), Number.isFinite(c.beat) ? t('admin.support.with.atBeat', { song, beat: formatNumber(c.beat) }) : song, true);
    }
    add('choirRequestId', t('admin.support.with.choirRequest'), c.choirRequestId, true);
    if (c.choirIssue) add('choirIssue', t('admin.support.with.choirIssue'), worded(CHOIR_ISSUE, c.choirIssue));
    add('errorCode', t('admin.support.with.errorCode'), c.errorCode, true);
    if (c.timingCheck) add('timingCheck', t('admin.support.with.timingCheck'), Number.isFinite(c.timingCheck.latencyMs) ? formatNumber(c.timingCheck.latencyMs, { style: 'unit', unit: 'millisecond' }) : JSON.stringify(c.timingCheck), true);
    if (c.audioErrors?.length) add('audioErrors', t('admin.support.with.audioErrors'), c.audioErrors.join('\n'), true);
    add('appVersion', t('admin.support.with.appVersion'), c.appVersion, true);
    add('page', t('admin.support.with.page'), c.page, true);
    add('betaSinger', t('admin.support.with.betaSinger'), c.betaSinger, true);
    for (const [key, value] of Object.entries(c)) if (!WORDED.includes(key)) add(key, key, typeof value === 'object' ? JSON.stringify(value) : value, true);
    return rows;
}

/** Who wrote: the account's name, else the email they left, else Guest. */
const sender = (m) => m.from.account?.name ?? m.from.email ?? t('admin.support.guest');

const TRACK_STATUS = { received: { tone: 'warn', label: tk('admin.support.tracks.status.received') }, delivered: { tone: 'good', label: tk('admin.support.tracks.status.delivered') }, closed: { tone: 'muted', label: tk('admin.support.tracks.status.closed') } };
const SCORE_KIND = { pdf: tk('admin.support.tracks.kind.pdf'), musicxml: tk('admin.support.tracks.kind.musicxml'), mxl: tk('admin.support.tracks.kind.mxl'), photo: tk('admin.support.tracks.kind.photo') };
const GIVE_REFUSALS = { email_unverified: tk('admin.people.refused.emailUnverified'), import_blocked: tk('admin.people.refused.importBlocked') };

/** A file's size as people read it: 820 kB, 1.8 MB. */
function size(bytes) {
    return bytes < 1e6 ? formatNumber(Math.max(1, Math.round(bytes / 1e3)), { style: 'unit', unit: 'kilobyte' }) : formatNumber(bytes / 1e6, { style: 'unit', unit: 'megabyte', maximumFractionDigits: 1 });
}

/** A learning tracks request in full: the piece, the score, the rights, and Give this song (or what was given). */
const TracksRequest = {
    components: { ChangeSheet, Field, SheetImport, StatusChip },
    props: { message: { type: Object, required: true }, me: { type: Object, required: true } },
    emits: ['given'],
    // Import this score (docs/score-import-plan.md, section 5): the score's MusicXML read here from its 10-minute link,
    // set up in Bring your music, checked (templates.check), then given with the director's record and the request.
    data: () => ({ giving: false, songFile: null, songFileName: '', busy: false, errors: null, sheetScore: null, importing: null, importFailed: null, checking: false, checkProblems: null, advice: null }),
    setup: () => ({ TRACK_STATUS, SCORE_KIND, size, when }),
    template: `
        <section class="space-y-4 rounded-2xl border border-line p-4" aria-labelledby="tracks-h" data-tracks-request>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="tracks-h" class="font-bold text-cream">{{ t('admin.support.tracks.title') }}</h3>
                <StatusChip :tone="TRACK_STATUS[tracks.status]?.tone ?? 'muted'" :label="t(TRACK_STATUS[tracks.status]?.label ?? TRACK_STATUS.received.label)" data-tracks-status />
            </div>
            <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                <dt class="text-muted">{{ t('admin.support.tracks.piece') }}</dt><dd class="font-semibold text-cream" translate="no">{{ tracks.piece }}</dd>
                <template v-if="tracks.composer"><dt class="text-muted">{{ t('admin.support.tracks.composer') }}</dt><dd class="text-cream" translate="no">{{ tracks.composer }}</dd></template>
                <template v-if="tracks.voicing"><dt class="text-muted">{{ t('admin.support.tracks.voicing') }}</dt><dd class="text-cream" translate="no">{{ tracks.voicing }}</dd></template>
                <dt class="text-muted">{{ t('admin.support.tracks.group') }}</dt><dd class="text-cream" translate="no">{{ tracks.group }}</dd>
            </dl>
            <div class="space-y-1.5">
                <p class="text-sm font-semibold text-cream">{{ t('admin.support.tracks.rights') }}</p>
                <blockquote class="border-s-2 border-line ps-3 text-sm text-cream" translate="no" data-tracks-rights>{{ tracks.rightsStatement }}</blockquote>
                <p v-if="tracks.rightsConfirmedAt" class="text-xs text-muted">{{ t('admin.support.tracks.confirmedAt', { when: when(tracks.rightsConfirmedAt) }) }}</p>
            </div>
            <div class="space-y-2">
                <p class="text-sm font-semibold text-cream">{{ t('admin.support.tracks.scores') }}</p>
                <p v-if="!tracks.scores?.length" class="text-sm text-muted" data-tracks-no-score>{{ t('admin.support.tracks.noScore') }}</p>
                <ul v-else class="divide-y divide-line rounded-xl border border-line">
                    <li v-for="s in tracks.scores" :key="s.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2.5 text-sm" :data-tracks-score="s.id">
                        <span class="min-w-0 flex-1 break-all text-cream" translate="no">{{ s.name }}</span>
                        <span class="text-xs text-muted">{{ SCORE_KIND[s.kind] ? t(SCORE_KIND[s.kind]) : s.kind }} · {{ size(s.bytes) }}</span>
                        <a v-if="s.url" :href="s.url" class="link tap-44 text-sm font-semibold" rel="noopener" download data-tracks-download>{{ t('admin.support.tracks.download') }}</a>
                        <button v-if="importable(s)" type="button" class="btn-secondary btn-sm" :disabled="!!importing || checking" :data-tracks-import="s.id" @click="importScore(s)">{{ importing === s.id ? t('admin.support.tracks.importing') : t('admin.support.tracks.import') }}</button>
                    </li>
                </ul>
                <!-- The link ran out, or the bucket wouldn't hand it over: the same file, downloaded and chosen here. -->
                <div v-if="importFailed" class="space-y-2 text-sm" role="alert" data-tracks-import-failed>
                    <p class="text-bad">{{ t('admin.support.tracks.importFailed') }}</p>
                    <label class="block space-y-1"><span class="label">{{ t('admin.support.tracks.chooseFile') }}</span><input type="file" accept=".musicxml,.xml,.mxl" class="input w-full" data-tracks-import-file @change="importChosen" /></label>
                </div>
                <div v-if="checkProblems?.length" class="space-y-1 text-sm" role="alert" data-tracks-check-problems>
                    <p class="text-bad">{{ t('admin.support.tracks.problems', { count: checkProblems.length }) }}</p>
                    <ul class="list-disc space-y-0.5 ps-5 text-cream"><li v-for="(p, i) in checkProblems" :key="i" translate="no">{{ p }}</li></ul>
                </div>
                <p v-if="tracks.scores?.some((s) => s.url)" class="text-xs text-muted">{{ t('admin.support.tracks.linkNote') }}</p>
            </div>
            <!-- Delivered: what was given, and when. Otherwise Give this song, from their confirmation. -->
            <p v-if="tracks.status === 'delivered' && tracks.song" class="text-sm text-cream" data-tracks-delivered>
                {{ t('admin.support.tracks.delivered', { when: when(tracks.deliveredAt) }) }} <a :href="'/t/' + tracks.song.id" class="link" translate="no">{{ tracks.song.title }}</a>
            </p>
            <template v-else-if="tracks.status === 'received'">
                <button v-if="tracks.give && message.from.account" type="button" class="btn-primary btn-sm" data-tracks-give @click="openGive">{{ t('admin.support.tracks.give') }}</button>
                <p v-else-if="message.from.account" class="text-sm text-muted">{{ t('admin.support.tracks.noGive') }} <a :href="'/admin/people/' + message.from.account.id" class="link">{{ t('admin.support.tracks.toAccount') }}</a></p>
            </template>
            <SheetImport v-if="sheetScore" :score="sheetScore" :busy="checking" :rights-record="tracks.give.rights" @close="sheetScore = null" @make="checkImported" />
            <ChangeSheet v-if="giving" :title="t('admin.support.tracks.giveTitle', { piece: tracks.piece, name: message.from.account.name })" :me="me" :busy="busy" :errors="errors" :fields="['song', 'trackRequest']" :reasoned="false" @close="giving = false" @confirm="give">
                <p class="text-sm text-cream">{{ t('admin.support.tracks.giveBody', { name: message.from.account.name, group: tracks.group }) }}</p>
                <p v-if="advice" class="text-sm text-cream" data-tracks-imported>{{ t('admin.people.song.picked', { title: songFile?.title ?? '', name: songFileName }) }}</p>
                <Field v-else :label="t('admin.people.song.file')" :help="songFile ? t('admin.people.song.picked', { title: songFile.title ?? '', name: songFileName }) : t('admin.people.song.fileHelp')" :error="errors?.song" required v-slot="f">
                    <input :id="f.id" type="file" accept=".json,application/json" class="input w-full" :aria-describedby="f.describedby" data-song-file @change="pickSong" />
                </Field>
                <!-- What a singer will meet (templates.check's advice): proposals, never applied to the choir's music. -->
                <div v-if="advice?.length" class="space-y-1 text-sm" data-tracks-advice>
                    <p class="font-semibold text-cream">{{ t('admin.support.tracks.advice') }}</p>
                    <ul class="list-disc space-y-0.5 ps-5 text-muted"><li v-for="(a, i) in advice" :key="i" translate="no">{{ a.text }}</li></ul>
                    <p class="text-xs text-muted">{{ t('admin.support.tracks.adviceNote') }}</p>
                </div>
                <div class="space-y-1 rounded-xl bg-raised p-3 text-xs text-muted" data-tracks-give-rights>
                    <p class="font-semibold text-cream">{{ t('admin.support.tracks.rightsRecord') }}</p>
                    <p translate="no">{{ tracks.give.rights.confirmedBy }}</p>
                    <p translate="no">{{ tracks.give.rights.source }}</p>
                </div>
                <p v-if="errors?.trackRequest" class="text-sm text-bad" role="alert">{{ errors.trackRequest[0] }}</p>
                <p v-if="errors?.refusal" class="text-sm text-bad" role="alert">{{ errors.refusal[0] }}</p>
            </ChangeSheet>
        </section>`,
    computed: {
        tracks: (vm) => vm.message.tracks,
    },
    methods: {
        openGive() {
            this.errors = null;
            this.songFile = null;
            this.songFileName = '';
            this.advice = null;
            this.giving = true;
        },
        /** A score the importer reads, for a request that can still be given. */
        importable(s) {
            return ['musicxml', 'mxl'].includes(s.kind) && !!s.url && this.tracks.status === 'received' && !!this.tracks.give && !!this.message.from.account;
        },
        /** The score from its link, into Bring your music; if the link won't give it up, the file chosen here. */
        async importScore(s) {
            this.importing = s.id;
            this.importFailed = null;
            this.checkProblems = null;
            this.songFileName = s.name;
            try {
                const response = await fetch(s.url, { mode: 'cors', credentials: 'omit' });
                if (!response.ok) throw new Error(`The score's link answered ${response.status}`);
                await this.openScore(new File([await response.arrayBuffer()], s.name));
            } catch (error) {
                console.warn('The score did not open from its link', error);
                this.importFailed = s.id;
            } finally {
                this.importing = null;
            }
        },
        async importChosen(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.songFileName = file.name;
            try {
                await this.openScore(file);
                this.importFailed = null;
            } catch (error) {
                console.warn('The chosen score did not open', error);
            }
        },
        async openScore(file) {
            const score = await readSheetFile(file);
            if (score.unreadable || !score.lines?.length) throw new Error('not a score the importer reads');
            this.sheetScore = score;
        },
        /** The song from the score, checked before it's given: problems stop it, advice comes along unapplied. */
        async checkImported(template) {
            this.checking = true;
            try {
                const { data } = await api.templates.check(template);
                if (data.problems?.length) {
                    this.checkProblems = data.problems;
                    this.sheetScore = null;
                    return;
                }
                this.sheetScore = null;
                this.errors = null;
                this.songFile = template;
                this.advice = data.advice ?? [];
                this.giving = true;
            } catch (error) {
                const fields = errorsFrom(error);
                if (fields) {
                    this.checkProblems = Object.values(fields).flat();
                    this.sheetScore = null;
                } else refused(error);
            } finally {
                this.checking = false;
            }
        },
        /** The arranger's template file, read here and sent as it is (the server checks it). */
        async pickSong(event) {
            const file = event.target.files?.[0];
            this.songFile = null;
            this.songFileName = file?.name ?? '';
            if (!file) return;
            try {
                const song = JSON.parse(await file.text());
                if (!song || typeof song !== 'object' || Array.isArray(song)) throw new SyntaxError('not a template');
                this.songFile = song;
                if (this.errors?.song) this.errors = { ...this.errors, song: undefined };
            } catch (error) {
                console.info('The song file was unreadable', error?.message ?? error);
                this.errors = { ...(this.errors ?? {}), song: [t('admin.people.song.unreadable')] };
            }
        },
        async give() {
            if (!this.songFile) {
                this.errors = { song: [t('admin.people.song.noFile')] };
                return;
            }
            this.busy = true;
            this.errors = null;
            try {
                const song = (await api.admin.people.giveSong(this.message.from.account.id, { song: this.songFile, rights: { ...this.tracks.give.rights }, trackRequest: this.message.id })).data;
                toast(t('admin.support.tracks.given', { title: song.title, group: this.tracks.group }));
                this.giving = false;
                this.$emit('given');
            } catch (error) {
                const fields = errorsFrom(error);
                if (fields) this.errors = fields;
                else if (error instanceof ApiError && GIVE_REFUSALS[error.code]) this.errors = { refusal: [t(GIVE_REFUSALS[error.code])] };
                else {
                    this.errors = {};
                    refused(error);
                }
            } finally {
                this.busy = false;
            }
        },
    },
};

export const SupportSection = {
    components: { EmptyState, Field, LoadFailed, StatusChip, TracksRequest },
    props: { me: { type: Object, required: true } },
    data: () => ({ messages: null, failed: false, cursor: null, loadingMore: false, filter: 'open', category: null, maybeSpam: false, open: null, reply: '', close: true, sending: false, errors: null }),
    setup: () => ({ FILTERS, STATUS, CATEGORY, MOMENT, ONLY, TRACK_STATUS, attached, sender, when, route }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-bold text-cream">{{ t('admin.nav.support') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="seg flex" role="radiogroup" :aria-label="t('admin.support.show')">
                        <button v-for="(word, key) in FILTERS" :key="key" type="button" role="radio" :aria-checked="filter === key ? 'true' : 'false'" class="seg-option" :data-support-filter="key" @click="pick(key)">{{ t(word) }}</button>
                    </div>
                    <!-- Directors' learning tracks requests or beta singers' notes alone (?category=), and Maybe spam (?maybeSpam=1). -->
                    <button v-for="(word, key) in ONLY" :key="key" type="button" class="chip tap-44" :class="category === key ? 'text-accent-strong' : 'hover:text-cream'" :aria-pressed="category === key ? 'true' : 'false'" :data-tracks-only="key === 'tracks' ? '' : null" :data-support-only="key" @click="only(key)">{{ t(word) }}</button>
                    <button type="button" class="chip tap-44" :class="maybeSpam ? 'text-accent-strong' : 'hover:text-cream'" :aria-pressed="maybeSpam ? 'true' : 'false'" data-maybe-spam-only @click="toggleSpam">{{ t('admin.support.maybeSpam') }}</button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <div>
                    <LoadFailed v-if="failed" :message="failed" @retry="load()" />
                    <p v-else-if="!messages" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                    <EmptyState v-else-if="!messages.length" icon="info" :title="t('admin.support.none')" />
                    <ul v-else class="space-y-2">
                        <li v-for="m in messages" :key="m.id">
                            <a :href="'/admin/support/' + m.id" class="card-interactive block p-4" :class="open?.id === m.id ? 'border-accent' : ''" @click.prevent="navigate('/admin/support/' + m.id)">
                                <span class="flex items-center justify-between gap-2"><span class="truncate font-bold text-cream" :translate="m.from.account || m.from.email ? 'no' : null">{{ sender(m) }}</span><StatusChip :tone="STATUS[m.status].tone" :label="t(STATUS[m.status].label)" /></span>
                                <span class="mt-1 block truncate text-sm text-cream" translate="no">{{ m.preview }}</span>
                                <span v-if="m.tracks" class="mt-1.5 flex flex-wrap items-center gap-2 text-xs" :data-tracks-row="m.id"><StatusChip :tone="TRACK_STATUS[m.tracks.status]?.tone ?? 'muted'" :label="t(TRACK_STATUS[m.tracks.status]?.label ?? TRACK_STATUS.received.label)" /><span class="truncate text-muted" translate="no">{{ m.tracks.group }}</span></span>
                                <span class="mt-1 block text-xs text-muted">{{ t(CATEGORY[m.category] ?? CATEGORY.other) }}<template v-if="MOMENT[m.moment]"> · {{ t(MOMENT[m.moment]) }}</template> · {{ when(m.createdAt) }}</span>
                                <!-- Marked quietly: Maybe spam and No reply address stay in the list, in the muted words. -->
                                <span v-if="m.maybeSpam || m.canReply === false" class="mt-1 block text-xs text-muted" :data-support-marks="m.id">
                                    <span v-if="m.maybeSpam" data-maybe-spam>{{ t('admin.support.maybeSpam') }}</span><template v-if="m.maybeSpam && m.canReply === false"> · </template><span v-if="m.canReply === false" data-no-reply>{{ t('admin.support.noReply') }}</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                    <button v-if="cursor && !failed" type="button" class="btn-secondary mt-3" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
                </div>
                <section v-if="open" class="card space-y-5 p-6" aria-labelledby="message-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="message-heading" class="text-lg font-bold text-cream" :translate="open.from.account || open.from.email ? 'no' : null">{{ sender(open) }}</h2>
                            <p class="text-sm text-muted">
                                <span v-if="open.canReply !== false && open.from.email" translate="no">{{ open.from.email }}</span><span v-else data-no-reply>{{ t('admin.support.noReply') }}</span>
                                · {{ t(CATEGORY[open.category] ?? CATEGORY.other) }} · {{ when(open.createdAt) }}<template v-if="open.maybeSpam"> · <span data-maybe-spam>{{ t('admin.support.maybeSpam') }}</span></template>
                            </p>
                            <a v-if="open.from.account" :href="'/admin/people/' + open.from.account.id" class="link tap-44 text-sm" @click.prevent="navigate('/admin/people/' + open.from.account.id)">{{ t('admin.support.tracks.toAccount') }}</a>
                        </div>
                        <button v-if="open.status === 'closed'" type="button" class="btn-secondary btn-sm" @click="setStatus('open')">{{ t('admin.support.reopen') }}</button>
                        <button v-else type="button" class="btn-ghost btn-sm" @click="setStatus('closed')">{{ t('admin.support.close') }}</button>
                    </div>
                    <p class="whitespace-pre-line text-cream" translate="no">{{ open.body }}</p>
                    <TracksRequest v-if="open.tracks" :message="open" :me="me" @given="given" />
                    <!-- Sent with it: the moment and what the app attached, worded (ids and codes as they came). -->
                    <section v-if="sentWith.length" class="space-y-2 rounded-2xl bg-raised p-4" aria-labelledby="sent-with-h" data-sent-with>
                        <h3 id="sent-with-h" class="text-sm font-semibold text-cream">{{ t('admin.support.with.title') }}</h3>
                        <dl class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                            <template v-for="row in sentWith" :key="row.key"><dt class="text-muted">{{ row.label }}</dt><dd class="min-w-0 whitespace-pre-line break-words text-cream" :translate="row.code ? 'no' : null" :data-sent="row.key">{{ row.value }}</dd></template>
                        </dl>
                    </section>
                    <ol v-if="open.replies.length" class="space-y-3">
                        <li v-for="(r, i) in open.replies" :key="i" class="rounded-2xl border border-edge p-4">
                            <p class="text-xs text-muted"><span translate="no">{{ r.by?.name }}</span> · {{ when(r.sentAt) }}</p>
                            <p class="mt-1 whitespace-pre-line text-cream" translate="no">{{ r.body }}</p>
                        </li>
                    </ol>
                    <!-- No reply address (a guest who left none, or Don't reply to me): nothing to send, only Close. -->
                    <p v-if="open.canReply === false" class="rounded-2xl border border-line p-4 text-sm text-muted" data-no-reply-note>{{ t('admin.support.noReplyNote') }}</p>
                    <form v-else class="space-y-3" @submit.prevent="send">
                        <Field :label="t('admin.support.reply')" :help="t('admin.support.replyHelp')" :error="errors?.body" required v-slot="f">
                            <textarea :id="f.id" v-model="reply" rows="6" class="input" :aria-describedby="f.describedby" :aria-invalid="f.invalid"></textarea>
                        </Field>
                        <div v-if="open.savedReplies.length" class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="text-muted">{{ t('admin.support.startFrom') }}</span>
                            <button v-for="s in open.savedReplies" :key="s.key" type="button" class="tap-44 chip hover:text-cream" @click="reply = s.body">{{ s.title }}</button>
                        </div>
                        <label class="check-row"><input v-model="close" type="checkbox" class="checkbox" /> {{ t('admin.support.closeAfter') }}</label>
                        <button type="submit" class="btn-primary" :disabled="sending || !reply.trim()">{{ sending ? t('admin.support.sending') : t('admin.support.send') }}</button>
                    </form>
                </section>
                <EmptyState v-else-if="messages?.length" icon="info" :title="t('admin.support.pick')" />
            </div>
        </div>`,
    watch: {
        'route.params.id': {
            handler(id) {
                if (id) this.show(id);
                else this.open = null;
            },
            immediate: true,
        },
    },
    computed: {
        sentWith: (vm) => (vm.open ? attached(vm.open) : []),
    },
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            const query = { status: this.filter, category: this.category ?? undefined, maybeSpam: this.maybeSpam ? 1 : undefined, cursor: more ? this.cursor : undefined };
            return loadInto(this, 'messages', () => api.admin.support.list({ query }), { more });
        },
        reload() {
            this.messages = null;
            this.load();
        },
        pick(filter) {
            this.filter = filter;
            this.reload();
        },
        /** Learning tracks or Beta notes alone; the same chip again shows every category. */
        only(category) {
            this.category = this.category === category ? null : category;
            this.reload();
        },
        toggleSpam() {
            this.maybeSpam = !this.maybeSpam;
            this.reload();
        },
        /** A song given from a tracks request: the message (now delivered and closed) read again, here and in the list. */
        async given() {
            try {
                this.keep((await api.admin.support.show({ params: { id: this.open.id } })).data);
            } catch (error) {
                refused(error);
            }
        },
        async show(id) {
            try {
                this.open = (await api.admin.support.show({ params: { id } })).data;
                this.reply = '';
                this.errors = null;
                // On a phone the message is under the list: brought into view, its heading first.
                if (screen.phone) this.$nextTick(() => this.$el.querySelector?.('#message-heading')?.scrollIntoView({ block: 'start' }));
            } catch (error) {
                refused(error);
            }
        },
        keep(message) {
            this.open = message;
            const i = this.messages?.findIndex((m) => m.id === message.id) ?? -1;
            if (i >= 0) this.messages.splice(i, 1, message);
        },
        async send() {
            this.sending = true;
            this.errors = null;
            try {
                this.keep((await api.admin.support.reply({ params: { id: this.open.id }, body: { body: this.reply.trim(), close: this.close } })).data);
                this.reply = '';
                toast(t('admin.support.sent'));
            } catch (error) {
                this.errors = errorsFrom(error);
                if (!this.errors) refused(error);
            } finally {
                this.sending = false;
            }
        },
        async setStatus(status) {
            try {
                this.keep((await api.admin.support.update({ params: { id: this.open.id }, body: { status } })).data);
            } catch (error) {
                refused(error);
            }
        },
        navigate,
    },
};
