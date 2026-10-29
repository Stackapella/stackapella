// A page's header, the same on every screen but Home and the studio (the design review: one way back,
// one title size): a 40px round back button, the title at 28px, and the page's own actions on the
// right. Back goes where the person came from, or `back` when they came straight here.

import { goBack } from '../router.js';
import { Icon } from './icon.js';

export const PageHeader = {
    components: { Icon },
    props: {
        title: { type: String, required: true },
        back: { type: String, default: '/' },
        titleIsContent: Boolean, // a song's or person's name, not our words
        leave: { type: Function, default: null }, // instead of going back (a screen that asks first)
        backLabel: { type: String, default: null }, // the button's name, when it isn't Back
    },
    setup: () => ({ goBack }),
    template: `
        <header class="flex min-h-18 items-center gap-3 py-4">
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="backLabel ?? t('common.back')" @click="leave ? leave() : goBack(back)"><Icon name="back" :size="20" /></button>
            <h1 class="min-w-0 flex-1 text-[1.75rem] font-extrabold leading-tight tracking-tight text-cream" :translate="titleIsContent ? 'no' : null"><slot name="title">{{ title }}</slot></h1>
            <slot name="aside" />
        </header>`,
};
