// Shared private arrangements need everyone's own Plus (Hillel, 2026-11-17: "make sure to access private arrangments all
// of the users (creator and otehrs) have plus accounts"; docs/pricing-plan.md, top; behind the plus_private switch,
// js/teams.js's teams.private). A person's own song with a link or another singer on it needs every person's own Plus,
// the creator's too; alone it stays free. Without Plus the song stays listed and every take is kept: opening it shows
// this sheet (the shared PlusMoment: the 7-day trial where the account still has it, else Get Plus, and Not now), never
// an error. Its words say whose song it is: yours (shared), someone else's (by a link or an invite), or a link you're
// turning on. PrivateNote is the calm line left on the screen once the sheet is closed.

import { defineAsyncComponent } from 'vue';
import { t, tk } from '../i18n.js';
import { PausedNote } from './paused-note.js';

// The sheet itself loads when it's first shown: the studio and Sing carry this module from the start, the sheet's prices
// and plan only when someone meets it.
const PlusMoment = defineAsyncComponent(() => import('./plus-moment.js').then((m) => m.PlusMoment));

/** The Plus page's ?from= (billing.checkout's `moment`) for every sheet here. */
export const PRIVATE_MOMENT = 'private_shared';

const WORDS = {
    own: [tk('together.private.ownTitle'), tk('together.private.ownText')],
    other: [tk('together.private.otherTitle'), tk('together.private.otherText')],
    song: [tk('together.private.songTitle'), tk('together.private.otherText')],
    join: [tk('together.private.otherTitle'), tk('together.private.joinText')],
    link: [tk('together.private.linkTitle'), tk('together.private.linkText')],
    invite: [tk('together.private.inviteTitle'), tk('together.private.inviteText')],
};

/** Whether a refusal is this rule's (403 plus_required, reason private_shared). */
export const privateRefusal = (error) => error?.code === 'plus_required' && error?.body?.reason === 'private_shared';

export const PrivatePlus = {
    components: { PlusMoment },
    props: {
        kind: { type: String, default: 'other' }, // own | other | song | join | link | invite
        name: { type: String, default: '' }, // whose song it is (someone else's)
        next: { type: String, default: '' }, // where the Plus page brings them back to
    },
    emits: ['close'],
    computed: {
        words() {
            const [title, text] = WORDS[this.kind] ?? WORDS.other;
            return { title: this.kind === 'other' && !this.name ? t('together.private.songTitle') : t(title, { name: this.name }), text: t(text) };
        },
        /** Back to this screen once Plus is on, unless told where. */
        backTo() {
            return this.next || window.location.pathname;
        },
    },
    setup: () => ({ PRIVATE_MOMENT }),
    template: `<PlusMoment :title="words.title" :text="words.text" :from="PRIVATE_MOMENT" :next="backTo" price data-private-plus @close="$emit('close')" />`,
};

/** The calm line on the song's screen: listed, every take kept, and the way to Plus. */
export const PrivateNote = {
    components: { PausedNote },
    props: { own: Boolean, name: { type: String, default: '' } },
    setup: () => ({ PRIVATE_MOMENT }),
    template: `<PausedNote :title="t('together.private.noteTitle')" :text="own ? t('together.private.noteOwn') : name ? t('together.private.noteOther', { name }) : t('together.private.noteSong')" :action="t('together.private.noteAction')" :from="PRIVATE_MOMENT" data-private-note />`,
};
