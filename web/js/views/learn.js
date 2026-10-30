// A lesson (docs/vocal-coaching-plan.md, "A lesson"): its steps one at a time with a progress bar
// (learn, try, play it through for stars, use it in a real song, record), then the end screen with
// the stars, the XP and Replay or Continue. At /learn/<lesson id>. A step that opens a song (Use
// it, Record) makes or reopens the course's own copy of it, and Sing brings the learner back here.
// In a Sharpen up session (js/sharpen.js) a lesson opens at its scored part and goes on to the next.

import { VoiceCareLine } from '../components/voice-care.js';
import { markRaw } from 'vue';
import { game, sk } from '../skin.js';
import { arrivedToday, first, variant } from '../funnel.js';
import { formatNumber, localized, t, tk } from '../i18n.js';
import { api } from '../api.js';
import { account } from '../stores/account.js';
import { goBack, navigate } from '../router.js';
import { addXp, nextLesson, strictnessFor, starsFor, unitOpen, voiceType } from '../course.js';
import { COOL_DOWN, finishLesson, findLesson, KEY_REACH, loadCooldown, learning, loadLearning, loadPlusCourses, noteSung, practised, remember, setCoursePersonal, setHome, setLessonKey } from '../stores/learning.js';
import { keyName } from '../theory.js';
import { keepScore } from '../stores/cloud.js';
import { audio, closeMic, engine } from '../audio.js';
import { deviceOf, micExplained } from '../mic-help.js';
import { playSound, starsSound } from '../sounds.js';
import { projects, loadProjects, startFromTemplate, takesOf } from '../stores/projects.js';
import { loadTemplate, partsFor } from '../templates.js';
import { trackInWorker } from '../pitchtrack.js';
import { scoreTake } from '../scoring.js';
import { PART_COLORS } from '../theme.js';
import { midiName } from '../dsp.js';
import { session as tabStorage } from '../storage.js';
import { Exercise } from '../components/exercise.js';
import { NoteFinder } from '../components/listening.js';
import { InAppCard } from '../components/mic-help.js';
import { ChordMoment } from '../components/chord-moment.js';
import { SessionWrap } from '../components/session-wrap.js';
import { ThenAndNow } from '../components/then-and-now.js';
import { VoiceCard } from '../components/voice-card.js';
import { LearnVisual } from '../components/learn-visual.js';
import { HEARD, LessonEnd, NoteResult, TakeResult } from '../components/results.js';
import { FactCard } from '../components/fact-card.js';
import { KeepSong } from '../components/keep-song.js';
import { daysInARow, easyRange, endLine, lessonMinutes, levelWord, noteLine, resultLine, unitPath } from '../results.js';
import { celebrate } from '../celebrate.js';
import { Icon, Logo } from '../components/icon.js';
import { toast } from '../components/ui.js';
import { count, countFor } from '../usage.js';
import { ShareResult } from '../components/share-result.js';
import { courseView, courseVisible, lessonAccess, lessonAfter } from '../courses.js';
import { isScored, stepOf } from '../lesson-steps.js';
import { TitleCard, markTitleSeen, titleSeen } from '../game/title-card.js';
import { endSharpen, sharpenAt, sharpenOn, sharpenStart } from '../sharpen.js';
import { effect } from '../game/effects.js';

const STEP_KIND = { learn: tk('course.step.learn'), try: tk('course.step.try'), play: tk('course.step.play'), use: tk('course.step.use'), record: tk('course.step.record'), home: tk('course.step.home') };
// Words for the scorer's tips (js/scoring.js TIPS).
const TIP_WORDS = { breathSupport: tk('course.tip.breathSupport'), relaxHigh: tk('course.tip.relaxHigh'), listenFirst: tk('course.tip.listenFirst'), onsets: tk('course.tip.onsets'), breatheEarly: tk('course.tip.breatheEarly'), waitForBeat: tk('course.tip.waitForBeat') };
const USED_SECONDS = 20; // singing in a lesson's song this long does the step that uses it
const DEFAULT_HOME = 57; // A3, where most untrained voices are comfortable, until unit 1 finds theirs
const FLIP_UNTIL_FOUND = 5; // semitones above the comfortable note, until the course finds the singer's flip

