// A screen that's still loading (Hillel: "when I click some pages (ie settings or a course) the
// screen is blank for a second ... add progress indicator"): the screen's frame, with its header
// (Back, and its title when the address says it), a quiet skeleton and a small spinner in the
// accent, instead of a blank page. main.js shows it for a lazy view after LOADING_DELAY_MS, so a
// quick open never flashes it. No h1: js/screen-name.js names the screen, and moves focus, once its
// own heading arrives; meanwhile a status says what's opening.

import { goBack } from '../router.js';
import { Icon, Logo } from './icon.js';

export const LOADING_DELAY_MS = 150;
/** How long a load runs before it says it's still going (Hillel's instant-feedback rule). */
export const SLOW_MS = 4000;

/** The loading frame for one screen: `title` is a key, a function giving the words, or null (unknown yet); `home` for the home's own header. */
export function screenLoading({ title = null, home = false } = {}) {
    return {
        components: { Icon, Logo },
        setup: () => ({ goBack, home }),
        computed: {
            name() {
                return typeof title === 'function' ? title() : title ? this.t(title) : null;
            },
        },
        template: `
            <div data-ui="screen" class="min-h-dvh bg-ink" aria-busy="true" data-screen-loading>
                <!-- Home's own top bar (app-wrap app-top), so the logo doesn't move when Home arrives. -->
                <header v-if="home" class="app-wrap app-top"><Logo /><span class="spinner ms-auto text-accent" aria-hidden="true"></span></header>
                <!-- Every other screen's bar (components/page.js PageHeader), on the same grid, so Back doesn't move when the screen arrives. -->
                <header v-else class="app-wrap app-bar">
                    <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="t('common.back')" @click="goBack('/')"><Icon name="back" :size="20" /></button>
                    <p v-if="name" class="min-w-0 flex-1 truncate text-[1.75rem] font-extrabold leading-tight tracking-tight text-cream" aria-hidden="true">{{ name }}</p>
                    <span v-else class="skeleton h-8 w-2/3" aria-hidden="true"></span>
                    <span class="spinner ms-auto text-accent" aria-hidden="true"></span>
                </header>
                <div class="app-wrap pb-16 pt-4">
                    <p class="sr-only" role="status">{{ name ? t('common.loadingScreen', { screen: name }) : t('common.loading') }}</p>
                    <div class="mt-4 space-y-4" aria-hidden="true">
                        <div class="skeleton h-12 w-full"></div>
                        <div class="skeleton h-4 w-2/3"></div>
                        <div class="skeleton h-3 w-full"></div>
                        <div class="skeleton h-3 w-5/6"></div>
                    </div>
                    <!-- Still going after a few seconds: said, so a slow line never looks stuck (Hillel, 2026-11-12). -->
                    <p v-if="slow" class="mt-6 text-sm text-muted" data-screen-slow>{{ t('common.stillLoading') }}</p>
                </div>
            </div>`,
        data: () => ({ slow: false }),
        mounted() {
            this.slowTimer = setTimeout(() => (this.slow = true), SLOW_MS); // the words only, never audio
        },
        beforeUnmount() {
            clearTimeout(this.slowTimer);
        },
    };
}

/**
 * Loading inside a screen whose frame is already up (a course's page, a lesson): a quiet skeleton and a spinner at once,
 * "Still loading" after a few seconds, and, once it has `failed`, what happened and Try again (emits retry).
 */
export const LoadingBlock = {
    props: { failed: Boolean },
    emits: ['retry'],
    data: () => ({ slow: false }),
    template: `
        <div class="space-y-4" :aria-busy="failed ? null : 'true'" data-loading-block>
            <template v-if="failed">
                <p class="text-cream" role="alert">{{ t('common.loadFailed') }}</p>
                <button type="button" class="btn-secondary" @click="$emit('retry')">{{ t('common.tryAgain') }}</button>
            </template>
            <template v-else>
                <p class="flex items-center gap-2 text-muted" role="status"><span class="spinner text-accent" aria-hidden="true"></span>{{ slow ? t('common.stillLoading') : t('common.loading') }}</p>
                <div class="space-y-3" aria-hidden="true">
                    <div class="skeleton h-10 w-full"></div>
                    <div class="skeleton h-4 w-2/3"></div>
                    <div class="skeleton h-3 w-5/6"></div>
                </div>
            </template>
        </div>`,
    mounted() {
        this.slowTimer = setTimeout(() => (this.slow = true), SLOW_MS); // the words only, never audio
    },
    beforeUnmount() {
        clearTimeout(this.slowTimer);
    },
};
