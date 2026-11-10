// A template of someone's own, at /t/<id> (contract/api.md, "Your own templates"; Hillel's 64.png):
// the song's details as chips; Pick your part, for 2, 3 or 4 singers, each part's range on one scale
// beside yours (the best fit chosen), a play button each, then Learn the part or Hear all parts. Its
// owner also sees You made this: where it's kept (backed up, or on this device and why, with the
// details to fill in when that's what's missing), who may open it (only them, or anyone with the
// link, to copy or share), and Edit, Make a copy, Export file and Delete. Anyone with the link of an
// unlisted one, or a choir group it's shared with, can learn it; anyone else hears it isn't here.

import { AccountWall, signInTo, signUpFor } from '../components/account-wall.js';
import { markRaw } from 'vue';
import { AppScreen } from '../components/page.js';
import { api } from '../api.js';
import { midiName, suggestPart } from '../dsp.js';
import { t, tk } from '../i18n.js';
import { goBack, navigate } from '../router.js';
import { exportFile, LICENCE } from '../creator.js';
import { playTemplate } from '../creator-audio.js';
import { lengthBeats, partsFor, singerOptions, templatePartName, validateTemplate } from '../templates.js';
import { formatTime, keyLabel } from '../theme.js';
import { newSinger, startFromTemplate } from '../stores/projects.js';
import { deleteTemplate, loadMine, mine, ownTemplate, saveTemplate, setVisibility } from '../stores/templates.js';
import { knownRange, learning, loadLearning, pickRange } from '../stores/learning.js';
import { account } from '../stores/account.js';
import { confirmAction, UNDO_SECONDS } from '../components/confirm.js';
import { paint } from '../colors.js';
import { PART_COLORS } from '../theme.js';
import { downloadFile } from '../components/create/save.js';
import { Field } from '../components/form.js';
import { Icon } from '../components/icon.js';
import { ShareIdea } from '../components/share-idea.js';
import { toast } from '../components/ui.js';
import { lazyComponent } from '../game/modules.js';

const PartBoard = lazyComponent('boards', 'PartBoard'); // game mode's only: loaded with game mode
import { game } from '../skin.js';

const STATES = {
    saved: { tone: 'good', label: tk('template.state.saved') },
    device: { tone: 'muted', label: tk('template.state.device') },
    offline: { tone: 'muted', label: tk('template.state.offline') },
    limit: { tone: 'warn', label: tk('template.state.limit') },
    readOnly: { tone: 'warn', label: tk('template.state.readOnly') },
    unverified: { tone: 'warn', label: tk('template.state.unverified') },
    invalid: { tone: 'warn', label: tk('template.state.invalid') },
    importedPrivate: { tone: 'warn', label: tk('template.state.device') },
};
const DIFFICULTY = { beginner: tk('templates.difficulty.beginner'), intermediate: tk('templates.difficulty.intermediate'), advanced: tk('templates.difficulty.advanced') };

// The boards keep a song that's ours (no owner) or public (the server's Boards::template), as
// templates.show says (`boards`). From a server too old to say, the grants that are always one of
// those: our library's and a public listing's (a group's, a project's, an idea's or a link's can be
// either, so they get none: a working board hidden, never a broken one shown).
const BOARD_GRANTS = new Set(['free', 'plus', 'month', 'kept', 'beta', 'admin', 'singer', 'template', 'public']);

/** Whether a song page offers its leaderboard: your own song when it's public; else as templates.show says (`boards`), or by its grant from a server too old to say. */
export const hasBoards = ({ own, grant, visibility, boards = null }) => (own ? visibility === 'public' : typeof boards === 'boolean' ? boards : BOARD_GRANTS.has(grant));

