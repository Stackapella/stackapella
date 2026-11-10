// Home's "Today" line in the course card (docs/home-returning-plan.md; js/home-today.js picks the
// case): past a singer's first week, one suggestion, shown in experiment ret1's arm b. Its button is
// secondary, so the card's own Continue stays the one primary; after the free course, the song is
// the first choice and a course the second, quieter one (Hillel, 2026-11-01). Today's challenge
// (docs/daily-challenge-plan.md) on a day with nothing else: game mode's sheet with its board, serious
// mode's calm line naming the song and part with Sing it. It's also offered in the first week, from its
// second day once that day's 5 minutes are sung, to every singer (ret1 doesn't reach the first week);
// past it, only in ret1's arm b, as before.

import { localized, t, tk } from '../i18n.js';
import { navigate } from '../router.js';
import { account } from '../stores/account.js';
import { learning, loadPlusCourses } from '../stores/learning.js';
import { loadProjects, projects } from '../stores/projects.js';
import { canStart, listTemplates, loadTemplate, templatePartName } from '../templates.js';
import { courseSummary, courseVisible } from '../courses.js';
import { dayOf } from '../course.js';
import { whatsNext } from '../whatsnext.js';
import { daysAway } from '../asks.js';
import { experiment } from '../experiments.js';
import { count } from '../usage.js';
import { local } from '../storage.js';
import { game, sk } from '../skin.js';
import { effect } from '../game/effects.js';
import { lazyComponent } from '../game/modules.js';
import { dailyOpen, dailyPick, takeChallenge } from '../daily-pick.js';

const DailySheet = lazyComponent('daily', 'DailySheet'); // game mode's only: loaded with game mode
import { inFirstWeek } from './voice-care.js';
import { isStuck, todayCase, watchNext } from '../home-today.js';

// Each case's line: serious mode's, then game mode's.
const LINES = {
    gap: [tk('home.today.gap'), tk('game.today.gap')],
    stuck: [tk('home.today.stuck'), tk('game.today.stuck')],
    done: [tk('home.today.done'), tk('game.today.done')],
    daily: [tk('home.today.daily'), tk('game.today.daily')],
};
const WATCH = 'stackapella.todayWatch'; // { id, day }: the next lesson, and the day it became next
const SAID = 'stackapella.todaySaid'; // { id, day }: the lesson the slower try was offered for

function readJson(key) {
    try {
        return JSON.parse(local.get(key) || 'null');
    } catch (error) {
        console.info('A Today record was unreadable', error?.message ?? error);
        return null;
    }
}

