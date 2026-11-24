// Hear yourself as a choir, the song's page (/t/<song>/choir; docs/own-choir-app-plan.md; js/own-choir.js). The terms
// once (English only: the short version, the terms, one button), then where the song stands: what happens and Start
// singing (into Sing, on a project made for it); on its way; ready (Listen, how much of it is really them, Learn your
// part, Delete); or what went wrong and Sing again. Stop making songs in my voice at the foot. Behind own_choir.

import { keepNext, plusPriceLine, PlusMoment } from '../components/plus-moment.js';
import { aiOn, aiUse } from '../ai-choice.js';
import { AiOffNotice } from '../components/ai-off.js';
import { loadPrices } from '../stores/plan.js';
import { api } from '../api.js';
import { mockWanted } from '../api-mode.js';
import { count } from '../usage.js';
import { local, session as tabStorage } from '../storage.js';
import { formatDate, formatNumber, locale, t, tk } from '../i18n.js';
import { siteHelpUrl } from '../hosts.js';
import { navigate } from '../router.js';
import { AppScreen } from '../components/page.js';
import { confirmAction } from '../components/confirm.js';
import { toast } from '../components/ui.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { deleteProject, loadProjects, projects, startFromTemplate } from '../stores/projects.js';
import { loadTemplate, partsFor, templatePartName } from '../templates.js';
import { songTitle } from '../song-titles.js';
import { Agreement } from './beta.js';
import { TurnstileBox } from '../turnstile.js';
import { ClipChoice, clipPick, clipTemplate, pickedClip, tooLong } from '../own-choir.js';
import { FirstNoteStep, HeadphonesPick, rememberedHeadphones } from '../components/first-note.js';
import { ChoirSettings } from '../components/choir-settings.js';
import { Segmented } from '../components/controls.js';
import { isPlus } from '../stores/plan.js';
import { inviteLine, inviteText, nameList, partList, togetherOn, whoSings, whoseList } from '../own-choir.js';
import { Icon } from '../components/icon.js';
import { ChoirGalleryRow } from '../components/choir-gallery-row.js';
import { galleryConfig } from '../choir-gallery.js';
import { openSession } from '../stores/session.js';
import { shareProject } from '../stores/sharing.js';
import { game as gameSkin } from '../stores/appearance.js';
import { checkKey, choirOf, choirRefusal, countChoir, downloadParts, emailNeeded, songsLeft, deleteEverything, deleteSong, dropHum, hasWords, humFor, isOpen, loadOwnChoir, makeFromVoice, markSeen, ownChoir, ownChoirOn, ownChoirSongsOn, requestFor, savedVoice, sendClip, termsNeeded, tunePart } from '../own-choir.js';
import { catalogue, loadCatalogue } from '../stores/catalogue.js';
import { loadMine, mine, ownTemplate } from '../stores/templates.js';
import { saveProject } from '../stores/projects.js';
import { CHOIR_FIX } from '../own-choir-state.js';
import { choirPosition, loadChoir, loadChoirFull, mixChoirShaped, playChoir, stopChoir } from '../own-choir-player.js';

// Why a clip couldn't make a voice, and the one fix (Hillel: "so they know why it failed, to fix it next time"; the
// server's `failureDetail`, owner-only).
const FAILURE_DETAILS = { quiet: tk('ownChoir.failed.detail.quiet'), noisy: tk('ownChoir.failed.detail.noisy'), notes: tk('ownChoir.failed.detail.notes'), short: tk('ownChoir.failed.detail.short'), loud: tk('ownChoir.failed.detail.loud'), edge: tk('ownChoir.failed.detail.edge'), length: tk('ownChoir.failed.detail.length') };
// The quota is said only when it matters (marketing's rule): under about this many songs, or minutes with no estimate.
const FEW_SONGS = 10;
const FEW_MINUTES = 10;
// The take's own problems (the worker's ref_prep, before any voice is saved or any GPU time spent): Sing again, with its fix.
const TAKE_PROBLEMS = ['quiet', 'loud', 'noisy', 'notes', 'edge', 'short'];
// A run of failures of one kind (take, ours) in a row on this device, by request: the second asks for the next fix.
const FAIL_RUN = 'stackapella.choirFailRun';
function failRunOf(id, kind) {
    let run = null;
    try {
        run = JSON.parse(local.get(FAIL_RUN) || 'null');
    } catch {
        run = null;
    }
    if (!id) return run;
    const ids = run?.kind === kind ? run.ids.filter((x) => x !== id) : [];
    const next = { kind, ids: [...ids, id].slice(-5) };
    local.set(FAIL_RUN, JSON.stringify(next));
    return next;
}
// Noise with the server's hints (failureHints, worked out from the worker's measures; the app never sees the numbers):
// sung quietly beside the room, the easier fix first (sing louder or closer); close to passing, a steady hum like a fan.
const NOISY_HINTS = { quietSinger: tk('ownChoir.failed.detail.noisyQuiet'), near: tk('ownChoir.failed.detail.noisyNear') };
// The notes with no reference pitch (the server's `noReference`: the take sat between keys or drifted, ai-modal's rule):
// sing along with the guide, which gives the starting note. Its key is also the next take's fix (views/sing.js guideFix).
export const NOTES_GUIDE = tk('ownChoir.failed.detail.notesGuide');
// The second take problem in a row: the next fix, headphones if they sang without, else another tune.
// The failure's title, by kind (Hillel's choir error design, 2026-11-24).
const FAIL_TITLES = { take: tk('ownChoir.failed.title.take'), gone: tk('ownChoir.failed.title.gone'), ours: tk('ownChoir.failed.title.ours') };
const NEXT_FIX = { headphones: tk('ownChoir.failed.next.headphones'), tune: tk('ownChoir.failed.next.tune') };
const FAILURES = { clip_unusable: tk('ownChoir.failed.clip_unusable'), bad_clip: tk('ownChoir.failed.bad_clip'), gates_failed: tk('ownChoir.failed.gates_failed'), render_failed: tk('ownChoir.failed.render_failed'), expired: tk('ownChoir.failed.expired'), reference_gone: tk('ownChoir.failed.reference_gone') };

