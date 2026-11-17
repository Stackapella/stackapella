// An invite to sing in a friend's song, at /join/<token> (contract/api.md, invites.show and
// invites.accept; Hillel's 75.png, components/invite-card.js): who invited you, to which song and
// part, the parts with yours lit. Signed in, Accept brings the song to this device and opens it;
// signed out, your email goes on to make the account (or Apple or Google), and the link is kept.
// A link that's been used, taken back or run out says so kindly. A shared private arrangement needs the joiner's own
// Plus (plus_private, Hillel 2026-11-17): the Plus sheet before Join (and on Join), with the way back here.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { joinedProject } from '../stores/cloud.js';
import { loadProjects } from '../stores/projects.js';
import { InviteAccept, InviteCard } from '../components/invite-card.js';
import { PausedNote } from '../components/paused-note.js';
import { PrivatePlus, privateRefusal } from '../components/private-plus.js';
import { isPlus, loadBilling } from '../stores/plan.js';
import { teams } from '../teams.js';

const GONE = {
    invite_used: tk('join.used'),
    invite_revoked: tk('join.revoked'),
    invite_expired: tk('join.expired'),
    not_found: tk('join.notFound'),
    invite_limit: tk('join.full'),
};

export const JoinView = {
    components: { InviteAccept, InviteCard, PausedNote, PrivatePlus },
    props: { token: { type: String, required: true } },
    data: () => ({ invite: null, problem: null, joining: false, paused: false, plusSheet: false }),
    setup: () => ({ account, navigate }),
    template: `
        <PrivatePlus v-if="plusSheet" kind="join" :name="invite?.owner.name ?? ''" :next="'/join/' + token" @close="plusSheet = false" />
        <InviteCard :from="invite?.owner.name ?? ''" :kind="kind" :lead="lead" :song="invite?.song?.id ?? 'amazing-grace'" :part="invite?.partKey ?? 'alto'" :sign-in="'/signin?next=/join/' + token">
            <template v-if="problem || !invite" #top>
                <p v-if="problem" class="text-lg text-cream" role="alert">{{ problem }}</p>
                <p v-else class="text-muted" role="status">{{ t('create.loading') }}</p>
                <button v-if="problem" type="button" class="btn-secondary" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </template>
            <template v-if="invite && !problem">
                <!-- The song is paused (its starter's Plus ended; teaming up is Plus): calm, nothing to buy, try again later. -->
                <PausedNote v-if="paused" :title="t('together.paused.songTitle')" :text="t('together.accept.paused', { name: invite.owner.name })" />
                <button v-else-if="account.me" type="button" class="btn-primary btn-lg w-full" :disabled="joining" @click="join">{{ joining ? t('join.joining') : t('invite.accept') }}</button>
                <InviteAccept v-else-if="account.loaded" :next="'/join/' + token" />
            </template>
        </InviteCard>`,
    computed: {
        /** "Your part: Tenor · invite expires in 7 days" (the expiry only when the invite has one). */
        kind() {
            if (!this.invite) return '';
            const days = this.invite.expiresAt ? Math.ceil((Date.parse(this.invite.expiresAt) - Date.now()) / 86400000) : 0;
            return [this.invite.partName ? t('join.yourPart', { part: this.invite.partName }) : t('join.kind'), ...(days > 0 ? [t('invite.expires', { count: days })] : [])].join(' · ');
        },
        lead() {
            if (!this.invite) return '';
            const title = this.invite.projectTitle || this.invite.song?.title || '';
            return this.invite.partName ? t('join.leadPart', { owner: this.invite.owner.name, title, part: this.invite.partName }) : t('join.lead', { owner: this.invite.owner.name, title });
        },
    },
    async created() {
        try {
            this.invite = (await api.invites.show(this.token)).data;
            if (this.needsPlus()) this.plusSheet = true;
        } catch (error) {
            this.problem = GONE[error?.code] ? t(GONE[error.code]) : error?.code === 'offline' ? t('common.offline') : t('join.failed');
        }
    },
    methods: {
        /** Joining this one needs the person's own Plus, and they haven't it (a guest makes an account first). */
        needsPlus() {
            return teams.private && this.invite?.plusNeeded === true && !!account.me && !isPlus();
        },
        async join() {
            if (this.needsPlus()) {
                this.plusSheet = true;
                return;
            }
            this.joining = true;
            try {
                const { data } = await api.invites.accept(this.token);
                await joinedProject(data, { id: null, name: this.invite.owner.name });
                await loadProjects();
                navigate(`/p/${data.project.id}`, { replace: true });
            } catch (error) {
                if (error?.code === 'shared_paused') {
                    this.paused = true;
                    return;
                }
                if (privateRefusal(error)) {
                    loadBilling().catch((reason) => console.info('The plan was not read again', reason?.message ?? reason));
                    this.plusSheet = true;
                    return;
                }
                this.problem = GONE[error?.code] ? t(GONE[error.code]) : error?.code === 'offline' ? t('common.offline') : t('join.failed');
            } finally {
                this.joining = false;
            }
        },
    },
};
