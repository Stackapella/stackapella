// New badges, said one celebration at a time (docs/onboarding-review-2.md, Must 5): in a lesson they wait
// for its end and are said there once, merged, as a line in the result (never a toast over the stars,
// the heading or the main button, and never carried into the next lesson); anywhere else, one short
// toast, replacing any badge toast still up. Game mode only: learning.js never says badges in serious.

import { reactive, watch } from 'vue';
import { formatList, t } from '../i18n.js';
import { route } from '../router.js';
import { dismissToast, toast, toasts } from '../components/ui.js';

export const BADGE_TOAST_SECONDS = 3;

/** Badges earned in the lesson running now, not yet said (their names, in order). */
export const badgeNews = reactive({ names: [] });

/** "New badge: X" or "New badges: X, Y and Z". */
export const badgeWords = (names) => (names.length === 1 ? t('badges.new', { name: names[0] }) : t('badges.newMany', { names: formatList(names) }));

function toastBadges(names) {
    for (const item of toasts.list.filter((x) => x.tone === 'badge')) dismissToast(item.id);
    toast(badgeWords(names), { seconds: BADGE_TOAST_SECONDS, tone: 'badge' });
}

/** Says newly earned badges: kept for the lesson's end in a lesson, else one short toast. */
export function sayBadges(names) {
    if (!names.length) return;
    if (route.name === 'learn') {
        for (const name of names) if (!badgeNews.names.includes(name)) badgeNews.names.push(name);
        return;
    }
    toastBadges(names);
}

// A lesson left before its end: what it earned is said once, on the way out.
watch(
    () => route.name,
    (name) => {
        if (name === 'learn' || !badgeNews.names.length) return;
        const names = badgeNews.names.splice(0);
        toastBadges(names);
    },
);

/**
 * The lesson end's line for the badges it brought (NoteResult, LessonEnd): it takes them from the
 * queue as they arrive (the save may land just after the screen) and says them once, after the stars
 * and the count have landed.
 */
export const BadgeLine = {
    data: () => ({ said: [] }),
    template: `<p v-if="said.length" class="ge-badge-line" role="status" data-badge-line>{{ badgeWords(said) }}</p>`,
    setup: () => ({ badgeWords }),
    created() {
        this.stop = watch(
            () => badgeNews.names.length,
            () => {
                if (badgeNews.names.length) this.said.push(...badgeNews.names.splice(0));
            },
            { immediate: true },
        );
    },
    unmounted() {
        this.stop?.();
    },
};
