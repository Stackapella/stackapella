import { toRaw, toRefs } from 'vue';
import { styleOf } from '../band.js';
import { audio, closeMic, engine, unlock } from '../audio.js';
import { midiName } from '../dsp.js';
import { formatList, formatNumber, t, timeAgo, tk } from '../i18n.js';
import { guideId, hasTake, nextSlot } from '../practice.js';
import { joinSyllables, LyricsLane } from '../components/practice.js';
import { guideGain, peakDbfs, rmsDbfs } from '../levels.js';
import { goBack, navigate } from '../router.js';
import * as sessionStore from '../stores/session.js';
import { FRAMES_PER_PEAK, GUIDE_PEAKS_RATE, session } from '../stores/session.js';
import { lengthBeats, lyricLines, partsFor, slotName } from '../templates.js';
import { beatsPerBar, defaultPartName, formatTime, keyLabel, PART_COLORS } from '../theme.js';
import { encodeWav } from '../wav.js';
import { ExportDialog, HeadphonesDialog, MicDialog, PartDialog, ProjectDialog, RangeDialog, TimingDialog } from '../components/dialogs.js';
import { Icon } from '../components/icon.js';
import { NotesView } from '../components/notes.js';
import { BetaBanner, BetaReport, SoundCheck } from '../components/beta.js';
import { TellHillel } from './beta.js';
import { betaBar, loadBar } from '../beta.js';
import { alignedTake, describeDevice, enqueue, sendAll } from '../beta-outbox.js';
import { RecordSetup } from '../record-setup.js';
import { keptKey, rememberSettings, saveProject, shownTitle } from '../stores/projects.js';
import { api } from '../api.js';
import * as db from '../db.js';
import { syncNow } from '../stores/cloud.js';
import { staysOnDevice } from '../sync.js';
import { toast, Waveform } from '../components/ui.js';
import { VoiceNote } from '../components/voice-note.js';
import { confirmAction } from '../components/confirm.js';
import { TrimSheet } from '../components/trim.js';
import { encodeMp3 } from '../mp3.js';
import { ShareSheet } from '../components/share.js';
import { CountIn } from '../components/count-in.js';
import { TogetherSheet } from '../components/together.js';
import { paint } from '../colors.js';
import { AddVoice, GhostRow, SetupChoices, StudioNotice, VoiceRow } from '../components/studio.js';
import { MicLevel } from '../components/sing/frame.js';
import { session as tabStorage } from '../storage.js';
import { account } from '../stores/account.js';
import { fileNames, zip } from '../zip.js';
import { prefs } from '../stores/prefs.js';
import { heardSpan } from '../trim.js';
import { LockedBanner } from '../components/cloud.js';
import { lockReason } from '../stores/plan.js';
import { count } from '../usage.js';

const roundButton = 'grid shrink-0 place-items-center rounded-full glass-bubble text-sm font-bold text-cream transition';

// The session store's actions the template calls as they are.
const { togglePlay, templatePart } = sessionStore;

const NONE = 'none'; // pickId when the picked track was tapped again: nothing picked

/**
 * The studio: the mixer and editor for a project. Its audio session (loading, the mix, the
 * transport, practice and recording) lives in stores/session.js, shared with the Sing screens;
 * this view holds the layout, the dialogs and the words.
 */

const PAN_SIDE = { left: tk('studio.row.panLeft'), right: tk('studio.row.panRight') };

