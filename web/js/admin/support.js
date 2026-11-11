// The support inbox (contract/api.md, admin.support.*): what people sent from Contact us, open ones
// first, then one in full: the message, the details that came with it, the answers sent, and a reply
// (from a saved reply, or written) that's emailed to them, closing it if done. Close and Reopen.
// A director's learning tracks request (category `tracks`, docs/choir-tracks-plan.md) shows its piece, its score's files,
// the rights they confirmed, and Give this song: the arranger's file goes to their account, shared into their group,
// with their confirmation as the rights record, and the request closes (admin.people.songs.create with trackRequest).

import { api, ApiError } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate, route } from '../router.js';
import { EmptyState, Field, StatusChip } from '../components/form.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, errorsFrom, LoadFailed, loadInto, refused, when } from './common.js';
import { readSheetFile } from '../sheet-import.js';
import { SheetImport } from '../components/create/sheet-import.js';

const FILTERS = { open: tk('admin.support.filter.open'), answered: tk('admin.support.filter.answered'), closed: tk('admin.support.filter.closed'), all: tk('admin.support.filter.all') };
const STATUS = { open: { tone: 'info', label: tk('admin.support.status.open') }, answered: { tone: 'good', label: tk('admin.support.status.answered') }, closed: { tone: 'muted', label: tk('admin.support.status.closed') } };
const CATEGORY = { tracks: tk('admin.support.tracks.category'), recording: tk('contact.category.recording'), account: tk('contact.category.account'), payments: tk('contact.category.payments'), coaching: tk('contact.category.coaching'), other: tk('contact.category.other'), bug: tk('contact.category.bug'), idea: tk('contact.category.idea'), diagnostics: tk('admin.support.diagnostics') };

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
    data: () => ({ messages: null, failed: false, cursor: null, loadingMore: false, filter: 'open', tracksOnly: false, open: null, reply: '', close: true, sending: false, errors: null }),
    setup: () => ({ FILTERS, STATUS, CATEGORY, TRACK_STATUS, when, route }),
    template: `
        <div class="space-y-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h1 class="text-2xl font-extrabold tracking-tight text-cream">{{ t('admin.nav.support') }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="seg flex" role="radiogroup" :aria-label="t('admin.support.show')">
                        <button v-for="(word, key) in FILTERS" :key="key" type="button" role="radio" :aria-checked="filter === key ? 'true' : 'false'" class="seg-option" @click="pick(key)">{{ t(word) }}</button>
                    </div>
                    <!-- Directors' learning tracks requests alone (admin.support.list ?category=tracks). -->
                    <button type="button" class="chip tap-44" :class="tracksOnly ? 'text-accent-strong' : 'hover:text-cream'" :aria-pressed="tracksOnly ? 'true' : 'false'" data-tracks-only @click="toggleTracks">{{ t('admin.support.tracks.only') }}</button>
                </div>
            </div>
            <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <div>
                    <LoadFailed v-if="failed" :message="failed" @retry="load()" />
                    <p v-else-if="!messages" class="text-muted" role="status">{{ t('admin.loading') }}</p>
                    <EmptyState v-else-if="!messages.length" icon="info" :title="t('admin.support.none')" />
                    <ul v-else class="space-y-2">
                        <li v-for="m in messages" :key="m.id">
                            <a :href="'/admin/support/' + m.id" class="card-interactive block p-4" :class="open?.id === m.id ? 'border-accent' : ''" @click.prevent="navigate('/admin/support/' + m.id)">
                                <span class="flex items-center justify-between gap-2"><span class="truncate font-bold text-cream" translate="no">{{ m.from.account?.name ?? m.from.email }}</span><StatusChip :tone="STATUS[m.status].tone" :label="t(STATUS[m.status].label)" /></span>
                                <span class="mt-1 block truncate text-sm text-cream" translate="no">{{ m.preview }}</span>
                                <span v-if="m.tracks" class="mt-1.5 flex flex-wrap items-center gap-2 text-xs" :data-tracks-row="m.id"><StatusChip :tone="TRACK_STATUS[m.tracks.status]?.tone ?? 'muted'" :label="t(TRACK_STATUS[m.tracks.status]?.label ?? TRACK_STATUS.received.label)" /><span class="truncate text-muted" translate="no">{{ m.tracks.group }}</span></span>
                                <span class="mt-1 block text-xs text-muted">{{ t(CATEGORY[m.category] ?? CATEGORY.other) }} · {{ when(m.createdAt) }}</span>
                            </a>
                        </li>
                    </ul>
                    <button v-if="cursor && !failed" type="button" class="btn-secondary mt-3" :disabled="loadingMore" @click="load(true)">{{ loadingMore ? t('admin.loading') : t('admin.loadMore') }}</button>
                </div>
                <section v-if="open" class="card space-y-5 p-6" aria-labelledby="message-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 id="message-heading" class="text-lg font-bold text-cream" translate="no">{{ open.from.account?.name ?? open.from.email }}</h2>
                            <p class="text-sm text-muted"><span translate="no">{{ open.from.email }}</span> · {{ t(CATEGORY[open.category] ?? CATEGORY.other) }} · {{ when(open.createdAt) }}</p>
                        </div>
                        <button v-if="open.status === 'closed'" type="button" class="btn-secondary btn-sm" @click="setStatus('open')">{{ t('admin.support.reopen') }}</button>
                        <button v-else type="button" class="btn-ghost btn-sm" @click="setStatus('closed')">{{ t('admin.support.close') }}</button>
                    </div>
                    <p class="whitespace-pre-line text-cream" translate="no">{{ open.body }}</p>
                    <TracksRequest v-if="open.tracks" :message="open" :me="me" @given="given" />
                    <dl v-if="open.context" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 rounded-2xl bg-raised p-3 text-xs text-muted">
                        <template v-for="(value, key) in open.context" :key="key"><dt class="font-semibold" translate="no">{{ key }}</dt><dd class="min-w-0 break-words" translate="no">{{ typeof value === 'object' ? JSON.stringify(value) : value }}</dd></template>
                    </dl>
                    <ol v-if="open.replies.length" class="space-y-3">
                        <li v-for="(r, i) in open.replies" :key="i" class="rounded-2xl border border-edge p-4">
                            <p class="text-xs text-muted"><span translate="no">{{ r.by?.name }}</span> · {{ when(r.sentAt) }}</p>
                            <p class="mt-1 whitespace-pre-line text-cream" translate="no">{{ r.body }}</p>
                        </li>
                    </ol>
                    <form class="space-y-3" @submit.prevent="send">
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
    created() {
        this.load();
    },
    methods: {
        load(more = false) {
            return loadInto(this, 'messages', () => api.admin.support.list({ query: { status: this.filter, category: this.tracksOnly ? 'tracks' : undefined, cursor: more ? this.cursor : undefined } }), { more });
        },
        pick(filter) {
            this.filter = filter;
            this.messages = null;
            this.load();
        },
        toggleTracks() {
            this.tracksOnly = !this.tracksOnly;
            this.messages = null;
            this.load();
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
