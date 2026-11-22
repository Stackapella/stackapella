// A choir with friends, the friend afterwards (docs/choir-together-plan.md, screen 4; behind own_choir_together): on
// Home, where their songs are, "Choirs you sing in": each song they joined, who invited them and which parts they sing,
// its state as for any song (on its way, then Listen), Learn their part (into their own copy, as for their own songs),
// and Leave this choir in a styled confirmation naming exactly what goes. Shown only to someone who sings in one.

import { t } from '../i18n.js';
import { api } from '../api.js';
import { navigate } from '../router.js';
import { toast } from './ui.js';
import { confirmAction } from './confirm.js';
import { startFromTemplate } from '../stores/projects.js';
import { loadTemplate, templatePartName } from '../templates.js';
import { choirOf, loadOwnChoir, ownChoir, partList, savedVoice, togetherOn } from '../own-choir.js';
import { loadChoir, playChoir, stopChoir } from '../own-choir-player.js';
import { HomeBand } from './home.js';

export const JoinedChoirs = {
    components: { HomeBand },
    data: () => ({ playingId: null, loadingId: null, busy: false }),
    setup: () => ({ savedVoice, navigate }),
    computed: {
        joined: () => (togetherOn() ? ownChoir.data?.joined ?? [] : []),
        /** A ready choir of their own already: then "Hear a song of your own" isn't offered. */
        ownReady: () => (ownChoir.data?.requests ?? []).some((r) => r.status === 'ready'),
    },
    unmounted() {
        this.stop();
    },
    methods: {
        song(entry) {
            return entry.song?.title ?? '';
        },
        yours(entry) {
            const keys = entry.member?.parts ?? [];
            return t('ownChoir.together.joined.yours', { count: keys.length, parts: partList(keys) });
        },
        ready(entry) {
            return entry.member?.status === 'ready';
        },
        firstPart(entry) {
            const key = entry.member?.parts?.[0];
            return key ? templatePartName({ key, name: key }).toLocaleLowerCase(document.documentElement.lang || undefined) : '';
        },
        async listen(entry) {
            if (this.playingId === entry.member.id || this.loadingId) {
                this.stop();
                return;
            }
            this.stop();
            this.loadingId = entry.member.id;
            try {
                await playChoir(await loadChoir(entry.song));
                this.playingId = entry.member.id;
            } catch (error) {
                console.warn('The choir did not play', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.loadingId = null;
            }
        },
        stop() {
            if (this.playingId) stopChoir();
            this.playingId = null;
        },
        /** Learn their part: a copy of the song of their own, playing the choir's parts, on Sing. */
        async learn(entry) {
            if (this.busy) return;
            this.busy = true;
            try {
                const key = entry.member.parts[0];
                const template = await loadTemplate(entry.song.templateId);
                const project = await startFromTemplate(template, { keyChosen: true, focusKey: key, choir: choirOf(entry.song) });
                navigate(`/p/${project.id}/sing?part=${encodeURIComponent(key)}`);
            } catch (error) {
                console.warn('The part did not open', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
        async leave(entry) {
            const owner = entry.song?.owner ?? '';
            const song = this.song(entry);
            const yes = await confirmAction({ title: t('ownChoir.together.leave.title', { owner, song }), body: t('ownChoir.together.leave.body', { owner }), action: t('ownChoir.together.leave.action') });
            if (!yes) return;
            try {
                this.stop();
                await api.ownChoir.members.delete(entry.member.id);
                await loadOwnChoir({ force: true });
                toast(t('ownChoir.together.leave.done', { owner }));
            } catch (error) {
                console.warn('The choir was not left', error);
                toast(t('common.didntWork'), { tone: 'error' });
            }
        },
    },
    template: `
        <HomeBand v-if="joined.length" id="joined-h" tone="green" :title="t('ownChoir.together.joined.title')" color="var(--color-part-2)" data-joined>
            <div class="space-y-3">
            <article v-for="(entry, i) in joined" :key="entry.member.id" class="card space-y-3 p-4" :data-joined-member="entry.member.id" :data-joined-state="entry.member.status">
                <div class="min-w-0">
                    <h3 class="flex flex-wrap items-center gap-2 font-bold text-cream"><span translate="no">{{ t('ownChoir.together.joined.row', { owner: entry.song.owner, song: song(entry) }) }}</span><span v-if="ready(entry) && !entry.member.seen" class="chip" data-joined-new>{{ t('ownChoir.together.joined.ready') }}</span></h3>
                    <p class="text-sm text-muted">{{ yours(entry) }}</p>
                </div>
                <p v-if="!ready(entry)" class="text-sm text-cream" role="status">{{ t('ownChoir.together.joined.making') }}</p>
                <div v-else class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <button type="button" class="btn-secondary btn-sm" :aria-busy="loadingId === entry.member.id ? 'true' : null" data-joined-listen @click="listen(entry)"><span v-if="loadingId === entry.member.id" class="spinner" aria-hidden="true"></span>{{ playingId === entry.member.id ? t('studio.stop') : t('ownChoir.together.home.listen') }}</button>
                    <button type="button" class="link inline-flex min-h-11 items-center font-semibold" :disabled="busy" data-joined-learn @click="learn(entry)">{{ t('ownChoir.learn.button', { part: firstPart(entry) }) }}</button>
                </div>
                <p v-if="i === 0 && ready(entry) && savedVoice() && !ownReady" class="text-sm text-muted"><button type="button" class="link inline-flex min-h-11 items-center text-start" data-joined-own @click="navigate('/t/' + encodeURIComponent(entry.song.templateId) + '/choir')">{{ t('ownChoir.together.own.next') }}</button></p>
                <button type="button" class="link-quiet inline-flex min-h-11 items-center text-sm" data-joined-leave @click="leave(entry)">{{ t('ownChoir.together.leave.button') }}</button>
            </article>
            </div>
        </HomeBand>`,
};
