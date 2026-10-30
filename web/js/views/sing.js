// Sing (docs/singing-ux-plan.md): a template project sung part by part (the coach and the phrase
// path) or as the whole song (the Stage with words or the staff, or the Notes lane), over the same
// session as the studio (js/stores/session.js), which stays the mixer and editor. At /p/<id>/sing.

import { VoiceCareLine } from '../components/voice-care.js';
import { game } from '../skin.js';
import { toRefs } from 'vue';
import { audio, closeMic, engine, openMic } from '../audio.js';
import { follow, hintStep, voiceFollower } from '../livevoice.js';
import { formatList, t, tk } from '../i18n.js';
import { BACKING, GUIDE_LEVELS, guideId, hasTake } from '../practice.js';
import { RecordSetup } from '../record-setup.js';
import { goBack, navigate } from '../router.js';
import { clefFor, currentPhrase, nextStep, nowText, partSaying, practised, progressOf, staffLayout, stepDone, timelineAt, toStep } from '../sing.js';
import { scoreTake } from '../scoring.js';
import { trackFrame, trackInWorker, VOICED_CLARITY } from '../pitchtrack.js';
import * as sessionStore from '../stores/session.js';
import { account } from '../stores/account.js';
import { count } from '../usage.js';
import { local } from '../storage.js';
import { countInText } from '../components/count-in.js';
import { session } from '../stores/session.js';
import { slotName } from '../templates.js';
import { newSinger, shownTitle } from '../stores/projects.js';
import { beatsPerBar, formatTime } from '../theme.js';
import { midiName, octaveFit, suggestPart, suggestTranspose } from '../dsp.js';
import { HeadphonesDialog, RangeDialog, TimingDialog } from '../components/dialogs.js';
import { Icon } from '../components/icon.js';
import { Lane, NowCard } from '../components/sing/lane.js';
import { toast } from '../components/ui.js';
import { joinSyllables } from '../components/practice.js';
import { Chooser } from '../components/sing/chooser.js';
import { Stage } from '../components/sing/stage.js';
import { RecordingStage } from '../components/sing/recording.js';
import { Coach, PhraseStage, StepDone } from '../components/sing/coach.js';
import { JustRecorded } from '../components/sing/recorded.js';
import { IdeaVoice } from '../components/idea-voice.js';
import { LineBar, ModeBar, SingHeader, Transport, TurnBand } from '../components/sing/frame.js';
import { PopupSlot } from '../game/hud.js';
import { effect } from '../game/effects.js';
import { comboMilestone, heldToEnd, popupQueue } from '../game/popups.js';
import { crtOnce } from '../game/crt.js';
import { decodeGhost, encodeGhost, keepDeviceGhost, loadGhost, placeGhost, saveGhost } from '../game/ghost.js';
import { stampDaily } from '../game/daily.js';
import { SCORING_VERSION } from '../scoring.js';
import { run } from '../game/motion.js';
import { PartSheet, SettingsSheet } from '../components/sing/sheets.js';
import { chordName, noteLabel } from '../notes.js';
import { prefs } from '../stores/prefs.js';
import { voiceNote, VoiceNote } from '../components/voice-note.js';
import { ResingSheet, ResungCard } from '../components/sing/resing.js';
import { WhatsNext } from '../components/whatsnext.js';
import { LockedBanner } from '../components/cloud.js';
import { ShareSheet } from '../components/share.js';
import { lockReason } from '../stores/plan.js';
import { paint } from '../colors.js';
import { appearance } from '../stores/appearance.js';
import { phraseBests, scoreSummary, sinceLast } from '../report.js';
import { bleedCheck } from '../quality.js';
import * as db from '../db.js';
import { counted as countDone, learning, loadLearning, pickRange, remember } from '../stores/learning.js';
import { TrimSheet } from '../components/trim.js';
import { heardSpan } from '../trim.js';
import { TakeBest } from '../components/boards.js';

const LADDER_CLEAN = 0.8; // Build up to speed: a round is clean with this share of its notes lit
const PHASE = {
    listening: tk('sing.phase.listening'),
    getReady: tk('sing.phase.getReady'),
    turn: tk('sing.phase.turn'),
    paused: tk('sing.phase.ready'),
};

const HEAR_RECORDING = { headphones: tk('sing.hear.recording'), softer: tk('sing.hear.recordingSofter') };
const CLARITY_HOLD = 0.15; // how far under the voiced clarity a showing voice may dip and stay
const TEMPO_STEPS = [0.7, 0.85, 1]; // Tempo's - and + (98.png), as Session settings' speeds
const KEY_STEPS = 7; // Lower and Higher reach this many semitones from the song's own key
const FIT_SPAN = 7; // a range narrower than a fifth (one note found, say) doesn't choose a key

/** What's said when a song opens on the part that suits your range: in your octave, or an octave off. */
const FIT_WORDS = { fits: tk('sing.fitsYourRange'), lower: tk('sing.fitsYourRangeLower'), higher: tk('sing.fitsYourRangeHigher'), closest: tk('sing.closestPart') };

