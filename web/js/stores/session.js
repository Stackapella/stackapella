// The audio session for the open project: loading it, its guides, the mix, the transport,
// practice and recording. Shared by the studio and the Sing screens (docs/singing-ux-plan.md,
// "A shared session store"), so both play, practise and record the same way. It holds no words
// and no dialogs: a screen asks it to act, and words what happened (finishRecording returns an
// outcome rather than showing a message).
//
// One project is open at a time: openSession(id) loads it, closeSession() stops everything.
// Everything audible is scheduled by the engine on the audio clock; the tick loop only reads the
// position for the screen.

import { computed, markRaw, reactive } from 'vue';
import * as db from '../db.js';
import { first } from '../funnel.js';
import { audio, engine, leadSeconds, unlock } from '../audio.js';
import { click, isSilent, peaks as toPeaks } from '../dsp.js';
import { renderGuide } from '../guides.js';
import { bandFor, renderBand, styleOf } from '../band.js';
import { guideKey, loadRecordedGuide, mixVoices, recordingApplies } from '../recorded-guides.js';
import { loadCatalogue, versionsFor } from './catalogue.js';
import { t } from '../i18n.js';
import { BACKING, GUIDE_LEVELS, guideId, hasTake, nextSlot, PRACTICE_DEFAULTS, SPEAKERS_UNDER, trackLevels, voiceLevels, withClicks } from '../practice.js';
import { clearTake, deleteKeptTake, deletePart, makeSlot, openProject, savePart, saveProject, useKeptTake } from './projects.js';
import { renderPart } from '../synth.js';
import { lengthBeats, lyricLines, partsFor, phrases as templatePhrases } from '../templates.js';
import { beatsPerBar, defaultPartName, PART_COLORS, transposeKey } from '../theme.js';
import { toast } from '../components/ui.js';
import { earn, practised } from './learning.js';
import { onCloudChange, refreshShared } from './cloud.js';
import { account } from './account.js';
import { mayChangePart } from '../sync.js';
import { FADE_SECONDS, joinPoints, matchGain, soundsDifferent, splice } from '../patch.js';
import { heardSpan, lineUp, trimmedSamples, trimsOf } from '../trim.js';
import { trackPeaks } from '../share.js';
import { compress, DEFAULT_ROOM, impulseResponse, levelGains, limit, polishRender, ROOMS, spread } from '../polish.js';
import { prefs } from './prefs.js';
import { local } from '../storage.js';
import { count } from '../usage.js';
import { chipCountIn } from '../game/chip.js';

export const GUIDE_PEAKS_RATE = 48000; // guide outlines are made at 48 kHz (stores/projects.js)
export const FRAMES_PER_PEAK = 512;

export const session = reactive({
    screen: 'sing', // 'sing' (practising) or 'studio' (mixing): setScreen()
    reloads: 0, // counts openings again with another device's change (screens holding a part re-find it)
    patching: null, // re-singing a phrase: { partId, plan } while it's recorded (prepareResing)
    openedId: null, // the project being opened or open
    project: null,
    parts: [],
    missing: false,
    saving: 0,
    position: 0,
    playing: false,
    recording: false,
    countInBeats: 0, // how many clicks the count-in has
    countIn: null, // the count-in's beat of the bar now (1 to the meter's last), counting up with its click
    recordFrom: 0,
    live: null, // the take being recorded: { peaks, count, color, name }
    rerecordId: null,
    guideHeard: false, // your guide played during the last take (for the boards)
    inReview: false, // Sing's Just recorded is open (Polished applies there)
    reviewing: false, // the review is playing your take with the others (never saved into practice)
    hearing: { off: [], guideLevel: GUIDE_LEVELS.medium, recordGuide: false }, // What you hear (per song on this device, never in the project)
    phase: null, // what the engine is playing: count-in, play, loop, listen, cue, turn
    preparing: false, // guides being rendered
    guidesVersion: 0,
    busy: false,
    // Per slot id, what its guide is: { kind: 'real' | 'synth', credit, offered } (offered: a real
    // voice exists but the synth plays, for `why`: 'key', 'speed', 'version', 'chosen' or 'failed').
    guideVoices: {},
});

// Not reactive: big audio, timers and handles.
const kept = {
    singingFrom: null, // performance.now() when playing or recording began, for the weekly goal
    samples: new Map(), // part id -> its take's samples
    guides: new Map(), // slot id -> rendered guide samples
    guidesKey: null,
    guidesReady: null,
    guidesRate: null,
    guideOffsets: new Map(), // slot id -> where its guide starts, in seconds (a real voice can start late)
    failedVoices: new Set(), // real voices' URLs that didn't load this time (offline): retried when online
    rendering: 0, // renders in flight (an open-time one and a Play-time one can overlap)
    frame: 0,
    wakeLock: null,
    practiceTimer: 0,
    partSaves: new Map(), // part -> its pending save's timer
    reloadAfter: false, // another device changed the open project while it played: open it again on stop
    trimSettle: 0, // a trim drag settling before its take is placed and saved
};

/** True while project `id` is still the open one: async work checks it after every wait. */
function stillOpen(id) {
    return session.openedId === id && !!session.project;
}

Object.assign(session, {
    isTemplate: computed(() => !!session.project?.template),
    practice: computed(() => (session.isTemplate ? session.project.practice : null)),
    focusPart: computed(() => (session.practice?.focus ? session.parts.find((p) => p.id === session.practice.focus) ?? null : null)),
    anySolo: computed(() => session.parts.some((p) => p.solo)),
    // Slow practice plays the whole song slower: the guides, the words, the loops and the clicks.
    speed: computed(() => (session.isTemplate ? session.practice?.speed ?? 1 : 1)),
    tempo: computed(() => (session.project?.bpm || 90) * session.speed),
    beatSeconds: computed(() => 60 / session.tempo),
    pickup: computed(() => session.project?.template?.pickupBeats ?? 0),
    contentLength: computed(() => session.parts.reduce((max, p) => Math.max(max, p.frames > 0 ? heardSpan(p).to : 0, p.guideSeconds ?? 0), 0)),
    sections: computed(() => (session.isTemplate ? session.project.template.sections.map((s) => ({ name: s.name, startSeconds: s.startBeat * session.beatSeconds, endSeconds: s.endBeat * session.beatSeconds })) : [])),
    phraseList: computed(() => (session.isTemplate ? templatePhrases(session.project.template).map((p) => ({ ...p, startSeconds: p.startBeat * session.beatSeconds, endSeconds: p.endBeat * session.beatSeconds })) : [])),
    focusLines: computed(() => {
        const part = session.focusPart && templatePart(session.focusPart);
        if (!part) return [];
        return lyricLines(part, session.project.template.parts).map((line) => ({
            ...line,
            startSeconds: line.startBeat * session.beatSeconds,
            endSeconds: line.endBeat * session.beatSeconds,
            syllables: line.syllables.map((s) => ({ ...s, seconds: s.beat * session.beatSeconds })),
        }));
    }),
    slotCount: computed(() => session.parts.filter((p) => p.templatePartKey).length),
    progress: computed(() => {
        const slots = session.parts.filter((p) => p.templatePartKey);
        return { done: slots.filter(hasTake).length, total: slots.length };
    }),
});

// ---- Opening and closing ------------------------------------------------------------------------

/** Loads project `id` (its parts and takes) and starts rendering its guides. */
// Another device changed the open project: it opens again with their change (after playing or
// recording, never in the middle), so a later save here can't overwrite it.
onCloudChange((changes) => {
    const id = session.openedId;
    if (!id || !changes.some((c) => c.id === id || c.projectId === id)) return;
    if (session.recording || session.playing || session.busy) kept.reloadAfter = true;
    else reloadFromCloud();
});

async function reloadFromCloud() {
    kept.reloadAfter = false;
    if (!session.openedId) return;
    engine.projectId = null; // the takes load again (a plain project's key doesn't change)
    await openSession(session.openedId, { refresh: false });
    session.reloads++; // screens holding a part (a dialog, Just recorded) take the new one
    toast(session.project?.shared ? t('together.updated') : t('cloud.updated'));
}

// A friend's project comes up to date when the app comes back to the front.
document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && session.project?.shared && !session.project.shared.removed) refreshShared(session.openedId);
});

