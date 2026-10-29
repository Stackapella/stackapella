// Words for the template creator (docs/template-creator-plan.md, "Syllables and fitting"): pasted
// words split into syllables the way the templates write them ("A-" "maz-" "ing"), by built-in
// English rules with a few exceptions (the Liang hyphenation patterns would need Hillel's OK as a
// new library; other languages are split by hand), and a line's syllables fitted to the notes sung
// for it: one each, extra notes held as melismas where they sit best (long notes, steps within a
// word), and too few notes answered with the note to split. Pure, unit-tested.

const VOWELS = new Set(['a', 'e', 'i', 'o', 'u', 'y']);
const DIGRAPHS = ['ch', 'sh', 'th', 'ph', 'wh', 'ck', 'ng', 'gh', 'qu'];
const ONSETS = new Set(['bl', 'br', 'cl', 'cr', 'dr', 'fl', 'fr', 'gl', 'gr', 'pl', 'pr', 'sc', 'sk', 'sl', 'sm', 'sn', 'sp', 'st', 'sw', 'tr', 'tw', 'wr', 'thr', 'shr', 'str', 'spr', 'scr', 'squ']);
// Words the rules get wrong, as they're sung.
const EXCEPTIONS = { every: ['ev', 'ery'], heaven: ['heav', 'en'], over: ['o', 'ver'], fire: ['fire'], hour: ['hour'], our: ['our'], lord: ['lord'], world: ['world'], people: ['peo', 'ple'], little: ['lit', 'tle'] };

const isVowel = (word, i) => VOWELS.has(word[i]) && !(word[i] === 'y' && (i === 0 || VOWELS.has(word[i - 1])) && i < word.length - 1);

/** Consonant units: digraphs count as one ("ch", "th"). */
function units(cluster) {
    const out = [];
    for (let i = 0; i < cluster.length; ) {
        const two = cluster.slice(i, i + 2);
        if (DIGRAPHS.includes(two)) {
            out.push(two);
            i += 2;
        } else {
            out.push(cluster[i]);
            i += 1;
        }
    }
    return out;
}

/** One word (letters only, lower case) split into syllables. */
function splitWord(word) {
    if (EXCEPTIONS[word]) return [...EXCEPTIONS[word]];
    if (word.length <= 3) return [word];
    // Suffixes sung as their own syllable.
    for (const suffix of ['ing', 'ings']) {
        if (word.endsWith(suffix) && word.length > suffix.length + 2) return [...splitWord(word.slice(0, -suffix.length)), suffix];
    }
    // Vowel groups, a silent final e (not after "l": "ta-ble"), and "-ed" heard only after t or d.
    let end = word.length;
    if (word.endsWith('e') && !word.endsWith('le') && !isVowel(word, end - 2) && [...word.slice(0, -1)].some((_, i) => isVowel(word, i))) end -= 1;
    if (word.endsWith('ed') && !/[td]ed$/.test(word)) end = word.length - 2;
    const groups = [];
    for (let i = 0; i < end; i++) {
        if (!isVowel(word, i)) continue;
        const start = i;
        while (i + 1 < end && isVowel(word, i + 1)) i++;
        groups.push([start, i + 1]);
    }
    if (groups.length <= 1) return [word];
    const cuts = [];
    for (let g = 0; g < groups.length - 1; g++) {
        const from = groups[g][1];
        const to = groups[g + 1][0];
        const cluster = units(word.slice(from, to));
        if (!cluster.length) {
            cuts.push(from); // two vowel groups side by side: between them
            continue;
        }
        if (cluster.length === 1) {
            cuts.push(from); // V-CV
            continue;
        }
        // The longest ending of the cluster that can start a syllable goes right; at least one unit.
        let right = 1;
        for (let k = cluster.length - 1; k >= 1; k--) {
            if (ONSETS.has(cluster.slice(k).join(''))) right = Math.max(right, cluster.length - k);
        }
        cuts.push(to - cluster.slice(cluster.length - right).join('').length);
    }
    const out = [];
    let at = 0;
    for (const cut of cuts) {
        out.push(word.slice(at, cut));
        at = cut;
    }
    out.push(word.slice(at));
    return out.filter(Boolean);
}

/**
 * A line of words as syllables, the templates' way: each keeps its spelling and punctuation, and
 * every syllable but a word's last ends with "-" ("A-", "maz-", "ing", "grace,").
 */
export function syllables(line) {
    const out = [];
    for (const raw of line.trim().split(/\s+/).filter(Boolean)) {
        const letters = raw.toLowerCase().replace(/[^a-z']/g, '');
        if (!letters) {
            out.push(raw);
            continue;
        }
        const parts = splitWord(letters.replace(/'/g, ''));
        // Map the parts back onto the word as written (capitals, apostrophes, punctuation stay).
        const bounds = new Set();
        let total = 0;
        for (const part of parts.slice(0, -1)) bounds.add((total += part.length));
        const pieces = [];
        let piece = '';
        let count = 0;
        for (const c of raw) {
            piece += c;
            if (/[a-z]/i.test(c) && bounds.has(++count)) {
                pieces.push(piece);
                piece = '';
            }
        }
        pieces.push(piece);
        pieces.forEach((piece, k) => out.push(k < pieces.length - 1 ? `${piece}-` : piece));
    }
    return out;
}

/**
 * Fits a line's syllables to its notes ({ beat, length, pitch }): `{ lyrics }` with each note's
 * syllable (null for a melisma's held notes), or `{ mismatch: { syllables, notes }, split }` with the
 * note to split when there are fewer notes than syllables.
 */
export function fitLine(sylls, notes) {
    const S = sylls.length;
    const N = notes.length;
    if (S > N) {
        const longest = notes.reduce((best, n, i) => (n.length > notes[best].length ? i : best), 0);
        return { mismatch: { syllables: S, notes: N }, split: longest };
    }
    if (!S) return { lyrics: notes.map(() => null) };
    // A held note's cost: short notes and leaps are poor melismas; a word's last syllable is where
    // a held note usually falls ("grace" over two notes), so it's a little cheaper there.
    const hold = (j, midWord) => (notes[j].length < 0.5 ? 2 : 1) + Math.min(3, Math.abs(notes[j].pitch - notes[j - 1].pitch) / 2) - (midWord ? 0 : 0.25);
    const INF = 1e9;
    const cost = Array.from({ length: S + 1 }, () => new Array(N + 1).fill(INF));
    const from = Array.from({ length: S + 1 }, () => new Array(N + 1).fill(-1));
    cost[0][0] = 0;
    for (let i = 1; i <= S; i++) {
        const word = sylls[i - 1].endsWith('-'); // the next syllable is in the same word
        for (let j = i; j <= N - (S - i); j++) {
            // Syllable i takes notes k..j-1 (k is its first).
            let held = 0;
            for (let k = j - 1; k >= i - 1; k--) {
                if (k < j - 1) held += hold(k + 1, word);
                const c = cost[i - 1][k] + held;
                if (c < cost[i][j]) {
                    cost[i][j] = c;
                    from[i][j] = k;
                }
            }
        }
    }
    const lyrics = new Array(N).fill(null);
    for (let i = S, j = N; i > 0; i--) {
        const k = from[i][j];
        lyrics[k] = sylls[i - 1];
        j = k;
    }
    return { lyrics };
}
