// The course's account wall (Hillel, 2026-11-06: a free account "after lesson 1 of any course"), one
// component wherever a guest meets it: a lesson past the first (learn.js), the course map's picked
// lesson and a course's page (`compact`). It says what comes with the account (their stars, their note,
// their place in the course, every lesson of every course) and that it's free with no card, never a
// price, a countdown or a second loud frame. Counted as the course's account ask, acct_course: shown once
// a device, yes on any way in, and Back to the course as its Not now. A wall, not a nudge: the asks'
// ledger isn't told.
//
// Experiment wall1 (docs/decisions.md, 2026-11-08), drawn when the wall shows: arm a is the wall as it is
// (Make a free account opens sign-up); arm b puts Google and Apple on the wall itself, the email way a
// link to sign-up, so a guest is one tap from an account rather than a page away.

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, providerSignIn } from '../stores/account.js';
import { learning } from '../stores/learning.js';
import { askNot, askShown, askYes } from '../funnel.js';
import { experiment } from '../experiments.js';
import { midiName } from '../dsp.js';
import { Icon } from './icon.js';

const PROVIDERS = { google: tk('auth.google'), apple: tk('auth.apple') };

export const CourseWall = {
    components: { Icon },
    setup: () => ({ PROVIDERS }),
    props: {
        course: { type: Object, default: null }, // a course from the catalogue, or null for the free course
        lessonId: { type: String, required: true }, // the lesson it opens, to come back to after sign-up
        home: { type: String, default: '' }, // Back to the course's address (the lesson page only)
        compact: Boolean, // the course map's card and a course's page: the heading, the keeps and the way in
        level: { type: Number, default: null }, // its heading's level where the page's outline needs another (Home: 2, under the lesson's h1)
        place: { type: String, required: true, validator: (v) => ['home', 'lesson', 'map', 'page'].includes(v) }, // where it was met, for the counts
    },
    template: `
        <section :class="compact ? 'space-y-3' : 'card space-y-5 p-6'" :aria-labelledby="uid" data-needs-account :data-wall-arm="arm">
            <component :is="'h' + (level ?? (compact ? 3 : 2))" :id="uid" class="font-bold text-cream" :class="compact ? 'text-base' : 'text-2xl tracking-tight'">{{ t('courses.wall.title') }}</component>
            <ul class="space-y-2" data-wall-keeps>
                <li v-for="keep in keeps" :key="keep" class="flex items-start gap-2.5 text-sm text-cream"><Icon name="check" :size="16" class="mt-0.5 shrink-0 text-good" />{{ keep }}</li>
            </ul>
            <p class="text-sm text-muted">{{ t('courses.wall.free') }}</p>
            <div v-if="inline" class="flex flex-col gap-2.5" :class="compact ? '' : 'max-w-sm'" data-wall-providers>
                <button v-for="p in providers" :key="p" type="button" class="btn-secondary w-full justify-center" :data-wall-provider="p" @click="provider(p)">{{ t(PROVIDERS[p]) }}</button>
                <a :href="signUp" class="link inline-flex min-h-6 items-center self-start text-sm" data-needs-account-yes @click.prevent="yes()">{{ t('courses.wall.email') }}</a>
            </div>
            <div v-else class="flex flex-wrap items-center gap-x-5 gap-y-3">
                <a :href="signUp" class="btn-primary" :class="compact ? 'w-full justify-center' : ''" data-needs-account-yes @click.prevent="yes()">{{ t('wrap.makeAccount') }}</a>
            </div>
            <p class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <a v-if="home" :href="home" class="link inline-flex min-h-6 items-center" data-needs-account-not @click.prevent="notNow">{{ t('course.backToCourse') }}</a>
                <a :href="signIn" class="link inline-flex min-h-6 items-center" data-wall-sign-in @click.prevent="navigate(signIn)">{{ t('courses.wall.signIn') }}</a>
            </p>
        </section>`,
    data: () => ({ uid: 'wall-' + Math.random().toString(36).slice(2, 8) }),
    computed: {
        next: (vm) => '/learn/' + vm.lessonId,
        signUp: (vm) => `/signin?mode=signup&for=course&next=${encodeURIComponent(vm.next)}`,
        signIn: (vm) => `/signin?next=${encodeURIComponent(vm.next)}`,
        /** wall1's arm, drawn as the wall shows (exposure); the wall as it is until the arm is known. */
        arm() {
            const { arm, ready } = experiment('wall1');
            return ready && arm ? arm : null;
        },
        providers: () => (account.config?.methods ?? Object.keys(PROVIDERS)).filter((m) => m in PROVIDERS),
        inline: (vm) => vm.arm === 'b' && vm.providers.length > 0,
        /** What comes with the account: the stars this course has from them, their note, their place, every lesson. */
        keeps() {
            const lessons = this.course ? learning.progress.courses?.[this.course.id]?.lessons ?? {} : learning.progress.lessons ?? {};
            const stars = Object.values(lessons).reduce((sum, kept) => sum + (kept?.stars ?? 0), 0);
            const title = localized(this.course?.title ?? learning.course?.title) || '';
            const home = learning.progress.home;
            return [
                ...(stars ? [t('courses.wall.keepStars', { count: stars })] : []),
                ...(home != null && !this.course ? [t('courses.wall.keepNote', { note: midiName(home) })] : []),
                ...(title ? [t('courses.wall.keepPlace', { course: title })] : []),
                t('courses.wall.keepAll'),
            ];
        },
    },
    mounted() {
        askShown('acct_course');
        askShown(`acct_course_${this.place}`);
    },
    methods: {
        yes() {
            askYes('acct_course');
            askYes(`acct_course_${this.place}`);
            navigate(this.signUp);
        },
        provider(p) {
            askYes('acct_course');
            askYes(`acct_course_${this.place}`);
            providerSignIn(p, { intended: this.next });
        },
        notNow() {
            askNot('acct_course');
            navigate(this.home);
        },
    },
};
