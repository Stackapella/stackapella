// Hear yourself as a choir (track B, phase 3; docs/own-choir-app-plan.md; contract: ownChoir.*). Behind own_choir
// (auth.config `ownChoir`, per account): a singer's 30 seconds, sung along in Sing on a project made for it, come back as
// every part of that song in a voice like theirs, the project's own guides, labelled as AI. The clip goes once its song is
// made; the voice prepared from it is kept privately for their next songs until they delete it (Settings) or a year
// unused. The parts made are the singer's, kept on this device to play offline, and deleted here with the song, on
// signing out, or when the server no longer has their song.

import { reactive } from 'vue';
import { aiUse } from './ai-choice.js';
import { local } from './storage.js';
import { api, uploadTo } from './api.js';
import * as db from './db.js';
import { account, loadAuthConfig } from './stores/account.js';
import { guideKey } from './recorded-guides.js';
import { encodeWav } from './wav.js';
import { count } from './usage.js';
import { formatDate, formatNumber, locale, t } from './i18n.js';
import { templatePartName } from './templates.js';
import { confirmAction } from './components/confirm.js';
import { toast } from './components/ui.js';
import { Icon } from './components/icon.js';
import { Switch } from './components/controls.js';
import { ownChoirOn, ownChoirSongsOn, CLIP_TEMPLATE, clipSongs, ownChoir, loadOwnChoir, togetherOn, partList, isOpen, readyUnseen, choirOf, savedVoice } from './own-choir-state.js';

// The state Home and a song's sheet need lives in own-choir-state.js (kept off the first screen's weight); the rest of
// the app imports it from here as before.
export { ownChoirOn, ownChoirSongsOn, CLIP_TEMPLATE, clipSongs, ownChoir, loadOwnChoir, togetherOn, partList, isOpen, readyUnseen, choirOf, savedVoice };

export const MIN_SECONDS = 15; // shorter, the card asks for a little longer (ownChoir.record.short)
export const MAX_SECONDS = 35; // the contract's most (and at least 5): the voice-capture song runs about 30 s


/** Whether a song has words to sing (any note with a lyric). */
export function hasWords(template) {
    return !!template?.parts?.some((part) => part.guide?.notes?.some((note) => note.lyric));
}

/** The part a hum or a song's own tune is (the melody, else the first part). */
export function tunePart(template) {
    return (template?.parts?.find((p) => p.melody) ?? template?.parts?.[0])?.key ?? null;
}

// The choir's 30 seconds: a tune the singer chooses (Hillel, 2026-11-22: "make it easy for the user to choose between
// Silent Night, Amazing Grace, Happy Birthday or Joy to the World"), whatever song the choir is for. Happy Birthday is
// the hidden `voice-capture` song (v4: Happy Birthday twice, "dear friend", in F). The server lists them, in order,
// the first the default, each with where the take stops (`stopBeat`, a phrase start; null for the whole tune).
const CLIP_CHOICE = 'stackapella.clipSong';


/** The tune chosen (remembered on this device), else the default. */
export function chosenClip() {
    const songs = clipSongs();
    let kept = null;
    try {
        kept = localStorage.getItem(CLIP_CHOICE);
    } catch {
        kept = null; // storage blocked: the default
    }
    return songs.find((song) => song.templateId === kept) ?? songs[0];
}

export function chooseClip(templateId) {
    clipPick.chosen = templateId;
    try {
        localStorage.setItem(CLIP_CHOICE, templateId);
    } catch (error) {
        console.info('The tune chosen was not kept', error?.message ?? error);
    }
}

/** The song a clip tune is titled as: Happy Birthday for voice-capture (its own title isn't for people). */
export const clipTitleId = (templateId) => (templateId === CLIP_TEMPLATE ? 'happy-birthday' : templateId);

/**
 * A tune cut where its take stops, as the server cuts what it sends the worker: in every part only the notes that start
 * before `stopBeat`, each unchanged (the last one holds its full length); sections, phrases and chords end there too.
 */
export function cutAt(template, stopBeat) {
    if (stopBeat == null) return template;
    const copy = structuredClone(template);
    let end = 0;
    for (const part of copy.parts) {
        if (!part.guide?.notes) continue;
        part.guide.notes = part.guide.notes.filter((n) => n.beat < stopBeat);
        for (const n of part.guide.notes) end = Math.max(end, n.beat + n.length);
    }
    if (Array.isArray(copy.chords)) copy.chords = copy.chords.filter((c) => c.beat < stopBeat);
    if (Array.isArray(copy.sections)) {
        copy.sections = copy.sections
            .filter((section) => section.startBeat < stopBeat)
            .map((section) => ({ ...section, endBeat: Math.min(section.endBeat, end), phrases: (section.phrases ?? []).filter(([from]) => from < stopBeat).map(([from, to]) => [from, Math.min(to, end)]) }));
    }
    return copy;
}