export const OwnChoirView = {
    components: { AiOffNotice, ChoirGalleryRow, Segmented, Agreement, AppScreen, ChoirSettings, ClipChoice, FirstNoteStep, HeadphonesPick, Icon, PlusMoment, TurnstileBox },
    props: { id: { type: String, required: true } },
    data: () => ({ failRun: null, retried: false, useEarlier: false, resing: false, listenTo: 'choir', plusSettings: false, shift: null, hearingClip: false, firstNote: null, leftNames: [], plusFriends: false, sentInvite: null, copied: false, plusFor: false, emailSent: false, sending: false, checkToken: null, checkRound: 0, state: 'loading', offReason: 'unavailable', canStart: false, template: null, terms: null, busy: false, playing: false, loadingTrack: false, hum: null, own: false }),
    setup: () => ({ rememberedHeadphones, isPlus, ownChoir, account, isOpen, templatePartName, savedVoice, formatNumber, navigate, aiOn, galleryConfig }),
    computed: {
        /** Back here from the Plus page, once Plus is on. */
        here: () => location.pathname,
        song() {
            return this.template ? songTitle(this.template) : '';
        },
        request() {
            return requestFor(this.id);
        },
        /** Their own recording (the clip their saved voice was made from), while the server keeps it; the owner's own only. */
        clipUrl() {
            return ownChoir.data?.voice?.clipListen?.url ?? null;
        },
        /** The help page for Hear yourself as a choir, on the public site, in the app's language. */
        helpUrl: () => siteHelpUrl('your-choir', locale.lang),
        /** The one-column header: the start, and the loading, sign-in or terms before it. */
        columnHead() {
            return this.state !== 'ready' || this.intro;
        },
        /** The start: nothing made yet, or it failed (not on its way, not ready). */
        intro() {
            return (this.request?.status !== 'ready' && !isOpen(this.request)) || !!this.retryPhase;
        },
        /**
         * After Try again on our failure (Hillel's choir error design, 2026-11-24): `building` while the new song is made,
         * `done` once it's ready, until Listen; on this page only (a reload shows the usual wait or the song).
         */
        retryPhase() {
            if (!this.retried || !this.request) return null;
            if (isOpen(this.request)) return 'building';
            return this.request.status === 'ready' ? 'done' : null;
        },
        /** Hillel's choir error design: our failure with the voice saved, and the try again after it. */
        oursScreen() {
            return !!this.retryPhase || (this.failKind === 'oursKept' && !this.resing);
        },
        /** The page's title: the failure's own (the design), Trying again, or the usual. */
        pageTitle() {
            if (this.retryPhase === 'building') return t('ownChoir.failed.retrying.title');
            if (this.retryPhase || !this.failKind || this.resing) return t('ownChoir.title');
            return t(FAIL_TITLES[this.failKind] ?? FAIL_TITLES.ours);
        },
        /** The line under the title: what happened and what to do (no alert box: the design). */
        pageLead() {
            if (this.retryPhase === 'building') return t('ownChoir.failed.retrying.body');
            if (this.retryPhase === 'done') return t('ownChoir.failed.worked.body');
            if (!this.failKind || this.resing) return '';
            return this.failKind === 'oursKept' ? t('ownChoir.failed.why') : this.failure;
        },
        /** While it's being made: each part's real state from the server (ready, making, waiting); never an estimate. */
        progress() {
            const keys = partsFor(this.template ?? {}).map((p) => p.key);
            const rows = this.request?.progress ?? [];
            return [...rows].sort((a, b) => keys.indexOf(a.partKey) - keys.indexOf(b.partKey));
        },
        progressReady() {
            return this.progress.filter((p) => p.status === 'ready').length;
        },
        /** The choir project for this song's request, else one still waiting for its take, if this device has it. */
        project() {
            const mine = projects.all.filter((p) => p.choir && p.template?.id === this.id && !p.deleted);
            return mine.find((p) => this.request && p.choir.requestId === this.request.id) ?? mine.find((p) => !p.choir.requestId) ?? null;
        },
        /** The song's parts, and how many the singer has really sung in the choir project. */
        parts() {
            return this.template ? partsFor(this.template, this.project?.singers).filter((p) => p.kind !== 'instrument') : [];
        },
        realCount() {
            const sung = new Set(this.project?.yourParts ?? []);
            return this.parts.filter((p) => sung.has(p.key)).length;
        },
        /** The part to learn next: the first not yet really theirs. */
        nextPart() {
            const sung = new Set(this.project?.yourParts ?? []);
            const open = this.parts.filter((p) => !sung.has(p.key));
            // With friends: the owner's best fit first (a part that's theirs and sits neither high nor low for them).
            const voice = (p) => (this.request?.voices ?? []).find((v) => v.partKey === p.key);
            return (this.together && open.find((p) => voice(p)?.whose !== 'friend' && !voice(p)?.fit)) || open[0] || null;
        },
        /**
         * The quota, said only when it matters (marketing's rule): the free one, a few left, or none. Hillel's start design
         * keeps it short in the footer, its minutes in bold: `lead` (bold, may be empty) then `rest`.
         */
        quotaLine() {
            const q = ownChoir.data?.quota;
            if (!q || this.failKind) return null; // a failure's alert says what it cost
            // The free offer; it names the song when it isn't this page's (the song they sing).
            if (!q.plus && q.freeLeft > 0) return { lead: '', rest: this.freeSongId && this.freeSongId !== this.id ? t('ownChoir.free.yourSong', { song: this.clipTitle }) : t('ownChoir.limit.free') };
            // Plus counts singing minutes (part-minutes); the songs are an estimate for this song, so "about". `resetsAt`
            // is when the subscription's month turns and the full allowance comes back.
            if (q.unit === 'minutes' && q.minutesLeft != null) {
                const songs = songsLeft(q, this.template);
                const minutes = formatNumber(Math.max(0, Math.floor(q.minutesLeft)));
                const date = q.resetsAt ? formatDate(q.resetsAt, { day: 'numeric', month: 'long' }) : '';
                const lead = t('ownChoir.limit.shortLead', { minutes });
                if (songs == null ? q.minutesLeft >= FEW_MINUTES : songs > FEW_SONGS) return null; // only when it matters
                if (songs == null) return date ? { lead, rest: t('ownChoir.limit.shortResets', { date }) } : { lead: '', rest: t('ownChoir.limit.minutesOnly', { minutes }) };
                return { lead, rest: date ? t('ownChoir.limit.shortSongs', { songs, date }) : t('ownChoir.limit.shortSongsNoDate', { songs }) };
            }
            if (q.plus && q.monthLeft > 0 && q.monthLeft <= 3) return { lead: '', rest: t('ownChoir.limit.left', { n: q.monthLeft }) };
            return null;
        },
        /** Step 1 with a saved voice (not singing again): Make my choir is the one action. */
        savedStep() {
            return !!savedVoice() && !this.resing && (!this.failSings || this.useEarlier);
        },
        /**
         * What went wrong, by what to do next (the coordinator's failure spec, for Hillel: "the main button most likely won't
         * work the second time"): `take` (the recording's own problem: sing again, its fix said), `oursKept` (ours, the
         * voice saved: Try again), `oursLost` (ours, nothing kept: sing again) or `gone` (the saved voice went first).
         */
        failKind() {
            if (this.request?.status !== 'failed') return null;
            const { failure, failureDetail: detail } = this.request;
            if (failure === 'reference_gone') return 'gone';
            if (failure === 'clip_unusable' && detail !== 'length') return 'take';
            if ((failure === 'render_failed' || failure === 'gates_failed') && savedVoice()) return 'oursKept';
            return 'oursLost';
        },
        /** Singing is the way on after this failure (all but ours with the voice saved). */
        failSings() {
            return !!this.failKind && this.failKind !== 'oursKept';
        },
        /** The second failure of its kind in a row on this device. */
        failRepeat() {
            const kind = this.failKind === 'take' ? 'take' : 'ours';
            return !!this.failKind && this.failRun?.kind === kind && this.failRun.ids.length >= 2 && this.failRun.ids.at(-1) === this.request.id;
        },
        /** An older, working voice to use instead of singing again (a take problem never saved this one). */
        earlierShown() {
            return this.failKind === 'take' && !!savedVoice();
        },
        /** Contact us, from this page (the second take problem in a row). */
        contactHref() {
            return `/contact?from=${encodeURIComponent(`/t/${this.id}/choir`)}`;
        },
        /** When the earlier voice was saved, said as a date. */
        earlierDate() {
            const at = savedVoice()?.savedAt;
            return at ? formatDate(at, { day: 'numeric', month: 'long' }) : '';
        },
        /**
         * "It didn't count", only when it didn't: the server's `counted` (false when given back: before any GPU work, or ours,
         * paid Plus minutes too since 2026-11-23); the free song's words for a free singer, the minutes' for Plus.
         */
        costLine() {
            const q = ownChoir.data?.quota;
            if (!this.failKind || !q || this.request.counted !== false) return '';
            return q.plus ? t('ownChoir.failed.cost.minutes') : t('ownChoir.failed.cost.free');
        },
        /** Step 1's choices (the tune, headphones) and Start singing: singing is the way and nothing stops it. */
        singing() {
            return this.canStart && !this.songTooLong && !this.needEmail && !this.freeHad && !this.savedStep && !(this.hum && !this.resing);
        },
        /** The song the choir is made of: the free song is the one they sing (Hillel), else this page's. */
        choirSong() {
            return this.freeSongId && this.freeSongId !== this.id ? this.clipTitle : this.song;
        },
        /** A song the server won't make (more than 8 parts, or a part over 5 minutes). */
        songTooLong() {
            return tooLong(this.template);
        },
        /** A choir with friends is on for this account. */
        // On for the account, or friends already in this song: removing one must always work (the security review).
        together() {
            return togetherOn() || (this.request?.members ?? []).length > 0;
        },
        game: () => gameSkin(),
        /** Who sings each part (the owner's view), their real takes from the choir project. */
        who() {
            return whoSings(this.request, this.parts, this.project?.yourParts ?? []);
        },
        /** The panel's label: the full AI label once, with the friends whose parts are in ("...your singing and Ben's"). */
        panelLabel() {
            const named = this.together ? this.friends.filter((m) => m.status === 'ready').map((m) => m.name) : [];
            return named.length ? t('ownChoir.together.listen.withFriends', { names: whoseList(named) }) : t('ownChoir.listen.label');
        },
        inviteWhy() {
            const keys = this.request?.invite?.parts ?? [];
            return t('ownChoir.together.invite.why', { parts: partList(keys, this.template), partCount: keys.length });
        },
        /** The Plus sheet past the free song's one friend: who's in it already. */
        plusFriendsText() {
            const friend = this.friends[0];
            return friend ? t('plus.moment.choirFriendsUsed', { name: friend.name }) : t('plus.moment.choirFriends');
        },
        friends() {
            return this.request?.members ?? [];
        },
        /** The invite line: while there's a part to give (or Plus would allow one); never when every part fits or it's full. */
        inviteShown() {
            const invite = this.request?.invite;
            return !!invite && (invite.canInvite || invite.reason === 'plus_required');
        },
        inviteLabel() {
            return inviteLine(this.request, this.template);
        },
        /** A friend's parts waiting for the owner's minutes: how many more, and when they come back. */
        minutesLine() {
            const waiting = this.friends.find((m) => m.status === 'waiting_minutes');
            const resets = ownChoir.data?.quota?.resetsAt;
            if (!waiting || waiting.minutesShort == null || !resets) return '';
            const keys = waiting.parts ?? [];
            return t('ownChoir.together.who.minutesNote', { name: waiting.name, parts: partList(keys, this.template), partCount: keys.length, count: Math.max(1, Math.ceil(waiting.minutesShort)), date: formatDate(resets, { day: 'numeric', month: 'long' }) });
        },
        /** Their free song is used (once per email, ever) and no Plus: said before any singing, with Get Plus. */
        freeHad() {
            const q = ownChoir.data?.quota;
            return !!q && !q.plus && q.freeLeft === 0;
        },
        /** What Plus costs, for the free song's card (once the prices load). */
        priceLine: () => plusPriceLine(),
        /** The free song waits for a confirmed email (asked before singing). */
        needEmail: () => emailNeeded(ownChoir.data?.quota),
        /** The check before the account's first song, when the server asks for it and a site key is set. */
        siteKey: () => checkKey(),
        /** Their saved voice came from humming and this song has words: singing one line with words would make it clearer. */
        wordlessNote() {
            return !!savedVoice()?.wordless && hasWords(this.template);
        },
        /**
         * The model sings English: a song with words in another language is said once, never blocked (ai-modal's finding).
         * Not when the song carries a sung respelling (Hillel approved them for Hebrew and Yiddish): the AI sings its real words.
         */
        notEnglish() {
            const language = this.template?.language ?? 'en';
            const respelled = Object.keys(this.template?.sungRespelling ?? {}).length > 0;
            return hasWords(this.template) && !language.toLowerCase().startsWith('en') && !respelled;
        },
        /** What there is to listen to: their recording (while kept), them with the choir (a whole-song take), the full choir. */
        viewOptions() {
            const options = [];
            if (this.clipUrl) options.push({ value: 'recording', label: t('ownChoir.version.recording') });
            if (this.request?.leadTake) options.push({ value: 'withChoir', label: t('ownChoir.version.youChoir') });
            options.push({ value: 'choir', label: t('ownChoir.version.fullChoir') });
            return options;
        },
        /**
         * Stop making songs from my singing: not on a first visit (the coordinator's review: nothing to stop yet), but
         * once they've had a choir or a voice (remembered on this device, so it stays after a song is deleted).
         */
        stopShown() {
            const had = !!this.request || savedVoice() || (ownChoir.data?.requests?.length ?? 0) > 0;
            if (had) local.set('stackapella.choirHad', '1');
            return had || local.get('stackapella.choirHad') === '1';
        },
        /** A free singer's choir song: the one their clip tune makes (`clipSongs[].songId`), or null with Plus. */
        freeSongId() {
            const q = ownChoir.data?.quota;
            if (!q || q.plus || !(q.freeLeft > 0)) return null;
            const choice = pickedClip();
            return choice.songId ?? (choice.templateId === 'voice-capture' ? 'happy-birthday' : choice.templateId);
        },
        clipTitle() {
            return clipPick.titles[pickedClip().templateId] ?? '';
        },
        /** What the page shows now, for its counts: set once it's open (not while loading, off or asking the terms). */
        seenKey() {
            return this.state === 'ready' ? `${this.id}:${this.request?.id ?? 'none'}:${this.request?.status ?? 'none'}` : null;
        },
        /** A take problem's one fix (the next fix the second time), as its words' key: said here, and over the next count-in. */
        fixKey() {
            if (this.failKind !== 'take') return null;
            if (this.failRepeat) return rememberedHeadphones() === 'no' ? NEXT_FIX.headphones : NEXT_FIX.tune;
            const hints = this.request.failureHints ?? [];
            if (this.request.failureDetail === 'noisy') {
                const hint = ['quietSinger', 'near'].find((h) => hints.includes(h));
                if (hint) return NOISY_HINTS[hint];
            }
            if (this.request.failureDetail === 'notes' && hints.includes('noReference')) return NOTES_GUIDE;
            return FAILURE_DETAILS[this.request.failureDetail] ?? FAILURES.clip_unusable;
        },
        /** The alert's one line: the fix for this take (the next fix the second time), or what happens now. */
        failure() {
            if (this.failKind === 'take') return t(this.fixKey);
            if (this.failKind === 'gone') return t(FAILURES.reference_gone);
            if (this.failRepeat) return t('ownChoir.failed.ours.twice');
            return this.failKind === 'oursKept' ? t('ownChoir.failed.step.body') : t('ownChoir.failed.ours.lost');
        },
    },
    template: `
        <!-- The start (and what comes before it) in Hillel's one 520 px column, its header on that column too: the song
             over the title. A song on its way or ready keeps the app's bar. -->
        <AppScreen :title="pageTitle" :back="'/?song=' + encodeURIComponent(id)" :column="columnHead && !firstNote ? 'max-w-[520px]' : null" :eyebrow="columnHead && !firstNote ? song || null : null" eyebrow-is-content :measure="columnHead ? 'max-w-[520px]' : 'max-w-2xl'" :compact="state === 'ready' && !columnHead && !!request && (request.status === 'ready' || isOpen(request))">
            <p v-if="state === 'loading'" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <!-- Why it can't open, each in its own words: signed out (sign in), offline (Try again), or not open to them. -->
            <AiOffNotice v-else-if="state === 'off' && offReason === 'aiOff'" data-choir-off="aiOff" @on="load" />
            <div v-else-if="state === 'off'" class="card space-y-3 p-5" :data-choir-off="offReason">
                <p class="text-cream">{{ offReason === 'signedOut' ? t('ownChoir.off.signedOut') : offReason === 'offline' ? t('common.offline') : t('ownChoir.off.unavailable') }}</p>
                <a v-if="offReason === 'signedOut'" :href="'/signin?next=' + encodeURIComponent('/t/' + id + '/choir')" class="btn-secondary self-start" @click.prevent="navigate('/signin?next=' + encodeURIComponent('/t/' + id + '/choir'))">{{ t('auth.signIn') }}</a>
                <button v-else-if="offReason === 'offline'" type="button" class="btn-secondary" @click="load">{{ t('common.tryAgain') }}</button>
            </div>

            <!-- The terms, once: the short version, the terms, one button (English only, as the AI licence). -->
            <section v-else-if="state === 'terms' && terms" class="space-y-5" data-choir-terms>
                <!-- The coordinator's review: no heading repeating the bar's title, no box scrolling inside the page, one wide button. -->
                <h1 v-if="terms.title !== t('ownChoir.title')" class="t-title text-cream" translate="no">{{ terms.title }}</h1>
                <div class="card p-5" role="region" :aria-label="terms.title"><Agreement :agreement="terms" :level="2" /></div>
                <button type="button" class="btn-primary w-full" :disabled="busy" data-choir-agree @click="agree">{{ t('ownChoir.terms.agree') }}</button>
            </section>

            <!-- The start (nothing yet, or it failed) follows Hillel's intro design to its values: a 600 px column, the
                 eyebrow 12 px over a big heading, the stepper, one card for the headphones and the actions, a quiet footer. -->
            <section v-else :class="intro ? 'flex flex-col gap-7 pt-7' : 'space-y-5'" data-choir-page :data-choir-state="request?.status ?? 'none'">
                <p v-if="!intro && request?.status !== 'ready'" class="eyebrow" translate="no">{{ song }}</p>
                <!-- Ready: the finished track first (Hillel: "some people will just want a finished track"): play it here and
                     download it, labelled AI; learning a part is one quiet option, never the main button. -->
<!-- Our failure with the voice saved, and trying again (Hillel's choir error design, 2026-11-24): the title and the line
                     under it, then two steps: the voice saved, and the choir (didn't come out with Try again, being made, or ready). -->
                <template v-if="oursScreen">
                    <!-- Said under its title (Hillel's choir error design, 2026-11-24): what happened, then what it cost only
                         where we know it didn't count. No alert box. -->
                    <div v-if="pageLead" class="-mt-4 flex flex-col gap-1" :role="failKind && !retryPhase ? 'alert' : 'status'" :data-choir-failed="failKind && !retryPhase ? '' : null" :data-choir-failed-reason="failKind && !retryPhase ? (request.failureDetail || request.failure || null) : null" :data-choir-fail-kind="retryPhase ? null : failKind" :data-choir-fail-repeat="failRepeat && !retryPhase ? '' : null" :data-choir-retry="retryPhase">
                        <p class="text-[18px] leading-normal text-muted [text-wrap:pretty]"><span data-choir-fail-fix>{{ pageLead }}</span><template v-if="costLine && !retryPhase">{{ ' ' }}<span data-choir-fail-cost>{{ costLine }}</span></template></p>
                        <a v-if="failKind && !oursScreen" :href="helpUrl + '#if-a-song-doesnt-come-out'" target="_blank" rel="noopener" class="link mt-2 inline-flex min-h-11 items-center self-start" data-choir-help-failed>{{ t('ownChoir.help.failed') }}</a>
                    </div>
                    <ol class="mt-4" data-choir-steps data-choir-ours>
                        <li class="grid grid-cols-[44px_minmax(0,1fr)] gap-x-4" data-choir-step="1" data-choir-step-state="saved">
                            <span class="flex flex-col items-center" aria-hidden="true">
                                <span :class="bigDot('done')" data-ui="step-dot"><Icon name="check" :size="20" /></span>
                                <span class="my-1.5 w-px flex-1 bg-line"></span>
                            </span>
                            <div class="min-w-0 pb-9">
                                <h2 class="text-[22px] font-semibold leading-[44px] text-cream">{{ t('ownChoir.voice.savedTitle') }}</h2>
                                <p class="text-base leading-normal text-muted" data-step-body>{{ t('ownChoir.failed.savedBody') }}</p>
                            </div>
                        </li>
                        <li class="grid grid-cols-[44px_minmax(0,1fr)] gap-x-4" data-choir-step="2" :data-choir-step-state="retryPhase || 'failed'">
                            <span class="flex flex-col items-center" aria-hidden="true">
                                <span v-if="retryPhase === 'building'" class="grid size-11 place-items-center" data-ui="step-dot"><span class="size-10 animate-spin rounded-full border-2 border-line border-t-accent motion-reduce:[animation-duration:2.4s]"></span></span>
                                <span v-else-if="retryPhase === 'done'" :class="bigDot('done')" data-ui="step-dot"><Icon name="check" :size="20" /></span>
                                <span v-else :class="bigDot('warn')" data-ui="step-dot">!</span>
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-[22px] font-semibold leading-[44px] text-cream">{{ retryPhase === 'building' ? t('ownChoir.failed.retrying.step') : retryPhase === 'done' ? t('ownChoir.failed.worked.step') : t('ownChoir.failed.step.title') }}</h2>
                                <p class="text-base leading-normal text-muted [text-wrap:pretty]" data-step-body>{{ retryPhase ? t('ownChoir.intro.buildBody', { song: choirSong }) : failure }}</p>
                                <div v-if="retryPhase === 'done'" class="mt-5 flex">
                                    <button type="button" class="btn-primary h-[60px] px-[30px] text-[18px] font-semibold" data-choir-listen @click="listen">{{ t('ownChoir.failed.worked.listen') }}</button>
                                </div>
                                <!-- Ours twice in a row: back to the song is the way on, once more the other (the coordinator's spec). -->
                                <div v-else-if="!retryPhase && failRepeat" class="mt-5 flex flex-wrap items-center gap-3">
                                    <button type="button" class="btn-primary h-[60px] px-[30px] text-[18px] font-semibold max-sm:w-full" data-choir-back @click="backToSong">{{ t('ownChoir.failed.back', { song }) }}</button>
                                    <button type="button" class="btn-secondary h-[60px] px-[26px] text-[17px] font-medium max-sm:w-full" :disabled="busy" data-choir-again @click="act('retry', retry)">{{ t('ownChoir.failed.onceMore') }}</button>
                                </div>
                                <div v-else-if="!retryPhase" class="mt-5 flex flex-wrap items-center gap-3">
                                    <button type="button" class="btn-primary h-[60px] px-[30px] text-[18px] font-semibold max-sm:w-full" :disabled="busy" :aria-busy="busy ? 'true' : null" data-choir-again @click="act('retry', retry)">{{ t('common.tryAgain') }}</button>
                                    <button type="button" class="btn-secondary h-[60px] px-[26px] text-[17px] font-medium max-sm:w-full" :disabled="busy" data-choir-resing @click="resing = true">{{ t('ownChoir.voice.record') }}</button>
                                </div>
                                <a v-if="!retryPhase" :href="helpUrl + '#if-a-song-doesnt-come-out'" target="_blank" rel="noopener" class="mt-3 inline-flex min-h-11 items-center self-start text-base text-muted underline decoration-accent underline-offset-[5px] hover:text-cream" data-choir-help-failed>{{ t('ownChoir.help.failed') }}</a>
                            </div>
                        </li>
                    </ol>
                </template>
                <template v-else-if="request?.status === 'ready'">
                    <p v-for="name in leftNames" :key="name" class="card p-4 text-cream" role="status" data-choir-left>{{ t('ownChoir.together.friendLeft', { name, song }) }}</p>
                    <h1 class="t-title text-cream">{{ t('ownChoir.listen.title', { song }) }} <span class="chip-tag align-middle" data-ai-voice="tag">{{ t('ownChoir.together.who.ai') }}</span></h1>
                    <div class="card space-y-4 p-5" data-choir-track>
                        <!-- The AI label once (the coordinator, 2026-11-23): the H1 says "in a voice like yours"; this line only when
                             friends' voices join (it names whose). -->
                        <p v-if="together && (request.members ?? []).length" class="t-hint" data-choir-label>{{ panelLabel }}</p>
                        <!-- A low voice's arrangement (the server's, from their range): said once, quietly. -->
                        <p v-if="request.voicing === 'low'" class="t-hint" data-choir-low-voice>{{ t('ownChoir.ready.lowVoice') }}</p>
                        <!-- Words not in English: the AI parts are sung on "oo" (ai-modal, via Hillel), from the song's language. -->
                        <p v-if="notEnglish" class="t-hint" data-choir-on-oo>{{ t('ownChoir.ready.onOo') }}</p>
                        <!-- Hillel: "compare your original, to you with choir around, or full choir" (for everyone). -->
                        <Segmented v-if="viewOptions.length > 1" :model-value="listenTo" :options="viewOptions" :label="t('ownChoir.version.label')" full class="overflow-x-auto [scrollbar-width:none] [&_.seg-option]:min-h-11 [&_.seg-option]:shrink-0" data-scroll-row data-listen-to @update:model-value="chooseView" />
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <button type="button" class="btn-primary btn-lg justify-center" :aria-busy="loadingTrack ? 'true' : null" data-choir-play @click="togglePlay"><span v-if="loadingTrack" class="spinner" aria-hidden="true"></span>{{ playing || hearingClip ? t('studio.stop') : t('ownChoir.ready.button') }}</button>
                            <button type="button" class="btn-secondary btn-lg justify-center" :disabled="loadingTrack" data-choir-download @click="download">{{ t('ownChoir.ready.download') }}<span v-if="!request.canDownload" class="chip-accent ms-2 text-xs" data-download-plus>{{ t('ownChoir.settings.plusBadge') }}</span></button>
                        </div>
                        <!-- Share (the share page credits each part as AI made from their singing): the studio's share sheet. -->
                        <button type="button" class="btn-secondary w-full justify-center" :disabled="busy" data-choir-share @click="share">{{ t('ownChoir.share.button') }}</button>
                        <p v-if="listenTo === 'recording'" class="t-hint" data-share-full>{{ t('ownChoir.share.fullOnly') }}</p>
                    </div>
                    <!-- The choir gallery (docs/choir-gallery-plan.md, behind choir_gallery): one quiet row, heard by staff first. -->
                    <ChoirGalleryRow :request="request" :song="song" :consent-version="galleryConfig(account.config)?.consentVersion ?? null" :ai="aiOn()" :share="galleryShare" @changed="(item) => (request.gallery = item)" />
                    <!-- Downloading is Plus's (Hillel, 2026-11-21); listening and sharing stay free. The server says who may. -->
                    <PlusMoment v-if="plusFor" :title="t('ownChoir.ready.download')" :text="t('plus.moment.choirDownload')" from="choir_download" :next="here" price @close="plusFor = false" />
                    <!-- Choir settings (the coordinator for Hillel): Sound at once, How it's sung by a remake; Plus's, shown to all. -->
                    <ChoirSettings :request="request" :template="template" :plus="isPlus()" :busy="busy" :minutes-left="ownChoir.data?.quota?.minutesLeft ?? null" :monthly="ownChoir.data?.quota?.minutesPerMonth ?? null" :has-lead="!!request.leadTake" :has-recording="ownChoir.data?.voice?.clipListen?.templateId === request.templateId" @sound="saveSound" @remake="remake" @plus="plusSettings = true" />
                    <PlusMoment v-if="plusSettings" :title="t('ownChoir.settings.title')" :text="t('plus.moment.choirSettings')" from="choir_settings" :next="here" price @close="plusSettings = false" />
                    <!-- A choir with friends (behind own_choir_together): the "really you" line becomes Who sings, one row a part, each
                         with its AI label (or their real take's), the parts that sit high or low for them, and one invite line. -->
                    <section v-if="together" class="space-y-2" data-choir-who aria-labelledby="choir-who-h">
                        <h2 id="choir-who-h" class="t-section">{{ t('ownChoir.together.who.title') }}</h2>
                        <ul class="card divide-y divide-line px-4" data-ui="who-sings">
                            <li v-for="row in who" :key="row.part.key" class="flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 py-2" :data-who-part="row.part.key" :data-who-friend="row.friend ? row.friend.id : null">
                                <span :class="game ? 'chip w-28 shrink-0 justify-center' : 'w-28 shrink-0 font-semibold text-cream'" data-ui="part-chip">{{ templatePartName(row.part) }}</span>
                                <!-- The name and its AI chip never part (a narrow row moves Remove under them instead). -->
                                <span class="flex-1 text-sm" :class="game && row.friend ? 'text-part-2' : 'text-cream'">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="inline-flex items-center gap-2 whitespace-nowrap"><span>{{ row.label }}</span><span v-if="row.ai" class="chip-tag" data-ai-voice="tag">{{ t('ownChoir.together.who.ai') }}</span></span>
                                        <span v-if="row.note" class="text-muted" data-who-fit>{{ row.note }}</span>
                                    </span>
                                    <span v-if="row.sub" class="t-hint block" data-who-until>{{ row.sub }}</span>
                                </span>
                                <button v-if="row.friend" type="button" class="ms-auto" :class="game ? 'inline-flex min-h-11 items-center rounded-full border border-line px-4 text-sm text-cream' : 'btn-secondary btn-sm min-h-11'" :aria-label="t('ownChoir.together.remove.button', { name: row.friend.name })" data-who-remove @click="removeFriend(row.friend)">{{ t('ownChoir.together.remove.short') }}</button>
                            </li>
                        </ul>
                        <p v-if="minutesLine" class="mt-4 text-sm text-cream" role="status" data-choir-minutes>{{ minutesLine }}</p>
                        <template v-if="inviteShown && !sentInvite">
                            <button type="button" class="link inline-flex min-h-11 items-center gap-1.5 font-semibold" :disabled="busy" data-choir-invite @click="invite">{{ inviteLabel }}</button>
                            <p class="text-sm text-muted" data-choir-invite-why>{{ inviteWhy }}</p>
                        </template>
                        <!-- The link, once (it works once, for 14 days): read-only with its lock, Copy link, or cancel. -->
                        <div v-if="sentInvite" class="space-y-2" data-choir-invite-sent>
                            <p class="flex min-h-11 items-center text-sm text-cream" role="status">{{ t('ownChoir.together.invite.sent') }}</p>
                            <div class="flex gap-2">
                                <div class="relative min-w-0 flex-1"><input ref="inviteLink" :value="sentInvite.url" readonly class="input truncate pe-[46px] text-start" :aria-label="inviteLabel" @focus="$event.target.select()" /><Icon name="lock" :size="18" class="input-icon end-4" /></div>
                                <button type="button" class="btn-secondary" data-choir-invite-copy @click="copyInvite">{{ t('ownChoir.together.invite.copy') }}</button>
                            </div>
                            <!-- Said here, by the link (a toast would sit over the song's heading). -->
                            <p v-if="copied" class="text-sm text-muted" data-choir-invite-copied>{{ t('ownChoir.together.invite.copied') }}</p>
                            <button type="button" class="link inline-flex min-h-11 items-center text-sm" data-choir-invite-cancel @click="cancelInvite">{{ t('ownChoir.together.invite.cancel') }}</button>
                        </div>
                        <PlusMoment v-if="plusFriends" :title="inviteLabel" :text="plusFriendsText" from="choir_friends" :next="here" price @close="plusFriends = false" />
                    </section>
                    <p class="text-sm text-muted" data-choir-learn><span v-if="!together" data-choir-progress>{{ t('ownChoir.learn.progress', { n: realCount, total: parts.length }) }}</span><template v-if="nextPart"><template v-if="!together"> · </template><button type="button" class="link inline-flex min-h-11 items-center font-semibold" :disabled="busy" data-choir-learn-part @click="learn(nextPart)">{{ t('ownChoir.learn.button', { part: lower(templatePartName(nextPart)) }) }}</button></template></p>
                    <button type="button" class="link-quiet inline-flex min-h-11 items-center self-start text-sm text-bad" data-choir-delete @click="remove">{{ t('ownChoir.delete.button') }}</button>
                </template>
                <!-- On its way: in-app only, never email. -->
                <div v-else-if="isOpen(request)" class="card space-y-2 p-5" role="status" data-choir-wait>
                    <h1 class="t-title text-cream">{{ t('ownChoir.wait.title') }}</h1>
                    <p class="text-cream">{{ t('ownChoir.wait.body', { song }) }}</p>
                    <!-- Hillel's 1b: each part's real state, and how many are ready (game: the rows as part chips). -->
                    <div v-if="progress.length" class="space-y-2 pt-1" data-choir-parts>
                        <ul :class="game ? 'flex flex-wrap gap-2' : 'divide-y divide-line'" class="pb-1">
                            <li v-for="p in progress" :key="p.partKey" :class="game ? 'chip gap-2' : 'flex min-h-11 items-center justify-between gap-3 py-1'" :data-choir-part="p.partKey" :data-choir-part-status="p.status">
                                <span :class="game ? '' : 'font-semibold text-cream'">{{ partLabel(p.partKey) }}</span>
                                <span class="inline-flex items-center gap-2" :class="game ? '' : 'text-sm'">
                                    <span class="size-2.5 shrink-0 rounded-full" :class="dot(p.status)" aria-hidden="true"></span>
                                    <span :class="p.status === 'waiting' ? 'text-muted' : 'text-cream'">{{ statusWord(p.status) }}</span>
                                </span>
                            </li>
                        </ul>
                        <!-- The take review's quiet footer (docs/design-rules.md, Reference layout): the count under a thin line. -->
                        <p class="border-t border-line pt-3 text-sm text-muted tabular-nums" data-choir-parts-count>{{ t('ownChoir.wait.partsReady', { n: progressReady, count: progress.length }) }}</p>
                    </div>
                    <!-- Today's making is full (the server's daily ceiling): it's queued for tomorrow, said plainly. -->
                    <p v-if="request.startsAfter" class="text-cream" data-choir-tomorrow>{{ t('ownChoir.busy.tomorrow') }}</p>
                    <p class="text-sm text-muted">{{ t('ownChoir.wait.kept') }}</p>
                </div>
                <!-- Nothing yet, or it failed: Hillel's start design (2026-11-22). A 520 px column, 28 px apart: the alert when
                     the last song failed, saying how to fix it; two steps, the first holding the choices and the ONE main
                     action (Start singing, or with a saved voice Make my choir); the quota and Stop in a quiet footer. -->
                <template v-else>
                    <!-- After Start singing: the first note they'd like (Hillel), in place of the steps; then Sing, in that key. -->
                    <FirstNoteStep v-if="firstNote" :first="firstNote.pitch" :song="firstNote.song" @go="record($event, rememberedHeadphones() === 'no')" />
                    <template v-else>
                    <!-- The failure said under its title (Hillel's choir error design, 2026-11-24): what happened, then what it cost only
                         where we know it didn't count. No alert box. -->
                    <div v-if="pageLead" class="-mt-4 flex flex-col gap-1" :role="failKind && !retryPhase ? 'alert' : 'status'" :data-choir-failed="failKind && !retryPhase ? '' : null" :data-choir-failed-reason="failKind && !retryPhase ? (request.failureDetail || request.failure || null) : null" :data-choir-fail-kind="retryPhase ? null : failKind" :data-choir-fail-repeat="failRepeat && !retryPhase ? '' : null" :data-choir-retry="retryPhase">
                        <p class="text-[18px] leading-normal text-muted [text-wrap:pretty]"><span data-choir-fail-fix>{{ pageLead }}</span><template v-if="costLine && !retryPhase">{{ ' ' }}<span data-choir-fail-cost>{{ costLine }}</span></template></p>
                        <a v-if="failKind && !oursScreen" :href="helpUrl + '#if-a-song-doesnt-come-out'" target="_blank" rel="noopener" class="link mt-2 inline-flex min-h-11 items-center self-start" data-choir-help-failed>{{ t('ownChoir.help.failed') }}</a>
                    </div>
                    <ol data-choir-steps>
                        <li class="grid grid-cols-[32px_minmax(0,1fr)] gap-x-3.5" data-choir-step="1">
                            <span class="flex flex-col items-center" aria-hidden="true">
                                <span :class="dotClass(0)" :style="game ? { fontSize: '14px' } : null" data-ui="step-dot"><Icon v-if="savedStep" name="check" :size="16" /><template v-else>{{ formatNumber(1) }}</template></span>
                                <span class="my-1.5 w-px flex-1 bg-line"></span>
                            </span>
                            <div class="flex min-w-0 flex-col pb-8 pt-1" :class="savedStep ? 'gap-[18px]' : 'gap-[22px]'" :data-choir-step-state="savedStep ? 'saved' : hum && !resing ? 'hum' : 'sing'">
                                <div class="flex flex-col gap-1.5">
                                    <h2 class="text-lg font-semibold leading-[1.3] text-cream">{{ savedStep ? (useEarlier ? t('ownChoir.failed.earlier.title') : t('ownChoir.voice.savedTitle')) : t('ownChoir.record.title') }}</h2>
                                    <p class="text-[15px] leading-[1.45] text-muted" data-step-body>{{ savedStep ? (useEarlier ? t('ownChoir.failed.earlier.body', { date: earlierDate }) : t('ownChoir.voice.savedBody')) : failKind ? t('ownChoir.failed.oneBody') : t('ownChoir.intro.oneBody') }}</p>
                                    <p v-if="savedStep && wordlessNote" class="text-sm text-muted" data-choir-wordless>{{ t('ownChoir.songs.wordless') }}</p>
                                </div>
                                <!-- The choices, only while singing is the way (a saved voice needs none). -->
                                <ClipChoice v-if="singing" :song-id="id" />
                                <HeadphonesPick v-if="singing" />
                                <p v-if="ownChoir.data?.busyToday && canStart" class="text-sm text-cream" data-choir-busy>{{ t('ownChoir.busy.today') }}</p>
                                <p v-if="notEnglish && canStart" class="text-sm text-muted" data-choir-english>{{ t('ownChoir.songs.notEnglish') }}</p>
                                <!-- What stops a song before any singing takes the action's place: too long, an email to confirm, the free song used. -->
                                <p v-if="!canStart"></p>
                                <p v-else-if="songTooLong" class="card p-5 text-cream" data-choir-too-long>{{ t('ownChoir.songs.tooLong') }}</p>
                                <div v-else-if="needEmail" class="card space-y-3 p-5" data-choir-email>
                                    <h3 class="text-lg font-semibold text-cream">{{ t('ownChoir.email.title') }}</h3>
                                    <p class="text-cream" role="status">{{ emailSent ? t('ownChoir.email.sent', { email: account.me.email }) : t('ownChoir.email.body') }}</p>
                                    <button type="button" :class="emailSent ? 'btn-secondary' : 'btn-primary'" class="self-start" :disabled="sending" :aria-busy="sending ? 'true' : null" data-choir-email-send @click="sendEmail">{{ emailSent ? t('ownChoir.email.again') : t('ownChoir.email.send') }}</button>
                                </div>
                                <div v-else-if="freeHad" class="card space-y-3 p-5" data-choir-free-had>
                                    <p class="text-cream">{{ t('ownChoir.limit.free_used') }}</p>
                                    <!-- What Plus unlocks here, and what it costs (the tagline: Plus is for what you make). -->
                                    <p class="text-sm text-muted">{{ t('ownChoir.limit.unlocks') }}</p>
                                    <p v-if="priceLine" class="font-semibold text-cream" data-plus-price>{{ priceLine }}</p>
                                    <button type="button" class="btn-primary self-start" data-choir-plus @click="getPlus">{{ t('plus.subscribe') }}</button>
                                </div>
                                <!-- A voice kept from before (Hillel: "store a model to make it easier"): one tap; singing again is the quiet link. -->
                                <div v-else-if="savedStep" class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                    <button type="button" class="btn-primary h-[52px] px-[26px] text-[17px] font-bold max-sm:w-full" :disabled="busy" :aria-busy="busy ? 'true' : null" data-choir-again @click="act(useEarlier ? 'earlier' : 'retry', again)">{{ t('ownChoir.voice.again') }}</button>
                                    <button v-if="useEarlier" type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" :disabled="busy" data-choir-resing @click="useEarlier = false">{{ t('ownChoir.record.again') }}</button>
                                    <button v-else type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" :disabled="busy" data-choir-resing @click="resing = true">{{ wordlessNote ? t('ownChoir.songs.wordlessGo') : t('ownChoir.voice.record') }}</button>
                                </div>
                                <!-- Their own hummed song, no voice saved yet: the humming itself becomes the voice (Hillel: "have their voice
                                     replace their humming"); singing it is the quiet other way. -->
                                <div v-else-if="hum && !resing" class="flex flex-col gap-3">
                                    <p class="text-sm text-cream">{{ t('ownChoir.songs.fromHumLine') }}</p>
                                    <TurnstileBox v-if="siteKey" :key="checkRound" :site-key="siteKey" @token="checkToken = $event" @failed="checkToken = null" data-choir-check />
                                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                        <button type="button" class="btn-primary h-[52px] px-[26px] text-[17px] font-bold max-sm:w-full" :disabled="busy || (!!siteKey && !checkToken)" :aria-busy="busy ? 'true' : null" data-choir-hum @click="fromHum">{{ t('ownChoir.songs.fromHum') }}</button>
                                        <button type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" :disabled="busy" data-choir-resing @click="resing = true">{{ t('ownChoir.songs.singInstead') }}</button>
                                    </div>
                                </div>
                                <div v-else class="flex flex-wrap items-center gap-x-5 gap-y-2">
                                    <button type="button" class="btn-primary h-[52px] gap-2.5 px-[26px] text-[17px] font-bold max-sm:w-full" :disabled="busy" data-choir-start @click="failKind ? act('sing', start) : start()"><span class="size-2.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>{{ failKind ? t('ownChoir.record.again') : t('ownChoir.record.start') }}</button>
                                    <!-- After a failure: an older working voice (a take problem), the second take problem's Contact us, or none. -->
                                    <button v-if="failKind && earlierShown && !failRepeat" type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" :disabled="busy" data-choir-use-earlier @click="useEarlier = true; resing = false">{{ t('ownChoir.failed.earlier.link') }}</button>
                                    <a v-else-if="failKind === 'take' && failRepeat" :href="contactHref" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" data-choir-contact @click.prevent="navigate(contactHref)">{{ t('contact.title') }}</a>
                                    <button v-else-if="!failSings && resing && (savedVoice() || hum)" type="button" class="link inline-flex min-h-11 items-center py-3 text-[15px] font-medium" :disabled="busy" data-choir-use-saved @click="resing = false">{{ savedVoice() ? t('ownChoir.voice.useSaved') : t('ownChoir.songs.fromHum') }}</button>
                                </div>
                            </div>
                        </li>
                        <li class="grid grid-cols-[32px_minmax(0,1fr)] gap-x-3.5" data-choir-step="2">
                            <span class="flex flex-col items-center" aria-hidden="true"><span :class="dotClass(1)" :style="game ? { fontSize: '14px' } : null" data-ui="step-dot">{{ formatNumber(2) }}</span></span>
                            <div class="flex min-w-0 flex-col gap-1 pt-[5px]">
                                <h2 class="text-base font-semibold text-cream/80">{{ t('ownChoir.intro.buildTitle') }}</h2>
                                <p class="text-[15px] leading-[1.45] text-muted" data-step-body>{{ t('ownChoir.intro.buildBody', { song: choirSong }) }}</p>
                            </div>
                        </li>
                    </ol>
                    </template>
                </template>
                <!-- The intro's quiet footer: the quota (only when it matters) and Stop. Stop is not on a ready song (the
                     coordinator for Hillel: a destructive account action stays off a celebratory screen). -->
                <!-- The failure and trying again (Hillel's choir error design): the two links on one row, spread apart. -->
                <footer v-if="intro && !firstNote" class="flex border-t border-line pt-5" :class="oursScreen ? 'flex-row flex-wrap items-center justify-between gap-x-6 gap-y-2 [&>a]:text-base [&>button]:text-base' : 'flex-col gap-3.5'" data-choir-foot>
                    <p v-if="quotaLine" class="text-sm leading-normal text-muted" data-choir-quota><strong v-if="quotaLine.lead" class="font-semibold text-cream">{{ quotaLine.lead }}</strong>{{ quotaLine.rest }}</p>
                    <!-- How it works, and the questions (ai-marketing's help page on the site), opened beside the app. -->
                    <a :href="helpUrl" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center self-start text-sm text-muted underline underline-offset-4 hover:text-cream" data-choir-help>{{ t('ownChoir.help.link') }}</a>
                    <button v-if="stopShown" type="button" class="inline-flex min-h-11 items-center self-start text-sm text-muted underline underline-offset-4 hover:text-cream" data-choir-stop @click="stopAll">{{ t('ownChoir.stop.button') }}</button>
                </footer>
                <!-- Find your note's foot (Hillel's design): the opt-out alone, centred over a hairline. -->
                <footer v-else-if="intro && firstNote && stopShown" class="mx-auto mt-4 w-full max-w-[480px] border-t border-edge pt-5 text-center" data-choir-foot>
                    <button type="button" class="inline-flex min-h-11 items-center text-sm text-subtle underline decoration-current underline-offset-[3px] hover:text-cream" data-choir-stop @click="stopAll">{{ t('ownChoir.stop.button') }}</button>
                </footer>
                <p v-else-if="!intro && request?.status !== 'ready' && stopShown" class="pt-4"><button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-choir-stop @click="stopAll">{{ t('ownChoir.stop.button') }}</button></p>
            </section>
        </AppScreen>`,
    unmounted() {
        this.stopTrack();
        this.stopClip();
    },
    watch: {
        // Failed again after Try again: the failure screen again (its second-time words), not Trying again.
        'request.status'(status) {
            if (status === 'failed') this.retried = false;
        },
        'request.id': {
            immediate: true,
            handler(id) {
                this.useEarlier = false;
                const kept = id ? local.get(`stackapella.choirView.${id}`) : null;
                this.listenTo = kept && this.viewOptions.some((o) => o.value === kept) ? kept : 'choir';
            },
        },
        // A Plus sheet opened from a choir moment (its `from` carries on to Checkout for the conversion).
        plusFor(open) {
            if (open) count('choir_plus_sheet_choir_download');
        },
        plusSettings(open) {
            if (open) count('choir_plus_sheet_choir_settings');
        },
        plusFriends(open) {
            if (open) count('choir_plus_sheet_choir_friends');
        },
        // The choir's anonymous counts (no IDs): what the singer saw, once per song and state while the page is open.
        seenKey: {
            immediate: true,
            handler(key) {
                if (!key) return;
                const status = this.request?.status;
                const reason = FAILURE_DETAILS[this.request?.failureDetail] ? this.request.failureDetail : 'other';
                if (status === 'failed') {
                    count(`choir_failure_seen_${reason}`);
                    this.failRun = failRunOf(this.request.id, this.failKind === 'take' ? 'take' : 'ours');
                    if (this.failRepeat) count(`choir_failure_repeat_${reason}`);
                } else if (status === 'ready') {
                    count('choir_ready_seen');
                    local.remove(FAIL_RUN); // made: the run is over
                }
                else if (!status) count('choir_intro_seen');
            },
        },
        // The free song had (however the page got there: a load, or the terms just agreed): its card says what Plus costs.
        freeHad: {
            immediate: true,
            handler(had) {
                if (had) loadPrices().catch((error) => console.info('The prices were not read', error?.message ?? error));
            },
        },
        // A refresh (coming back, the poll) that finds the song ready while it's open here: its notice goes too.
        'request.status'(now) {
            if (now === 'ready' && this.state === 'ready') markSeen(this.request);
        },
    },
    created() {
        this.load();
    },
    mounted() {
        // The song on its way updates by itself: when the singer comes back to the page, and every so often while it's
        // being made (a network check on a timer, never audio).
        this.refresh = () => {
            if (document.visibilityState !== 'visible' || this.state !== 'ready') return;
            loadOwnChoir({ force: true });
            // Confirmed in another tab: the email step clears by itself.
            if (this.needEmail) loadAccount({ fresh: true }).catch((error) => console.warn('The account was not checked', error));
        };
        document.addEventListener('visibilitychange', this.refresh);
        window.addEventListener('focus', this.refresh);
        this.poll = setInterval(() => {
            if (isOpen(this.request)) this.refresh();
        }, 20000);
    },
    beforeUnmount() {
        document.removeEventListener('visibilitychange', this.refresh);
        window.removeEventListener('focus', this.refresh);
        clearInterval(this.poll);
    },
    methods: {
        /** A step's dot: the first filled with the accent, the rest ringed (game: its chips, the first its accent chip). */
        /** The choir error design's 44 px dots: done (filled with the accent, a tick) or warn (an amber ring, !); game: its chips. */
        bigDot(kind) {
            if (this.game) return kind === 'done' ? 'chip-accent size-11 justify-center p-0' : 'chip size-11 justify-center p-0 text-warn';
            return kind === 'done' ? 'flex size-11 items-center justify-center rounded-full bg-accent text-accent-ink' : 'flex size-11 items-center justify-center rounded-full border-[1.5px] border-warn text-[20px] font-bold text-warn';
        },
        dotClass(i) {
            if (this.game) return i === 0 ? 'chip-accent size-8 justify-center p-0 tabular-nums' : 'chip size-8 justify-center p-0 tabular-nums';
            return i === 0 ? 'flex size-8 items-center justify-center rounded-full bg-accent text-[14px] font-bold text-accent-ink tabular-nums' : 'flex size-8 items-center justify-center rounded-full border-[1.5px] border-line text-[14px] font-semibold text-muted tabular-nums';
        },
        statusWord(status) {
            return status === 'ready' ? t('ownChoir.wait.part.ready') : status === 'making' ? t('ownChoir.wait.part.making') : t('ownChoir.wait.part.waiting');
        },
        partLabel(key) {
            const part = partsFor(this.template ?? {}).find((p) => p.key === key);
            return templatePartName(part ?? { key, name: key });
        },
        /** The dot: green when ready, pulsing while it's being made (still with reduced motion), plain while it waits. */
        dot(status) {
            if (status === 'ready') return 'bg-good';
            if (status === 'making') return matchMedia('(prefers-reduced-motion: reduce)').matches ? 'bg-accent' : 'bg-accent animate-pulse';
            return 'border border-muted';
        },
        /** A part's name mid-sentence: lower case ("Learn the soprano part"). */
        lower(name) {
            return (name ?? '').toLocaleLowerCase(document.documentElement.lang || undefined);
        },
        /** Where things stand, and which page to show: the terms, the song, or why it can't open (each in its own words). */
        async load() {
            this.state = 'loading';
            await loadAuthConfig().catch((error) => console.warn('The settings did not load', error));
            // Who's signed in, once known (a fresh page asks at start): never "sign in" to someone who is.
            if (!account.loaded) await loadAccount().catch((error) => console.warn('The account was not checked', error));
            if (!account.me) {
                this.state = 'off';
                // A fresh page offline can't tell who's signed in: it says it's offline, never "sign in".
                this.offReason = navigator.onLine === false ? 'offline' : 'signedOut';
                return;
            }
            // AI features off (docs/ai-optional-plan.md): the choir isn't reachable; it says so, with the way to turn them on.
            if (!aiOn()) {
                this.state = 'off';
                this.offReason = 'aiOff';
                return;
            }
            await loadCatalogue().catch((error) => console.warn('The catalogue did not load', error));
            // One of their own songs (not the catalogue's) needs own_choir_songs as well.
            // Theirs only when it's one of their own saved songs; a song neither in the library nor theirs (a hidden or
            // withdrawn library song, a link that reached it anyway) never opens here.
            const listed = catalogue.list.some((entry) => entry.id === this.id);
            if (!listed) await loadMine().catch((error) => console.warn('Their songs did not load', error)); // one from another device too
            this.own = !listed && (mine.list.some((song) => song.id === this.id) || !!(await ownTemplate(this.id).catch(() => null)));
            if (!listed && !this.own) {
                this.state = 'off';
                this.offReason = 'unavailable';
                return;
            }
            const open = ownChoirOn() && (!this.own || ownChoirSongsOn());
            try {
                [this.template] = await Promise.all([loadTemplate(this.id), loadOwnChoir({ force: true }), loadProjects()]);
            } catch (error) {
                console.warn('The choir page did not load', error);
                this.state = 'off';
                this.offReason = navigator.onLine === false || error?.code === 'offline' ? 'offline' : 'unavailable';
                return;
            }
            // Off for them: only a song already made can be seen (and deleted); nothing new starts.
            if (!open && !this.request) {
                this.state = 'off';
                this.offReason = 'unavailable';
                return;
            }
            this.canStart = open;
            try {
                // Their own hummed song's humming, kept on this device: ready before the terms, for the step after them.
                if (this.own) this.hum = await humFor(this.id);
                if (open && termsNeeded()) {
                    this.terms = (await api.beta.agreement.show({ lang: 'en', for: 'own_choir' })).data;
                    this.state = 'terms';
                    return;
                }
            } catch (error) {
                console.warn('The terms did not load', error);
                this.state = 'off';
                this.offReason = navigator.onLine === false ? 'offline' : 'unavailable';
                return;
            }
            this.state = 'ready';
            // A friend who left (or was removed) since: said once here, kept for this visit after it's marked seen.
            this.leftNames = (this.request?.left ?? []).filter((l) => !l.seen).map((l) => l.name);
            if (this.leftNames.length) markSeen(this.request);
            // Its notice goes once the ready song is here to hear (never a song still on its way).
            markSeen((ownChoir.data?.requests ?? []).find((r) => r.templateId === this.id && r.status === 'ready' && !r.seen));
        },
        async agree() {
            this.busy = true;
            try {
                ownChoir.data = (await api.ownChoir.consent({ version: this.terms.version })).data;
                this.state = 'ready';
            } catch (error) {
                if (error?.code === 'agreement_changed') this.terms = (await api.beta.agreement.show({ lang: 'en', for: 'own_choir' })).data;
                else toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** Their own hummed song from the humming itself: sent as the clip, then deleted from the device. */
        async fromHum() {
            if (this.busy || !this.hum) return;
            this.busy = true;
            try {
                await sendClip({ templateId: this.id, partKey: tunePart(this.template), samples: this.hum.samples, sampleRate: this.hum.sampleRate, startSeconds: 0, hum: true, turnstileToken: this.checkToken });
                await dropHum(this.id);
                this.hum = null;
            } catch (error) {
                console.warn('The hum was not sent', error);
                toast(this.refusal(error), { tone: 'error' });
                this.freshCheck();
            } finally {
                this.busy = false;
            }
        },
        /** A refusal in the singer's words (the quota, one song at a time, a song of theirs not in the account yet). */
        refusal(error) {
            return choirRefusal(error, { own: this.own });
        },
        /** A token works once: after a refused or spent one, a fresh check. */
        freshCheck() {
            this.checkToken = null;
            this.checkRound += 1;
        },
        /** Invites a friend: a link shown once, through the device's share sheet, else copied. */
        async invite() {
            if (this.busy) return;
            if (this.request?.invite?.reason === 'plus_required') {
                this.plusFriends = true;
                return;
            }
            this.busy = true;
            try {
                const { data } = await api.ownChoir.invites.create(this.request.id);
                const text = inviteText(this.request, this.template, this.song);
                let shared = false;
                if (navigator.share) {
                    try {
                        await navigator.share({ title: this.song, text, url: data.url });
                        shared = true;
                    } catch (error) {
                        if (error?.name !== 'AbortError') console.info('The share sheet did not open', error?.message ?? error);
                    }
                }
                if (!shared) {
                    try {
                        await navigator.clipboard.writeText(`${text} ${data.url}`);
                        this.copied = true;
                    } catch (error) {
                        console.info('The link was not copied; it shows here to copy', error?.message ?? error); // the sent line shows it
                    }
                }
                this.sentInvite = data;
            } catch (error) {
                if (error?.code === 'plus_required') this.plusFriends = true;
                else if (error?.code === 'choir_full') toast(t('ownChoir.together.invite.cap'));
                else {
                    console.warn('The invite was not made', error);
                    toast(t('common.didntWork'), { tone: 'error' });
                }
            } finally {
                this.busy = false;
            }
        },
        async copyInvite() {
            try {
                await navigator.clipboard.writeText(this.sentInvite.url);
                this.copied = true;
            } catch (error) {
                console.info('The link was not copied', error?.message ?? error);
            }
        },
        async cancelInvite() {
            if (!this.sentInvite) return;
            try {
                await api.ownChoir.invites.delete(this.sentInvite.id);
                this.sentInvite = null;
                this.copied = false;
                toast(t('ownChoir.together.invite.cancelled'));
            } catch (error) {
                console.warn('The invite was not cancelled', error);
                toast(t('common.didntWork'), { tone: 'error' });
            }
        },
        /** Takes a friend out of this choir, in a styled confirmation naming exactly what goes and what comes back. */
        async removeFriend(friend) {
            const member = this.friends.find((m) => m.id === friend.id);
            const keys = member?.parts ?? [];
            const yes = await confirmAction({
                title: t('ownChoir.together.remove.title', { name: friend.name, song: this.song }),
                // Their parts go, and the owner's shares of the song come down with them (the security review).
                body: t('ownChoir.together.remove.body', { count: keys.length, name: friend.name, parts: partList(keys, this.template) }) + ' ' + t('ownChoir.together.remove.shares'),
                action: t('ownChoir.together.remove.action', { name: friend.name }),
            });
            if (!yes) return;
            try {
                await api.ownChoir.members.delete(friend.id);
                await loadOwnChoir({ force: true });
                toast(t('ownChoir.together.remove.done', { name: friend.name }));
            } catch (error) {
                console.warn('The friend was not removed', error);
                toast(t('common.didntWork'), { tone: 'error' });
            }
        },
        /** More songs are Plus's: the Plus page, which leads back here once Plus is on. */
        getPlus() {
            keepNext(location.pathname, 'choir_song');
            navigate('/plus?from=choir_song');
        },
        /** The free song's email step: the confirmation link, sent again to their address. */
        async sendEmail() {
            if (this.sending) return;
            this.sending = true;
            try {
                await api.auth.verification.resend({ email: account.me.email });
                this.emailSent = true;
            } catch (error) {
                console.warn('The confirmation email was not sent', error);
                toast(error?.code === 'rate_limited' ? t('auth.error.wait') : t('common.didntWork'), { tone: 'error' });
            } finally {
                this.sending = false;
            }
        },
        /** A failure's way on, counted by which (anonymous: sing, retry, earlier or back), then taken. */
        act(action, then) {
            count(`choir_failure_action_${action}`);
            return then();
        },
        /** Ours twice in a row: back to the song, where it can be sung meanwhile. */
        backToSong() {
            count('choir_failure_action_back');
            navigate('/?song=' + encodeURIComponent(this.id));
        },
        /** Try again after our failure: the same as again(), then this page says Trying again until it's made (the design). */
        async retry() {
            await this.again();
            if (this.request && this.request.status !== 'failed') this.retried = true;
        },
        /** It worked this time: Listen takes the page to the song, playing. */
        listen() {
            this.retried = false;
            this.$nextTick(() => this.togglePlay());
        },
        /** The song from the saved voice: one tap, then the page says it's on its way. */
        async again() {
            if (this.busy) return;
            this.busy = true;
            try {
                await makeFromVoice(this.id, { turnstileToken: this.checkToken });
            } catch (error) {
                console.warn('The choir was not asked for', error);
                toast(this.refusal(error), { tone: 'error' });
                if (error?.code === 'voice_required') await loadOwnChoir(); // gone meanwhile: Start singing shows again
            } finally {
                this.busy = false;
            }
        },
        /** Into Sing on the choir project: the one this device has, else a new one in the song's own key. */
        /** Start singing: first, the note they'd like to start on (Hillel), for the tune they chose. */
        async start() {
            if (this.busy) return;
            this.busy = true;
            count(`choir_song_${pickedClip().templateId.replaceAll('-', '_')}`);
            if (this.request?.status === 'failed') count('choir_try_again');
            try {
                const clip = await clipTemplate();
                const notes = clip.parts.find((p) => p.key === tunePart(clip))?.guide?.notes ?? [];
                this.shift = null;
                this.firstNote = { pitch: notes[0]?.pitch ?? null, song: clipPick.titles[pickedClip().templateId] ?? '' };
            } finally {
                this.busy = false;
            }
        },
        /** The note chosen (or the usual key): the take's project in that key, then Sing. */
        async record(shift = 0, silent = false) {
            this.busy = true;
            try {
                // The tune they chose, whatever this song is (Hillel, 2026-11-22), on a project only for the take
                // (`forTemplateId`: the song the choir is for), moved to start on their note; its melody, any octave.
                const chosen = pickedClip().templateId;
                const forId = this.freeSongId ?? this.id; // the free choir is the song they sing (Hillel; the server's rule)
                let project = projects.all.find((p) => p.choir?.forTemplateId === forId && !p.choir.requestId && !p.deleted) ?? null;
                // A take's project for another tune or key than the one chosen now: it goes, and the tune starts afresh.
                if (project && (project.template?.id !== chosen || (project.transpose || 0) !== shift || !!project.choir.noHeadphones !== silent)) {
                    await deleteProject(project.id).catch((error) => console.warn('The earlier take was not removed', error));
                    project = null;
                }
                // Sung whole (this song's own tune, with headphones): the whole tune, which becomes the lead.
                const whole = clipPick.whole && chosen === this.id && !silent;
                if (project && !!project.choir.whole !== whole) {
                    await deleteProject(project.id).catch((error) => console.warn('The earlier take was not removed', error));
                    project = null;
                }
                const clip = project?.template ?? (await clipTemplate(pickedClip(), { whole }));
                const tune = tunePart(clip);
                // The failed take's one fix goes with the next take, over its count-in (views/sing.js choirTip).
                if (this.fixKey) tabStorage.set(CHOIR_FIX, this.fixKey);
                else tabStorage.remove(CHOIR_FIX);
                project ??= await startFromTemplate(clip, { transpose: shift, keyChosen: true, focusKey: tune, choir: { requestId: null, forTemplateId: forId, noHeadphones: silent, whole, auto: tabStorage.get('stackapella.choirFromSite') === this.id } });
                navigate(`/p/${project.id}/sing${tune ? `?part=${encodeURIComponent(tune)}` : ''}`);
            } finally {
                this.busy = false;
            }
        },
        /**
         * The choir project for the ready song, keeping its parts to play offline: its own, else a new one tied to it; `focusKey`
         * opens it on that part. One at a time (a second tap waits for the first).
         */
        async choirProject(focusKey = null, { lead = false } = {}) {
            let project = this.project?.choir?.requestId === this.request.id ? this.project : null;
            if (project) {
                await saveProject({ ...JSON.parse(JSON.stringify(project)), choir: choirOf(this.request, { lead }) });
                return project;
            }
            return startFromTemplate(this.template, { keyChosen: true, focusKey, choir: choirOf(this.request, { lead }) });
        },
        /** The ready song's parts, decoded once (kept on the device after the first time). */
        voices() {
            if (this.loaded?.id !== this.request.id) this.loaded = { id: this.request.id, voices: loadChoir(this.request) };
            return this.loaded.voices;
        },
        /** Listen to (remembered per song on this device): stops what plays, and plays the new one if something was playing. */
        async chooseView(view) {
            const was = this.playing || this.hearingClip;
            this.stopTrack();
            this.stopClip();
            this.listenTo = view;
            local.set(`stackapella.choirView.${this.request.id}`, view);
            count(view === 'recording' ? 'choir_view_recording' : view === 'withChoir' ? 'choir_view_with_choir' : 'choir_view_full');
            if (was) this.togglePlay();
        },
        /**
         * The voices of the version chosen, and the sound to play them with. You + choir: their whole-song take as the tune
         * (Plus's Lead blend mixes both: 0 their take, 1 the AI's, null as the view says); their recording mixed under at
         * its level when the clip was sung to this song. Download follows the same.
         */
        async version(base, sound) {
            const s = { ...(sound ?? {}) };
            let voices = [...base];
            const balance = [...(s.balance ?? [])];
            const lead = this.request.leadTake;
            if (this.listenTo === 'withChoir' && lead) {
                const [take] = await loadChoir({ ...this.request, parts: [{ ...lead, partKey: 'lead-take' }] });
                const b = s.leadBlend ?? 0;
                if (b <= 0) voices = voices.filter((v) => v.partKey !== lead.partKey);
                else if (b < 1) balance.push({ partKey: lead.partKey, db: 20 * Math.log10(b) });
                if (b < 1) {
                    voices.push(take);
                    if (b > 0) balance.push({ partKey: 'lead-take', db: 20 * Math.log10(1 - b) });
                }
            }
            const clip = ownChoir.data?.voice?.clipListen;
            if (s.recordingLevel != null && clip?.url && clip.templateId === this.request.templateId) {
                const [mine] = await loadChoir({ ...this.request, parts: [{ id: 'clip', partKey: 'your-recording', url: clip.url, sampleRate: clip.sampleRate, frames: clip.frames, offsetFrames: clip.offsetFrames ?? 0 }] });
                voices.push(mine);
                balance.push({ partKey: 'your-recording', db: s.recordingLevel });
            }
            return { voices, sound: { ...s, balance } };
        },
        /** Plays (or stops) the finished track here; the press answers at once, and it stops by itself at the end. */
        /** Choir settings' Sound: kept with the song, and heard at once (playing, it carries on from where it was). */
        async saveSound(sound) {
            const request = this.request;
            request.settings = { ...(request.settings ?? {}), sound };
            if (this.playing) {
                const at = choirPosition()?.seconds ?? 0;
                const shaped = await this.version(await this.voices(), sound);
                await playChoir(shaped.voices, shaped.sound, at, { tune: tunePart(this.template) }).catch((error) => console.warn('The choir did not play', error));
            }
            try {
                const { data } = await api.ownChoir.requests.sound(request.id, sound);
                Object.assign(request, data);
            } catch (error) {
                console.warn('The sound was not kept', error);
                if (error?.code === 'plus_required') this.plusSettings = true;
                else toast(t('common.didntWork'), { tone: 'error' });
            }
        },
        /** How it's sung: a remake with these settings (the current version plays until the new one is ready). */
        async remake(sung) {
            if (this.busy) return;
            this.busy = true;
            try {
                const { data } = await api.ownChoir.requests.remake(this.request.id, sung);
                Object.assign(this.request, data);
            } catch (error) {
                console.warn('The remake did not start', error);
                if (error?.code === 'plus_required') this.plusSettings = true;
                else toast(choirRefusal(error) || t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** Their own 30 seconds (the saved voice's clip), played as it is; the choir stops first. */
        hearClip() {
            if (this.hearingClip) {
                this.stopClip();
                return;
            }
            this.stopTrack();
            const player = new Audio(this.clipUrl);
            this.clipPlayer = player;
            player.addEventListener('ended', () => this.stopClip());
            player.play().then(() => (this.hearingClip = true), (error) => {
                console.warn('Their recording did not play', error);
                this.stopClip();
                toast(t('common.didntWork'), { tone: 'error' });
            });
        },
        stopClip() {
            this.clipPlayer?.pause();
            this.clipPlayer = null;
            this.hearingClip = false;
        },
        async togglePlay() {
            if (this.listenTo === 'recording') {
                this.stopTrack();
                this.hearClip();
                return;
            }
            this.stopClip();
            if (this.playing || this.loadingTrack) {
                this.stopTrack();
                return;
            }
            this.loadingTrack = true;
            try {
                const { voices, sound } = await this.version(await this.voices(), this.request.settings?.sound ?? null);
                if (!this.loadingTrack) return; // stopped while it loaded
                await playChoir(voices, sound, 0, { tune: tunePart(this.template) });
                this.playing = true;
                countChoir('own_choir_heard');
                count('choir_listen_played');
                this.watchEnd();
            } catch (error) {
                console.warn('The choir did not play', error);
                this.loaded = null;
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.loadingTrack = false;
            }
        },
        /** Reads the engine's own clock once a frame to see the end (nothing is scheduled on a timer). */
        watchEnd() {
            const at = choirPosition();
            if (!this.playing) return;
            if (!at || at.done) {
                if (at?.done) aiUse('choir_heard'); // played to its end (AI optional's measure, once a day)
                this.stopTrack();
                return;
            }
            this.frame = requestAnimationFrame(() => this.watchEnd());
        },
        stopTrack() {
            cancelAnimationFrame(this.frame);
            if (this.playing) stopChoir();
            this.playing = false;
            this.loadingTrack = false;
        },
        /** The finished track as a WAV on the device, its name saying AI (design rules, "AI: plain about it"). Only where
         * the server says this song may be downloaded (`canDownload`, Plus): else the Plus sheet, saying so. */
        async download() {
            if (this.request?.canDownload !== true) {
                this.plusFor = true;
                return;
            }
            this.loadingTrack = true;
            try {
                // The full-quality parts (the page plays the lighter listening copy).
                const full = await this.version(await loadChoirFull(await downloadParts(this.request)), this.request.settings?.sound ?? null);
                const wav = await mixChoirShaped(full.voices, full.sound, { tune: tunePart(this.template) });
                const url = URL.createObjectURL(wav);
                const link = Object.assign(document.createElement('a'), { href: url, download: `${t('ownChoir.ready.fileName', { song: this.song })}.wav` });
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 60000); // the file's link only, never audio
                countChoir('own_choir_download');
            } catch (error) {
                // Not theirs to download after all (the server's word, e.g. Plus ended meanwhile): the Plus sheet.
                if (error?.code === 'plus_required') {
                    this.plusFor = true;
                    return;
                }
                console.warn('The choir did not download', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.loadingTrack = false;
            }
        },
        /** The gallery's share: unlisted, the song as Share would send it (You + choir when that's what they listen to). */
        async galleryShare() {
            // The mock can't share (cloud save is off there, stores/cloud.js): its flows stand in a share's id.
            if (mockWanted()) return 'hMockGallery';
            const lead = this.listenTo === 'withChoir' && !!this.request.leadTake;
            const project = await this.choirProject(null, { lead });
            await openSession(project.id);
            return (await shareProject({ title: this.song, visibility: 'unlisted' })).id;
        },
        async share() {
            if (this.busy) return;
            this.busy = true;
            try {
                // Share follows Listen to (Hillel): You + choir shares their take as the lead; Your recording shares the
                // full choir (the bare clip stays private; the coordinator, 2026-11-22).
                const lead = this.listenTo === 'withChoir' && !!this.request.leadTake;
                navigate(`/p/${(await this.choirProject(null, { lead })).id}?sheet=share`);
            } finally {
                this.busy = false;
            }
        },
        async learn(part) {
            if (this.busy) return;
            this.busy = true;
            try {
                countChoir('own_choir_sing');
                navigate(`/p/${(await this.choirProject(part.key)).id}/sing?part=${encodeURIComponent(part.key)}`);
            } finally {
                this.busy = false;
            }
        },
        async remove() {
            // With friends in it: their parts go too, and the confirmation says so by name.
            const names = this.together ? this.friends.map((m) => m.name) : [];
            const friendsGo = names.length ? ' ' + t('ownChoir.together.delete.friends', { people: nameList(names) }) : '';
            const yes = await confirmAction({ title: t('ownChoir.delete.title', { song: this.song }), body: t('ownChoir.delete.body') + friendsGo, action: t('ownChoir.delete.action') });
            if (!yes) return;
            try {
                const project = projects.all.find((p) => p.choir?.requestId === this.request.id);
                await deleteSong(this.request);
                // Its project goes too, so Home never lists a song whose parts are gone.
                if (project) await deleteProject(project.id);
                toast(t('ownChoir.delete.done'));
            } catch (error) {
                console.warn('The choir was not deleted', error);
                toast(t('common.didntWork'), { tone: 'error' });
            }
        },
        async stopAll() {
            const yes = await confirmAction({ title: t('ownChoir.stop.title'), body: t('ownChoir.stop.body'), action: t('ownChoir.stop.action') });
            if (!yes) return;
            try {
                await deleteEverything();
                toast(t('ownChoir.stop.done'));
                navigate('/');
            } catch (error) {
                console.warn('Hear yourself as a choir was not turned off', error);
                toast(t('common.didntWork'), { tone: 'error' });
            }
        },
    },
};