export const TodayLine = {
    components: { DailySheet },
    // `lessonId`: the next lesson (null when none waits); `finished`: every lesson open to them has stars.
    props: { lessonId: { type: String, default: null }, finished: { type: Boolean, default: false } },
    data: () => ({ watch: null, said: null, song: null, course: null, picked: false, sheet: false, counted: null, dailySong: null, starting: false }),
    setup: () => ({ dailyPick, game, localized, sk }),
    template: `
        <div v-if="shown" class="mb-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-line pb-4" :data-today="kase">
            <p class="min-w-0 font-semibold text-cream">{{ line }}</p>
            <div class="flex flex-wrap gap-2">
                <button v-if="kase === 'gap'" type="button" class="btn-secondary btn-sm" @click="go('/workout')">{{ t('home.today.gapGo') }}</button>
                <button v-else-if="kase === 'stuck'" type="button" class="btn-secondary btn-sm" @click="go('/learn/' + lessonId + '?slower=1')">{{ t('home.today.stuckGo') }}</button>
                <template v-else-if="kase === 'done'">
                    <button v-if="song" type="button" class="btn-secondary btn-sm" data-today-song @click="go('/?song=' + song.id, 'song')"><span translate="no">{{ t('home.today.doneSong', { title: song.title }) }}</span></button>
                    <button v-if="course" type="button" class="btn-ghost btn-sm" data-today-course @click="go('/learn/c/' + course.id, 'course')"><span translate="no">{{ t('home.today.doneCourse', { title: localized(course.title) }) }}</span></button>
                </template>
                <button v-else-if="kase === 'daily' && game()" type="button" class="btn-secondary btn-sm" @click="tap(); sheet = true">{{ t('home.today.dailyGo') }}</button>
                <button v-else-if="kase === 'daily'" type="button" class="btn-secondary btn-sm" :disabled="starting || !dailySong" data-today-daily-sing @click="sing">{{ t('home.today.dailySing') }}</button>
            </div>
            <DailySheet v-if="sheet && game() && dailyPick.today" :challenge="dailyPick.today" @close="sheet = false" />
        </div>`,
    created() {
        this.said = readJson(SAID);
        this.track();
    },
    watch: {
        lessonId() {
            this.track();
        },
        kase: {
            immediate: true,
            handler(now) {
                if (now === 'done' && !this.picked) this.pick();
                if (now === 'daily' && !game()) this.loadDailySong();
            },
        },
        shown: {
            immediate: true,
            handler(now) {
                if (!now || this.counted === this.kase) return;
                this.counted = this.kase;
                count(`home_today_${this.kase}_shown`);
                if (this.kase === 'stuck' && this.said?.id !== this.lessonId) {
                    this.said = { id: this.lessonId, day: dayOf() };
                    local.set(SAID, JSON.stringify(this.said));
                }
            },
        },
    },
    computed: {
        /** The case for today. In a new singer's first week the first-week card speaks, so only the challenge, and only once it may be offered. */
        kase() {
            const daily = (game() ? effect('daily') : true) && !!dailyPick.today;
            if (inFirstWeek()) return daily && dailyOpen() ? 'daily' : null;
            const today = dayOf();
            return todayCase({
                away: daysAway(),
                workoutOpen: true, // unlimited workouts on every plan (2026-11-07)
                stuck: !!this.lessonId && isStuck(this.watch, learning.progress.practice, today, this.said),
                finished: this.finished,
                daily,
            });
        },
        /** Experiment ret1's arm b shows it (drawn only when there is something to say). */
        shown() {
            if (!this.kase) return false;
            if (this.kase === 'daily' && !game() && !this.dailySong) return false; // serious names the song: drawn once it's known
            if (inFirstWeek()) return true; // the challenge, the only first-week case (ret1 starts past it)
            if (this.kase === 'done' && !this.song && !this.course) return false;
            const { arm, ready } = experiment('ret1');
            return ready && arm === 'b';
        },
        line() {
            if (this.kase === 'daily' && !game()) return t('home.today.dailySong', { title: this.dailySong.title, part: this.dailyPart });
            return sk(...LINES[this.kase]);
        },
        dailyPart() {
            const part = this.dailySong?.parts.find((p) => p.key === dailyPick.today?.partKey);
            return part ? templatePartName(part) : '';
        },
    },
    methods: {
        /** The next lesson's watch: kept while it stays next, started again when it changes. */
        track() {
            if (!this.lessonId) return;
            const kept = readJson(WATCH);
            const now = watchNext(kept, this.lessonId, dayOf());
            if (now !== kept) local.set(WATCH, JSON.stringify(now));
            this.watch = now;
        },
        /** After the free course: a song in your range (What's next's pick), and a course to try. */
        async pick() {
            this.picked = true;
            try {
                await loadPlusCourses();
                const open = learning.catalogue.filter((e) => courseVisible(e, account.me, account.config, account.loaded) && learning.courses[e.id]);
                const summary = (e) => courseSummary(learning.courses[e.id], learning.progress);
                this.course = open.find((e) => summary(e).started && summary(e).done < summary(e).total) ?? open.find((e) => !summary(e).started) ?? null;
                const [songs] = await Promise.all([listTemplates(), loadProjects()]);
                const finished = projects.list.filter((p) => p.template && p.everyPart).map((p) => p.template.id);
                const picks = whatsNext({ songs, range: learning.progress.range, finished, startable: (s) => canStart(s, !!account.me) });
                this.song = (picks.find((p) => p.kind === 'fits') ?? picks[0])?.song ?? null;
            } catch (error) {
                console.warn("Today's next steps not worked out", error);
            }
        },
        /** Serious mode's line names the day's song and part. */
        async loadDailySong() {
            const id = dailyPick.today?.templateId;
            if (!id || this.dailySong?.id === id) return;
            try {
                this.dailySong = await loadTemplate(id);
            } catch (error) {
                console.warn("Today's song did not load", error);
            }
        },
        /** Serious mode's Sing it: straight to the song on the day's part (the challenge opened). */
        async sing() {
            this.tap();
            count('daily_viewed');
            this.starting = true;
            try {
                await takeChallenge(dailyPick.today, this.dailySong);
            } catch (error) {
                console.warn('The challenge could not start', error);
                this.starting = false;
            }
        },
        tap(which = null) {
            count(`home_today_${this.kase}${which ? '_' + which : ''}_tap`);
        },
        go(path, which = null) {
            this.tap(which);
            navigate(path);
        },
    },
};