// The choice as the screens show it: the tune picked, and each tune's title in the app's language.
// noHeadphones: the intro's Headphones pick (components/first-note.js keeps it remembered), read here so Sing the whole
// song is offered only with headphones (a lead sung by eye would drift against the AI parts; ai-backend).
export const clipPick = reactive({ chosen: null, titles: {}, whole: false, noHeadphones: (() => {
    try {
        return localStorage.getItem('stackapella.clipHeadphones') === 'no';
    } catch {
        return false; // storage blocked: headphones, the default
    }
})() });

export function pickedClip() {
    return clipSongs().find((song) => song.templateId === clipPick.chosen) ?? chosenClip();
}

export async function loadClipTitles() {
    const { loadTemplate } = await import('./templates.js');
    const { songTitle } = await import('./song-titles.js');
    await Promise.all(clipSongs().map(async ({ templateId }) => {
        if (clipPick.titles[templateId]) return;
        try {
            clipPick.titles[templateId] = songTitle(await loadTemplate(clipTitleId(templateId)));
        } catch (error) {
            console.warn('A tune\'s title did not load', error);
        }
    }));
}

/** Step 1's title for the tune picked: Happy Birthday's names its second song too. */
export function clipLine() {
    const choice = pickedClip();
    const song = clipPick.titles[choice.templateId] ?? '';
    return choice.templateId === CLIP_TEMPLATE ? t('ownChoir.intro.one', { song }) : t('ownChoir.intro.oneSong', { song });
}

/**
 * The four tunes to sing for the 30 seconds (the choice is remembered), as Hillel's start design draws them: a card each,
 * 60 px tall, its name and what of it is sung (Happy Birthday, twice through), two across where they fit.
 * One radio group: arrows move the choice, as the app's Segmented does.
 */
export const ClipChoice = {
    // songId: the choir's own song; when it's the tune chosen, they may sing the whole of it (it becomes the lead).
    props: { songId: { type: String, default: null } },
    components: { Switch },
    setup: () => ({ clipPick, CLIP_TEMPLATE }),
    computed: {
        songs: () => clipSongs(),
        picked: () => pickedClip().templateId,
    },
    mounted() {
        loadClipTitles();
    },
    template: `
        <div v-if="songs.length > 1" class="flex flex-col gap-2.5" data-clip-choice>
            <p id="clip-choice-label" class="text-sm font-semibold text-cream/80">{{ t('ownChoir.intro.choose') }}</p>
            <div class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-2" role="radiogroup" aria-labelledby="clip-choice-label" @keydown="key">
                <button v-for="(song, i) in songs" :key="song.templateId" ref="option" type="button" role="radio" class="flex min-h-[60px] flex-col justify-center gap-0.5 rounded-xl border-[1.5px] px-4 py-2.5 text-start transition-colors"
                    :class="picked === song.templateId ? 'border-accent bg-accent/10' : 'border-line hover:border-muted hover:bg-raised'" :aria-checked="picked === song.templateId ? 'true' : 'false'" :tabindex="picked === song.templateId ? 0 : -1"
                    :data-clip-song="song.templateId" @click="chooseClip(song.templateId)">
                    <span class="text-[15px] text-cream" :class="picked === song.templateId ? 'font-semibold' : 'font-medium'">{{ clipPick.titles[song.templateId] ?? '' }}</span>
                    <span class="text-[13px]" :class="picked === song.templateId ? 'text-cream/75' : 'text-muted'">{{ song.templateId === CLIP_TEMPLATE ? t('ownChoir.intro.subTwice') : t('ownChoir.intro.subFirst') }}</span>
                </button>
            </div>
            <Switch v-if="songId && picked === songId && !clipPick.noHeadphones" :on="clipPick.whole" :label="t('ownChoir.intro.whole')" :hint="t('ownChoir.intro.wholeHint')" class="pt-2" data-clip-whole @change="clipPick.whole = $event" />
        </div>`,
    methods: {
        chooseClip,
        key(event) {
            const rtl = getComputedStyle(this.$el).direction === 'rtl';
            const step = { ArrowRight: rtl ? -1 : 1, ArrowDown: 1, ArrowLeft: rtl ? 1 : -1, ArrowUp: -1 }[event.key];
            if (!step) return;
            event.preventDefault();
            const ids = this.songs.map((song) => song.templateId);
            const to = (ids.indexOf(this.picked) + step + ids.length) % ids.length;
            chooseClip(ids[to]);
            this.$refs.option[to]?.focus();
        },
    },
};

