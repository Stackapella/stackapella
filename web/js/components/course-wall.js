// The course's account wall (Hillel, 2026-11-06: a free account "after lesson 1 of any course"), one
// component wherever a guest meets it: a lesson past the first (learn.js), the course map's picked
// lesson and a course's page (`compact`). It says what comes with the account (their stars, their note,
// their place in the course, every lesson of every course) and that it's free with no card, never a
// price, a countdown or a second loud frame. Counted as the course's account ask, acct_course: shown once
// a device, yes on any way in, and Back to the course as its Not now. A wall, not a nudge: the asks'
// ledger isn't told.
//
// Google and Apple are on the wall itself, the email way a link to sign-up, so a guest is one tap from an account
// rather than a page away (wall1's arm b, for everyone since Hillel's "Stop both, decide", 2026-11-12; docs/decisions.md).

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account, providerSignIn } from '../stores/account.js';
import { learning } from '../stores/learning.js';
import { askNot, askShown, askYes } from '../funnel.js';
import { midiName } from '../dsp.js';
import { Icon } from './icon.js';
import { game } from '../skin.js';
import { addTomorrow, nextUpLine, sendMyselfTheLink } from '../next-up.js';
import { countUnitSong, unitSongProject } from '../unit-song.js';
import { toast } from './ui.js';

const PROVIDERS = { google: tk('auth.google'), apple: tk('auth.apple') };

export const CourseWall = {
    components: { Icon },
    setup: () => ({ PROVIDERS, navigate, addTomorrow, sendMyselfTheLink }), // navigate: the template's sign-in link (Sentry, 2026-11-10: it wasn't there)
    props: {
        course: { type: Object, default: null }, // a course from the catalogue, or null for the free course
        lessonId: { type: String, required: true }, // the lesson it opens, to come back to after sign-up
        home: { type: String, default: '' }, // Back to the course's address (the lesson page only)
        compact: Boolean, // the course map's card and a course's page: the heading, the keeps and the way in
        level: { type: Number, default: null }, // its heading's level where the page's outline needs another (Home: 2, under the lesson's h1)
        place: { type: String, required: true, validator: (v) => ['home', 'lesson', 'map', 'page'].includes(v) }, // where it was met, for the counts
    },
    template: `
        <section :class="compact ? 'space-y-3' : 'card space-y-5 p-6'" :aria-labelledby="uid" data-needs-account>
            <component :is="'h' + (level ?? (compact ? 3 : 2))" :id="uid" class="font-bold text-cream" :class="compact ? 'text-base' : 'text-2xl tracking-tight'">{{ t('courses.wall.title') }}</component>
            <!-- What waits, named (docs/retention-plan.md, idea 2): the lesson this wall opens. -->
            <p v-if="nextUp" class="text-sm text-cream" translate="no" data-wall-next>{{ nextUp }}</p>
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
            <!-- One quiet way back for a guest not making an account yet, idea 2: a text link, never a button. The share sheet
                 where the device has one, else one calendar entry for tomorrow, morning or evening, made here. -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <p class="flex flex-wrap items-center gap-x-4 gap-y-2" data-wall-back>
                <button v-if="canShare" type="button" class="link inline-flex min-h-6 items-center" data-wall-send @click="sendMyselfTheLink(lessonId)">{{ t('courses.wall.sendLink') }}</button>
                <button v-else-if="!choosingTime" type="button" class="link inline-flex min-h-6 items-center" data-wall-calendar @click="choosingTime = true">{{ t('courses.wall.calendar') }}</button>
                <template v-else>
                    <span class="text-muted">{{ t('courses.wall.calendar') }}:</span>
                    <button type="button" class="link inline-flex min-h-6 items-center" data-wall-calendar-at="morning" @click="addTomorrow(lessonId, 'morning')">{{ t('courses.wall.morning') }}</button>
                    <button type="button" class="link inline-flex min-h-6 items-center" data-wall-calendar-at="evening" @click="addTomorrow(lessonId, 'evening')">{{ t('courses.wall.evening') }}</button>
                </template>
            </p>
            <!-- Unit 1's song (retention row 8, idea 4): for a guest not ready for an account, a minute inside a real song first,
                 opened straight in Sing along on its tune. Beside the way back, its own element; the free course's full wall only. -->
            <p v-if="song" class="flex items-center" data-wall-song><button type="button" class="link inline-flex min-h-6 items-center" :disabled="songOpening" data-wall-song-open @click="singSong">{{ t('courses.wall.songFirst') }}</button></p>
            </div>
        </section>`,
    data: () => ({ uid: 'wall-' + Math.random().toString(36).slice(2, 8), choosingTime: false, canShare: typeof navigator.share === 'function', songOpening: false }),
    computed: {
        next: (vm) => '/learn/' + vm.lessonId,
        nextUp: (vm) => nextUpLine(vm.lessonId),
        signUp: (vm) => `/signin?mode=signup&for=course&next=${encodeURIComponent(vm.next)}`,
        signIn: (vm) => `/signin?next=${encodeURIComponent(vm.next)}`,
        /** Unit 1's song, offered on the free course's full wall (not a Plus course's, not the map's compact card). */
        song: (vm) => (!vm.course && !vm.compact ? learning.course?.units?.[0]?.song ?? null : null),
        providers: () => (account.config?.methods ?? Object.keys(PROVIDERS)).filter((m) => m in PROVIDERS),
        inline: (vm) => vm.providers.length > 0,
        /** What comes with the account: the stars this course has from them, their note, their place, every lesson. */
        keeps() {
            const lessons = this.course ? learning.progress.courses?.[this.course.id]?.lessons ?? {} : learning.progress.lessons ?? {};
            const stars = Object.values(lessons).reduce((sum, kept) => sum + (kept?.stars ?? 0), 0);
            const done = Object.values(lessons).filter((kept) => kept?.stars).length;
            const title = localized(this.course?.title ?? learning.course?.title) || '';
            const home = learning.progress.home;
            return [
                // Serious mode counts lessons, never stars (docs/skins-plan.md).
                ...(stars ? [game() ? t('courses.wall.keepStars', { count: stars }) : t('courses.wall.keepLessons', { count: done })] : []),
                ...(home != null && !this.course ? [t('courses.wall.keepNote', { note: midiName(home) })] : []),
                ...(title ? [t('courses.wall.keepPlace', { course: title })] : []),
                t('courses.wall.keepAll'),
            ];
        },
    },
    mounted() {
        askShown('acct_course');
        askShown(`acct_course_${this.place}`);
        if (this.song) countUnitSong('shown', 'wall');
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
        /** Sing unit 1's song first: its tune in Sing along, the other voices around it (counted us_tap:wall, us_kept:wall). */
        async singSong() {
            this.songOpening = true;
            try {
                const project = await unitSongProject(this.song, { lessonId: this.lessonId, unitId: learning.course.units[0].id, place: 'wall' });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.error("Unit 1's song could not open from the wall", error);
                toast(t('course.songFailed'), { tone: 'error' });
            } finally {
                this.songOpening = false;
            }
        },
        notNow() {
            askNot('acct_course');
            navigate(this.home);
        },
    },
};
