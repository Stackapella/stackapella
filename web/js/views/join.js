// An invite to sing in a friend's song, at /join/<token> (contract/api.md, invites.show and
// invites.accept; Hillel's 75.png, components/invite-card.js): who invited you, to which song and
// part, the parts with yours lit. Signed in, Accept brings the song to this device and opens it;
// signed out, your email goes on to make the account (or Apple or Google), and the link is kept.
// A link that's been used, taken back or run out says so kindly.

import { api } from '../api.js';
import { t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { joinedProject } from '../stores/cloud.js';
import { loadProjects } from '../stores/projects.js';
import { InviteAccept, InviteCard } from '../components/invite-card.js';

const GONE = {
    invite_used: tk('join.used'),
    invite_revoked: tk('join.revoked'),
    invite_expired: tk('join.expired'),
    not_found: tk('join.notFound'),
    invite_limit: tk('join.full'),
};

export const JoinView = {
    components: { InviteAccept, InviteCard },
    props: { token: { type: String, required: true } },
    data: () => ({ invite: null, problem: null, joining: false }),
    setup: () => ({ account, navigate }),
    template: `
        <InviteCard :from="invite?.owner.name ?? ''" :kind="kind" :lead="lead" :song="invite?.song?.id ?? 'amazing-grace'" :part="invite?.partKey ?? 'alto'" :sign-in="'/signin?next=/join/' + token">
            <template v-if="problem || !invite" #top>
                <p v-if="problem" class="text-lg text-cream" role="alert">{{ problem }}</p>
                <p v-else class="text-muted" role="status">{{ t('create.loading') }}</p>
                <button v-if="problem" type="button" class="btn-secondary" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </template>
            <template v-if="invite && !problem">
                <button v-if="account.me" type="button" class="btn-primary btn-lg w-full" :disabled="joining" @click="join">{{ joining ? t('join.joining') : t('invite.accept') }}</button>
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
        } catch (error) {
            this.problem = GONE[error?.code] ? t(GONE[error.code]) : error?.code === 'offline' ? t('common.offline') : t('join.failed');
        }
    },
    methods: {
        async join() {
            this.joining = true;
            try {
                const { data } = await api.invites.accept(this.token);
                await joinedProject(data, { id: null, name: this.invite.owner.name });
                await loadProjects();
                navigate(`/p/${data.project.id}`, { replace: true });
            } catch (error) {
                this.problem = GONE[error?.code] ? t(GONE[error.code]) : error?.code === 'offline' ? t('common.offline') : t('join.failed');
            } finally {
                this.joining = false;
            }
        },
    },
};