export async function openSession(id, { refresh = true } = {}) {
    closeSession();
    session.openedId = id;
    const opened = await openProject(id);
    if (session.openedId !== id) return; // another project was opened meanwhile
    if (!opened) {
        session.missing = true;
        return;
    }
    session.project = opened.project;
    // A friend's project: what the others sang since, fetched now (it opens again if anything changed).
    if (refresh && opened.project.shared && !opened.project.shared.removed) refreshShared(id);
    session.parts = opened.parts.map((part) => ({ ...part, peaks: part.peaks ? markRaw(part.peaks) : null, guidePeaks: part.guidePeaks ? markRaw(part.guidePeaks) : null }));
    kept.samples = opened.samples;
    if (session.project.template) {
        // withOthers and compare were once saved by the review's listening: they follow the way you sing now.
        session.project.practice = { ...PRACTICE_DEFAULTS, ...session.project.practice, withOthers: false, compare: false };
        // Real voices come from the catalogue: wait a moment for it, then go with what's known.
        await Promise.race([loadCatalogue(), new Promise((resolve) => setTimeout(resolve, 1500))]);
        if (session.openedId !== id) return;
        prepareGuides(engine.unlocked ? engine.sampleRate : GUIDE_PEAKS_RATE);
    }
}

/**
 * Stops playback or recording (a recording in progress is dropped) and forgets the project. Saves
 * still waiting (a slider's, a practice change) are made at once; the promise is theirs.
 */
export function closeSession() {
    const saves = [];
    for (const [part, timer] of kept.partSaves) {
        clearTimeout(timer);
        saves.push(savePart(plainPart(part)));
    }
    kept.partSaves.clear();
    if (kept.practiceTimer || saves.length) {
        clearTimeout(kept.practiceTimer);
        saves.push(persist(async () => {})); // the project too, before it goes
    }
    kept.practiceTimer = 0;
    cancelAnimationFrame(kept.frame);
    if (session.playing) {
        engine.cancelCapture();
        engine.stop();
    }
    kept.wakeLock?.release().catch((error) => console.info('The wake lock was already gone', error));
    Object.assign(session, { openedId: null, project: null, parts: [], missing: false, position: 0, playing: false, recording: false, countIn: null, recordFrom: 0, live: null, rerecordId: null, inReview: false, reviewing: false, hearing: { off: [], guideLevel: GUIDE_LEVELS.medium, recordGuide: false }, phase: null, preparing: false, busy: false });
    kept.samples = new Map();
    kept.guides = new Map();
    kept.guidesKey = null;
    kept.guidesReady = null;
    kept.guideOffsets = new Map();
    kept.failedVoices = new Set();
    session.guideVoices = {};
    return Promise.all(saves);
}

// ---- The engine and saving ----------------------------------------------------------------------

/** A part's spread for Make it sound good: by its place among the parts heard, the lowest in the middle. */
function partSpread(part, all) {
    const heard = session.parts.filter((p) => all.get(p.id)?.audible || all.get(guideId(p.id))?.audible);
    const index = heard.indexOf(part);
    return index < 0 ? 0 : spread(index, heard.length);
}

/** Whether this part is the song's melody (Make it sound good puts it a little forward). */
function isMelody(part) {
    return !!part.templatePartKey && !!session.project?.template?.parts.find((p) => p.key === part.templatePartKey)?.melody;
}

/**
 * What the engine plays for a take: its kept stretch (the trims), and with Hear it polished on,
 * levelled and gently compressed as Make it sound good does (the take itself stays dry).
 */
function engineSamples(part, samples) {
    const stretch = trimmedSamples(samples, part.sampleRate, trimsOf(part));
    if (!polishedNow()) return stretch;
    const [gain] = levelGains([{ samples: stretch, sampleRate: part.sampleRate, melody: isMelody(part) }]);
    return compress(stretch.map((v) => v * gain), part.sampleRate);
}

/** Hear it polished: the takes levelled and in the room, as they'll sound when shared. */
export async function setHearPolished(on) {
    prefs.hearPolished = on;
    const playing = session.playing && !session.recording;
    if (playing) await stopPlayback();
    applyRoom();
    engine.projectId = null; // every take loads again, polished or not
    if (session.project && (await ready()) && playing) startPlayback();
}

/** Polished plays in the studio and Sing's review; Sing's practice and recording stay dry. */
function polishedNow() {
    return prefs.hearPolished && (session.screen !== 'sing' || session.inReview);
}

/** Sing's review opened or closed: Polished (if on) comes or goes with it. */
export function setInReview(on) {
    if (session.inReview === on) return;
    session.inReview = on;
    if (!prefs.hearPolished) return;
    applyRoom();
    engine.projectId = null; // the takes load again, polished or dry, when they next play
}

function applyRoom() {
    if (!engine.ctx) return;
    const room = polishedNow() ? prefs.room ?? DEFAULT_ROOM : 'dry';
    engine.setRoom(impulseResponse(room, engine.sampleRate), ROOMS[room]?.wet ?? 0);
}

/** Unlocks audio (from the click) and loads this project's takes and guides into the engine. */
export async function ready() {
    const id = session.openedId;
    await unlock();
    if (session.isTemplate) {
        await prepareGuides(engine.sampleRate);
    }
    if (!stillOpen(id)) return false;
    const loaded = `${session.project.id}:${session.guidesVersion}`;
    if (engine.projectId !== loaded) {
        applyRoom();
        [...engine.parts.keys()].forEach((id) => engine.removePart(id));
        for (const part of session.parts) {
            const samples = kept.samples.get(part.id);
            if (samples) engine.setPart(part.id, engineSamples(part, samples), part.sampleRate, voiceMix(part.id));
            const guide = kept.guides.get(part.id);
            if (guide) engine.setPart(guideId(part.id), guide, kept.guidesRate, voiceMix(guideId(part.id)));
        }
        if (kept.backing) engine.setPart(BACKING, kept.backing, kept.guidesRate, voiceMix(BACKING));
        engine.projectId = loaded;
    }
    syncMix();
    return true;
}

/**
 * Readies the guides at this sample rate and key, if not done already: a part's real voice where it
 * applies (docs/beta-singers-plan.md), otherwise the synth, rendered in a worker.
 */
export function prepareGuides(sampleRate) {
    if (!session.project) return Promise.resolve(); // closed meanwhile (left before it finished opening)
    const voices = session.parts.filter((p) => p.templatePartKey).map((p) => {
        const urls = voiceChoice(p).recordings.map((r) => (kept.failedVoices.has(r.url) ? `${r.url}:failed` : r.url));
        return `${p.templatePartKey}=${urls.length ? urls.join('+') : 'synth'}`;
    }).join(',');
    const chords = session.project.template?.chords?.length ? styleOf(session.practice?.chordsSound) : 'none';
    const key = `${session.openedId}:${sampleRate}:${session.project.transpose || 0}:${voices}:${chords}`;
    if (kept.guidesKey === key) return kept.guidesReady; // done, or still rendering: wait for it
    kept.guidesKey = key;
    kept.guidesReady = renderGuides(key, sampleRate);
    return kept.guidesReady;
}

/**
 * A part's chosen voices, as stored: a list of version ids (one today; several for the choir), with
 * "synth" for the practice voice; empty means the default. Older projects kept 'real' or 'synth'.
 */
export function chosenVoices(practice, partKey) {
    const kept = practice?.voices?.[partKey];
    if (kept === 'synth') return ['synth'];
    return Array.isArray(kept) ? kept : [];
}

/**
 * What a slot's guide should be: the chosen real voices (else the default) when they apply, with the
 * reason when one exists but the synth plays instead. `versions` are every voice on offer.
 */
function voiceChoice(slot) {
    const template = session.project.template;
    const found = session.project.beta ? betaVoice(slot) : versionsFor(template.id, slot.templatePartKey);
    if (!found) return { recordings: [], why: null, credit: null, versions: [], chosen: [] };
    const transpose = session.project.transpose || 0;
    const speed = session.practice?.speed ?? 1;
    const chosen = chosenVoices(session.practice, slot.templatePartKey);
    const synth = chosen.includes('synth');
    // Ids no longer published drop out; with none left, the default.
    let picked = chosen.map((id) => found.versions.find((v) => v.id === id)).filter(Boolean);
    if (!picked.length) picked = [found.versions.find((v) => v.default) ?? found.versions[0]];
    const applies = picked.every((recording) => recordingApplies({ recording, version: found.version, template, transpose, speed, voice: synth ? 'synth' : 'real' }));
    const why = applies ? null : synth ? 'chosen' : found.version !== template.version ? 'version' : transpose !== 0 ? 'key' : 'speed';
    return { recordings: applies ? picked : [], why, credit: picked[0].credit, credits: picked.map((r) => r.credit), versions: found.versions, chosen: synth ? ['synth'] : picked.map((r) => r.id) };
}