export const SingView = {
    mixins: [RecordSetup],
    components: { VoiceCareLine, PopupSlot, TakeBest, IdeaVoice, PhraseStage, Chooser, Stage, RecordingStage, Lane, NowCard, Coach, StepDone, JustRecorded, LineBar, ModeBar, SingHeader, Transport, TurnBand, PartSheet, SettingsSheet, HeadphonesDialog, TimingDialog, RangeDialog, Icon, VoiceNote, ResingSheet, ResungCard, TrimSheet, WhatsNext, LockedBanner, ShareSheet },
    props: { id: { type: String, required: true } },
    setup: () => ({ ...toRefs(session), audio, formatTime, shownTitle, prefs, game, effect }),
    data: () => ({ crt: false, ghostTake: null, pendingGhost: null, hud: popupQueue(), viewPicked: false, readied: null, combo: 0, hintState: { hint: null }, voice: voiceFollower(), dialog: null, choosing: false, recorded: null, said: '', suggested: null, celebrated: null, lining: false, lined: null, resingPlanned: null, resung: null, takeNumbers: {}, fitSuggestion: null }),
    template: `
        <div class="flex h-dvh flex-col bg-ink">
            <p class="sr-only" aria-live="polite">{{ said }}</p>
            <div v-if="missing" class="m-auto text-center">
                <p class="text-cream">{{ t('studio.missing') }}</p>
                <button type="button" class="btn-primary mt-4" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </div>
            <p v-else-if="!project" class="m-auto text-muted" role="status">{{ t('sing.loading') }}</p>
            <div v-else-if="!isTemplate" class="m-auto max-w-sm px-4 text-center">
                <p class="text-cream">{{ t('sing.notTemplate') }}</p>
                <button type="button" class="btn-primary mt-4" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </div>
            <Chooser v-else-if="choosing || !way || !focusPart" :parts="partChoices" :transpose="project.transpose || 0" :way="choosing ? null : way" :suggested="suggested"
                :title="shownTitle(project)" :focus="focusPart ? { slot: focusPart, name: partName, range: templatePart(focusPart)?.range ?? null } : null" :bpm="project.bpm" :phrases="phraseRows.length" :length="lengthSeconds" :auto="!choosing"
                @choose="choose" @range="dialog = 'range'" @back="leave" @studio="openStudio" />
            <!-- Recording strips everything else away (113.png): the lane, the words, Stop -->
            <RecordingStage v-else-if="recording" :title="shownTitle(project)" :named="!!project.title?.trim()" :part-name="partName" :color="paint(focusPart.color)" :take="takeNumbers[rerecordId] ?? 1" :hearing="hearLine"
                :count-in="countIn" :position="position" :length="lengthSeconds" :busy="busy" :on-pitch="onPitch" :line="hasWords ? currentLine : null" :next="hasWords ? nextLine : null" @stop="recordOrStop" @again="startOver">
                <template #hearing><span class="relative block"><button type="button" class="relative grid size-11 shrink-0 place-items-center rounded-full glass-bubble text-cream" :aria-label="t('sing.settings.open')" data-settings-open @click="dialog = 'settings'"><Icon name="sliders" :size="20" /><span v-if="hearingChanged" class="absolute end-0 top-0 size-3 rounded-full bg-accent ring-2 ring-ink" data-hearing-changed aria-hidden="true"></span></button>
                    <!-- Game mode's bonus pop-ups (docs/game-elements-plan.md): under the header's end, never on the lane -->
                    <PopupSlot v-if="effect('popups')" class="ge-slot--float" :queue="hud" /></span></template>
                <Lane :notes="notes" :words="hasWords ? [] : laneWords" :transpose="project.transpose || 0" :beat-seconds="beatSeconds" :position="position" :color="paint(focusPart.color)" state="recording" :note-name="laneName"
                    :keyboard="prefs.keyboard" :voice="voice.points" :lit="voice.lit" :hits="voice.hits" :trail="onFire ? 'var(--ge-fire)' : null" :ghost="ghostLine" :hint="hintState.hint" :hint-text="hintChip" :data-voice="voice.points.length" :data-lit="voice.lit.size" />
            </RecordingStage>
            <template v-else>
                <!-- Your turn is a band in your colour; otherwise the song, and your part with what's happening -->
                <TurnBand v-if="bandShown" :color="paint(focusPart.color)" :others="otherNames" :marked="hearingChanged" @settings="dialog = 'settings'" />
                <SingHeader v-else :title="shownTitle(project)" :named="!!project.title?.trim()" :info="songInfo" :part-name="partName" :color="paint(focusPart.color)" :chip="chip"
                    :recording="recording" :recorded-seconds="recordedSeconds" :count-in="countIn" :about="about" :badge="settingsBadge"
                    @back="leave" @part="dialog = 'part'" @settings="dialog = 'settings'" :marked="hearingChanged" />
                <LockedBanner v-if="locked" :reason="locked" class="mx-4 mt-2 md:mx-6" />
                <ModeBar v-if="!recording && !bandShown && !recorded && !resung && (way === 'whole' || coach.step === 'record')" :class="coaching && !playing ? 'hidden md:flex' : ''" :mode="practice.mode" @mode="setMode" />
                <VoiceNote v-if="!recording" @use-range="useRange" />

                <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                    <Coach v-if="way === 'parts' && !recording" class="min-h-0 flex-1 md:w-96 md:flex-none md:shrink-0 md:border-e md:border-line" :class="overlay ? 'hidden md:flex' : ''" :part-name="partName" :color="paint(focusPart.color)" :step="coach.step"
                        :phrases="phraseRows" :counts="coach.phrases" :here="here" :playing="playing"
                        :music-key="keyMovable ? project.key || project.template.key : null" :can-lower="keyLowerable" :can-higher="keyRaisable" @lower="moveKey(-1)" @higher="moveKey(1)"
                        @action="coachAction" @phrase="playPhrase" @repeat="repeatPhrase" @first-note="firstNote" @step="setStep" @other-part="dialog = 'part'" />
                    <!-- The stage: words or staff, or the Notes lane -->
                    <div class="relative min-h-0 flex-1 flex-col overflow-y-auto text-cream" :class="way === 'parts' && !overlay && !recording ? 'hidden md:flex' : 'flex'" :data-stage-view="phraseStage ? 'phrase' : view">
                        <ResungCard v-if="resung" class="m-auto" :different="resung.different" :hearing="playing" :line="resungLine"
                            @hear="hearResung" @keep="keepResung" @again="resingAgain" @whole="resingWhole" @cancel="dropResung" />
                        <JustRecorded v-else-if="recorded" class="m-auto" :outcome="recorded" :part-name="nameOf(recorded.part)" :next-name="recorded.next ? nameOf(recorded.next) : ''" :hearing="playing"
                            :nudge-ms="nudgeMsOf(recorded.part)" :lining="lining" :lined="lined" :can-resing="phraseRows.length > 1" :color="paint(recorded.part.color)" :stack="recordedStack"
                            :score="recorded.report?.score ?? null" :notes="reportNotes" :track="recorded.report?.track ?? null" :beat-seconds="fullBeatSeconds" :offset-seconds="recorded.part.offsetSeconds + (recorded.part.nudgeSeconds || 0)"
                            :phrases="reportPhrases" :phrase-labels="phraseRows.map((p) => p.label)" :position="position" :length-seconds="lengthSeconds" :bleed="!!recorded.report?.bleed" :sung-by="sungBy"
                            @hear="hearTake" @again="recordPart" @next="nextPart" @nudge="nudge" @nudged="nudged" @line-up="lineUp" @studio="openStudio" @resing="resingFrom" @trim="openTrim" :polished="prefs.hearPolished" @polish="hearPolished" @share="dialog = 'share'" @hear-phrase="hearPhrase">
                            <template #report>
                                <!-- The part's leaderboard (components/boards.js): your best, or why it didn't count; never a lesson's song -->
                                <TakeBest v-if="game() && recorded.report?.score?.percent != null && !project.course && project.template?.id" :key="'best-' + recorded.part.id" @result="ghostResult" :template-id="project.template.id" :template-version="project.template.version" :part-key="recorded.part.templatePartKey"
                                    :part-name="nameOf(recorded.part)" :song-title="shownTitle(project)" :score="recorded.report.score.percent" :key-offset="project.transpose ?? 0" :guide-muted="!guideHeard" :take-id="recorded.part.takeId ?? null" />
                                <!-- A song started from an idea's tune (docs/ideas-app-plan.md): add this take to it -->
                                <IdeaVoice v-if="project.idea && recorded.part.templatePartKey" :key="'idea-' + recorded.part.id + '-' + (recorded.part.takeId ?? '')" :idea="project.idea" :part="recorded.part" :part-name="nameOf(recorded.part)" />
                                <!-- Voice care: one quiet line after a long day's singing (docs/voice-care-plan.md) -->
                                <VoiceCareLine :key="'care-' + recorded.part.id + '-' + (recorded.part.takeId ?? '')" />
                                <WhatsNext v-if="recorded.everyPart" :current="{ id: project.template.id, difficulty: project.template.difficulty }" />
                            </template>
                        </JustRecorded>
                        <StepDone v-else-if="way === 'parts' && showDone" :step="coach.step" :part-name="partName" :color="paint(focusPart.color)" :phrases="phraseRows" :next="nextStep(coach.step)"
                            @next="setStep(nextStep(coach.step))" @again="onceMore" />
                        <PhraseStage v-else-if="phraseStage" v-bind="phraseStage" :names="pitchName" :color="paint(focusPart.color)" @first-note="firstNote" />
                        <template v-else-if="view === 'notes'">
                            <div class="flex min-h-0 flex-1">
                                <div class="flex min-h-0 flex-1 flex-col gap-2 pt-2">
                                    <NowCard class="mx-4 md:mx-6" :note="nowCard.note" :word="nowCard.word" :next="nowCard.next" :color="paint(focusPart.color)" :outlined="turn" :hint="turn ? hintState.hint : null" />
                                    <!-- The combo, and in game mode the bonus pop-ups beside it (docs/game-elements-plan.md) -->
                                    <div v-if="turn && game() && (combo >= 3 || (effect('popups') && hud.now))" class="mx-4 flex min-h-8 items-center gap-3 md:mx-6">
                                        <p v-if="combo >= 3" class="self-start rounded-full bg-raised px-3 py-1 text-sm font-semibold text-cream" data-combo>{{ t('course.combo', { count: combo }) }}</p>
                                        <PopupSlot v-if="effect('popups')" :queue="hud" />
                                    </div>
                                    <Lane class="min-h-0 flex-1" :class="crt ? 'ge-crt' : ''" :notes="notes" :words="laneWords" :others="laneOthers" :transpose="project.transpose || 0" :beat-seconds="beatSeconds" :position="position"
                                        :color="paint(focusPart.color)" :state="recording ? 'recording' : turn ? 'turn' : 'listen'" :note-name="laneName"
                                        :keyboard="prefs.keyboard" :chords="laneChords" :loop="laneLoop" :voice="turn ? voice.points : []" :lit="voice.lit" :hits="voice.hits" :trail="onFire ? 'var(--ge-fire)' : null" :ghost="ghostLine" :hint="turn ? hintState.hint : null" :data-voice="turn ? voice.points.length : 0" :data-lit="voice.lit.size" />
                                </div>
                            </div>
                        </template>
                        <Stage v-else :line="currentLine" :next="nextLine" :position="position" :color="paint(focusPart.color)" :turn="turn" :recording="recording" :now-text="now.state === 'singing' ? nowLine : ''" :sings="sings" :coming-up="comingUp" :line-of="way === 'whole' && !recording ? lineOf : ''"  :start="firstNoteShown ? firstNoteInfo : null" @hear-note="firstNote"
                            :get-ready="getReady ? { beatsLeft: getReady.beatsLeft, beatsPerBar: getReady.beatsPerBar } : null"
                            :staff="view === 'staff' || !hasWords ? staffNow : null" :current="staffCurrent" :words="hasWords" :tuning="stageTuning" :hint="turn ? hintState.hint : null">
                            <!-- Option D: a slim strip of the same lane over the big words, on your turn. -->
                            <div v-if="turn" class="h-32 w-full shrink-0"><Lane :notes="notes" :words="[]" :others="[]" :transpose="project.transpose || 0" :beat-seconds="beatSeconds" :position="position"
                                :color="paint(focusPart.color)" :state="recording ? 'recording' : 'turn'" :note-name="laneName" :keyboard="false" :voice="voice.points" :lit="voice.lit" :hits="voice.hits" :trail="onFire ? 'var(--ge-fire)' : null" :ghost="ghostLine" :hint="hintState.hint" /></div>
                        </Stage>
                    </div>
                </div>

                <div v-if="getReady && !recording" class="flex justify-center pb-2"><button type="button" class="btn-secondary btn-sm" @click="firstNote"><Icon name="note" :size="16" /> {{ t('sing.firstNoteCard.playMine') }}</button></div>
                <LineBar v-if="way === 'whole' && !recording && !recorded && phraseRows.length > 1" class="hidden bg-panel md:grid" :lines="phraseRows" :position="position" @seek="seekLine" />
                <Transport v-if="!recorded && !resung" :class="coaching && !playing ? 'hidden md:flex' : ''" :position="position" :length="lengthSeconds" :playing="playing && !recording" :recording="recording" :busy="busy" :loop="!!practice.loop" :part-name="partName"
                    :turn="way === 'parts' && phase === 'turn' && !recording" :bpm="way === 'whole' ? Math.round((project.bpm || project.template.tempo) * (practice.speed ?? 1)) : 0" :slower="canSlower" :faster="canFaster" @tempo="stepTempo" @play="transportPlay" @record="recordPart" @loop="loop" @again="repeatPhrase(here)" />
            </template>

            <SettingsSheet v-if="dialog === 'settings'" :hearing="{ rows: hearingRows, guideLevel: hearingLevel, speakers, voices: voicesLabel, split: !!practice.split }" :part-name="partName" :part-color="paint(focusPart.color)" @part="dialog = 'part'" :view="view" @view="setView" :recording="recording" @toggle="toggleHearing" @level="setGuideLevel" :speed="practice.speed ?? 1" :speed-note="speedNote" :has-chords="!!project.template.chords?.length" :chords="!!practice.chords"
                :chords-level="practice.chordsLevel ?? 0.5" :chords-sound="practice.chordsSound ?? 'gentle'" :color="paint(focusPart.color)" :bpm="project.bpm || project.template.tempo" :sample="noteSample"
                @reset="resetSettings" @speed="setSpeed" @chords="setChords" @chords-level="(v) => setPracticeValue('chordsLevel', v)" @split="(v) => setPracticeValue('split', v)" :ladder="!!practice.ladder" @ladder="setLadder" @chords-sound="setChordsSound" @voices="dialog = 'part'" @close="dialog = null" />
            <PartSheet v-if="dialog === 'part'" :parts="partSheet" :focus-id="focusPart?.id" :voice="focusPart ? guideVoices[focusPart.id] ?? null : null" :key-note="keyNote" :way="way" :disabled="recording"
                @select="selectPart" @voice="chooseVoice" @range="dialog = 'range'" @way="switchWay" @close="dialog = null" />
            <ShareSheet v-if="dialog === 'share'" :title="project.title || ''" :parts="sharedParts" @close="dialog = null" />
            <TrimSheet v-if="dialog === 'trim' && recorded" :part="recorded.part" :name="nameOf(recorded.part)" :color="paint(recorded.part.color)" :beat-seconds="60 / (project.bpm || 90)" :playing="playing"
                @trim="trim" @play-start="hearEdge('start')" @play-end="hearEdge('end')" @play-trimmed="hearTrimmed" @stop="stopHearing" @close="closeTrim" />
            <ResingSheet v-if="dialog === 'resing'" :phrases="phraseRows" :plan="resingPlanned" @pick="pickResing" @start="startResing" @close="closeResing" />
            <HeadphonesDialog v-if="dialog === 'headphones'" speaker-option @close="dialog = null" @ok="headphonesOn" @speaker="headphonesNone" />
            <TimingDialog v-if="dialog === 'timing' || dialog === 'timing-record'" :before-recording="dialog === 'timing-record'" @close="dialog = null" @done="timingDone" @skip="timingSkipped" />
            <RangeDialog v-if="dialog === 'range' && project" :template="project.template" :transpose="project.transpose || 0" @close="dialog = null" @use="useRange" />
        </div>`,
    computed: {
        /** Build up to speed applies: on your own, on a looped phrase, playing (not recording). */
        onLadder() {
            return !!this.practice?.ladder && this.way === 'parts' && this.coach.step === 'own' && this.practice.phrase !== null && this.playing && !this.recording;
        },
        /** Game mode's on-fire run (docs/game-elements-plan.md, idea 19): 10 or more in a row, your voice's trail turns pink. */
        ghostKeyNow() {
            return this.ghostWhere ? Object.values(this.ghostWhere).join(':') : null;
        },
        onFire() {
            return this.turn && this.combo >= 10 && effect('popups');
        },
        /** Where your ghost is kept: this song's version, your part and the scorer. */
        ghostWhere() {
            const template = this.project?.template;
            const key = this.focusPart?.templatePartKey;
            return template?.id && key ? { templateId: template.id, templateVersion: template.version, partKey: key, scoringVersion: SCORING_VERSION } : null;
        },
        /** Game mode's ghost (game/ghost.js): your best take's line, on your turn and while recording, where the part sits now. */
        ghostLine() {
            if (!this.ghostTake || !this.turn || !effect('ghost')) return [];
            return placeGhost(decodeGhost(this.ghostTake), this.notes, { keyOffset: this.ghostTake.keyOffset ?? 0, transpose: this.project.transpose || 0 });
        },
        /** The key moves while nothing's recorded in the song (a take would be left in the old key). */
        keyMovable: (vm) => !!vm.project?.template && !vm.parts.some(hasTake),
        keyLowerable: (vm) => (vm.project.transpose || 0) > -KEY_STEPS,
        keyRaisable: (vm) => (vm.project.transpose || 0) < KEY_STEPS,
        /** What you hear: every part but yours, on or off for this visit; the melody's says so. */
        /** Every part: yours first (your guide: off while recording unless turned on), then the others. */
        hearingRows() {
            const melody = sessionStore.melodyKey();
            const yours = this.focusPart;
            const rows = this.parts.filter((p) => p.templatePartKey && p.id !== yours?.id).map((p) => ({ id: p.id, name: this.nameOf(p), off: session.hearing.off.includes(p.id), melody: p.templatePartKey === melody }));
            if (!yours) return rows;
            // Your guide: on as the mode plays it (On your own has it off), or while recording when turned on.
            const off = this.recording ? !session.hearing.recordGuide : session.hearing.off.includes(yours.id) || this.practice.mode === 'own';
            return [{ id: yours.id, name: this.nameOf(yours), off, yours: true }, ...rows];
        },
        hearingLevel: () => session.hearing.guideLevel,
        guideHeard: () => session.guideHeard,
        hearingChanged: () => session.hearing.off.length > 0 || session.hearing.recordGuide || session.hearing.guideLevel !== GUIDE_LEVELS.medium,
        speakers: () => audio.route === 'speakers',
        way: (vm) => vm.practice?.sing?.way ?? null,
        view: (vm) => vm.practice?.sing?.view ?? 'words',
        partName: (vm) => (vm.focusPart ? vm.nameOf(vm.focusPart) : ''),
        perBar: (vm) => beatsPerBar(vm.project?.timeSignature),
        beat: (vm) => vm.position / vm.beatSeconds,
        songInfo() {
            const p = this.project;
            const section = this.sections.find((s) => this.position >= s.startSeconds && this.position < s.endSeconds);
            return [section?.name, t('studio.meta.key', { key: p.key }), t('studio.meta.bpm', { bpm: p.bpm })].filter(Boolean).join(' · ');
        },
        notes() {
            return this.focusPart ? sessionStore.templatePart(this.focusPart)?.guide.notes ?? [] : [];
        },
        /** The Stage's tuning meter: your voice now against the note (a reading from the last 0.2 s), or null. */
        stageTuning() {
            const last = this.voice.points.at(-1);
            const latency = (audio.latencyFrames ?? engine.estimatedLatencyFrames?.() ?? 0) / (engine.sampleRate || 48000);
            const note = this.notes.find((n) => this.position - latency >= n.beat * this.beatSeconds && this.position - latency < (n.beat + n.length) * this.beatSeconds);
            if (!last || !note || this.position - latency - last.seconds > 0.2) return null;
            const cents = (last.midi - (note.pitch + (this.project.transpose || 0))) * 100;
            return { cents, lit: this.voice.lit.has(note.beat) };
        },
        /** The parts with a take, for Share just my part. */
        sharedParts() {
            return this.parts.filter(hasTake).map((p) => ({ id: p.id, name: this.nameOf(p), color: paint(p.color) }));
        },
        /** Why the project can't take a new recording (a downgrade, a Plus song), or null. */
        locked() {
            return lockReason(this.project);
        },
        /** A beat at full speed: takes are recorded at full speed, whatever the practice speed. */
        fullBeatSeconds() {
            return 60 / (this.project.bpm || 90);
        },
        /** How it went's chart: the recorded part's notes in the key sung, and the phrases. */
        reportNotes() {
            const notes = this.recorded ? sessionStore.templatePart(this.recorded.part)?.guide.notes ?? [] : [];
            return notes.map((n) => ({ ...n, pitch: n.pitch + (this.project.transpose || 0) }));
        },
        reportPhrases() {
            return this.phraseList.map((p) => [p.startBeat, p.endBeat]);
        },
        now() {
            return timelineAt(this.notes, this.beat, { beatsPerBar: this.perBar });
        },
        nowLine() {
            const [key, values] = nowText(this.now, { name: (note) => this.noteName(note) });
            return t(key, { ...values, direction: values.direction });
        },
        /** The lane's words: your syllables by beat. */
        laneWords() {
            return this.focusLines.flatMap((l) => l.syllables).filter((s) => !s.borrowed).map((s) => ({ beat: s.beat, text: s.text }));
        },
        /** The parts either side of yours, faint on the lane (Settings can hide them). */
        laneOthers() {
            if (!prefs.showOthers || !this.focusPart) return [];
            const mean = (slot) => {
                const notes = sessionStore.templatePart(slot)?.guide.notes ?? [];
                return notes.reduce((sum, n) => sum + n.pitch, 0) / Math.max(1, notes.length);
            };
            const mine = mean(this.focusPart);
            const others = this.parts.filter((p) => p.templatePartKey && p.id !== this.focusPart.id).map((slot) => ({ slot, mean: mean(slot) }));
            const above = others.filter((o) => o.mean >= mine).sort((a, b) => a.mean - b.mean)[0];
            const below = others.filter((o) => o.mean < mine).sort((a, b) => b.mean - a.mean)[0];
            return [above && { ...above, above: true }, below && { ...below, above: false }].filter(Boolean)
                .map((o) => ({ name: this.nameOf(o.slot), color: (appearance.version, paint(o.slot.color)), notes: sessionStore.templatePart(o.slot).guide.notes, above: o.above }));
        },
        /** The chord names along the lane's top, while the chords play. */
        laneChords() {
            const chords = this.project.template.chords;
            if (!this.practice.chords || !chords?.length) return null;
            return chords.map((c) => ({ seconds: c.beat * this.beatSeconds, name: chordName(c, this.project.transpose || 0, this.project.key || this.project.template.key) }));
        },
        laneLoop() {
            const phrase = this.practice.loop ? this.phraseList[this.practice.phrase] : null;
            return phrase ? { startSeconds: phrase.startSeconds, endSeconds: phrase.endSeconds } : null;
        },
        /** The now card: the note now (or your next), its word, and what comes after. */
        nowCard() {
            const word = (note) => this.laneWords.find((w) => Math.abs(w.beat - note.beat) < 1e-6)?.text.replace(/-$/, '') ?? '';
            if (this.now.state === 'singing') {
                const [next, then] = this.now.next;
                const say = (n) => (word(n) ? t('sing.card.noteOn', { note: this.noteName(n), word: word(n) }) : this.noteName(n));
                const text = !next ? t('sing.card.last') : !then ? t('sing.card.next', { next: say(next) }) : t('sing.card.nextThen', { next: say(next), then: say(then) });
                return { note: this.noteName(this.now.note), word: word(this.now.note), next: text };
            }
            const coming = this.notes.find((n) => n.beat >= this.beat - 1e-6);
            return { note: coming ? this.noteName(coming) : '', word: coming ? word(coming) : '', next: this.comingUp };
        },
        /** Part by part on a phone, between phrases: the coach has the screen (the transport comes back while a phrase plays). */
        coaching() {
            return this.way === 'parts' && !this.overlay && !this.recording;
        },
        recordedSeconds() {
            return Math.max(0, this.position - this.recordFrom);
        },
        /** The recording stage: the share of your notes sung so far that were on pitch (lit), or null before the first. */
        onPitch() {
            if (!this.recording || this.countIn !== null) return null;
            const latency = (audio.latencyFrames ?? engine.estimatedLatencyFrames?.() ?? 0) / (engine.sampleRate || 48000);
            const now = this.position - latency;
            const sung = this.notes.filter((n) => n.beat * this.beatSeconds >= this.recordFrom - 1e-6 && (n.beat + n.length) * this.beatSeconds <= now);
            return sung.length ? Math.round((100 * sung.filter((n) => this.voice.lit.has(n.beat)).length) / sung.length) : null;
        },
        /** The hint in words beside your voice on the recording stage's lane. */
        hintChip() {
            if (!this.hintState.hint) return '';
            return this.hintState.hint === 'higher' ? t('sing.hint.lowChip') : t('sing.hint.highChip');
        },
        /** Your turn (on your own, part by part or recording): the band in your colour, not the header. */
        bandShown() {
            return this.turn && !this.recording;
        },
        /** "Alto sings G4 on 'grace'", while your guide sings it. */
        sings() {
            if (!this.playing || this.turn || this.now.state !== 'singing') return '';
            const [key, values] = partSaying(this.partName, { note: this.now.note, name: this.noteName(this.now.note), syllable: this.now.syllable });
            return t(key, values);
        },
        /** "Coming up, you start on D4", before your part comes in. */
        comingUp() {
            if (this.now.state === 'singing' || this.recording) return '';
            const next = this.notes.find((n) => n.beat >= this.beat - 1e-6);
            return next ? t('sing.comingUpOn', { note: this.noteName(next) }) : '';
        },
        /** Your next note and its word, for the first-note card while nothing plays. */
        firstNoteInfo() {
            const next = this.notes.find((n) => n.beat >= this.beat - 1e-6) ?? this.notes[0];
            if (!next) return null;
            const syllable = this.focusLines.flatMap((l) => l.syllables).find((s) => Math.abs(s.beat - next.beat) < 1e-6);
            return { note: this.noteName(next) || midiName(next.pitch + (this.project.transpose || 0)), word: syllable?.text ?? '' };
        },
        firstNoteShown() {
            return !this.playing && !this.recording && !this.recorded && !this.resung && !this.overlay && this.way === 'whole' && this.view !== 'notes' && !!this.firstNoteInfo;
        },
        /** Your part's range (in this key) and who sings its guide, beside the modes. */
        about() {
            const range = this.focusPart && sessionStore.templatePart(this.focusPart)?.range;
            const span = range ? t('sing.about.rangeInline', { low: this.noteName({ pitch: range.low }), high: this.noteName({ pitch: range.high }) }) : '';
            const voice = this.focusPart && this.guideVoices[this.focusPart.id];
            const who = voice?.kind === 'real' && voice.credit ? t('part.sungBy', { name: voice.credit }) : '';
            return [span, who].filter(Boolean).join(' · ');
        },
        settingsBadge() {
            const speed = this.practice.speed ?? 1;
            return speed !== 1 ? t('sing.speedPercent', { percent: Math.round(speed * 100) }) : '';
        },
        speedNote() {
            return Object.values(this.guideVoices).some((v) => v.why === 'speed') ? t('studio.realVoiceSpeed', { percent: Math.round((this.practice.speed ?? 1) * 100) }) : '';
        },
        /** Settings' Voices row: who sings your part's guide. */
        /** Settings' Guide voices, or '' (hidden) when no part has a real voice to choose (Hillel). */
        voicesLabel() {
            if (!Object.values(this.guideVoices).some((v) => v?.versions?.length)) return '';
            const voice = this.focusPart && this.guideVoices[this.focusPart.id];
            const yours = voice?.kind === 'real' && voice.credit ? t('part.sungBy', { name: voice.credit }) : t('part.practiceVoice');
            return t('sing.settings.voicesOf', { part: this.partName, voice: yours });
        },
        /** Your part's sheet: each part with its range and its guide's voice. */
        partSheet() {
            return this.partChoices.map((c) => {
                const voice = this.guideVoices[c.slot.id];
                return {
                    slot: c.slot,
                    name: c.name,
                    range: c.range ? t('sing.about.range', { low: this.noteName({ pitch: c.range.low }), high: this.noteName({ pitch: c.range.high }) }) : '',
                    voiceLabel: c.recorded ? t('sing.status.recorded') : voice?.kind === 'real' && voice.credit ? t('part.sungBy', { name: voice.credit }) : t('part.practiceVoice'),
                };
            });
        },
        /** The kind note about the real voice and your key, inside Your part. */
        keyNote() {
            return voiceNote((range) => this.useRange(range));
        },

        getReady() {
            if (!this.playing || this.now.state !== 'getReady') return null;
            const line = this.focusLines.find((l) => l.startBeat >= this.now.entry.beat - 1e-6);
            return { beatsLeft: this.now.beatsLeft, beatsPerBar: this.perBar, note: this.noteName(this.now.entry), line: line ? joinSyllables(line.syllables) : '' };
        },
        /** Your turn: the stage takes your colour while you sing alone or record. */
        turn() {
            return this.recording || this.phase === 'turn' || (this.playing && this.way === 'whole' && this.practice.mode === 'own');
        },
        chip() {
            if (this.recording && this.countIn !== null) return this.countIn ? countInText(this.countIn) : '';
            if (this.recording) return t('sing.phase.recording', { time: formatTime(Math.max(0, this.position - this.recordFrom)) });
            if (!this.playing) return t(PHASE.paused);
            if (this.getReady) return t(PHASE.getReady);
            return t(this.turn ? PHASE.turn : PHASE.listening);
        },
        hasWords() {
            return this.focusLines.length > 0;
        },
        currentLine() {
            const lines = this.focusLines;
            return lines.find((l) => this.position >= l.startSeconds - 0.3 && this.position < l.endSeconds) ?? lines.find((l) => l.startSeconds > this.position) ?? null;
        },
        nextLine() {
            const i = this.focusLines.indexOf(this.currentLine);
            return i >= 0 ? this.focusLines[i + 1] ?? null : null;
        },
        /** The staff pages by phrase (a part with no words has no lines). */
        /** The phrase on the staff: the one being sung, or, while your part rests, your next notes. */
        staffPhrase() {
            const at = this.beat;
            const notes = this.focusPart ? sessionStore.templatePart(this.focusPart)?.guide.notes ?? [] : [];
            const yours = this.phraseList.filter((p) => notes.some((n) => n.beat >= p.startBeat - 1e-6 && n.beat < p.endBeat));
            const list = yours.length ? yours : this.phraseList;
            return list.find((p) => at >= p.startBeat - 0.5 && at < p.endBeat) ?? list.find((p) => p.startBeat > at) ?? list[0] ?? null;
        },
        staffNotes() {
            const p = this.staffPhrase;
            return p ? this.notes.filter((n) => n.beat >= p.startBeat - 1e-6 && n.beat < p.endBeat) : [];
        },
        staffNow() {
            if (!this.focusPart || !this.staffNotes.length) return null;
            return staffLayout(this.staffNotes, { key: this.project.key || this.project.template.key, transpose: this.project.transpose || 0, clef: clefFor(this.focusPart.templatePartKey), beatsPerBar: this.perBar, pickupBeats: this.pickup });
        },
        staffCurrent() {
            return this.now.state === 'singing' ? this.staffNotes.indexOf(this.now.note) : -1;
        },
        lengthSeconds() {
            return Math.max(this.contentLength, ...this.phraseList.map((p) => p.endSeconds));
        },
        coach() {
            return progressOf(this.practice, this.focusPart?.templatePartKey);
        },
        /** The phrase you're on: the one chosen, else the first not yet practised in this step. */
        here() {
            const chosen = this.practice.phrase;
            if (this.playing && chosen !== null && chosen !== undefined) return chosen;
            if (this.readied !== null) return this.readied; // N readied it, part by part: Play plays it
            return currentPhrase(this.coach, this.phraseList.length);
        },
        /** Part by part on a phone: these take over the screen from the coach. */
        overlay() {
            return !!this.recorded || !!this.resung || (this.way === 'parts' && (this.phase === 'turn' || this.showDone));
        },
        showDone() {
            return this.coach.step !== 'record' && stepDone(this.coach, this.phraseList.length) && this.celebrated !== `${this.focusPart.id}:${this.coach.step}` && !this.playing;
        },
        otherNames() {
            return this.parts.filter((p) => p.templatePartKey && p.id !== this.focusPart?.id).map((p) => this.nameOf(p)).join(', ');
        },
        partChoices() {
            return this.parts.filter((p) => p.templatePartKey).map((slot) => ({ slot, name: this.nameOf(slot), range: sessionStore.templatePart(slot)?.range ?? null, recorded: hasTake(slot) }));
        },
        /** Just recorded's stack: each part's waveform (its take, or its guide), yours marked. */
        /**
         * Your own harmony (onboarding step 10): once two or more of the song's parts are your takes,
         * "Are You Sleeping, sung by Maya and Maya" (the name you put on a card, an adult's account
         * name), else "sung by you and you".
         */
        sungBy() {
            const yours = this.parts.filter((p) => p.templatePartKey && hasTake(p) && sessionStore.mine(p)).length;
            if (yours < 2) return '';
            const name = (account.me?.isAdult ? account.me.name : null) ?? local.get('stackapella.shareName') ?? null;
            const names = formatList(Array.from({ length: yours }, () => name || t('sing.recorded.you')));
            return t('sing.recorded.sungBy', { song: shownTitle(this.project), names });
        },
        recordedStack() {
            return this.parts.filter((p) => p.templatePartKey).map((p) => ({ id: p.id, name: this.nameOf(p), color: (appearance.version, paint(p.color)), peaks: hasTake(p) ? p.peaks : p.guidePeaks, you: p.id === this.recorded?.part.id, done: hasTake(p), muted: session.hearing.off.includes(p.id) }));
        },
        /** The re-sung phrase's words, on its card. */
        resungLine() {
            return this.resung ? this.phraseRows[this.resung.outcome.plan.phraseIndex]?.label ?? '' : '';
        },
        /** Part by part, between plays (66.png): the phrase you're on, its notes and the others close to them. */
        phraseStage() {
            if (this.way !== 'parts' || this.viewPicked || this.playing || this.turn || this.coach.step === 'record' || !this.focusPart) return null;
            const p = this.phraseRows[this.here];
            if (!p) return null;
            const transpose = this.project.transpose || 0;
            const within = (n) => n.beat >= p.startBeat - 1e-6 && n.beat < p.endBeat;
            const notes = this.notes.filter(within).map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch + transpose, name: this.noteName(n) || this.syllableAt(n.beat) }));
            if (!notes.length) return null;
            const low = Math.min(...notes.map((n) => n.pitch)) - 1;
            const high = Math.max(...notes.map((n) => n.pitch)) + 1;
            const others = this.parts.filter((slot) => slot.templatePartKey && slot.id !== this.focusPart.id)
                .flatMap((slot) => (sessionStore.templatePart(slot)?.guide.notes ?? []).filter(within).map((n) => ({ beat: n.beat, length: n.length, pitch: n.pitch + transpose })))
                .filter((n) => n.pitch >= low && n.pitch <= high);
            return { number: p.index + 1, start: p.start, notes, others, from: p.startBeat, to: p.endBeat };
        },
        /** Your part's first three notes, named as chosen (Session settings' sample). */
        /** "Line 1 of 4": the line the playhead is in (or the next). */
        lineOf() {
            const rows = this.phraseRows;
            if (rows.length < 2) return '';
            const at = rows.findIndex((p) => this.position < p.endSeconds);
            return t('sing.lineOf', { line: (at < 0 ? rows.length - 1 : at) + 1, total: rows.length });
        },
        /**
         * Who you hear while recording, worked out from the mix the engine plays (so the words can't
         * drift from the sound): your guide if it's on, each other part that plays, the chords.
         */
        hearLine() {
            if (!this.focusPart || !this.recording) return '';
            void session.hearing.off.length; // the mix reads it; so does this line
            const all = sessionStore.levels();
            const items = [];
            const mine = all.get(guideId(this.focusPart.id));
            if (mine?.audible) items.push(t('sing.hear.yourGuide'));
            const others = this.parts.filter((p) => p.templatePartKey && p.id !== this.focusPart.id);
            const heard = others.filter((p) => [p.id, guideId(p.id)].some((id) => all.get(id)?.audible));
            if (heard.length > 1 && heard.length === others.length) items.push(t('sing.hear.everyOther'));
            else items.push(...heard.map((p) => this.nameOf(p)));
            if (all.get(BACKING)?.audible) items.push(t('sing.hear.chords'));
            if (!items.length) return t('sing.hear.recordingNone');
            const line = t(this.speakers ? HEAR_RECORDING.softer : HEAR_RECORDING.headphones, { list: formatList(items) });
            return mine?.audible ? line : `${line} ${t('sing.hear.guideOffRecording')}`;
        },
        canSlower: (vm) => vm.speedIndex !== 0,
        canFaster: (vm) => vm.speedIndex !== TEMPO_STEPS.length - 1,
        speedIndex() {
            const i = TEMPO_STEPS.indexOf(this.practice.speed ?? 1);
            return i < 0 ? TEMPO_STEPS.length - 1 : i;
        },
        noteSample() {
            return this.notes.slice(0, 3).map((n) => this.noteName(n)).join(' ');
        },
        phraseRows() {
            return this.phraseList.map((p, index) => {
                const words = joinSyllables(this.focusLines.flatMap((l) => l.syllables).filter((s) => s.beat >= p.startBeat - 1e-6 && s.beat < p.endBeat));
                const first = this.notes.find((n) => n.beat >= p.startBeat - 1e-6 && n.beat < p.endBeat);
                return { ...p, index, label: words || t('studio.phraseNumber', { number: index + 1 }), start: first ? this.noteName(first) || this.syllableAt(first.beat) : '' };
            });
        },
    },
    watch: {
        // Game mode's ghost: your best take of this part, loaded as the part is chosen (or the song's version changes).
        ghostKeyNow: {
            async handler(now) {
                this.ghostTake = null;
                this.pendingGhost = null;
                if (!now || !game()) return;
                const ghost = await loadGhost(this.ghostWhere);
                if (this.ghostKeyNow === now) this.ghostTake = ghost;
            },
            immediate: true,
        },
        // Opened again with another device's change: Just recorded holds the part as it now is.
        reloads() {
            if (!this.recorded) return;
            const part = session.parts.find((p) => p.id === this.recorded.part.id);
            if (part) this.recorded = { ...this.recorded, part };
            else this.recorded = null;
        },
        /** Just recorded's mixer mutes for its review only: leaving it, every part plays again. */
        recorded(now) {
            sessionStore.setInReview(!!now);
            if (!now) sessionStore.clearReview();
        },
        /** A phrase played through to its end counts once (each round of a loop too). */
        position(now, before) {
            this.hearVoice(now);
            // The ladder: once the turn's last note has passed, the round is judged (once).
            if (this.onLadder && this.phase === 'turn' && !this.rungJudged) {
                const phrase = this.phraseList[this.practice.phrase];
                const last = Math.max(...this.notes.filter((n) => n.beat >= phrase.startBeat - 1e-6 && n.beat < phrase.endBeat - 1e-6).map((n) => n.beat + n.length));
                if (Number.isFinite(last) && now >= last * this.beatSeconds) {
                    this.rungJudged = true;
                    this.judgeRung();
                }
            }
            // Re-singing stops by itself a moment after the join.
            if (this.recording && session.patching && now >= session.patching.plan.to + 0.3 && !this.busy) {
                this.finishRecording();
                return;
            }
            // Hillel: the recording went on after the song ended. It stops by itself a moment after
            // the song's end and goes to the review, as Stop and keep does (the engine ends playback
            // at the end, never a recording, which has no end of its own).
            if (this.recording && !session.patching && now >= this.lengthSeconds + 0.5 && !this.busy) {
                this.finishRecording();
                return;
            }
            if (this.way !== 'parts' || !this.playing || this.recording || this.coach.step === 'own') return;
            const index = this.practice.phrase;
            const phrase = this.phraseList[index ?? -1];
            // A jump (another phrase chosen, or a seek) isn't a round heard.
            if (!phrase || index !== this.countingPhrase) {
                this.countingPhrase = index;
                this.counted = false;
                return;
            }
            const end = phrase.endSeconds - 0.08;
            if (now >= end && !this.counted) {
                this.counted = true; // once per round: the loop going round arms the next
                this.count(index);
            } else if (now < before - 0.5) {
                this.counted = false;
            }
        },
        /** On your own: a turn sung through to its end counts (Guide back on, Again or Pause don't). */
        phase(now, before) {
            if (this.way === 'parts' && before === 'turn' && now === 'listen' && this.coach.step === 'own' && this.practice.phrase !== null) this.count(this.practice.phrase);
            // The ladder judges each turn on its own: the phrase's notes start it unlit.
            if (now === 'turn' && this.onLadder) {
                const phrase = this.phraseList[this.practice.phrase];
                for (const n of this.notes) if (n.beat >= phrase.startBeat - 1e-6 && n.beat < phrase.endBeat - 1e-6) this.voice.lit.delete(n.beat);
                this.rungJudged = false;
            }
        },
        turn(now) {
            if (now && this.playing) this.say(t('sing.say.turn'));
        },
        /** Each recording of a part in this visit is the next take ("Take 2"); a part with a take starts at 2. */
        recording(now) {
            const part = now && this.parts.find((p) => p.id === this.rerecordId);
            if (part) this.takeNumbers = { ...this.takeNumbers, [part.id]: (this.takeNumbers[part.id] ?? (hasTake(part) ? 1 : 0)) + 1 };
        },
        /**
         * Said to a screen reader: the count-in starting (when there is one), then the recording,
         * never each beat. countIn is 0 through the engine's lead even without a count-in, and null
         * once the recording itself has begun.
         */
        countIn(now, before) {
            if (!this.recording) return;
            if (now !== null && before === null && this.countInBeats > 0) this.say(t('sing.say.countIn'));
            else if (now === null && before !== null) this.say(t('sing.say.recording'));
        },
        getReady(now, before) {
            if (now && !before) this.say(t('sing.say.getReady', { note: now.note }));
        },
        playing(now) {
            this.keepAwake(now);
            if (now) {
                this.voice = voiceFollower(); // a new run: a fresh line, nothing lit
                this.combo = 0;
                this.comboCounted = new Set();
                this.hud.clear(this.position);
            } else this.hud.clear();
        },
        /** Your turn on the lane: the mic opens (if it isn't already) so your voice can be drawn. */
        bandShown(now) {
            if (now && this.view === 'notes' && !engine.mic) openMic().catch((error) => console.warn('No mic for the live line', error));
        },
    },
    async created() {
        // The studio may have it open already (Sing from the studio): no need to load it again.
        if (session.openedId !== this.id || !session.project) await sessionStore.openSession(this.id);
        // A beta take is recorded in the studio's beta mode (the sound check, Check and send).
        if (session.project?.beta) {
            navigate(`/p/${this.id}`, { replace: true });
            return;
        }
        sessionStore.setScreen('sing');
        if (this.isTemplate) sessionStore.refreshVoices();
        this.pickFitting().catch((error) => console.warn('The part that fits was not picked', error));
    },
    mounted() {
        // Game mode's CRT boot, once a session (game/crt.js): the class only for the boot's moment.
        if (crtOnce()) {
            this.crt = true;
            run(600, () => {}).done.then(() => (this.crt = false));
        }
        this.onKey = (event) => this.key(event);
        document.addEventListener('keydown', this.onKey);
        this.onInterrupt = () => this.interrupted();
        engine.onInterrupt = this.onInterrupt;
    },
    beforeUnmount() {
        // A re-sung phrase on trial wasn't kept: the take goes back as it was.
        if (this.resung) sessionStore.tryTake(this.resung.outcome.part, this.resung.before);
        sessionStore.cancelResing();
        sessionStore.setInReview(false);
        document.removeEventListener('keydown', this.onKey);
        this.keepAwake(false);
        this.leaving = true;
        if (engine.onInterrupt === this.onInterrupt) engine.onInterrupt = null;
        // Back to the studio keeps the session open; anywhere else closes it.
        if (session.openedId === this.id && !(location.pathname === `/p/${this.id}`)) {
            sessionStore.closeSession();
            closeMic();
        } else if (this.playing && !this.recording) {
            sessionStore.stopPlayback();
        }
    },
    methods: {
        navigate,
        joinSyllables,
        templatePart: (slot) => sessionStore.templatePart(slot),
        setMode: (mode) => sessionStore.setMode(mode),
        nameOf(slot) {
            return slotName(slot, this.project?.template);
        },
        /** A note's name, your way (Settings: letters, solfège, numbers or none), in this song's key. */
        /** The tempo's - and +: a step slower or faster (70%, 85%, full). */
        stepTempo(direction) {
            const next = TEMPO_STEPS[Math.max(0, Math.min(TEMPO_STEPS.length - 1, this.speedIndex + direction))];
            if (next !== (this.practice.speed ?? 1)) this.setSpeed(next);
        },
        /** The line bar: to the start of a line. */
        seekLine(seconds) {
            sessionStore.seek(seconds);
        },
        /** A pitch (in the key sung) named as notes are named here. */
        pitchName(pitch) {
            return noteLabel(pitch, prefs.noteNames, this.project.key || this.project.template.key);
        },
        /** The transport's Play: part by part, the step's action on the phrase you're on (66.png). */
        transportPlay() {
            if (this.way === 'parts' && !this.playing && this.coach.step !== 'record' && !this.recorded) this.coachAction();
            else this.play();
        },
        noteName(note) {
            return noteLabel(note.pitch + (this.project.transpose || 0), prefs.noteNames, this.project.key || this.project.template.key);
        },
        /**
         * Your voice, live, on your turn and while recording (the Notes view): a reading about 30
         * times a second from the mic, at song time less the measured latency, judged as the score
         * judges it (js/livevoice.js). Nothing is scheduled here: it only reads.
         */
        hearVoice(now) {
            if (!this.turn || !engine.mic) return;
            const at = performance.now();
            if (at - (this.heardAt ?? 0) < 20) return; // about 50 readings a second, on the frame loop
            this.heardAt = at;
            const frame = engine.inputFrame();
            if (!frame) return;
            const latency = (audio.latencyFrames ?? engine.estimatedLatencyFrames()) / engine.sampleRate;
            const seconds = now - latency;
            const note = this.notes.find((n) => seconds >= n.beat * this.beatSeconds && seconds < (n.beat + n.length) * this.beatSeconds);
            // Clarity hysteresis: a voice already showing stays through a borderline reading (it was
            // flashing); a new one needs a clear one. The light and the score take only clear ones.
            const reading = trackFrame(frame, engine.sampleRate, { minClarity: 0 });
            const clear = reading.hz > 0 && reading.clarity >= VOICED_CLARITY;
            this.voiced = clear || (this.voiced && reading.hz > 0 && reading.clarity >= VOICED_CLARITY - CLARITY_HOLD);
            const before = this.voice.offset ?? null;
            this.voice = follow(this.voice, { seconds, hz: clear ? reading.hz : 0, shownHz: this.voiced ? reading.hz : 0, loudness: reading.loudness }, note ? { beat: note.beat, pitch: note.pitch + (this.project.transpose || 0) } : null);
            // The words' hint, from the line's centre over the last 0.3 s (never on a vibrato).
            const recent = this.voice.points.filter((p) => p.seconds > seconds - 0.3 && p.alpha === 1);
            const centre = recent.length ? recent.reduce((sum, p) => sum + p.midi, 0) / recent.length : null;
            const cents = note && centre !== null && seconds - recent.at(-1).seconds < 0.1 ? (centre - (note.pitch + (this.project.transpose || 0))) * 100 : null;
            this.hintState = hintStep(this.hintState, { cents, dt: 0.02 });
            // Singing in another octave is fine: said once, kindly.
            if (this.voice.offset && this.voice.offset !== before && !this.octaveSaid) {
                this.octaveSaid = true;
                this.say(this.voice.offset < 0 ? t('sing.octaveLower') : t('sing.octaveHigher'));
            }
            // The combo: notes in a row lit, starting again quietly when one goes by unlit.
            this.comboCounted ??= new Set();
            for (const n of this.notes) {
                if (this.comboCounted.has(n.beat) || seconds < (n.beat + n.length) * this.beatSeconds || seconds > (n.beat + n.length) * this.beatSeconds + 1) continue;
                this.comboCounted.add(n.beat);
                this.combo = this.voice.lit.has(n.beat) ? this.combo + 1 : 0;
                if (!effect('popups')) continue;
                const index = this.phraseOf(n)?.index ?? null;
                const milestone = comboMilestone(this.combo);
                if (milestone) this.hud.offer({ kind: 'combo', count: this.combo, phrase: index }, seconds);
                const phrase = this.phraseCleared(n);
                if (phrase !== null) this.hud.offer({ kind: 'phrase', phrase }, seconds);
                // A long note held in tune to its end, when nothing bigger was just said for it.
                const bs = this.beatSeconds;
                if (!milestone && phrase === null && this.voice.lit.has(n.beat) && heldToEnd(this.voice.points, { start: n.beat * bs, end: (n.beat + n.length) * bs, pitch: n.pitch + (this.project.transpose || 0) })) {
                    this.hud.offer({ kind: 'hold', phrase: index }, seconds);
                }
            }
            this.hud.tick(seconds);
        },
        /** Game mode's "Phrase clear": `n` was its phrase's last note and every note of the phrase lit; its index, else null. */
        phraseCleared(n) {
            const p = this.phraseOf(n);
            if (!p) return null;
            const notes = this.notes.filter((m) => m.beat >= p.startBeat - 1e-6 && m.beat < p.endBeat);
            if (notes.at(-1)?.beat !== n.beat) return null;
            return notes.length > 1 && notes.every((m) => this.voice.lit.has(m.beat)) ? p.index : null;
        },
        /**
         * A scored take as your ghost (game/ghost.js): a guest's is kept on the device when it beats the
         * one kept; signed in, it waits for the board to say it's your new best (ghostResult).
         */
        offerGhost(track, percent, startSeconds) {
            const where = this.ghostWhere;
            if (!where || !track?.hz || !game()) return;
            const ghost = { ...encodeGhost(track, { startSeconds }), score: Math.round(percent), keyOffset: this.project.transpose || 0 };
            if (account.me) {
                this.pendingGhost = { where, ghost };
                return;
            }
            if (keepDeviceGhost(where, ghost)) this.ghostTake = ghost;
        },
        /** The board's answer to a take: a new best keeps its line as your ghost, with your account. */
        async ghostResult(result) {
            const pending = this.pendingGhost;
            this.pendingGhost = null;
            if (!pending || !result?.counted || !result.improved) return;
            const ghost = { ...pending.ghost, score: result.best };
            try {
                await saveGhost(pending.where, ghost);
                if (this.ghostKeyNow === Object.values(pending.where).join(':')) this.ghostTake = ghost;
            } catch (error) {
                console.warn('The ghost was not kept', error);
            }
        },
        /** The phrase a note is in (its row), or null. */
        phraseOf(n) {
            return this.phraseRows.find((row) => n.beat >= row.startBeat - 1e-6 && n.beat < row.endBeat) ?? null;
        },
        /** The lane names sounding pitches (transposed already), your way. */
        laneName(midi) {
            return this.noteName({ pitch: midi - (this.project.transpose || 0) });
        },
        async chooseVoice(voice) {
            if (this.focusPart) await sessionStore.setVoice(this.focusPart, voice);
        },
        /**
         * Back to where Sing was opened from: the home, the studio or a lesson. Opened cold (a link,
         * a reload), a lesson's song goes to its lesson and any other to the home.
         */
        leave() {
            if (this.recording) return;
            goBack(this.project?.course ? `/learn/${this.project.course.lessonId}` : '/');
        },
        /** The studio, to mix, edit and export: one tap from Sing, never where leaving Sing lands by itself. */
        openStudio() {
            if (this.recording) return;
            navigate(`/p/${this.id}`);
        },
        sing(fields) {
            this.practice.sing = { ...(this.practice.sing ?? {}), ...fields };
            sessionStore.savePractice();
        },
        /** A part picked while your range isn't known: its range (in the key sung) is kept as yours, so nothing asks again. */
        keepPicked(slot) {
            const range = sessionStore.templatePart(slot)?.range;
            const transpose = this.project.transpose || 0;
            if (range) pickRange(range.low + transpose, range.high + transpose).catch((error) => console.error('The part picked was not kept as your range', error));
        },
        choose({ way, slotId }) {
            const slot = this.parts.find((p) => p.id === slotId);
            this.sing({ way });
            sessionStore.practise(slot);
            this.fitKey(slot).then(() => this.keepPicked(slot)).catch((error) => console.error('The key that fits was not found', error));
            if (way === 'whole' && this.practice.mode === 'own' && !hasTake(slot)) sessionStore.setMode('singAlong');
            this.choosing = false;
            this.recorded = null;
        },
        /**
         * Hillel: once your range is known, a song opens on the part that fits it (with the key that
         * fits, when it may move), said once; the header's chip changes it. The part picker only asks
         * when the range isn't known, or no part fits even with the key moved (said why). A new singer
         * gets the melody first, in their own octave (dsp.js suggestPart; "Melody first").
         */
        async pickFitting() {
            if (!this.isTemplate || this.focusPart || this.practice.sing?.fitSaid) return;
            await loadLearning();
            const range = learning.progress.range;
            if (!range || range.high - range.low < FIT_SPAN || this.focusPart) return;
            const choices = this.partChoices.filter((c) => c.range);
            const melodyFirst = await newSinger({ projectId: this.project.id, templateId: this.project.template?.id, me: account.me?.id ?? null }).catch(() => false);
            if (this.focusPart) return; // chosen while it was worked out
            const parts = choices.map((c) => {
                const part = sessionStore.templatePart(c.slot);
                return { key: c.slot.id, range: c.range, melody: !!part?.melody, notes: part?.guide?.notes };
            });
            const best = suggestPart(range, parts, { melodyFirst });
            this.sing({ fitSaid: true });
            if (!best) return;
            // Never asked again once the range is known: the closest part when none fits (said so).
            const slot = choices.find((c) => c.slot.id === best.part.key).slot;
            sessionStore.practise(slot);
            // Melody first, counted: how often the part suggested is the melody, and (selectPart) changed.
            count(best.part.melody ? 'sug_melody' : 'sug_part');
            this.fitSuggestion = { id: slot.id, switched: false };
            const words = !best.fits ? FIT_WORDS.closest : best.octave < 0 ? FIT_WORDS.lower : best.octave > 0 ? FIT_WORDS.higher : FIT_WORDS.fits;
            toast(t(words, { part: this.nameOf(slot) }));
            await this.fitKey(slot);
        },
        /** Lower or Higher (128.png): the whole song a semitone, guides, chords and scoring with it. */
        moveKey(direction) {
            const transpose = Math.max(-KEY_STEPS, Math.min(KEY_STEPS, (this.project.transpose || 0) + direction));
            this.sing({ keyChosen: true });
            if (transpose === (this.project.transpose || 0)) return;
            this.project.transpose = transpose;
            sessionStore.changeKey();
        },
        /**
         * Learning a part starts in the key that fits your range (the course's, or a range check's),
         * once per song and only before anything's recorded in it; Lower and Higher move it from there.
         */
        async fitKey(slot) {
            if (this.practice.sing?.keyChosen || !this.keyMovable) return;
            this.sing({ keyChosen: true });
            await loadLearning();
            const range = learning.progress.range;
            const part = sessionStore.templatePart(slot)?.range;
            if (!range || !part || range.high - range.low < FIT_SPAN) return;
            // In the octave the part fits (an octave down, say), else the closest key.
            const { transpose } = octaveFit(range, part) ?? suggestTranspose(range, part);
            if (transpose === (this.project.transpose || 0)) return;
            this.project.transpose = transpose;
            sessionStore.changeKey();
        },
        useRange({ transpose, partId }) {
            this.sing({ keyChosen: true });
            this.dialog = null;
            if ((this.project.transpose || 0) !== transpose) {
                this.project.transpose = transpose;
                sessionStore.changeKey();
            }
            this.suggested = this.parts.find((p) => p.templatePartKey === partId)?.id ?? null;
        },
        /** The part sheet's Phrase by phrase or Whole song; a way kept for every time (Settings) follows it. */
        switchWay(way) {
            this.dialog = null;
            this.sing({ way });
            if (prefs.singWay) prefs.singWay = way;
        },
        setView(view) {
            // Chosen here: the stage shows it at once, playing or not (Hillel), in place of the phrase picture.
            this.viewPicked = true;
            this.sing({ view });
        },
        selectPart(slotId) {
            if (this.recording) return;
            const slot = this.parts.find((p) => p.id === slotId);
            if (this.fitSuggestion && !this.fitSuggestion.switched && slotId !== this.fitSuggestion.id) {
                this.fitSuggestion.switched = true;
                count('sug_switch');
            }
            sessionStore.practise(slot);
            // This song's part only: a known range stays as it is.
            if (slot) this.keepPicked(slot);
            this.recorded = null;
        },
        async play() {
            this.recorded = null;
            await sessionStore.togglePlay();
        },
        async loop() {
            // Your choice from now on, for this song: a phrase played never turns it back on (Hillel).
            this.practice.loopChosen = true;
            if (this.practice.phrase === null || this.practice.phrase === undefined) {
                const index = this.phraseList.findIndex((p) => this.position >= p.startSeconds && this.position < p.endSeconds);
                sessionStore.selectPhrase(Math.max(0, index));
            }
            await sessionStore.toggleLoop();
        },
        /** Session settings' Reset: Words, full speed, no chords, and the singing settings as they start. */
        async resetSettings() {
            this.setView('words');
            if ((this.practice.speed ?? 1) !== 1) await this.setSpeed(1);
            if (this.practice.chords) await this.setChords(false);
            Object.assign(prefs, { noteNames: 'letters', keyboard: true, showOthers: true, shortcuts: true });
            sessionStore.resetHearing();
        },
        /** N: the next phrase, ready (it plays with Play). */
        nextPhrase() {
            if (!this.phraseList.length) return;
            // The last phrase that has started (in a rest, the one just sung), then the one after it.
            const now = this.way === 'parts' && !this.playing ? this.here : this.phraseList.findLastIndex((p) => this.position >= p.startSeconds - 1e-6);
            const next = Math.min(this.phraseList.length - 1, now + 1);
            if (this.way === 'parts' && !this.playing) this.readied = next;
            sessionStore.selectPhrase(next);
        },
        /** Your syllable at a beat ("maz"), for naming a note when note names are off. */
        syllableAt(beat) {
            const s = this.focusLines.flatMap((l) => l.syllables).find((x) => !x.borrowed && Math.abs(x.beat - beat) < 1e-6);
            return s ? s.text.replace(/-$/, '') : '';
        },
        /** M: your guide off (on your own) or back on (sing along). */
        muteGuide() {
            sessionStore.setMode(this.practice.mode === 'own' ? 'singAlong' : 'own');
        },
        async setChords(on) {
            if (on) await sessionStore.ready(); // the chords render with the guides
            sessionStore.setPractice('chords', on);
        },
        /** What you hear's toggles. Yours is your guide: on in On your own means Sing along (as Guide back on was). */
        async toggleHearing(id) {
            if (id !== this.focusPart?.id) return sessionStore.toggleHearing(id);
            if (this.recording) return sessionStore.setRecordGuide(!session.hearing.recordGuide);
            if (this.practice.mode !== 'own') return sessionStore.toggleHearing(id);
            if (session.hearing.off.includes(id)) sessionStore.toggleHearing(id);
            // The coach's turn mutes your guide in the engine: the phrase starts again with it.
            const replay = this.way === 'parts' && this.playing;
            if (replay) await sessionStore.stopPlayback();
            sessionStore.setMode('singAlong');
            if (replay) await this.playPhrase(this.here);
        },
        setGuideLevel(level) {
            sessionStore.setGuideLevel(level);
        },
        setPracticeValue(field, value) {
            sessionStore.setPractice(field, value);
        },
        async setChordsSound(sound) {
            await sessionStore.setChordsSound(sound);
        },
        async setSpeed(speed) {
            if (this.practice.ladder) sessionStore.setPractice('ladder', false); // a speed chosen by hand ends the ladder
            await sessionStore.setSpeed(speed);
        },
        /**
         * Build up to speed (the tempo ladder): it starts at 70%, and on your own, on a looped phrase,
         * each round you sing cleanly (80% of its notes lit) goes up 5% at the round's end, to full
         * speed; a round that isn't clean stays where it is, never lower. The next speed renders ahead.
         */
        async setLadder(on) {
            sessionStore.setPractice('ladder', on);
            if (!on) return;
            await sessionStore.setSpeed(sessionStore.LADDER.start);
            this.readyNextRung();
        },
        readyNextRung() {
            const next = sessionStore.nextRung(this.practice.speed ?? 1);
            if (next) sessionStore.renderAtSpeed(next);
        },
        /** Your turn on the ladder is over (its last note passed): clean, and the next speed starts at the turn's end. */
        judgeRung() {
            const phrase = this.phraseList[this.practice.phrase];
            const notes = this.notes.filter((n) => n.beat >= phrase.startBeat - 1e-6 && n.beat < phrase.endBeat - 1e-6);
            if (!notes.length) return;
            const lit = notes.filter((n) => this.voice.lit.has(n.beat)).length / notes.length;
            if (lit < LADDER_CLEAN || !sessionStore.nextRung(this.practice.speed ?? 1)) return;
            const end = engine.passEnd();
            if (end === null || !sessionStore.climb(end)) return; // not ready, or too late: the next round tries again
            const percent = Math.round((this.practice.speed ?? 1) * 100);
            const words = percent >= 100 ? t('sing.ladder.upToSpeed') : t('sing.ladder.up', { percent });
            toast(words);
            this.say(words);
            this.readyNextRung();
        },
        firstNote() {
            sessionStore.startingNote();
        },
        async playPhrase(index) {
            this.readied = null;
            if (this.way === 'parts') {
                const step = this.coach.step;
                sessionStore.setMode(step === 'singAlong' ? 'singAlong' : 'listen');
            }
            sessionStore.selectPhrase(index);
            // A phrase loops until you turn Loop off; after that it plays once, and Loop stays off.
            const phrase = this.phraseList[index];
            if (this.practice.loopChosen && !this.practice.loop) await sessionStore.playFrom(phrase.startSeconds, { to: phrase.endSeconds });
            else if (!this.practice.loop) await sessionStore.toggleLoop();
            else if (!this.playing) await sessionStore.togglePlay();
        },
        async repeatPhrase(index) {
            // On your own: back to your own mode first (Hear it or Guide back on may have left another).
            if (this.way === 'parts' && this.coach.step === 'own') sessionStore.setMode('own');
            sessionStore.selectPhrase(index);
            await sessionStore.listenThenRepeat();
        },
        nextStep,
        count(index) {
            const key = this.focusPart.templatePartKey;
            this.practice.progress = { ...(this.practice.progress ?? {}), [key]: practised(this.coach, index) };
            sessionStore.savePractice();
        },
        /** A step: its mode for your part, and its counts afresh. Record hands over to recording. */
        async setStep(step) {
            if (!step) return;
            this.readied = null;
            if (this.playing) await sessionStore.stopPlayback();
            const key = this.focusPart.templatePartKey;
            this.practice.progress = { ...(this.practice.progress ?? {}), [key]: toStep(this.coach, step) };
            sessionStore.savePractice();
            this.celebrated = null;
            sessionStore.selectPhrase(null);
            if (step === 'listen') sessionStore.setMode('listen');
            else if (step === 'singAlong') sessionStore.setMode('singAlong');
            else if (step === 'own') sessionStore.setMode('own');
            this.say(t('sing.say.step', { step: t(`sing.coach.step.${step}`) }));
        },
        /** The step's big action, on the phrase you're on. */
        coachAction() {
            const step = this.coach.step;
            this.readied = null; // played now: from here the path goes on by itself
            if (step === 'record') this.recordPart();
            else if (step === 'own') this.repeatPhrase(this.here);
            else this.playPhrase(this.here);
        },
        onceMore() {
            this.celebrated = `${this.focusPart.id}:${this.coach.step}`;
            // Once more, as the step does it: on your own that's listen then repeat.
            if (this.coach.step === 'own') this.repeatPhrase(0);
            else this.playPhrase(0);
        },

        // ---- Recording ----

        /** Records your part from the start (the pickup included), after the usual setup. */
        recordPart() {
            if (this.recording) {
                this.recordOrStop();
                return;
            }
            if (!this.focusPart) return;
            this.recorded = null;
            this.rerecordId = this.focusPart.id;
            this.position = 0;
            this.recordOrStop();
        },
        /** The recording stage's Start over: this take goes (nothing kept) and the same part records again, counted in. */
        async startOver() {
            if (!this.recording || this.busy) return;
            await sessionStore.dropRecording();
            await this.recordOrStop();
        },
        async interrupted() {
            if (this.recording && !this.busy) await this.finishRecording({ interrupted: true });
            else if (this.playing && !this.recording) await sessionStore.stopPlayback();
        },
        /** Stops recording; Just recorded shows what's next (or a re-sung phrase, on trial). */
        async finishRecording(options) {
            const outcome = await sessionStore.finishRecording(options);
            if (outcome.kind === 'patch') {
                this.showResung(outcome);
                return;
            }
            if (outcome.kind === 'short') {
                if (outcome.interrupted) toast(t('studio.stoppedBeforeStart'));
                return;
            }
            if (outcome.kind === 'silent') {
                toast(t('studio.silentTake'), { tone: 'error', seconds: 12 });
                return;
            }
            if (outcome.interrupted) toast(t('studio.takeKept', { time: formatTime(outcome.upTo) }), { seconds: 12 });
            this.lined = null;
            this.recorded = outcome;
            if (outcome.kind === 'slot' && sessionStore.templatePart(outcome.part)) this.scoreRecorded(outcome.part, outcome.previousScore);
            else if (outcome.part) sessionStore.updatePart(outcome.part, { scoring: null }); // nothing to work out: it goes up now
        },
        /**
         * How it went: the take scored in the background (the pitch tracked in a worker), against
         * last time and the phrases' bests; the summary is kept with the part (and goes up with it).
         */
        async scoreRecorded(part, previous = null) {
            this.recorded = { ...this.recorded, report: { score: null, track: null, change: null, improved: [] } };
            const samples = sessionStore.takeOf(part);
            const notes = sessionStore.templatePart(part)?.guide.notes;
            if (!samples || !notes) return;
            const takeId = (await db.get('parts', part.id))?.takeId ?? null; // the take this report is about
            try {
                const track = await trackInWorker(samples, part.sampleRate);
                const score = scoreTake(track, notes, {
                    beatSeconds: this.fullBeatSeconds,
                    startSeconds: part.offsetSeconds + (part.nudgeSeconds || 0),
                    transpose: this.project.transpose || 0,
                    phrases: this.reportPhrases,
                    timing: audio.latencyEstimated ? 'estimated' : 'measured',
                });
                // The other parts heard in the mic (no headphones): said calmly, and the take doesn't count.
                const bleed = bleedCheck(samples, part.sampleRate, { notes, beatSeconds: this.fullBeatSeconds, startSeconds: part.offsetSeconds + (part.nudgeSeconds || 0), track });
                const summary = scoreSummary(score);
                const change = bleed ? null : sinceLast(previous, summary);
                const key = part.templatePartKey;
                const { bests, improved } = phraseBests(session.practice.bests?.[key] ?? {}, summary.phrases);
                // Recorded again meanwhile: this report is about a take that's gone.
                if ((await db.get('parts', part.id))?.takeId !== takeId) return;
                if (this.recorded?.part.id !== part.id) {
                    sessionStore.updatePart(part, { scoring: null });
                    return;
                }
                this.recorded = { ...this.recorded, report: { score, track, change, improved: bleed ? [] : improved, bleed } };
                if (score.status === 'scored' && !bleed) this.offerGhost(track, score.percent, part.offsetSeconds + (part.nudgeSeconds || 0));
                if (score.status === 'scored' && !bleed && this.ghostWhere) stampDaily(this.ghostWhere.templateId, this.ghostWhere.partKey); // today's challenge taken
                if (score.status === 'scored' && !bleed) {
                    remember({ percent: score.percent, insights: score.insights, tendency: score.tendency }).catch((error) => console.error('Not remembered', error));
                    if (score.percent >= 90) countDone('excellentTakes', { add: 1 }).catch((error) => console.error('Not counted', error));
                    // Kept with the take it's about, so it goes up with that take (js/sync.js).
                    sessionStore.updatePart(part, { score: { ...summary, takeId }, scoring: null });
                    session.practice.bests = { ...(session.practice.bests ?? {}), [key]: bests };
                    sessionStore.savePractice();
                } else {
                    sessionStore.updatePart(part, { scoring: null }); // nothing to keep: the take goes up now
                }
            } catch (error) {
                console.error('How it went could not be worked out', error);
                sessionStore.updatePart(part, { scoring: null });
                if (this.recorded?.part.id === part.id) this.recorded = { ...this.recorded, report: null };
            }
        },
        // ---- Re-sing a phrase ----

        /** A phrase picked: where it will join, and what to sing so it joins smoothly. */
        pickResing(index) {
            this.resingPlanned = sessionStore.resingPlan(this.recorded.part, index);
            if (!this.resingPlanned) {
                this.closeResing();
                toast(t('sing.resing.cantJoin'), { tone: 'error' });
            }
        },
        closeResing() {
            this.dialog = null;
            this.resingPlanned = null;
        },
        /** Records just that stretch, with the others, after the usual setup; it stops by itself after the join. */
        startResing() {
            const part = this.recorded?.part ?? this.resung?.outcome.part;
            const plan = this.resingPlanned ?? this.resung?.outcome.plan;
            this.dialog = null;
            this.resingPlanned = null;
            if (!part || !plan) return;
            this.resung = null;
            this.recorded = null;
            sessionStore.prepareResing(part, plan);
            this.recordOrStop();
        },
        showResung(outcome) {
            const result = sessionStore.patchedTake(outcome);
            if (!result) {
                toast(t('sing.resing.cantJoin'), { tone: 'error', seconds: 12 });
                this.recorded = { kind: 'slot', part: outcome.part, first: false, next: null };
                return;
            }
            this.resung = { outcome, before: sessionStore.takeOf(outcome.part), ...result };
            sessionStore.tryTake(outcome.part, result.samples);
        },
        /** Plays the stretch around the new phrase, with it in place (nothing saved yet). */
        async hearResung() {
            if (this.playing) {
                await sessionStore.stopPlayback();
                return;
            }
            sessionStore.reviewListen();
            this.position = Math.max(0, this.resung.outcome.plan.from - 2);
            await sessionStore.togglePlay();
        },
        async keepResung() {
            const { outcome, samples, before } = this.resung;
            if (this.playing) await sessionStore.stopPlayback();
            this.resung = null;
            const undo = await sessionStore.keepTake(outcome.part, samples, before);
            toast(t('sing.resing.kept'), { seconds: 10, actions: [{ label: t('common.undo'), run: undo }] });
            this.recorded = { kind: 'slot', part: outcome.part, first: false, next: null };
        },
        /** Puts the take back as it was. */
        async dropResung() {
            const { outcome, before } = this.resung;
            if (this.playing) await sessionStore.stopPlayback();
            sessionStore.tryTake(outcome.part, before);
            this.resung = null;
            this.recorded = { kind: 'slot', part: outcome.part, first: false, next: null };
        },
        async resingAgain() {
            const { outcome, before } = this.resung;
            if (this.playing) await sessionStore.stopPlayback();
            sessionStore.tryTake(outcome.part, before);
            this.resingPlanned = outcome.plan;
            this.startResing();
        },
        async resingWhole() {
            await this.dropResung();
            this.recordPart();
        },
        /** Just recorded: hear your take with the others, from the start. */
        async hearTake() {
            if (this.playing) {
                await sessionStore.stopPlayback();
                return;
            }
            sessionStore.reviewListen();
            this.position = 0;
            await sessionStore.togglePlay();
        },
        /** The review's Play phrase N: from the phrase's start, with the others. */
        async hearPhrase(index) {
            const phrase = this.phraseList[index];
            if (!phrase) return;
            if (this.playing) await sessionStore.stopPlayback();
            sessionStore.reviewListen();
            await sessionStore.playFrom(phrase.startBeat * this.fullBeatSeconds, { to: phrase.endBeat * this.fullBeatSeconds });
        },
        /** The review's Re-sing phrase N: the re-sing sheet with that phrase picked. */
        resingFrom(index) {
            this.dialog = 'resing';
            if (Number.isInteger(index)) this.pickResing(index);
        },
        nextPart() {
            const next = this.recorded?.next;
            this.recorded = null;
            if (next) {
                sessionStore.practise(next);
                sessionStore.setMode('listen');
            }
        },
        nudgeMsOf(part) {
            return Math.round((part.nudgeSeconds || 0) * 1000);
        },
        /** Just recorded's nudge, in ms: saved as the part's offset, heard at once. */
        async hearPolished(on) {
            await sessionStore.setHearPolished(on);
        },
        // ---- Trim the ends (feature 17), as in the studio: live, with one Undo on closing ----
        openTrim() {
            const part = this.recorded.part;
            this.trimBefore = { start: part.trimStartFrames ?? 0, end: part.trimEndFrames ?? 0 };
            this.dialog = 'trim';
        },
        trim(trims) {
            sessionStore.setTrims(this.recorded.part, trims).catch((error) => console.error('The trim was not saved', error));
        },
        hearEdge(edge) {
            const span = heardSpan(this.recorded.part);
            sessionStore.playFrom(edge === 'start' ? span.from - 1 : span.to - 3);
        },
        /** Trim's Play trimmed part: the kept stretch, start to end, with the others. */
        hearTrimmed() {
            const span = heardSpan(this.recorded.part);
            sessionStore.playFrom(span.from, { to: span.to });
        },
        stopHearing() {
            sessionStore.stopPlayback();
        },
        closeTrim() {
            this.dialog = null;
            if (session.playing) sessionStore.stopPlayback();
            const part = this.recorded?.part;
            const before = this.trimBefore;
            if (!part || !before || (before.start === (part.trimStartFrames ?? 0) && before.end === (part.trimEndFrames ?? 0))) return;
            toast(t('trim.done', { part: this.nameOf(part) }), { seconds: 10, actions: [{ label: t('common.undo'), run: () => sessionStore.setTrims(part, before) }] });
        },
        nudge(ms) {
            const part = this.recorded?.part;
            if (!part) return;
            sessionStore.updatePart(part, { nudgeSeconds: ms / 1000 });
        },
        /** The nudge let go: playing, it carries on from here with the take in its new place. */
        nudged() {
            if (this.playing) sessionStore.startPlayback();
        },
        /** "Line it up for me": the median entry against the notes, as a nudge (only past 20 ms). */
        async lineUp() {
            const part = this.recorded?.part;
            const samples = part && sessionStore.takeOf(part);
            const notes = part && sessionStore.templatePart(part)?.guide.notes;
            if (!samples || !notes) return;
            this.lining = true;
            try {
                const track = await trackInWorker(samples, part.sampleRate);
                const score = scoreTake(track, notes, { beatSeconds: this.fullBeatSeconds, startSeconds: part.offsetSeconds, transpose: this.project.transpose || 0 });
                const late = score.entryMs ?? 0;
                this.lined = Math.abs(late) > 20 ? Math.max(-150, Math.min(150, -Math.round(late / 5) * 5)) : 0;
                if (this.lined) {
                    this.nudge(this.lined);
                    this.nudged();
                }
            } catch (error) {
                console.error('Lining up failed', error);
                this.lined = null;
            } finally {
                this.lining = false;
            }
        },

        // ---- Around the screen ----

        say(text) {
            this.said = '';
            this.$nextTick(() => (this.said = text));
        },
        /** The screen stays on while practising (recording keeps it on itself). */
        async keepAwake(on) {
            this.awake = on;
            if (on && !this.wakeLock && navigator.wakeLock) {
                const lock = await navigator.wakeLock.request('screen').catch(() => null);
                if (this.awake && !this.wakeLock) this.wakeLock = lock;
                else lock?.release().catch((error) => console.info('The wake lock was already gone', error)); // stopped meanwhile
                lock?.addEventListener?.('release', () => this.wakeLock === lock && (this.wakeLock = null));
            } else if (!on && this.wakeLock) {
                this.wakeLock.release().catch((error) => console.info('The wake lock was already gone', error));
                this.wakeLock = null;
            }
        },
        key(event) {
            const target = event.target instanceof Element ? event.target : null;
            if (this.dialog || this.choosing || !this.way || !this.focusPart || this.recorded || event.metaKey || event.ctrlKey || event.altKey) return;
            if (this.recording && event.key === 'Escape') {
                event.preventDefault();
                this.recordOrStop(); // Esc stops and keeps, as Space does
                return;
            }
            if (target?.closest('input, select, textarea, button, a, [role=dialog]')) return; // Space presses a focused button
            const key = event.key.toLowerCase();
            if (event.code === 'Space') {
                event.preventDefault();
                this.recording ? this.recordOrStop() : this.play();
            } else if (this.recording || !prefs.shortcuts) {
                return; // single-letter shortcuts can be turned off (WCAG 2.1.4); Space always plays
            } else if (key === 'r') {
                this.recordPart();
            } else if (['1', '2', '3'].includes(key) && this.way === 'whole') {
                this.setMode(['listen', 'singAlong', 'own'][Number(key) - 1]);
            } else if (key === 'l') {
                this.loop();
            } else if (key === 'n') {
                this.nextPhrase();
            } else if (key === 'm') {
                this.muteGuide();
            } else if (key === 'v') {
                this.setView(this.view === 'words' ? 'staff' : this.view === 'staff' ? 'notes' : 'words');
            }
        },
    },
};
