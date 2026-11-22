// A choir with friends: the friend's join (docs/choir-together-plan.md, screen 3; behind own_choir_together), at
// /choir/join/<token>. The choir page's focused column in a joining state: who invited them to sing which parts of
// which song, Listen (the song as it is), then, signed in, the own-choir terms once and this song's yes: three plain
// points, the words of an own song, and Show my name (off). Agreeing is the button: "Agree and start singing" (the usual
// 30 seconds, on the part the invite names) or, with a saved voice, "Agree and use my saved voice". Signed out: a free
// account or Sign in, coming back here. Every refusal in its own words; never a browser dialog.

import { api } from '../api.js';
import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { AppScreen } from '../components/page.js';
import { AccountWall } from '../components/account-wall.js';
import { Notice } from '../components/form.js';
import { Switch } from '../components/controls.js';
import { toast } from '../components/ui.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { startFromTemplate } from '../stores/projects.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { Agreement } from './beta.js';
import { choirRefusal, keepJoin, loadOwnChoir, ownChoir, partList, savedVoice, termsNeeded, togetherOn } from '../own-choir.js';
import { loadChoir, playChoir, stopChoir } from '../own-choir-player.js';
import { game as gameSkin } from '../stores/appearance.js';

export const ChoirTogetherJoinView = {
    components: { AccountWall, Agreement, AppScreen, Notice, Switch },
    props: { token: { type: String, required: true } },
    data: () => ({ partsChanged: false, wordsChanged: false, state: 'loading', invite: null, terms: null, showName: false, busy: false, playing: false, loadingTrack: false, template: null }),
    setup: () => ({ account, savedVoice, navigate }),
    computed: {
        here() {
            return `/choir/join/${encodeURIComponent(this.token)}`;
        },
        /** The parts the invite names (its title says these). */
        parts() {
            return this.invite?.parts ?? [];
        },
        /** Exactly what this person's voice would sing (the server's `yourParts`), else the named parts. */
        yourParts() {
            return this.invite?.yourParts ?? this.parts;
        },
        partsText() {
            return partList(this.parts, this.template);
        },
        title() {
            const invite = this.invite;
            return invite ? t('ownChoir.together.join.title', { count: this.parts.length, owner: invite.owner, parts: this.partsText, song: invite.title }) : '';
        },
        /** The part they sing along with for their 30 seconds: the first the invite names. */
        firstPart() {
            const key = this.parts[0];
            return key ? templatePartName(this.template?.parts?.find((p) => p.key === key) ?? { key, name: key }) : '';
        },
        /** The yes's three points, shown read-only before an account and as the yes after. */
        points() {
            const owner = this.invite?.owner ?? '';
            return [t('ownChoir.together.agree.one', { count: this.yourParts.length, owner, parts: partList(this.yourParts, this.template), song: this.invite?.title ?? '' }), t('ownChoir.together.agree.two', { owner }), t('ownChoir.together.agree.three', { owner })];
        },
        game: () => gameSkin(),
        header() {
            return this.invite ? t('ownChoir.together.join.header', { owner: this.invite.owner }) : t('ownChoir.title');
        },
    },
    created() {
        this.load();
    },
    unmounted() {
        this.stop();
    },
    methods: {
        async load() {
            this.state = 'loading';
            try {
                this.invite = (await api.ownChoir.invites.show(this.token)).data;
            } catch (error) {
                console.warn('The invite did not open', error);
                this.state = navigator.onLine === false || error?.code === 'offline' ? 'offline' : 'closed';
                return;
            }
            if (this.invite.status !== 'open') {
                this.state = 'closed';
                return;
            }
            loadTemplate(this.invite.templateId).then((template) => (this.template = template), () => {});
            await loadAuthConfig().catch((error) => console.warn('The settings did not load', error));
            if (!account.loaded) await loadAccount().catch((error) => console.warn('The account was not checked', error));
            if (!account.me) {
                this.state = 'signedOut';
                return;
            }
            if (!togetherOn()) {
                this.state = 'unavailable';
                return;
            }
            await loadOwnChoir({ force: true }).catch((error) => console.warn('The choir did not load', error));
            // Already in it (back on the link after joining): the Home row is where it lives now.
            if ((ownChoir.data?.joined ?? []).some((j) => j.song?.templateId === this.invite.templateId && j.song?.owner === this.invite.owner)) {
                this.state = 'joined';
                return;
            }
            if (termsNeeded()) {
                try {
                    this.terms = (await api.beta.agreement.show({ lang: 'en', for: 'own_choir' })).data;
                    this.state = 'terms';
                } catch (error) {
                    console.warn('The terms did not load', error);
                    this.state = 'unavailable';
                }
                return;
            }
            await this.readYourParts();
            this.state = 'agree';
        },
        /** The invite again, now knowing whose voice: exactly the parts it would sing (`yourParts`). */
        async readYourParts() {
            const query = savedVoice() ? { useSavedVoice: 1 } : { partKey: this.parts[0] };
            try {
                this.invite = (await api.ownChoir.invites.show(this.token, query)).data;
            } catch (error) {
                console.info('Which parts are theirs was not read; the named parts stand', error?.message ?? error);
            }
        },
        async agreeTerms() {
            this.busy = true;
            try {
                ownChoir.data = (await api.ownChoir.consent({ version: this.terms.version })).data;
                await this.readYourParts();
                this.state = 'agree';
            } catch (error) {
                if (error?.code === 'agreement_changed') this.terms = (await api.beta.agreement.show({ lang: 'en', for: 'own_choir' })).data;
                else toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** Plays (or stops) the song as it is, from its listening copies. */
        async listen() {
            if (this.playing || this.loadingTrack) {
                this.stop();
                return;
            }
            this.loadingTrack = true;
            try {
                const voices = await loadChoir({ templateId: this.invite.templateId, version: 'listen', parts: this.invite.listen ?? [] });
                await playChoir(voices);
                this.playing = true;
            } catch (error) {
                console.warn('The song did not play', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.loadingTrack = false;
            }
        },
        stop() {
            if (this.playing) stopChoir();
            this.playing = false;
        },
        /** "Agree and use my saved voice": joined at once; their parts are on their way. */
        async useSaved() {
            if (this.busy) return;
            this.busy = true;
            try {
                await api.ownChoir.invites.accept(this.token, { showName: this.showName, wordsSha: this.invite.wordsSha ?? null, useSavedVoice: true, parts: this.yourParts });
                await loadOwnChoir({ force: true });
                this.state = 'joined';
            } catch (error) {
                this.refused(error);
            } finally {
                this.busy = false;
            }
        },
        /** "Agree and start singing": the usual 30 seconds, on the invite's first part, in a choir project that knows it's a join. */
        async sing() {
            if (this.busy) return;
            this.busy = true;
            try {
                const template = this.template ?? (await loadTemplate(this.invite.templateId));
                const partKey = this.parts[0];
                const join = { token: this.token, showName: this.showName, wordsSha: this.invite.wordsSha ?? null, partKey, parts: this.yourParts, owner: this.invite.owner };
                const project = await startFromTemplate(template, { keyChosen: true, focusKey: partKey, choir: { requestId: null } });
                keepJoin(project.id, join);
                navigate(`/p/${project.id}/sing?part=${encodeURIComponent(partKey)}`);
            } catch (error) {
                console.warn('The singing did not start', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        lower(name) {
            return (name ?? '').toLocaleLowerCase(document.documentElement.lang || undefined);
        },
        /** A refusal in its own words; the terms come back if they're needed after all. */
        refused(error) {
            const code = error?.code;
            if (code === 'consent_required') {
                this.load();
                return;
            }
            if (code === 'invite_closed') this.state = 'closed';
            else if (code === 'own_song') this.state = 'own';
            else if (code === 'already_member') this.state = 'joined';
            else if (code === 'parts_changed') {
                // Which parts are theirs changed: the yes again, with what a voice like theirs sings now.
                this.partsChanged = true;
                this.load();
            }
            else if (code === 'words_changed') {
                // The words changed since this opened: shown again, with the same two buttons.
                this.wordsChanged = true;
                this.load();
            }
            else toast(choirRefusal(error), { tone: 'error' });
        },
    },
    template: `
        <AppScreen :title="header" back="/" measure="max-w-2xl">
            <p v-if="state === 'loading'" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <div v-else-if="state === 'closed' || state === 'offline' || state === 'unavailable' || state === 'own'" class="card space-y-3 p-5" :data-join-off="state">
                <p class="text-cream">{{ state === 'offline' ? t('common.offline') : state === 'own' ? t('ownChoir.together.join.own') : state === 'unavailable' ? t('ownChoir.off.unavailable') : t('ownChoir.together.join.expired') }}</p>
                <button v-if="state === 'offline'" type="button" class="btn-secondary self-start" @click="load">{{ t('common.tryAgain') }}</button>
            </div>

            <!-- The own-choir terms, once (as the choir page has them). -->
            <section v-else-if="state === 'terms' && terms" class="space-y-5" data-join-terms>
                <h1 class="text-2xl font-bold text-cream" translate="no">{{ terms.title }}</h1>
                <Notice v-if="terms.draft" tone="warn">{{ t('beta.consent.draft') }}</Notice>
                <div class="card p-5" :class="game ? '' : 'max-h-[50dvh] overflow-y-auto'" tabindex="0" role="region" :aria-label="terms.title"><Agreement :agreement="terms" :level="2" /></div>
                <button type="button" class="btn-primary" :disabled="busy" data-choir-agree @click="agreeTerms">{{ t('ownChoir.terms.agree') }}</button>
            </section>

            <section v-else class="space-y-5" data-join :data-join-state="state">
                <h1 class="pt-4 text-3xl font-extrabold tracking-tight text-cream" data-join-title>{{ state === 'joined' ? t('ownChoir.together.join.done', { owner: invite.owner }) : title }}</h1>
                <p v-if="state !== 'joined'" class="text-cream">{{ t('ownChoir.together.join.how', { owner: invite.owner }) }}</p>
                <button type="button" class="btn-secondary self-start" :aria-busy="loadingTrack ? 'true' : null" data-join-listen @click="listen"><span v-if="loadingTrack" class="spinner" aria-hidden="true"></span>{{ playing ? t('studio.stop') : t('ownChoir.together.join.listen', { owner: invite.owner }) }}</button>

                <!-- Signed out: what joining means, read-only, then an account (the link kept). -->
                <template v-if="state === 'signedOut'">
                    <ul class="card space-y-2 p-5 text-cream" data-join-points>
                        <li v-for="(point, i) in points" :key="i" class="flex gap-2.5"><span class="w-5 shrink-0 font-bold tabular-nums" aria-hidden="true">{{ i + 1 }}.</span><span>{{ point }}</span></li>
                    </ul>
                    <AccountWall reason="choirJoin" :next="here" />
                </template>

                <div v-else-if="state === 'joined'" class="card space-y-3 p-5" role="status" data-join-done>
                    <p class="text-cream">{{ t('ownChoir.together.joined.making') }}</p>
                    <button type="button" class="btn-secondary self-start" @click="navigate('/')">{{ t('ownChoir.together.joined.title') }}</button>
                </div>

                <!-- This song's yes: three plain points, the words of an own song, Show my name (off); agreeing is the button. -->
                <section v-else-if="state === 'agree'" class="card space-y-4 p-5" aria-labelledby="join-agree-h" data-join-agree>
                    <h2 id="join-agree-h" class="text-lg font-bold text-cream">{{ t('ownChoir.together.agree.title', { owner: invite.owner }) }}</h2>
                    <ul class="space-y-2 text-cream">
                        <li v-for="(point, i) in points" :key="i" class="flex gap-2.5"><span class="w-5 shrink-0 font-bold tabular-nums" aria-hidden="true">{{ i + 1 }}.</span><span>{{ point }}</span></li>
                    </ul>
                    <p v-if="partsChanged" class="text-sm text-cream" role="status" data-join-parts-changed>{{ t('ownChoir.together.parts.changed') }}</p>
                    <p v-if="wordsChanged" class="text-sm text-cream" role="status" data-join-words-changed>{{ t('ownChoir.together.words.changed') }}</p>
                    <div v-if="invite.words" class="space-y-1" data-join-words>
                        <p class="text-sm font-semibold text-muted">{{ t('ownChoir.together.agree.words') }}</p>
                        <p class="whitespace-pre-line text-cream" translate="no">{{ invite.words }}</p>
                    </div>
                    <p class="text-sm text-muted" data-join-free>{{ t('ownChoir.together.agree.free') }}</p>
                    <Switch :on="showName" :label="t('ownChoir.together.agree.showName', { owner: invite.owner })" :hint="showName ? '' : t('ownChoir.together.agree.showNameOff')" class="py-1" data-join-show-name @change="showName = $event" />
                    <p v-if="!savedVoice()" class="text-sm text-muted">{{ t('ownChoir.together.agree.part', { part: lower(firstPart) }) }}</p>
                    <!-- On a phone: full width and stacked, the yes first; then a quiet No thanks. -->
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <button v-if="savedVoice()" type="button" class="btn-primary w-full sm:w-auto" :disabled="busy" data-join-saved @click="useSaved">{{ t('ownChoir.together.agree.saved') }}</button>
                        <button type="button" :class="savedVoice() ? 'btn-secondary' : 'btn-primary'" class="w-full sm:w-auto" :disabled="busy" data-join-sing @click="sing">{{ t('ownChoir.together.agree.sing') }}</button>
                    </div>
                    <button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-join-no @click="navigate('/')">{{ t('ownChoir.together.agree.no') }}</button>
                </section>
            </section>
        </AppScreen>`,
};