/** A beta project's real voice for a part: the ones My parts sent, for the version being recorded. */
function betaVoice(slot) {
    const recording = session.project.beta.guides?.find((g) => g.partKey === slot.templatePartKey);
    return recording ? { versions: [{ ...recording, default: true }], version: session.project.beta.templateVersion } : null;
}

async function renderGuides(key, sampleRate) {
    kept.rendering++;
    session.preparing = true;
    try {
        const template = session.project.template;
        const transpose = session.project.transpose || 0;
        const slots = session.parts.filter((p) => templatePart(p));
        const voices = {};
        // One part after another: decoding every real voice at once can use hundreds of MB on a phone.
        const rendered = [];
        for (const part of slots) {
            rendered.push(await renderOne(part, { template, transpose, sampleRate, voices }));
            if (kept.guidesKey !== key) return; // a newer render took over
        }
        slots.forEach((part, i) => {
            kept.guides.set(part.id, rendered[i].samples);
            kept.guideOffsets.set(part.id, rendered[i].offsetSeconds);
            // The lane shows what plays: the voice's own outline, at its own rate, and its length.
            part.guidePeaks = markRaw(toPeaks(rendered[i].samples, FRAMES_PER_PEAK));
            part.guidePeaksRate = sampleRate;
            part.guideSeconds = rendered[i].offsetSeconds + rendered[i].samples.length / sampleRate;
        });
        kept.backing = template.chords?.length ? markRaw(await songBand(template, { transpose, speed: session.practice?.speed ?? 1, sampleRate, style: styleOf(session.practice?.chordsSound) })) : null;
        if (kept.guidesKey !== key) return;
        session.guideVoices = voices;
        kept.guidesRate = sampleRate;
        session.guidesVersion++;
    } finally {
        kept.rendering--;
        session.preparing = kept.rendering > 0;
    }
}

/** One slot's guide: its real voice when it applies and loads, otherwise the synth. */
async function renderOne(part, { template, transpose, sampleRate, voices }) {
    const choice = voiceChoice(part);
    const about = { credit: choice.credit, credits: choice.credits ?? [], versions: choice.versions, chosen: choice.chosen };
    if (choice.recordings.length && choice.recordings.every((r) => !kept.failedVoices.has(r.url))) {
        try {
            const decoder = new OfflineAudioContext(1, 1, sampleRate);
            const loaded = [];
            for (const recording of choice.recordings) {
                loaded.push(await loadRecordedGuide(recording, {
                    key: guideKey(template.id, template.version, part.templatePartKey, recording.id),
                    sampleRate,
                    decode: (bytes) => decoder.decodeAudioData(bytes),
                }).catch((error) => {
                    kept.failedVoices.add(recording.url);
                    throw error;
                }));
            }
            const real = mixVoices(loaded);
            voices[part.id] = { kind: 'real', ...about, why: null, url: choice.recordings[0].url, ids: choice.recordings.map((r) => r.id), offsetSeconds: real.offsetSeconds };
            return real;
        } catch (error) {
            console.warn('The real voice could not load: the synth plays', error);
        }
    }
    const failed = choice.recordings.some((r) => kept.failedVoices.has(r.url));
    voices[part.id] = { kind: 'synth', ...about, why: failed ? 'failed' : choice.why, url: null, offsetSeconds: 0 };
    const samples = await renderGuide({ templateId: template.id, templateVersion: template.version, part: templatePart(part), tempo: template.tempo, transpose, speed: session.practice?.speed ?? 1, sampleRate });
    return { samples, sampleRate, offsetSeconds: 0 };
}

/**
 * Looks again for real voices: the catalogue afresh (a new voice since the app opened), and those
 * that failed to load (back online). Re-renders now unless something is playing; Play does it then.
 */
export async function refreshVoices() {
    const id = session.openedId;
    kept.failedVoices.clear();
    await loadCatalogue({ fresh: true });
    if (!stillOpen(id) || !session.isTemplate || session.playing) return;
    if (engine.unlocked) await ready();
    else await prepareGuides(GUIDE_PEAKS_RATE);
}

/** The template's part that a slot sings, or null for a plain part. */
export function templatePart(part) {
    return part.templatePartKey ? session.project.template?.parts.find((tp) => tp.key === part.templatePartKey) ?? null : null;
}

/**
 * Which screen has the session: 'sing' practises (the focused part's mode decides what plays),
 * 'studio' mixes (every part as its row says; a beta project's part keeps its practice there).
 */
export function setScreen(screen) {
    const moved = session.screen !== screen;
    session.screen = screen;
    if (screen === 'sing' && session.project) loadHearing();
    if (moved && prefs.hearPolished) {
        // Polished is the studio's (and the review's), never Sing's practice: the takes load again.
        applyRoom();
        engine.projectId = null;
    }
    syncMix();
}

export function levels() {
    // The studio mixes, a beta project's too: every row as its Solo and Mute say, a take in place of
    // its guide (a beta singer, 2026-10-29: Solo and Mute "do not work reliably", then "when I want to
    // listen to my own playback, I can only hear the original tone": the beta studio had kept Sing's
    // practice, which plays your part's guide and, listening, only the melody with it).
    const mixing = session.screen === 'studio';
    let practice = !session.isTemplate ? { ...PRACTICE_DEFAULTS } : mixing ? { ...session.practice, focus: null } : session.practice;
    // Listen then repeat on your own: the listen half has your guide (the engine mutes it, and your
    // take, for your turn). Without this, "own" kept it silent and you heard only the others.
    if (kept.repeating && practice.mode === 'own') practice = { ...practice, mode: 'singAlong' };
    // Slower than full speed, takes (recorded at full speed) step aside for their guides.
    let slots = session.speed === 1 ? session.parts : session.parts.map((p) => (p.templatePartKey ? { ...p, useGuide: true } : p));
    // Sing plays the arrangement: the studio's mutes, solos and levels stay in the studio (a beta singer
    // heard only his own take while recording, the others muted out of sight). The whole song's Listen plays every part; part by part, yours (and the
    // melody). The review hears your take in place, with the others, without changing either.
    if (session.screen === 'sing' && session.isTemplate) {
        // What you hear turns parts off for the visit, recording included, and sets your guide's level.
        slots = slots.map((p) => ({ ...p, muted: session.hearing.off.includes(p.id), solo: false, volume: 1 }));
        practice = session.reviewing ? { ...practice, mode: 'listen', withOthers: true, compare: true } : { ...practice, withOthers: practice.sing?.way === 'whole', compare: false };
        practice = { ...practice, guideLevel: session.hearing.guideLevel };
    }
    return voiceLevels(slots, practice, { recordingId: session.rerecordId, melody: melodyKey(), othersLevel: onSpeakers() ? SPEAKERS_UNDER : 1, recordGuide: session.screen === 'sing' && session.hearing.recordGuide, hearRecorded: mixing });
}

/** Recording without headphones (the timing check's route, or "No headphones" before recording). */
export function onSpeakers() {
    return audio.route === 'speakers';
}

// What you hear is remembered (Hillel: so it needn't be set again): the parts on or off, and your
// guide while recording, per song on this device (never in the project, so the studio is
// untouched); your guide's level for every song (prefs).
const hearingKey = () => `stackapella.hearing.${session.project.id}`;

function loadHearing() {
    let kept = {};
    try {
        kept = JSON.parse(local.get(hearingKey()) || '{}');
    } catch (error) {
        console.warn('What you hear unreadable; every part on', error);
    }
    session.hearing = { off: Array.isArray(kept.off) ? kept.off : [], recordGuide: !!kept.recordGuide, guideLevel: prefs.guideLevel ?? GUIDE_LEVELS.medium };
}

function saveHearing() {
    local.set(hearingKey(), JSON.stringify({ off: session.hearing.off, recordGuide: session.hearing.recordGuide }));
}

/** What you hear: a part on or off in Sing (recording too), remembered for this song. */
export function toggleHearing(id) {
    const off = session.hearing.off;
    session.hearing.off = off.includes(id) ? off.filter((x) => x !== id) : [...off, id];
    saveHearing();
    syncMix();
}

/** What you hear: your guide while you record (off unless turned on, mid-take too). */
export function setRecordGuide(on) {
    session.hearing.recordGuide = on;
    if (on && session.recording) session.guideHeard = true;
    saveHearing();
    syncMix();
}

/** What you hear: Sing along's guide level for this visit. */
export function setGuideLevel(level) {
    session.hearing.guideLevel = level;
    prefs.guideLevel = level;
    syncMix();
}

/** Settings' Reset: every part on, your guide off while recording, its level Medium. */
export function resetHearing() {
    session.hearing = { off: [], guideLevel: GUIDE_LEVELS.medium, recordGuide: false };
    prefs.guideLevel = GUIDE_LEVELS.medium;
    if (!session.project) return;
    local.remove(hearingKey());
    syncMix();
}

