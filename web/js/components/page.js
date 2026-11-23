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
        eyebrowIsContent: Boolean, // the eyebrow is a song's name, not our words
        // A screen with one narrow column (its width class, as 'max-w-[520px]'): Back, then the eyebrow and a large title,
        // all on that column's left edge rather than the bar's (Hillel's choir start design, 2026-11-22).
        column: { type: String, default: null },
        // The bar's compact title (17 px/600, as Sing's header): for a screen whose own large title is its one h1 (the
        // coordinator's review, 2026-11-23: two big titles stacked). The tab is named from that h1 instead.
        compact: Boolean,
    },
    setup: () => ({ goBack }),
    template: `
        <header v-if="column" class="app-wrap pt-[calc(28px+env(safe-area-inset-top))]" data-ui="page-header" data-header-column>
            <div class="mx-auto flex w-full flex-col gap-5" :class="column">
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="backLabel ?? t('common.back')" @click="leave ? leave() : goBack(back)"><Icon name="back" :size="20" /></button>
                <div class="flex flex-col gap-2">
                    <p v-if="eyebrow" class="text-[13px] font-semibold uppercase tracking-[0.14em] text-accent" :translate="eyebrowIsContent ? 'no' : null" data-header-eyebrow>{{ eyebrow }}</p>
                    <h1 class="text-[34px] font-bold leading-[1.1] tracking-[-0.01em] text-cream" :translate="titleIsContent ? 'no' : null"><slot name="title">{{ title }}</slot></h1>
                </div>
            </div>
        </header>
        <header v-else class="app-wrap app-bar" data-ui="page-header">
            <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="backLabel ?? t('common.back')" @click="leave ? leave() : goBack(back)"><Icon name="back" :size="20" /></button>
            <div v-if="eyebrow" class="min-w-min flex-1">
                <p class="text-sm text-muted">{{ eyebrow }}</p>
                <p v-if="compact" class="text-[17px] font-semibold leading-tight text-cream" :translate="titleIsContent ? 'no' : null" data-header-compact><slot name="title">{{ title }}</slot></p>
                <h1 v-else class="text-xl font-bold leading-tight text-cream" :translate="titleIsContent ? 'no' : null"><slot name="title">{{ title }}</slot></h1>
            </div>
            <p v-else-if="compact" class="min-w-min flex-1 text-[17px] font-semibold leading-tight text-cream" :translate="titleIsContent ? 'no' : null" data-header-compact><slot name="title">{{ title }}</slot></p>
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
        eyebrowIsContent: Boolean,
        column: { type: String, default: null }, // PageHeader's one-column header, at this width
        compact: Boolean, // PageHeader's compact bar title: the screen's own title is its h1
        measure: { type: String, default: 'max-w-3xl' },
        contentClass: { type: [String, Array, Object], default: '' },
        foot: { type: String, default: 'pb-16' }, // the space under the content (a screen with a pinned foot has its own)
        // The side panel's own: on a phone, the gap above it (the content's own gap, so a phone looks as before); at 900 px,
        // its top (the content's top padding) or min-[900px]:static for a side that can outgrow the window.
        sideClass: { type: [String, Array, Object], default: 'mt-6' },
    },
    template: `
        <div class="flex min-h-dvh flex-col">
            <PageHeader :title="title" :back="back" :title-is-content="titleIsContent" :leave="leave" :back-label="backLabel" :eyebrow="eyebrow" :eyebrow-is-content="eyebrowIsContent" :column="column" :compact="compact">
                <template v-if="$slots.title" #title><slot name="title" /></template>
                <template #aside><slot name="aside" /></template>
            </PageHeader>
            <div class="app-wrap flex flex-1 flex-col" :class="foot">
                <!-- With a side (Hillel's width design): the content at left, the side panel at right from 900 px, sticky
                     where it fits; a phone has the side after the content, as written. -->
                <div v-if="$slots.side" class="app-split mx-auto w-full flex-1" :class="measure" data-app-split>
                    <div class="flex min-w-0 flex-col" :class="contentClass"><slot /></div>
                    <aside class="flex min-w-0 flex-col gap-6 min-[900px]:sticky min-[900px]:top-6 min-[900px]:!mt-0" :class="sideClass" data-app-side><slot name="side" /></aside>
                </div>
                <div v-else class="mx-auto flex w-full flex-1 flex-col" :class="[measure, contentClass]"><slot /></div>
            </div>
        </div>`,
};
