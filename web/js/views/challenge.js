// A challenge link (contract: boards.challenges.*; docs/leaderboards-plan.md), at /challenge/<token>:
// who set it and their score for one part of a song ("someone", to a guest or anyone under 18), and
// Sing it, which starts that song on that part. Signed in, it's accepted first, which connects two
// adults on each other's friends' boards (the server never connects anyone under 18).

import { call } from '../api.js';
import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { account, loadAccount } from '../stores/account.js';
import { startFromTemplate } from '../stores/projects.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { count } from '../usage.js';
import { Versus, partColourVar } from '../game/versus.js';
import { effect } from '../game/effects.js';
import { game } from '../skin.js';
import { boardOf } from '../components/boards.js';

export const ChallengeView = {
    components: { Versus },
    props: { token: { type: String, required: true } },
    data: () => ({ challenge: null, template: null, failed: null, busy: false, yours: null }),
    setup: () => ({ effect, account, game }),
    template: `
        <!-- Game mode's versus (docs/game-elements-plan.md): the same challenge, head to head -->
        <Versus v-if="effect('versus') && challenge && template && part && !failed" :by="challenge.by" :score="challenge.score" :song="template.title" :part="partName"
            :colour="colour" :yours="yours" :me="account.me?.name ?? ''" :heading="heading" :busy="busy" @sing="sing" />
        <main v-else class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center gap-5 px-4 py-10" data-challenge>
            <p v-if="failed" class="card p-6 text-cream" role="alert">{{ failed }}</p>
            <p v-else-if="!challenge || !template" class="text-muted" role="status">{{ t('boards.loading') }}</p>
            <template v-else>
                <p class="text-sm font-semibold uppercase tracking-widest text-accent">{{ game() ? t('boards.challenge.eyebrow') : t('boards.challenge.eyebrowSerious') }}</p>
                <h1 class="text-3xl font-extrabold tracking-tight text-cream">{{ heading }}</h1>
                <p v-if="game()" class="text-muted">{{ t('boards.challenge.how') }}</p>
                <button type="button" class="btn-primary btn-lg self-start" :disabled="busy" @click="sing">{{ t('boards.challenge.sing', { part: partName }) }}</button>
            </template>
        </main>`,
    computed: {
        part() {
            return this.template?.parts.find((p) => p.key === this.challenge?.partKey) ?? null;
        },
        /** The part's colour, for the challenger's side of the versus. */
        colour() {
            return partColourVar(this.challenge?.partKey, Math.max(0, this.template?.parts.indexOf(this.part) ?? 0));
        },
        partName() {
            return this.part ? templatePartName(this.part) : '';
        },
        heading() {
            const values = { score: this.challenge.score, part: this.partName, song: this.template.title };
            // Serious mode: a song sent to you, with no score to beat (docs/skins-plan.md row 15).
            if (!game()) return this.challenge.by ? t('boards.challenge.sentFrom', { ...values, name: this.challenge.by }) : t('boards.challenge.sentFromSomeone', values);
            return this.challenge.by ? t('boards.challenge.from', { ...values, name: this.challenge.by }) : t('boards.challenge.fromSomeone', values);
        },
    },
    async created() {
        try {
            await loadAccount().catch(() => null); // signed in or not, the challenge shows
            this.challenge = (await call('boards.challenges.show', { params: { token: this.token } })).data;
            this.template = await loadTemplate(this.challenge.templateId);
            if (!this.part) throw new Error('no such part');
            // Your best on that part, for the versus (signed in; none yet is said plainly).
            if (account.me && effect('versus')) boardOf(this.challenge.templateId, this.challenge.partKey, 'friends').then((board) => (this.yours = board?.you?.score ?? null)).catch((error) => console.info('No best to show', error?.message ?? error));
        } catch (error) {
            console.warn('The challenge did not open', error);
            this.failed = error?.code === 'not_found' ? t('boards.challenge.gone') : t('boards.challenge.failed');
        }
    },
    methods: {
        /** Accepted (signed in), then the song starts on that part. */
        async sing() {
            this.busy = true;
            try {
                if (account.me) {
                    await call('boards.challenges.accept', { params: { token: this.token } }).catch((error) => console.warn('The challenge was not accepted', error));
                    count('challenge_accepted');
                }
                const project = await startFromTemplate(this.template, { focusKey: this.challenge.partKey });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.warn('The challenge could not start the song', error);
                this.failed = t('boards.challenge.failed');
                this.busy = false;
            }
        },
    },
};