/** The song's melody part key (the template part marked `melody`), or null. */
export function melodyKey() {
    return session.project?.template?.parts.find((p) => p.melody)?.key ?? null;
}

/**
 * The harmony as the studio mixes it, whatever screen is open (a share is the song, not Sing's
 * practice mix): every part, at full speed, the takes where they're recorded.
 */
export function mixLevels() {
    const practice = session.isTemplate ? { ...session.practice, focus: null } : { ...PRACTICE_DEFAULTS };
    return voiceLevels(session.parts, practice, {});
}

/** Where the audible voices end (a take's kept stretch, a guide's length), in seconds. */
function heardEnd(all) {
    let end = 0;
    for (const part of session.parts) {
        if (all.get(part.id)?.audible && part.frames > 0) end = Math.max(end, heardSpan(part).to);
        if (all.get(guideId(part.id))?.audible && kept.guides.get(part.id)) end = Math.max(end, (kept.guideOffsets.get(part.id) ?? 0) + kept.guides.get(part.id).length / kept.guidesRate);
    }
    return end || session.contentLength;
}

/** One voice's mix for the engine: a take sits at its recorded place, a guide from its own start. */
export function voiceMix(voiceId, all = levels()) {
    const level = all.get(voiceId) ?? { audible: false, volume: 0, pan: 0 };
    const part = session.parts.find((p) => p.id === voiceId);
    const guideOf = voiceId.endsWith(':guide') ? voiceId.slice(0, -':guide'.length) : null;
    // Hear it polished spreads the parts a little by voice, the lowest in the middle (as the export does).
    const owner = part ?? (guideOf ? session.parts.find((p) => p.id === guideOf) : null);
    const pan = polishedNow() && owner ? Math.max(-1, Math.min(1, level.pan + partSpread(owner, all))) : level.pan;
    // A trimmed take plays its kept stretch from where that stretch always sat (trimming never moves the singing).
    return { ...level, pan, offsetSeconds: part ? part.offsetSeconds + (part.nudgeSeconds || 0) + trimsOf(part).start / part.sampleRate : kept.guideOffsets.get(guideOf) ?? 0 };
}

/**
 * A part's voice (remembered in the project): 'real' for the default, 'synth' for the practice
 * voice, or a list of version ids (one today; several sing together in the choir, later).
 */
export async function setVoice(slot, voice) {
    if (session.recording) return;
    if (session.playing) await stopPlayback(); // the guides are swapped: start again from here
    const ids = voice === 'synth' ? ['synth'] : voice === 'real' ? [] : voice;
    if (!ids.includes('synth')) kept.failedVoices.clear(); // a real voice that failed is tried again
    session.practice.voices = { ...session.practice.voices, [slot.templatePartKey]: ids };
    savePractice();
    if (engine.unlocked) await ready();
    else await prepareGuides(GUIDE_PEAKS_RATE);
}

export function syncMix() {
    if (!engine.projectId?.startsWith(session.project.id)) return;
    const all = levels();
    for (const part of session.parts) {
        engine.updatePart(part.id, voiceMix(part.id, all));
        if (part.templatePartKey) engine.updatePart(guideId(part.id), voiceMix(guideId(part.id), all));
    }
}

/** Runs `task`, then saves the project, counting what's being saved for the "Saving" label. */
export async function persist(task) {
    const project = session.project; // the project this change belongs to, even if it's closed meanwhile
    session.saving++;
    try {
        await task();
        if (project) {
            const saved = await saveProject(project);
            project.updatedAt = saved.updatedAt;
        }
    } catch (error) {
        console.error('Saving failed', error);
        toast(t('common.couldNotSave'), { tone: 'error' });
    } finally {
        session.saving--;
    }
}

/** Settings changes save shortly after the last one (a slider sends many). */
/** The review plays your take with the others (Hear it, Play phrase N, Hear it in place). */
export function reviewListen() {
    session.reviewing = true;
    syncMix();
}

/** The review is over: Listen is as it was. */
export function clearReview() {
    if (!session.reviewing) return;
    session.reviewing = false;
    syncMix();
}

export function updatePart(part, fields) {
    Object.assign(part, fields);
    syncMix();
    clearTimeout(kept.partSaves.get(part));
    kept.partSaves.delete(part);
    // A slider sends many changes: saved once it rests. A tap (Solo, Mute) is saved at once, so a
    // reload straight after it keeps it.
    const tap = Object.keys(fields).every((key) => key === 'solo' || key === 'muted');
    if (tap) {
        persist(() => savePart(plainPart(part)));
        return;
    }
    kept.partSaves.set(part, setTimeout(() => {
        kept.partSaves.delete(part);
        persist(() => savePart(plainPart(part)));
    }, 400));
}

function plainPart(part) {
    const { saveTimer, ...plain } = part;
    return plain;
}

/** Adds the parts a bigger group sings, or removes the ones a smaller group doesn't. */
/** The song for `singers` voices: parts it doesn't need go, new ones come. Returns an undo. */
export async function changeSingers(singers) {
    const id = session.openedId;
    if (session.playing) await stopPlayback();
    const template = session.project.template;
    const before = session.project.singers;
    const wanted = partsFor(template, singers);
    const undos = [];
    for (const part of session.parts.filter((p) => p.templatePartKey && !wanted.some((w) => w.key === p.templatePartKey))) {
        if (session.practice.focus === part.id) session.practice.focus = null;
        undos.push(await dropPart(part));
    }
    const project = session.project;
    const added = [];
    for (const part of wanted.filter((w) => !session.parts.some((p) => p.templatePartKey === w.key))) {
        const slot = await makeSlot(project, template, part, project.transpose || 0);
        added.push(slot.id);
        if (stillOpen(id)) session.parts.push({ ...slot, peaks: null, guidePeaks: markRaw(slot.guidePeaks) });
    }
    const undo = async () => {
        if (!stillOpen(id)) return;
        for (const slotId of added) {
            const slot = session.parts.find((p) => p.id === slotId);
            if (slot && !hasTake(slot)) await dropPart(slot);
        }
        for (const restore of undos) await restore();
        session.project.singers = before;
        await persist(async () => {});
    };
    project.singers = singers;
    if (!stillOpen(id)) {
        await saveProject(project); // left meanwhile: the parts are saved, so is the count
        return;
    }
    session.parts.sort((a, b) => a.order - b.order);
    kept.guidesKey = null; // render the new parts' guides
    if (engine.unlocked) await ready();
    await persist(async () => {});
    return undo;
}

/** Who made a part, for one made here in a friend's project. */
function myMark() {
    return session.project?.shared && account.me ? { createdBy: { id: account.me.id, name: account.me.name } } : {};
}

/** Whether this person may change `part`: always in their own project; in a friend's, only their own parts. */
export function mine(part) {
    return mayChangePart(session.project, part, account.me?.id);
}

/** In a friend's song: a part of it that's the singer's own, with its guide (the owner's slot stays theirs). */
export async function addMySlot(partKey) {
    const id = session.openedId;
    const project = session.project;
    const part = project?.template?.parts.find((p) => p.key === partKey);
    if (!part) return null;
    const slot = await makeSlot(project, project.template, part, project.transpose || 0, myMark());
    if (!stillOpen(id)) return null;
    session.parts.push({ ...slot, peaks: null, guidePeaks: markRaw(slot.guidePeaks) });
    session.parts.sort((a, b) => a.order - b.order);
    kept.guidesKey = null; // its guide renders with the others
    if (engine.unlocked) await ready();
    return session.parts.find((p) => p.id === slot.id) ?? null;
}

/** New key: re-render the guides. Recorded takes stay in the key they were sung in. */
export async function changeKey() {
    const id = session.openedId;
    if (session.playing) await stopPlayback();
    session.project.key = transposeKey(session.project.template.key, session.project.transpose || 0);
    await persist(async () => {});
    if (!stillOpen(id)) return;
    await prepareGuides(engine.unlocked ? engine.sampleRate : GUIDE_PEAKS_RATE);
    if (engine.unlocked && stillOpen(id)) await ready();
}

// ---- Practice (template projects) ---------------------------------------------------------------

export function savePractice() {
    clearTimeout(kept.practiceTimer);
    kept.practiceTimer = setTimeout(() => {
        kept.practiceTimer = 0;
        persist(async () => {});
    }, 500);
}

/** Puts `part` in focus for practice (or none, with null). */
export function practise(part) {
    if (!session.isTemplate) return;
    session.practice.focus = part?.id ?? null;
    session.practice.compare = false;
    if (part) session.practice.mode = hasTake(part) ? 'listen' : session.practice.mode;
    syncMix();
    savePractice();
}

