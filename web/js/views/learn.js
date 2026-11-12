// A lesson (docs/vocal-coaching-plan.md, "A lesson"): its steps one at a time with a progress bar
// (learn, try, play it through for stars, use it in a real song, record), then the end screen with
// the stars, the XP and Replay or Continue. At /learn/<lesson id>. A step that opens a song (Use
// it, Record) makes or reopens the course's own copy of it, and Sing brings the learner back here.
// In a Sharpen up session (js/sharpen.js) a lesson opens at its scored part and goes on to the next.

import { VoiceCareLine } from '../components/voice-care.js';
import { markRaw } from 'vue';
import { game, sk } from '../skin.js';
import { arrivedToday, askShown, askYes, first, variant } from '../funnel.js';
import { askSeen } from '../asks.js';
import { formatDate, formatNumber, localized, t, tk } from '../i18n.js';
import { api, ApiError } from '../api.js';
import { account } from '../stores/account.js';
import { backTo, goBack, navigate } from '../router.js';
import { addXp, cappedWidening, dayOf, nextLesson, strictnessFor, starsFor, unitOpen, voiceType } from '../course.js';
import { addCourseTime, COOL_DOWN, finishBoss, finishLesson, findLesson, KEY_REACH, knownRange, SONG_USED_SECONDS, loadCooldown, learning, loadLearning, loadPlusCourses, loadSongCourse, noteSung, practised, remember, setCoursePersonal, setHome, setLessonKey } from '../stores/learning.js';
import { keyName } from '../theory.js';
import { keepScore } from '../stores/cloud.js';
import { audio, closeMic, engine } from '../audio.js';
import { deviceOf, micExplained } from '../mic-help.js';
import { playSound, starsSound } from '../sounds.js';
import { prefetchStores, projectsStore } from '../lazy-stores.js';
import { loadTemplate, partsFor, templatePartName, validateTemplate } from '../templates.js';
import { trackInWorker } from '../pitchtrack.js';
import { scoreTake } from '../scoring.js';
import { PART_COLORS } from '../theme.js';
import { midiName } from '../dsp.js';
import { session as tabStorage } from '../storage.js';
import { VoiceCapture } from '../components/voice-capture.js';
import { InAppCard } from '../components/mic-help.js';
import { ChordMoment } from '../components/chord-moment.js';
import { ThenAndNow } from '../components/then-and-now.js';
import { CourseWall } from '../components/course-wall.js';
import { CoursePart } from '../components/course-part.js';
import { VoiceCard } from '../components/voice-card.js';
import { LearnVisual } from '../components/learn-visual.js';
import { Exercise, KeepSong, LessonEnd, NoteResult, prefetchLesson, TakeResult } from '../components/lazy-lesson.js';
import { FactCard } from '../components/fact-card.js';
import { daysInARow, easyRange, endLine, HEARD, lessonMinutes, levelWord, noteLine, resultLine, unitPath } from '../results.js';
import { celebrate } from '../celebrate.js';
import { Icon, Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { count, countFor } from '../usage.js';
import { claimShare, streakMilestone } from '../share-moment.js';
import { SessionWrap, ShareResult } from '../components/lazy-results.js';
import { courseLessons, courseView, courseVisible, guestOpen, lessonAccess, lessonAfter, songCourse, songCourseVisible } from '../courses.js';
import { pageLesson, takePageLesson } from '../page-lesson.js';
import { isSongLesson, songExercise, songFit, songOf, songTranspose } from '../song-course.js';
import { underLayers } from '../under-mix.js';
import { realKeyFit } from '../real-key.js';
import { countUnitSong, unitSongOffer, unitSongProject } from '../unit-song.js';
import { lessonFor } from '../experiments.js';
import { isScored, prefetchSteps, stepOf } from '../lesson-steps.js';
import { clearMs, cue, lazyComponent, load, markTitleSeen, titleSeen } from '../game/modules.js';
import { endSharpen, sharpenAt, sharpenOn, sharpenStart } from '../sharpen.js';
import { courseRules, effect, setCourseRules } from '../game/effects.js';
import { flipKeyBase } from '../style-scores.js';
import { flipLesson, flipRest, flipStarted, flipStopped } from '../flip-rest.js';
import { whistleBase } from '../whistle.js';
import { capMinutes, capReached } from '../daily-cap.js';
import { slowed } from '../home-today.js';
import { BossBar, BossEnd, BossIntro } from '../components/boss.js';
import { BOSS_PASS, bossBeaten, bossOpen, bossPercent, bossResult, challengeOf } from '../boss.js';
import { nextUpLine } from '../next-up.js';

// The song step's key line, in Sing's own words for the part that fits (views/sing.js FIT_WORDS).
const FIT_WORDS = { real: tk('sing.realKey'), fits: tk('sing.fitsYourRange'), lower: tk('sing.fitsYourRangeLower'), higher: tk('sing.fitsYourRangeHigher'), closest: tk('course.fitClosest') };
const STEP_KIND = { learn: tk('course.step.learn'), try: tk('course.step.try'), play: tk('course.step.play'), use: tk('course.step.use'), record: tk('course.step.record'), home: tk('course.step.home') };
// Words for the scorer's tips (js/scoring.js TIPS).
const TIP_WORDS = { breathSupport: tk('course.tip.breathSupport'), relaxHigh: tk('course.tip.relaxHigh'), listenFirst: tk('course.tip.listenFirst'), onsets: tk('course.tip.onsets'), breatheEarly: tk('course.tip.breatheEarly'), waitForBeat: tk('course.tip.waitForBeat') };
const DEFAULT_HOME = 57; // A3, where most untrained voices are comfortable, until unit 1 finds theirs
const FLIP_UNTIL_FOUND = 5; // semitones above the comfortable note, until the course finds the singer's flip

const TitleCard = lazyComponent('titleCard', 'TitleCard'); // game mode's only: loaded with game mode

// A friend's note (docs/improvement-log.md, row 4): lesson 1's shared card links here with ?their=<note> (and
// ?ref=share-note), so the friend is asked for their own note with the sender's beside it. Kept for the tab, only a
// note's name ("A3", "C#4"); nothing about who sent it.
const FRIEND_KEY = 'stackapella.friendNote';
const NOTE_NAME = /^[A-G]#?\d$/;

/** Takes ?their= from the address once (counted as the friend's landing seen), then keeps it for the tab. */
function takeFriendNote(search = location.search) {
    const their = new URLSearchParams(search).get('their');
    if (!their || !NOTE_NAME.test(their)) return;
    if (tabStorage.get(FRIEND_KEY) !== their) count('note_friend_seen');
    tabStorage.set(FRIEND_KEY, their);
}

export const LearnView = {
    components: { VoiceCapture, BossBar, BossEnd, BossIntro, VoiceCareLine, ChordMoment, SessionWrap, ThenAndNow, CoursePart, CourseWall, VoiceCard, FactCard, Exercise, InAppCard, KeepSong, LearnVisual, LessonEnd, NoteResult, TakeResult, Icon, Logo, ShareResult, TitleCard },
    props: { id: { type: String, required: true } },
    // `kept`: this visit's sung steps, their results and takes by step index (a later step may build on
    // one: js/lesson-steps.js's `earlier`), emptied on replay and gone when the lesson closes.
    // `sharpen`: this lesson's place in a Sharpen up session (`{ place, total, start, nextId }`), or null.
    data: () => ({ sharpen: null, guiding: false, step: 0, micAsked: false, result: null, done: null, ran: false, checking: false, error: null, askHeard: false, tip: null, used: false, skippedHome: false, noSound: false, skipped: false, micPermission: null, noteClip: null, kept: { results: {}, takes: {} }, titleCard: null, songLooked: false, songStep: null, songError: null, flipRestAt: null, capRest: false, capFrom: null, takesOn: {}, bossStarted: false, bossNotes: null, bossLive: 0, bossSaid: '', fitLine: '', unitSongOpening: false, startPending: false, slower: new URLSearchParams(location.search).get('slower') === '1' }),
    // A step's Then and now offers its card with the lesson's name and link (docs/design-rules.md, Sharing in lessons).
    provide() {
        return { lessonShare: () => this.thenShareContext };
    },
    setup: () => ({ account, learning, nextUpLine, STEP_KIND, localized, PART_COLORS, TIP_WORDS, HEARD, game, sk, askYes, effect, bossResult, bossBeaten , courseRules, streakMilestone }),
    template: `
        <div data-ui="screen" class="flex min-h-dvh flex-col bg-ink">
            <!-- Game mode's title card, the first time a lesson opens (docs/game-elements-plan.md) -->
            <TitleCard v-if="titleCard && variant.ready" v-bind="titleCard" @done="titleCard = null" />
            <!-- The bar the window's width, Back near its edge (16 px), never further in than the site's logo (Hillel, 2026-11-10,
                 docs/design-rules.md); the bar's height is every screen's (app-bar), its rule the page's width. -->
            <header class="border-b border-line"><div class="app-bar px-4">
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream" :aria-label="t('course.backToCourse')" @click="leave"><Icon name="back" :size="22" /></button>
                <div v-if="entry" class="min-w-0 flex-1">
                    <p v-if="sharpen" class="truncate text-xs font-semibold text-accent-strong" data-sharpen-place>{{ t('sharpen.place', { place: sharpen.place, total: sharpen.total }) }}</p>
                    <p v-else class="truncate text-xs text-muted" translate="no">{{ localized(entry.unit.title) }}</p>
                    <p class="line-clamp-2 font-bold leading-snug text-cream" translate="no">{{ localized(entry.lesson.title) }}</p>
                </div>
                <!-- The step, once (80.png: no second label over the heading): its count and the bars. -->
                <div v-if="entry && !done && needsAccount === false && !flipRestAt && !capRest && !(entry.boss && (!bossStarted || effect('boss')))" class="flex shrink-0 items-center gap-2">
                    <span class="text-xs tabular-nums text-muted">{{ t('course.stepCount', { step: step + 1, total: entry.lesson.steps.length }) }}<span v-if="slower" data-slower> · {{ t('course.slower') }}</span><span class="sr-only"> · {{ t(STEP_KIND[current.kind] ?? STEP_KIND.play) }}</span></span>
                    <span class="flex w-14 gap-1 sm:w-24" aria-hidden="true" data-step-bars><span v-for="(s, i) in entry.lesson.steps" :key="i" class="h-1.5 flex-1 rounded-full" :class="i <= step ? 'bg-accent' : 'bg-line'"></span></span>
                </div>
            </div></header>
            <!-- A lesson's end sits in the middle of a desktop's height, not at the top over an empty half (Hillel: "too much bottom space"). -->
            <div class="mx-auto flex w-full flex-col gap-6 px-4 pb-[max(4rem,calc(env(safe-area-inset-bottom)+2rem))] pt-8 min-[900px]:px-10" :class="flipRestAt || capRest ? 'max-w-xl flex-1 justify-center' : entry?.boss && (!bossStarted || done) ? 'max-w-xl md:flex-1 md:justify-center md:pb-16' : split ? 'max-w-[1200px] md:pt-16' : done && !done.unheard && !noteDone ? 'max-w-5xl md:flex-1 md:justify-center md:pb-16' : done && noteDone ? 'max-w-3xl' : takeResult && !done ? 'max-w-4xl' : 'max-w-xl'">
                <p v-if="!learning.loaded || !variant.ready || (!entry && !learning.coursesLoaded && !songLooked)" class="text-muted" role="status">{{ t('course.loading') }}</p>
                <p v-else-if="!entry" class="text-cream">{{ t('course.noLesson') }} <a href="/" class="link" @click.prevent="navigate('/')">{{ t('course.backToCourse') }}</a></p>
                <!-- Signed out past lesson 1 of a course (Hillel, 2026-11-06): a free account, never a price. -->
                <!-- The account still being checked where a guest would meet the wall: the lesson's own wait, never a flash of the wall. -->
                <p v-else-if="needsAccount === null" class="text-muted" role="status" data-account-checking>{{ t('course.loading') }}</p>
                <CourseWall v-else-if="needsAccount" place="lesson" :course="entry.course" :lesson-id="entry.lesson.id" :home="courseHome" class="max-w-xl" />
                <p v-else-if="!open" class="text-cream">{{ entry.boss ? sk('boss.lockedSerious', 'boss.locked') : sk('course.lockedSerious', 'course.locked') }} <a href="/" class="link" @click.prevent="navigate('/')">{{ t('course.backToCourse') }}</a></p>
                <!-- A unit's boss round (docs/boss-round-plan.md): its intro before the first step, and its own end. -->
                <BossIntro v-else-if="entry.boss && !bossStarted && !done" ref="bossIntro" :unit="entry.unitIndex + 1" :unit-title="localized(entry.unit.title)" :title="localized(entry.lesson.title)" :best="bossResult(learning.progress, entry.unit.id)?.best ?? null" :beaten="bossBeaten(learning.progress, entry.unit.id)" :ready="!!bossNotes" @start="bossStart" @later="goBack('/learn')" />
                <BossEnd v-else-if="done && done.boss" ref="bossEnd" :done="done" :title="localized(entry.lesson.title)" @again="bossAgain" @back="goBack('/learn')" />
                <!-- A course's daily cap (js/daily-cap.js, First steps to belting): checked as one of its lessons opens, never mid-lesson. -->
                <section v-else-if="capRest" class="card flex w-full flex-col items-center gap-5 p-6 text-center md:p-8" data-ui="cap-rest" data-cap-rest>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('course.dailyCap.title') }}</h1>
                    <p class="max-w-prose text-muted">{{ t('course.dailyCap.line') }}</p>
                    <button type="button" class="btn-primary" @click="goBack(courseHome)">{{ t('course.backToCourse') }}</button>
                </section>
                <!-- Yodeling's five-minute flip cap (js/flip-rest.js): checked as a flip lesson opens, never mid-lesson. -->
                <section v-else-if="flipRestAt" class="card flex w-full flex-col items-center gap-5 p-6 text-center md:p-8" data-flip-rest>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ t('course.flipRest.title') }}</h1>
                    <p class="max-w-prose text-muted">{{ t('course.flipRest.line', { time: flipRestTime }) }}</p>
                    <button type="button" class="btn-primary" @click="goBack(courseHome)">{{ t('course.backToCourse') }}</button>
                </section>

                <!-- The end screen: couldn't hear (nothing earned, nothing lost) -->
                <section v-else-if="done && done.unheard" class="flex flex-col items-center gap-5 text-center" aria-labelledby="done-title">
                    <h1 id="done-title" ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ done.skipped ? t('course.done.skippedTitle') : sk('course.done.unheardTitle', 'game.done.continue') }}</h1>
                    <p class="max-w-prose text-muted">{{ done.skipped ? sk('course.done.skippedSerious', 'course.done.skipped') : sk('course.done.unheardSerious', 'course.done.unheard') }}</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <button type="button" class="btn-primary" @click="replay">{{ t('course.tryAgain') }}</button>
                        <button type="button" class="btn-secondary" @click="continueOn">{{ next ? t('course.next') : t('course.backToCourse') }}</button>
                    </div>
                </section>
                <!-- Your comfortable note (ux-spec 5.8, 48.png): the note, big, with the keyboard round it -->
                <section v-else-if="done && noteDone" class="flex flex-col items-center gap-6" aria-labelledby="done-title">
                    <h1 id="done-title" ref="heading" tabindex="-1" class="sr-only">{{ t(done.word) }}</h1>
                    <NoteResult ref="noteResult" :home="learning.progress.home" :range="easyRange(learning.progress.home, learning.progress.range)" :line="noteLine(learning.progress.home, voiceType(learning.progress.range))" :stars="done.stars" :xp="done.xp" :fresh="done.fresh" />
                    <!-- Their note beside the friend's who sent the card (docs/improvement-log.md, row 4). -->
                    <p v-if="friendNote && learning.progress.home !== null" class="text-center text-cream" translate="no" data-friend-compare>{{ t('course.friendNote.both', { theirs: friendNote, yours: midiName(Math.round(learning.progress.home)) }) }}</p>
                    <!-- Your note in a chord (onboarding item 3): the end of the note you held, with two guide voices. -->
                    <ChordMoment v-if="noteClip && chordMoment" :clip="noteClip" :note="learning.progress.home" :color="color" />
                    <div v-if="askHeard" class="w-full space-y-3 border-t border-line pt-5 text-center">
                        <p class="text-sm text-muted">{{ t('results.heard') }}</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            <button v-for="(label, value) in HEARD" :key="value" type="button" class="chip tap-44 hover:text-cream" @click="saveHeard(value)">{{ t(label) }}</button>
                            <button type="button" class="chip tap-44 text-muted hover:text-cream" @click="askHeard = false">{{ t('course.heard.skip') }}</button>
                        </div>
                    </div>
                    <!-- What waits, named (docs/retention-plan.md, idea 2), as the course wall says it: lesson 1's end is where a first session often ends. -->
                    <p v-if="next && nextUpLine(next.id)" class="text-sm text-muted" translate="no" data-end-next-line>{{ nextUpLine(next.id) }}</p>
                    <!-- One primary way on (onboarding review 2, 7): Next lesson; sharing and singing again are quieter. -->
                    <div class="flex flex-wrap justify-center gap-3">
                        <button type="button" class="btn-primary order-first basis-full sm:order-last sm:basis-auto" @click="continueOn">{{ next ? t('course.next') : t('course.backToCourse') }}</button>
                        <!-- Your note, to share (onboarding step 6): the most shareable fact of the first session. -->
                        <ShareResult :label="t('results.share.note')" :note="noteShare" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" />
                        <button type="button" class="btn-ghost" @click="replay">{{ done.stars < 3 && game() ? t('results.playAgainStars') : t('course.replay') }}</button>
                    </div>
                </section>
                <!-- Lesson complete (ux-spec 5.8, 50.png and 51.png) -->
                <!-- An unscored lesson's end (the cool-down): its own line and one way on, no stars. -->
                <section v-else-if="done && done.unscored" class="flex flex-col items-center gap-6 py-10 text-center" data-unscored-end>
                    <h1 ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ localized(entry.lesson.endLine) || t('course.cooldown.end') }}</h1>
                    <button type="button" class="btn-primary" data-unscored-done @click="goBack(courseHome)">{{ t('common.done') }}</button>
                </section>
                <template v-else-if="done">
                    <LessonEnd ref="end" :done="done" :path="path" :next="upNext" :ask-heard="askHeard" @next="continueOn" @replay="replay" @heard="saveHeard" @skip-heard="askHeard = false">
                        <template v-if="unitShare" #share><ShareResult :label="t('results.share.button')" :milestone="unitMoment" :entry="entry" :course-slug="shareSlug" :color="color" /></template>
                        <!-- The unit's song (retention row 8, unit-song.js): one quiet card under the main button, its part opened
                             straight in Sing along with the other voices singing. Game mode's words in game mode. -->
                        <template v-if="unitSong" #song>
                            <div class="space-y-2 rounded-2xl border border-line p-4 text-start" data-unit-song :data-place="unitSong.place">
                                <p class="font-semibold text-cream" translate="no">{{ unitSongLine('title') }}</p>
                                <p class="text-sm text-muted" translate="no">{{ unitSongLine('text') }}</p>
                                <button type="button" class="btn-secondary w-full" :disabled="unitSongOpening" data-unit-song-open @click="openUnitSong">{{ t('course.unitSong.sing') }}</button>
                            </div>
                        </template>
                    </LessonEnd>
                    <!-- The unit's boss, just opened by this challenge (past week one, not tried yet): one quiet line, never a pop-up. -->
                    <div v-if="bossUnlocked" class="mx-auto mt-4 flex max-w-prose flex-wrap items-center justify-center gap-3 text-center" data-boss-unlocked>
                        <p class="text-sm text-cream" translate="no">{{ effect('boss') ? t('boss.unlocked.game', { title: localized(entry.unit.boss.title) }) : t('boss.unlocked.review', { title: localized(entry.unit.boss.title) }) }}</p>
                        <button type="button" class="btn-secondary btn-sm" @click="navigate('/learn/' + entry.unit.boss.id)">{{ effect('boss') ? t('boss.takeItOn') : t('boss.review.start') }}</button>
                    </div>
                    <!-- Voice care (docs/voice-care-plan.md): one quiet line after a long day's singing, the cool-down a tap away. -->
                    <VoiceCareLine class="mx-auto mt-4 max-w-prose text-center" />
                    <!-- Keep the finale (js/keep-song.js): a finale that opts in, kept as a song in Sing. -->
                    <KeepSong v-if="entry.lesson.keepSong" class="mt-6" :lesson="entry.lesson" :kept="kept" :base="stepBase" />
                    <!-- The first session's wrap (onboarding step 5): what it gave, your week, one way to keep it. -->
                    <SessionWrap v-if="wrap" :lessons="wrap.lessons" :stars="wrap.stars" />
                    <!-- A guest's free lesson of a Plus course (Hillel let guests try one, 2026-10-30): kept here, and in an account once made. -->
                    <section v-if="guestSave" class="w-full space-y-3 rounded-3xl border border-edge bg-panel p-5 text-start" aria-labelledby="guest-save-title" data-guest-save>
                        <h2 id="guest-save-title" class="font-bold text-cream">{{ t('courses.guest.title') }}</h2>
                        <p class="text-sm text-muted">{{ t('courses.guest.body', { course: localized(entry.course.title) }) }}</p>
                        <a :href="guestSignUp" class="btn-primary btn-sm" data-guest-signup @click.prevent="askYes('acct_course'); navigate(guestSignUp)">{{ t('wrap.makeAccount') }}</a>
                    </section>
                    <!-- The free course's first unit done (onboarding step 6): your voice card, to keep and share. -->
                    <VoiceCard v-if="voiceCardShown" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" />
                    <!-- The lesson's fact card (docs/courses-ideas.md, idea 1): below the stars and the wraps, in both skins. -->
                    <FactCard v-if="entry.lesson?.fact" :fact="entry.lesson.fact" />
                    <!-- Share where the celebration is (onboarding step 4): on two or three stars, with the take that earned them. -->
                    <!-- Not beside the session wrap (one keep-it ask beside the goal): it comes back on the next lesson's end. -->
                    <!-- At most one Share a session (js/share-moment.js; docs/design-rules.md, Sharing in lessons): the first earned moment shows it. -->
                    <div v-if="done.stars >= 2 && takeResult && !wrap && endShare" class="flex justify-center" data-end-share>
                        <ShareResult :label="game() ? t('boards.challenge.button') : t('results.share.result')" :result="takeResult" :record="!!done.newBest" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" :course-slug="shareSlug" :streak="done.streakNew ? streakMilestone(done.streak) : null" />
                    </div>
                    <p v-if="done.tip && TIP_WORDS[done.tip]" class="max-w-prose text-sm text-cream">{{ t('course.done.tip') }} {{ t(TIP_WORDS[done.tip]) }}</p>
                </template>

                <!-- A step -->
                <!-- A Learn step: its takeaway and steps on the left, its picture on the right (above, smaller, on a phone). -->
                <section v-else :aria-label="t(STEP_KIND[current.kind] ?? STEP_KIND.play)" :class="split ? 'grid gap-8 md:grid-cols-2 md:items-center md:gap-14' : ''">
                    <!-- In an app's own browser, before anything else: the mic likely won't work here. -->
                    <InAppCard v-if="askMic && inApp" :app="inApp" class="md:col-span-2" />
                    <div v-if="split" class="md:order-2 md:pb-8"><LearnVisual :key="'v' + step" :type="current.visual" class="max-w-[12rem] md:max-w-[22rem]" /></div>
                    <div class="flex flex-col gap-5">
                    <BossBar v-if="entry.boss && effect('boss')" :total="bossTotal" :scored="bossScored" :live="result ? 0 : bossLive" :round="bossRound" :rounds="bossRounds" :said="bossSaid" />
                    <p v-if="step === 0 && entry.lesson.findsHome && chordMoment" class="-mb-2 text-sm font-semibold text-accent-strong" data-chord-promise>{{ t('chordMoment.promise') }}</p>
                    <p v-if="current.kind === 'home'" class="-mb-3 text-sm font-semibold text-accent-strong" translate="no">{{ localized(entry.unit.title) }} · {{ t('course.stepLabel', { step: step + 1 }) }}</p>
                    <h1 ref="heading" tabindex="-1" class="font-bold leading-snug text-cream outline-none" data-step-line :class="current.headline ? 'text-3xl font-extrabold tracking-tight' : 'text-2xl'" translate="no">{{ localized(current.headline ?? current.text) }}</h1>
                    <ol v-if="current.steps?.length" class="space-y-2.5">
                        <li v-for="(item, i) in current.steps" :key="i" class="flex items-center gap-4 rounded-2xl border border-edge bg-panel px-4 py-3.5">
                            <span class="w-5 shrink-0 text-center text-xl font-extrabold tabular-nums text-accent-strong" aria-hidden="true">{{ i + 1 }}</span>
                            <span class="min-w-0"><span class="block font-bold text-cream" translate="no">{{ localized(item.title) }}</span><span class="block text-sm text-muted" translate="no">{{ localized(item.detail) }}</span></span>
                        </li>
                    </ol>
                    <!-- A lesson that needs headphones says so quietly on its first card, before the mic asks. -->
                    <p v-if="needsHeadphones && step === 0 && !done" class="flex items-center gap-2 text-sm text-muted" data-best-headphones><Icon name="headphones" :size="16" class="shrink-0" />{{ t('course.bestWithHeadphones') }}</p>
                    <!-- Your part (course-parts.js), on a course with parts: its lesson 1's first card, beside the course page's. -->
                    <CoursePart v-if="partPickHere" :course="entry.course" class="pt-2" />
                    <p v-if="needsFlip" class="rounded-2xl border border-line p-3 text-sm text-muted" role="status" data-needs-flip>{{ t('courses.flipFirst') }}</p>
                    <p v-if="needsWhistle" class="rounded-2xl border border-line p-3 text-sm text-muted" role="status" data-needs-whistle>{{ t('courses.whistleFirst') }}</p>
                    <div v-if="learning.progress.home === null && !entry.lesson.findsHome && !song && (current.kind === 'try' || current.kind === 'play')" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line p-3 text-sm text-muted"><span>{{ t('course.home.reminder') }}</span><button type="button" class="btn-ghost btn-sm" @click="navigate('/learn/' + homeLesson)">{{ t('course.home.reminderLink') }}</button></div>

                    <!-- A song course's step (js/song-course.js): its notes come with the song, so it waits for them. -->
                    <p v-if="song && !songStep" class="text-muted" role="status" data-song-step>{{ songError || t('course.loading') }}</p>
                    <Exercise v-else-if="current.kind === 'learn' && current.listen" :key="'l' + step" :exercise="stepListen" :base="stepBase" mode="listen" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :higher-shown="higherShown" @lower="moveKey(-1)" @higher="moveKey(1)" @done="ran = true" />
                    <Exercise v-else-if="current.kind === 'try'" :key="'t' + step" :exercise="stepExercise" :base="stepBase" mode="try" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :higher-shown="higherShown" @lower="moveKey(-1)" @higher="moveKey(1)" @done="ran = true" @practised="practised" />
                    <template v-else-if="current.kind === 'play'">
                        <Exercise v-show="!takeResult || guiding" ref="exercise" :key="'p' + step" :earns-bonus="!entry.boss && courseRules.combo" :exercise="stepExercise" :base="stepBase" :under-takes="underTakes" :headphones-for="needsHeadphones ? entry.lesson.id : ''" :personal="personal" mode="play" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :higher-shown="higherShown" @lower="moveKey(-1)" @higher="moveKey(1)" @done="scored" @practised="practised" @lit="bossLive = $event" />
                        <!-- The take's result (ux-spec 5.8, 49.png) -->
                        <p v-if="entry.boss && takeResult" class="text-lg font-semibold text-cream" role="status" data-boss-round>{{ t('boss.roundResult', { round: bossRound, percent: formatNumber(takeResult.percent) }) }}</p>
                        <template v-else-if="takeResult && !guiding">
                            <TakeResult :result="takeResult" :xp="takeXp" :together-first="!entry.course && entry.unitIndex === 0" />
                            <ThenAndNow v-if="current.exercise?.thenAndNow" :id="current.exercise.thenAndNow" :others="current.exercise.thenAndNowOthers ?? []" :now="result?.take ?? null" />
                            <p v-if="vibratoLine" class="text-cream" role="status">{{ vibratoLine }}</p>
                            <!-- A course's own measure, said plainly (a registered scorer's line, js/lesson-scores.js). -->
                            <p v-if="result?.line" class="text-cream" data-measured-line>{{ t(result.line.key, result.line.values ?? {}) }}</p>
                            <p v-if="result?.heardFirst" class="text-sm text-muted" data-heard-first>{{ t('course.read.heardFirst') }}</p>
                            <p v-if="result?.foldSaid" class="text-sm text-muted" data-whistle-fold>{{ t('course.whistle.folded') }}</p>
                            <p v-for="(line, i) in harmonyLines" :key="'h' + i" class="text-cream">{{ line }}</p>
                            <p v-if="measuresLine" class="text-sm text-muted">{{ measuresLine }}</p>
                            <div class="flex flex-wrap gap-3">
                                <!-- Sing it again, quiet after a take (one primary: Continue); Share waits for the lesson's end. -->
                                <button v-if="!takesUsed" type="button" class="btn-ghost" @click="playAgain">{{ takeResult.stars < 3 && game() ? t('results.playAgainStars') : t('course.replay') }}</button>
                                <!-- After two weak takes: a take with the guide singing too, unscored (components/exercise.js). -->
                                <button v-if="result?.offerGuide && !takesUsed" type="button" class="btn-ghost" data-sing-with-guide @click="singWithGuide">{{ t('course.withGuide') }}</button>
                            </div>
                        </template>
                        <p v-else-if="result" class="text-center text-cream" role="status">{{ result.percent === null ? t('course.notHeard') : sk('course.resultSerious', 'course.result', { percent: result.percent, stars: starsOf(result) }) }}</p>
                    </template>
                    <!-- A Plus course's own step (js/lesson-steps.js: the trace lane, listen and choose, ...): it shows its own result. -->
                    <component :is="registered.component" v-else-if="registered" :key="'r' + step" :exercise="current.exercise" :base="stepBase" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :higher-shown="higherShown" :earlier="kept" :personal="personal" @lower="moveKey(-1)" @higher="moveKey(1)" @done="registeredDone" @practised="practised" />
                    <template v-else-if="current.kind === 'home'">
                        <!-- A lesson that opens on finding your note (an experiment's short lesson 1): the mic moment here first,
                             as a step before it would carry it: in an app's browser its card, else Turn on my mic and why. -->
                        <div v-if="micFirst" class="flex flex-col gap-4" data-mic-first>
                            <InAppCard v-if="inApp" :app="inApp" />
                            <button type="button" class="btn-primary btn-lg self-start" data-mic-first-go @click="micOn"><Icon v-if="!inApp" name="mic" :size="18" /> {{ inApp ? t('course.mic.carryOn') : t('course.mic.turnOn') }}</button>
                            <p class="text-xs text-muted" data-mic-why>{{ t('course.mic.why') }}</p>
                        </div>
                        <template v-else>
                            <p v-if="friendNote" class="text-center text-lg font-semibold text-cream" data-friend-note>{{ t('course.friendNote.ask', { note: friendNote }) }}</p>
                            <VoiceCapture mode="note" :color="color" @found="foundHome" @no-sound="(now) => (noSound = now)" />
                        </template>
                        <p v-if="skippedHome" class="text-center text-muted" role="status">{{ t('course.home.skipped') }}</p>
                    </template>
                    <template v-else-if="current.kind === 'use' || current.kind === 'record'">
                        <button type="button" class="btn-primary btn-lg self-start" :disabled="checking" @click="openSong"><Icon name="mic" :size="18" /> {{ current.kind === 'record' ? t('course.recordIn') : t('course.openIn') }}</button>
                        <p v-if="fitLine" class="text-sm text-cream" data-fit-line>{{ fitLine }}</p>
                        <p v-if="current.kind === 'use'" class="text-sm text-muted" role="status">{{ used ? t('course.usedIt') : t('course.useFirst') }}</p>
                        <p v-if="current.kind === 'record' && result" class="text-cream" role="status">{{ result.noTake ? t('course.recordFirst') : result.percent === null ? t('course.notHeard') : sk('course.resultSerious', 'course.result', { percent: result.percent, stars: starsOf(result) }) }}</p>
                        <p v-if="current.kind === 'record' && result && measuresLine" class="text-sm text-muted">{{ measuresLine }}</p>
                        <button v-if="current.kind === 'record'" type="button" class="btn-ghost btn-sm self-start" :disabled="checking" @click="checkRecording">{{ checking ? t('course.checking') : t('course.checkRecording') }}</button>
                    </template>
                    <!-- A whistle moved down to compare (js/whistle.js), said once on this device. -->
                    <p v-if="registered && result?.foldSaid" class="text-sm text-muted" role="status" data-whistle-fold>{{ t('course.whistle.folded') }}</p>
                    <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>

                    <!-- The foot, pinned: Back in glass, and the way on (or past) at the end. A sung step (128.png):
                         Skip, quiet, and a wide Continue that waits until it's done. -->
                    <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 bg-ink/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur">
                        <!-- Skip only while there's something to skip: once the step is done, Back (onboarding review 2, 12). -->
                        <button v-if="sungStep && !canGoOn" type="button" class="link-quiet min-h-11 px-2 text-sm" data-skip @click="skip">{{ t('course.skip') }}</button>
                        <button v-else type="button" class="btn-ghost" @click="step === 0 ? goBack('/') : go(step - 1)">{{ t('common.back') }}</button>
                        <!-- Finding your note, there's nothing to press until a note is held (or skipped): a hint, not a dead button. -->
                        <button v-if="current.kind === 'home' && !canGoOn" type="button" class="link-quiet min-h-11 text-sm" @click="skipHome">{{ noSound ? t('micCheck.skipSetup') : t('course.home.skip') }}</button>
                        <p v-else-if="current.kind === 'record' && !canGoOn" class="text-sm text-muted">{{ t('course.recordGoOnHint') }}</p>
                        <!-- A sung step (Hillel, as the warm-up): what's left to do beside the way on, which lights up once it's done. -->
                        <div v-else class="ms-auto flex items-center gap-3 max-sm:flex-1 max-sm:justify-end">
                            <span v-if="sungStep && !canGoOn" class="text-end text-sm text-muted" data-go-on-hint>{{ registered?.hint ? t(last ? registered.hint.finish : registered.hint.goOn) : last ? t('course.singToFinish') : t('course.singToGoOn') }}</span>
                            <button type="button" class="btn-primary max-sm:grow" :disabled="!canGoOn" data-go-on @click="goOn"><Icon v-if="askMic && !inApp" name="mic" :size="18" /> {{ askMic ? (inApp ? t('course.mic.carryOn') : t('course.mic.turnOn')) : takesUsed && takeResult ? t('course.goodEnough') : last ? t('course.finish') : t('course.continue') }}</button>
                        </div>
                        <!-- Before the browser asks for the mic: why we listen, in one line (docs/onboarding-review.md, step 2). -->
                        <p v-if="askMic" class="basis-full text-center text-xs text-muted sm:text-end" data-mic-why>{{ t('course.mic.why') }}</p>
                    </div>
                    </div>
                </section>
            </div>
        </div>`,
    computed: {
        /** The friend's note, on the free course's first lesson only (row 4); else null. */
        friendNote() {
            if (this.entry?.course || this.entry?.unitIndex !== 0 || this.entry?.lesson?.id !== this.entry?.unit?.lessons?.[0]?.id) return null;
            const kept = tabStorage.get(FRIEND_KEY);
            return kept && NOTE_NAME.test(kept) ? kept : null;
        },
        /** The lesson, in the free course or a Plus course this account can see (else none, as an unknown id). */
        found(vm) {
            const entry = learning.loaded ? findLesson(vm.id) : null;
            if (!entry?.course) return entry;
            // A song course: its own catalogue (live, or staff while it's reviewed); the song itself is its gate.
            if (songCourse(entry.course)) return songCourseVisible(vm.songEntry, account.me) ? entry : null;
            return courseVisible(learning.catalogue.find((c) => c.id === entry.course.id), account.me, account.config) ? entry : null;
        },
        /** An experiment's arm of this lesson (its `variants`, js/experiments.js), drawn as it opens. */
        variant: (vm) => lessonFor(vm.found?.lesson),
        /** The lesson as this device sees it: its variant's steps for an arm it's in, else as written. */
        entry: (vm) => (vm.found && vm.variant.lesson !== vm.found.lesson ? { ...vm.found, lesson: vm.variant.lesson } : vm.found),
        /** This lesson's song course's catalogue entry (`{ id, template, status }`), or null. */
        songEntry: (vm) => (isSongLesson(vm.id) ? learning.songs?.find((c) => vm.id.startsWith(`${c.prefix}-`)) ?? null : null),
        /** Which step a song step's notes were asked for: a new one asks again. */
        songKey: (vm) => (vm.song && vm.entry ? `${vm.id}:${vm.step}` : null),
        /** The current step's song (`{ template, part, phrases }`), when its notes come from one. */
        song: (vm) => songOf(vm.current),
        /** What the step plays: a song step's exercise once its notes are in, else the step's own. */
        // "Try it slower" (Home's Today line, docs/home-returning-plan.md): the lesson at 85% of its tempo, this visit only.
        stepExercise: (vm) => slowed(vm.song && vm.current?.exercise?.song ? vm.songStep?.exercise : vm.current?.exercise, vm.slower),
        stepListen: (vm) => slowed(vm.song && vm.current?.listen?.song ? vm.songStep?.exercise : vm.current?.listen, vm.slower),
        /** The progress this lesson's course reads: a Plus course's own lessons, or the free course's. */
        courseProgress: (vm) => (vm.entry?.course ? courseView(learning.progress, vm.entry.course.id) : learning.progress),
        /** The course's public slug, for a shared card's link to the lesson's page: the free course's is 'harmony'; a song course has none. */
        shareSlug: (vm) => (!vm.entry ? null : !vm.entry.course ? 'harmony' : songCourse(vm.entry.course) ? null : learning.catalogue.find((c) => c.id === vm.entry.course.id)?.slug ?? null),
        /** This lesson's end may offer Share: the session's first earned moment, or this one again (a unit's end shares the unit instead). */
        endShare: (vm) => !!vm.entry && !vm.unitMoment && claimShare(`lesson:${vm.entry.lesson.id}`),
        /** A unit, or the whole course, just finished (its last lesson sung, with stars): its own card in the unit-done panel. */
        unitMoment(vm) {
            const e = vm.entry;
            const d = vm.done;
            if (!e || !d || !(d.stars >= 1) || vm.upNext || e.cooldown || e.boss || !vm.shareSlug || e.unit.lessons.at(-1)?.id !== e.lesson.id) return null;
            const course = e.course ?? learning.course;
            const courseName = localized(course?.title);
            const unit = localized(e.unit.title);
            if (course?.units?.at(-1)?.id === e.unit.id) return { kind: 'course', title: t('results.share.courseDone'), headline: courseName, line: t('results.share.courseLine'), text: t('results.share.courseText', { course: courseName }), lessonId: null };
            return { kind: 'unit', title: t('results.share.unitDone'), headline: unit, line: courseName, text: t('results.share.unitText', { unit, course: courseName }), lessonId: e.unit.lessons[0].id };
        },
        unitShare: (vm) => !!vm.unitMoment && claimShare(`${vm.unitMoment.kind}:${vm.entry.unit.id}`),
        /**
         * The unit's song at its end (retention row 8): a unit of the free course's first four with a song, its challenge
         * sung with stars (never a boss), and nothing up next in the unit. `place` (u1 to u4) is what's counted.
         */
        unitSong: (vm) => unitSongOffer({ entry: vm.entry, done: vm.done, progress: learning.progress, course: learning.course }),
        /** What a step's Then and now card names and links to (bullseye.js, breath-hold.js inject it as lessonShare). */
        thenShareContext: (vm) => (vm.entry && vm.shareSlug ? { lessonId: vm.entry.lesson.id, lessonTitle: localized(vm.entry.lesson.title), courseSlug: vm.shareSlug, color: vm.color } : null),
        /** A Plus course's own values for its scorers (`progress.courses.<id>.personal`); none for the free course. */
        personal: (vm) => (vm.entry?.course ? learning.progress.courses?.[vm.entry.course.id]?.personal ?? {} : {}),
        /** A Plus course's gates: staff try every lesson before launch (js/courses.js). */
        access: (vm) => (vm.entry?.course ? lessonAccess(vm.entry.course, vm.id, learning.progress, { signedIn: !!account.me, staff: !!account.me?.staff }) : null),
        /** Where Back to the course goes: the Plus course's page, or the free course. */
        courseHome: (vm) => (vm.songEntry ? `/learn/s/${vm.songEntry.template}` : vm.entry?.course ? `/learn/c/${vm.entry.course.id}` : '/learn'),
        /** A Learn step with a picture: side by side on a wide screen. */
        split: (vm) => !vm.done && vm.current?.kind === 'learn' && !!vm.current.visual,
        /** A lesson whose sound under you rests through a speaker (`headphones: "needed"`, its own or its course's). */
        needsHeadphones: (vm) => (vm.entry?.lesson.headphones ?? vm.entry?.course?.headphones) === 'needed',
        /** The part pick on a course with parts: its first lesson's first card. */
        partPickHere: (vm) => !vm.done && vm.step === 0 && !!vm.entry?.part && vm.entry.unitIndex === 0 && vm.entry.unit.lessons[0]?.id === vm.entry.lesson.id,
        open: (vm) => !!vm.entry && (vm.entry.cooldown || (vm.access ? vm.access.open : pageLesson(learning.course?.id, vm.entry.lesson.id) || (unitOpen(learning.course.units, vm.entry.unitIndex, learning.progress) && (!vm.entry.boss || bossOpen(vm.entry.unit, learning.progress))))),
        /** The lesson that finds your note, for the reminder when it was skipped. */
        homeLesson: () => learning.course?.units[0]?.lessons.find((l) => l.findsHome)?.id ?? null,
        /** The lesson as it opens, once the account is known (js/page-lesson.js takes a page's grant then), or null. */
        pageArrival: (vm) => (vm.entry && !vm.entry.cooldown && !vm.entry.boss && account.loaded ? `${vm.entry.course?.id ?? 'free'}:${vm.entry.lesson.id}` : null),
        /**
         * Signed out, past a course's first lesson (any course, the free one too): a free account to keep going. Null
         * (not known yet) while the account is still being checked on a device that kept none: a member opening a later
         * lesson directly saw the wall flash before their account came (the coordinator, 2026-11-08).
         */
        needsAccount() {
            const entry = this.entry;
            if (!entry || account.me || entry.boss || entry.cooldown) return false;
            const wall = entry.course ? !!this.access?.needsAccount
                // Only the free course's own lessons (a song's lessons and the like keep their own rules).
                : !!learning.course && courseLessons(learning.course).some((l) => l.lesson.id === entry.lesson.id) && !guestOpen(learning.course, entry.lesson.id);
            if (!wall) return false;
            return account.loaded ? true : null;
        },
        /** P4: a guest's first lesson of a course, at its end, offered the account (counted as acct_course). */
        guestSave() {
            return !!(this.done && !this.noteDone && !this.done.unscored && this.entry?.course && !account.me);
        },
        guestSignUp() {
            return `/signin?mode=signup&for=progress&next=${encodeURIComponent(this.courseHome)}`;
        },
        current: (vm) => vm.entry?.lesson.steps[vm.step] ?? null,
        /** The voice card: the free course's first unit, every lesson in it with stars. */
        voiceCardShown() {
            if (!this.done || this.done.unheard || this.entry?.course || this.entry?.boss || this.entry?.unitIndex !== 0) return false;
            return this.entry.unit.lessons.every((lesson) => learning.progress.lessons?.[lesson.id]?.stars);
        },
        /** Your note for its card: "A3", and what it suits. */
        noteShare() {
            const home = learning.progress.home;
            if (home === null) return null;
            const line = noteLine(home, voiceType(learning.progress.range));
            return { name: midiName(Math.round(home)), line: t(line.key, line.voiceKey ? { voice: t(line.voiceKey) } : line.values) };
        },
        /** The first session's wrap: the free course's third lesson (or later) done on the day this device arrived. */
        wrap() {
            if (!this.done || this.done.unheard || this.entry?.course || this.entry?.boss || !arrivedToday()) return null;
            const sung = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars);
            return sung.length >= 3 ? { lessons: sung.length, stars: sung.reduce((sum, l) => sum + l.stars, 0) } : null;
        },
        /** The chord moment shows, unless this device is in the onb1 experiment's control arm (docs/onboarding-review.md section 4). */
        chordMoment: () => variant() !== 'onb1-a',
        /** The way on opens the mic for the first time (the next step finds your note): it says so, and why. */
        askMic: (vm) => vm.entry?.lesson.steps[vm.step + 1]?.kind === 'home' && !audio.mic && vm.micPermission !== 'granted',
        /** The note finder is the lesson's first step, so no step before it said why we listen: it says so itself, first. */
        micFirst: (vm) => vm.step === 0 && vm.current?.kind === 'home' && !vm.micAsked && !audio.mic && vm.micPermission !== 'granted',
        /** An app's own browser (Instagram, TikTok...), by name, or null. */
        inApp: () => deviceOf(navigator.userAgent, { touchPoints: navigator.maxTouchPoints ?? 0 }).inApp,
        /** The lesson that finds your note, done with a note found: its own end (the keyboard). */
        noteDone: (vm) => !!vm.entry?.lesson.findsHome && learning.progress.home !== null && !vm.skippedHome,
        /** An exercise's result to show, once it's scored and heard. */
        takeResult() {
            const r = this.result;
            if (!r || r.percent === null || !r.chart?.dots?.length) return null;
            const stars = this.starsOf(r);
            // `label`: a course measure's own name for its percent (ring, steadiness), else it's "on pitch".
            return { ...r.chart, percent: r.percent, stars, word: levelWord(stars, r.percent), line: resultLine({ target: r.chart.target, lit: r.chart.lit, hold: r.chart.hold }), label: r.label ?? null };
        },
        /** What the stars measure, when the lesson teaches something else (the review's #16), or holds. */
        measuresLine() {
            const step = this.current;
            if (step?.measures) return localized(step.measures);
            return step?.exercise?.score === 'hold' ? sk('course.stars.holdSerious', 'course.stars.hold') : '';
        },
        /** 8-vibrato: what was heard, described, never graded ("a gentle vibrato, about 5.5 a second"). */
        vibratoLine() {
            if (this.current?.exercise?.show !== 'vibrato' || !this.result || this.result.percent === null) return '';
            const v = this.result.vibrato;
            return v ? t('course.vibrato.heard', { rate: formatNumber(Math.round(v.hz * 2) / 2, { maximumFractionDigits: 1 }) }) : t('course.vibrato.straight');
        },
        /** Two lines (the Counterpoint course, js/harmony.js): your own line held, any pulls onto a partner, and the rules checklist. */
        harmonyLines() {
            const h = this.result?.percent === null ? null : this.result?.harmony;
            if (!h) return [];
            const lines = [];
            if (game() && h.ownLine >= 3 && h.heardPartners) lines.push(t('harmony.ownLine', { count: formatNumber(h.ownLine) })); // a streak: the game skin's
            if (h.pulls) lines.push(h.pulls.length ? t('harmony.pulled', { count: formatNumber(h.pulls.length) }) : t('harmony.pulledNone'));
            else if (h.withPartners && !h.heardPartners) lines.push(t('harmony.speaker'));
            if (h.checks) {
                lines.push(t('harmony.contrary', { count: formatNumber(h.checks.contrary), moves: formatNumber(h.checks.moves) }));
                lines.push(h.checks.parallels.length ? t('harmony.parallels', { count: formatNumber(h.checks.parallels.length) }) : t('harmony.parallelsNone'));
                if (h.checks.directs?.length) lines.push(t('harmony.directs', { count: formatNumber(h.checks.directs.length) }));
                lines.push(h.checks.cadence ? t('harmony.cadence') : t('harmony.noCadence'));
            }
            return lines;
        },
        /** The XP finishing on this take would earn (the play step is the lesson's scored one). */
        takeXp() {
            if (!this.takeResult || !this.last) return null;
            return addXp(learning.progress, { kind: 'exercise', stars: this.takeResult.stars }).gained || null;
        },
        path: (vm) => (vm.entry ? unitPath(vm.entry.unit, vm.courseProgress, vm.entry.lesson.id) : []),
        /** The Up next card: the next lesson, its own Learn picture, a line and about how long. */
        upNext() {
            const lesson = this.next;
            if (!lesson) return null;
            const learn = lesson.steps.find((s) => s.kind === 'learn' && s.visual);
            const said = lesson.steps.find((s) => s.headline);
            // Its number in its unit, so the end names it as the map does (docs/retention-plan.md, idea 2: "Next: Lesson 2").
            const number = (findLesson(lesson.id)?.index ?? -1) + 1 || null;
            return { lesson, number, minutes: lessonMinutes(lesson), visual: learn?.visual ?? null, line: said?.headline ?? null };
        },
        last: (vm) => !!vm.entry && vm.step === vm.entry.lesson.steps.length - 1,
        color: (vm) => vm.paint(PART_COLORS[((vm.entry?.unit.colour ?? 4) - 1) % PART_COLORS.length]),
        /**
         * How far Lower and Higher may move this lesson's key: KEY_REACH either way, or less where a step's exercise
         * says (`keyRange: { down, up }`, First steps to belting: `up: 0` hides Higher), the tightest in the lesson.
         */
        keyLimits(vm) {
            const ranges = (vm.entry?.lesson.steps ?? []).map((s) => (s.exercise ?? s.listen)?.keyRange).filter(Boolean);
            const limit = (side) => Math.max(0, Math.min(KEY_REACH, ...ranges.map((r) => (Number.isFinite(r[side]) ? r[side] : KEY_REACH))));
            return { down: limit('down'), up: limit('up') };
        },
        /** Lower or Higher on this lesson (128.png), kept with the course's progress, within the lesson's limits. */
        keyShift: (vm) => Math.max(-vm.keyLimits.down, Math.min(vm.keyLimits.up, learning.progress.keys?.[vm.id] ?? 0)),
        /** The note the exercises are built on: the one that fits your voice (unit 1 finds it), moved by the key. */
        base: (vm) => (learning.progress.home ?? DEFAULT_HOME) + vm.keyShift,
        /**
         * The note this step is built on: the base, or for `exercise.base: 'flip'` (Yodeling) the
         * singer's own flip note above it, kept by the course (`personal.flip`; a fifth until it's found).
         */
        // A Learn step's example (`listen`) is laid the same way as the exercise it introduces.
        // A song step is built on the song's own tonic, in the key that fits your range (js/song-course.js).
        // `base: 'flipKey'` (a song's yodel tag) moves a little from the flip note into the line's key (flipKeyBase).
        stepBase(vm) {
            if (vm.songStep) return vm.songStep.tonic + vm.keyShift;
            const laid = vm.current?.exercise ?? vm.current?.listen;
            // `base: 'whistle'` (Whistling): laid from your whistle note (`personal.whistle`), at the octave nearest your voice's.
            if (laid?.base === 'whistle') return whistleBase(vm.personal.whistle, vm.base, vm.keyShift);
            if (laid?.base !== 'flip' && laid?.base !== 'flipKey') return vm.base;
            if (vm.personal.flip === undefined) return vm.base + FLIP_UNTIL_FOUND;
            return vm.base + (laid.base === 'flipKey' ? flipKeyBase(vm.personal.flip, laid.lineKey ?? 0) : vm.personal.flip);
        },
        /** A step built on the flip note before the course has found it. */
        /** A step laid from the whistle note before lesson 1 has found it. */
        needsWhistle: (vm) => (vm.current?.exercise ?? vm.current?.listen)?.base === 'whistle' && !Number.isFinite(vm.personal.whistle),
        needsFlip: (vm) => ['flip', 'flipKey'].includes((vm.current?.exercise ?? vm.current?.listen)?.base) && vm.personal.flip === undefined,
        /** When flip lessons open again, as a time of day. */
        flipRestTime: (vm) => (vm.flipRestAt ? formatDate(vm.flipRestAt, { timeStyle: 'short' }) : ''),
        /** Earlier steps' takes this step sings over (`exercise.under: { step }` or `{ steps: [...] }`, js/under-mix.js), from this visit. */
        underTakes: (vm) => underLayers(vm.current?.exercise?.under, vm.kept.takes),
        /** "Key D♭": the exercises' tonic, named as a key. */
        // The step's own note: a song's tonic, or a step laid from the flip or the whistle note, as it's sung.
        musicKey: (vm) => keyName({ tonic: vm.stepBase, mode: vm.songStep?.minor ? 'minor' : 'major' }),
        canLower: (vm) => vm.keyShift > -vm.keyLimits.down,
        canHigher: (vm) => vm.keyShift < vm.keyLimits.up,
        /**
         * A step's takes are used up (`exercise.retries`, First steps to belting: the voice teacher's limit on repeats):
         * no Sing it again or more stars, and the way on says "Good enough: move on".
         */
        takesUsed: (vm) => Number.isFinite(vm.current?.exercise?.retries) && (vm.takesOn[vm.step] ?? 0) >= vm.current.exercise.retries,
        /** Higher shown at all: not where the lesson may only go lower (`keyRange.up: 0`). */
        higherShown: (vm) => vm.keyLimits.up > 0,
        /** An exercise to sing (Try it, Sing it): Skip and the wide Continue. */
        sungStep: (vm) => vm.current?.kind === 'try' || vm.current?.kind === 'play' || !!vm.registered,
        /** A step type from js/lesson-steps.js (a Plus course's), or null. */
        registered: (vm) => (vm.current ? stepOf(vm.current.kind) : null),
        // A Plus course is beyond the beginner's units: standard unless a lesson says otherwise (the
        // free course's first four units are relaxed by their place, js/course.js strictnessFor).
        strictness: (vm) => vm.entry?.lesson.strictness ?? (vm.entry?.course ? 'standard' : strictnessFor(vm.entry?.unitIndex ?? 0)),
        /** Learn and Use it go on at once; a sung step once it's been tried. */
        canGoOn() {
            const kind = this.current?.kind;
            // A boss's round must be heard to count: sing it again, or Skip (the try then doesn't count).
            if (kind === 'play' || (this.registered && isScored(kind))) return !!this.result && !(this.entry?.boss && this.result.percent === null);
            if (this.registered) return this.ran; // an unscored registered step: once it's done
            if (kind === 'record') return !!this.result && !this.result.noTake;
            if (kind === 'home') return (learning.progress.home !== null && this.ran) || this.skippedHome;
            if (kind === 'use') return this.used;
            if (kind === 'try') return this.ran; // Wait for me ends by itself once every note is found
            return true;
        },
        /** This challenge's end opened the unit's boss, and it hasn't been tried (docs/boss-round-plan.md). */
        bossUnlocked() {
            const entry = this.entry;
            if (!this.done || this.done.unheard || !entry || entry.boss || entry.course || !entry.unit.boss) return false;
            return challengeOf(entry.unit)?.id === entry.lesson.id && bossOpen(entry.unit, learning.progress) && !bossResult(learning.progress, entry.unit.id);
        },
        /** A boss's sung steps' indexes (its rounds). */
        bossSteps: (vm) => (vm.entry?.boss ? vm.entry.lesson.steps.map((s, i) => (s.kind === 'play' || (stepOf(s.kind) && isScored(s.kind)) ? i : -1)).filter((i) => i >= 0) : []),
        bossRounds: (vm) => vm.bossSteps.length,
        bossRound: (vm) => Math.max(1, vm.bossSteps.filter((i) => i <= vm.step).length),
        /** The round's scored notes, from each step's notes (loaded as the intro shows). */
        bossTotal: (vm) => vm.bossSteps.reduce((sum, i) => sum + (vm.bossNotes?.[i] ?? 1), 0),
        /** Notes' worth scored so far: each finished round's percent of its notes (the bar's honest drain). */
        bossScored: (vm) => vm.bossSteps.reduce((sum, i) => sum + ((vm.kept.results[i]?.percent ?? 0) / 100) * (vm.bossNotes?.[i] ?? 1), 0),
        /** The lesson after this one, if it's open (an experienced singer jumps from their note to unit 5). */
        next() {
            if (!this.entry || this.entry.boss) return null; // a boss ends back on the course
            if (this.sharpen) return findLesson(this.sharpen.nextId ?? '')?.lesson ?? null; // the session's next, or its end
            if (this.entry.course) return lessonAfter(this.entry.course, this.entry.lesson.id, learning.progress, { signedIn: !!account.me, staff: !!account.me?.staff });
            const units = learning.course.units;
            const here = this.entry;
            const lessons = units.flatMap((unit, unitIndex) => unit.lessons.map((lesson) => ({ unit, unitIndex, lesson })));
            const after = lessons[lessons.findIndex((l) => l.lesson.id === here.lesson.id) + 1];
            return after && unitOpen(units, after.unitIndex, learning.progress) ? after.lesson : null;
        },
    },
    watch: {
        /** A friend's landing that reached its own note: counted once (note_friend_found). */
        done(now, before) {
            if (now && !before && this.noteDone && this.friendNote) count('note_friend_found');
        },
        /** The unit's song on screen: counted once a device a place (us_shown). */
        'unitSong.place': {
            immediate: true,
            handler(place) {
                if (place) countUnitSong('shown', place);
            },
        },
        // A lesson arrived at from its own public page (js/page-lesson.js, Hillel: "Yes, once"): granted as it opens,
        // once the account is known, when it would otherwise be walled or locked.
        pageArrival: {
            immediate: true,
            handler(key) {
                if (!key) return;
                const entry = this.entry;
                const course = entry.course ?? learning.course;
                const blocked = !this.open || (!account.me && !guestOpen(course, entry.lesson.id));
                takePageLesson(course?.id, entry.lesson.id, blocked);
            },
        },
        // The title card waits for the wall to be ruled out: once the account is known (a member opening a later lesson
        // directly) or comes after a sign-in, it plays as on any first visit.
        needsAccount(now, before) {
            if (now === false && before !== false) {
                this.showTitle();
                this.countStart(); // the lesson opens now (the account loaded, or the guest signed up): its start and its step
                this.reachStep(this.step);
            }
        },
        // The lesson's own step components (lesson-steps.js loads them on demand), fetched as it opens.
        'entry.lesson': { handler: (lesson) => prefetchSteps(lesson), immediate: true },
        // A song step's key, said under Open in Sing (Hillel: "One line under Open in Sing").
        current: { handler() { this.loadFitLine(); }, immediate: true },
        // The course's own game rules (course.json's "game", a lesson's "quiet"), for the lesson open.
        entry: {
            handler(entry) {
                setCourseRules(entry?.course ?? null, entry?.lesson ?? null);
            },
            immediate: true,
        },
        // A boss's note counts (its songs' notes too), for the bar and the score's weights, as it opens.
        'entry.lesson.id': {
            handler() {
                this.loadBossNotes();
            },
            immediate: true,
        },
        // P4 is counted (acct_course) and takes the day's one ask, so nothing else asks beside it;
        // it isn't held back by the ledger, as it's the lesson the guest chose, with no Not now.
        guestSave(shown) {
            if (!shown) return;
            askShown('acct_course');
            askSeen('acct_course');
        },
        id() {
            flipStopped();
            this.capStop();
            this.sharpen = null;
            this.reset();
            this.flipOpen({ check: true });
            this.capOpen({ check: true });
            this.beginSharpen();
            this.showTitle();
        },
        /**
         * A take's result: its stars' sound as the last lands (two or three), and focus on its
         * heading, so a screen reader says the measure and Tab goes on to Listen back and the rest.
         */
        takeResult(now, before) {
            if (this.entry?.boss) return; // a boss's round: no stars, so no stars' sound; its line says the percent
            if (now && !before && now.stars >= 2) starsSound(now.stars, 0.35 + (now.stars - 1) * 0.3);
            if (now && !before) this.focusWhenShown('[data-result-heading]');
        },
        step(now) {
            this.reachStep(now);
            this.bossLive = 0;
            this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
        },
        /** A song step's notes, loaded with its song each time the step (or the lesson) changes. */
        songKey: {
            handler() {
                this.resolveSong();
            },
            immediate: true,
        },
    },
    async created() {
        takeFriendNote();
        // Already allowed (an earlier visit): the way on is plain Continue. Not every browser can say.
        navigator.permissions
            ?.query({ name: 'microphone' })
            .then((status) => (this.micPermission = status.state))
            .catch((error) => console.info('The mic permission could not be read', error?.message ?? error));
        await loadLearning();
        if (this.id === COOL_DOWN) await loadCooldown(); // voice care's cool-down, outside every course
        else if (!this.entry && isSongLesson(this.id)) {
            // A song course's lesson: that song's course first (never the Plus courses).
            await loadSongCourse({ lessonId: this.id }).catch((error) => console.error('The song course could not load', error));
            this.songLooked = true;
        } else if (!this.entry) await loadPlusCourses(); // a Plus course's lesson: its course first
        // Back from Sing: where the learner was in this lesson.
        const kept = Number(tabStorage.get(`stackapella.lesson.${this.id}`));
        if (kept && this.entry && kept < this.entry.lesson.steps.length) {
            this.step = kept;
            this.flipOpen({ check: false }); // back from Sing mid-lesson: never stopped part-way
            this.capOpen({ check: false });
            this.beginSharpen({ resumed: true });
        } else if (this.entry) {
            this.flipOpen({ check: true });
            this.capOpen({ check: true });
            tabStorage.set(`stackapella.lesson.${this.id}.reached`, ''); // a new run: its steps count afresh
            // Counted once the lesson really opens: never behind the course's account wall (a walled guest's view isn't a
            // start: first_week_real, 2026-11-12, found every wall view counted lesson 2 started), nor before that's known.
            this.startPending = !this.entry.boss;
            if (this.needsAccount === false) this.countStart();
            this.beginSharpen();
            this.showTitle();
        }
        this.reachStep(this.step);
        if (this.current?.kind === 'record') this.checkRecording();
        if (this.current?.kind === 'use') this.checkUse();
        this.$nextTick(() => (this.$refs.bossIntro ? this.$refs.bossIntro.focus() : this.$refs.heading?.focus({ preventScroll: true })));
    },
    mounted() {
        // The exercise and the results (components/lazy-lesson.js) and the song stores (lazy-stores.js: a song step's Open
        // in Sing), fetched once the lesson's first screen has painted.
        requestAnimationFrame(() => {
            prefetchLesson();
            prefetchStores();
        });
    },
    beforeUnmount() {
        closeMic(); // the exercises' mic: done with until the next lesson
        flipStopped();
        setCourseRules(); // every other screen keeps game mode's everything
        this.capStop();
        document.removeEventListener('visibilitychange', this.capSeen);
    },
    methods: {
        /**
         * Focus on `selector` once it's on the page: the next tick, or, if a part draws a moment later (a lesson part whose
         * code came in just now, components/lazy-lesson.js), as soon as it does, for up to 3 s.
         */
        focusWhenShown(selector) {
            const focus = () => {
                const target = document.querySelector(selector);
                target?.focus({ preventScroll: true });
                return !!target;
            };
            this.$nextTick(() => {
                if (focus()) return;
                const watch = new MutationObserver(() => focus() && watch.disconnect());
                watch.observe(this.$el, { childList: true, subtree: true });
                setTimeout(() => watch.disconnect(), 3000); // a page's own timer, never audio
            });
        },
        /**
         * A capped course's lesson opening (daily-cap.js): resting for the day once its minutes are spent (`check`: as
         * it opens, never on coming back mid-lesson), else its time counts from now, while it's on screen.
         */
        capOpen({ check }) {
            this.capRest = false;
            const course = this.entry?.course;
            if (!this.entry || !this.open || capMinutes(course) === null) return;
            if (check && capReached(learning.progress, course)) {
                this.capRest = true;
                return;
            }
            this.capFrom = document.hidden ? null : Date.now();
            this.capSeen ??= () => (document.hidden ? this.capStop() : this.capFrom === null && !this.capRest && capMinutes(this.entry?.course) !== null && (this.capFrom = Date.now()));
            document.addEventListener('visibilitychange', this.capSeen);
        },
        /** The time since the lesson opened (or came back on screen) joins the day's count. */
        capStop() {
            if (this.capFrom === null) return;
            const seconds = (Date.now() - this.capFrom) / 1000;
            this.capFrom = null;
            const course = this.entry?.course;
            if (course) addCourseTime(course.id, seconds).catch((error) => console.error('The day\'s time was not kept', error));
        },
        /** A flip lesson opening: its time counts toward the flip cap, unless the cap says rest first (`check`). */
        flipOpen({ check }) {
            this.flipRestAt = null;
            if (!this.entry || !this.open || !flipLesson(this.entry.lesson)) return;
            const until = check ? flipRest() : null;
            if (until) this.flipRestAt = until;
            else flipStarted();
        },
        /** Game mode's title card ("Stage 2-3"), once a lesson on this device, as it begins (its module loaded first: game/modules.js). */
        async showTitle() {
            const entry = this.entry;
            // Never on the cool-down: it's no stage of the game (unscored, outside every course).
            if (!entry || entry.boss || entry.lesson.unscored || !this.open || this.needsAccount !== false || this.step !== 0 || this.sharpen || !effect('titles')) return; // no fanfare before a wall
            await load('titleCard');
            if (this.entry !== entry || this.step !== 0 || titleSeen(entry.lesson.id)) return;
            markTitleSeen(entry.lesson.id);
            this.titleCard = { unit: entry.unitIndex + 1, lesson: entry.index + 1, unitTitle: localized(entry.unit.title), lessonTitle: localized(entry.lesson.title), colour: this.color };
        },
        /** The first step's own mic moment (micFirst): said why, counted as the step before it would be, then the note finder asks. */
        micOn() {
            micExplained();
            this.micAsked = true;
        },
        /** The way on. "Turn on my mic" said why on the screen, so the browser asks next with no sheet first. */
        goOn() {
            if (this.askMic) micExplained();
            this.go(this.step + 1);
        },
        goBack,
        navigate,
        midiName,
        starsFor,
        easyRange,
        noteLine,
        voiceType,
        /**
         * A Sharpen up session on this lesson (opened from the course page, js/sharpen.js): straight to
         * its scored part. `resumed`: back from Sing, already at its step.
         */
        beginSharpen({ resumed = false } = {}) {
            const run = this.entry?.course ? sharpenAt(this.id) : null;
            const start = run ? sharpenStart(this.entry.lesson) : null;
            if (start === null) return;
            this.sharpen = { place: run.place, total: run.total, start, nextId: run.ids[run.index + 1] ?? null };
            if (!resumed) {
                if (run.index === 0) count('sharpen_up'); // a session's first lesson: one session
                this.go(start);
            }
        },
        /** Back, from the header: out of the lesson; out of a Sharpen up session too, to its course page. */
        leave() {
            if (!this.sharpen) return goBack('/');
            endSharpen();
            tabStorage.set(`stackapella.lesson.${this.id}`, ''); // opened again later, it starts at its start
            backTo(this.courseHome); // back to the course page it came from, never a second copy of it
        },
        reset() {
            this.takesOn = {};
            Object.assign(this, { guiding: false, step: this.sharpen?.start ?? 0, result: null, done: null, ran: false, error: null, tip: null, used: false, skippedHome: false, skipped: false, kept: { results: {}, takes: {} }, bossStarted: false, bossLive: 0, bossSaid: '' });
        },
        /**
         * A song step's exercise (js/song-course.js): the song loaded (only someone who may start it
         * gets its notes), its phrases laid as the course's exercise, in the key that fits your range.
         * An answer for a step already left is dropped (Next is quick); a song that won't load says so.
         */
        async resolveSong() {
            const spec = this.song;
            const asked = this.songKey;
            this.songStep = null;
            this.songError = null;
            if (!spec) return;
            const listen = !this.current.exercise?.song;
            try {
                const template = await loadTemplate(spec.template);
                if (asked !== this.songKey) return;
                const { exercise, tonic, minor } = songExercise(template, listen ? this.current.listen : this.current.exercise, { listen });
                this.songStep = { exercise, tonic: tonic + songTranspose(template, spec.part, knownRange(), learning.progress.home ?? null), minor };
            } catch (error) {
                if (asked !== this.songKey) return;
                // A song this account may not open (plus_required, not_found) is a refusal, not a fault.
                if (error instanceof ApiError && error.status < 500) console.info('The lesson song was refused', error.code);
                else console.error('The lesson song could not open', error);
                this.songError = t('course.songFailed');
            }
        },
        /** Lower or Higher: a semitone, for every exercise in this lesson from now on. */
        moveKey(direction) {
            setLessonKey(this.id, this.keyShift + direction).catch((error) => console.error('The key was not kept', error));
        },
        /** Skip: on past a sung step. Skipping the scored one ends the lesson without stars (nothing lost). */
        skip() {
            if ((this.current.kind === 'play' || (this.registered && isScored(this.current.kind))) && !this.result) this.skipped = true;
            this.go(this.step + 1);
        },
        /** A run with the mic: its time counts toward the week's goal at once. */
        practised(seconds) {
            this.guiding = false; // a guided take has ended: back to the kept result
            if (this.entry?.lesson.unscored) return; // the cool-down is never part of progress
            practised(seconds).catch((error) => console.error('Practice time not counted', error));
        },
        go(step) {
            if (step < 0) return;
            if (step >= this.entry.lesson.steps.length) {
                this.finish();
                return;
            }
            this.guiding = false;
            this.step = step;
            this.result = null;
            this.ran = false;
            this.error = null;
            tabStorage.set(`stackapella.lesson.${this.id}`, String(step));
            if (this.current.kind === 'play' || (this.registered && isScored(this.current.kind))) this.skipped = false; // back on the sung step: a skip no longer stands
            if (this.current.kind === 'use') this.checkUse();
        },
        /**
         * A result's stars: from its percent, never under a generous first lesson's floor (`minStars`),
         * nor over a take heard first's ceiling (`maxStars`, sight-singing's Hear it first).
         */
        starsOf(result) {
            return Math.min(result.maxStars ?? 3, Math.max(starsFor({ percent: result.percent, timing: 'none' }), result.minStars ?? 0));
        },
        scored(result) {
            this.result = result;
            if (result.percent !== null) this.takesOn = { ...this.takesOn, [this.step]: (this.takesOn[this.step] ?? 0) + 1 }; // this visit's takes, for `retries`
            this.tip = result.tips?.[0] ?? null;
            // Kept for a later step of this visit (its take beneath it, its result to compare).
            const { take = null, ...rest } = result;
            this.kept = { results: { ...this.kept.results, [this.step]: rest }, takes: { ...this.kept.takes, [this.step]: take ?? result.chart?.take ?? null } };
            // A Plus course's own values (a first hold's length as the learner's baseline), kept with its progress.
            if (result.personal && this.entry?.course) setCoursePersonal(this.entry.course.id, result.personal).catch((error) => console.error('Not kept', error));
            // The notes sung on pitch widen the range the course knows (unit 1 finds it as you sing); a
            // span slide's by at most a tone at either end, so one strained note can't raise every key.
            if (result.percent !== null && result.sung?.length) {
                const sung = [Math.min(...result.sung), Math.max(...result.sung)];
                noteSung(...(this.current?.exercise?.score === 'span' ? cappedWidening(learning.progress.range, sung) : sung));
            }
            if (this.entry?.boss && result.percent !== null) this.bossSaid = t('boss.said', { round: this.bossRound, percent: formatNumber(Math.round((this.bossScored / Math.max(1, this.bossTotal)) * 100)), pass: formatNumber(BOSS_PASS) });
            remember(result).catch((error) => console.error('Not remembered', error)); // for the workout's weak spot
            // The history (scores.create): the exercise's numbers, by lesson and step.
            if (result.percent !== null) keepScore({ lesson: `${this.id}.${this.step}`, summary: { pitchPercent: result.percent, stars: this.starsOf(result), insights: result.insights ?? [] } }).catch((error) => console.error('Score not kept', error));
        },
        /** A registered step's end: a scored one's result, or an unscored one done. */
        registeredDone(result) {
            if (isScored(this.current.kind)) this.scored(result);
            else this.ran = true;
        },
        async foundHome(midi, clip = null) {
            this.noteClip = clip ? markRaw(clip) : null; // in memory only, for the chord moment
            await setHome(midi);
            this.ran = true;
            this.skippedHome = false;
        },
        /** Skipped: go on with a typical note (DEFAULT_HOME); the lesson counts, with one star, to replay later. */
        skipHome() {
            first('f_note_skip'); // the funnel: the note skipped on this device
            this.skippedHome = true;
        },
        /**
         * The lesson's stars: from its last sung step (exercise or recording); a lesson with nothing
         * to score (learning, practising in a song) is done when it's done.
         */
        async finish() {
            if (this.entry.boss) return this.finishBossRound();
            // Unscored (the cool-down): no stars, XP, progress or celebration, just its own last line.
            if (this.entry.lesson.unscored) {
                tabStorage.set(`stackapella.lesson.${this.id}`, '');
                this.done = { unscored: true };
                this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
                return;
            }
            const steps = this.entry.lesson.steps;
            const scoredAt = steps.findLastIndex((s) => isScored(s.kind));
            const scoredStep = scoredAt >= 0 ? steps[scoredAt] : null;
            // The last scored step's own result, kept by scored(): a step after it (a selfcheck, a learn card)
            // clears this.result as it opens, and the lesson's stars are still that step's.
            const result = scoredStep ? this.kept.results[scoredAt] ?? (this.step === scoredAt ? this.result : null) : null;
            const percent = result?.percent ?? null;
            tabStorage.set(`stackapella.lesson.${this.id}`, '');
            if (scoredStep && percent === null) {
                // Couldn't hear: it earns nothing and costs nothing (the stars kept stay as they were).
                this.done = { stars: 0, xp: 0, unheard: true, skipped: this.skipped, tip: null };
                this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
                return;
            }
            const stars = scoredStep ? Math.max(1, this.starsOf(result)) : this.skippedHome ? 1 : 3;
            const before = this.courseProgress.lessons[this.id]?.stars ?? 0;
            const firstToday = !((learning.progress.xp?.days?.[dayOf()] ?? 0) > 0); // no lesson finished yet today (slice 2's Streak!)
            const outcome = await finishLesson(this.id, { percent, stars, bonus: scoredStep ? result?.comboBonus ?? 0 : 0 }); // the combo bonus: notes lit in runs of 10 or more
            const unit = this.entry.unit;
            this.done = {
                label: scoredStep ? result?.label ?? null : null, // a course measure's name for the percent
                ...outcome,
                tip: typeof this.tip === 'string' ? this.tip : null,
                word: levelWord(outcome.stars, percent ?? 100),
                line: endLine({ stars: outcome.stars, before, scored: !!scoredStep }),
                fresh: outcome.stars >= 2,
                percent, // serious mode's heading: the measure, not the stars
                streak: courseRules.streak ? daysInARow(learning.progress) : 0, // a course without streaks shows none
                streakNew: firstToday, // the day's first lesson end: game mode's Streak! bursts over the streak chip, once
                place: unit.lessons.findIndex((l) => l.id === this.id) + 1,
                total: unit.lessons.length,
                unit: this.entry.unitIndex + 1, // game mode's stage clear names the unit and the lesson
                sharpened: !!this.sharpen && !this.sharpen.nextId, // a Sharpen up session's last lesson
            };
            this.chime(outcome.stars);
            // A level, or the unit (or the whole course) finished for the first time: the special sound, after the stars.
            const unitDone = before === 0 && unit.lessons.at(-1)?.id === this.id;
            if (outcome.levelledUp || unitDone) playSound('special');
            // Game mode: a unit's stage clear (docs/game-feel-plan.md, slice 2), its card over the results and, with Game
            // sounds, its own chip fanfare; a level that came with it is on the card (its own cue plays in the tally).
            if (unitDone && effect('titles') && !this.sharpen) {
                this.titleCard = { unit: this.entry.unitIndex + 1, lesson: this.done.place, unitTitle: localized(unit.title), lessonTitle: localized(this.entry.lesson.title), colour: this.color, clear: true, level: outcome.levelledUp ? outcome.level.level : null, ms: clearMs() };
                cue('stageClear');
            }
            // Earned just now: a small celebration for 2 stars, the full burst for 3, as the stars land.
            if (outcome.stars >= 2) this.$nextTick(() => celebrate(outcome.stars, this.$refs.end?.starsEl() ?? this.$refs.noteResult?.$el ?? null));
            // Once, at the end of the first lesson, for a signed-in learner: where they came from.
            this.askHeard = !!account.me && !account.me.heardFrom && this.entry.lesson.findsHome;
            this.$nextTick(() => (this.$refs.end ? this.$refs.end.focus() : this.$refs.heading?.focus({ preventScroll: true })));
        },
        /** "How did you hear about Stackapella?": once per person, one tap. */
        async saveHeard(value) {
            this.askHeard = false;
            try {
                account.me = (await api.account.update({ heardFrom: value })).data;
            } catch (error) {
                console.warn('heardFrom not saved', error);
            }
        },
        replay() {
            this.reset();
        },
        /**
         * A boss's note counts by step (docs/boss-round-plan.md): an exercise's notes, or its song's part's phrases, so
         * the bar and the score weigh each round by its notes before it's sung. A song that won't load counts one note.
         */
        async loadBossNotes() {
            const entry = this.entry;
            this.bossNotes = null;
            if (!entry?.boss) return;
            const counts = await Promise.all(entry.lesson.steps.map(async (step) => {
                const exercise = step.exercise;
                if (!exercise?.song) return Math.max(1, exercise?.notes?.length ?? 1);
                try {
                    return Math.max(1, songExercise(await loadTemplate(exercise.song.template), exercise, { listen: false }).exercise.notes.length);
                } catch (error) {
                    console.info('A boss song\'s notes could not be counted', error?.message ?? error);
                    return 1;
                }
            }));
            if (this.entry === entry) this.bossNotes = counts;
        },
        /** Into the boss's first round, from its intro (always round 1: a reload mid-round lost the rounds before it). */
        bossStart() {
            this.bossStarted = true;
            if (this.step !== 0) this.go(0);
            this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
        },
        /** Again, from its end: straight into the first round (the intro's been seen). */
        bossAgain() {
            this.reset();
            this.bossStart();
        },
        /**
         * A boss's end: every round's percent, weighted by its notes, as one (none when a round was skipped or not heard:
         * it doesn't count, and nothing's lost); 80% beats it. Kept with no stars; game mode's fanfare on a win.
         */
        async finishBossRound() {
            tabStorage.set(`stackapella.lesson.${this.id}`, '');
            const skipped = this.bossSteps.some((i) => !(i in this.kept.results));
            const percent = skipped ? null : bossPercent(this.bossSteps.map((i) => ({ percent: this.kept.results[i].percent ?? null, notes: this.bossNotes?.[i] ?? 1 })));
            if (percent === null) this.done = { boss: true, counted: false, skipped };
            else {
                const outcome = await finishBoss(this.entry.unit.id, percent);
                this.done = { boss: true, counted: true, percent, ...outcome };
                // The boss stage's win: its fanfare and the full burst. The calm review has none.
                if (outcome.beaten && effect('boss')) {
                    cue('stageClear');
                    this.$nextTick(() => celebrate(3, this.$refs.bossEnd?.$el ?? null));
                }
            }
            this.$nextTick(() => this.$refs.bossEnd?.focus());
        },
        /** The take's result: play the exercise again, from the tap. */
        playAgain() {
            this.result = null;
            this.$nextTick(() => this.$refs.exercise?.start());
        },
        /** A take with the guide singing too, over the kept result (it stays, and so does going on). */
        singWithGuide() {
            this.guiding = true;
            this.$nextTick(() => this.$refs.exercise?.singWithGuide());
        },
        /** The stars' chime, a note for each, rising (on the audio clock, as the stars appear). */
        chime(stars) {
            starsSound(stars, 0.35 + (stars - 1) * 0.3);
        },
        /**
         * A lesson's step reached, anonymous: fs_r:<lessonId>.<step> in the free course (where 4-words' 12 starts and 1
         * finish were lost), cs_r:<lessonId>.<step> in any other; once per step each time the lesson is run, kept for the
         * tab so coming back from Sing doesn't count again.
         */
        /** lesson_start and the course's start, once this run, when the lesson opens (never behind the wall). */
        countStart() {
            if (!this.startPending || !this.entry) return;
            this.startPending = false;
            count('lesson_start'); // a lesson begun, not one come back to from Sing
            countFor(this.entry.course ? 'pl_s' : 'fl_s', this.entry.course ? this.entry.course.id : this.entry.lesson.id); // by course, or the free course's lesson
            if (this.entry.course) countFor('cl_s', this.entry.lesson.id); // any other course's lesson, by its own id
            // The first lesson begun in an app's own browser (Instagram, Facebook and the like), where the mic often can't open.
            if (!this.entry.course && this.entry.unitIndex === 0 && this.entry.unit.lessons[0]?.id === this.entry.lesson.id && this.inApp) count('l1_inapp');
        },
        reachStep(index) {
            const entry = this.entry;
            if (!entry || entry.boss || entry.cooldown || index === null || index === undefined) return;
            if (this.needsAccount !== false) return; // behind the wall, or not yet known: counted when it opens
            const key = `stackapella.lesson.${this.id}.reached`;
            const reached = (tabStorage.get(key) ?? '').split(',').filter(Boolean);
            if (reached.includes(String(index))) return;
            tabStorage.set(key, [...reached, index].join(','));
            countFor(entry.course ? 'cs_r' : 'fs_r', `${entry.lesson.id}.${index}`);
        },
        /** "Use it": done once the lesson's song has been sung in (for a while) or recorded. */
        async checkUse() {
            const step = this.current;
            const { loadProjects, projects } = await projectsStore();
            await loadProjects();
            const project = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === step.template);
            this.used = !!project && ((project.practice?.sungSeconds ?? 0) >= SONG_USED_SECONDS || !!project.recorded);
        },
        continueOn() {
            if (this.sharpen) sharpenOn(this.id); // the session moves on (or ends, with the course page next)
            navigate(this.next ? `/learn/${this.next.id}` : this.entry?.course ? this.courseHome : '/');
        },
        /** A record step's song id: its template's, or a Plus course's own song's (`courseSong`, songs/<id>.json; course_check holds its id to the file name). */
        songIdOf: (step) => step.template ?? step.courseSong?.replace(/^.*\/|\.json$/g, ''),
        /** A record step's song: a template, or one in the Plus course's own folder (course_check checks it). */
        async stepSong(step) {
            if (!step.courseSong) return loadTemplate(step.template);
            const file = learning.catalogue.find((c) => c.id === this.entry.course?.id)?.file ?? '';
            const response = await fetch(`/course/plus/${file.replace(/[^/]*$/, '')}${step.courseSong}`);
            if (!response.ok) throw new Error(`The course's song could not load (${response.status})`);
            return validateTemplate(await response.json());
        },
        /** The course's own copy of the song, made once per lesson and reopened after. */
        /**
         * The line under a song step's Open in Sing: how the part fits you ("Alto fits your range", "... sung an
         * octave lower"), as the lesson's copy has it, or will when it's made. Nothing for a copy made before
         * songs opened in your key (it kept the printed key), or with nothing known of your voice yet.
         */
        async loadFitLine() {
            this.fitLine = '';
            const step = this.current;
            if (!step || (step.kind !== 'use' && step.kind !== 'record') || step.part === 'all' || (!step.template && !step.courseSong)) return;
            const lessonId = this.id;
            try {
                const { loadProjects, projects } = await projectsStore();
                await loadProjects();
                const existing = projects.all.find((p) => p.course?.lessonId === lessonId && p.template?.id === this.songIdOf(step));
                const template = await this.stepSong(step);
                const key = [step.part].flat()[0];
                const fit = existing ? existing.course?.fit ?? null : (await realKeyFit(template, key, knownRange())) ?? songFit(template, key, knownRange(), learning.progress.home ?? null);
                const part = template.parts.find((p) => p.key === key);
                if (!fit || !part || this.id !== lessonId || this.current !== step) return;
                const words = fit.real ? FIT_WORDS.real : !fit.fits ? FIT_WORDS.closest : fit.octave < 0 ? FIT_WORDS.lower : fit.octave > 0 ? FIT_WORDS.higher : FIT_WORDS.fits;
                this.fitLine = t(words, { part: templatePartName(part) });
            } catch (error) {
                console.info('The song step\'s key line was left out', error?.message ?? error);
            }
        },
        /** The unit song's title or line in the singer's language: game mode's own where it has them. */
        unitSongLine(field) {
            const s = this.unitSong;
            return localized((game() && s.game?.[field]) || s[field]);
        },
        /** Opens the unit's song: its part (the tune, or the part named), in the singer's key, straight in Sing along. */
        async openUnitSong() {
            this.unitSongOpening = true;
            this.error = null;
            try {
                const project = await unitSongProject(this.unitSong, { lessonId: this.id, unitId: this.entry.unit.id, place: this.unitSong.place });
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.error("The unit's song could not open", error);
                this.error = t('course.songFailed');
            } finally {
                this.unitSongOpening = false;
            }
        },
        async courseProject() {
            const { loadProjects, projects, startFromTemplate } = await projectsStore();
            await loadProjects();
            const step = this.current;
            const existing = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === this.songIdOf(step));
            if (existing) return existing;
            const template = await this.stepSong(step);
            const focusKey = step.part === 'all' ? partsFor(template, step.singers)[0].key : [step.part].flat()[0];
            // Hillel, 2026-11-02: the song opens in the singer's key (docs/song-keys-plan.md), the same rule as
            // song courses; kept as chosen so Sing doesn't fit it again, and Lower and Higher still move it.
            // A part with a real voice keeps the song's own key when the singer's range covers it within two
            // semitones (real-key.js, Hillel: "Prefer the real key").
            const fit = (await realKeyFit(template, focusKey, knownRange())) ?? songFit(template, focusKey, knownRange(), learning.progress.home ?? null);
            return startFromTemplate(template, { singers: step.singers, focusKey, transpose: fit?.transpose ?? 0, keyChosen: true, course: { lessonId: this.id, fit } });
        },
        async openSong() {
            this.error = null;
            try {
                const project = await this.courseProject();
                tabStorage.set(`stackapella.lesson.${this.id}`, String(this.step));
                navigate(`/p/${project.id}/sing`);
            } catch (error) {
                console.error('The lesson song could not open', error);
                this.error = t('course.songFailed');
            }
        },
        /** A recording step: the part (or every part) has a take, scored against its notes. */
        async checkRecording() {
            const step = this.current;
            this.checking = true;
            try {
                const { loadProjects, projects, takesOf } = await projectsStore();
                await loadProjects();
                const project = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === this.songIdOf(step));
                if (!project) return;
                const { parts, template, takes } = await takesOf(project.id);
                // `part`: one part, a list of them (Stack the tag's lead and baritone), or 'all'.
                const wanted = parts.filter((p) => p.templatePartKey && (step.part === 'all' || [step.part].flat().includes(p.templatePartKey)));
                if (!wanted.length || wanted.some((p) => !takes.get(p.id))) {
                    this.result = { percent: null, noTake: true };
                    return;
                }
                // The parts that could be heard, averaged; none heard is "couldn't hear", never 0%.
                const percents = [];
                for (const part of wanted) {
                    const samples = takes.get(part.id);
                    const notes = template.parts.find((tp) => tp.key === part.templatePartKey).guide.notes;
                    const track = await trackInWorker(samples, part.sampleRate);
                    const score = scoreTake(track, notes, { beatSeconds: 60 / template.tempo, startSeconds: part.offsetSeconds + (part.nudgeSeconds || 0), transpose: project.transpose || 0, strictness: this.strictness, timing: 'none' });
                    if (score.status === 'scored') percents.push(score.percent);
                    if (score.status === 'scored' && !this.tip) this.tip = score.tips?.[0] ?? null;
                }
                this.result = { percent: percents.length ? Math.round(percents.reduce((a, b) => a + b, 0) / percents.length) : null };
            } catch (error) {
                console.error('The recording could not be checked', error);
                toast(t('course.checkFailed'), { tone: 'error' });
            } finally {
                this.checking = false;
            }
        },
    },
};
