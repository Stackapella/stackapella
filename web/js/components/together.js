// Sing together (contract/api.md, "Collaboration: friends on a project"; docs/backend-plan.md C7):
// friends take turns on one project, by a link the owner sends them (no email from us). The owner
// sees who's in it, makes an invite (for a part, or any), copies or shares its link (shown once),
// takes back an invite, and removes a singer (their parts kept, or taken out). A singer sees who's
// in it, adds a part of the song as their own, and leaves (keeping their parts for the project, or
// taking them out). A project not yet on the account, or signed out, says what's needed first.

import { api } from '../api.js';
import { formatDate, formatList, t } from '../i18n.js';
import { navigate } from '../router.js';
import { templatePartName } from '../templates.js';
import { account } from '../stores/account.js';
import { changed, leftProject, syncNow } from '../stores/cloud.js';
import { storedProject } from '../stores/projects.js';
import { Icon } from './icon.js';
import { Notice } from './form.js';
import { Modal, toast } from './ui.js';

export const TogetherSheet = {
    components: { Icon, Modal, Notice },
    props: { project: { type: Object, required: true }, parts: { type: Array, default: () => [] } },
    emits: ['close', 'add-part', 'left'],
    data: () => ({ members: null, invites: [], failed: null, partKey: '', made: null, busy: false, removing: null, leaving: false, addKey: '', stored: null, saving: false }),
    setup: () => ({ account, templatePartName, formatDate }),
    template: `
        <Modal :title="t('together.title')" :subtitle="singer ? '' : t('together.hint')" wide @close="$emit('close')">
            <div class="space-y-5">
                <p v-if="singer" class="text-muted">{{ t('together.singerHint', { owner: project.shared.owner?.name ?? '' }) }}</p>
                <Notice v-if="!account.me" tone="info">{{ t('together.signIn') }} <a href="/signin" class="link" @click.prevent="navigate('/signin?next=/p/' + project.id)">{{ t('auth.signIn') }}</a></Notice>
                <!-- 106.png: first, save it to your account; then the invite link. -->
                <template v-else-if="!onAccount">
                    <div class="rounded-2xl bg-raised/60 px-4 py-3" data-save-first>
                        <p class="font-semibold text-cream">{{ t('together.saveFirst') }}</p>
                        <p class="text-sm text-muted">{{ t('together.saveFirstHint') }}</p>
                    </div>
                    <p v-if="failed" class="text-sm text-bad" role="alert">{{ failed }}</p>
                    <div class="-mx-6 flex flex-wrap items-center justify-end gap-3 border-t border-line px-6 pt-5">
                        <button type="button" class="btn-secondary" @click="$emit('close')">{{ t('together.notNow') }}</button>
                        <button type="button" class="btn-primary" :disabled="saving" @click="saveFirst">{{ saving ? t('common.saving') : t('together.saveAndInvite') }}</button>
                    </div>
                </template>
                <template v-else>
                    <p v-if="failed" class="text-sm text-bad" role="alert">{{ failed }}</p>
                    <p v-else-if="!members" class="text-muted" role="status">{{ t('create.loading') }}</p>
                    <section v-else class="space-y-2" :aria-label="t('together.who')">
                        <h3 class="font-bold text-cream">{{ t('together.who') }}</h3>
                        <ul class="divide-y divide-line rounded-2xl border border-line">
                            <li v-for="m in members" :key="m.id" class="flex flex-wrap items-center gap-3 px-4 py-2.5">
                                <span class="min-w-0 flex-1 text-cream" translate="no">{{ m.name }}<span v-if="m.id === account.me.id" class="text-muted"> {{ t('together.you') }}</span></span>
                                <span class="text-sm text-muted">{{ m.role === 'owner' ? t('together.owner') : t('together.singer') }}</span>
                                <button v-if="!singer && m.role === 'singer'" type="button" class="btn-ghost btn-sm" @click="removing = m">{{ t('together.remove') }}</button>
                            </li>
                        </ul>
                        <div v-if="removing" class="card space-y-3 p-4">
                            <p class="text-cream">{{ t('together.removeAsk', { name: removing.name }) }}</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="remove(removing, true)">{{ t('together.keepTheirs') }}</button>
                                <button type="button" class="btn-danger btn-sm" :disabled="busy" @click="remove(removing, false)">{{ t('together.dropTheirs') }}</button>
                                <button type="button" class="btn-ghost btn-sm" @click="removing = null">{{ t('common.cancel') }}</button>
                            </div>
                        </div>
                    </section>

                    <section v-if="!singer" class="space-y-3" :aria-label="t('together.inviteTitle')">
                        <h3 class="font-bold text-cream">{{ t('together.inviteTitle') }}</h3>
                        <div v-if="made" class="space-y-2 rounded-2xl bg-raised p-3">
                            <p class="text-sm text-cream">{{ made.partName ? t('together.linkFor', { part: made.partName }) : t('together.linkAny') }}</p>
                            <div class="flex flex-wrap items-center gap-2">
                                <code class="min-w-0 flex-1 truncate text-sm text-cream">{{ made.url }}</code>
                                <button type="button" class="btn-secondary btn-sm" @click="copy(made.url)">{{ t('share.copy') }}</button>
                                <button v-if="canShare" type="button" class="btn-secondary btn-sm" @click="share(made.url)">{{ t('share.share') }}</button>
                            </div>
                            <p class="text-xs text-muted">{{ t('together.linkOnce', { date: formatDate(made.expiresAt, { day: 'numeric', month: 'long' }) }) }}</p>
                        </div>
                        <form class="flex flex-wrap items-end gap-3" @submit.prevent="invite">
                            <label v-if="templateParts.length" class="block text-sm text-muted">{{ t('together.forPart') }}
                                <select v-model="partKey" class="input mt-1 w-auto">
                                    <option value="">{{ t('together.anyPart') }}</option>
                                    <option v-for="p in templateParts" :key="p.key" :value="p.key">{{ templatePartName(p) }}</option>
                                </select>
                            </label>
                            <button type="submit" class="btn-primary" :disabled="busy">{{ t('together.makeLink') }}</button>
                        </form>
                        <ul v-if="invites.length" class="space-y-2 text-sm">
                            <li v-for="i in invites" :key="i.id" class="flex flex-wrap items-center gap-3">
                                <span class="min-w-0 flex-1 text-cream">{{ i.partName ? t('together.openFor', { part: i.partName }) : t('together.openAny') }} <span class="text-muted">{{ t('together.until', { date: formatDate(i.expiresAt, { day: 'numeric', month: 'long' }) }) }}</span></span>
                                <button type="button" class="btn-ghost btn-sm" @click="revoke(i)">{{ t('together.revoke') }}</button>
                            </li>
                        </ul>
                    </section>

                    <section v-else class="space-y-4">
                        <form v-if="templateParts.length" class="flex flex-wrap items-end gap-3" @submit.prevent="$emit('add-part', addKey); $emit('close')">
                            <label class="block text-sm text-muted">{{ t('together.addMine') }}
                                <select v-model="addKey" class="input mt-1 w-auto" required>
                                    <option v-for="p in templateParts" :key="p.key" :value="p.key">{{ templatePartName(p) }}</option>
                                </select>
                            </label>
                            <button type="submit" class="btn-secondary">{{ t('together.add') }}</button>
                        </form>
                        <button v-if="!leaving" type="button" class="btn-ghost text-bad" @click="leaving = true">{{ t('together.leave') }}</button>
                        <div v-else class="card space-y-3 p-4">
                            <p class="text-cream">{{ t('together.leaveAsk') }}</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary btn-sm" :disabled="busy" @click="leave(true)">{{ t('together.leaveKeep') }}</button>
                                <button type="button" class="btn-danger btn-sm" :disabled="busy" @click="leave(false)">{{ t('together.leaveDrop') }}</button>
                                <button type="button" class="btn-ghost btn-sm" @click="leaving = false">{{ t('common.cancel') }}</button>
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </Modal>`,
    computed: {
        singer() {
            return !!this.project.shared;
        },
        /** On the account (the device's copy: the sync may have saved it since the studio opened). */
        onAccount() {
            const p = this.stored ?? this.project;
            return this.singer || (p.version > 0 && !p.notSaved);
        },
        templateParts() {
            return this.project.template?.parts ?? [];
        },
        canShare: () => typeof navigator.share === 'function',
    },
    async created() {
        this.addKey = this.templateParts[0]?.key ?? '';
        this.stored = await storedProject(this.project.id);
        if (account.me && this.onAccount) this.load();
    },
    methods: {
        navigate,
        /** Save and invite: the song goes to the account now, then the invites open. */
        async saveFirst() {
            this.saving = true;
            this.failed = null;
            try {
                await changed('project', this.stored ?? this.project);
                await syncNow();
                this.stored = await storedProject(this.project.id);
                if (this.onAccount) this.load();
                else this.failed = t('together.notSavedYet');
            } catch (error) {
                console.warn('Saving the song for Sing together failed', error);
                this.failed = t('together.notSavedYet');
            } finally {
                this.saving = false;
            }
        },
        async load() {
            try {
                this.members = (await api.projects.members.list(this.project.id)).data;
                if (!this.singer) this.invites = (await api.invites.list(this.project.id)).data;
            } catch (error) {
                this.failed = error?.code === 'offline' ? t('common.offline') : t('together.notLoaded');
            }
        },
        async invite() {
            this.busy = true;
            this.failed = null;
            try {
                const part = this.templateParts.find((p) => p.key === this.partKey);
                this.made = (await api.invites.create(this.project.id, part ? { partKey: part.key, partName: templatePartName(part) } : {})).data;
                this.invites = (await api.invites.list(this.project.id)).data;
            } catch (error) {
                this.failed = error?.code === 'invite_limit' ? t('together.limit') : error?.code === 'offline' ? t('common.offline') : t('together.notMade');
            } finally {
                this.busy = false;
            }
        },
        async revoke(invite) {
            await api.invites.revoke(invite.id).catch((error) => console.warn('The invite was not taken back', error));
            this.invites = this.invites.filter((i) => i.id !== invite.id);
            if (this.made?.id === invite.id) this.made = null;
        },
        async remove(member, keepParts) {
            this.busy = true;
            try {
                await api.projects.members.delete(this.project.id, member.id, { keepParts });
                this.members = this.members.filter((m) => m.id !== member.id);
                this.removing = null;
                toast(t('together.removed', { name: member.name }));
            } catch (error) {
                this.failed = error?.code === 'offline' ? t('common.offline') : t('together.notRemoved');
            } finally {
                this.busy = false;
            }
        },
        /** Leaves: on the account, then the project stays here to listen to, as it was. */
        async leave(keepParts) {
            this.busy = true;
            try {
                await api.projects.members.delete(this.project.id, account.me.id, { keepParts });
                await leftProject(this.project.id);
                toast(t('together.left', { title: this.project.title }));
                this.$emit('left');
            } catch (error) {
                this.failed = error?.code === 'offline' ? t('common.offline') : t('together.notLeft');
            } finally {
                this.busy = false;
            }
        },
        async copy(url) {
            try {
                await navigator.clipboard.writeText(url);
                toast(t('share.copied'));
            } catch {
                toast(t('share.copyFailed'), { tone: 'error' });
            }
        },
        share(url) {
            navigator.share({ title: this.project.title, url }).catch((error) => console.info('Not shared', error?.name));
        },
    },
};

/** Who's singing a part of a friend's project: "Sam's part", for the part's row. */
export const singerOf = (part) => part?.createdBy?.name ?? null;

/** The names on a project, for its card: "With Ana". */
export const withNames = (names) => formatList(names);