/** The chosen tune as Sing plays it for the choir's 30 seconds (and a friend's join): cut at its stop, unless sung whole. */
export async function clipTemplate(choice = pickedClip(), { whole = false } = {}) {
    const { loadTemplate } = await import('./templates.js');
    const template = await loadTemplate(choice.templateId);
    return whole ? template : cutAt(template, choice.stopBeat);
}

/** Where the clip of a take ends, in seconds after beat 0: the last note before the tune's stop, held out (35 at most). */
export function clipSecondsFor(template, stopBeat, beatSeconds) {
    if (stopBeat == null) return MAX_SECONDS;
    const notes = cutAt(template, stopBeat).parts.flatMap((p) => p.guide?.notes ?? []);
    const end = Math.max(0, ...notes.map((n) => n.beat + n.length));
    return Math.min(MAX_SECONDS, end * beatSeconds + 0.5);
}

// A hummed tune's humming, kept on this device only while own songs are on, so "Hear it in your voice" can use it as the
// clip (Hillel: "they could hum it and have their voice replace their humming"); deleted once it's sent.
const humKey = (id) => `hum:${id}`;

/** Keeps a hum's take for a draft or a song (`id`). */
export async function keepHum(id, take) {
    await db.put('audio', { samples: db.toInt16(take.samples), sampleRate: take.sampleRate }, humKey(id));
}

/** The kept hum for `id`: `{ samples, sampleRate }`, or null. */
export async function humFor(id) {
    const kept = await db.get('audio', humKey(id)).catch((error) => (console.warn('The kept hum was not read', error), null));
    return kept?.samples ? { samples: db.toFloat32(kept.samples), sampleRate: kept.sampleRate } : null;
}

/** Moves a draft's hum to the saved song's id (the creator, when it's saved). */
export async function moveHum(from, to) {
    const kept = await db.get('audio', humKey(from)).catch((error) => (console.warn('The kept hum was not read', error), null));
    if (!kept) return;
    await db.put('audio', kept, humKey(to));
    await dropHum(from);
}

export async function dropHum(id) {
    await db.remove('audio', humKey(id)).catch((error) => console.warn('A kept hum was not removed', error));
}


/** Every choir part this device keeps, from the projects that kept them: `[{ project, request }]`. */
async function keptChoirs() {
    const { loadProjects, projects } = await import('./stores/projects.js');
    await loadProjects();
    return projects.all.filter((p) => p.choir?.parts?.length).map((p) => ({ project: p, request: p.choir }));
}

/** Drops the parts of songs the server no longer has (deleted on another device, or with the account). */
export async function forgetGone() {
    // The songs this account may still hear: its own, and those it sings in (a friend's Learn copy plays the owner's).
    const live = new Map((ownChoir.data?.requests ?? []).map((r) => [r.id, r]));
    for (const entry of ownChoir.data?.joined ?? []) if (entry.song?.id) live.set(entry.song.id, entry.song);
    for (const { project, request } of await keptChoirs()) {
        // An older choir project kept no templateId of its own: its song's id is the project's template.
        const kept = { ...request, templateId: request.templateId ?? project.template?.id, id: request.requestId };
        const now = live.get(request.requestId);
        if (!now) {
            await forgetParts(kept);
            continue;
        }
        // Parts no longer playing in it (a friend left or was removed): their audio goes from this device, and the
        // project keeps only what plays now.
        const active = new Set((now.parts ?? []).map((p) => p.id));
        const stale = (request.parts ?? []).filter((p) => !active.has(p.id));
        if (!stale.length) continue;
        await forgetParts({ ...kept, parts: stale });
        const stored = await db.get('projects', project.id).catch(() => null);
        if (stored?.choir) await db.put('projects', { ...stored, choir: { ...stored.choir, parts: (now.parts ?? []).map((p) => ({ ...p })) } }).catch((error) => console.warn('The choir project was not updated', error));
    }
    forgetStaleJoins();
}

/** Signing out: every choir part kept on this device goes (they're the singer's, not this device's). */
export async function forgetAllHere() {
    local.set(JOINS, '{}'); // the invites this device was joining by go with the person (sign-out, account deletion)
    for (const { project, request } of await keptChoirs()) await forgetParts({ ...request, templateId: request.templateId ?? project.template?.id, id: request.requestId });
    for (const request of ownChoir.data?.requests ?? []) await forgetParts(request);
    ownChoir.data = null;
}

/** A song's newest request, or null. */
export function requestFor(templateId) {
    return ownChoir.data?.requests?.find((r) => r.templateId === templateId) ?? null;
}

