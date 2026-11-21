// Hear yourself as a choir, the song's page (/t/<song>/choir; docs/own-choir-app-plan.md; js/own-choir.js). The terms
// once (English only: the short version, the terms, one button), then where the song stands: what happens and Start
// singing (into Sing, on a project made for it); on its way; ready (Listen, how much of it is really them, Learn your
// part, Delete); or what went wrong and Sing again. Stop making songs in my voice at the foot. Behind own_choir.

import { PlusMoment } from '../components/plus-moment.js';
import { api } from '../api.js';
import { formatNumber, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { AppScreen } from '../components/page.js';
import { Notice } from '../components/form.js';
import { confirmAction } from '../components/confirm.js';
import { toast } from '../components/ui.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { deleteProject, loadProjects, projects, startFromTemplate } from '../stores/projects.js';
import { loadTemplate, partsFor, templatePartName } from '../templates.js';
import { songTitle } from '../song-titles.js';
import { Agreement } from './beta.js';
import { choirOf, countChoir, deleteEverything, deleteSong, dropHum, hasWords, humFor, isOpen, loadOwnChoir, makeFromVoice, markSeen, ownChoir, ownChoirOn, ownChoirSongsOn, requestFor, savedVoice, sendClip, termsNeeded, tunePart } from '../own-choir.js';
import { catalogue, loadCatalogue } from '../stores/catalogue.js';
import { saveProject } from '../stores/projects.js';
import { choirPosition, loadChoir, mixChoir, playChoir, stopChoir } from '../own-choir-player.js';

const FAILURES = { clip_unusable: tk('ownChoir.failed.clip_unusable'), bad_clip: tk('ownChoir.failed.bad_clip'), gates_failed: tk('ownChoir.failed.gates_failed'), render_failed: tk('ownChoir.failed.render_failed'), expired: tk('ownChoir.failed.expired'), reference_gone: tk('ownChoir.failed.reference_gone') };

export const OwnChoirView = {
    components: { Agreement, AppScreen, Notice, PlusMoment },
    props: { id: { type: String, required: true } },
    data: () => ({ plusFor: false, state: 'loading', offReason: 'unavailable', canStart: false, template: null, terms: null, busy: false, playing: false, loadingTrack: false, hum: null, own: false }),
    setup: () => ({ ownChoir, isOpen, templatePartName, savedVoice, formatNumber, navigate }),
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
            return this.parts.find((p) => !sung.has(p.key)) ?? null;
        },
        /** The quota, said only when it matters (marketing's rule): the free one, a few left, or none. */
        quotaLine() {
            const q = ownChoir.data?.quota;
            if (!q) return '';
            if (!q.plus && q.freeLeft > 0) return t('ownChoir.limit.free');
            if (q.plus && q.monthLeft > 0 && q.monthLeft <= 3) return t('ownChoir.limit.left', { n: q.monthLeft });
            return '';
        },
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
                <Notice v-if="!terms.reviewed" tone="warn">{{ t('beta.consent.draft') }}</Notice>
                <div class="card max-h-[50dvh] overflow-y-auto p-5" tabindex="0" role="region" :aria-label="terms.title"><Agreement :agreement="terms" :level="2" /></div>
                <button type="button" class="btn-primary" :disabled="busy" data-choir-agree @click="agree">{{ t('ownChoir.terms.agree') }}</button>
            </section>

            <section v-else class="space-y-5" data-choir-page :data-choir-state="request?.status ?? 'none'">
                <p class="eyebrow" translate="no">{{ song }}</p>
                <!-- Ready: the finished track first (Hillel: "some people will just want a finished track"): play it here and
                     download it, labelled AI; learning a part is one quiet option, never the main button. -->
                <template v-if="request?.status === 'ready'">
                    <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ t('ownChoir.listen.title', { song }) }}</h1>
                    <div class="card space-y-4 p-5" data-choir-track>
                        <p class="text-sm text-muted" data-choir-label>{{ t('ownChoir.listen.label') }}</p>
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <button type="button" class="btn-primary btn-lg justify-center" :aria-busy="loadingTrack ? 'true' : null" data-choir-play @click="togglePlay"><span v-if="loadingTrack" class="spinner" aria-hidden="true"></span>{{ playing ? t('studio.stop') : t('ownChoir.ready.button') }}</button>
                            <button type="button" class="btn-secondary btn-lg justify-center" :disabled="loadingTrack" data-choir-download @click="download">{{ t('ownChoir.ready.download') }}</button>
                        </div>
                        <!-- Share (the share page credits each part as AI made from their singing): the studio's share sheet. -->
                        <button type="button" class="btn-secondary w-full justify-center" :disabled="busy" data-choir-share @click="share">{{ t('ownChoir.share.button') }}</button>
                    </div>
                    <!-- Downloading is Plus's (Hillel, 2026-11-21); listening and sharing stay free. The server says who may. -->
                    <PlusMoment v-if="plusFor" :title="t('ownChoir.ready.download')" :text="t('plus.moment.choirDownload')" from="choir_download" :next="here" @close="plusFor = false" />
                    <p class="text-sm text-muted" data-choir-learn><span data-choir-progress>{{ t('ownChoir.learn.progress', { n: realCount, total: parts.length }) }}</span><template v-if="nextPart"> · <button type="button" class="link inline-flex min-h-11 items-center font-semibold" :disabled="busy" data-choir-learn-part @click="learn(nextPart)">{{ t('ownChoir.learn.button', { part: templatePartName(nextPart) }) }}</button></template></p>
                    <button type="button" class="btn-ghost btn-sm text-bad" data-choir-delete @click="remove">{{ t('ownChoir.delete.button') }}</button>
                </template>
                <!-- On its way: in-app only, never email. -->
                <div v-else-if="isOpen(request)" class="card space-y-2 p-5" role="status" data-choir-wait>
                    <h1 class="text-2xl font-bold text-cream">{{ t('ownChoir.wait.title') }}</h1>
                    <p class="text-cream">{{ t('ownChoir.wait.body', { song }) }}</p>
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
                    <!-- A voice kept from before (Hillel: "store a model to make it easier"): the next song is one tap; singing again
                         is a quiet option that replaces it. Without one, Start singing. -->
                    <p v-if="notEnglish" class="text-sm text-muted" data-choir-english>{{ t('ownChoir.songs.notEnglish') }}</p>
                    <template v-if="!canStart"></template>
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
                        <button type="button" class="btn-primary btn-lg" :disabled="busy" :aria-busy="busy ? 'true' : null" data-choir-hum @click="fromHum">{{ t('ownChoir.songs.fromHum') }}</button>
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
            if (document.visibilityState === 'visible' && this.state === 'ready') loadOwnChoir({ force: true });
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
            this.own = !catalogue.list.some((entry) => entry.id === this.id);
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
                await sendClip({ templateId: this.id, partKey: tunePart(this.template), samples: this.hum.samples, sampleRate: this.hum.sampleRate, startSeconds: 0, hum: true });
                await dropHum(this.id);
                this.hum = null;
            } catch (error) {
                console.warn('The hum was not sent', error);
                toast(this.refusal(error), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        /** A refusal in the singer's words (the quota, one song at a time, a song of theirs not in the account yet). */
        refusal(error) {
            const which = error?.body?.which;
            if (error?.code === 'quota_reached') return which === 'day' ? t('ownChoir.limit.day') : which === 'free' ? t('ownChoir.limit.free_used') : t('ownChoir.limit.month');
            if (error?.code === 'request_open') return t('ownChoir.open');
            if (error?.code === 'not_found' && this.own) return t('ownChoir.songs.notSaved');
            if (error?.code === 'song_too_long') return t('ownChoir.songs.tooLong');
            return t('ownChoir.record.failed');
        },
        /** The song from the saved voice: one tap, then the page says it's on its way. */
        async again() {
            if (this.busy) return;
            this.busy = true;
            try {
                await makeFromVoice(this.id);
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
                project ??= await startFromTemplate(this.template, { keyChosen: true, choir: { requestId: null } });
                navigate(`/p/${project.id}/sing`);
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
                const wav = mixChoir(await this.voices());
                const url = URL.createObjectURL(wav);
                const link = Object.assign(document.createElement('a'), { href: url, download: `${t('ownChoir.ready.fileName', { song: this.song })}.wav` });
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 60000); // the file's link only, never audio
                countChoir('own_choir_download');
            } catch (error) {
                console.warn('The choir did not download', error);
                this.loaded = null;
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
            const yes = await confirmAction({ title: t('ownChoir.delete.title', { song: this.song }), body: t('ownChoir.delete.body'), action: t('ownChoir.delete.action') });
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
