// Hear yourself as a choir, the song's page (/t/<song>/choir; docs/own-choir-app-plan.md; js/own-choir.js). The terms
// once (English only: the short version, the terms, one button), then where the song stands: what happens and Start
// singing (into Sing, on a project made for it); on its way; ready (Listen, how much of it is really them, Learn your
// part, Delete); or what went wrong and Sing again. Stop making songs in my voice at the foot. Behind own_choir.

import { keepNext, plusPriceLine, PlusMoment } from '../components/plus-moment.js';
import { loadPrices } from '../stores/plan.js';
import { api } from '../api.js';
import { formatDate, formatNumber, t, tk } from '../i18n.js';
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
import { tooLong } from '../own-choir.js';
import { inviteLine, inviteText, nameList, partList, togetherOn, whoSings, whoseList } from '../own-choir.js';
import { Icon } from '../components/icon.js';
import { game as gameSkin } from '../stores/appearance.js';
import { checkKey, choirOf, choirRefusal, countChoir, downloadParts, emailNeeded, songsLeft, deleteEverything, deleteSong, dropHum, hasWords, humFor, isOpen, loadOwnChoir, makeFromVoice, markSeen, ownChoir, ownChoirOn, ownChoirSongsOn, requestFor, savedVoice, sendClip, termsNeeded, tunePart } from '../own-choir.js';
import { catalogue, loadCatalogue } from '../stores/catalogue.js';
import { loadMine, mine, ownTemplate } from '../stores/templates.js';
import { saveProject } from '../stores/projects.js';
import { choirPosition, loadChoir, loadChoirFull, mixChoir, playChoir, stopChoir } from '../own-choir-player.js';

const FAILURES = { clip_unusable: tk('ownChoir.failed.clip_unusable'), bad_clip: tk('ownChoir.failed.bad_clip'), gates_failed: tk('ownChoir.failed.gates_failed'), render_failed: tk('ownChoir.failed.render_failed'), expired: tk('ownChoir.failed.expired'), reference_gone: tk('ownChoir.failed.reference_gone') };