/**
 * A song's part-minutes as the server counts them (its `needed`): each part's last note end, in minutes at the song's
 * tempo, summed over the parts.
 */
export function partMinutes(template) {
    const tempo = template?.tempo || 0;
    if (!tempo) return 0;
    return (template.parts ?? []).reduce((sum, part) => {
        const notes = part.guide?.notes ?? [];
        return sum + (notes.length ? Math.max(...notes.map((n) => n.beat + n.length)) / tempo : 0);
    }, 0);
}

/** A song the server won't make (own_choir.max_parts 8, max_part_seconds 300; `409 song_too_long`): said before singing. */
export function tooLong(template) {
    const tempo = template?.tempo || 0;
    const parts = template?.parts ?? [];
    if (parts.length > 8) return true;
    return !!tempo && parts.some((part) => {
        const notes = part.guide?.notes ?? [];
        return notes.length && (Math.max(...notes.map((n) => n.beat + n.length)) / tempo) * 60 > 300;
    });
}

/** About how many songs like this one the minutes left make (rounded down); null when the allowance isn't in minutes. */
export function songsLeft(quota, template) {
    if (quota?.unit !== 'minutes' || quota.minutesLeft == null) return null;
    const need = partMinutes(template);
    return need > 0 ? Math.floor(quota.minutesLeft / need) : null;
}

/** The free song waits for a confirmed email (the server refuses it otherwise): asked before singing, never after. */
export function emailNeeded(quota) {
    return !!account.me && account.me.emailVerified === false && !quota?.plus && (quota?.freeLeft ?? 0) > 0;
}

/** The check before the account's first song: the site key, when the server asks for it (`checkNeeded`); else null. */
export function checkKey() {
    return ownChoir.data?.checkNeeded ? account.config?.turnstileSiteKey ?? null : null;
}

/** A refusal in the singer's words (Sing's card and the choir page say the same). */
export function choirRefusal(error, { own = false } = {}) {
    const code = error?.code;
    const which = error?.body?.which;
    if (code === 'quota_reached') {
        if (which === 'minutes') {
            const q = ownChoir.data?.quota;
            const minutes = (n) => formatNumber(Math.max(0, Math.round(n ?? 0)));
            const date = q?.resetsAt ? formatDate(q.resetsAt, { day: 'numeric', month: 'long' }) : '';
            const values = { needed: minutes(error.body.needed), left: minutes(q?.minutesLeft), more: minutes(q?.minutesPerMonth), date };
            return date ? t('ownChoir.limit.minutesMore', values) : t('ownChoir.limit.minutes', values);
        }
        if (which === 'day') return t('ownChoir.limit.day');
        if (which === 'free') return t('ownChoir.limit.free_used');
        // Comped Plus's songs, in any 30 days: when the oldest leaves the window, if the server says.
        const resets = ownChoir.data?.quota?.resetsAt;
        return resets ? t('ownChoir.limit.monthMore', { date: formatDate(resets, { day: 'numeric', month: 'long' }) }) : t('ownChoir.limit.month');
    }
    if (code === 'request_open') return t('ownChoir.open');
    if (code === 'not_found' && own) return t('ownChoir.songs.notSaved');
    if (code === 'song_too_long') return t('ownChoir.songs.tooLong');
    if (code === 'email_unverified') return t('ownChoir.email.needed');
    // A throwaway address can't take the free song: a lasting one, changed in Settings.
    if (code === 'validation_failed' && error?.errors?.email) return t('ownChoir.email.lasting');
    if (code === 'turnstile_required' || code === 'turnstile_failed') return t('ownChoir.check.failed');
    if (code === 'free_song_is_your_clip') return t('ownChoir.free.refused');
    return t('ownChoir.record.failed');
}

/** Download's full-quality parts (Plus; the server refuses `plus_required` otherwise): the request with them in place. */
export async function downloadParts(request) {
    const { data } = await api.ownChoir.requests.download(request.id);
    return { ...request, parts: data.parts };
}

// ---- A choir with friends (docs/choir-together-plan.md, behind own_choir_together) ---------------------------------



/** Whether every one of these voices sits `fit` ('high' or 'low') for the owner. */
function allFit(request, keys, fit) {
    const voices = request?.voices ?? [];
    // "The high parts" only for two or more: one part is named ("the soprano part").
    return keys.length > 1 && keys.every((key) => voices.find((v) => v.partKey === key)?.fit === fit);
}

/** The invite line: "Invite a friend to sing the high parts", the low ones, or the parts named. */
export function inviteLine(request, template) {
    const keys = request?.invite?.parts ?? [];
    if (allFit(request, keys, 'high')) return t('ownChoir.together.invite.high');
    if (allFit(request, keys, 'low')) return t('ownChoir.together.invite.low');
    return t('ownChoir.together.invite.parts', { count: keys.length, parts: partList(keys, template) });
}