export function setMode(mode) {
    if (!mode || !session.focusPart) return;
    session.practice.mode = mode;
    syncMix();
    savePractice();
}

export function setPractice(field, value) {
    session.practice[field] = value;
    syncMix();
    savePractice();
}

/** The chords' style (js/band.js: gentle, pop or swing): rendered again, so playback stops first. */
export async function setChordsSound(sound) {
    const id = session.openedId;
    if (!session.isTemplate || session.recording) return;
    if (session.playing) await stopPlayback();
    session.practice.chordsSound = sound;
    savePractice();
    if (!stillOpen(id)) return;
    if (engine.unlocked) await ready();
    else await prepareGuides(GUIDE_PEAKS_RATE);
}

/** Slow practice (1, 0.85 or 0.7): the guides render again at that speed, so playback stops first. */
export async function setSpeed(speed) {
    const id = session.openedId;
    if (!session.isTemplate || session.recording) return;
    if (session.playing) await stopPlayback();
    session.practice.speed = speed;
    savePractice();
    if (!stillOpen(id)) return;
    if (engine.unlocked) await ready();
    else await prepareGuides(GUIDE_PEAKS_RATE);
}

export function selectPhrase(index) {
    session.practice.phrase = index;
    if (index === null) session.practice.loop = false;
    else session.position = session.phraseList[index].startSeconds;
    savePractice();
    if (session.playing && !session.recording) startPlayback();
}

/**
 * Stops looping and carries on (a beta singer: "now I had it loop a section, but now can't get it to
 * unloop that section"): playing, the song goes on from where it is, past the phrase.
 */
export async function endLoop() {
    if (!session.practice?.loop) return;
    session.practice.loop = false;
    savePractice();
    if (!session.playing || session.recording) return;
    const at = session.position;
    await stopPlayback();
    session.position = at;
    if (await ready()) startPlayback();
}

/** Loops the selected phrase, or stops looping. False when no phrase is selected. */
export async function toggleLoop() {
    if (session.practice.phrase === null) return false;
    session.practice.loop = !session.practice.loop;
    savePractice();
    if (session.practice.loop) {
        if (await ready()) startPlayback();
    } else if (session.playing) {
        await stopPlayback();
    }
    return true;
}

/** Hear a phrase, then sing it back against the other parts (your guide muted). */
export async function listenThenRepeat() {
    const focus = session.focusPart;
    if (!focus || session.recording) return;
    if (!(await ready())) return;
    const list = session.practice.phrase === null ? session.phraseList.filter((p) => p.endSeconds > session.position + 0.05) : [session.phraseList[session.practice.phrase]];
    if (!list.length) return;
    engine.play({
        bpm: session.tempo,
        beatsPerBar: beatsPerBar(session.project.timeSignature),
        pickupBeats: session.pickup,
        metronome: session.project.metronome,
        // Round and round on one phrase, unless you've turned Loop off.
        repeat: { phrases: list, mute: [guideId(focus.id), focus.id], cueBeats: 1, cycle: session.practice.phrase !== null && (session.practice.loop || !session.practice.loopChosen) },
    });
    kept.repeating = true;
    syncMix();
    session.playing = true;
    tick();
}

/** Plays the focus part's first note (of the selected phrase, or of the part). */
export async function startingNote() {
    const part = session.focusPart && templatePart(session.focusPart);
    if (!part) return;
    const id = session.openedId;
    await unlock();
    if (!stillOpen(id)) return;
    const phrase = session.phraseList[session.practice.phrase ?? -1];
    const from = phrase ? phrase.startSeconds : Math.max(0, session.position - 0.01);
    const note = part.guide.notes.find((n) => n.beat * session.beatSeconds >= from - 0.01) ?? part.guide.notes[0];
    engine.preview(renderPart([{ beat: 0, length: 1.5, pitch: note.pitch }], { bpm: 60, sampleRate: engine.sampleRate, transpose: session.project.transpose || 0 }), engine.sampleRate);
}

// ---- The transport ------------------------------------------------------------------------------

export async function togglePlay() {
    if (session.recording) return;
    if (session.playing) {
        await stopPlayback();
    } else {
        if (session.position >= session.contentLength - 0.05) session.position = 0;
        if (await ready()) startPlayback();
    }
}

/** Plays from the position, or loops the selected phrase when looping is on. */
export function startPlayback({ to = null } = {}) {
    const phrase = session.practice?.loop ? session.phraseList[session.practice.phrase] : null;
    if (!Number.isFinite(session.position) || session.position < 0) session.position = 0;
    engine.play({
        fromSeconds: phrase ? phrase.startSeconds : session.position,
        toSeconds: phrase ? null : to, // a stretch (Play phrase N) ends on the audio clock
        bpm: session.tempo,
        beatsPerBar: beatsPerBar(session.project.timeSignature),
        pickupBeats: session.pickup,
        metronome: session.project.metronome,
        loop: phrase ? { startSeconds: phrase.startSeconds, endSeconds: phrase.endSeconds } : null,
    });
    session.playing = true;
    singing();
    tick();
}

export async function stopPlayback() {
    await engine.stop();
    if (kept.repeating) {
        kept.repeating = false;
        syncMix();
    }
    session.playing = false;
    session.phase = null;
    cancelAnimationFrame(kept.frame);
    doneSinging();
    if (kept.reloadAfter) reloadFromCloud();
}

// ---- Practice time, toward the weekly goal (docs/vocal-coaching-plan.md, "The weekly goal") ----

const PRACTICE_RUN_SECONDS = 30; // a run this long earns the practice run's XP

/** Playing or recording began (a seek while playing carries on the same run). */
function singing() {
    kept.singingFrom ??= performance.now();
}

/** Playing or recording stopped: the time counts toward the goal, and a long enough run earns XP. */
function doneSinging() {
    if (kept.singingFrom === null || kept.singingFrom === undefined) return;
    const seconds = (performance.now() - kept.singingFrom) / 1000;
    kept.singingFrom = null;
    // Kept with the song too: a lesson's "Use it" step is done once its song has been sung in.
    if (session.practice) {
        session.practice.sungSeconds = Math.round((session.practice.sungSeconds ?? 0) + seconds);
        savePractice();
    }
    const counted = seconds >= PRACTICE_RUN_SECONDS ? earn('practiceRun', { seconds }) : practised(seconds);
    counted.catch((error) => console.error('Practice time not counted', error));
}

/** Plays from `seconds` (Trim's "Play from the start" and "Play to the end"), to `to` when given. */
export async function playFrom(seconds, { to = null } = {}) {
    if (session.recording) return;
    if (session.playing) await stopPlayback();
    session.position = Math.max(0, seconds);
    if (await ready()) startPlayback({ to });
}

/** Moves the playhead (not while recording), carrying on playing from there if playing. */
export function seek(seconds) {
    if (session.recording) return;
    session.position = seconds;
    if (session.playing) startPlayback();
}

/** Reads what the engine is playing, once a frame, for the screen. */
function tick() {
    const info = engine.positionInfo();
    if (!info) return;
    const position = info.position;
    session.phase = info.phase;
    // Never a position that isn't a number: the next Play starts from it, and from NaN nothing sounds.
    if (!Number.isFinite(position)) {
        kept.frame = requestAnimationFrame(tick);
        return;
    }
    if (session.recording) {
        const beat = 60 / (session.project.bpm || 90);
        if (position < session.recordFrom - 0.02) {
            // The click sounding now: its beat in the song, then its place in the bar (as the clicks accent it).
            const left = Math.ceil((session.recordFrom - position) / beat - 0.02);
            const perBar = beatsPerBar(session.project.timeSignature);
            const clickBeat = Math.round(session.recordFrom / beat) - left;
            // Before the first click (the engine's lead): 0, counting in with nothing to show yet.
            session.countIn = left > session.countInBeats ? 0 : ((((clickBeat - session.pickup) % perBar) + perBar) % perBar) + 1;
        } else {
            session.countIn = null;
        }
    }
    session.position = Math.max(session.recording ? session.recordFrom : 0, position);
    const ended = info.done || (!session.recording && info.phase === 'play' && position >= session.contentLength + 0.1);
    if (ended) {
        stopPlayback();
        if (info.phase === 'play') session.position = session.contentLength;
        return;
    }
    kept.frame = requestAnimationFrame(tick);
}

// ---- Recording ----------------------------------------------------------------------------------

