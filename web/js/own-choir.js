// Hear yourself as a choir (track B, phase 3; docs/own-choir-app-plan.md; contract: ownChoir.*). Behind own_choir
// (auth.config `ownChoir`, per account): a singer's 30 seconds, sung along in Sing on a project made for it, come back as
// every part of that song in a voice like theirs, the project's own guides, labelled as AI. The clip goes once its song is
// made; the voice prepared from it is kept privately for their next songs until they delete it (Settings) or a year
// unused. The parts made are the singer's, kept on this device to play offline, and deleted here with the song, on
// signing out, or when the server no longer has their song.

import { reactive } from 'vue';
import { local } from './storage.js';
import { api, uploadTo } from './api.js';
import * as db from './db.js';
import { account, loadAccount, loadAuthConfig } from './stores/account.js';
import { guideKey } from './recorded-guides.js';
import { encodeWav } from './wav.js';
import { count } from './usage.js';
import { formatDate, formatNumber, locale, t } from './i18n.js';
import { templatePartName } from './templates.js';
import { confirmAction } from './components/confirm.js';
import { toast } from './components/ui.js';
import { Icon } from './components/icon.js';
import { TurnstileBox } from './turnstile.js';
import { Switch } from './components/controls.js';

export const MIN_SECONDS = 15; // shorter, the card asks for a little longer (ownChoir.record.short)
export const MAX_SECONDS = 35; // the contract's most (and at least 5): the voice-capture song runs about 30 s
const OPEN = ['uploading', 'queued', 'rendering'];

/** Whether it's on for this account: auth.config `ownChoir`, known to be true. */
export function ownChoirOn() {
    return account.config?.ownChoir === true;
}

/** Whether their own songs can be heard in their voice too (auth.config `ownChoirSongs`, the own_choir_songs switch). */
export function ownChoirSongsOn() {
    return ownChoirOn() && account.config?.ownChoirSongs === true;
}

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
// the hidden `voice-capture` song (Happy Birthday, then For He's a Jolly Good Fellow). The server lists them, in order,
// the first the default, each with where the take stops (`stopBeat`, a phrase start; null for the whole tune).
export const CLIP_TEMPLATE = 'voice-capture';
const CLIP_CHOICE = 'stackapella.clipSong';

/** The tunes a clip may sing, as the server lists them (the default alone until it has said). */
export function clipSongs() {
    const listed = ownChoir.data?.clipSongs;
    return listed?.length ? listed : [{ templateId: CLIP_TEMPLATE, stopBeat: null }];
}

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