/** What the share sheet says with the link. */
export function inviteText(request, template, song) {
    const keys = request?.invite?.parts ?? [];
    if (allFit(request, keys, 'high')) return t('ownChoir.together.invite.shareHigh', { song });
    if (allFit(request, keys, 'low')) return t('ownChoir.together.invite.shareLow', { song });
    return t('ownChoir.together.invite.shareParts', { count: keys.length, parts: partList(keys, template), song });
}

/**
 * Who sings each part, for the owner's Who sings list (the visual review: "Your voice" / "Ben's voice", each with one small
 * AI chip, the panel line carrying the full label once): `{ part, label, ai, note, friend }`. A real take of theirs is
 * "Your real voice", without the chip.
 */
export function whoSings(request, parts, realKeys = []) {
    const real = new Set(realKeys);
    const resets = ownChoir.data?.quota?.resetsAt;
    return parts.map((part) => {
        const voice = (request?.voices ?? []).find((v) => v.partKey === part.key);
        if (real.has(part.key)) return { part, label: t('ownChoir.together.who.real'), ai: false, note: '', sub: '', friend: null };
        if (voice?.whose === 'friend') {
            const name = voice.name ?? '';
            // Waiting for the owner's minutes: the name and its chip on one line, when it's made on a muted line under it.
            const waiting = voice.status === 'waiting_minutes' && resets;
            const label = voice.status === 'ready' || waiting ? t('ownChoir.together.who.friendVoice', { name }) : t('ownChoir.together.who.making', { name });
            const sub = waiting ? t('ownChoir.together.who.until', { date: formatDate(resets, { day: 'numeric', month: 'long' }) }) : '';
            return { part, label, ai: true, note: '', sub, friend: { id: voice.memberId, name } };
        }
        const note = voice?.fit === 'high' ? t('ownChoir.together.who.high') : voice?.fit === 'low' ? t('ownChoir.together.who.low') : '';
        return { part, label: t('ownChoir.together.who.you'), ai: true, note, sub: '', friend: null };
    });
}

/** The friends' names as the page's language lists whose singing it is: "Ben's", "Ben's and Cara's" ("el de Ben y el de Cara"). */
export function whoseList(names) {
    const each = names.map((name) => t('ownChoir.together.whose', { name }));
    try {
        return new Intl.ListFormat(locale.lang, { style: 'long', type: 'conjunction' }).format(each);
    } catch {
        return each.join(', ');
    }
}

/** Plain names as a list: "Ben and Cara". */
export function nameList(names) {
    try {
        return new Intl.ListFormat(locale.lang, { style: 'long', type: 'conjunction' }).format(names);
    } catch {
        return names.join(', ');
    }
}

// A join's project (the friend singing their 30 seconds for an invite) keeps the invite on this device, by project, never
// in the project itself: the account's copy of a project keeps only the choir fields it knows, so a sync would drop it.
const JOINS = 'stackapella.choirJoins';
const joins = () => {
    try {
        return JSON.parse(local.get(JOINS) || '{}');
    } catch (error) {
        console.info('The joins kept here were unreadable', error?.message ?? error);
        return {};
    }
};

/** The invite a project was started for ({ token, showName, wordsSha, partKey, owner }), or null. */
export function joinFor(projectId) {
    return (projectId && joins()[projectId]) || null;
}

/** Keeps (or, with null, forgets) the invite a project was started for. */
export function keepJoin(projectId, join) {
    const all = joins();
    if (join) all[projectId] = { ...join, at: Date.now() };
    else delete all[projectId];
    local.set(JOINS, JSON.stringify(all));
}

/** Invites kept past their 14 days (never sung for) go, with the project made only for them. */
function forgetStaleJoins() {
    const all = joins();
    const old = Object.keys(all).filter((id) => !all[id]?.at || Date.now() - all[id].at > 14 * 864e5);
    if (!old.length) return;
    for (const id of old) delete all[id];
    local.set(JOINS, JSON.stringify(all));
    import('./stores/projects.js').then(({ deleteProject }) => Promise.all(old.map((id) => deleteProject(id).catch(() => null)))).catch((error) => console.warn('A joining project was not removed', error));
}

// A friend's sung tune for an invite, kept in memory here until they agree (never uploaded or stored before the yes):
// { samples, sampleRate, startSeconds, partKey, octave, projectId }, by token.
const joinClips = new Map();

/** Keeps the tune they just sang for this invite, until the yes. */
export function keepJoinClip(token, clip) {
    joinClips.set(token, clip);
}