export const TemplateView = {
    components: { AccountWall, Field, Icon, AppScreen, PartBoard, ShareIdea },
    props: { id: { type: String, required: true } },
    data: () => ({ directed: null, template: null, grant: null, boards: null, missing: false, failed: false, singers: 4, picked: null, player: null, playingPart: null, starting: false, changing: false, details: { description: '', words: '', tune: '' }, backingUp: false }),
    setup: () => ({ STATES, DIFFICULTY, account, signInTo, signUpFor, keyLabel, midiName, templatePartName, singerOptions, formatTime, goBack, paint, PART_COLORS, game }),
    template: `
        <AppScreen :title="template?.title ?? t('template.title')" :title-is-content="!!template" back="/" measure="max-w-3xl">
            <!-- Signed out, a private song is a 404 too: a director opening their choir's song from an email, on a device they've never signed in on, signs in and comes back to it (docs/agents/handoff-frontend2.md, choir tracks stage 1) -->
            <div v-if="missing && !account.me" class="card space-y-4 p-6" role="alert" data-template-sign-in>
                <p class="text-cream">{{ t('template.signInToOpen') }}</p>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
                    <a :href="signInTo('/t/' + id)" class="btn-primary" @click.prevent="navigate(signInTo('/t/' + id))">{{ t('auth.signIn') }}</a>
                    <a :href="signUpFor('template', '/t/' + id)" class="link tap-44 text-sm" @click.prevent="navigate(signUpFor('template', '/t/' + id))">{{ t('wrap.makeAccount') }}</a>
                </div>
            </div>
            <p v-else-if="missing" class="card p-6 text-cream" role="alert">{{ t('template.missing') }}</p>
            <p v-else-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!template" class="text-muted" role="status">{{ t('create.loading') }}</p>
            <div v-else class="space-y-8">
                <div class="-mt-2 space-y-3">
                    <p v-if="template.description" class="text-cream" translate="no">{{ template.description }}</p>
                    <p v-else-if="record" class="text-muted">{{ t('template.noDescription') }}</p>
                    <ul class="flex flex-wrap gap-2 tabular-nums text-xs text-cream">
                        <li class="rounded-lg bg-raised px-2.5 py-1">{{ t(DIFFICULTY[template.difficulty] ?? DIFFICULTY.beginner) }}</li>
                        <li class="rounded-lg bg-raised px-2.5 py-1">{{ keyLabel(template.key) }}</li>
                        <li class="rounded-lg bg-raised px-2.5 py-1">{{ t('studio.meta.bpm', { bpm: template.tempo }) }}</li>
                        <li class="rounded-lg bg-raised px-2.5 py-1">{{ formatTime(seconds) }}</li>
                    </ul>
                    <p v-if="credits" class="text-sm text-muted" translate="no">{{ credits }}</p>
                </div>

                <!-- A choir's own song (an import, private to you and your groups): sharing it with the choir comes first, since that's the only way it reaches the singers (docs/agents/handoff-frontend2.md, choir tracks stage 1) -->
                <section v-if="choirSong" class="card space-y-3 p-5 md:p-6" aria-labelledby="choir-heading" data-share-choir>
                    <h2 id="choir-heading" class="text-xl font-extrabold tracking-tight text-cream">{{ t('template.choir.title') }}</h2>
                    <p class="text-sm text-muted">{{ t('template.choir.hint') }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                        <a v-for="g in directed" :key="g.id" :href="'/groups/' + g.id + '?share=' + id" class="btn-primary" translate="no" @click.prevent="navigate('/groups/' + g.id + '?share=' + id)">{{ t('template.choir.shareWith', { name: g.name }) }}</a>
                        <a v-if="!directed.length" :href="'/groups?start=1&share=' + id" class="btn-primary" @click.prevent="navigate('/groups?start=1&share=' + id)">{{ t('template.choir.start') }}</a>
                    </div>
                </section>

                <section class="card space-y-4 p-5 md:p-6" aria-labelledby="pick-heading">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="pick-heading" class="text-xl font-extrabold tracking-tight text-cream">{{ t('template.pick') }}</h2>
                        <div v-if="singerOptions(template).length > 1" class="flex items-center gap-2">
                            <span id="singers-label" class="text-sm text-muted">{{ t('template.singers') }}</span>
                            <div class="seg flex" role="radiogroup" aria-labelledby="singers-label">
                                <button v-for="n in singerOptions(template)" :key="n" type="button" role="radio" :aria-checked="singers === n ? 'true' : 'false'" class="seg-option min-w-10" @click="setSingers(n)">{{ n }}</button>
                            </div>
                        </div>
                    </div>
                    <ul class="space-y-2" role="radiogroup" aria-labelledby="pick-heading">
                        <li v-for="(part, i) in shownParts" :key="part.key" class="relative flex flex-wrap items-center gap-3 rounded-tile p-3 transition" :class="picked === part.key ? 'bg-(--sel-wash) shadow-[inset_0_0_0_2px_var(--selected)]' : 'bg-(--fill) hover:bg-(--fill-2)'">
                            <button type="button" role="radio" :aria-checked="picked === part.key ? 'true' : 'false'" class="flex min-w-0 flex-1 items-center gap-3 text-start after:absolute after:inset-0 after:rounded-[inherit]" @click="picked = part.key">
                                <span class="radio-mark" aria-hidden="true"></span>
                                <span class="w-28 shrink-0 sm:w-32">
                                    <span class="flex items-center gap-2 font-bold text-cream">{{ templatePartName(part) }}<span v-if="part.melody" class="tabular-nums text-[10px] font-semibold uppercase tracking-wider text-muted">{{ t('practice.melody') }}</span></span>
                                    <span class="block tabular-nums text-xs text-muted">{{ t('templates.range', { low: midiName(part.range.low), high: midiName(part.range.high) }) }}</span>
                                </span>
                                <span class="relative hidden h-2.5 flex-1 rounded-full bg-ink/60 sm:block" aria-hidden="true">
                                    <span v-if="yourRange" class="absolute -inset-y-1.5 rounded-md border border-dashed border-muted/70" :style="bar(yourRange)"></span>
                                    <span class="absolute inset-y-0 rounded-full" :style="{ ...bar(part.range), background: paint(part.color ?? PART_COLORS[i % PART_COLORS.length]) }"></span>
                                </span>
                            </button>
                            <button type="button" class="glass-bubble relative z-10 grid size-11 shrink-0 place-items-center rounded-full text-cream" :aria-label="playingPart === part.key ? t('studio.stop') : t('template.hearPart', { part: templatePartName(part) })" @click="hearPart(part)"><Icon :name="playingPart === part.key ? 'stop' : 'play'" :size="16" /></button>
                            <!-- The picked part's leaderboard (components/boards.js; docs/leaderboard-redesign-plan.md: one board, under
                                 the part you picked), above the row's hit area so it never picks the part -->
                            <PartBoard v-if="picked === part.key && game() && template.id && !template.course && !imported && onBoards" :key="'board-' + part.key" class="relative z-10 min-w-0 basis-full sm:ps-8" :song-title="template.title" :template-id="template.id" :part-key="part.key" :part-name="templatePartName(part)" :color="paint(part.color ?? PART_COLORS[i % PART_COLORS.length])" />
                        </li>
                    </ul>
                    <p data-ui="facts" class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted">
                        <span>{{ t('template.oneEach') }}</span>
                        <span v-if="yourRange" class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded border border-dashed border-muted/70" aria-hidden="true"></span>{{ t('template.yourRange') }}</span>
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" :class="choirSong ? 'btn-secondary' : 'btn-primary'" :disabled="starting || !picked" @click="learn">{{ t('template.learnPart', { part: pickedName }) }} <Icon name="arrow" :size="16" /></button>
                        <button type="button" class="btn-secondary" @click="hear"><Icon :name="player && !playingPart ? 'stop' : 'play'" :size="16" /> {{ player && !playingPart ? t('studio.stop') : t('template.hearAll') }}</button>
                    </div>
                </section>

                <section v-if="record" class="space-y-4" aria-labelledby="yours-heading">
                    <h2 id="yours-heading" class="text-xs font-bold uppercase tracking-widest text-muted">{{ imported ? t('template.yoursImported') : t('template.yours') }}</h2>
                    <div class="card space-y-3 p-5" :class="record.state === 'saved' ? '' : 'border-warn/50'">
                        <p class="font-bold text-cream">{{ t(STATES[record.state]?.label ?? STATES.device.label) }}</p>
                        <p v-if="record.state === 'limit'" class="text-sm text-muted">{{ t('template.limitHint') }} <a href="/plus?from=private_template" class="link" @click.prevent="navigate('/plus?from=private_template')">{{ t('export.seePlus') }}</a></p>
                        <p v-else-if="record.state === 'unverified'" class="text-sm text-muted">{{ t('template.unverifiedHint') }}</p>
                        <p v-else-if="record.state === 'importedPrivate'" class="text-sm text-muted" data-template-imported-private>{{ t('template.importedHint') }}</p>
                        <template v-else-if="record.state === 'invalid'">
                            <p class="text-sm text-muted">{{ t('template.fillIn') }}</p>
                            <ul v-if="record.invalid?.reasons?.length" class="list-disc ps-5 text-sm text-muted" translate="no"><li v-for="(reason, i) in record.invalid.reasons" :key="i">{{ reason }}</li></ul>
                            <form class="space-y-3" @submit.prevent="backUp">
                                <Field :label="t('create.save.description')" v-slot="f"><input :id="f.id" v-model="details.description" class="input" maxlength="200" :placeholder="t('template.linePlaceholder')" /></Field>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <Field :label="t('create.save.wordsBy')" v-slot="f"><input :id="f.id" v-model="details.words" class="input" maxlength="120" :placeholder="t('template.namePlaceholder')" translate="no" /></Field>
                                    <Field :label="t('create.save.tuneBy')" v-slot="f"><input :id="f.id" v-model="details.tune" class="input" maxlength="120" :placeholder="t('template.namePlaceholder')" translate="no" /></Field>
                                </div>
                                <button type="submit" class="btn-primary" :disabled="backingUp">{{ backingUp ? t('common.saving') : t('template.backUp') }}</button>
                            </form>
                        </template>
                        <AccountWall v-else-if="!account.me" reason="template" :next="'/t/' + id" bare />
                        <p v-else-if="record.state === 'saved'" class="text-sm text-muted">{{ t('template.savedHint') }}</p>
                    </div>
                    <!-- Share as an idea (docs/ideas-app-plan.md): a backed-up song of your own, once Ideas is open -->
                    <ShareIdea v-if="account.me && record.state === 'saved' && !otherAuthor && !imported" :template="template" :template-id="id" />
                    <!-- An import stays private to you and your groups (contract/api.md, imported_private): no idea, no link, so neither is offered -->
                    <p v-if="account.me && imported" class="text-sm text-muted" data-template-choir-private>{{ t('template.choirPrivate') }}</p>
                    <fieldset v-else-if="account.me" class="grid gap-3 sm:grid-cols-2" :disabled="changing || otherAuthor">
                        <legend class="sr-only">{{ t('create.save.visibility') }}</legend>
                        <label v-for="v in ['private', 'unlisted']" :key="v" class="radio-card">
                            <input type="radio" name="visibility" class="radio mt-px" :checked="(record.visibility === 'unlisted') === (v === 'unlisted')" @change="visibility(v)" />
                            <span><span class="block font-semibold text-cream">{{ v === 'unlisted' ? t('create.save.unlisted') : t('create.save.private') }}</span><span class="block text-sm text-muted">{{ v === 'unlisted' ? t('create.save.unlistedHint') : t('create.save.privateHint') }}</span></span>
                        </label>
                        <p v-if="otherAuthor" class="text-sm text-muted sm:col-span-2">{{ t('create.save.otherHint') }}</p>
                    </fieldset>
                    <div v-if="record.visibility === 'unlisted' && record.state === 'saved'" class="flex flex-wrap items-center gap-2">
                        <input class="input min-w-0 flex-1 basis-56" :value="link" readonly :aria-label="t('template.link')" translate="no" @focus="$event.target.select()" />
                        <button type="button" class="btn-secondary" @click="copyLink">{{ t('share.copy') }}</button>
                        <button v-if="canShare" type="button" class="btn-secondary" @click="shareLink">{{ t('share.share') }}</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" class="btn-secondary" :disabled="record.state === 'readOnly'" @click="navigate('/create?edit=' + id)">{{ t('template.edit') }}</button>
                        <button type="button" class="btn-secondary" @click="navigate('/create?copy=' + id)">{{ t('template.copy') }}</button>
                        <!-- A song made from sheet music has no template file: it stays an import (docs/sheet-music-patent-notes.md) -->
                        <button v-if="!imported" type="button" class="btn-ghost" @click="exportIt"><Icon name="download" :size="16" /> {{ t('template.export') }}</button>
                        <button type="button" class="btn-ghost ms-auto text-bad" @click="remove">{{ t('template.delete') }}</button>
                    </div>
                </section>
            </div>
        </AppScreen>`,
    computed: {
        /** Whether this song has boards: the server keeps them for our songs and public ones, never a private or unlisted one. */
        onBoards() {
            return hasBoards({ own: this.grant === 'own', grant: this.grant, visibility: this.record?.visibility, boards: this.boards });
        },
        record() {
            return mine.list.find((r) => r.id === this.id) ?? null;
        },
        otherAuthor() {
            return this.template?.licence?.words === LICENCE.other;
        },
        imported() {
            return !!(this.record?.source === 'sheet-music' || this.record?.imported);
        },
        /** Your own imported song, signed in, once your groups are known: it reaches your choir only through a group. */
        choirSong() {
            return !!(this.groupsWanted && this.record?.state === 'saved' && this.directed);
        },
        /** Your groups are wanted for your own imported song, once you're known to be signed in (the account can come after the page). */
        groupsWanted() {
            return !!(this.grant === 'own' && this.imported && account.me?.id);
        },
        shownParts() {
            return this.template ? partsFor(this.template, this.singers) : [];
        },
        /** Your range, when the app knows it (the course's range check, or what's been sung). */
        yourRange: () => knownRange(),
        /** The scale the bars share: every part shown and your range, a little room either side. */
        scale() {
            const lows = this.shownParts.map((p) => p.range.low);
            const highs = this.shownParts.map((p) => p.range.high);
            if (this.yourRange) {
                lows.push(this.yourRange.low);
                highs.push(this.yourRange.high);
            }
            return { low: Math.min(...lows) - 2, high: Math.max(...highs) + 2 };
        },
        pickedName() {
            const part = this.shownParts.find((p) => p.key === this.picked);
            return part ? templatePartName(part).toLocaleLowerCase() : '';
        },
        seconds() {
            return (lengthBeats(this.template) * 60) / this.template.tempo;
        },
        credits() {
            const c = this.template.credits ?? {};
            return [c.words && t('template.wordsBy', { name: c.words }), c.tune && t('template.tuneBy', { name: c.tune }), c.arrangement && t('template.arrangedBy', { name: c.arrangement })].filter(Boolean).join(' · ');
        },
        link() {
            return `${location.origin}/t/${this.id}`;
        },
        canShare: () => typeof navigator.share === 'function',
    },
    watch: {
        groupsWanted(wanted) {
            if (wanted && this.directed === null) this.loadGroups();
        },
        shownParts() {
            if (!this.shownParts.some((p) => p.key === this.picked)) this.pickBest();
        },
        yourRange() {
            this.pickBest();
        },
        record: {
            handler(record) {
                if (!record || record.state !== 'invalid' || !this.template) return;
                const c = this.template.credits ?? {};
                this.details = { description: this.template.description ?? '', words: c.words ?? '', tune: c.tune ?? '' };
            },
            immediate: true,
        },
    },
    async created() {
        loadLearning();
        try {
            await loadMine();
            const own = await ownTemplate(this.id);
            const shown = own ? null : (await api.templates.show(this.id)).data;
            this.template = own ?? validateTemplate(shown.template);
            this.grant = own ? 'own' : shown.grant;
            this.boards = own ? null : shown.boards ?? null;
            this.singers = Math.max(...singerOptions(this.template));
            this.pickBest();
        } catch (error) {
            if (error?.code === 'not_found' || error?.status === 404) this.missing = true;
            else {
                console.warn('The template did not load', error);
                this.failed = error?.code === 'offline' ? t('common.offline') : t('template.failed');
            }
        }
    },
    unmounted() {
        this.player?.stop();
    },
    methods: {
        navigate,
        /** The groups you direct, for Share it with your choir (quietly none if they can't load: the card then offers to start one). */
        async loadGroups() {
            try {
                this.directed = (await api.groups.list()).data.filter((g) => g.role === 'director');
            } catch (error) {
                if (error?.code !== 'offline') console.warn('Your groups did not load', error);
            }
        },
        /** The part to suggest for your range (dsp.js suggestPart: the melody first for a new singer), else the melody. */
        async pickBest() {
            const parts = this.shownParts;
            if (!parts.length) return;
            const melodyFirst = this.yourRange ? await newSinger({ templateId: this.template?.id, me: account.me?.id ?? null }).catch(() => false) : false;
            const best = this.yourRange ? suggestPart(this.yourRange, parts.map((p) => ({ ...p, notes: p.guide?.notes })), { melodyFirst })?.part : null;
            if (parts !== this.shownParts) return; // the singers changed while it was worked out
            this.picked = (best ?? parts.find((p) => p.melody) ?? parts[0]).key;
        },
        setSingers(n) {
            this.singers = n;
            this.player?.stop();
        },
        bar(range) {
            const { low, high } = this.scale;
            const at = (m) => ((Math.max(low, Math.min(high, m)) - low) / (high - low)) * 100;
            return { insetInlineStart: at(range.low) + '%', width: Math.max(2, at(range.high) - at(range.low)) + '%' };
        },
        /** Every part together, or `only` one of them alone; again stops it. */
        async play(only = null) {
            const was = this.playingPart;
            const wasPlaying = !!this.player;
            await this.player?.stop();
            if (wasPlaying && was === only) return;
            const muted = only ? this.shownParts.filter((p) => p.key !== only).map((p) => p.key) : [];
            const player = await playTemplate(this.template, { singers: this.singers, muted, real: true }); // its real voices, as Sing plays them
            this.player = markRaw(player); // raw, so `this.player === player` below can be true (a reactive copy never is)
            this.playingPart = only;
            await player.done;
            if (this.player === player) {
                this.player = null;
                this.playingPart = null;
            }
        },
        hear() {
            return this.play(null);
        },
        hearPart(part) {
            return this.play(part.key);
        },
        async learn() {
            this.starting = true;
            await this.player?.stop();
            try {
                const project = await startFromTemplate(this.template, { singers: this.singers, focusKey: this.picked });
                // A part picked while your range isn't known: kept as yours, so nothing asks again.
                const part = this.shownParts.find((p) => p.key === this.picked);
                if (!this.yourRange && part?.range) await pickRange(part.range.low, part.range.high);
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.error('Starting the template failed', error);
                toast(t('projects.couldNotStart'), { tone: 'error' });
            } finally {
                this.starting = false;
            }
        },
        /** The details the account asked for, then saved to it again. */
        async backUp() {
            this.backingUp = true;
            try {
                const d = this.details;
                const template = { ...this.template, description: d.description.trim(), credits: { ...this.template.credits, words: d.words.trim(), tune: d.tune.trim() } };
                await saveTemplate(template);
                this.template = template;
            } catch (error) {
                console.warn('The template was not backed up', error);
                toast(t('template.backUpFailed'), { tone: 'error' });
            } finally {
                this.backingUp = false;
            }
        },
        async visibility(value) {
            this.changing = true;
            try {
                await setVisibility(this.id, value);
            } finally {
                this.changing = false;
            }
        },
        async copyLink() {
            try {
                await navigator.clipboard.writeText(this.link);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
        shareLink() {
            // Closing the share sheet rejects too: nothing to say.
            navigator.share({ title: this.template.title, url: this.link }).catch((error) => console.info('Not shared', error.name));
        },
        exportIt() {
            const { blob, name } = exportFile(this.template);
            downloadFile(blob, name);
        },
        async remove() {
            const sure = await confirmAction({
                title: t('template.deleteTitle', { title: this.template.title }),
                body: t('template.deleteBody'),
                details: [
                    { label: t('template.deleteParts'), value: String(this.template.parts.length) },
                    { label: t('template.deleteWho'), value: this.record?.visibility === 'unlisted' ? t('create.save.unlisted') : t('create.save.private') },
                ],
                action: t('template.delete'),
                cancel: t('confirm.keep'),
            });
            if (!sure) return;
            // An Undo while it's on screen, once the store hands one back (deleteSong's pattern).
            const undo = await deleteTemplate(this.id);
            const title = this.template.title;
            toast(t('template.deleted', { title }), undo ? { seconds: UNDO_SECONDS, actions: [{ label: t('common.undo'), run: async () => { await undo(); navigate('/t/' + this.id); } }] } : {});
            navigate('/', { replace: true });
        },
    },
};