export const StudioView = {
    mixins: [RecordSetup],
    components: { CountIn, LyricsLane, Icon, Waveform, ProjectDialog, HeadphonesDialog, TimingDialog, ExportDialog, PartDialog, MicDialog, RangeDialog, NotesView, BetaBanner, BetaReport, SoundCheck, TellHillel, VoiceNote, TrimSheet, LockedBanner, ShareSheet, TogetherSheet, AddVoice, GhostRow, StudioNotice, SetupChoices, VoiceRow, MicLevel },
    props: { id: { type: String, required: true } },
    setup: () => ({ ...toRefs(session), sessionStore, audio, defaultPartName, formatTime, shownTitle, PART_COLORS, roundButton, hasTake, GUIDE_PEAKS_RATE, FRAMES_PER_PEAK, togglePlay, templatePart, prefs, betaBar, count }),
    data: () => ({
        recordsFromStart: true, // the studio always records from 0:00, Hillel's ask (record-setup.js)
        renaming: null, // the part whose name is being edited in its row
        accountTakes: {}, // part id -> the account's kept takes for it (parts.takes.list), once its row opens
        hearingTake: null, // { id, audio } while a kept take plays on its own
        dialog: null, // settings | headphones | timing | timing-record | mic | export | part | range
        dialogPart: null,
        exporting: false,
        box: { left: 0, width: 0 }, // where the timeline sits in the scrolling area, for the playhead
        hearing: false, // the beta take playing in the mix, as learners will hear it
        previousTake: null, // a beta take's Undo: the take before it, to send that one instead
        sending: false,
        openRow: null, // the part whose row is open (its level, timing, trim and more)
        headphones: tabStorage.get('stackapella.headphones') === '1', // on: recording skips the headphones question
        planned: 0, // voices planned with Add another voice (rows still to come)
        pickId: null, // the part its circle picked to record (else the next to record)
    }),
    template: `
        <div class="flex h-dvh flex-col bg-ink">
            <div v-if="missing" class="m-auto text-center">
                <p class="text-cream">{{ t('studio.missing') }}</p>
                <button type="button" class="mt-4 rounded-full bg-cream px-5 py-2.5 font-semibold text-ink" @click="navigate('/')">{{ t('common.backToProjects') }}</button>
            </div>
            <div v-else-if="project" class="relative flex min-h-0 flex-1 flex-col overflow-hidden">

                <!-- Header -->
                <header class="flex items-center gap-3 px-3 pb-2 pt-[max(0.75rem,env(safe-area-inset-top))] md:px-5 md:py-3">
                    <button type="button" class="grid size-10 shrink-0 place-items-center rounded-full glass-bubble text-cream" :aria-label="t('common.backToProjects')" @click="leave"><Icon name="back" :size="20" /></button>
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="song-name">{{ t('project.nameLabel') }}</label>
                        <input id="song-name" :value="project.title" maxlength="80" autocomplete="off" translate="no" :placeholder="t('project.namePlaceholder')" :readonly="!!project.shared"
                            class="block w-full truncate rounded-lg border border-transparent bg-transparent px-1 -mx-1 text-lg font-extrabold tracking-tight text-cream placeholder:text-muted hover:border-line focus:border-accent md:text-xl"
                            @change="rename($event.target.value)" @keydown.enter="$event.target.blur()" />
                        <button type="button" class="block min-h-6 max-w-full truncate text-start text-xs text-muted hover:text-cream md:text-sm" @click="dialog = project.shared ? 'together' : 'settings'">{{ meta }}<span v-if="credit"> · {{ credit }}</span> · <span :class="saving ? 'text-muted' : 'text-good'">{{ saving ? t('common.saving') : t('common.saved') }}</span></button>
                    </div>
                    <span v-if="recording" class="flex items-center gap-2 rounded-full bg-record px-3 py-1.5 text-sm font-bold text-record-label" role="status"><span class="size-2 animate-pulse rounded-full bg-record-label"></span><span translate="no">{{ t('studio.recordingNamed', { name: liveName }) }}</span><span class="tabular-nums">{{ formatTime(Math.max(0, position - recordFrom)) }}</span></span>
                    <template v-else>
                        <!-- The mic (53.png): a live dot, green while it's open, and the device's name. -->
                        <button type="button" class="hidden max-w-64 items-center gap-2 rounded-full border py-2 pe-4 ps-3 text-sm hover:bg-raised md:flex" :class="audio.mic && (audio.latencyFrames === null || audio.latencyEstimated) ? 'border-warn/60 text-cream' : 'border-edge text-cream'" :title="audio.mic ? t('studio.chooseMic') : t('studio.mic.idle')" data-mic-button @click="openAudioDialog('mic')">
                            <span class="size-2 shrink-0 rounded-full" :class="audio.mic ? 'bg-good shadow-[0_0_8px_var(--color-good)]' : 'bg-line'" aria-hidden="true"></span><span class="truncate" translate="no">{{ micLabel }}</span><span v-if="audio.mic && (audio.latencyFrames === null || audio.latencyEstimated)" class="shrink-0 text-warn">· {{ t('studio.checkTiming') }}</span><span v-else-if="audio.mic" class="shrink-0 text-muted">· {{ t('studio.mic.live') }}</span>
                        </button>
                        <button v-if="canTogether" type="button" class="btn-secondary btn-sm hidden items-center gap-2 lg:flex" :class="people.length > 1 ? 'pe-3 ps-1' : ''" @click="dialog = 'together'">
                            <span v-if="people.length > 1" class="grid size-7 place-items-center rounded-full bg-part-1 text-xs font-bold text-part-text-1" aria-hidden="true">{{ initial }}</span>{{ people.length > 1 ? togetherLabel : t('studio.inviteSingers') }}
                        </button>
                        <button v-if="!project.beta && parts.some(hasTake)" type="button" class="btn-secondary btn-sm hidden md:flex" @click="dialog = 'share'">{{ t('share.title') }}</button>
                        <button v-if="!project.beta" type="button" class="btn-secondary btn-sm hidden items-center gap-1.5 md:flex" :disabled="!parts.length" @click="dialog = 'export'"><Icon name="download" :size="16" /> {{ t('studio.export') }}</button>
                        <button v-if="!project.shared" type="button" class="hidden size-10 place-items-center rounded-full glass-bubble text-cream md:grid" :aria-label="t('studio.menu.settings')" :title="t('studio.menu.settings')" @click="dialog = 'settings'"><Icon name="sliders" :size="18" /></button>
                        <button v-if="isTemplate && !project.beta" type="button" class="btn-primary btn-sm flex items-center gap-1.5" @click="navigate('/p/' + id + '/sing')"><Icon name="note" :size="16" /> {{ t('sing.open') }}</button>
                        <details class="relative md:hidden">
                            <summary class="list-none rounded-full border border-line p-2 text-cream hover:bg-raised" :aria-label="t('common.more')"><Icon name="more" /></summary>
                            <div class="absolute end-0 z-30 mt-2 w-60 rounded-2xl border border-line bg-raised p-1.5 shadow-xl">
                                <button v-if="isTemplate && !project.beta" type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" @click="menu('sing', $event)">{{ t('studio.menu.learn') }}</button>
                                <button v-if="!project.beta && parts.some(hasTake)" type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" @click="menu('share', $event)">{{ t('share.title') }}</button>
                                <button type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel disabled:opacity-40" :disabled="!parts.length" @click="menu('export', $event)">{{ t('studio.export') }}</button>
                                <button v-if="!project.shared" type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" @click="menu('settings', $event)">{{ t('studio.menu.settings') }}</button>
                                <button type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" @click="menu('mic', $event)">{{ t('studio.menu.micAndTiming') }}</button>
                                <button v-if="canTogether" type="button" class="block w-full rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" @click="menu('together', $event)">{{ t('together.title') }}</button>
                                <button v-if="parts.some(hasTake)" type="button" class="flex w-full items-center gap-2 rounded-xl px-3 py-2.5 text-start text-cream hover:bg-panel" :aria-pressed="prefs.hearPolished" @click="menu('polish', $event)"><Icon name="sparkle" :size="16" :class="prefs.hearPolished ? 'text-accent' : 'text-muted'" /> {{ t('polish.hear') }}</button>
                            </div>
                        </details>
                    </template>
                </header>

                <LockedBanner v-if="locked" :reason="locked" class="mx-3 mt-1 md:mx-5" />
                <p v-if="project.shared" class="mx-3 mt-1 rounded-2xl bg-raised px-4 py-3 text-sm text-cream md:mx-5" role="status">
                    {{ project.shared.removed ? t('together.noLonger') : t('together.banner', { owner: project.shared.owner?.name ?? '' }) }}
                    <button v-if="!project.shared.removed" type="button" class="link ms-1" @click="dialog = 'together'">{{ t('together.who') }}</button>
                </p>
                <BetaBanner v-if="project.beta && betaPart" :part-name="nameOf(betaPart)" :song="shownTitle(project)" :note="project.beta.note" :phrase="notePhrase" :looping="loopedPhrase !== null && loopedPhrase === notePhrase" :bar="betaBar" :can-send="hasTake(betaPart) && !recording"
                    @parts="leave" @send="openReport" @loop="loopPhrase" @tell="dialog = 'tell'" />
                <!-- A phrase looping (from the banner or the report): said, with the way out (Escape too). -->
                <p v-if="loopedPhrase !== null" class="mx-3 mt-1 flex flex-wrap items-center gap-3 rounded-2xl bg-raised px-4 py-2.5 text-sm text-cream md:mx-5" role="status" data-loop-on>
                    <Icon name="loop" :size="16" class="text-accent" /><span class="flex-1">{{ t('studio.loop.on', { number: loopedPhrase + 1 }) }}</span>
                    <button type="button" class="btn-secondary btn-sm" aria-pressed="true" data-loop-off @click="endLoop">{{ t('studio.loop.off') }}</button>
                </p>

                <VoiceNote @use-range="useRange" />

                <div v-if="audio.deviceChanged && audio.latencyFrames !== null" class="mx-3 mt-1 flex items-center gap-3 rounded-2xl bg-part-6/10 px-4 py-2 text-sm text-cream md:mx-5">
                    {{ t('studio.deviceChanged') }} <button type="button" class="font-semibold text-part-6 underline" @click="openAudioDialog('mic')">{{ t('studio.checkMicAndTiming') }}</button>
                    <button type="button" class="ms-auto text-muted" :aria-label="t('common.dismiss')" @click="audio.deviceChanged = false"><Icon name="close" :size="16" /></button>
                </div>

                <!-- The stack of voices -->
                <div class="flex min-h-0 flex-1">
                    <div class="relative flex min-h-0 flex-1 flex-col">
                        <div class="mx-3 mt-2 flex md:mx-5" dir="ltr">
                            <div class="hidden shrink-0 truncate pe-3 ps-1 text-xs md:block md:w-[373px]" data-summary><template v-if="parts.length"><span class="font-bold text-cream">{{ t('studio.partCount', { count: parts.length }) }}</span><span class="text-muted"> · {{ yoursSummary }}</span></template></div>
                            <div ref="ruler" class="relative mx-2 h-7 flex-1 cursor-pointer md:me-[9px] md:ms-0" @pointerdown="seekFrom($event)">
                                <span v-for="mark in marks" :key="mark.at" class="absolute top-0 h-full border-s border-line ps-1 pt-1 text-[10px] text-muted md:text-[11px]" :style="{ left: (mark.at / length * 100) + '%' }">{{ mark.label }}</span>
                                <span class="pointer-events-none absolute -bottom-1 size-3 -translate-x-1/2 rounded-full" :class="recording ? 'bg-record' : 'bg-cream'" :style="{ left: (Math.max(0, position) / length * 100) + '%' }" aria-hidden="true"></span>
                            </div>
                        </div>
                        <!-- The words (81.png): each line where it's sung, the one now filled; a tap goes there. -->
                        <div v-if="laneLines.length" class="mx-3 mt-1 flex md:mx-5" dir="ltr">
                            <div class="hidden shrink-0 pe-3 ps-1 pt-1.5 text-xs text-muted md:block md:w-[373px]">{{ t('studio.lyricsLane') }}</div>
                            <LyricsLane class="mx-2 flex-1 md:me-[9px] md:ms-0" :lines="laneLines" :length-seconds="length" :position="position" @seek="sessionStore.seek" />
                        </div>
                        <div ref="scroller" class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 pb-6 pt-2 md:px-5">
                            <div class="flex min-h-full flex-col gap-2">
                                <!-- The tracks: they share the height on a tall window (88 to 150 px each), with one playhead across them. -->
                                <div v-if="parts.length" class="relative flex flex-col gap-2 md:flex-1" data-tracks>
                                <template v-for="part in parts" :key="part.id">
                                    <VoiceRow :name="nameOf(part)" :letter="letterOf(part)" :color="paint(part.color)" :tags="tagsOf(part)" :when="whenOf(part)" :guide="!hasTake(part)"
                                        :solo="part.solo" :muted="part.muted" :audible="isAudible(part)" :open="openRow === part.id" :recording="recording && rerecordId === part.id"
                                        :position="position" :length-seconds="length" :volume="part.volume" :pan="part.pan" :tall="rowFocus === part.id || (recording && rerecordId === part.id)"
                                        :pickable="!project.beta && !recording && sessionStore.mine(part)" :picked="picked?.id === part.id" :data-next-track="picked?.id === part.id && !recording ? '' : null" :class="openRow === part.id ? 'md:flex-none' : 'md:max-h-[150px] md:min-h-[88px] md:flex-1'"
                                        @toggle="toggleRow(part)" @solo="toggle(part, 'solo')" @mute="toggle(part, 'muted')" @volume="update(part, { volume: $event })" @pan="update(part, { pan: $event })" @seek="laneSeek($event)" @pick="togglePick(part)">
                                        <template #meter><MicLevel v-if="recording && rerecordId === part.id" color="var(--color-record)" /></template>
                                        <template #lane>
                                            <template v-if="recording && rerecordId === part.id">
                                                <div v-if="part.guidePeaks" class="absolute inset-0 opacity-30"><Waveform :peaks="part.guidePeaks" :frames-per-peak="FRAMES_PER_PEAK" :sample-rate="part.guidePeaksRate || GUIDE_PEAKS_RATE" :offset-seconds="guideVoices[part.id]?.offsetSeconds || 0" :length-seconds="length" :color="paint(part.color)" dim /></div>
                                                <div class="absolute inset-0"><Waveform :peaks="livePeaks" :frames-per-peak="512" :sample-rate="engine.sampleRate" :offset-seconds="recordFrom" :length-seconds="length" :color="paint(part.color)" :version="live?.count ?? 0" /></div>
                                            </template>
                                            <Waveform v-else-if="hasTake(part)" :peaks="part.peaks" :frames-per-peak="part.framesPerPeak" :sample-rate="part.sampleRate"
                                                :offset-seconds="part.offsetSeconds + (part.nudgeSeconds || 0)" :length-seconds="length" :color="paint(part.color)"
                                                :trim-start-frames="part.trimStartFrames || 0" :trim-end-frames="part.trimEndFrames || 0" :dim="!isAudible(part)" />
                                            <Waveform v-else-if="part.guidePeaks" :peaks="part.guidePeaks" :frames-per-peak="FRAMES_PER_PEAK" :sample-rate="part.guidePeaksRate || GUIDE_PEAKS_RATE"
                                                :offset-seconds="guideVoices[part.id]?.offsetSeconds || 0" :length-seconds="length" :color="paint(part.color)" dim />
                                            <div v-if="rowFocus === part.id && templatePart(part)" class="absolute inset-0"><NotesView :notes="templatePart(part).guide.notes" :transpose="project.transpose || 0"
                                                :beat-seconds="beatSeconds" :length-seconds="length" :color="paint(part.color)" :position="position" :label="focusNoteNames" /></div>
                                        </template>
                                        <template #open>
                                            <!-- The open row (Hillel's studio redesign): Level and Pan with their values, Timing, four equal actions, then the takes. -->
                                            <div class="space-y-3 text-sm" data-row-open>
                                                <input v-if="renaming === part.id" :value="nameOf(part)" maxlength="40" class="input min-h-10 py-1.5" :aria-label="t('studio.row.renameLabel')" data-rename-input
                                                    @keydown.enter.prevent="renameTo(part, $event.target.value)" @keydown.escape.prevent.stop="renaming = null" @blur="renaming === part.id && renameTo(part, $event.target.value)" />
                                                <div class="grid grid-cols-[4rem_minmax(0,1fr)_3rem] items-center gap-x-3 gap-y-1 text-muted">
                                                    <label :for="'level-' + part.id">{{ t('studio.row.level') }}</label>
                                                    <input :id="'level-' + part.id" v-fill type="range" min="0" max="1.5" step="0.01" :value="part.volume" class="range" @input="update(part, { volume: +$event.target.value })" />
                                                    <span class="text-end tabular-nums text-cream">{{ formatNumber(Math.round(part.volume * 100)) }}</span>
                                                    <label :for="'pan-' + part.id">{{ t('studio.row.pan') }}</label>
                                                    <input :id="'pan-' + part.id" type="range" dir="ltr" min="-1" max="1" step="0.05" :value="part.pan" class="range [--p:0%]" @input="update(part, { pan: +$event.target.value })" @dblclick="update(part, { pan: 0 })" />
                                                    <span class="text-end tabular-nums text-cream">{{ panLabel(part.pan) }}</span>
                                                </div>
                                                <div v-if="hasTake(part) && sessionStore.mine(part)" class="grid grid-cols-[4rem_minmax(0,1fr)] items-center gap-x-3 text-muted">
                                                    <span>{{ t('part.timing') }}</span>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <div class="stepper flex-1">
                                                            <button type="button" :aria-label="t('studio.row.earlier')" @click="nudge(part, -0.01)"><Icon name="minus" :size="16" /></button>
                                                            <span class="flex-1 text-center font-[650] text-cream">{{ nudgeLabel(part) }}</span>
                                                            <button type="button" :aria-label="t('studio.row.later')" @click="nudge(part, 0.01)"><Icon name="plus" :size="16" /></button>
                                                        </div>
                                                        <button v-if="part.nudgeSeconds" type="button" class="btn-ghost btn-sm" @click="update(part, { nudgeSeconds: 0 })">{{ t('part.asRecorded') }}</button>
                                                    </div>
                                                </div>
                                                <div class="grid grid-cols-4 gap-2">
                                                    <button type="button" class="btn-secondary btn-sm px-2" :disabled="recording" @click="rerecord(part)"><span class="size-2 shrink-0 rounded-full bg-record" aria-hidden="true"></span>{{ !sessionStore.mine(part) ? t('together.singItToo') : t('studio.row.retake') }}</button>
                                                    <button type="button" class="btn-secondary btn-sm px-2" :disabled="recording || !hasTake(part) || !sessionStore.mine(part)" @click="openTrim(part)">{{ t('trim.button') }}</button>
                                                    <button type="button" class="btn-secondary btn-sm px-2" :disabled="recording || !sessionStore.mine(part) || renaming === part.id" @click="startRename(part)">{{ t('studio.row.rename') }}</button>
                                                    <button type="button" class="btn-secondary btn-sm px-2" @click="openPart(part)">{{ t('studio.row.voice') }}</button>
                                                </div>
                                                <button v-if="part.templatePartKey && !project.beta" type="button" class="btn-ghost btn-sm" @click="singPart(part)">{{ t('studio.row.learn') }}</button>
                                                <!-- Takes (Hillel: "keep all takes so users can swap to try. You'd see the takes by expanding the row"). -->
                                                <section v-if="hasTake(part) && takesOf(part).length" class="space-y-1.5" :aria-labelledby="'takes-' + part.id" data-takes>
                                                    <h3 :id="'takes-' + part.id" class="text-xs font-semibold text-muted">{{ t('studio.takes.title') }}</h3>
                                                    <ul class="divide-y divide-line rounded-[14px] bg-(--fill)">
                                                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2" data-take-current>
                                                            <span class="min-w-0 flex-1"><span class="block font-semibold text-cream">{{ t('studio.takes.current') }}</span><span class="block text-xs text-muted">{{ takeLine({ at: part.recordedAt, seconds: part.sampleRate ? part.frames / part.sampleRate : 0, pitch: part.score?.pitchPercent ?? null }) }}</span></span>
                                                        </li>
                                                        <li v-for="take in takesOf(part)" :key="take.id" class="flex flex-wrap items-center gap-x-2 gap-y-1 px-3 py-2" data-take>
                                                            <span class="min-w-0 flex-1 text-cream">{{ takeLine(take) }}</span>
                                                            <button type="button" class="btn-ghost btn-sm" :disabled="take.ready === false" :aria-label="hearingTake?.id === take.id ? t('studio.stop') : t('studio.takes.play', { line: takeLine(take) })" @click="hearTake(part, take)"><Icon :name="hearingTake?.id === take.id ? 'stop' : 'play'" :size="14" /></button>
                                                            <button v-if="sessionStore.mine(part)" type="button" class="btn-secondary btn-sm" :disabled="recording || take.ready === false" @click="useTakeOf(part, take)">{{ t('studio.takes.use') }}</button>
                                                            <button v-if="sessionStore.mine(part)" type="button" class="btn-ghost btn-sm" :disabled="recording" :aria-label="t('studio.takes.deleteNamed', { line: takeLine(take) })" @click="deleteTakeOf(part, take)"><Icon name="trash" :size="14" /></button>
                                                        </li>
                                                    </ul>
                                                </section>
                                            </div>
                                        </template>
                                    </VoiceRow>
                                </template>
                                <span class="pointer-events-none absolute inset-y-0 hidden w-0.5 md:block" :class="recording ? 'bg-record' : 'bg-cream'" :style="{ left: 'calc(373px + (100% - 382px) * ' + playheadShare + ')' }" aria-hidden="true" data-playhead></span>
                                </div>

                                <!-- A new part, while it's recorded -->
                                <VoiceRow v-if="live && !rerecordId" :name="liveName" :letter="liveName.slice(0, 1).toUpperCase()" :color="paint(live.color)" :tags="[{ text: t('studio.row.recording'), tone: 'rec' }]" :when="t('studio.row.overOthers')"
                                    recording tall :position="position" :length-seconds="length" @seek="() => {}">
                                    <template #meter><MicLevel color="var(--color-record)" /></template>
                                    <template #lane><div class="absolute inset-0"><Waveform :peaks="livePeaks" :frames-per-peak="512" :sample-rate="engine.sampleRate" :offset-seconds="recordFrom" :length-seconds="length" :color="paint(live.color)" :version="live.count" /></div></template>
                                </VoiceRow>

                                <!-- The next voice to add -->
                                <template v-if="showAdd && !parts.length">
                                    <AddVoice :first="!parts.length" :number="parts.length + 1" :name="defaultPartName(parts.length)" :color="paint(addColor)" :after="parts[0] ? nameOf(parts[0]) : ''" :mic="micLabel" :hearing="!!audio.mic"
                                        :headphones="headphones" :count-in="project.countInBars" :click="!!project.metronome" :bpm="project.bpm || null"
                                        @record="transportRecord" @mic="openAudioDialog('mic')" @headphones="setHeadphones" @count-in="setCountIn" @click="setClick" />
                                    <GhostRow v-for="g in ghosts" :key="g.index" :name="g.name" :color="paint(g.color)" :text="g.text" />
                                </template>
                                <!-- The next track, armed (Hillel's studio redesign): a dashed slot that the ring arms, then + Add track. -->
                                <template v-if="showAdd && parts.length">
                                    <section v-for="n in 1 + planned" :key="'armed-' + n" class="flex flex-col gap-2 rounded-2xl border p-2 md:flex-row md:items-stretch md:gap-3" :class="n === 1 && !picked ? 'border-record/50' : 'border-line'" :aria-label="defaultPartName(parts.length + n - 1)" :data-next-track="n === 1 && !picked ? '' : null" data-armed>
                                        <div class="flex items-center gap-2.5 md:w-[352px] md:shrink-0">
                                            <button v-if="n === 1" type="button" class="tap-44 grid size-8 shrink-0 place-items-center rounded-full border-2 transition" :class="!picked ? 'border-record' : 'border-line hover:border-record/70'" :aria-pressed="!picked ? 'true' : 'false'" :aria-label="t('studio.pickNamed', { name: defaultPartName(parts.length) })" data-pick-new @click="pickId = null">
                                                <span class="size-3.5 rounded-full" :class="!picked ? 'bg-record' : 'bg-transparent'" aria-hidden="true"></span>
                                            </button>
                                            <span v-else class="size-8 shrink-0" aria-hidden="true"></span>
                                            <span class="min-w-0"><span class="flex items-center gap-2 font-bold text-cream" translate="no"><span class="size-2.5 shrink-0 rounded-full" :style="{ background: paint(n === 1 ? addColor : ghosts[0]?.color ?? addColor) }" aria-hidden="true"></span>{{ defaultPartName(parts.length + n - 1) }}</span>
                                                <span class="block text-xs text-muted">{{ n === 1 && !picked ? t('studio.row.armed') : t('studio.add.waiting') }}</span></span>
                                        </div>
                                        <div class="h-14 shrink-0 rounded-lg border border-dashed md:h-auto md:min-h-14 md:flex-1" :class="n === 1 && !picked ? 'border-record/50' : 'border-line'" aria-hidden="true"></div>
                                    </section>
                                    <button v-if="parts.length + planned < 7" type="button" class="btn-secondary self-start" data-add-track @click="planned++; count('track_add')"><Icon name="plus" :size="16" /> {{ t('studio.row.addTrack') }}</button>
                                </template>

                                <p v-if="!parts.length" class="flex flex-wrap items-center justify-center gap-x-2 px-2 text-center text-sm text-muted">{{ t('studio.add.orLearn') }} <button type="button" class="btn-ghost btn-sm" @click="navigate('/')">{{ t('studio.add.pickSong') }}</button></p>
                            </div>
                        </div>
                        <div v-if="preparing" class="pointer-events-none absolute inset-x-0 top-12 mx-auto w-fit rounded-full bg-raised px-4 py-2 text-sm text-cream shadow-lg">{{ t('studio.preparingGuides') }}</div>
                        <div v-if="countIn !== null" class="pointer-events-none absolute inset-0 grid place-items-center bg-ink/40">
                            <CountIn :key="countIn" :beat="countIn" class="count-pop text-[140px] font-extrabold leading-none text-cream md:text-[200px]" />
                        </div>
                    </div>
                    <aside v-if="people.length > 1" class="hidden w-64 shrink-0 space-y-3 border-s border-line p-4 lg:block" :aria-label="t('together.who')">
                        <h2 class="text-sm font-bold text-cream">{{ t('together.who') }}</h2>
                        <ul class="space-y-2">
                            <li v-for="p in people" :key="p.name" class="flex items-center gap-2 text-sm text-cream" translate="no"><span class="grid size-7 place-items-center rounded-full bg-raised text-xs font-bold">{{ p.name.slice(0, 1) }}</span>{{ p.name }}<span v-if="p.you" class="text-muted">{{ t('together.you') }}</span></li>
                        </ul>
                        <button v-if="canTogether" type="button" class="btn-ghost btn-sm" @click="dialog = 'together'">{{ t('together.title') }}</button>
                    </aside>
                </div>

                <StudioNotice />
                <!-- Transport -->
                <footer class="border-t border-line bg-panel px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-3 md:px-6 md:py-4" :class="recording ? 'bg-record/5' : ''">
                    <div class="flex items-center gap-2 md:gap-3">
                        <div class="w-16 shrink-0 md:w-auto md:min-w-48">
                            <p class="text-xl font-bold tabular-nums md:text-4xl" :class="recording ? 'text-record' : 'text-cream'">{{ formatTime(position) }}<span class="hidden text-base font-normal text-muted md:inline"> / {{ formatTime(contentLength) }}</span></p>
                            <p class="truncate text-[11px] text-muted md:text-xs">{{ recording ? t('studio.othersPlayOn', { where: barBeat || '' }) : barBeat }}</p>
                        </div>
                        <template v-if="recording">
                            <button type="button" class="ms-auto flex items-center gap-3 rounded-full border-2 border-record py-2 pe-6 ps-2 font-bold text-cream" @click="recordOrStop">
                                <span class="grid size-10 place-items-center rounded-full bg-record text-record-label"><Icon name="stop" :size="18" /></span>{{ t('studio.stopRecording') }}
                            </button>
                        </template>
                        <template v-else>
                            <button type="button" :class="[roundButton, 'ms-auto size-11', playing || position > 0 ? '' : 'hidden md:grid']" :aria-label="playing ? t('studio.stop') : t('studio.rewind')" @click="stopOrRewind"><Icon :name="playing ? 'stop' : 'rewind'" :size="16" /></button>
                            <button v-if="somethingToPlay" type="button" class="grid size-14 shrink-0 place-items-center rounded-full glass-bubble-solid transition" :aria-label="playing ? t('studio.pause') : t('studio.play')" @click="togglePlay"><Icon :name="playing ? 'pause' : 'play'" :size="26" /></button>
                            <button type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-full glass-record py-2 pe-4 ps-2 font-semibold transition disabled:opacity-50 md:max-w-xs md:flex-none md:pe-7" :disabled="noTrack" data-record :class="playing || position > 0 ? '' : somethingToPlay ? 'ms-auto md:ms-0' : 'ms-auto md:flex-1'" @click="transportRecord">
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-ink/90 text-record"><Icon name="record" :size="16" /></span>
                                <span class="truncate">{{ recordLabel }}</span>
                            </button>
                            <div class="ms-auto hidden md:block">
                                <SetupChoices compact :headphones="headphones" :count-in="project.countInBars" :click="!!project.metronome" :polished="parts.some(hasTake) ? prefs.hearPolished : null" @headphones="setHeadphones" @count-in="setCountIn" @click="setClick" @polished="toggleHearPolished" />
                            </div>
                        </template>
                    </div>
                </footer>
            </div>
            <ProjectDialog v-if="dialog === 'settings'" :project="project" :can-template="!project.beta && parts.some(hasTake)" @close="dialog = null" @save="saveSettings" @range="dialog = 'range'" @template="navigate('/create?from=' + project.id)" />
            <HeadphonesDialog v-if="dialog === 'headphones'" @close="dialog = null" @ok="headphones = true; headphonesOn($event)" />
            <TimingDialog v-if="dialog === 'timing' || dialog === 'timing-record'" :before-recording="dialog === 'timing-record'" :required="!!project?.beta" @close="dialog = null" @done="timingDone" @skip="timingSkipped" />
            <SoundCheck v-if="dialog === 'soundcheck'" @close="dialog = null" @done="soundCheckDone" />
            <BetaReport v-if="dialog === 'betaReport' && betaPart" :samples="betaTake" :sample-rate="betaPart.sampleRate" :notes="templatePart(betaPart).guide.notes" :beat-seconds="beatSeconds"
                :start-seconds="0" :phrases="phraseList.map((p) => [p.startBeat, p.endBeat])" :syllables="betaSyllables" :timing="betaPart.recordedWith?.estimated ?? audio.latencyEstimated ? 'estimated' : 'measured'" :hearing="hearing" :busy="sending" :can-use-previous="!!previousTake" :bar="betaBar" :assignment-id="project.beta.assignmentId" @previous="usePreviousTake" @tell="dialog = 'tell'" @course="navigate('/learn')"
                @close="closeReport" @again="recordAgain" @loop="loopFromReport" @hear="hearAsLearners" @send="sendTake" />
            <TellHillel v-if="dialog === 'tell'" :context="tellContext" @close="dialog = null" />
            <MicDialog v-if="dialog === 'mic'" @close="dialog = null" @timing="openAudioDialog('timing')" />
            <ExportDialog v-if="dialog === 'export'" :title="shownTitle(project)" :seconds="contentLength" :parts="exportParts" :default-name="exportName" :sample-rate="engine.sampleRate" @unmute="(id) => update(parts.find((p) => p.id === id), { muted: false })" :credits="voiceCredits" :has-chords="!!project.template?.chords?.length" :busy="exporting" @close="dialog = null" @export="exportMix" @upgrade="navigate('/plus?from=' + $event)" />
            <PartDialog v-if="dialog === 'part' && dialogPart" :theirs="sessionStore.mine(dialogPart) ? null : (dialogPart.createdBy?.name ?? project.shared?.owner?.name ?? '')" :part="dialogPart" :shown-name="nameOf(dialogPart)" :color="paint(dialogPart.color)" :voice="guideVoices[dialogPart.id] ?? null" :recording="recording" @voice="chooseVoice(dialogPart, $event)" :slot="!!dialogPart.templatePartKey" :recorded="hasTake(dialogPart)" @close="dialog = null" @update="update(dialogPart, $event)" @rerecord="rerecord(dialogPart)" @delete="removePart(dialogPart)" @clear="clearTakeOf(dialogPart)" @practise="dialog = null; singPart(dialogPart)" @trim="openTrim(dialogPart)" :harmonise="!project.template && !project.beta && !project.shared" @harmonise="harmonise(dialogPart)" />
            <TrimSheet v-if="dialog === 'trim' && dialogPart" :part="dialogPart" :name="nameOf(dialogPart)" :color="paint(dialogPart.color)" :beat-seconds="60 / (project.bpm || 90)" :playing="playing" :can-line-up="parts.filter(hasTake).length > 1"
                @trim="trim(dialogPart, $event)" @play-start="hearEdge(dialogPart, 'start')" @play-end="hearEdge(dialogPart, 'end')" @play-trimmed="hearTrimmed(dialogPart)" @stop="stopPlayback" @line-up="lineUp" @close="closeTrim(dialogPart)" />
            <ShareSheet v-if="dialog === 'share'" :title="project.title || ''" :parts="sharedParts" @close="dialog = null" />
            <TogetherSheet v-if="dialog === 'together'" :project="project" :parts="parts" @close="dialog = null" @add-part="addMine" @left="dialog = null" />
            <RangeDialog v-if="dialog === 'range'" :template="project.template" :transpose="project.transpose || 0" @close="dialog = null" @use="useRange" />
        </div>`,
    computed: {
        /** Where a recording stops by itself: a song's own length, or the first track's end (not the track being recorded over). */
        songEnd() {
            if (this.project?.template) return lengthBeats(this.project.template) * this.beatSeconds;
            const first = this.parts.filter((p) => hasTake(p) && p.id !== this.rerecordId).sort((a, b) => a.order - b.order)[0];
            return first ? heardSpan(first).to : null;
        },
        engine: () => engine,
        /** The next voice's colour: the first part colour not used yet. */
        addColor() {
            const used = new Set(this.parts.map((p) => p.color));
            return PART_COLORS.find((c) => !used.has(c)) ?? PART_COLORS[this.parts.length % PART_COLORS.length];
        },
        /** The add row: a plain project's next voice (a song's parts are its rows; a beta take records its own). */
        showAdd() {
            return !this.isTemplate && !this.project.beta && !this.recording && !this.project.shared?.removed;
        },
        /** The voices still to come under the one up next (53.png): two in an empty studio, one after the first, and any planned with Add another voice. */
        ghosts() {
            const count = (this.parts.length === 0 ? 2 : this.parts.length === 1 ? 1 : 0) + this.planned;
            const used = new Set([...this.parts.map((p) => p.color), this.addColor]);
            const free = PART_COLORS.filter((c) => !used.has(c));
            const first = this.parts[0] ? this.nameOf(this.parts[0]) : defaultPartName(0);
            return Array.from({ length: count }, (_, k) => {
                const index = this.parts.length + 1 + k;
                return { index, name: defaultPartName(index), color: free[k % Math.max(1, free.length)] ?? PART_COLORS[index % PART_COLORS.length], text: k === 0 ? t('studio.add.ghostOnTop', { name: first }) : t('studio.add.ghostLater') };
            });
        },
        /**
         * The words under the ruler (81.png): your part's lines (else the tune's), where they're sung,
         * with a word's start borrowed from the part that sang it ("A-" for an alto coming in on "maz-").
         */
        laneLines() {
            const template = this.project.template;
            if (!template) return [];
            const source = this.focusPart ? templatePart(this.focusPart) : template.parts.find((p) => p.melody) ?? template.parts[0];
            const lines = this.focusPart && this.focusLines.length ? this.focusLines : source ? lyricLines(source, template.parts) : [];
            return lines.map((line) => ({ startSeconds: line.startBeat * this.beatSeconds, endSeconds: line.endBeat * this.beatSeconds, text: joinSyllables(line.syllables) }));
        },
        /** Who's in it (a friend's project, or one friends sing in): for the side panel. */
        people() {
            const me = account.me?.name;
            const names = [this.project.shared?.owner?.name ?? me, ...this.parts.map((p) => p.createdBy?.name)].filter(Boolean);
            return [...new Set(names)].map((name) => ({ name, you: name === me }));
        },
        initial() {
            return (account.me?.name || t('studio.me')).slice(0, 1).toUpperCase();
        },
        /** Why the project can't be changed (a downgrade, a Plus song), or null. */
        locked() {
            return lockReason(this.project);
        },
        /** The parts with a take, for Share just my part. */
        sharedParts() {
            return this.parts.filter(hasTake).map((p) => ({ id: p.id, name: this.nameOf(p), color: paint(p.color) }));
        },
        /** The parts a practice track can be made for. */
        exportParts() {
            return this.parts.map((p) => ({ id: p.id, name: this.nameOf(p), muted: !!p.muted, sampleRate: hasTake(p) ? p.sampleRate : null }));
        },
        meta() {
            const p = this.project;
            return [p.key ? t('studio.meta.key', { key: keyLabel(p.key) }) : null, p.bpm ? t('studio.meta.bpm', { bpm: p.bpm }) : null, p.timeSignature].filter(Boolean).join(' · ');
        },
        credit() {
            const credits = this.project.template?.credits;
            return credits?.arrangement ? t('studio.arrangementCredit', { name: credits.arrangement }) : '';
        },
        focusNoteNames() {
            const part = this.focusPart && templatePart(this.focusPart);
            if (!part) return '';
            const phrase = this.phraseList[this.practice.phrase ?? -1];
            const notes = part.guide.notes.filter((n) => !phrase || (n.beat * this.beatSeconds >= phrase.startSeconds - 0.01 && n.beat * this.beatSeconds < phrase.endSeconds));
            return t('studio.notesOf', { name: this.nameOf(this.focusPart), notes: notes.map((n) => midiName(n.pitch + (this.project.transpose || 0))).join(', ') });
        },
        /** The desktop lyrics lane only shows when words have room. */
        /** A song's first part without a take, in order: what Record records next. */
        nextToRecord() {
            // The song's suggested order (as Next says after a take); in a friend's song, of your own parts.
            return this.isTemplate ? nextSlot(this.parts.filter((p) => sessionStore.mine(p)), this.project.template) : null;
        },
        /** Sing together: on projects that can go to the account (not a beta or a lesson's song). */
        canTogether() {
            return !this.project.beta && !this.project.course && !this.project.shared?.removed;
        },
        togetherLabel() {
            const names = [...new Set(this.parts.filter((p) => p.createdBy && p.createdBy.id !== account.me?.id).map((p) => p.createdBy.name))];
            if (this.project.shared?.owner?.name) names.unshift(this.project.shared.owner.name);
            return names.length ? t('together.with', { names: formatList(names) }) : t('studio.justYou');
        },
        /** The part a beta singer is recording, practised in the studio (its notes on its row). */
        rowFocus() {
            return this.project?.beta ? this.practice?.focus ?? null : null;
        },
        /** The part Record records: the one its circle picked, else the next of yours without a take. */
        picked() {
            if (this.pickId === NONE) return null;
            const chosen = this.parts.find((p) => p.id === this.pickId && sessionStore.mine(p));
            return chosen ?? this.nextToRecord;
        },
        /** A song with its track unpicked (Hillel: a second tap clears it): Record waits for a pick. */
        noTrack() {
            return this.pickId === NONE && this.isTemplate && !this.recording && !this.betaPart;
        },
        /** "none recorded by you yet", "2 recorded by you", "all recorded by you". */
        yoursSummary() {
            const yours = this.parts.filter((p) => hasTake(p) && sessionStore.mine(p)).length;
            if (!yours) return t('studio.summary.noneYet');
            return yours === this.parts.length ? t('studio.summary.allYours') : t('studio.summary.yours', { count: yours });
        },
        playheadShare() {
            return Math.max(0, Math.min(1, Math.max(0, this.position) / this.length));
        },
        recordLabel() {
            if (this.recording) return t('studio.stopRecording');
            if (this.betaPart) return t('studio.recordNamed', { name: this.nameOf(this.betaPart) });
            if (this.noTrack) return t('studio.pickFirst');
            const part = this.picked;
            if (part) return hasTake(part) ? t('studio.rerecordNamed', { name: this.nameOf(part) }) : t('studio.recordNamed', { name: this.nameOf(part) });
            // The label names the voice (53.png): Record Melody, then Record Harmony 1.
            return this.isTemplate ? t('studio.recordNew') : t('studio.recordNamed', { name: defaultPartName(this.parts.length) });
        },
        barSeconds() {
            return this.project.bpm ? (beatsPerBar(this.project.timeSignature) * 60) / this.project.bpm : null;
        },
        /** The timeline fits the song (117.png): to its last bar line, so the waveforms fill the lanes; eight bars while it's empty. */
        length() {
            const end = Math.max(this.contentLength, this.recording ? this.position + 1 : 0);
            if (!this.barSeconds) return Math.max(10, Math.ceil(end / 5) * 5);
            const pickup = this.pickup * this.beatSeconds;
            if (end <= 0) return pickup + this.barSeconds * 8;
            return pickup + Math.max(1, Math.ceil((end - pickup) / this.barSeconds - 1e-6)) * this.barSeconds;
        },
        marks() {
            const marks = [];
            if (this.barSeconds) {
                const bars = this.length / this.barSeconds;
                const room = this.box.width || 800;
                const step = [1, 2, 4, 8].find((n) => (room / bars) * n >= 30) ?? 8;
                // Bar 1 starts after the pickup beats.
                const first = this.pickup * this.beatSeconds;
                for (let bar = 0; first + bar * this.barSeconds < this.length; bar += step) {
                    marks.push({ at: first + bar * this.barSeconds, label: bar + 1 });
                }
            } else {
                for (let s = 0; s < this.length; s += 5) {
                    marks.push({ at: s, label: formatTime(s) });
                }
            }
            return marks;
        },
        barBeat() {
            if (!this.project.bpm || this.position < 0) return null;
            const beat = 60 / this.project.bpm;
            const perBar = beatsPerBar(this.project.timeSignature);
            const total = Math.floor(this.position / beat + 1e-6) - this.pickup;
            if (total < 0) return t('studio.pickup');
            return t('studio.barBeat', { bar: Math.floor(total / perBar) + 1, beat: (total % perBar) + 1 });
        },
        countInLabel() {
            return t('studio.countInLabel', { count: this.project.countInBars });
        },
        exportName() {
            const arranger = this.project.template?.credits?.arrangement?.replace(/\s*\(draft\)$/, '');
            return arranger ? t('studio.exportName', { title: shownTitle(this.project), arranger }) : shownTitle(this.project);
        },
        micLabel() {
            if (!audio.mic) return t('studio.menu.mic');
            return audio.mic.label.replace(/^Default - /, '').replace(/\s*\([0-9a-f]{4}:[0-9a-f]{4}\)$/i, '');
        },
        /** "Alto: Sarah K." for each real voice playing, for the export's credits. */
        voiceCredits() {
            // Only voices in the mix: a muted (or not soloed) part isn't heard, so isn't credited.
            return this.parts.filter((p) => !hasTake(p) && this.isAudible(p) && this.guideVoices[p.id]?.kind === 'real' && this.guideVoices[p.id].credit).map((p) => t('export.credit', { part: this.nameOf(p), name: this.guideVoices[p.id].credit }));
        },
        /** A beta project's part: the one being recorded for Hillel. */
        betaPart() {
            return this.project?.beta ? this.parts.find((p) => p.templatePartKey === this.project.beta.partKey) ?? null : null;
        },
        /**
         * The beta part's current take, from the song's start (what Hillel's review lines up with):
         * a take recorded from later, or nudged, is padded or cut. Reads the part's peaks, which
         * every new take (and its undo) replaces, so it follows "Record again".
         */
        /** The beta part's lyrics, for naming each phrase in the report by its words. */
        /** Play only when there's something to hear: a take, or a song's guides (Hillel: no dead Play). */
        somethingToPlay() {
            return this.isTemplate || this.parts.some(hasTake);
        },
        betaSyllables() {
            const part = this.betaPart && templatePart(this.betaPart);
            if (!part) return [];
            return lyricLines(part, this.project.template.parts.filter((p) => p !== part)).flatMap((line) => line.syllables);
        },
        betaTake() {
            const part = this.betaPart;
            if (!part?.peaks) return null;
            return alignedTake(sessionStore.takeOf(part), part);
        },
        /** The phrase Hillel's retake note is pinned to. */
        /** The phrase looping in the studio (its index), or null. */
        loopedPhrase() {
            return this.practice?.loop && this.practice.phrase !== null && this.practice.phrase !== undefined ? this.practice.phrase : null;
        },
        notePhrase() {
            const beat = this.project?.beta?.noteBeat;
            if (beat === null || beat === undefined) return null;
            const index = this.phraseList.findIndex((p) => beat >= p.startBeat && beat < p.endBeat);
            return index >= 0 ? index : null;
        },
        tellContext() {
            const template = this.project?.template;
            if (!template) return {};
            return { templateId: template.id, partKey: this.project.beta?.partKey ?? this.focusPart?.templatePartKey ?? null, beat: Math.max(0, Math.round((this.position / this.beatSeconds) * 4) / 4) };
        },
        /** The part being recorded, named in the person's language. */
        liveName() {
            const target = this.parts.find((p) => p.id === this.rerecordId);
            return target ? this.nameOf(target) : this.live?.name ?? '';
        },
        livePeaks() {
            return this.live ? this.live.peaks.subarray(0, this.live.count) : null;
        },
    },
    async created() {
        await sessionStore.openSession(this.id);
        sessionStore.setScreen('studio');
        // Home's More menu: Share or Invite someone to sing opens here, on its sheet.
        const sheet = new URLSearchParams(location.search).get('sheet');
        if (sheet === 'share' || sheet === 'together') {
            history.replaceState(history.state, '', location.pathname);
            this.dialog = sheet;
        }
        if (this.project?.beta) loadBar(); // the bar a take must meet, as it is today
        // Opened from My parts' Practise phrase N: that phrase looped, once.
        if (this.project?.beta?.loopOnOpen) {
            if (this.notePhrase !== null) this.loopPhrase(this.notePhrase);
            saveProject({ ...toRaw(this.project), beta: { ...toRaw(this.project.beta), loopOnOpen: false } }).catch((error) => console.warn('Not kept', error));
        }
        this.$nextTick(() => this.measureBox());
        // A real voice may have arrived since the catalogue was last loaded.
        if (this.isTemplate) sessionStore.refreshVoices();
    },
    watch: {
        // Opened again with another device's change: a dialog shows the part as it now is (or closes).
        reloads() {
            if (!this.dialogPart) return;
            this.dialogPart = session.parts.find((p) => p.id === this.dialogPart.id) ?? null;
            if (!this.dialogPart && ['part', 'trim'].includes(this.dialog)) this.dialog = null;
        },
        guidesVersion() {
            this.announceVoices();
        },
        playing(now) {
            if (!now && this.hearing) this.stopHearing(); // played to the end
        },
        /**
         * Hillel: "the first track should set the length of the song. when recording future tracks
         * recording should stop (and playhead reset) when reaching the end of the first track". A
         * song's template sets it for a song; nothing does while the first track is being recorded.
         */
        position(now) {
            if (this.recording && !this.busy && this.songEnd && now >= this.songEnd + 0.3) this.stopAtEnd();
        },
    },
    mounted() {
        this.onKey = (event) => {
            if (this.dialog || event.metaKey || event.ctrlKey || ['INPUT', 'SELECT', 'TEXTAREA'].includes(event.target.tagName)) return;
            const key = event.key.toLowerCase();
            if (event.code === 'Space') {
                event.preventDefault();
                this.recording ? this.recordOrStop() : togglePlay();
            } else if (key === 'r' && prefs.shortcuts) {
                this.transportRecord();
            } else if (event.key === 'Escape' && this.loopedPhrase !== null) {
                this.endLoop(); // Escape stops a loop, as Stop looping does
            }
        };
        document.addEventListener('keydown', this.onKey);
        this.onOnline = () => this.isTemplate && sessionStore.refreshVoices();
        window.addEventListener('online', this.onOnline);
        this.resize = new ResizeObserver(() => this.measureBox());
        this.onInterrupt = (reason) => this.interrupted(reason);
        engine.onInterrupt = this.onInterrupt;
    },
    updated() {
        if (this.$refs.scroller && !this.observing) {
            this.resize.observe(this.$refs.scroller);
            this.observing = true;
            this.measureBox();
        }
    },
    beforeUnmount() {
        this.stopTake();
        document.removeEventListener('keydown', this.onKey);
        window.removeEventListener('online', this.onOnline);
        this.resize.disconnect();
        this.leaving = true;
        // Into Sing for this project keeps the session (its takes and guides) open.
        if (session.openedId === this.id && location.pathname !== `/p/${this.id}/sing`) sessionStore.closeSession();
        if (engine.onInterrupt === this.onInterrupt) engine.onInterrupt = null;
        closeMic(); // opened for this session's recordings; the mic light goes out on leaving
    },
    methods: {
        navigate,
        nameOf(part) {
            return slotName(part, this.project?.template);
        },
        /** The end of the song reached while recording: the take kept, and back to the start. */
        async stopAtEnd() {
            await this.finishRecording();
            if (!this.recording) this.position = 0;
        },
        startRename(part) {
            this.renaming = part.id;
            this.$nextTick(() => document.querySelector('[data-rename-input]')?.select());
        },
        /** Pan as a value: C for the middle, L 30 or R 30 either side. */
        panLabel(pan) {
            const amount = Math.round(Math.abs(pan) * 100);
            return amount === 0 ? t('studio.row.panCentre') : t(pan < 0 ? PAN_SIDE.left : PAN_SIDE.right, { amount: formatNumber(amount) });
        },
        /** A track's name, edited in its row: Enter or leaving the field saves, Escape keeps the old one. */
        renameTo(part, name) {
            this.renaming = null;
            const kept = name.trim();
            if (kept && kept !== this.nameOf(part)) {
                this.update(part, { name: kept });
                count('track_rename');
            }
        },
        /** A row's letter: the first of its name (S, A, T, B for a song's parts). */
        letterOf(part) {
            return (this.nameOf(part).trim()[0] ?? '?').toUpperCase();
        },
        /** Who sings a row: You, a friend's name, or the guide (a real voice, the practice voice). */
        tagsOf(part) {
            if (this.recording && this.rerecordId === part.id) return [{ text: t('studio.row.recording'), tone: 'rec' }];
            const friend = part.createdBy && part.createdBy.id !== account.me?.id ? part.createdBy.name : null;
            if (!hasTake(part)) return [...(friend ? [{ text: friend, tone: 'friend' }] : []), { text: this.badge(part), tone: 'guide' }];
            // A lesson's voices kept as recorded parts (Keep the finale, courses-harmony): said as such, not "Your take".
            if (part.fromLesson) return [{ text: t('studio.badge.fromLesson'), tone: 'guide' }];
            return [{ text: friend ?? t('studio.badge.yourTake'), tone: friend ? 'friend' : 'you' }];
        },
        /** "Recorded 1 hour ago", under a track's name (81.png). */
        whenOf(part) {
            const at = part.recordedAt ?? null;
            return hasTake(part) && at ? t('studio.row.recordedAgo', { ago: timeAgo(at) }) : '';
        },
        toggleRow(part) {
            this.openRow = this.openRow === part.id ? null : part.id;
            if (this.openRow) this.loadAccountTakes(part);
        },
        /** The account's kept takes for a part (another device's among them), when it has the project. */
        async loadAccountTakes(part) {
            if (!account.me || !part.version || staysOnDevice(this.project)) return;
            try {
                this.accountTakes = { ...this.accountTakes, [part.id]: (await api.parts.takes.list(part.id)).data };
            } catch (error) {
                if (error?.code !== 'offline') console.warn("The part's takes did not load", error);
            }
        },
        /**
         * A part's earlier takes, newest first (Hillel: "keep all takes so users can swap to try. You'd
         * see the takes by expanding the row"): the ones kept on this device, then any only the account
         * has (recorded on another device).
         */
        takesOf(part) {
            const local = (part.keptTakes ?? []).map((k) => ({ id: k.takeId, at: k.recordedAt, seconds: k.sampleRate ? k.frames / k.sampleRate : 0, pitch: k.score?.pitchPercent ?? null, synced: k.synced, here: true }));
            const known = new Set([part.takeId, ...local.map((k) => k.id)]);
            const elsewhere = (this.accountTakes[part.id] ?? [])
                .filter((item) => !item.current && !known.has(item.take.id))
                .map((item) => ({ id: item.take.id, at: Date.parse(item.take.createdAt), seconds: item.durationSeconds, pitch: item.pitchPercent, synced: true, here: false, url: item.take.url, ready: item.take.status === 'ready' }));
            return [...local, ...elsewhere].sort((a, b) => (b.at ?? 0) - (a.at ?? 0));
        },
        takeLine(take) {
            return [take.at ? timeAgo(take.at) : '', formatTime(take.seconds), take.pitch === null || take.pitch === undefined ? '' : t('studio.takes.onPitch', { percent: take.pitch })].filter(Boolean).join(' · ');
        },
        /** A kept take on its own (never the mix): its file from the device, or the account's link. */
        async hearTake(part, take) {
            const again = this.hearingTake?.id === take.id;
            this.stopTake();
            if (again) return;
            let src = take.url ?? null;
            if (take.here) {
                const samples = await db.getAudio(keptKey(take.id));
                const entry = part.keptTakes.find((k) => k.takeId === take.id);
                if (!samples || !entry) return;
                src = URL.createObjectURL(encodeWav([samples], entry.sampleRate));
            }
            if (!src) return;
            const audioEl = new Audio(src);
            this.hearingTake = { id: take.id, audio: audioEl, url: take.here ? src : null };
            audioEl.addEventListener('ended', () => this.hearingTake?.id === take.id && this.stopTake());
            audioEl.play().catch((error) => console.warn('The take did not play', error));
        },
        stopTake() {
            if (!this.hearingTake) return;
            this.hearingTake.audio.pause();
            if (this.hearingTake.url) URL.revokeObjectURL(this.hearingTake.url);
            this.hearingTake = null;
        },
        /** A kept take as the part's own again; the one it replaces is kept. Undo swaps back. */
        async useTakeOf(part, take) {
            this.stopTake();
            count('take_use');
            if (take.here) {
                const undo = await sessionStore.useTake(part, take.id);
                if (undo) toast(t('studio.takes.using', { name: this.nameOf(part) }), { actions: [{ label: t('common.undo'), run: undo }] });
                return;
            }
            // Only the account has it: it switches there, and comes down with the next sync.
            try {
                await api.parts.takes.use(part.id, take.id);
                toast(t('studio.takes.usingElsewhere'));
                syncNow();
            } catch (error) {
                toast(error?.code === 'take_not_ready' ? t('studio.takes.notReady') : t('studio.takes.failed'), { tone: 'error' });
            }
        },
        /** A kept take gone: from this device, and to the account's Recently deleted when it has it. */
        async deleteTakeOf(part, take) {
            const sure = await confirmAction({ title: t('studio.takes.deleteTitle'), body: take.synced ? t('studio.takes.deleteBin') : t('studio.takes.deleteForGood'), action: t('studio.takes.delete') });
            if (!sure) return;
            if (this.hearingTake?.id === take.id) this.stopTake();
            if (take.here) await sessionStore.dropTake(part, take.id);
            if (take.synced && account.me && !staysOnDevice(this.project)) {
                try {
                    await api.takes.delete(take.id);
                } catch (error) {
                    console.warn('The take stays on the account until it can be deleted', error);
                }
                this.loadAccountTakes(part);
            }
        },
        /** A tap on a lane moves the playhead there (the lanes line up with the ruler). */
        laneSeek(event) {
            if (event.target.closest('button')) return;
            this.seekFrom(event, event.currentTarget);
        },
        nudge(part, seconds) {
            const next = Math.round(((part.nudgeSeconds ?? 0) + seconds) * 1000) / 1000;
            this.update(part, { nudgeSeconds: Math.max(-0.2, Math.min(0.2, next)) });
        },
        nudgeLabel(part) {
            const ms = Math.round((part.nudgeSeconds ?? 0) * 1000);
            return ms === 0 ? t('part.asRecorded') : ms < 0 ? t('part.earlier', { ms: -ms }) : t('part.later', { ms });
        },
        /** Headphones on: recording doesn't ask (for this visit); off, it asks again. */
        setHeadphones(on) {
            this.headphones = on;
            if (on) tabStorage.set('stackapella.headphones', '1');
            else tabStorage.remove('stackapella.headphones');
        },
        setCountIn(bars) {
            this.project.countInBars = bars;
            sessionStore.persist(async () => {});
        },
        setClick(on) {
            this.project.metronome = on;
            sessionStore.persist(async () => {});
        },
        /** A slot's badge: Recorded, Real voice (with its singer), the practice voice in your key, or Guide. */
        badge(part) {
            if (hasTake(part)) return t('studio.badge.recorded');
            const voice = this.guideVoices[part.id];
            if (voice?.why === 'key') return t('studio.badge.practiceInKey');
            if (voice?.kind !== 'real') return t('studio.badge.guide');
            return voice.credit ? t('studio.badge.realVoiceBy', { name: voice.credit }) : t('studio.badge.realVoice');
        },
        async chooseVoice(part, voice) {
            await sessionStore.setVoice(part, voice);
        },
        /**
         * Once, when real voices reach a project that had the synth (docs/beta-singers-plan.md,
         * recommendation 2): one note for all of them, with the way back to the practice voice. A new
         * project starts with its voices already seen.
         */
        announceVoices() {
            const practice = this.practice;
            if (!practice) return;
            const seen = new Set(practice.voicesSeen ?? []);
            const fresh = this.parts.filter((part) => this.guideVoices[part.id]?.kind === 'real' && !seen.has(this.guideVoices[part.id].url));
            if (!fresh.length) return;
            fresh.forEach((part) => seen.add(this.guideVoices[part.id].url));
            practice.voicesSeen = [...seen];
            sessionStore.savePractice();
            if (practice.seedVoices) {
                practice.seedVoices = false;
                return;
            }
            const names = formatList(fresh.map((part) => this.nameOf(part)));
            toast(t('studio.realVoicesNew', { parts: names, count: fresh.length }), {
                seconds: 12,
                actions: [{ label: t('studio.usePracticeVoice'), run: () => this.dialogFor(fresh[0]) }],
            });
        },
        /** The part's settings, where its guide voice is chosen. */
        dialogFor(part) {
            this.openPart(part);
        },
        measureBox() {
            const ruler = this.$refs.ruler;
            const scroller = this.$refs.scroller;
            if (!ruler || !scroller) return;
            const r = ruler.getBoundingClientRect();
            const s = scroller.getBoundingClientRect();
            this.box = { left: r.left - s.left, width: r.width };
        },
        /**
         * Back to where the studio was opened from (the home, Sing, My parts, a lesson); opened cold,
         * a beta project goes to My parts, a lesson's song to its lesson, anything else home.
         */
        leave() {
            if (this.recording) return;
            goBack(this.project?.beta ? '/beta' : this.project?.course ? `/learn/${this.project.course.lessonId}` : '/');
        },
        async addMine(partKey) {
            const part = await sessionStore.addMySlot(partKey);
            if (part) toast(t('together.added', { name: this.nameOf(part) }));
        },
        menu(name, event) {
            event.target.closest('details').open = false;
            if (name === 'polish') {
                this.toggleHearPolished();
            } else if (name === 'sing') {
                navigate('/p/' + this.id + '/sing');
            } else if (name === 'mic' || name === 'timing') {
                this.openAudioDialog(name);
            } else {
                this.dialog = name;
            }
        },
        /** Starts audio inside the click (Safari needs that before the mic opens), then the dialog. */
        openAudioDialog(name) {
            unlock().catch((error) => console.info('Audio waits for a tap', error));
            this.dialog = name;
        },
        isAudible(part) {
            return this.anySolo ? part.solo : !part.muted;
        },
        update(part, fields) {
            sessionStore.updatePart(part, fields);
        },
        async toggleHearPolished() {
            const on = !prefs.hearPolished;
            unlock().catch((error) => console.info('Audio waits for a tap', error));
            await sessionStore.setHearPolished(on);
            toast(on ? t('polish.hearOn') : t('polish.hearOff'));
        },
        // ---- Trimming (feature 17): every change plays at once; one Undo when the sheet closes ----
        /** Harmonise this: the take as the tune, in the creator, with the other parts suggested under it. */
        harmonise(part) {
            navigate(`/create?from=${this.project.id}&part=${part.id}&harmonise=1`);
        },
        openTrim(part) {
            this.dialogPart = part;
            this.trimBefore = { start: part.trimStartFrames ?? 0, end: part.trimEndFrames ?? 0 };
            this.dialog = 'trim';
        },
        trim(part, trims) {
            sessionStore.setTrims(part, trims).catch((error) => console.error('The trim was not saved', error));
        },
        /** Plays across an edge with the other parts: from a second before the start, or three before the end. */
        hearEdge(part, edge) {
            const span = heardSpan(part);
            sessionStore.playFrom(edge === 'start' ? span.from - 1 : span.to - 3);
        },
        stopPlayback() {
            sessionStore.stopPlayback();
        },
        closeTrim(part) {
            this.dialog = null;
            if (this.playing) sessionStore.stopPlayback();
            const before = this.trimBefore;
            if (!before || (before.start === (part.trimStartFrames ?? 0) && before.end === (part.trimEndFrames ?? 0))) return;
            toast(t('trim.done', { part: this.nameOf(part) }), { seconds: 10, actions: [{ label: t('common.undo'), run: () => sessionStore.setTrims(part, before) }] });
        },
        async lineUp(mode) {
            this.dialog = null;
            const undo = await sessionStore.lineUpParts(mode);
            toast(undo ? t(mode === 'start' ? tk('trim.linedStart') : tk('trim.linedEnd')) : t('trim.nothingToLineUp'), undo ? { seconds: 10, actions: [{ label: t('common.undo'), run: undo }] } : {});
        },
        toggle(part, flag) {
            sessionStore.updatePart(part, { [flag]: !part[flag] });
        },
        /** The song's name, set in place on the title (blank shows "Untitled song" in lists). */
        async rename(title) {
            this.project.title = title.trim();
            await sessionStore.persist(async () => {});
        },
        async saveSettings({ backingStyle = null, ...fields }) {
            this.dialog = null; // one sheet at a time: a question about the singers replaces the settings
            if (backingStyle && backingStyle !== styleOf(this.practice?.chordsSound)) sessionStore.setChordsSound(backingStyle);
            const keyChanged = this.isTemplate && (fields.transpose ?? 0) !== (this.project.transpose || 0);
            const singers = this.isTemplate && fields.singers !== this.slotCount ? fields.singers : null;
            if (singers && !(await this.confirmSingers(singers))) {
                fields.singers = this.slotCount;
            }
            const losingTakes = singers && this.losing(singers).length > 0;
            Object.assign(this.project, fields);
            this.dialog = null;
            // A plain project's settings are where the next new project starts.
            if (!this.isTemplate) rememberSettings(this.project);
            await sessionStore.persist(async () => {});
            if (keyChanged) await this.changeKey();
            if (singers && fields.singers === singers) {
                const undo = await sessionStore.changeSingers(singers);
                toast(t('studio.nowForSingers', { count: singers }), losingTakes && undo ? { seconds: 10, actions: [{ label: t('common.undo'), run: undo }] } : {});
            }
        },
        /** The recorded parts a song for `singers` voices would drop. */
        losing(singers) {
            const keep = new Set(partsFor(this.project.template, singers).map((p) => p.key));
            return this.parts.filter((p) => p.templatePartKey && !keep.has(p.templatePartKey) && hasTake(p));
        },
        /** Fewer singers drops recorded parts: asked first (with Undo after). */
        async confirmSingers(singers) {
            const losing = this.losing(singers);
            if (!losing.length) return true;
            return confirmAction({
                title: t('confirm.reduceSingers.title', { count: singers }),
                body: t('confirm.reduceSingers.body', { parts: formatList(losing.map((p) => this.nameOf(p))), takes: losing.length }),
                action: t('confirm.reduceSingers.action', { count: singers }),
            });
        },
        async changeKey() {
            if (this.progress.done) {
                toast(t('studio.keyChangedTakesStay'), { seconds: 10 });
            }
            await sessionStore.changeKey();
        },
        async useRange({ transpose, partId }) {
            this.dialog = null;
            this.project.transpose = transpose;
            await this.changeKey();
            const part = this.parts.find((p) => p.templatePartKey === partId);
            if (part) this.practise(part);
        },
        toggleMetronome() {
            this.project.metronome = !this.project.metronome;
            sessionStore.persist(async () => {});
        },
        cycleCountIn() {
            this.project.countInBars = (this.project.countInBars + 1) % 3;
            sessionStore.persist(async () => {});
        },
        openPart(part) {
            this.dialogPart = part;
            this.dialog = 'part';
        },

        // ---- Practice (template projects) ----

        practise(part) {
            sessionStore.practise(part);
            this.$nextTick(() => this.measureBox());
        },
        async toggleLoop() {
            if (!(await sessionStore.toggleLoop())) {
                toast(t('practice.pickPhraseFirst'));
            }
        },
        /** A beta take records its part; a song's next unrecorded part comes next; otherwise a new part. */
        /** The circle: picks a track to record, and a second tap on the picked one clears it. */
        togglePick(part) {
            this.pickId = this.picked?.id === part.id ? NONE : part.id;
        },
        transportRecord() {
            if (this.noTrack) return;
            if (this.recording) {
                this.recordOrStop();
            } else if (this.betaPart) {
                this.recordFocus();
            } else if (this.picked) {
                // A part with your take records again from where that take starts; one without, from the playhead.
                if (hasTake(this.picked)) this.rerecord(this.picked);
                else {
                    this.rerecordId = this.picked.id;
                    this.recordOrStop();
                }
            } else {
                this.recordOrStop();
            }
        },
        /** A song's part, practised in Sing (which the studio's practice bar used to do). */
        singPart(part) {
            sessionStore.practise(part);
            navigate(`/p/${this.id}/sing`);
        },
        recordFocus() {
            if (this.recording) {
                this.recordOrStop();
                return;
            }
            // A beta project always records the part Hillel asked for.
            const part = this.betaPart ?? this.focusPart;
            if (!part) return;
            this.rerecordId = part.id;
            this.position = 0; // a template part is sung from the start, pickup included
            this.recordOrStop();
        },
        rowPointer(event, part) {
            // Phones: a tap opens the part. Larger screens: a click on the timeline moves the playhead.
            if (window.matchMedia('(min-width: 768px)').matches) {
                this.seekFrom(event, this.$refs.ruler);
            } else {
                this.openPart(part);
            }
        },

        // ---- Transport ----

        seekFrom(event, element = event.currentTarget) {
            const rect = element.getBoundingClientRect();
            sessionStore.seek(Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)) * this.length);
        },
        async stopOrRewind() {
            if (this.recording) {
                await this.finishRecording();
            } else if (this.playing) {
                await sessionStore.stopPlayback();
            } else {
                this.position = 0;
            }
        },

        // ---- Recording ----

        // ---- Beta: check and send ----

        openReport() {
            if (!this.betaTake) return;
            this.dialog = 'betaReport';
        },
        async closeReport() {
            this.dialog = null;
            if (this.hearing) await this.stopHearing();
        },
        async stopHearing() {
            this.hearing = false;
            if (this.playing) await sessionStore.stopPlayback();
            if (this.betaPart) this.practise(this.betaPart); // a beta take: back to practising its part (a song's own focus stays)
        },
        /** The take in the mix with the other parts, at the levels learners hear a guide at. */
        async hearAsLearners() {
            if (this.hearing) {
                await this.stopHearing();
                return;
            }
            sessionStore.practise(null);
            this.position = 0;
            this.hearing = true;
            await togglePlay();
            // As a learner hears it: levelled as it will be published, with every other part's
            // guide at full level, whatever this project's mutes and solos.
            const take = sessionStore.takeOf(this.betaPart);
            const gain = guideGain(rmsDbfs(take), 0, peakDbfs(take));
            for (const slot of this.parts.filter((p) => p.templatePartKey)) {
                const mine = slot === this.betaPart;
                for (const [id, volume, audible] of [[slot.id, gain, mine], [guideId(slot.id), 1, !mine]]) {
                    const voice = engine.parts.get(id);
                    if (voice) engine.updatePart(id, { volume, pan: 0, audible, offsetSeconds: voice.offsetSeconds });
                }
            }
        },
        /** Sends the take before this one instead: it comes back, and the report checks it. */
        async usePreviousTake() {
            const undo = this.previousTake;
            this.previousTake = null;
            await this.closeReport();
            await undo?.();
            this.$nextTick(() => this.openReport());
        },
        async recordAgain() {
            await this.closeReport();
            this.recordFocus();
        },
        async loopFromReport(index) {
            await this.closeReport();
            this.loopPhrase(index);
        },
        /** Loops phrase `index`; pressed again while it loops, stops looping (the song carries on). */
        async loopPhrase(index) {
            if (this.loopedPhrase === index) {
                await this.endLoop();
                return;
            }
            this.practise(this.betaPart);
            sessionStore.selectPhrase(index);
            if (!this.practice.loop) await this.toggleLoop();
        },
        async endLoop() {
            await sessionStore.endLoop();
        },
        /** Keeps the take on the device to send, then sends what's waiting, and goes back to My parts. */
        async sendTake({ note, scores }) {
            this.sending = true;
            try {
                if (this.hearing) await this.stopHearing();
                const template = this.project.template;
                await enqueue({
                    assignmentId: this.project.beta.assignmentId,
                    templateId: template.id,
                    partKey: this.project.beta.partKey,
                    templateVersion: this.project.beta.templateVersion,
                    key: template.key,
                    samples: this.betaTake,
                    sampleRate: this.betaPart.sampleRate,
                    // As it was when the take was recorded, not now (a reload, another mic).
                    latencyFrames: this.betaPart.recordedWith?.latencyFrames ?? audio.latencyFrames ?? 0,
                    device: describeDevice(),
                    mic: this.betaPart.recordedWith?.mic ?? audio.mic?.label ?? '',
                    scores,
                    note,
                });
                this.dialog = null;
                const cellular = navigator.connection?.type === 'cellular';
                toast(t(cellular ? tk('beta.send.queuedCellular') : tk('beta.send.queued')), { seconds: 8 });
                sendAll().catch((error) => console.error('Sending takes failed', error)); // carries on in the background
                navigate('/beta');
            } catch (error) {
                console.error('The take could not be kept to send', error);
                toast(t('beta.send.keepFailed'), { tone: 'error', seconds: 12 });
            } finally {
                this.sending = false;
            }
        },
        /** A call, the screen locking, the mic going away: stop, and keep what was sung. */
        async interrupted() {
            if (this.recording && !this.busy) {
                await this.finishRecording({ interrupted: true });
            } else if (this.playing && !this.recording) {
                await sessionStore.stopPlayback();
            }
        },
        /** Stops recording, and says what happened, with Undo. */
        async finishRecording(options) {
            const outcome = await sessionStore.finishRecording(options);
            if (outcome.kind === 'short') {
                if (outcome.interrupted) toast(t('studio.stoppedBeforeStart'));
                return;
            }
            if (outcome.kind === 'silent') {
                toast(t('studio.silentTake'), { tone: 'error', seconds: 12 });
                return;
            }
            // After an interruption, one message says where the take stops, with the usual actions.
            const say = (message, options = {}) => toast(outcome.interrupted ? t('studio.takeKept', { time: formatTime(outcome.upTo) }) : message, { ...options, seconds: outcome.interrupted ? 12 : options.seconds });
            const previous = outcome.kind === 'slot' && !outcome.first;
            const undo = { label: previous ? t('studio.takes.undoPrevious') : t('common.undo'), run: () => (previous && count('take_undo'), outcome.undo()) };
            const name = this.nameOf(outcome.part);
            if (this.project.beta && outcome.part === this.betaPart) {
                // A take for Hillel: no next part to suggest, only the check before sending. The
                // take it replaced stays at hand, so the singer can send whichever was better.
                this.previousTake = outcome.first ? null : outcome.undo;
                say(t('beta.studio.recorded', { name }), { seconds: 10, actions: [undo, { label: t('beta.studio.checkAndSend'), run: () => this.openReport() }] });
            } else if (outcome.kind === 'new') {
                say(t('studio.recorded', { name }), { actions: [undo, { label: t('common.tryAgain'), run: () => outcome.undo().then(() => { this.position = outcome.part.offsetSeconds; this.recordOrStop(); }) }] });
            } else if (outcome.everyPart) {
                say(t('studio.everyPartYou'), { seconds: 10, actions: [undo, { label: t('studio.export'), run: () => (this.dialog = 'export') }] });
            } else if (outcome.next) {
                const next = outcome.next;
                say(t(outcome.first ? tk('studio.recordedNext') : tk('studio.rerecordedNext'), { name, next: this.nameOf(next) }), { seconds: 10, actions: [undo, { label: t('studio.singNamed', { name: this.nameOf(next) }), run: () => this.singPart(next) }] });
            } else {
                say(t(outcome.first ? tk('studio.recorded') : tk('studio.rerecorded'), { name }), { actions: [undo] });
            }
            if (!outcome.interrupted && outcome.gaps) {
                toast(t('studio.gaps'), { tone: 'error' });
            }
        },
        /** A template slot goes back to its guide; Undo brings the take back. */
        async clearTakeOf(part) {
            this.dialog = null;
            const undo = await sessionStore.clearTakeOf(part);
            if (undo) {
                toast(t('studio.takeCleared', { name: this.nameOf(part) }), { actions: [{ label: t('common.undo'), run: undo }] });
            }
        },
        /** Trim's Play trimmed part: the kept stretch, from its start to its end, with the others. */
        hearTrimmed(part) {
            const span = heardSpan(part);
            sessionStore.playFrom(span.from, { to: span.to });
        },
        rerecord(part) {
            this.dialog = null;
            this.rerecordId = part.id;
            this.position = part.offsetSeconds;
            this.recordOrStop();
        },
        async removePart(part) {
            const name = this.nameOf(part);
            this.dialog = null; // one sheet at a time: the question replaces the part's settings
            const sure = await confirmAction({
                title: t('confirm.deletePart.title', { name }),
                body: hasTake(part) ? t('confirm.deletePart.bodyTake') : t('confirm.deletePart.body'),
                action: t('confirm.deletePart.action', { name }),
            });
            if (!sure) return;
            const undo = await sessionStore.dropPart(part);
            toast(t('confirm.deletePart.done', { name }), { seconds: 10, actions: [{ label: t('common.undo'), run: undo }] });
        },

        // ---- Export ----

        async exportMix({ name, format = 'wav', chords = false, room = null, track = null }) {
            this.exporting = true;
            try {
                const comment = this.voiceCredits.length ? t('export.credits', { names: formatList(this.voiceCredits) }) : null;
                let file;
                if (format === 'stems') {
                    const rendered = sessionStore.renderStems();
                    const names = fileNames(rendered.map(({ part }) => `${name} - ${this.nameOf(part)}.wav`));
                    const stems = await Promise.all(rendered.map(async ({ samples, sampleRate }, i) => ({ name: names[i], bytes: new Uint8Array(await encodeWav([samples], sampleRate).arrayBuffer()) })));
                    file = zip(stems);
                } else {
                    const rendered = await sessionStore.renderMix({ chords, room, track });
                    file = format === 'mp3' ? await encodeMp3(rendered.channels, rendered.sampleRate) : encodeWav(rendered.channels, rendered.sampleRate, { comment });
                }
                const url = URL.createObjectURL(file);
                const link = Object.assign(document.createElement('a'), { href: url, download: `${name}.${format === 'stems' ? 'zip' : format}` });
                document.body.append(link);
                link.click();
                link.remove();
                setTimeout(() => URL.revokeObjectURL(url), 60000);
                count('export_done');
                count(format === 'stems' ? 'exp_stems' : track ? 'exp_practice' : 'exp_song'); // by kind: the song, a practice track, every part
                this.dialog = null;
            } catch (error) {
                console.error('Export failed', error);
                toast(t('studio.exportFailed'), { tone: 'error' });
            } finally {
                this.exporting = false;
            }
        },
    },
};