/** The tune sung for this invite and kept here, or null (a reload loses it). */
export function joinClipFor(token) {
    return joinClips.get(token) ?? null;
}

/** Discards the take at once (No thanks, leaving, singing it again), with the project made only for it. */
export async function dropJoinClip(token) {
    const clip = joinClips.get(token);
    joinClips.delete(token);
    if (!clip?.projectId) return;
    keepJoin(clip.projectId, null);
    const { deleteProject } = await import('./stores/projects.js');
    await deleteProject(clip.projectId).catch((error) => console.warn('The joining take was not removed', error));
}

/** A friend joining by an invite: the clip sent as `sendClip` sends it, to `invites.accept`; answers the member. */
export async function joinWithClip(token, { partKey, clipTemplateId = null, clipTranspose = 0, guide = true, parts = null, octave = 0, samples, sampleRate, startSeconds, showName = false, wordsSha = null }) {
    const clip = clipFrom(samples, sampleRate, startSeconds);
    const bytes = await clip.wav.arrayBuffer();
    const sha256 = hex(await crypto.subtle.digest('SHA-256', bytes));
    const { data: member, upload } = await api.ownChoir.invites.accept(token, { showName, wordsSha, useSavedVoice: false, parts, octave, ...(clipTemplateId ? { clipTemplateId } : {}), ...(clipTranspose ? { clipTranspose } : {}), ...(guide ? {} : { guide: false }), partKey, offsetFrames: clip.offsetFrames, sampleRate, frames: clip.frames, bytes: bytes.byteLength, sha256 });
    if (upload) {
        await uploadTo(upload, clip.wav);
        await api.ownChoir.members.complete(member.id);
    }
    await loadOwnChoir({ force: true });
    return member;
}



/**
 * The project's guides when it's a choir project (`project.choir`): the song's parts made from the singer's voice, as
 * voiceChoice expects them (`{ versions, version }`), or null. Each says it's AI and made from their singing (`choir`).
 */
export function choirVoices(project, partKey) {
    // The server's answer, else the parts the project kept when it was opened (so it plays offline, after a reload too).
    const request = (ownChoir.data?.requests ?? []).find((r) => r.id === project?.choir?.requestId && r.status === 'ready') ?? (project?.choir?.parts ? project.choir : null);
    const part = request?.parts?.find((p) => p.partKey === partKey);
    return part ? { versions: [{ ...part, default: true, choir: true }], version: request.version } : null;
}


/**
 * The clip from a take (`samples` at `sampleRate`, its frame 0 at `startSeconds` of the song, latency already out):
 * from the song's beat 0 (or the take's start, if it started later), at most 30 seconds, as a 16-bit mono WAV, and
 * where beat 0 falls in it. `{ wav, frames, offsetFrames, seconds }`.
 */
/**
 * How loud the singing is, as the worker measures it (ai-modal): the 90th percentile of 50 ms frame levels (dBFS, the
 * loudest stretches of singing), and whether it clipped. The worker takes singing between -35 and -6 dBFS.
 */
export function takeLevel(samples, sampleRate) {
    const frame = Math.max(1, Math.round(sampleRate * 0.05));
    const levels = [];
    let over = 0;
    for (let at = 0; at + frame <= samples.length; at += frame) {
        let sum = 0;
        for (let i = at; i < at + frame; i++) {
            const v = samples[i];
            sum += v * v;
            if (Math.abs(v) >= 0.99) over += 1;
        }
        levels.push(10 * Math.log10(sum / frame + 1e-12));
    }
    levels.sort((a, b) => a - b);
    const db = levels.length ? levels[Math.min(levels.length - 1, Math.floor(levels.length * 0.9))] : -120;
    return { db, clipped: over > samples.length * 0.0005 };
}

/**
 * A take the worker would refuse that a level can't show (the coordinator's failure spec, from ref_prep): `short`, under
 * 17 s of whole phrases sung (stopped early). `phrases`: [fromBeat, toBeat] pairs; the take's frame of the song's beat 0
 * is at `-startSeconds`. Null when the take passes. Pure. (No breath check before lines here: ai-modal is loosening the
 * worker's edge rule, and a client check on today's would only turn people away sooner; the coordinator, 2026-11-23.)
 */
export const SHORT_PHRASE_SECONDS = 17;
export function takeProblem(samples, sampleRate, { startSeconds = 0, beatSeconds, phrases = [] }) {
    if (!phrases.length || !(beatSeconds > 0)) return null;
    const songEnd = samples.length / sampleRate + startSeconds; // the song's time the take reached
    const whole = phrases.filter(([, to]) => to * beatSeconds <= songEnd).reduce((sum, [from, to]) => sum + (to - from) * beatSeconds, 0);
    return whole < SHORT_PHRASE_SECONDS ? 'short' : null;
}

