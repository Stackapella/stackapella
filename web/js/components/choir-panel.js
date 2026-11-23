// Home's "Hear yourself as a choir" panel (Hillel, 2026-11-22: "add the new your-choir feature as a main panel on the app
// page, I'm thinking in the top right"). The Reference layout: a small label, the title, then what's true now: a song
// ready and not yet heard (a row each, Listen; a friend's parts or a friend leaving said by name), one being made, or
// the offer (one muted line, Try it). It replaces Home's old ready card, so a choir is never said twice. On a wide screen
// it sits in the course card's side column (`side`: no card of its own); on a phone, its own card after the course.

import { t } from '../i18n.js';
import { navigate } from '../router.js';
import { clipSongs, isOpen, ownChoir, ownChoirOn, readyUnseen, togetherOn } from '../own-choir.js';

/** A friend who left this song since it was last seen (a choir with friends), if any. */
function leftOf(request) {
    return (request.left ?? []).find((l) => !l.seen) ?? null;
}

/** The friend whose parts just came into this ready song, if any (a choir with friends). */
function friendReady(request) {
    return togetherOn() ? (request.members ?? []).find((m) => m.status === 'ready' && !m.seen) ?? null : null;
}

/** Home's ready songs: ready and not yet heard, or one a friend left since (said whatever the switch). */
export function choirReady() {
    const left = (ownChoir.data?.requests ?? []).filter((r) => (r.left ?? []).some((l) => !l.seen));
    const ready = ownChoirOn() ? readyUnseen() : [];
    return [...ready, ...left.filter((r) => !ready.includes(r))];
}

export const ChoirPanel = {
    props: { side: Boolean },
    computed: {
        rows: () => choirReady(),
        /** A song on its way (the newest), when nothing is waiting to be heard. */
        making: () => (ownChoirOn() ? (ownChoir.data?.requests ?? []).filter((r) => isOpen(r)).at(-1) ?? null : null),
        shown() {
            return ownChoirOn() || this.rows.length > 0;
        },
        state() {
            return this.rows.length ? 'ready' : this.making ? 'making' : 'try';
        },
        together: () => togetherOn(),
    },
    methods: {
        line(r) {
            const left = leftOf(r);
            if (left) return t('ownChoir.together.friendLeft', { name: left.name, song: r.title });
            const friend = friendReady(r);
            if (friend) return t('ownChoir.together.home.owner', { name: friend.name, song: r.title });
            return this.together ? t('ownChoir.together.joined.mine', { song: r.title }) : t('ownChoir.ready.notice', { song: r.title });
        },
        open(templateId) {
            navigate('/t/' + encodeURIComponent(templateId) + '/choir');
        },
        /** Try it: the free song is the one they sing (Hillel), so the first tune's own song (Happy Birthday by default). */
        tryIt() {
            const first = clipSongs()[0];
            this.open(first?.songId ?? (first?.templateId === 'voice-capture' ? 'happy-birthday' : first?.templateId ?? 'happy-birthday'));
        },
    },
    template: `
        <section v-if="shown" :class="side ? '' : 'card p-5'" aria-labelledby="choir-panel-title" data-choir-panel :data-choir-panel-state="state">
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('ownChoir.home.label') }}</p>
            <h2 id="choir-panel-title" class="mt-1.5 text-lg font-bold leading-snug text-cream">{{ t('ownChoir.title') }}</h2>
            <ul v-if="rows.length" class="mt-2 divide-y divide-line" role="status" data-choir-ready>
                <li v-for="r in rows" :key="r.id" class="flex flex-wrap items-center gap-x-3 gap-y-2 py-3" data-choir-notice>
                    <p class="min-w-0 flex-1 basis-40 text-sm text-cream">{{ line(r) }}</p>
                    <button type="button" class="btn-secondary btn-sm min-h-11" @click="open(r.templateId)">{{ t('ownChoir.ready.button') }}</button>
                </li>
            </ul>
            <template v-else-if="making">
                <p class="mt-1.5 text-sm text-muted" role="status" data-choir-panel-making>{{ t('ownChoir.home.making', { song: making.title }) }}</p>
                <button type="button" class="btn-secondary btn-sm mt-4 min-h-11" @click="open(making.templateId)">{{ t('ownChoir.home.see') }}</button>
            </template>
            <template v-else>
                <p class="mt-1.5 text-sm text-muted">{{ t('ownChoir.home.body') }}</p>
                <button type="button" class="btn-secondary btn-sm mt-4 min-h-11" data-choir-try @click="tryIt">{{ t('ownChoir.home.try') }}</button>
            </template>
        </section>`,
};