/**
 * Records from the position (or from 0:00, `fromStart`): into the part `session.rerecordId` names, or a new part. The mic is
 * already open (the screen opened it during setup, docs/architecture.md "Invariants").
 */
export async function startRecording({ fromStart = false } = {}) {
    if (!(await ready())) return;
    // The studio always records from the start (Hillel: "In the studio we should always rewind to
    // the start before recording"); Sing records from where it is.
    if (fromStart) session.position = 0;
    session.reviewing = false;
    session.guideHeard = session.hearing.recordGuide; // the take counts with the guide off unless it played
    syncMix();
    const target = session.parts.find((p) => p.id === session.rerecordId);
    const used = new Set(session.parts.map((p) => p.color));
    session.live = {
        name: target ? target.name : defaultPartName(session.parts.length),
        peaks: new Float32Array(Math.ceil((600 * engine.sampleRate) / FRAMES_PER_PEAK)),
        count: 0,
        color: target?.color ?? PART_COLORS.find((c) => !used.has(c)) ?? PART_COLORS[session.parts.length % PART_COLORS.length],
    };
    session.recordFrom = session.position;
    const perBar = beatsPerBar(session.project.timeSignature);
    // Count in to the bar line, then on to where recording starts (a pickup starts mid-bar).
    const intoBar = (((Math.round(session.recordFrom / session.beatSeconds) - session.pickup) % perBar) + perBar) % perBar;
    engine.play({
        fromSeconds: session.recordFrom,
        bpm: session.project.bpm || 90,
        beatsPerBar: perBar,
        pickupBeats: session.pickup,
        countInBeats: (session.countInBeats = session.project.countInBars ? session.project.countInBars * perBar + intoBar : 0),
        countInSounds: chipCountIn(), // game mode with Game sounds on: chip blips (the take's own clicks stay)
        metronome: session.project.metronome && !!session.project.bpm,
        record: true,
        latencyFrames: audio.latencyFrames ?? 0,
        onChunk: addLivePeaks,
        leadSeconds: leadSeconds(),
    });
    session.recording = true;
    session.playing = true;
    singing();
    navigator.wakeLock?.request('screen').then((lock) => (kept.wakeLock = lock)).catch((error) => console.info('The screen may sleep while recording', error));
    tick();
}

function addLivePeaks(chunk) {
    const live = session.live;
    if (!live) return;
    const added = toPeaks(chunk, FRAMES_PER_PEAK);
    if (live.count + added.length <= live.peaks.length) {
        live.peaks.set(added, live.count);
        live.count += added.length;
    }
}

/**
 * Stops recording and keeps the take. Returns what happened, for the screen to word:
 * - `{ kind: 'short', interrupted }`: stopped during the count-in, nothing kept;
 * - `{ kind: 'silent' }`: the mic heard pure silence, nothing kept;
 * - `{ kind: 'slot', part, first, next, everyPart, undo, interrupted, upTo, gaps }`: a template part;
 * - `{ kind: 'new', part, undo, interrupted, upTo, gaps }`: a new part.
 * `undo()` puts things back; `upTo` is where the take ends, in seconds.
 */
export async function finishRecording({ interrupted = false } = {}) {
    const id = session.openedId;
    const take = await stopRecorder();
    if (!stillOpen(id)) return { kind: 'short', interrupted: false };
    const live = session.live;
    session.live = null;
    let target = session.parts.find((p) => p.id === session.rerecordId);
    session.rerecordId = null;
    // In a friend's project, singing someone else's part makes it your own part of that voice.
    if (target && !mine(target)) target = target.templatePartKey ? await addMySlot(target.templatePartKey) : null;
    syncMix();
    const recordFrom = session.recordFrom;
    session.position = recordFrom;
    if (!take || take.samples.length < engine.sampleRate * 0.3) {
        return { kind: 'short', interrupted: interrupted || !!take?.interrupted };
    }
    if (isSilent(take.samples)) {
        session.patching = null;
        return { kind: 'silent' };
    }
    if (session.patching) {
        // A re-sung phrase: nothing is saved yet; the screen offers it in place (patchedTake).
        const { partId, plan } = session.patching;
        session.patching = null;
        const part = session.parts.find((p) => p.id === partId);
        return part ? { kind: 'patch', part, plan, samples: take.samples, sampleRate: take.sampleRate, recordFrom } : { kind: 'short', interrupted: false };
    }
    const about = { interrupted: interrupted || !!take.interrupted, upTo: recordFrom + take.samples.length / take.sampleRate, gaps: take.emptyQuanta > 0 };
    const fields = {
        offsetSeconds: recordFrom,
        nudgeSeconds: 0,
        trimStartFrames: 0, // a new take is whole
        trimEndFrames: 0,
        recordedAt: Date.now(), // "just now", on the studio's row
        score: null, // How it went, once it's worked out (js/report.js)
        scoring: Date.now(), // while it's worked out, the take waits to go up with it (js/sync.js)
        sampleRate: take.sampleRate,
        frames: take.samples.length,
        framesPerPeak: FRAMES_PER_PEAK,
        peaks: markRaw(toPeaks(take.samples, FRAMES_PER_PEAK)),
        // How it was recorded, for a beta take sent later (after a reload, or on another mic).
        recordedWith: markRaw({ latencyFrames: audio.latencyFrames, estimated: !!audio.latencyEstimated, mic: audio.mic?.label ?? null }), // raw: saved as is
    };
    if (target) {
        const before = { fields: { offsetSeconds: target.offsetSeconds, nudgeSeconds: target.nudgeSeconds, sampleRate: target.sampleRate, frames: target.frames, peaks: target.peaks, framesPerPeak: target.framesPerPeak ?? FRAMES_PER_PEAK, recordedWith: target.recordedWith ?? null, ...trimFields(target) }, samples: kept.samples.get(target.id) };
        const first = !hasTake(target);
        const previousScore = target.score ?? null; // for "+6% since last time"
        await storeTake(target, fields, take.samples);
        // The take recorded over is kept (projects.js savePart): Undo uses it again, and the new one is kept in turn.
        const stored = await db.get('parts', target.id);
        Object.assign(target, { takeId: stored?.takeId ?? target.takeId, keptTakes: stored?.keptTakes ?? [] });
        const previous = !first ? target.keptTakes[0]?.takeId : null;
        const undo = previous ? () => useTake(target, previous) : () => (before.samples ? storeTake(target, before.fields, before.samples) : clearTakeOf(target));
        const next = (target.templatePartKey && nextSlot(session.parts, session.project.template, target.id)) || null;
        const everyPart = !!target.templatePartKey && session.progress.done === session.progress.total;
        if (first) recorded(everyPart);
        if (session.focusPart?.id === target.id) session.practice.mode = 'listen';
        syncMix();
        session.position = recordFrom;
        return { kind: 'slot', part: target, first, next, everyPart, undo, previousScore, ...about };
    }
    session.parts.push({ id: crypto.randomUUID(), projectId: session.project.id, name: live.name, color: live.color, order: session.parts.length, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(), ...myMark(), ...fields });
    const part = session.parts[session.parts.length - 1]; // the reactive copy
    recorded(false);
    await storeTake(part, fields, take.samples);
    session.position = recordFrom;
    return { kind: 'new', part, undo: () => dropPart(part), ...about };
}

/**
 * Stops recording and keeps nothing (the recording stage's Start over): the part stays as it was,
 * and the same part (or re-sung phrase) is set to record again from the same place.
 */
export async function dropRecording() {
    const id = session.openedId;
    await stopRecorder();
    if (!stillOpen(id)) return;
    session.live = null;
    syncMix();
    session.position = session.recordFrom;
}

/** The engine stopped and the session out of recording; the take, or null when there was none. */
async function stopRecorder() {
    session.busy = true;
    try {
        return await engine.stop();
    } catch (error) {
        console.warn('The take was dropped (the project was closed)', error);
        return null;
    } finally {
        session.recording = false;
        session.playing = false;
        session.countIn = null;
        cancelAnimationFrame(kept.frame);
        kept.wakeLock?.release().catch((error) => console.info('The wake lock was already gone', error));
        session.busy = false;
        doneSinging();
    }
}

// ---- Re-sing a phrase (docs/more-features-plan.md, feature 11; js/patch.js) ------------------------

/**
 * Where a re-sung phrase would join `part`'s take, in project seconds: `{ from, to, singFrom,
 * singTo, phraseIndex }` (singTo: the word to sing on to when a held note moved the join), or null.
 */