/** 'low', 'loud' or 'ok' for a level, with a little room inside the worker's -35 to -6 dBFS. */
export function levelVerdict({ db, clipped }) {
    if (clipped || db > -8) return 'loud';
    if (db < -33) return 'low';
    return 'ok';
}

export function clipFrom(samples, sampleRate, startSeconds, maxSeconds = MAX_SECONDS) {
    const beat0 = Math.round(-startSeconds * sampleRate); // the take's frame at the song's beat 0
    const start = Math.max(0, beat0);
    const end = Math.min(samples.length, start + Math.min(maxSeconds, MAX_SECONDS) * sampleRate);
    const clip = samples.subarray(start, end);
    return { wav: encodeWav([clip], sampleRate), frames: clip.length, offsetFrames: beat0 >= 0 ? beat0 - start : null, seconds: clip.length / sampleRate };
}

const hex = (buffer) => [...new Uint8Array(buffer)].map((b) => b.toString(16).padStart(2, '0')).join('');

/**
 * Sends a take as a song's clip: the request, the upload, then complete. Resolves the request (queued); refusals are the
 * contract's ApiErrors (consent_required, quota_reached with `which`, request_open, account_required).
 */
export async function sendClip({ templateId, clipTemplateId = null, clipTranspose = 0, guide = true, octave = null, partKey, samples, sampleRate, startSeconds, hum = false, turnstileToken = null, clipSeconds = MAX_SECONDS, whole = false, auto = false }) {
    // A whole-song take (Hillel: compare you as the lead): its first stretch, to the tune's stop, is the voice's clip; the
    // whole take goes beside it as the lead (owner-only, deleted with the voice), its beat 0 where the clip's is.
    const clip = clipFrom(samples, sampleRate, startSeconds, clipSeconds);
    const leadWav = whole ? encodeWav([samples], sampleRate) : null;
    const leadBytes = leadWav ? await leadWav.arrayBuffer() : null;
    const beat0 = Math.round(-startSeconds * sampleRate);
    const leadBody = leadWav ? { lead: { sampleRate, frames: samples.length, bytes: leadBytes.byteLength, sha256: hex(await crypto.subtle.digest('SHA-256', leadBytes)), offsetFrames: beat0 >= 0 ? beat0 : 0 } } : {};
    if (hum) clip.offsetFrames = null; // a hum sets its own tempo: where the song's beat 0 falls in it isn't known
    const bytes = await clip.wav.arrayBuffer();
    const sha256 = hex(await crypto.subtle.digest('SHA-256', bytes));
    const { data: request, upload, leadUpload } = await api.ownChoir.requests.create({ templateId, ...(clipTemplateId ? { clipTemplateId } : {}), ...(clipTranspose ? { clipTranspose } : {}), ...(guide ? {} : { guide: false }), ...leadBody, ...(octave != null ? { octave } : {}), partKey, offsetFrames: clip.offsetFrames, sampleRate, frames: clip.frames, bytes: bytes.byteLength, sha256, ...(turnstileToken ? { turnstileToken } : {}), ...(auto ? { auto: true } : {}) });
    aiUse('choir_start'); // AI optional's measure: a choir song asked for, once a day (ai-choice.js)
    // The upload and complete, once more if the first try fails (the contract: send it again after a mismatch); if it still
    // fails, the request goes, so it never sits "on its way" and a new try isn't refused as one song at a time.
    const finish = async () => {
        await uploadTo(upload, clip.wav);
        if (leadWav && leadUpload) await uploadTo(leadUpload, leadWav);
        return (await api.ownChoir.requests.complete(request.id)).data;
    };
    let queued;
    try {
        queued = await finish().catch(finish);
    } catch (error) {
        await api.ownChoir.requests.delete(request.id).catch((cleanup) => console.warn('The unsent choir request was not removed', cleanup));
        throw error;
    }
    await loadOwnChoir();
    return queued;
}

/** Whether the terms need agreeing (again): none yet, or a newer version than the one agreed. */
export function termsNeeded() {
    const c = ownChoir.data?.consent;
    return !c || c.version < (ownChoir.data?.termsVersion ?? 0);
}


/** A song from the saved voice: one tap, no recording (the request comes back queued). */
export async function makeFromVoice(templateId, { turnstileToken = null } = {}) {
    const { data: queued } = await api.ownChoir.requests.create({ templateId, ...(turnstileToken ? { turnstileToken } : {}) });
    aiUse('choir_start');
    await loadOwnChoir();
    return queued;
}

