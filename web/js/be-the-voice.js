// Be the voice (Hillel, 2026-11-12: "it's now free for anyone to be the guide voice. Let's make it very clear ... in the
// song, which is missing a real part"; "make sure users can easily just record themselves, they should only need legal
// stuff if they then later decide to share it ... always be pleasant about it, not annoying"; docs/open-guide-voices-plan.md
// and the engagement review, shots-review/review-2026-11-12/be-the-voice-engagement-review.md).
//
// A library song's part that still plays the practice voice says so calmly: a "No voice yet" tag in Sing's part picker
// (day one), a line on the song's sheet (past the first week), and, after a usable take of it, one invitation under Sing's
// review. Record first, the legal part only when they share: Share it signs a guest in (sign-up "Save your alto and share
// it", straight back to the same take), then one gentle sheet (components/share-voice.js): three plain points, 18 or over,
// the credit with its preview, Share it or Keep it private for now. Every take already saves to the account, private.

import { account } from './stores/account.js';
import { catalogue, recordingFor } from './stores/catalogue.js';
import { daysSinceArrival } from './funnel.js';
import { learning } from './stores/learning.js';
import { local, session } from './storage.js';
import { count } from './usage.js';
import { Icon } from './components/icon.js';

/** The first week stays as it is: from the eighth day here, or three lessons in, whichever comes first. */
export function pastFirstWeek() {
    const days = daysSinceArrival();
    const lessons = Object.values(learning.progress.lessons ?? {}).filter((l) => l?.stars).length;
    return (days !== null && days >= 7) || lessons >= 3;
}

/** Whether the call is open at all: the server's lend_voice switch (auth.config `lendVoice`), and not turned down here. */
export function voiceAskOpen() {
    return !!account.config?.lendVoice && local.get(UNDER_18) !== '1';
}

/** Whether a catalogue part has a real voice: the catalogue's `hasRealGuide`, or its default guide where that isn't sent yet. */
const voiced = (templateId, part) => (typeof part.hasRealGuide === 'boolean' ? part.hasRealGuide : !!recordingFor(templateId, part.key));

/** Whether `partKey` of library song `templateId` still has no real voice (unknown songs: never asked). */
export function needsVoice(templateId, partKey) {
    const entry = catalogue.list.find((song) => song.id === templateId);
    if (!entry || !partKey) return false;
    const part = entry.parts?.find((p) => p.key === partKey);
    return !!part && !voiced(templateId, part);
}

/** The song's parts with no real voice yet, in its order (their keys). */
export function voicelessParts(templateId) {
    const entry = catalogue.list.find((song) => song.id === templateId);
    return (entry?.parts ?? []).filter((p) => !voiced(templateId, p)).map((p) => p.key);
}

/** The lowest on-pitch share a take is invited at (the review: "when it shows, the compliment is true"). */
export const USABLE_PERCENT = 70;

/**
 * Whether a take could be used as the part's guide, checked quietly before anything is asked: the whole part (at least 90%
 * of its length), in the song's own key, scored by the on-device checks at USABLE_PERCENT or more, with no bleed from
 * speakers. `take`: { seconds, partSeconds, transpose, score, bleed, speakers }.
 */
export function usableTake({ seconds, partSeconds, transpose, score, bleed, speakers }) {
    if (transpose || speakers || bleed) return false;
    if (!(partSeconds > 0) || !(seconds >= partSeconds * 0.9)) return false;
    return score?.status === 'scored' && score.percent >= USABLE_PERCENT;
}

// "Not now": that part, 14 days (the review: a part drilled ten times isn't asked ten times).
const LATER = 'stackapella.voiceLater'; // { [templateId:partKey]: at }
export const LATER_DAYS = 14;
const UNDER_18 = 'stackapella.voiceUnder18'; // '1': a singer under 18 said so; the call stops on this device
const SESSION = 'stackapella.voiceAsked'; // the session's one invitation: the part it went to

function laterMap() {
    try {
        const kept = JSON.parse(local.get(LATER) || '{}');
        return kept && typeof kept === 'object' ? kept : {};
    } catch (error) {
        console.info('Be the voice: the not-now list was unreadable', error?.message ?? error);
        return {};
    }
}

/** Whether the singer said Not now to this part in the last 14 days. */
export function saidLater(templateId, partKey, now = Date.now()) {
    const at = Number(laterMap()[`${templateId}:${partKey}`] ?? 0);
    return at > 0 && now - at < LATER_DAYS * 86400000;
}

/** Not now, for this part: 14 days. */
export function later(templateId, partKey, now = Date.now()) {
    local.set(LATER, JSON.stringify({ ...laterMap(), [`${templateId}:${partKey}`]: now }));
    count('bv_later');
}

/** One invitation a session (share-moment.js's rule): the first part to ask keeps it; the same part may show it again. */
export function claimInvitation(templateId, partKey) {
    const id = `${templateId}:${partKey}`;
    const held = session.get(SESSION);
    if (!held) {
        session.set(SESSION, id);
        return true;
    }
    return held === id;
}

/** Be the voice's anonymous counts (the review's section 5): each a kind usage.js and config/counts.php know. */
export function countVoice(kind) {
    count(kind);
}

/** A singer under 18 (the sheet): thanked, the take kept, and the call stops on this device. */
export function under18() {
    local.set(UNDER_18, '1');
    count('bv_stop:age');
}

/**
 * The invitation under a usable take's review: true words ("That alto sounded good"), what it could become, and for a
 * guest that it saves to a free account first, private. Share it, or Not now. Never a pop-up.
 */
export const BeTheVoice = {
    components: { Icon },
    props: { part: { type: String, required: true }, guest: Boolean },
    emits: ['share', 'later'],
    template: `
        <div class="card flex flex-col gap-3 p-4 sm:flex-row sm:items-center" data-be-the-voice>
            <span class="grid size-11 shrink-0 place-items-center rounded-full bg-raised text-accent-strong" aria-hidden="true"><Icon name="mic" :size="20" /></span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-cream" translate="no">{{ t('voices.be.good', { part }) }}</p>
                <p class="text-sm text-muted">{{ t('voices.be.invite') }}</p>
                <p v-if="guest" class="mt-1 text-sm text-muted" data-be-the-voice-guest>{{ t('voices.be.guestSaves') }}</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <button type="button" class="btn-secondary" data-be-the-voice-share @click="$emit('share')">{{ t('voices.be.share') }}</button>
                <button type="button" class="btn-ghost" data-be-the-voice-later @click="$emit('later')">{{ t('voices.be.later') }}</button>
            </div>
        </div>`,
};

/** The quiet mark on a part with no voice yet (Sing's part picker): a label, not a link. */
export const NoVoiceTag = {
    template: `<span class="rounded-full bg-raised px-2 py-0.5 text-[10px] font-semibold text-muted" data-no-voice>{{ t('voices.be.tag') }}</span>`,
};