/** The four tunes to sing for the 30 seconds, as one quiet row of choices (the choice is remembered). */
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
        <div v-if="songs.length > 1" class="space-y-2" data-clip-choice>
            <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ t('ownChoir.intro.choose') }}</p>
            <div class="grid grid-cols-2 gap-2" role="group" :aria-label="t('ownChoir.intro.choose')">
                <button v-for="song in songs" :key="song.templateId" type="button" class="min-h-11 rounded-2xl border px-4 py-2.5 text-start"
                    :class="picked === song.templateId ? 'border-accent bg-raised' : 'border-line hover:border-muted'" :aria-pressed="picked === song.templateId ? 'true' : 'false'"
                    :data-clip-song="song.templateId" @click="chooseClip(song.templateId)">
                    <span class="block text-[15px] font-semibold text-cream">{{ clipPick.titles[song.templateId] ?? '' }}</span>
                    <span v-if="song.templateId === CLIP_TEMPLATE" class="block text-xs text-muted">{{ t('ownChoir.intro.thenJolly') }}</span>
                </button>
            </div>
            <Switch v-if="songId && picked === songId && !clipPick.noHeadphones" :on="clipPick.whole" :label="t('ownChoir.intro.whole')" :hint="t('ownChoir.intro.wholeHint')" class="pt-2" data-clip-whole @change="clipPick.whole = $event" />
        </div>`,
    methods: { chooseClip },
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

/** The singer's consent, quota and songs (ownChoir.show), once loaded; null while off or unknown. */
export const ownChoir = reactive({ data: null, loaded: false });

/** Loads (or reloads) where things stand; quietly nothing while it's off. */
export async function loadOwnChoir({ force = false } = {}) {
    // `force`: even with the switch off, so someone with a kept voice or songs can still see and delete them. A guest has
    // no choir: nothing is asked for (it would only answer 401, a page error on every guest page). Who's signed in is
    // known first (the app's own account check, shared, never a second request).
    if (!account.loaded) await loadAccount().catch((error) => console.warn('The account was not checked', error));
    if ((!ownChoirOn() && !force) || !account.me) {
        ownChoir.data = null;
        ownChoir.loaded = true;
        return null;
    }
    try {
        ownChoir.data = (await api.ownChoir.show()).data;
        forgetGone().catch((error) => console.warn('Old choir parts were not cleared', error));
    } catch (error) {
        if (error?.code !== 'not_found') console.warn('Hear yourself as a choir did not load', error);
        if (error?.code === 'not_found') ownChoir.data = null; // off for this person: nothing of theirs to show
    } finally {
        ownChoir.loaded = true;
    }
    return ownChoir.data;
}

/** Every choir part this device keeps, from the projects that kept them: `[{ project, request }]`. */
async function keptChoirs() {
    const { loadProjects, projects } = await import('./stores/projects.js');
    await loadProjects();
    return projects.all.filter((p) => p.choir?.parts?.length).map((p) => ({ project: p, request: p.choir }));
}

/** Drops the parts of songs the server no longer has (deleted on another device, or with the account). */
async function forgetGone() {
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

/** Whether a choir with friends is on for this account (auth.config `ownChoirTogether`, only when known to be true). */
export function togetherOn() {
    return account.config?.ownChoirTogether === true;
}

/** Part keys as the page's language lists them, lower case: "soprano and alto", "soprano y contralto". */
export function partList(keys, template = null) {
    const names = (keys ?? []).map((key) => {
        const part = template?.parts?.find((p) => p.key === key) ?? { key, name: key };
        return templatePartName(part).toLocaleLowerCase(locale.lang);
    });
    try {
        return new Intl.ListFormat(locale.lang, { style: 'long', type: 'conjunction' }).format(names);
    } catch {
        return names.join(', ');
    }
}

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

/** Whether a request is still being made. */
export const isOpen = (request) => !!request && OPEN.includes(request.status);

/** The ready songs not yet heard: Home's one quiet notice each. */
export function readyUnseen() {
    return (ownChoir.data?.requests ?? []).filter((r) => r.status === 'ready' && !r.seen);
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

/** What a choir project keeps of its song: the request, its version and parts (to play offline). */
export function choirOf(request) {
    return { requestId: request.id, templateId: request.templateId, version: request.version, parts: request.parts.map((p) => ({ ...p })) };
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
export async function sendClip({ templateId, clipTemplateId = null, clipTranspose = 0, guide = true, octave = null, partKey, samples, sampleRate, startSeconds, hum = false, turnstileToken = null, clipSeconds = MAX_SECONDS, whole = false }) {
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
    const { data: request, upload, leadUpload } = await api.ownChoir.requests.create({ templateId, ...(clipTemplateId ? { clipTemplateId } : {}), ...(clipTranspose ? { clipTranspose } : {}), ...(guide ? {} : { guide: false }), ...leadBody, ...(octave != null ? { octave } : {}), partKey, offsetFrames: clip.offsetFrames, sampleRate, frames: clip.frames, bytes: bytes.byteLength, sha256, ...(turnstileToken ? { turnstileToken } : {}) });
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

/** The voice kept for their next songs (Hillel: "store a model to make it easier"), or null. */
export function savedVoice() {
    return ownChoir.data?.voice ?? null;
}

/** A song from the saved voice: one tap, no recording (the request comes back queued). */
export async function makeFromVoice(templateId, { turnstileToken = null } = {}) {
    const { data: queued } = await api.ownChoir.requests.create({ templateId, ...(turnstileToken ? { turnstileToken } : {}) });
    await loadOwnChoir();
    return queued;
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
 * The card under a choir project's take (Sing's review), in place of Be the voice's invitation: Make my choir, or, under
 * 15 seconds, why not yet and Sing again. `seconds`: the take's length; `busy` while it sends; `error`: words to show.
 */
export const ChoirCard = {
    components: { TurnstileBox },
    // siteKey: the check before the account's first song (the server's `checkNeeded`, only with a site key set).
    // join: a friend's tune for an invite: the button leads to the yes (nothing is sent from here).
    // sent: the request this take became, sent by itself (from /your-choir): its parts' states while it's made, then Listen.
    props: { seconds: { type: Number, required: true }, busy: Boolean, error: { type: String, default: '' }, siteKey: { type: String, default: null }, join: Boolean, sent: { type: Object, default: null }, check: { type: String, default: null } },
    // check: 'quiet' or 'clipped', a take the worker would likely refuse: Sing it again, or Send it anyway (`force`).
    emits: ['make', 'again', 'listen', 'force'],
    data: () => ({ token: null, checkKey: 0 }),
    setup: () => ({ MIN_SECONDS }),
    watch: {
        // A refused check (a token works once): a fresh one.
        error() {
            if (this.siteKey) {
                this.token = null;
                this.checkKey += 1;
            }
        },
    },
    computed: {
        ready() {
            return this.sent?.status === 'ready';
        },
        progress() {
            return this.sent?.progress ?? [];
        },
        readyCount() {
            return this.progress.filter((p) => p.status === 'ready').length;
        },
    },
    methods: {
        partLabel(key) {
            return templatePartName({ key, name: key });
        },
        statusWord(status) {
            return status === 'ready' ? t('ownChoir.wait.part.ready') : status === 'making' ? t('ownChoir.wait.part.making') : t('ownChoir.wait.part.waiting');
        },
        dot(status) {
            if (status === 'ready') return 'bg-good';
            if (status === 'making') return matchMedia('(prefers-reduced-motion: reduce)').matches ? 'bg-accent' : 'bg-accent animate-pulse';
            return 'border border-muted';
        },
    },
    template: `
        <!-- Sent by itself (Hillel: from /your-choir it starts at once): made while they listen back, then Listen. -->
        <div v-if="sent" class="card space-y-3 p-4" role="status" data-choir-sent :data-state="sent.status">
            <p class="font-bold text-cream">{{ ready ? t('ownChoir.record.readyNow') : t('ownChoir.wait.title') }}</p>
            <ul v-if="!ready && progress.length" class="divide-y divide-line">
                <li v-for="p in progress" :key="p.partKey" class="flex min-h-11 items-center justify-between gap-3 py-1" :data-choir-part="p.partKey" :data-choir-part-status="p.status">
                    <span class="font-semibold text-cream">{{ partLabel(p.partKey) }}</span>
                    <span class="inline-flex items-center gap-2 text-sm"><span class="size-2.5 shrink-0 rounded-full" :class="dot(p.status)" aria-hidden="true"></span><span :class="p.status === 'waiting' ? 'text-muted' : 'text-cream'">{{ statusWord(p.status) }}</span></span>
                </li>
            </ul>
            <p v-if="!ready && progress.length" class="border-t border-line pt-3 text-sm text-muted tabular-nums">{{ t('ownChoir.wait.partsReady', { n: readyCount, count: progress.length }) }}</p>
            <button v-if="ready" type="button" class="btn-primary" data-choir-listen @click="$emit('listen')">{{ t('ownChoir.record.listen') }}</button>
        </div>
        <div v-else-if="check" class="card space-y-3 p-4" role="alert" :data-choir-check="check">
            <p class="text-cream">{{ check === 'clipped' ? t('ownChoir.check.clipped') : t('ownChoir.check.quiet') }}</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary" data-choir-again @click="$emit('again')">{{ t('ownChoir.check.again') }}</button>
                <button type="button" class="btn-secondary" :disabled="busy" data-choir-force @click="$emit('force', token)">{{ t('ownChoir.check.send') }}</button>
            </div>
        </div>
        <div v-else class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-center" data-choir-card>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-cream">{{ t('ownChoir.title') }}</p>
                <p class="text-sm text-muted" :role="error ? 'alert' : null">{{ error || (seconds < MIN_SECONDS ? t('ownChoir.record.short') : t('ownChoir.wait.kept')) }}</p>
            </div>
            <TurnstileBox v-if="siteKey && seconds >= MIN_SECONDS" :key="checkKey" class="sm:basis-full" :site-key="siteKey" @token="token = $event" @failed="token = null" data-choir-check />
            <div class="flex shrink-0 flex-wrap gap-2">
                <button v-if="seconds >= MIN_SECONDS" type="button" class="btn-secondary" :disabled="busy || (!!siteKey && !token)" :aria-busy="busy ? 'true' : null" data-choir-make @click="$emit('make', token)"><span v-if="busy" class="spinner" aria-hidden="true"></span>{{ busy ? t('ownChoir.record.sending') : join ? t('ownChoir.together.join.next') : t('ownChoir.record.use') }}</button>
                <button v-else type="button" class="btn-secondary" data-choir-again @click="$emit('again')">{{ t('ownChoir.record.again') }}</button>
            </div>
        </div>`,
};

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
