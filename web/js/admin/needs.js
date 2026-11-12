// What needs Hillel now (the coordinator's guide workflow ask; admin.beta.guides.list's per-part `needs`,
// admin.beta.assignments.again). Two pieces:
// - NeedsCard, the top of Admin Home: only what needs him now (outreach waiting, the week's worst drop-offs, the site's
//   sync and sitemap problems). The voices aren't in it, nor its total (Hillel, 2026-11-12: "dont include voices in what
//   needs you now"): every song's parts lacking singers isn't a need of the day. They stay on Library > Guides, and a
//   take waiting for him is on Inbox > Review.
// - PartNeeds, each part on Admin > Guides: chips (takes to review, Changed, No singer), the singers asked and where
//   they stand, and Ask again for a singer who recorded an older version: one tap (the server's own note), or with a
//   note of Hillel's.

import { api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { StatusChip } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { ChangeSheet, LoadFailed, refused, when } from './common.js';
import { DropoffLine, dropPath, lastWeekOf } from './dropoffs.js';
import { SharingPanel, surfaceName, weakest } from './sharing.js';
import { breadthLine } from './breadth.js';

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

/**
 * How many a server's list stands for: its lines, or, when its last line is "and N more" (the sitemap check's cap), the
 * lines before it plus N.
 */
export function listCount(lines = []) {
    const more = /^and (\d+) more$/i.exec(lines.at(-1) ?? '');
    return more ? lines.length - 1 + Number(more[1]) : lines.length;
}

/** What Home counts in the nav: the songs the last deploy couldn't sync and the sitemap check's problems. */
export function homeCount(overview) {
    return listCount(overview?.songsNotSynced ?? []) + listCount(overview?.sitemapProblems ?? []);
}

/** A list of lines from the server as one item: its title, its lines on a tap. */
const NeedLines = {
    props: { kind: { type: String, required: true }, title: { type: String, required: true }, show: { type: String, required: true }, note: { type: String, default: '' }, lines: { type: Array, required: true } },
    template: `
        <details class="rounded-xl border border-line p-3" :data-needs-list="kind">
            <summary class="flex min-h-11 cursor-pointer list-none flex-wrap items-center gap-x-2 gap-y-1 [&::-webkit-details-marker]:hidden">
                <span class="font-semibold text-cream">{{ title }}</span>
                <span class="text-sm text-muted">{{ show }}</span>
            </summary>
            <p v-if="note" class="mt-1 text-sm text-muted">{{ note }}</p>
            <ul class="mt-2 space-y-1 text-sm text-cream">
                <li v-for="(line, i) in lines" :key="i" class="break-words" :class="more(line) ? 'text-sm text-muted' : 'font-mono text-xs'" :translate="more(line) ? null : 'no'" data-need-line>{{ line }}</li>
            </ul>
        </details>`,
    methods: {
        /** The server's "and N more": said as words, not as a line of its own. */
        more: (line) => /^and \d+ more$/i.test(line),
    },
};

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
    components: { DropoffLine, Icon, LoadFailed, NeedLines, SharingPanel },
    // Outreach's follow-ups due and replies to read (admin.overview's outreach: null for an editor), and the songs the last
    // deploy's sync couldn't load (admin.overview's songsNotSynced: the file and why, a line each; empty when it was clean),
    // and what the weekly sitemap check last found (sitemapProblems: a page search can't index and why; "and N more").
    // For an admin, the week's three worst drop-offs and how sharing went (admin.dropoffs.show, admin.sharing.show), asked here.
    // `loaded` once the overview came, `failed` when it didn't (the shell's ask; retry asks again).
    props: { outreach: { type: Object, default: null }, notSynced: { type: Array, default: () => [] }, sitemap: { type: Array, default: () => [] }, admin: { type: Boolean, default: false }, loaded: { type: Boolean, default: false }, failed: { type: Boolean, default: false } },
    emits: ['retry'],
    data: () => ({ dropoffs: null, sharing: null, breadth: null }),
    setup: () => ({ listCount, formatNumber, dropPath, lastWeekOf, surfaceName }),
    template: `
        <section class="space-y-3" aria-labelledby="needs-h" data-admin-needs>
            <h2 id="needs-h" class="text-xl font-bold text-cream">{{ t('admin.needs.title') }}<span v-if="total" class="ms-2 font-medium tabular-nums text-muted" data-needs-total>{{ formatNumber(total) }}</span></h2>
            <LoadFailed v-if="failed && !loaded" :message="t('admin.needs.failed')" @retry="$emit('retry')" />
            <p v-else-if="!loaded" class="text-sm text-muted" role="status">{{ t('admin.loading') }}</p>
            <p v-else-if="!total" class="text-sm text-muted" data-needs-none>{{ t('admin.needs.none') }}</p>
            <!-- Sharing isn't a need, so it shows (and counts) only beside the rest; nothing needing him, it sits under the line. -->
            <div v-if="loaded && (total || sharing)" class="grid items-start gap-4 min-[900px]:grid-cols-2">
                <!-- Outreach (docs/outreach-plan.md): a row for each kind waiting, its count at the left, each to where it's done. -->
                <section v-if="outreachRows.length" class="card overflow-hidden" aria-labelledby="needs-outreach-h" data-needs-outreach>
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                        <h3 id="needs-outreach-h" class="eyebrow text-muted">{{ t('admin.nav.outreach') }}</h3>
                        <a href="/admin/outreach" class="link tap-44 inline-flex items-center gap-1 text-sm" @click.prevent="navigate('/admin/outreach')">{{ t('admin.needs.toOutreach') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                    </div>
                    <ul class="divide-y divide-line">
                        <li v-for="row in outreachRows" :key="row.key">
                            <a :href="row.path" class="flex min-h-14 items-center gap-3.5 px-5 py-3 text-cream transition hover:bg-(--fill)" :data-needs-outreach-row="row.key" @click.prevent="navigate(row.path)">
                                <span class="grid h-7 min-w-7 shrink-0 place-items-center rounded-lg px-1.5 text-sm font-bold tabular-nums" :class="row.tone === 'warn' ? 'bg-warn/15 text-warn' : 'bg-accent/15 text-accent-strong'" data-count>{{ formatNumber(row.count) }}</span>
                                <span class="min-w-0 flex-1">{{ row.label }}</span>
                                <Icon name="chevron" :size="16" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
                            </a>
                        </li>
                    </ul>
                </section>
                <!-- Drop-offs (docs/retention-plan.md, its Detect section): the week's three worst steps, each to where it's read. -->
                <section v-if="worst.length" class="card overflow-hidden" aria-labelledby="needs-drops-h" data-needs-dropoffs>
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                        <h3 id="needs-drops-h" class="eyebrow text-muted">{{ t('admin.nav.dropoffs') }}</h3>
                        <a href="/admin/dropoffs" class="link tap-44 inline-flex items-center gap-1 text-sm" @click.prevent="navigate('/admin/dropoffs')">{{ t('admin.needs.toDropoffs') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                    </div>
                    <ul class="divide-y divide-line">
                        <li v-for="d in worst" :key="d.path + d.step">
                            <a :href="dropPath(d.path)" class="flex items-center gap-3.5 px-5 py-3 transition hover:bg-(--fill)" :data-needs-dropoff="d.step" @click.prevent="navigate(dropPath(d.path))">
                                <DropoffLine :drop="d" :last-week="lastWeekOf(dropoffs.previous, d.path, d.step)" />
                                <Icon name="chevron" :size="16" class="shrink-0 -rotate-90 text-muted rtl:rotate-90" />
                            </a>
                        </li>
                    </ul>
                </section>
                <!-- Sharing (docs/retention-plan.md, its Sharing section): the four measures and their move, the weakest surface named. -->
                <section v-if="sharing" class="card overflow-hidden" aria-labelledby="needs-sharing-h" data-needs-sharing>
                    <div class="flex items-center justify-between gap-3 border-b border-line px-5 py-3.5">
                        <h3 id="needs-sharing-h" class="eyebrow text-muted">{{ t('admin.nav.sharing') }}</h3>
                        <a href="/admin/sharing" class="link tap-44 inline-flex items-center gap-1 text-sm" @click.prevent="navigate('/admin/sharing')">{{ t('admin.needs.toSharing') }}<span aria-hidden="true" class="rtl:-scale-x-100">→</span></a>
                    </div>
                    <div class="space-y-3 p-4">
                        <SharingPanel :report="sharing" class="!grid-cols-2" />
                        <p v-if="weakSurface" class="text-sm text-muted" data-needs-sharing-weakest>{{ t('admin.needs.weakest', { surface: surfaceName(weakSurface.surface), perShare: formatNumber(weakSurface.arrivalsPerShare, { maximumFractionDigits: 2 }) }) }}</p>
                    </div>
                </section>
                <!-- The site: songs the last deploy couldn't sync (each still on its live version), and the sitemap check's
                     problems, an item each, its lines on a tap. -->
                <section v-if="notSynced.length || sitemap.length" class="card space-y-3 p-5" aria-labelledby="needs-site-h" data-needs-site>
                    <h3 id="needs-site-h" class="eyebrow text-muted">{{ t('admin.needs.site') }}</h3>
                    <NeedLines v-if="notSynced.length" kind="notSynced" :title="notSyncedTitle" :show="t('admin.needs.notSynced.show')" :note="t('admin.needs.notSynced.note')" :lines="notSynced" />
                    <NeedLines v-if="sitemap.length" kind="sitemap" :title="t('admin.needs.sitemap.title', { count: listCount(sitemap) })" :show="t('admin.needs.sitemap.show')" :note="t('admin.needs.sitemap.note')" :lines="sitemap" />
                </section>
            </div>
            <!-- Breadth (docs/retention-plan.md): how much of the app this week's active singers have used, one line to its tab. -->
            <a v-if="breadthText" href="/admin/breadth" class="link tap-44 inline-flex text-sm" data-needs-breadth @click.prevent="navigate('/admin/breadth')">{{ breadthText }}</a>
        </section>`,
    computed: {
        /** Outreach's rows, those with something: the first emails due soon, follow-ups due, replies to read. */
        outreachRows() {
            const o = this.outreach;
            if (!o) return [];
            return [
                { key: 'sendBySoon', count: o.sendBySoon ?? 0, label: t('admin.needs.outreach.sendBySoon', { count: o.sendBySoon ?? 0 }), path: '/admin/outreach', tone: 'warn' },
                { key: 'followUps', count: o.followUpsDue ?? 0, label: t('admin.needs.outreach.followUps', { count: o.followUpsDue ?? 0 }), path: '/admin/outreach?status=follow_up_due', tone: 'accent' },
                { key: 'replies', count: o.unreadReplies ?? 0, label: t('admin.needs.outreach.replies', { count: o.unreadReplies ?? 0 }), path: '/admin/outreach?status=replied', tone: 'accent' },
            ].filter((row) => row.count > 0);
        },
        /** What needs him now, beside the title: outreach's, the week's worst drop-offs and the site's (never the voices). */
        total() {
            return this.outreachRows.reduce((sum, row) => sum + row.count, 0) + listCount(this.notSynced) + listCount(this.sitemap) + this.worst.length;
        },
        /** The week's three worst drop-offs (none before the first report). */
        worst() {
            return this.dropoffs?.latest?.worst ?? [];
        },
        /** Breadth's one line (docs/retention-plan.md): three or more this week against last; not a need, never counted. */
        breadthText() {
            return breadthLine(this.breadth);
        },
        weakSurface() {
            return this.sharing ? weakest(this.sharing.surfaces, this.sharing.floor) : null;
        },
        /** The server lists the first 5: at 5, there may be more. */
        notSyncedTitle() {
            const n = this.notSynced.length;
            return n >= 5 ? t('admin.needs.notSynced.titleMany', { count: n }) : t('admin.needs.notSynced.title', { count: n });
        },
    },
    created() {
        if (!this.admin) return;
        // Each its own ask, each quiet when it fails: the card says what it has.
        api.admin.dropoffs().then(({ data }) => (this.dropoffs = data), (error) => console.warn('Drop-offs did not load', error));
        api.admin.sharing().then(({ data }) => (this.sharing = data), (error) => console.warn('Sharing did not load', error));
        api.admin.breadth().then(({ data }) => (this.breadth = data), (error) => console.warn('Breadth did not load', error));
    },
    methods: { navigate },
};

