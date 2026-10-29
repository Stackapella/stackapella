// How it went (docs/vocal-coaching-plan.md, "The report after singing"): a take's score as the
// history keeps it (numbers only, never audio), the change since last time, and personal bests by
// phrase. Pure: js/scoring.js scores, the screens word it.

import { stars } from './scoring.js';

/** Stars for one phrase's percent: as a take's, without the timing (a phrase has no entries of its own). */
export function phraseStars(percent) {
    if (percent === null || percent === undefined) return 0;
    return percent >= 90 ? 3 : percent >= 70 ? 2 : 1;
}

/** What's kept of a score (`takes.create`'s `scores`, `scores.create`'s `summary`): at most a few hundred bytes. */
export function scoreSummary(score) {
    if (!score || score.status !== 'scored') return { pitchPercent: null, stars: 0, level: null, phrases: [] };
    return {
        pitchPercent: score.percent,
        stars: stars(score).stars,
        level: score.word,
        tendency: Math.round(score.tendency ?? 0),
        entryMs: score.entryMs,
        insights: score.insights,
        phrases: score.phrases.filter((p) => p.percent !== null).map((p) => ({ index: p.index, pitchPercent: p.percent })),
    };
}

/** "+6% since last time": the change in percent, or null with nothing to compare. */
export function sinceLast(previous, now) {
    if (previous?.pitchPercent === null || previous?.pitchPercent === undefined || now?.pitchPercent === null || now?.pitchPercent === undefined) return null;
    return now.pitchPercent - previous.pitchPercent;
}

/**
 * Personal bests by phrase index (`{ [index]: percent }`) with this take's: the new bests, and the
 * phrases that beat theirs (a first try isn't a "best" to celebrate).
 */
export function phraseBests(bests = {}, phrases = []) {
    const next = { ...bests };
    const improved = [];
    for (const p of phrases) {
        if (p.pitchPercent === null || p.pitchPercent === undefined) continue;
        const had = bests[p.index];
        if (had === undefined || p.pitchPercent > had) next[p.index] = p.pitchPercent;
        if (had !== undefined && p.pitchPercent > had) improved.push(p.index);
    }
    return { bests: next, improved };
}

// ---- The review after a take (Hillel's 79.png): phrase by phrase, and the one thing to try ----

/** What a note's trouble was, plainly: wavered (not steady), scooped in, or off (not near it). */
function troubleOf(result) {
    if (!result || result.level === 'unheard') return null;
    if (result.level === 'notYet') return 'off';
    if (result.steady === false) return 'wavered';
    if (result.scoop) return 'scooped';
    return null;
}

/**
 * Each sung phrase with its percent and its notes in trouble: `[{ index, percent, troubles:
 * [{ note, lyric, kind }] }]`, `note` the note's index. `notes` are the part's notes (with their
 * lyrics); `phrases` `[startBeat, endBeat]`.
 */
export function phraseReview(score, notes, phrases) {
    if (!score || score.status !== 'scored') return [];
    return score.phrases
        .filter((p) => p.percent !== null)
        .map((p) => {
            const [start, end] = phrases[p.index] ?? [-Infinity, Infinity];
            const troubles = score.notes
                .filter((r) => notes[r.index] && notes[r.index].beat >= start - 1e-9 && notes[r.index].beat < end - 1e-9)
                .map((r) => ({ note: r.index, lyric: (notes[r.index].lyric ?? '').replace(/-$/, '').trim() || null, kind: troubleOf(r) }))
                .filter((x) => x.kind);
            return { index: p.index, percent: p.percent, troubles };
        });
}

/**
 * The one thing to try: the phrase with the most trouble (the lowest percent among those with
 * any), the kind most of its notes had, and up to two of their words. Null when it all went well.
 */
export function oneThing(review) {
    const troubled = review.filter((p) => p.troubles.length);
    if (!troubled.length) return null;
    const phrase = [...troubled].sort((a, b) => a.percent - b.percent || b.troubles.length - a.troubles.length)[0];
    const counts = {};
    for (const x of phrase.troubles) counts[x.kind] = (counts[x.kind] ?? 0) + 1;
    const kind = Object.keys(counts).sort((a, b) => counts[b] - counts[a])[0];
    const words = [...new Set(phrase.troubles.filter((x) => x.kind === kind && x.lyric).map((x) => x.lyric))].slice(0, 2);
    return { phrase: phrase.index, kind, words, count: counts[kind] };
}
