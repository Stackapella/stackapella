// A page's header, the same on every screen but Home and the studio (the design review: one way back,
// one title size): a 44px round back button, the title at 28px, and the page's own actions on the
// right. Back goes where the person came from, or `back` when they came straight here.
//
// It sits on the public site's grid (Hillel, 2026-11-10: "can we make the app and admin header and page width match
// the marketing site"; docs/design-rules.md, "One grid"): the site header's row and height (app-wrap app-bar,
// css/input.css), so Back is where the site's and Home's logo is, at every width. AppScreen puts a screen under it.

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
        eyebrow: { type: String, default: null }, // a small line over a smaller title (a course's hub, a lesson-like step)
    },
    setup: () => ({ goBack }),
    template: `
        <header class="app-wrap app-bar" data-ui="page-header">
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="backLabel ?? t('common.back')" @click="leave ? leave() : goBack(back)"><Icon name="back" :size="20" /></button>
            <div v-if="eyebrow" class="min-w-min flex-1">
                <p class="text-sm text-muted">{{ eyebrow }}</p>
                <h1 class="text-xl font-bold leading-tight text-cream" :translate="titleIsContent ? 'no' : null"><slot name="title">{{ title }}</slot></h1>
            </div>
            <h1 v-else class="min-w-min flex-1 text-[1.75rem] font-extrabold leading-tight tracking-tight text-cream" :translate="titleIsContent ? 'no' : null"><slot name="title">{{ title }}</slot></h1>
            <slot name="aside" />
        </header>`,
};

/**
 * A screen on the site's grid: its header (PageHeader), then its content on the same row (app-wrap), so the content's
 * edges line up with the header's, the site's and Home's. `measure` is the content's own width inside that row, centred
 * as the site centres a page of reading (its .s-doc): a form or a list of rows keeps a readable measure ('max-w-3xl'),
 * a screen of cards takes the whole row (''). Attributes (data-ui, a data hook, a class) go on the screen itself.
 */
export const AppScreen = {
    components: { PageHeader },
    props: {
        title: { type: String, required: true },
        back: { type: String, default: '/' },
        titleIsContent: Boolean,
        leave: { type: Function, default: null },
        backLabel: { type: String, default: null },
        eyebrow: { type: String, default: null },
        measure: { type: String, default: 'max-w-3xl' },
        contentClass: { type: [String, Array, Object], default: '' },
        foot: { type: String, default: 'pb-16' }, // the space under the content (a screen with a pinned foot has its own)
    },
    template: `
        <div class="flex min-h-dvh flex-col">
            <PageHeader :title="title" :back="back" :title-is-content="titleIsContent" :leave="leave" :back-label="backLabel" :eyebrow="eyebrow">
                <template v-if="$slots.title" #title><slot name="title" /></template>
                <template #aside><slot name="aside" /></template>
            </PageHeader>
            <div class="app-wrap flex flex-1 flex-col" :class="foot">
                <div class="mx-auto flex w-full flex-1 flex-col" :class="[measure, contentClass]"><slot /></div>
            </div>
        </div>`,
};