export const PartNeeds = {
    components: { ChangeSheet, NeedChips },
    props: { needs: { type: Object, required: true }, me: { type: Object, required: true }, partName: { type: String, default: '' } },
    emits: ['asked'],
    data: () => ({ busy: null, noting: null, note: '', newTake: null, newTakeNote: '' }),
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
                        <!-- An accepted take that's live: the same singer records it again (Hillel: "the singer wants to record again"). -->
                        <button v-else-if="liveTake(s) && isAdmin" type="button" class="btn-secondary btn-sm" :disabled="!!busy" data-ask-new-take @click="askNewTake(s)">{{ t('admin.newTake.button') }}</button>
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
            <ChangeSheet v-if="newTake" :title="t('admin.newTake.title', { name: newTake.singer.name, part: partName })" :me="me" :busy="!!busy" :reasoned="false" :needs-sudo="false" @close="newTake = null" @confirm="again(newTake, newTakeNote.trim(), true)">
                <p class="text-cream">{{ t('admin.newTake.body', { name: newTake.singer.name }) }}</p>
                <label class="block space-y-1.5"><span class="label">{{ t('admin.needs.noteLabel', { name: newTake.singer.name }) }}</span><textarea v-model="newTakeNote" class="input min-h-16 w-full" maxlength="500" rows="2" data-new-take-note></textarea></label>
            </ChangeSheet>
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
        /** A singer whose take of this version is accepted (live, or waiting to publish), not yet asked again. */
        liveTake: (s) => ['guide', 'accepted'].includes(s.status) && !s.previous && !s.askedAgainAt,
        askNewTake(s) {
            this.newTakeNote = t('admin.newTake.note');
            this.newTake = s;
        },
        /** Asks a singer for the part again: the server's note, or Hillel's. */
        async again(s, note = '', newTake = false) {
            this.busy = s.assignment;
            try {
                await api.admin.beta.assignments.again(s.assignment, { note });
                toast(newTake ? t('admin.newTake.done', { name: s.singer.name }) : t('admin.needs.asked', { name: s.singer.name }));
                this.noting = null;
                this.newTake = null;
                this.$emit('asked');
            } catch (error) {
                refused(error);
            } finally {
                this.busy = null;
            }
        },
    },
};
