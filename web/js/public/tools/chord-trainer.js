// The chord trainer for /tools (docs/tools-plan.md, batch 2; docs/tools/competitors-pipe-forks-drone-latency-ear.md
// section 6): hear a chord and name its quality. The common is easy: major or minor, played together. A
// level ladder adds diminished and augmented, then the sevenths; Up and Down play it broken, and
// inversions are one tap away. After an answer: "Yours" and "Right" to hear both. The chord shapes are the
// app's own (js/theory.js QUALITIES through js/ear-rounds.js chordRound, the guide voice, one singer a
// note); the card is js/public/tools/ear-quiz.js. The words come from the page.
//
//   import { mountChordTrainer, WORDS } from '/js/public/tools/chord-trainer.js';
//   const trainer = mountChordTrainer(el, { words });
//
// Options: style ('together', 'up' or 'down'), low and high (MIDI), store (false: keep nothing).

import { CHORDS, chordRound } from '../../ear-rounds.js';
import { mountQuiz, noteName } from './ear-quiz.js';
import { SHARE_FILES } from './share-card.js';

export { CHORDS };
export const STYLES = ['together', 'up', 'down'];
/** The ladder: major and minor, then diminished and augmented, then the common sevenths, then the rest. */
export const LEVELS = [['maj', 'min'], ['dim', 'aug'], ['dom7', 'maj7', 'min7'], ['hdim7', 'dim7']];
export const LEVEL_WORDS = ['level1', 'level2', 'level3', 'level4'];
// The share card's drawing (share-card.js), kept offline by the tools' service worker (page.js).
export const WARM = SHARE_FILES;
// Written out in full, so a page's words test can read the list as it stands; tests/ear-trainers.test.js
// checks it is still the quiz's words, the chords, styles and the levels.
export const WORDS = [
    'prompt', 'readySub', 'play', 'again', 'next', 'answers', 'right', 'youSaid', 'yours', 'rightOne', 'level', 'levelShort',
    'levelDown', 'levelUp', 'toNext', 'topLevel', 'goLevel', 'ownSet', 'statRight', 'statStreak', 'settings', 'settingsTitle', 'reset', 'done', 'practise',
    'fixedRoot', 'range', 'from', 'to', 'needOne', 'results', 'heard', 'missed', 'accuracy', 'keys', 'song', 'playNotes',
    'lastTen', 'share', 'shareText', 'cardTitle', 'cardScore', 'cardLine', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved', 'maj',
    'min', 'dim', 'aug', 'dom7', 'maj7', 'min7', 'hdim7', 'dim7', 'together', 'up', 'down', 'level1', 'level2', 'level3',
    'level4', 'inversions', 'promptMajorMinor',
];

export function mountChordTrainer(root, { words = {}, style = 'together', low = 48, high = 69, store = 'chord-ear-training', ...rest } = {}) {
    return mountQuiz(root, {
        words,
        choices: CHORDS.map((key) => ({ key, label: words[key] ?? key })),
        levels: LEVELS.map((keys, i) => ({ keys, label: words[LEVEL_WORDS[i]] ?? '', ...(i === 0 && words.promptMajorMinor ? { prompt: words.promptMajorMinor } : {}) })),
        modes: STYLES.map((key) => ({ key, label: words[key] ?? key })),
        mode: STYLES.includes(style) ? style : 'together',
        low,
        high,
        toggles: [{ key: 'inversions', label: words.inversions }],
        round: ({ key, mode, base, inversion, inversions, low: from, high: to, random }) => chordRound({ key, style: mode, base, inversion: inversion ?? (inversions ? 'any' : 0), low: from, high: to, random }),
        // Hillel's mockup: the chord's notes lit on a small keyboard, and named, once it's answered.
        keyboard: true,
        describe: (round, notes) => notes.map((n) => noteName(n)).join(' · '),
        // Eight of the last ten right offers a card to share, linking back here (?ref=share-chord-ear-training).
        share: { slug: 'chord-ear-training', tag: 'share-chord-ear-training', file: 'my-chord-ear-training.png', part: 1 },
        store: store || null,
        ...rest,
    });
}