export function resingPlan(part, phraseIndex) {
    const take = kept.samples.get(part.id);
    const notes = templatePart(part)?.guide.notes;
    const phrase = session.phraseList[phraseIndex];
    if (!take || !notes || !phrase) return null;
    const beat = 60 / (session.project.bpm || 90); // recordings are made at full speed
    const joins = joinPoints({
        take,
        sampleRate: part.sampleRate,
        takeStartSeconds: part.offsetSeconds + (part.nudgeSeconds || 0),
        notes: notes.map((n) => ({ start: n.beat * beat, end: (n.beat + n.length) * beat, lyric: n.lyric ?? null })),
        startSeconds: phrase.startBeat * beat,
        endSeconds: phrase.endBeat * beat,
        beatSeconds: beat,
    });
    return { ...joins, phraseIndex };
}

/** Sets up recording just that stretch: from a couple of beats before the join (to come in with the others). */
export function prepareResing(part, plan) {
    const beat = 60 / (session.project.bpm || 90);
    session.patching = { partId: part.id, plan };
    session.rerecordId = part.id;
    session.position = Math.max(0, plan.from - 2 * beat);
}

/** Leaves re-singing without recording (Cancel, or leaving the screen). */
export function cancelResing() {
    if (session.patching && session.rerecordId === session.patching.partId) session.rerecordId = null;
    session.patching = null;
}

/**
 * The take with the re-sung phrase in place: `{ samples, different }`, or null when the new
 * recording can't be joined (made at another sample rate). The new phrase gets the take's nudge,
 * is gain-matched to the singing around it, and is crossfaded in at both joins.
 */
export function patchedTake({ part, plan, samples: patch, sampleRate, recordFrom }) {
    const take = kept.samples.get(part.id);
    if (!take || sampleRate !== part.sampleRate) return null;
    const takeStart = part.offsetSeconds + (part.nudgeSeconds || 0);
    const frame = (seconds) => Math.round((seconds - takeStart) * sampleRate);
    const fromFrame = Math.max(0, frame(plan.from));
    const toFrame = Math.min(take.length, frame(plan.to));
    if (!(toFrame > fromFrame)) return null;
    const patchStart = frame(recordFrom + (part.nudgeSeconds || 0));
    const sung = patch.subarray(Math.max(0, fromFrame - patchStart), Math.max(0, toFrame - patchStart));
    const around = take.subarray(Math.max(0, fromFrame - 2 * sampleRate), Math.min(take.length, toFrame + 2 * sampleRate));
    const gain = matchGain(around, sung, sampleRate);
    return {
        samples: splice(take, patch, { fromFrame, toFrame, patchStart, gain, fadeFrames: Math.round(FADE_SECONDS * sampleRate) }),
        different: soundsDifferent(around, sung, sampleRate),
    };
}

/** Hears `samples` as `part`'s take without saving anything (a re-sung phrase on trial). */
export function tryTake(part, samples) {
    kept.samples.set(part.id, samples);
    engine.projectId = null; // the engine takes the new samples when it next plays
}

/** Keeps a re-sung phrase: a new take for the part. Returns an undo to the take before. */
export async function keepTake(part, samples, before) {
    // Peaks made here are at 512 frames each; a take from the account may have had coarser ones.
    await storeTake(part, { peaks: markRaw(toPeaks(samples, FRAMES_PER_PEAK)), framesPerPeak: FRAMES_PER_PEAK }, samples);
    return () => storeTake(part, { peaks: markRaw(toPeaks(before, FRAMES_PER_PEAK)), framesPerPeak: FRAMES_PER_PEAK }, before);
}

/** XP for a part's first take, and for the take that finishes a harmony. */
function recorded(everyPart) {
    earn('part').then(() => everyPart && earn('harmony')).catch((error) => console.error('XP not added', error));
}

/** The current take's samples for `part` (dry, as recorded), or null. */
export function takeOf(part) {
    return kept.samples.get(part.id) ?? null;
}

export async function storeTake(part, fields, samples) {
    Object.assign(part, fields);
    kept.samples.set(part.id, samples);
    engine.setPart(part.id, engineSamples(part, samples), part.sampleRate, voiceMix(part.id));
    syncMix();
    await persist(() => savePart(plainPart(part), samples));
    count('take_recorded'); // after it's saved: a save that fails isn't a take
    first('f_take'); // the funnel: this device's first take
    if (session.parts.filter((p) => p.frames > 0).length >= 2) first('f_harmony'); // and its first song of two parts sung
}

/**
 * A part's kept take as its current one (Hillel: "keep all takes so users can swap to try"): heard
 * at once, the take it replaces kept in turn. Returns an undo that swaps them back, or null.
 */
export async function useTake(part, takeId) {
    const was = part.takeId;
    const used = await useKeptTake(part.id, takeId);
    if (!used) return null;
    for (const key of ['frames', 'sampleRate', 'framesPerPeak', 'offsetSeconds', 'recordedAt', 'score', 'nudgeSeconds', 'trimStartFrames', 'trimEndFrames', 'takeId', 'syncedTakeId', 'keptTakes']) part[key] = used.part[key];
    part.peaks = used.part.peaks ? markRaw(used.part.peaks) : null;
    kept.samples.set(part.id, used.samples);
    if (engine.projectId?.startsWith(session.project?.id)) engine.setPart(part.id, engineSamples(part, used.samples), part.sampleRate, voiceMix(part.id));
    syncMix();
    return was ? () => useTake(part, was) : null;
}

/** A kept take gone from the device; the session's copy follows. Returns what it was, or null. */
export async function dropTake(part, takeId) {
    const gone = await deleteKeptTake(part.id, takeId);
    if (gone) part.keptTakes = (part.keptTakes ?? []).filter((k) => k.takeId !== takeId);
    return gone;
}

const trimFields = (part) => ({ trimStartFrames: part.trimStartFrames ?? 0, trimEndFrames: part.trimEndFrames ?? 0 });
const TRIM_SETTLE_MS = 150; // a timer for saving and re-placing a take, never for scheduling audio

/** Plays a part's take with its trims (once the engine has this project; ready() loads it otherwise). */
function placeTake(part) {
    const samples = kept.samples.get(part.id);
    if (samples && engine.projectId?.startsWith(session.project.id)) engine.setPart(part.id, engineSamples(part, samples), part.sampleRate, voiceMix(part.id));
    syncMix();
}

/**
 * Trims a part's take (feature 17): `{ start, end }` in frames at the take's rate. The recording
 * stays whole; the part keeps two numbers. Returns an undo.
 */
export async function setTrims(part, { start, end }) {
    const before = trimFields(part);
    const within = trimsOf({ ...part, trimStartFrames: start, trimEndFrames: end }); // within the take, never overlapping
    Object.assign(part, { trimStartFrames: within.start, trimEndFrames: within.end });
    // A drag sends many: the take is placed and saved once it settles (the sheet draws at once).
    clearTimeout(kept.trimSettle);
    await new Promise((resolve) => (kept.trimSettle = setTimeout(resolve, TRIM_SETTLE_MS)));
    placeTake(part);
    if (session.playing && !session.recording) startPlayback(); // the new stretch plays from here on
    await persist(() => savePart(plainPart(part)));
    return () => setTrims(part, { start: before.trimStartFrames, end: before.trimEndFrames });
}

/**
 * "Line up the ends" for every recorded part: 'start' or 'end'. The song ends at the template's last
 * note when there is one. Returns one undo for them all, or null when there was nothing to line up.
 */
export async function lineUpParts(mode) {
    const beatSeconds = 60 / (session.project.bpm || 90); // takes are recorded at full speed
    const template = session.project.template;
    const trims = lineUp(session.parts, mode, { beatSeconds, songEnd: template ? lengthBeats(template) * beatSeconds : null });
    if (!trims.size) return null;
    const undos = [];
    for (const part of session.parts) {
        if (trims.has(part.id)) undos.push(await setTrims(part, trims.get(part.id)));
    }
    return async () => {
        for (const undo of undos) await undo();
    };
}

/** A template slot goes back to its guide. Returns an undo, or null if there was no take. */
export async function clearTakeOf(part) {
    const before = { fields: { offsetSeconds: part.offsetSeconds, nudgeSeconds: part.nudgeSeconds, sampleRate: part.sampleRate, frames: part.frames, peaks: part.peaks, ...trimFields(part) }, samples: kept.samples.get(part.id) };
    Object.assign(part, { frames: 0, peaks: null, trimStartFrames: 0, trimEndFrames: 0 });
    kept.samples.delete(part.id);
    engine.removePart(part.id);
    syncMix();
    await persist(() => clearTake(plainPart(part)));
    return before.samples ? () => storeTake(part, before.fields, before.samples) : null;
}