/**
 * The choir wait (docs/decisions.md, 2026-11-24): a song the app started by itself waits a few seconds before it's made.
 * holdChoir keeps it waiting while they listen back (5 s each time, never past 2 minutes; 409 claimed once it's begun);
 * startChoir lets it go at once. Both answer with the song, kept in the store.
 */
export async function holdChoir(id) {
    const { data } = await api.ownChoir.requests.hold(id);
    keepRequest(data);
    return data;
}

export async function startChoir(id) {
    const { data } = await api.ownChoir.requests.start(id);
    keepRequest(data);
    return data;
}

/** One song as the server just said it, in place in the store. */
function keepRequest(request) {
    const list = ownChoir.data?.requests;
    const at = list?.findIndex((r) => r.id === request?.id) ?? -1;
    if (at >= 0) list.splice(at, 1, { ...list[at], ...request });
}

/** Deletes a song made from their singing: on the server, then its parts kept on this device. */
export async function deleteSong(request) {
    await api.ownChoir.requests.delete(request.id);
    await forgetParts(request);
    await loadOwnChoir();
}

/** Turns it all off: every song, any recording waiting, the consent; and every part kept here. */
export async function deleteEverything() {
    const requests = ownChoir.data?.requests ?? [];
    await api.ownChoir.delete();
    for (const request of requests) await forgetParts(request);
    // Their choir projects go too, as a single song's delete does, so Home never lists songs whose parts are gone.
    const { deleteProject, loadProjects, projects } = await import('./stores/projects.js');
    await loadProjects();
    for (const project of projects.all.filter((p) => p.choir)) await deleteProject(project.id).catch((error) => console.warn('A choir project was not removed', error));
    await loadOwnChoir({ force: true });
}

async function forgetParts(request) {
    for (const part of request.parts ?? []) {
        await db.remove('guides', guideKey(request.templateId, request.version, part.partKey, part.id)).catch((error) => console.warn('A choir part was not removed from the device', error));
    }
}

/** A ready song opened: its notice goes (once). */
export async function markSeen(request) {
    // Seen: the song ready, and any friend who left it since (a choir with friends: `left`).
    if (!request || (request.seen && !(request.left ?? []).some((l) => !l.seen))) return;
    request.seen = true;
    for (const gone of request.left ?? []) gone.seen = true;
    await api.ownChoir.requests.seen(request.id).catch((error) => console.warn('The choir notice was not marked seen', error));
}

/** The anonymous counts the app sends (contract: own_choir_heard, _sing, _replaced). */
export function countChoir(kind) {
    count(kind);
}

/**
 * Settings' "Your voice" row: shown only while a voice is kept, what it's for and until when, and Delete in a styled
 * confirmation naming exactly what goes (the voice) and what stays (the songs made).
 */
export const SavedVoiceRow = {
    components: { Icon },
    data: () => ({ busy: false }),
    setup: () => ({ ownChoir }),
    computed: {
        // Shown whenever a voice is kept, the switch on or not, so it can always be deleted (the security review).
        voice: () => savedVoice(),
        until: (vm) => (vm.voice?.expiresAt ? formatDate(vm.voice.expiresAt, { day: 'numeric', month: 'long', year: 'numeric' }) : ''),
    },
    created() {
        // Once the switch is known (a fresh page asks for auth.config at start): where the voice stands.
        loadAuthConfig().then(() => (account.me && !ownChoir.data ? loadOwnChoir({ force: true }) : null)).catch((error) => console.warn('Your voice did not load', error));
    },
    template: `
        <div v-if="voice" class="card flex flex-wrap items-center gap-4 p-5" data-saved-voice>
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-raised text-cream" aria-hidden="true"><Icon name="mic" :size="20" /></span>
            <span class="min-w-0 flex-1"><span class="block font-bold text-cream">{{ t('ownChoir.voice.title') }}</span><span class="block text-sm text-muted">{{ t('ownChoir.voice.line', { date: until }) }}</span></span>
            <button type="button" class="btn-ghost btn-sm text-bad" :disabled="busy" data-saved-voice-delete @click="remove">{{ t('ownChoir.voice.delete') }}</button>
        </div>`,
    methods: {
        async remove() {
            const yes = await confirmAction({ title: t('ownChoir.voice.deleteTitle'), body: t('ownChoir.voice.deleteBody'), action: t('ownChoir.voice.deleteAction') });
            if (!yes) return;
            this.busy = true;
            try {
                await api.ownChoir.voice.delete();
                await loadOwnChoir();
                toast(t('ownChoir.voice.deleted'));
            } catch (error) {
                console.warn('The saved voice was not deleted', error);
                toast(t('common.didntWork'), { tone: 'error' });
            } finally {
                this.busy = false;
            }
        },
    },
};