export const OwnChoirView = {
    components: { Agreement, AppScreen, Icon, PlusMoment, TurnstileBox },
    props: { id: { type: String, required: true } },
    data: () => ({ leftNames: [], plusFriends: false, sentInvite: null, copied: false, plusFor: false, emailSent: false, sending: false, checkToken: null, checkRound: 0, state: 'loading', offReason: 'unavailable', canStart: false, template: null, terms: null, busy: false, playing: false, loadingTrack: false, hum: null, own: false }),
    setup: () => ({ ownChoir, account, isOpen, templatePartName, savedVoice, formatNumber, navigate }),
    computed: {
        /** Back here from the Plus page, once Plus is on. */
        here: () => location.pathname,
        song() {
            return this.template ? songTitle(this.template) : '';
        },
        request() {
            return requestFor(this.id);
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
        /** The quota, said only when it matters (marketing's rule): the free one, a few left, or none. */
        quotaLine() {
            const q = ownChoir.data?.quota;
            if (!q) return '';
            if (!q.plus && q.freeLeft > 0) return t('ownChoir.limit.free');
            // Plus counts singing minutes (part-minutes); the songs are an estimate for this song, so "about".
            if (q.unit === 'minutes' && q.minutesLeft != null) {
                // Each month of the subscription, from its renewal day: `resetsAt` is when the month turns and the full
                // allowance comes back (marketing's words: "47 minutes left this month, about 10 songs; 60 more on 14 December").
                const songs = songsLeft(q, this.template);
                const minutes = formatNumber(Math.max(0, Math.floor(q.minutesLeft)));
                const more = formatNumber(Math.round(q.minutesPerMonth ?? 0));
                const date = q.resetsAt ? formatDate(q.resetsAt, { day: 'numeric', month: 'long' }) : '';
                if (songs == null) return date ? t('ownChoir.limit.minutesOnlyMore', { minutes, more, date }) : t('ownChoir.limit.minutesOnly', { minutes });
                return date ? t('ownChoir.limit.minutesLeftMore', { minutes, songs, more, date }) : t('ownChoir.limit.minutesLeft', { minutes, songs });
            }
            if (q.plus && q.monthLeft > 0 && q.monthLeft <= 3) return t('ownChoir.limit.left', { n: q.monthLeft });
            return '';
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
        /** The model sings English: a song with words in another language is said once, never blocked (ai-modal's finding). */
        notEnglish() {
            const language = this.template?.language ?? 'en';
            return hasWords(this.template) && !language.toLowerCase().startsWith('en');
        },
        steps() {
            return [t('ownChoir.intro.one', { song: this.song }), t('ownChoir.intro.two'), t('ownChoir.intro.three')];
        },
        failure() {
            const code = this.request?.failure;
            return FAILURES[code] ? t(FAILURES[code]) : t('ownChoir.failed.body');
        },
    },
    template: `
        <AppScreen :title="t('ownChoir.title')" :back="'/?song=' + encodeURIComponent(id)" measure="max-w-2xl">
            <p v-if="state === 'loading'" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <!-- Why it can't open, each in its own words: signed out (sign in), offline (Try again), or not open to them. -->
            <div v-else-if="state === 'off'" class="card space-y-3 p-5" :data-choir-off="offReason">
                <p class="text-cream">{{ offReason === 'signedOut' ? t('ownChoir.off.signedOut') : offReason === 'offline' ? t('common.offline') : t('ownChoir.off.unavailable') }}</p>
                <a v-if="offReason === 'signedOut'" :href="'/signin?next=' + encodeURIComponent('/t/' + id + '/choir')" class="btn-secondary self-start" @click.prevent="navigate('/signin?next=' + encodeURIComponent('/t/' + id + '/choir'))">{{ t('auth.signIn') }}</a>
                <button v-else-if="offReason === 'offline'" type="button" class="btn-secondary" @click="load">{{ t('common.tryAgain') }}</button>
            </div>

            <!-- The terms, once: the short version, the terms, one button (English only, as the AI licence). -->
            <section v-else-if="state === 'terms' && terms" class="space-y-5" data-choir-terms>
                <h1 class="text-2xl font-bold text-cream" translate="no">{{ terms.title }}</h1>
                <div class="card p-5" :class="game ? '' : 'max-h-[50dvh] overflow-y-auto'" tabindex="0" role="region" :aria-label="terms.title"><Agreement :agreement="terms" :level="2" /></div>
                <button type="button" class="btn-primary" :disabled="busy" data-choir-agree @click="agree">{{ t('ownChoir.terms.agree') }}</button>
            </section>

            <section v-else class="space-y-5" data-choir-page :data-choir-state="request?.status ?? 'none'">
                <p class="eyebrow" translate="no">{{ song }}</p>
                <!-- Ready: the finished track first (Hillel: "some people will just want a finished track"): play it here and
                     download it, labelled AI; learning a part is one quiet option, never the main button. -->
                <template v-if="request?.status === 'ready'">
                    <p v-for="name in leftNames" :key="name" class="card p-4 text-cream" role="status" data-choir-left>{{ t('ownChoir.together.friendLeft', { name, song }) }}</p>
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('ownChoir.listen.title', { song }) }}</h1>
                    <div class="card space-y-4 p-5" data-choir-track>
                        <p class="text-sm text-muted" data-choir-label>{{ panelLabel }}</p>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <button type="button" class="btn-primary btn-lg justify-center" :aria-busy="loadingTrack ? 'true' : null" data-choir-play @click="togglePlay"><span v-if="loadingTrack" class="spinner" aria-hidden="true"></span>{{ playing ? t('studio.stop') : t('ownChoir.ready.button') }}</button>
                            <button type="button" class="btn-secondary btn-lg justify-center" :disabled="loadingTrack" data-choir-download @click="download">{{ t('ownChoir.ready.download') }}</button>
                        </div>
                        <!-- Share (the share page credits each part as AI made from their singing): the studio's share sheet. -->
                        <button type="button" class="btn-secondary w-full justify-center" :disabled="busy" data-choir-share @click="share">{{ t('ownChoir.share.button') }}</button>
                    </div>
                    <!-- Downloading is Plus's (Hillel, 2026-11-21); listening and sharing stay free. The server says who may. -->
                    <PlusMoment v-if="plusFor" :title="t('ownChoir.ready.download')" :text="t('plus.moment.choirDownload')" from="choir_download" :next="here" price @close="plusFor = false" />
                    <!-- A choir with friends (behind own_choir_together): the "really you" line becomes Who sings, one row a part, each
                         with its AI label (or their real take's), the parts that sit high or low for them, and one invite line. -->
                    <section v-if="together" class="space-y-2" data-choir-who aria-labelledby="choir-who-h">
                        <h2 id="choir-who-h" class="text-sm font-semibold text-muted">{{ t('ownChoir.together.who.title') }}</h2>
                        <ul class="card divide-y divide-line px-4" data-ui="who-sings">
                            <li v-for="row in who" :key="row.part.key" class="flex min-h-11 flex-wrap items-center gap-x-3 gap-y-1 py-2" :data-who-part="row.part.key" :data-who-friend="row.friend ? row.friend.id : null">
                                <span :class="game ? 'chip w-28 shrink-0 justify-center' : 'w-28 shrink-0 font-semibold text-cream'" data-ui="part-chip">{{ templatePartName(row.part) }}</span>
                                <!-- The name and its AI chip never part (a narrow row moves Remove under them instead). -->
                                <span class="flex-1 text-sm" :class="game && row.friend ? 'text-part-2' : 'text-cream'">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="inline-flex items-center gap-2 whitespace-nowrap"><span>{{ row.label }}</span><span v-if="row.ai" class="chip" data-ai-voice="tag">{{ t('ownChoir.together.who.ai') }}</span></span>
                                        <span v-if="row.note" class="text-muted" data-who-fit>{{ row.note }}</span>
                                    </span>
                                    <span v-if="row.sub" class="block text-xs text-muted" data-who-until>{{ row.sub }}</span>
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
                    <h1 class="text-2xl font-bold text-cream">{{ t('ownChoir.wait.title') }}</h1>
                    <p class="text-cream">{{ t('ownChoir.wait.body', { song }) }}</p>
                    <!-- Today's making is full (the server's daily ceiling): it's queued for tomorrow, said plainly. -->
                    <p v-if="request.startsAfter" class="text-cream" data-choir-tomorrow>{{ t('ownChoir.busy.tomorrow') }}</p>
                    <p class="text-sm text-muted">{{ t('ownChoir.wait.kept') }}</p>
                </div>
                <!-- Nothing yet, or it failed: what happens, Start singing. -->
                <template v-else>
                    <div v-if="request?.status === 'failed'" class="card space-y-2 p-5" role="alert" data-choir-failed>
                        <h2 class="text-lg font-bold text-cream">{{ t('ownChoir.failed.title') }}</h2>
                        <p class="text-cream">{{ failure }}</p>
                    </div>
                    <h1 v-else class="text-3xl font-extrabold tracking-tight text-cream">{{ t('ownChoir.record.title') }}</h1>
                    <!-- Numbered by hand, so game mode (which drops list markers) keeps them too. -->
                    <ol class="space-y-2 text-cream" data-choir-steps>
                        <li v-for="(step, i) in steps" :key="i" class="flex gap-2.5"><span class="w-5 shrink-0 font-bold tabular-nums" aria-hidden="true">{{ formatNumber(i + 1) }}.</span><span>{{ step }}</span></li>
                    </ol>
                    <p class="text-sm text-muted">{{ t('ownChoir.record.headphones') }}</p>
                    <p v-if="quotaLine" class="text-sm text-cream" data-choir-quota>{{ quotaLine }}</p>
                    <p v-if="ownChoir.data?.busyToday && canStart" class="text-sm text-cream" data-choir-busy>{{ t('ownChoir.busy.today') }}</p>
                    <!-- A voice kept from before (Hillel: "store a model to make it easier"): the next song is one tap; singing again
                         is a quiet option that replaces it. Without one, Start singing. -->
                    <p v-if="notEnglish" class="text-sm text-muted" data-choir-english>{{ t('ownChoir.songs.notEnglish') }}</p>
                    <template v-if="!canStart"></template>
                    <!-- More parts, or a longer part, than a song may have: said here, before any singing. -->
                    <p v-else-if="songTooLong" class="card p-5 text-cream" data-choir-too-long>{{ t('ownChoir.songs.tooLong') }}</p>
                    <!-- The free song needs a confirmed email: asked here, before any singing; it clears once they come back. -->
                    <div v-else-if="needEmail" class="card space-y-3 p-5" data-choir-email>
                        <h2 class="text-lg font-bold text-cream">{{ t('ownChoir.email.title') }}</h2>
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
                    <template v-else-if="savedVoice()">
                        <p class="text-sm text-cream">{{ t('ownChoir.voice.againLine') }}</p>
                        <p v-if="wordlessNote" class="text-sm text-muted" data-choir-wordless>{{ t('ownChoir.songs.wordless') }}</p>
                        <button type="button" class="btn-primary btn-lg" :disabled="busy" :aria-busy="busy ? 'true' : null" data-choir-again @click="again">{{ t('ownChoir.voice.again') }}</button>
                        <button type="button" class="link tap-44 self-start text-sm" :disabled="busy" data-choir-start @click="start">{{ wordlessNote ? t('ownChoir.songs.wordlessGo') : t('ownChoir.voice.record') }}</button>
                    </template>
                    <!-- Their own hummed song, no voice saved yet: the humming itself becomes the voice (Hillel: "have their voice
                         replace their humming"); singing it is the quiet other way. -->
                    <template v-else-if="hum">
                        <p class="text-sm text-cream">{{ t('ownChoir.songs.fromHumLine') }}</p>
                        <TurnstileBox v-if="siteKey" :key="checkRound" :site-key="siteKey" @token="checkToken = $event" @failed="checkToken = null" data-choir-check />
                        <button type="button" class="btn-primary btn-lg" :disabled="busy || (!!siteKey && !checkToken)" :aria-busy="busy ? 'true' : null" data-choir-hum @click="fromHum">{{ t('ownChoir.songs.fromHum') }}</button>
                        <button type="button" class="link tap-44 self-start text-sm" :disabled="busy" data-choir-start @click="start">{{ t('ownChoir.songs.singInstead') }}</button>
                    </template>
                    <button v-else-if="canStart" type="button" class="btn-primary btn-lg" :disabled="busy" data-choir-start @click="start">{{ request?.status === 'failed' ? t('ownChoir.record.again') : t('ownChoir.record.start') }}</button>
                </template>
                <!-- Not on a ready song (the coordinator for Hillel: a destructive account action stays off a celebratory
                     screen; the voice's own delete is the saved voice row in Settings). -->
                <p v-if="request?.status !== 'ready'" class="pt-4"><button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-choir-stop @click="stopAll">{{ t('ownChoir.stop.button') }}</button></p>
            </section>
        </AppScreen>`,
    unmounted() {
        this.stopTrack();
    },
    watch: {
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
        async start() {
            this.busy = true;
            try {
                let project = this.project && !this.project.choir?.requestId ? this.project : null;
                // Always the tune (the melody, with its words), in whatever octave suits them: never "any part".
                const tune = tunePart(this.template);
                project ??= await startFromTemplate(this.template, { keyChosen: true, focusKey: tune, choir: { requestId: null } });
                navigate(`/p/${project.id}/sing${tune ? `?part=${encodeURIComponent(tune)}` : ''}`);
            } finally {
                this.busy = false;
            }
        },
        /**
         * The choir project for the ready song, keeping its parts to play offline: its own, else a new one tied to it; `focusKey`
         * opens it on that part. One at a time (a second tap waits for the first).
         */
        async choirProject(focusKey = null) {
            let project = this.project?.choir?.requestId === this.request.id ? this.project : null;
            if (project) {
                await saveProject({ ...JSON.parse(JSON.stringify(project)), choir: choirOf(this.request) });
                return project;
            }
            return startFromTemplate(this.template, { keyChosen: true, focusKey, choir: choirOf(this.request) });
        },
        /** The ready song's parts, decoded once (kept on the device after the first time). */
        voices() {
            if (this.loaded?.id !== this.request.id) this.loaded = { id: this.request.id, voices: loadChoir(this.request) };
            return this.loaded.voices;
        },
        /** Plays (or stops) the finished track here; the press answers at once, and it stops by itself at the end. */
        async togglePlay() {
            if (this.playing || this.loadingTrack) {
                this.stopTrack();
                return;
            }
            this.loadingTrack = true;
            try {
                const voices = await this.voices();
                if (!this.loadingTrack) return; // stopped while it loaded
                await playChoir(voices);
                this.playing = true;
                countChoir('own_choir_heard');
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
                const wav = mixChoir(await loadChoirFull(await downloadParts(this.request)));
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
        async share() {
            if (this.busy) return;
            this.busy = true;
            try {
                navigate(`/p/${(await this.choirProject()).id}?sheet=share`);
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