export const LearnView = {
    components: { VoiceCareLine, ChordMoment, SessionWrap, ThenAndNow, VoiceCard, FactCard, Exercise, InAppCard, KeepSong, LearnVisual, LessonEnd, NoteFinder, NoteResult, TakeResult, Icon, Logo, ShareResult, TitleCard },
    props: { id: { type: String, required: true } },
    // `kept`: this visit's sung steps, their results and takes by step index (a later step may build on
    // one: js/lesson-steps.js's `earlier`), emptied on replay and gone when the lesson closes.
    // `sharpen`: this lesson's place in a Sharpen up session (`{ place, total, start, nextId }`), or null.
    data: () => ({ sharpen: null, guiding: false, step: 0, result: null, done: null, ran: false, checking: false, error: null, askHeard: false, tip: null, used: false, skippedHome: false, noSound: false, skipped: false, micPermission: null, noteClip: null, kept: { results: {}, takes: {} }, titleCard: null }),
    setup: () => ({ account, learning, STEP_KIND, localized, PART_COLORS, TIP_WORDS, HEARD, game, sk }),
    template: `
        <div class="min-h-dvh bg-ink">
            <!-- Game mode's title card, the first time a lesson opens (docs/game-elements-plan.md) -->
            <TitleCard v-if="titleCard" v-bind="titleCard" @done="titleCard = null" />
            <header class="flex items-center gap-3 border-b border-line px-4 pb-3 pt-[max(0.75rem,env(safe-area-inset-top))]">
                <button type="button" class="grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream" :aria-label="t('course.backToCourse')" @click="leave"><Icon name="back" :size="22" /></button>
                <div v-if="entry" class="min-w-0 flex-1">
                    <p v-if="sharpen" class="truncate text-xs font-semibold text-accent-strong" data-sharpen-place>{{ t('sharpen.place', { place: sharpen.place, total: sharpen.total }) }}</p>
                    <p v-else class="truncate text-xs text-muted" translate="no">{{ localized(entry.unit.title) }}</p>
                    <p class="truncate font-bold text-cream" translate="no">{{ localized(entry.lesson.title) }}</p>
                </div>
                <!-- The step, once (80.png: no second label over the heading): its count and the bars. -->
                <div v-if="entry && !done" class="flex shrink-0 items-center gap-2">
                    <span class="text-xs tabular-nums text-muted">{{ t('course.stepCount', { step: step + 1, total: entry.lesson.steps.length }) }}<span class="sr-only"> · {{ t(STEP_KIND[current.kind] ?? STEP_KIND.play) }}</span></span>
                    <span class="flex w-24 gap-1" aria-hidden="true"><span v-for="(s, i) in entry.lesson.steps" :key="i" class="h-1.5 flex-1 rounded-full" :class="i <= step ? 'bg-accent' : 'bg-line'"></span></span>
                </div>
            </header>
            <div class="mx-auto flex flex-col gap-6 px-4 pb-[max(4rem,calc(env(safe-area-inset-bottom)+2rem))] pt-8" :class="split || (done && !done.unheard && !noteDone) ? 'max-w-5xl md:pt-16' : done && noteDone ? 'max-w-3xl' : takeResult && !done ? 'max-w-4xl' : 'max-w-xl'">
                <p v-if="!learning.loaded || (!entry && !learning.coursesLoaded)" class="text-muted" role="status">{{ t('course.loading') }}</p>
                <p v-else-if="!entry" class="text-cream">{{ t('course.noLesson') }} <a href="/" class="link" @click.prevent="navigate('/')">{{ t('course.backToCourse') }}</a></p>
                <p v-else-if="plusOnly" class="text-cream">{{ entry.course ? t('courses.plusOnly') : t('course.plusOnly') }} <a :href="plusLink" class="link" @click.prevent="navigate(plusLink)">{{ t('export.seePlus') }}</a> · <a :href="courseHome" class="link" @click.prevent="navigate(courseHome)">{{ t('course.backToCourse') }}</a></p>
                <p v-else-if="!open" class="text-cream">{{ t('course.locked') }} <a href="/" class="link" @click.prevent="navigate('/')">{{ t('course.backToCourse') }}</a></p>

                <!-- The end screen: couldn't hear (nothing earned, nothing lost) -->
                <section v-else-if="done && done.unheard" class="flex flex-col items-center gap-5 text-center" aria-labelledby="done-title">
                    <h1 id="done-title" ref="heading" tabindex="-1" class="text-3xl font-extrabold tracking-tight text-cream outline-none">{{ done.skipped ? t('course.done.skippedTitle') : sk('course.done.unheardTitle', 'game.done.continue') }}</h1>
                    <p class="max-w-prose text-muted">{{ done.skipped ? t('course.done.skipped') : t('course.done.unheard') }}</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <button type="button" class="btn-primary" @click="replay">{{ t('course.tryAgain') }}</button>
                        <button type="button" class="btn-secondary" @click="continueOn">{{ next ? t('course.next') : t('course.backToCourse') }}</button>
                    </div>
                </section>
                <!-- Your comfortable note (ux-spec 5.8, 48.png): the note, big, with the keyboard round it -->
                <section v-else-if="done && noteDone" class="flex flex-col items-center gap-6" aria-labelledby="done-title">
                    <h1 id="done-title" ref="heading" tabindex="-1" class="sr-only">{{ t(done.word) }}</h1>
                    <NoteResult ref="noteResult" :home="learning.progress.home" :range="easyRange(learning.progress.home, learning.progress.range)" :line="noteLine(learning.progress.home, voiceType(learning.progress.range))" :stars="done.stars" :xp="done.xp" :fresh="done.fresh" />
                    <!-- Your note in a chord (onboarding item 3): the end of the note you held, with two guide voices. -->
                    <ChordMoment v-if="noteClip && chordMoment" :clip="noteClip" :note="learning.progress.home" :color="color" />
                    <div v-if="askHeard" class="w-full space-y-3 border-t border-line pt-5 text-center">
                        <p class="text-sm text-muted">{{ t('results.heard') }}</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            <button v-for="(label, value) in HEARD" :key="value" type="button" class="chip tap-44 hover:text-cream" @click="saveHeard(value)">{{ t(label) }}</button>
                            <button type="button" class="chip tap-44 text-muted hover:text-cream" @click="askHeard = false">{{ t('course.heard.skip') }}</button>
                        </div>
                    </div>
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
                    <LessonEnd ref="end" :done="done" :path="path" :next="upNext" :ask-heard="askHeard" @next="continueOn" @replay="replay" @heard="saveHeard" @skip-heard="askHeard = false" />
                    <!-- Voice care (docs/voice-care-plan.md): one quiet line after a long day's singing, the cool-down a tap away. -->
                    <VoiceCareLine class="mx-auto mt-4 max-w-prose text-center" />
                    <!-- Keep the finale (js/keep-song.js): a finale that opts in, kept as a song in Sing. -->
                    <KeepSong v-if="entry.lesson.keepSong" class="mt-6" :lesson="entry.lesson" :kept="kept" :base="stepBase" />
                    <!-- The first session's wrap (onboarding step 5): what it gave, your week, one way to keep it. -->
                    <SessionWrap v-if="wrap" :lessons="wrap.lessons" :stars="wrap.stars" />
                    <!-- A guest's free lesson of a Plus course (Hillel let guests try one, 2026-10-30): kept here, and in an account once made. -->
                    <section v-if="entry.course && !account.me" class="w-full space-y-3 rounded-3xl border border-edge bg-panel p-5 text-start" aria-labelledby="guest-save-title" data-guest-save>
                        <h2 id="guest-save-title" class="font-bold text-cream">{{ t('courses.guest.title') }}</h2>
                        <p class="text-sm text-muted">{{ t('courses.guest.body', { course: localized(entry.course.title) }) }}</p>
                        <a :href="guestSignUp" class="btn-primary btn-sm" data-guest-signup @click.prevent="navigate(guestSignUp)">{{ t('wrap.makeAccount') }}</a>
                    </section>
                    <!-- The free course's first unit done (onboarding step 6): your voice card, to keep and share. -->
                    <VoiceCard v-if="voiceCardShown" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" />
                    <!-- The lesson's fact card (docs/courses-ideas.md, idea 1): below the stars and the wraps, in both skins. -->
                    <FactCard v-if="entry.lesson?.fact" :fact="entry.lesson.fact" />
                    <!-- Share where the celebration is (onboarding step 4): on two or three stars, with the take that earned them. -->
                    <!-- Not beside the session wrap (one keep-it ask beside the goal): it comes back on the next lesson's end. -->
                    <div v-if="done.stars >= 2 && takeResult && !wrap" class="flex justify-center" data-end-share>
                        <ShareResult :label="game() ? t('boards.challenge.button') : t('results.share.result')" :result="takeResult" :record="!!done.newBest" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" />
                    </div>
                    <p v-if="done.tip && TIP_WORDS[done.tip]" class="max-w-prose text-sm text-cream">{{ t('course.done.tip') }} {{ t(TIP_WORDS[done.tip]) }}</p>
                    <!-- The free course's last lesson: the celebration first, then the way on (an upgrade moment). -->
                    <div v-if="keepGoing" class="w-full rounded-2xl border border-part-2/40 p-4 text-start">
                        <p class="font-semibold text-cream">{{ t('plus.moment.courseUnit7') }}</p>
                        <a href="/plus?from=course_unit7" class="btn-go" @click.prevent="navigate('/plus?from=course_unit7')">{{ t('export.seePlus') }}</a>
                    </div>
                </template>

                <!-- A step -->
                <!-- A Learn step: its takeaway and steps on the left, its picture on the right (above, smaller, on a phone). -->
                <section v-else :aria-label="t(STEP_KIND[current.kind] ?? STEP_KIND.play)" :class="split ? 'grid gap-8 md:grid-cols-2 md:items-center md:gap-14' : ''">
                    <!-- In an app's own browser, before anything else: the mic likely won't work here. -->
                    <InAppCard v-if="askMic && inApp" :app="inApp" class="md:col-span-2" />
                    <div v-if="split" class="md:order-2 md:pb-8"><LearnVisual :key="'v' + step" :type="current.visual" class="max-w-[12rem] md:max-w-[22rem]" /></div>
                    <div class="flex flex-col gap-5">
                    <p v-if="step === 0 && entry.lesson.findsHome && chordMoment" class="-mb-2 text-sm font-semibold text-accent-strong" data-chord-promise>{{ t('chordMoment.promise') }}</p>
                    <p v-if="current.kind === 'home'" class="-mb-3 text-sm font-semibold text-accent-strong" translate="no">{{ localized(entry.unit.title) }} · {{ t('course.stepLabel', { step: step + 1 }) }}</p>
                    <h1 ref="heading" tabindex="-1" class="font-bold leading-snug text-cream outline-none" :class="current.headline ? 'text-3xl font-extrabold tracking-tight' : 'text-2xl'" translate="no">{{ localized(current.headline ?? current.text) }}</h1>
                    <ol v-if="current.steps?.length" class="space-y-2.5">
                        <li v-for="(item, i) in current.steps" :key="i" class="flex items-center gap-4 rounded-2xl border border-edge bg-panel px-4 py-3.5">
                            <span class="w-5 shrink-0 text-center text-xl font-extrabold tabular-nums text-accent-strong" aria-hidden="true">{{ i + 1 }}</span>
                            <span class="min-w-0"><span class="block font-bold text-cream" translate="no">{{ localized(item.title) }}</span><span class="block text-sm text-muted" translate="no">{{ localized(item.detail) }}</span></span>
                        </li>
                    </ol>
                    <p v-if="needsFlip" class="rounded-2xl border border-line p-3 text-sm text-muted" role="status" data-needs-flip>{{ t('courses.flipFirst') }}</p>
                    <div v-if="learning.progress.home === null && !entry.lesson.findsHome && (current.kind === 'try' || current.kind === 'play')" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line p-3 text-sm text-muted"><span>{{ t('course.home.reminder') }}</span><button type="button" class="btn-ghost btn-sm" @click="navigate('/learn/' + homeLesson)">{{ t('course.home.reminderLink') }}</button></div>

                    <Exercise v-if="current.kind === 'learn' && current.listen" :key="'l' + step" :exercise="current.listen" :base="stepBase" mode="listen" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="moveKey(-1)" @higher="moveKey(1)" @done="ran = true" />
                    <Exercise v-else-if="current.kind === 'try'" :key="'t' + step" :exercise="current.exercise" :base="stepBase" mode="try" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="moveKey(-1)" @higher="moveKey(1)" @done="ran = true" @practised="practised" />
                    <template v-else-if="current.kind === 'play'">
                        <Exercise v-show="!takeResult || guiding" ref="exercise" :key="'p' + step" earns-bonus :exercise="current.exercise" :base="stepBase" :under-take="underTake" :personal="personal" mode="play" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" @lower="moveKey(-1)" @higher="moveKey(1)" @done="scored" @practised="practised" />
                        <!-- The take's result (ux-spec 5.8, 49.png) -->
                        <template v-if="takeResult && !guiding">
                            <TakeResult :result="takeResult" :xp="takeXp" :together-first="!entry.course && entry.unitIndex === 0" />
                            <ThenAndNow v-if="current.exercise?.thenAndNow" :id="current.exercise.thenAndNow" :now="result?.take ?? null" />
                            <p v-if="vibratoLine" class="text-cream" role="status">{{ vibratoLine }}</p>
                            <!-- A course's own measure, said plainly (a registered scorer's line, js/lesson-scores.js). -->
                            <p v-if="result?.line" class="text-cream" data-measured-line>{{ t(result.line.key, result.line.values ?? {}) }}</p>
                            <p v-for="(line, i) in harmonyLines" :key="'h' + i" class="text-cream">{{ line }}</p>
                            <p v-if="measuresLine" class="text-sm text-muted">{{ measuresLine }}</p>
                            <div class="flex flex-wrap gap-3">
                                <button type="button" class="btn-secondary" @click="playAgain">{{ takeResult.stars < 3 && game() ? t('results.playAgainStars') : t('course.replay') }}</button>
                                <!-- After two weak takes: a take with the guide singing too, unscored (components/exercise.js). -->
                                <button v-if="result?.offerGuide" type="button" class="btn-ghost" data-sing-with-guide @click="singWithGuide">{{ t('course.withGuide') }}</button>
                                <!-- Share your result (js/result-card.js): with a star to show off -->
                                <ShareResult v-if="takeResult.stars > 0" :result="takeResult" :entry="entry" :lesson-title="localized(entry.lesson.title)" :unit-title="localized(entry.unit.title)" :color="color" />
                            </div>
                        </template>
                        <p v-else-if="result" class="text-center text-cream" role="status">{{ result.percent === null ? t('course.notHeard') : t('course.result', { percent: result.percent, stars: starsOf(result) }) }}</p>
                    </template>
                    <!-- A Plus course's own step (js/lesson-steps.js: the trace lane, listen and choose, ...): it shows its own result. -->
                    <component :is="registered.component" v-else-if="registered" :key="'r' + step" :exercise="current.exercise" :base="stepBase" :strictness="strictness" :color="color" :music-key="musicKey" :can-lower="canLower" :can-higher="canHigher" :earlier="kept" :personal="personal" @lower="moveKey(-1)" @higher="moveKey(1)" @done="registeredDone" @practised="practised" />
                    <template v-else-if="current.kind === 'home'">
                        <NoteFinder :color="color" @found="foundHome" @skip="skipHome" @no-sound="(now) => (noSound = now)" />
                        <p v-if="skippedHome" class="text-center text-muted" role="status">{{ t('course.home.skipped') }}</p>
                    </template>
                    <template v-else-if="current.kind === 'use' || current.kind === 'record'">
                        <button type="button" class="btn-primary btn-lg self-start" :disabled="checking" @click="openSong"><Icon name="mic" :size="18" /> {{ current.kind === 'record' ? t('course.recordIn') : t('course.openIn') }}</button>
                        <p v-if="current.kind === 'use'" class="text-sm text-muted" role="status">{{ used ? t('course.usedIt') : t('course.useFirst') }}</p>
                        <p v-if="current.kind === 'record' && result" class="text-cream" role="status">{{ result.noTake ? t('course.recordFirst') : result.percent === null ? t('course.notHeard') : t('course.result', { percent: result.percent, stars: starsOf(result) }) }}</p>
                        <p v-if="current.kind === 'record' && result && measuresLine" class="text-sm text-muted">{{ measuresLine }}</p>
                        <button v-if="current.kind === 'record'" type="button" class="btn-ghost btn-sm self-start" :disabled="checking" @click="checkRecording">{{ checking ? t('course.checking') : t('course.checkRecording') }}</button>
                    </template>
                    <p v-if="error" class="text-sm text-bad" role="alert">{{ error }}</p>

                    <!-- The foot, pinned: Back in glass, and the way on (or past) at the end. A sung step (128.png):
                         Skip, quiet, and a wide Continue that waits until it's done. -->
                    <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-between gap-3 bg-ink/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur">
                        <!-- Skip only while there's something to skip: once the step is done, Back (onboarding review 2, 12). -->
                        <button v-if="sungStep && !canGoOn" type="button" class="link-quiet min-h-11 px-2 text-sm" @click="skip">{{ t('course.skip') }}</button>
                        <button v-else type="button" class="btn-secondary" @click="step === 0 ? goBack('/') : go(step - 1)">{{ t('common.back') }}</button>
                        <!-- Finding your note, there's nothing to press until a note is held (or skipped): a hint, not a dead button. -->
                        <button v-if="current.kind === 'home' && !canGoOn" type="button" class="link-quiet min-h-11 text-sm" @click="skipHome">{{ noSound ? t('micCheck.skipSetup') : t('course.home.skip') }}</button>
                        <p v-else-if="current.kind === 'record' && !canGoOn" class="text-sm text-muted">{{ t('course.recordGoOnHint') }}</p>
                        <!-- A sung step (Hillel, as the warm-up): what's left to do beside the way on, which lights up once it's done. -->
                        <div v-else class="ms-auto flex items-center gap-3">
                            <span v-if="sungStep && !canGoOn" class="text-end text-sm text-muted" data-go-on-hint>{{ registered?.hint ? t(last ? registered.hint.finish : registered.hint.goOn) : last ? t('course.singToFinish') : t('course.singToGoOn') }}</span>
                            <button type="button" class="btn-primary" :disabled="!canGoOn" data-go-on @click="goOn"><Icon v-if="askMic && !inApp" name="mic" :size="18" /> {{ askMic ? (inApp ? t('course.mic.carryOn') : t('course.mic.turnOn')) : last ? t('course.finish') : t('course.continue') }}</button>
                        </div>
                        <!-- Before the browser asks for the mic: why we listen, in one line (docs/onboarding-review.md, step 2). -->
                        <p v-if="askMic" class="basis-full text-center text-xs text-muted sm:text-end" data-mic-why>{{ t('course.mic.why') }}</p>
                    </div>
                    </div>
                </section>
            </div>
        </div>`,
    computed: {
        /** The lesson, in the free course or a Plus course this account can see (else none, as an unknown id). */
        entry(vm) {
            const entry = learning.loaded ? findLesson(vm.id) : null;
            if (!entry?.course) return entry;
            return courseVisible(learning.catalogue.find((c) => c.id === entry.course.id), account.me, account.config) ? entry : null;
        },
        /** The progress this lesson's course reads: a Plus course's own lessons, or the free course's. */
        courseProgress: (vm) => (vm.entry?.course ? courseView(learning.progress, vm.entry.course.id) : learning.progress),
        /** A Plus course's own values for its scorers (`progress.courses.<id>.personal`); none for the free course. */
        personal: (vm) => (vm.entry?.course ? learning.progress.courses?.[vm.entry.course.id]?.personal ?? {} : {}),
        /** A Plus course's gates: staff try every lesson before launch (js/courses.js). */
        access: (vm) => (vm.entry?.course ? lessonAccess(vm.entry.course, vm.id, learning.progress, { plus: vm.plus, staff: !!account.me?.staff }) : null),
        /** Where Back to the course goes: the Plus course's page, or the free course. */
        courseHome: (vm) => (vm.entry?.course ? `/learn/c/${vm.entry.course.id}` : '/learn'),
        /** See Plus: the free course's moment; a Plus course's waits for its own (billing.checkout's `plus_course`). */
        plusLink: (vm) => (vm.entry?.course ? '/plus' : '/plus?from=course_unit7'),
        /** A Learn step with a picture: side by side on a wide screen. */
        split: (vm) => !vm.done && vm.current?.kind === 'learn' && !!vm.current.visual,
        open: (vm) => !!vm.entry && (vm.entry.cooldown || (vm.access ? vm.access.open : unitOpen(learning.course.units, vm.entry.unitIndex, learning.progress))),
        plus: () => account.me?.plan === 'plus',
        /** The lesson that finds your note, for the reminder when it was skipped. */
        homeLesson: () => learning.course?.units[0]?.lessons.find((l) => l.findsHome)?.id ?? null,
        /** A Plus unit's lesson, for someone without Plus. */
        /** A guest's sign-up from a Plus course's free lesson: the progress reason, then back to the course. */
        guestSignUp() {
            return `/signin?mode=signup&for=progress&next=${encodeURIComponent(`/learn/c/${this.entry?.course?.id ?? ''}`)}`;
        },
        plusOnly: (vm) => (vm.access ? vm.access.plusOnly : !!vm.entry?.unit.plus && !vm.plus),
        /** Finished the last free unit's challenge, on the free plan: where the course goes next. */
        keepGoing(vm) {
            if (vm.plus || !vm.done || vm.done.unheard || !learning.course || vm.entry?.course) return false;
            const units = learning.course.units;
            const lastFree = units.findLastIndex((u) => !u.plus);
            return vm.entry?.unitIndex === lastFree && vm.entry.index === units[lastFree].lessons.length - 1;
        },
        current: (vm) => vm.entry?.lesson.steps[vm.step] ?? null,
        /** The voice card: the free course's first unit, every lesson in it with stars. */
        voiceCardShown() {
            if (!this.done || this.done.unheard || this.entry?.course || this.entry?.unitIndex !== 0) return false;
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
            if (!this.done || this.done.unheard || this.entry?.course || !arrivedToday()) return null;
            const sung = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars);
            return sung.length >= 3 ? { lessons: sung.length, stars: sung.reduce((sum, l) => sum + l.stars, 0) } : null;
        },
        /** The chord moment shows, unless this device is in the onb1 experiment's control arm (docs/onboarding-review.md section 4). */
        chordMoment: () => variant() !== 'onb1-a',
        /** The way on opens the mic for the first time (the next step finds your note): it says so, and why. */
        askMic: (vm) => vm.entry?.lesson.steps[vm.step + 1]?.kind === 'home' && !audio.mic && vm.micPermission !== 'granted',
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
            return step?.exercise?.score === 'hold' ? t('course.stars.hold') : '';
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
            return { lesson, minutes: lessonMinutes(lesson), visual: learn?.visual ?? null, line: said?.headline ?? null };
        },
        last: (vm) => !!vm.entry && vm.step === vm.entry.lesson.steps.length - 1,
        color: (vm) => vm.paint(PART_COLORS[((vm.entry?.unit.colour ?? 4) - 1) % PART_COLORS.length]),
        /** Lower or Higher on this lesson (128.png), kept with the course's progress. */
        keyShift: (vm) => learning.progress.keys?.[vm.id] ?? 0,
        /** The note the exercises are built on: the one that fits your voice (unit 1 finds it), moved by the key. */
        base: (vm) => (learning.progress.home ?? DEFAULT_HOME) + vm.keyShift,
        /**
         * The note this step is built on: the base, or for `exercise.base: 'flip'` (Yodeling) the
         * singer's own flip note above it, kept by the course (`personal.flip`; a fifth until it's found).
         */
        // A Learn step's example (`listen`) is laid the same way as the exercise it introduces.
        stepBase: (vm) => vm.base + ((vm.current?.exercise ?? vm.current?.listen)?.base === 'flip' ? vm.personal.flip ?? FLIP_UNTIL_FOUND : 0),
        /** A step built on the flip note before the course has found it. */
        needsFlip: (vm) => (vm.current?.exercise ?? vm.current?.listen)?.base === 'flip' && vm.personal.flip === undefined,
        /** An earlier step's take this step sings over (`exercise.under: { step }`), from this visit. */
        underTake: (vm) => (vm.current?.exercise?.under ? vm.kept.takes[vm.current.exercise.under.step] ?? null : null),
        /** "Key D♭": the exercises' tonic, named as a key. */
        musicKey: (vm) => keyName({ tonic: vm.base, mode: 'major' }),
        canLower: (vm) => vm.keyShift > -KEY_REACH,
        canHigher: (vm) => vm.keyShift < KEY_REACH,
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
            if (kind === 'play' || (this.registered && isScored(kind))) return !!this.result;
            if (this.registered) return this.ran; // an unscored registered step: once it's done
            if (kind === 'record') return !!this.result && !this.result.noTake;
            if (kind === 'home') return (learning.progress.home !== null && this.ran) || this.skippedHome;
            if (kind === 'use') return this.used;
            if (kind === 'try') return this.ran; // Wait for me ends by itself once every note is found
            return true;
        },
        /** The lesson after this one, if it's open (an experienced singer jumps from their note to unit 5). */
        next() {
            if (!this.entry) return null;
            if (this.sharpen) return findLesson(this.sharpen.nextId ?? '')?.lesson ?? null; // the session's next, or its end
            if (this.entry.course) return lessonAfter(this.entry.course, this.entry.lesson.id, learning.progress, { plus: this.plus, staff: !!account.me?.staff });
            const units = learning.course.units;
            const here = this.entry;
            const lessons = units.flatMap((unit, unitIndex) => unit.lessons.map((lesson) => ({ unit, unitIndex, lesson })));
            const after = lessons[lessons.findIndex((l) => l.lesson.id === here.lesson.id) + 1];
            return after && (this.plus || !after.unit.plus) && unitOpen(units, after.unitIndex, learning.progress) ? after.lesson : null;
        },
    },
    watch: {
        id() {
            this.sharpen = null;
            this.reset();
            this.beginSharpen();
            this.showTitle();
        },
        /**
         * A take's result: its stars' sound as the last lands (two or three), and focus on its
         * heading, so a screen reader says the measure and Tab goes on to Listen back and the rest.
         */
        takeResult(now, before) {
            if (now && !before && now.stars >= 2) starsSound(now.stars, 0.35 + (now.stars - 1) * 0.3);
            if (now && !before) this.$nextTick(() => document.querySelector('[data-result-heading]')?.focus({ preventScroll: true }));
        },
        step() {
            this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
        },
    },
    async created() {
        // Already allowed (an earlier visit): the way on is plain Continue. Not every browser can say.
        navigator.permissions
            ?.query({ name: 'microphone' })
            .then((status) => (this.micPermission = status.state))
            .catch((error) => console.info('The mic permission could not be read', error?.message ?? error));
        await loadLearning();
        if (this.id === COOL_DOWN) await loadCooldown(); // voice care's cool-down, outside every course
        else if (!this.entry) await loadPlusCourses(); // a Plus course's lesson: its course first
        // Back from Sing: where the learner was in this lesson.
        const kept = Number(tabStorage.get(`stackapella.lesson.${this.id}`));
        if (kept && this.entry && kept < this.entry.lesson.steps.length) {
            this.step = kept;
            this.beginSharpen({ resumed: true });
        } else if (this.entry) {
            count('lesson_start'); // a lesson begun, not one come back to from Sing
            countFor(this.entry.course ? 'pl_s' : 'fl_s', this.entry.course ? this.entry.course.id : this.entry.lesson.id); // by course, or the free course's lesson
            this.beginSharpen();
            this.showTitle();
        }
        if (this.current?.kind === 'record') this.checkRecording();
        if (this.current?.kind === 'use') this.checkUse();
        this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
    },
    beforeUnmount() {
        closeMic(); // the exercises' mic: done with until the next lesson
    },
    methods: {
        /** Game mode's title card ("Stage 2-3"), once a lesson on this device, as it begins. */
        showTitle() {
            const entry = this.entry;
            // Never on the cool-down: it's no stage of the game (unscored, outside every course).
            if (!entry || entry.lesson.unscored || !this.open || this.step !== 0 || this.sharpen || !effect('titles') || titleSeen(entry.lesson.id)) return;
            markTitleSeen(entry.lesson.id);
            this.titleCard = { unit: entry.unitIndex + 1, lesson: entry.index + 1, unitTitle: localized(entry.unit.title), lessonTitle: localized(entry.lesson.title), colour: this.color };
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
            navigate(this.courseHome);
        },
        reset() {
            Object.assign(this, { guiding: false, step: this.sharpen?.start ?? 0, result: null, done: null, ran: false, error: null, tip: null, used: false, skippedHome: false, skipped: false, kept: { results: {}, takes: {} } });
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
        /** A result's stars: from its percent, never under a generous first lesson's floor (`minStars`). */
        starsOf(result) {
            return Math.max(starsFor({ percent: result.percent, timing: 'none' }), result.minStars ?? 0);
        },
        scored(result) {
            this.result = result;
            this.tip = result.tips?.[0] ?? null;
            // Kept for a later step of this visit (its take beneath it, its result to compare).
            const { take = null, ...rest } = result;
            this.kept = { results: { ...this.kept.results, [this.step]: rest }, takes: { ...this.kept.takes, [this.step]: take ?? result.chart?.take ?? null } };
            // A Plus course's own values (a first hold's length as the learner's baseline), kept with its progress.
            if (result.personal && this.entry?.course) setCoursePersonal(this.entry.course.id, result.personal).catch((error) => console.error('Not kept', error));
            // The notes sung on pitch widen the range the course knows (unit 1 finds it as you sing).
            if (result.percent !== null && result.sung?.length) noteSung(Math.min(...result.sung), Math.max(...result.sung));
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
            // Unscored (the cool-down): no stars, XP, progress or celebration, just its own last line.
            if (this.entry.lesson.unscored) {
                tabStorage.set(`stackapella.lesson.${this.id}`, '');
                this.done = { unscored: true };
                this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
                return;
            }
            const scoredStep = [...this.entry.lesson.steps].reverse().find((s) => isScored(s.kind));
            const percent = scoredStep ? this.result?.percent ?? null : null;
            tabStorage.set(`stackapella.lesson.${this.id}`, '');
            if (scoredStep && percent === null) {
                // Couldn't hear: it earns nothing and costs nothing (the stars kept stay as they were).
                this.done = { stars: 0, xp: 0, unheard: true, skipped: this.skipped, tip: null };
                this.$nextTick(() => this.$refs.heading?.focus({ preventScroll: true }));
                return;
            }
            const stars = scoredStep ? Math.max(1, this.starsOf(this.result)) : this.skippedHome ? 1 : 3;
            const before = this.courseProgress.lessons[this.id]?.stars ?? 0;
            const outcome = await finishLesson(this.id, { percent, stars, bonus: scoredStep ? this.result?.comboBonus ?? 0 : 0 }); // the combo bonus: notes lit in runs of 10 or more
            const unit = this.entry.unit;
            this.done = {
                label: scoredStep ? this.result?.label ?? null : null, // a course measure's name for the percent
                ...outcome,
                tip: typeof this.tip === 'string' ? this.tip : null,
                word: levelWord(outcome.stars, percent ?? 100),
                line: endLine({ stars: outcome.stars, before, scored: !!scoredStep }),
                fresh: outcome.stars >= 2,
                percent, // serious mode's heading: the measure, not the stars
                streak: daysInARow(learning.progress),
                place: unit.lessons.findIndex((l) => l.id === this.id) + 1,
                total: unit.lessons.length,
                unit: this.entry.unitIndex + 1, // game mode's stage clear names the unit and the lesson
                sharpened: !!this.sharpen && !this.sharpen.nextId, // a Sharpen up session's last lesson
            };
            this.chime(outcome.stars);
            // A level, or the unit (or the whole course) finished for the first time: the special sound, after the stars.
            const unitDone = before === 0 && unit.lessons.at(-1)?.id === this.id;
            if (outcome.levelledUp || unitDone) playSound('special');
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
        /** "Use it": done once the lesson's song has been sung in (for a while) or recorded. */
        async checkUse() {
            const step = this.current;
            await loadProjects();
            const project = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === step.template);
            this.used = !!project && ((project.practice?.sungSeconds ?? 0) >= USED_SECONDS || !!project.recorded);
        },
        continueOn() {
            if (this.sharpen) sharpenOn(this.id); // the session moves on (or ends, with the course page next)
            navigate(this.next ? `/learn/${this.next.id}` : this.entry?.course ? this.courseHome : '/');
        },
        /** The course's own copy of the song, made once per lesson and reopened after. */
        async courseProject() {
            await loadProjects();
            const step = this.current;
            const existing = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === step.template);
            if (existing) return existing;
            const template = await loadTemplate(step.template);
            const focusKey = step.part === 'all' ? partsFor(template, step.singers)[0].key : step.part;
            return startFromTemplate(template, { singers: step.singers, focusKey, course: { lessonId: this.id } });
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
                await loadProjects();
                const project = projects.all.find((p) => p.course?.lessonId === this.id && p.template?.id === step.template);
                if (!project) return;
                const { parts, template, takes } = await takesOf(project.id);
                const wanted = parts.filter((p) => p.templatePartKey && (step.part === 'all' || p.templatePartKey === step.part));
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
