// Lend your voice (docs/lend-your-voice-plan.md, slice 2; contract: voices.*, beta.me.show,
// beta.agreement.show?for=volunteer): volunteers record the songs' parts and the lessons' clips that
// still play the synth, unpaid, said plainly. Reached from Settings only (no new tab), behind
// auth.config's lendVoice. Not yet a volunteer: what it is, the agreement and how you're credited,
// and Lend my voice. A volunteer: what you hold (each ends after 14 days, said from day 10), and
// what's wanted, songs or lesson clips, an expert's skill said in words. A claim is then recorded
// and sent on the beta singers' own screen (/beta), and Hillel reviews it there.

import { api, ApiError } from '../api.js';
import { formatDate, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { AppScreen } from '../components/page.js';
import { Notice } from '../components/form.js';
import { confirmAction } from '../components/confirm.js';
import { toast } from '../components/ui.js';
import { account, loadAccount, loadAuthConfig } from '../stores/account.js';
import { Agreement } from './beta.js';
import { AccountWall } from '../components/account-wall.js';
import { loadProjects, projects } from '../stores/projects.js';
import { loadCatalogue } from '../stores/catalogue.js';
import { needsVoice } from '../be-the-voice.js';
import { templatePartName } from '../templates.js';
import { songTitle } from '../song-titles.js';

const KINDS = ['all', 'songs', 'clips'];
const KIND_WORDS = { all: tk('voices.kind.all'), songs: tk('voices.kind.songs'), clips: tk('voices.kind.clips') };
// Refusals said in the person's words (contract: voices.join and voices.claims.create).
const REFUSED = {
    email_unverified: tk('voices.refused.email'),
    adult_required: tk('voices.refused.adult'),
    account_too_new: tk('voices.refused.tooNew'),
    beta_singer: tk('voices.refused.betaSinger'),
    agreement_changed: tk('voices.refused.agreement'),
    claim_limit: tk('voices.refused.limit'),
    rate_limited: tk('voices.refused.today'),
    not_wanted: tk('voices.refused.taken'),
    not_volunteer: tk('voices.refused.notVolunteer'),
    volunteer_blocked: tk('voices.refused.blocked'),
};

const day = (time) => (time ? formatDate(time, { month: 'short', day: 'numeric' }) : '');

export const VoicesView = {
    components: { AccountWall, Agreement, Notice, AppScreen },
    data: () => ({ state: 'loading', agreement: null, wanted: null, skills: [], claims: [], kind: 'all', skill: '', credit: 'name', busy: null, refusal: null }),
    setup: () => ({ KINDS, KIND_WORDS, account, day, navigate }),
    template: `
        <AppScreen :title="t('voices.title')" back="/settings" :measure="state === 'volunteer' ? '' : 'max-w-3xl'">
            <!-- Ready to share (Be the voice; the engagement review): your takes of parts that still have no voice, earlier Not nows
                 included. Each opens its take in Sing with the share sheet. -->
            <section v-if="account.me && ready.length" class="mb-8 space-y-3" aria-labelledby="voices-ready" data-voices-ready>
                <h2 id="voices-ready" class="text-xl font-extrabold tracking-tight text-cream">{{ t('voices.ready.title') }}</h2>
                <p class="text-sm text-muted">{{ t('voices.ready.line') }}</p>
                <ul class="card divide-y divide-line">
                    <li v-for="r in ready" :key="r.projectId + ':' + r.partKey" class="flex flex-wrap items-center gap-3 p-4">
                        <span class="min-w-0 flex-1"><span class="block font-bold text-cream" translate="no">{{ r.song }}</span><span class="text-sm text-muted" translate="no">{{ r.part }}</span></span>
                        <button type="button" class="btn-secondary btn-sm" @click="navigate('/p/' + r.projectId + '/sing?voice=' + encodeURIComponent(r.partKey))">{{ t('voices.be.share') }}</button>
                    </li>
                </ul>
            </section>
            <p v-if="state === 'loading'" class="text-muted" role="status">{{ t('common.loading') }}</p>
            <!-- Off (an old link, a shared take's "see how it goes"): calm, and what to do now, never a dead end. -->
            <div v-else-if="state === 'off'" class="card space-y-3 p-5" role="status" data-voices-off>
                <p class="text-cream">{{ t('voices.off') }}</p>
                <p class="text-muted">{{ t('voices.offNow') }}</p>
                <a href="/" class="btn-secondary self-start" data-voices-off-home @click.prevent="navigate('/')">{{ t('projects.title') }}</a>
            </div>
            <AccountWall v-else-if="state === 'signedOut'" reason="voice" next="/voices" />
            <div v-else-if="state === 'betaSinger'" class="card space-y-3 p-5" data-voices-beta>
                <p class="text-cream">{{ t('voices.isBetaSinger') }}</p>
                <a href="/beta" class="btn-secondary self-start" @click.prevent="navigate('/beta')">{{ t('voices.toYourParts') }}</a>
            </div>

            <!-- Not yet a volunteer: what it is, the agreement, how you're credited, and Lend my voice. -->
            <section v-else-if="state === 'join'" class="space-y-5" data-voices-join>
                <p class="text-lg text-cream">{{ t('voices.intro') }}</p>
                <ul class="list-disc space-y-1 ps-5 text-muted">
                    <li>{{ t('voices.point.unpaid') }}</li>
                    <li>{{ t('voices.point.review') }}</li>
                    <li>{{ t('voices.point.yours') }}</li>
                </ul>
                <div v-if="agreement" class="card max-h-[45dvh] overflow-y-auto p-5" tabindex="0" role="region" :aria-label="t('voices.agreement')"><Agreement :agreement="agreement" /></div>
                <fieldset class="space-y-2">
                    <legend class="label">{{ t('voices.credit') }}</legend>
                    <label class="check-row"><input v-model="credit" type="radio" class="radio" value="name" /> {{ t('voices.creditName', { name: account.me?.name ?? '' }) }}</label>
                    <label class="check-row"><input v-model="credit" type="radio" class="radio" value="anonymous" /> {{ t('voices.creditAnonymous') }}</label>
                </fieldset>
                <Notice v-if="refusal" tone="bad" data-voices-refused>{{ refusal }}</Notice>
                <button type="button" class="btn-primary" :disabled="busy === 'join' || !agreement" data-voices-agree @click="join">{{ busy === 'join' ? t('auth.working') : t('voices.agree') }}</button>
            </section>

            <!-- A volunteer: what you hold, then what's wanted. From 900 px (Hillel's width design) what's wanted at left, wide,
                 and yours to record at right; a phone keeps this order. -->
            <div v-else-if="state === 'volunteer'" class="app-split" data-voices-split>
                <section v-if="claims.length" class="space-y-3 min-[900px]:col-start-2 min-[900px]:row-start-1" aria-labelledby="voices-held" data-voices-claims>
                    <h2 id="voices-held" class="text-lg font-bold text-cream">{{ t('voices.held') }}</h2>
                    <ul class="space-y-2">
                        <li v-for="c in claims" :key="c.id" class="card flex flex-wrap items-center justify-between gap-3 p-4" :data-claim="c.id">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-cream" translate="no">{{ c.title }}</p>
                                <p class="text-sm" :class="c.ending ? 'text-warn' : 'text-muted'">{{ c.take ? t('voices.sent') : c.ending ? t('voices.ending', { date: day(c.expiresAt) }) : t('voices.until', { date: day(c.expiresAt) }) }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <a v-if="!c.take" href="/beta" class="btn-primary btn-sm" data-claim-record @click.prevent="navigate('/beta')">{{ t('voices.record') }}</a>
                                <button v-if="!c.take" type="button" class="btn-ghost btn-sm" :disabled="busy === c.id" data-claim-release @click="release(c)">{{ t('voices.release') }}</button>
                            </div>
                        </li>
                    </ul>
                </section>
                <section class="mt-8 space-y-3 min-[900px]:col-start-1 min-[900px]:row-start-1 min-[900px]:mt-0" :class="claims.length ? '' : 'min-[900px]:col-span-2'" aria-labelledby="voices-wanted" data-voices-wanted>
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <h2 id="voices-wanted" class="text-lg font-bold text-cream">{{ t('voices.wanted') }}</h2>
                        <div class="flex w-full min-w-0 flex-wrap items-center gap-3 sm:w-auto">
                            <div class="seg flex" role="radiogroup" :aria-label="t('voices.show')">
                                <button v-for="k in KINDS" :key="k" type="button" role="radio" :aria-checked="kind === k" class="seg-option" @click="setKind(k)">{{ t(KIND_WORDS[k]) }}</button>
                            </div>
                            <select v-if="skills.length > 1" v-model="skill" class="input w-full min-w-0 sm:w-auto sm:max-w-xs" :aria-label="t('voices.skill')" data-voices-skill @change="loadWanted">
                                <option value="">{{ t('voices.anySkill') }}</option>
                                <option v-for="s in skills" :key="s.id" :value="s.id">{{ s.words }}</option>
                            </select>
                        </div>
                    </div>
                    <Notice v-if="refusal" tone="bad" data-voices-refused>{{ refusal }}</Notice>
                    <p v-if="wanted === null" class="text-muted" role="status">{{ t('common.loading') }}</p>
                    <p v-else-if="!wanted.length" class="card p-5 text-muted">{{ t('voices.none') }}</p>
                    <ul v-else class="space-y-2">
                        <li v-for="w in wanted" :key="w.id" class="card flex flex-wrap items-center justify-between gap-3 p-4" :data-wanted="w.id">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-cream"><span translate="no">{{ w.kind === 'song' ? w.title : w.sing }}</span><span v-if="w.kind === 'song'" class="text-muted"> · {{ w.partName }}</span></p>
                                <p class="text-sm text-muted">{{ w.kind === 'song' ? t('voices.songPart') : t('voices.clip', { seconds: Math.round(w.seconds ?? 0) }) }}<template v-if="w.skill && w.skill !== 'any'"> · {{ skillWords(w.skill) }}</template></p>
                            </div>
                            <button type="button" class="btn-secondary btn-sm" :disabled="!!busy" data-wanted-claim @click="claim(w)">{{ busy === w.id ? t('auth.working') : t('voices.claim') }}</button>
                        </li>
                    </ul>
                </section>
            </div>
        </AppScreen>`,
    computed: {
        /** Your kept takes of library songs' parts that still have no voice: { projectId, partKey, song, part }. */
        ready() {
            return projects.list
                .filter((p) => p.template?.id && !p.course && !p.beta && !p.shared)
                .flatMap((p) => (p.yourParts ?? []).filter((key) => needsVoice(p.template.id, key)).map((key) => {
                    const part = p.template.parts?.find((tp) => tp.key === key);
                    return { projectId: p.id, partKey: key, song: songTitle(p.template), part: part ? templatePartName(part) : key };
                }));
        },
    },
    async created() {
        loadProjects().catch((error) => console.warn('Your songs did not load', error));
        loadCatalogue().catch((error) => console.warn('The catalogue did not load', error));
        await this.load();
    },
    methods: {
        skillWords(id) {
            return this.skills.find((s) => s.id === id)?.words ?? id;
        },
        async load() {
            this.refusal = null;
            const config = await loadAuthConfig().catch(() => null);
            if (!config?.lendVoice) return (this.state = 'off');
            await loadAccount();
            if (!account.me) return (this.state = 'signedOut');
            let me = null;
            try {
                me = (await api.beta.me.show()).data;
            } catch (error) {
                // Not a beta singer yet (403 not_beta_singer, or none at all): the way in. Anything else is said in the log.
                if (!(error instanceof ApiError && (error.status === 404 || error.code === 'not_beta_singer'))) console.warn('beta.me failed', error);
            }
            if (me && !me.volunteer) return (this.state = 'betaSinger');
            if (!me) {
                this.state = 'join';
                this.agreement = (await api.beta.agreement.show({ for: 'volunteer' }).catch((error) => (console.warn('The agreement did not load', error), null)))?.data ?? null;
                return;
            }
            this.state = 'volunteer';
            await Promise.all([this.loadClaims(), this.loadWanted()]);
        },
        async loadClaims() {
            this.claims = (await api.voices.claims.list().catch(() => ({ data: [] }))).data;
        },
        async loadWanted() {
            this.wanted = null;
            try {
                const { data } = await api.voices.wanted({ kind: this.kind === 'all' ? undefined : this.kind, skill: this.skill || undefined });
                this.wanted = data.items;
                this.skills = data.skills ?? [];
            } catch (error) {
                console.warn('Wanted voices did not load', error);
                this.wanted = [];
                this.refusal = t('voices.failed');
            }
        },
        setKind(kind) {
            this.kind = kind;
            this.loadWanted();
        },
        said(error) {
            return REFUSED[error?.code] ? t(REFUSED[error.code]) : t('voices.failed');
        },
        async join() {
            this.busy = 'join';
            this.refusal = null;
            try {
                await api.voices.join({ agreementVersion: this.agreement.version, credit: this.credit, linksProfile: false });
                toast(t('voices.joined'));
                await this.load();
            } catch (error) {
                if (error?.code === 'agreement_changed') this.agreement = (await api.beta.agreement.show({ for: 'volunteer' }).catch(() => null))?.data ?? this.agreement;
                this.refusal = this.said(error);
            } finally {
                this.busy = null;
            }
        },
        async claim(item) {
            this.busy = item.id;
            this.refusal = null;
            try {
                await api.voices.claims.create(item.id);
                toast(t('voices.claimed'));
                await Promise.all([this.loadClaims(), this.loadWanted()]);
            } catch (error) {
                this.refusal = this.said(error);
                if (error?.code === 'not_wanted') await this.loadWanted();
            } finally {
                this.busy = null;
            }
        },
        async release(c) {
            if (!(await confirmAction({ title: t('voices.releaseTitle'), body: t('voices.releaseBody', { title: c.title }), action: t('voices.release'), destructive: false }))) return;
            this.busy = c.id;
            try {
                await api.voices.claims.delete(c.id);
                await Promise.all([this.loadClaims(), this.loadWanted()]);
            } catch (error) {
                toast(this.said(error), { tone: 'error' });
            } finally {
                this.busy = null;
            }
        },
    },
};
