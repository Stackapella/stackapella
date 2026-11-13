// A part said in words before its notes (the first-session review, 2026-11-13, proposal 2; Hillel: yes): "The tune, the
// highest part", "Just under the tune", "Under the alto, often men", "The lowest part". A newcomer reads where a part sits
// before D4 or G2 means anything. From the song's own parts, by where each sits (its median note), so it holds for any
// arrangement: SATB, a barbershop tenor above the tune, a round, a duet. Sing's Which part is yours?, and game's cards.

import { locale, t, tk } from './i18n.js';
import { templatePartName } from './templates.js';

const WORDS = {
    tune: tk('parts.words.tune'),
    tuneHighest: tk('parts.words.tuneHighest'),
    tuneLowest: tk('parts.words.tuneLowest'),
    lowest: tk('parts.words.lowest'),
    aboveTune: tk('parts.words.aboveTune'),
    underTune: tk('parts.words.underTune'),
    under: tk('parts.words.under'),
    underMen: tk('parts.words.underMen'),
};
const MOSTLY_MEN = new Set(['tenor', 'baritone']); // as choirs cast them; said "often", never "only"

function median(part) {
    const pitches = (part.guide?.notes ?? []).map((n) => n.pitch).sort((a, b) => a - b);
    return pitches.length ? pitches[pitches.length >> 1] : null;
}

/** Which words describe `partKey` in `template` (a key under parts.words), or null when its parts can't be placed. */
export function partWordsKey(template, partKey) {
    const parts = (template?.parts ?? []).filter((p) => median(p) !== null);
    const part = parts.find((p) => p.key === partKey);
    if (!part) return null;
    if (parts.length < 2) return part.melody ? { key: 'tune' } : null;
    const high = [...parts].sort((a, b) => median(b) - median(a)); // highest first
    const at = high.indexOf(part);
    const tune = parts.find((p) => p.melody) ?? null;
    if (part === tune) return { key: at === 0 ? 'tuneHighest' : at === high.length - 1 ? 'tuneLowest' : 'tune' };
    if (at === high.length - 1) return { key: 'lowest' };
    if (tune && median(part) > median(tune)) return { key: 'aboveTune' };
    const above = high[at - 1];
    if (above === tune) return { key: 'underTune' };
    return { key: MOSTLY_MEN.has(part.key) ? 'underMen' : 'under', above };
}

/** The words for a part, in the singer's language ("Under the alto, often men"), or '' when there are none. */
export function partWords(template, partKey) {
    const found = partWordsKey(template, partKey);
    if (!found) return '';
    // "Under the alto": a part named mid-sentence, lower-case where the language writes it so (German keeps its capital).
    const name = found.above ? templatePartName(found.above) : '';
    return t(WORDS[found.key], found.above ? { part: locale.lang.startsWith('de') ? name : name.toLocaleLowerCase(locale.lang) } : {});
}