/** Removes a part and its take. Returns an undo that puts both back. */
export async function dropPart(part) {
    const samples = kept.samples.get(part.id) ?? null;
    const id = session.openedId;
    session.parts = session.parts.filter((p) => p.id !== part.id);
    kept.samples.delete(part.id);
    engine.removePart(part.id);
    await persist(() => deletePart(part.id));
    return async () => {
        if (!stillOpen(id) || session.parts.some((p) => p.id === part.id)) return;
        session.parts.push(part);
        session.parts.sort((a, b) => a.order - b.order);
        if (samples) kept.samples.set(part.id, samples);
        // The engine loads it all again when it next plays (now, if it's running): the take, and a slot's guide.
        kept.guidesKey = null;
        engine.projectId = null;
        if (engine.unlocked) await ready();
        syncMix();
        await persist(() => savePart(plainPart(part), samples));
    };
}

// ---- Export -------------------------------------------------------------------------------------

const CHORDS_UNDER = 0.5; // Export's piano chords, as loud as Sing's chords start

/** The mix as heard now, rendered to stereo: `{ channels, sampleRate }`. */
/** The mix as heard, for export: the chords left out unless `chords` (they're "a cappella" by default). */
/**
 * The mix for Export: as heard, or polished in `room`; or a practice track (feature 12) of
 * `track.partId`, 'without' it or with it 'louder', with a click and a bar of count-in if asked.
 */
export async function renderMix({ chords = false, room = null, track = null, only = null, mix: asMixed = false, sampleRate = engine.sampleRate } = {}) {
    if (!(await ready())) throw new Error('The project was closed');
    let all = asMixed ? mixLevels() : levels();
    // Sing's chords never go in a file; Export's "Piano chords underneath" adds the song's own, on piano.
    all.set(BACKING, { audible: false, volume: 0, pan: 0 });
    if (track) all = trackLevels(all, track.partId, track.kind);
    if (only) all = onlyTake(all, only);
    const piano = chords ? await pianoChords() : null;
    const mix = [...all.keys()].map((id) => ({ id, ...voiceMix(id, all) }));
    if (piano) mix.push({ id: 'export-chords', audible: true, volume: CHORDS_UNDER, pan: 0, ...piano });
    // To where the voices heard end (a share's mix must not run past its takes), with a moment's tail.
    const lengthSeconds = heardEnd(all) + 0.5;
    const rendered = room ? await polishedMix(all, room, lengthSeconds, sampleRate, piano) : await engine.render(mix, lengthSeconds, sampleRate);
    if (!track?.click) return rendered;
    const clicked = withClicks(rendered, {
        beatSeconds: 60 / (session.project.bpm || 90), // takes are recorded at full speed
        beatsPerBar: beatsPerBar(session.project.timeSignature),
        pickupBeats: session.pickup,
        accent: click(rendered.sampleRate, { hz: 2500 }),
        normal: click(rendered.sampleRate),
    });
    limit(clicked.channels, clicked.sampleRate); // the clicks on top of a full mix never clip
    return clicked;
}

/** Just one part's take heard (Share just my part): every other voice silent. */
function onlyTake(all, partId) {
    return new Map([...all].map(([id, level]) => [id, id === partId ? { ...level, audible: true } : { ...level, audible: false }]));
}

/**
 * A share's mix (contract/api.md harmonies.create): the whole harmony as heard, or `partId`'s take
 * alone, polished in `room` (or dry), with each heard part's peaks every 50 ms (`tracks`) and the
 * published voices heard (`guides`). Beat 0 is the mix's start for a song's project.
 */
export async function shareMix({ room = null, partId = null } = {}) {
    if (session.speed !== 1) throw Object.assign(new Error('Slow practice is on'), { code: 'slow' });
    // At most 48 kHz (the account's limit): an audio interface at 96 kHz renders down.
    const rendered = await renderMix({ room, only: partId, mix: true, sampleRate: Math.min(48000, engine.sampleRate) });
    const all = partId ? onlyTake(mixLevels(), partId) : mixLevels();
    const mixSeconds = rendered.channels[0].length / rendered.sampleRate;
    const tracks = [];
    const guides = [];
    for (const part of session.parts) {
        const take = all.get(part.id);
        const guide = all.get(guideId(part.id));
        if (take?.audible && kept.samples.get(part.id)) {
            const samples = trimmedSamples(kept.samples.get(part.id), part.sampleRate, trimsOf(part));
            tracks.push({ name: (part.name || '').slice(0, 60) || '1', color: part.color.toLowerCase(), peaks: trackPeaks(samples, part.sampleRate, { offsetSeconds: voiceMix(part.id, all).offsetSeconds, volume: take.volume, mixSeconds }) });
        } else if (guide?.audible && kept.guides.get(part.id)) {
            tracks.push({ name: (part.name || '').slice(0, 60) || '1', color: part.color.toLowerCase(), peaks: trackPeaks(kept.guides.get(part.id), kept.guidesRate, { offsetSeconds: kept.guideOffsets.get(part.id) ?? 0, volume: guide.volume, mixSeconds }) });
            if (session.guideVoices[part.id]?.kind === 'real') guides.push(...(session.guideVoices[part.id].ids ?? []));
        }
    }
    return { ...rendered, tracks: tracks.slice(0, 12), guides: guides.slice(0, 16), beatZeroSeconds: session.project.template ? 0 : null };
}

/**
 * Stems (Plus): each recorded part on its own, dry, as a mono WAV's samples at its take's rate, lined
 * up from the song's start (so they line up again in any editor), its trims kept. [{ part, samples, sampleRate }]
 */
export function renderStems() {
    const length = session.contentLength + 0.5;
    return session.parts.filter((part) => kept.samples.get(part.id)).map((part) => {
        const sampleRate = part.sampleRate;
        const out = new Float32Array(Math.ceil(length * sampleRate));
        const stretch = trimmedSamples(kept.samples.get(part.id), sampleRate, trimsOf(part));
        const at = Math.round((part.offsetSeconds + (part.nudgeSeconds || 0)) * sampleRate + trimsOf(part).start);
        for (let i = Math.max(0, -at); i < stretch.length && at + i < out.length; i++) out[at + i] = stretch[i];
        return { part, samples: out, sampleRate };
    });
}

/**
 * Make it sound good (js/polish.js): every audible voice as heard (a take's kept stretch, or a
 * guide), dry, in the parts' order, levelled, compressed, spread and in `room`, at -14 LUFS.
 */
/**
 * A template song's chords played by the band (js/band.js) in `style`: Sing's chords underneath
 * (keys, bass, and the groove in Pop and Swing), or with `tracks` just some of it.
 */
async function songBand(template, { transpose = 0, speed = 1, sampleRate, style = 'gentle', tracks = ['keys', 'bass', 'drums'] }) {
    const endBeat = Math.max(...template.parts.map((p) => p.guide.notes.reduce((end, n) => Math.max(end, n.beat + n.length), 0)));
    const band = bandFor(template.chords, { endBeat, perBar: beatsPerBar(template.timeSignature), style });
    return renderBand(band, { bpm: template.tempo, sampleRate, endBeat, style, transpose, speed, drums: style !== 'gentle', tracks });
}

/** The song's chords on piano, for Export (the real piano when it's there): `{ samples, sampleRate }`, or null. */
async function pianoChords() {
    const template = session.project.template;
    if (!template?.chords?.length) return null;
    const sampleRate = kept.guidesRate ?? engine.sampleRate;
    const samples = await songBand(template, { transpose: session.project.transpose || 0, speed: session.practice?.speed ?? 1, sampleRate, tracks: ['keys'] });
    return { samples, sampleRate, offsetSeconds: 0 };
}

async function polishedMix(all, room, lengthSeconds, sampleRate, piano = null) {
    const voices = [];
    for (const part of session.parts) {
        for (const id of [part.id, guideId(part.id)]) {
            const level = all.get(id);
            const playing = engine.parts.get(id);
            if (!level?.audible || !playing) continue;
            const samples = id === part.id ? trimmedSamples(kept.samples.get(part.id), part.sampleRate, trimsOf(part)) : playing.buffer.getChannelData(0);
            const sampleRate = id === part.id ? part.sampleRate : playing.buffer.sampleRate;
            voices.push({ samples, sampleRate, offsetSeconds: voiceMix(id, all).offsetSeconds, volume: level.volume, pan: level.pan, spread: partSpread(part, all), audible: true, melody: isMelody(part) });
        }
    }
    if (piano) voices.push({ samples: piano.samples, sampleRate: piano.sampleRate, offsetSeconds: 0, volume: CHORDS_UNDER, pan: 0, spread: 0, audible: true, under: true });
    return polishRender({ voices, lengthSeconds, sampleRate, room });
}
