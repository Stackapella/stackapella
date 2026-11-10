// The interval trainer for /tools (docs/tools-plan.md, batch 2; docs/tools/competitors-pipe-forks-drone-latency-ear.md
// section 5): hear two notes, up, down or together, and name the leap. The common is easy: five familiar
// intervals going up, with Up, Down and Together as chips. A level ladder adds the rest (thirds and sixths,
// then sixths and sevenths, then everything in an octave). After an answer: "Yours" and "Right" to hear both,
// and a song that starts with it (the page's own list, up and down). The rounds are the app's own
// (js/ear-rounds.js intervalRound, the guide voice); the card is js/public/tools/ear-quiz.js. The words come from the page.
//
//   import { mountIntervalTrainer, WORDS } from '/js/public/tools/interval-trainer.js';
//   const trainer = mountIntervalTrainer(el, { words, songs: { up: { P4: 'Here Comes the Bride' }, down: { ... } } });
//
// Options: direction ('up', 'down' or 'together'), low and high (MIDI), songs, store (false: keep nothing).

import { INTERVALS, intervalRound } from '../../ear-rounds.js';
import { fill, mountQuiz, noteName } from './ear-quiz.js';
import { SHARE_FILES } from './share-card.js';

export { INTERVALS };
export const DIRECTIONS = ['up', 'down', 'together'];
/** The ladder: each level's new intervals, from the five most learners know first. */
export const LEVELS = [['M2', 'M3', 'P4', 'P5', 'P8'], ['m3', 'M6'], ['m6', 'm7'], ['m2', 'TT', 'M7']];
export const LEVEL_WORDS = ['level1', 'level2', 'level3', 'level4'];
// The share card's drawing (share-card.js), kept offline by the tools' service worker (page.js).
export const WARM = SHARE_FILES;
// Written out in full, so a page's words test can read the list as it stands; tests/ear-trainers.test.js
// checks it is still the quiz's words, the intervals, directions and the levels.
export const WORDS = [
    'prompt', 'readySub', 'play', 'again', 'next', 'answers', 'right', 'youSaid', 'yours', 'rightOne', 'level', 'levelShort',
    'levelDown', 'levelUp', 'toNext', 'topLevel', 'goLevel', 'ownSet', 'statRight', 'statStreak', 'settings', 'settingsTitle', 'reset', 'done', 'practise',
    'fixedRoot', 'range', 'from', 'to', 'needOne', 'results', 'heard', 'missed', 'accuracy', 'keys', 'song', 'playNotes',
    'lastTen', 'share', 'shareText', 'cardTitle', 'cardScore', 'cardLine', 'cardChallenge', 'cardTagline', 'cardSite', 'cardSaved', 'm2',
    'M2', 'm3', 'M3', 'P4', 'TT', 'P5', 'm6', 'M6', 'm7', 'M7', 'P8', 'up', 'down', 'together', 'level1', 'level2', 'level3',
    'level4', 'semitones', 'showSemitones',
];

export function mountIntervalTrainer(root, { words = {}, direction = 'up', low = 48, high = 72, songs = {}, store = 'interval-ear-training', ...rest } = {}) {
    return mountQuiz(root, {
        words,
        choices: Object.keys(INTERVALS).map((key) => ({ key, label: words[key] ?? key })),
        levels: LEVELS.map((keys, i) => ({ keys, label: words[LEVEL_WORDS[i]] ?? '' })),
        modes: DIRECTIONS.map((key) => ({ key, label: words[key] ?? key })),
        mode: DIRECTIONS.includes(direction) ? direction : 'up',
        low,
        high,
        round: ({ key, mode, base, low: from, high: to, random }) => intervalRound({ key, way: mode, base, low: from, high: to, random }),
        // Each tile's size in semitones (Hillel's mockup), on unless turned off in Settings.
        sublabel: (key) => fill(words.semitones, { n: INTERVALS[key] }),
        toggles: [{ key: 'sublabels', label: words.showSemitones, on: true }],
        // What it was: its two notes, in the order they were sung.
        describe: (round) => {
            const [a, b] = round.clips[0].notes.length > 1 ? round.clips[0].notes : [round.clips[0].notes[0], round.clips[0].voices[0][0]];
            return `${noteName(round.base + a.step, { octave: true })} · ${noteName(round.base + b.step, { octave: true })}`;
        },
        reminder: (round) => songs[round.way === 'down' ? 'down' : 'up']?.[round.key] ?? '',
        // Eight of the last ten right offers a card to share, linking back here (?ref=share-interval-ear-training).
        share: { slug: 'interval-ear-training', tag: 'share-interval-ear-training', file: 'my-interval-ear-training.png', part: 4 },
        store: store || null,
        ...rest,
    });
}
