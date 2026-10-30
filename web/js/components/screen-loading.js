// A screen that's still loading (Hillel: "when I click some pages (ie settings or a course) the
// screen is blank for a second ... add progress indicator"): the screen's frame, with its header
// (Back, and its title when the address says it), a quiet skeleton and a small spinner in the
// accent, instead of a blank page. main.js shows it for a lazy view after LOADING_DELAY_MS, so a
// quick open never flashes it. No h1: js/screen-name.js names the screen, and moves focus, once its
// own heading arrives; meanwhile a status says what's opening.

import { goBack } from '../router.js';
import { Icon, Logo } from './icon.js';

export const LOADING_DELAY_MS = 150;

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
            <div class="min-h-dvh bg-ink px-4 pb-16 pt-[max(0.5rem,env(safe-area-inset-top))] md:px-8" aria-busy="true" data-screen-loading>
                <div class="mx-auto w-full max-w-5xl">
                    <header class="flex min-h-18 items-center gap-3 py-4">
                        <Logo v-if="home" />
                        <button v-else type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream transition" :aria-label="t('common.back')" @click="goBack('/')"><Icon name="back" :size="20" /></button>
                        <p v-if="!home && name" class="min-w-0 flex-1 truncate text-[1.75rem] font-extrabold leading-tight tracking-tight text-cream" aria-hidden="true">{{ name }}</p>
                        <span v-else-if="!home" class="skeleton h-8 w-2/3" aria-hidden="true"></span>
                        <span class="spinner ms-auto text-accent" aria-hidden="true"></span>
                    </header>
                    <p class="sr-only" role="status">{{ name ? t('common.loadingScreen', { screen: name }) : t('common.loading') }}</p>
                    <div class="mt-4 space-y-4" aria-hidden="true">
                        <div class="skeleton h-12 w-full"></div>
                        <div class="skeleton h-4 w-2/3"></div>
                        <div class="skeleton h-3 w-full"></div>
                        <div class="skeleton h-3 w-5/6"></div>
                    </div>
                </div>
            </div>`,
    };
}
