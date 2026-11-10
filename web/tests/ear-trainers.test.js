// The ear trainers (js/ear-rounds.js, shared with the lessons' listen-and-choose steps; the public card
// js/public/tools/ear-quiz.js with interval-trainer.js and chord-trainer.js; docs/tools/competitors-pipe-forks-
// drone-latency-ear.md sections 5 and 6): rounds in the lessons' shape, inside the range, sung together as
// several guide voices; the card plays a round, takes one answer, marks it by sign as well as colour,
// plays "Yours" beside "Right", keeps the score and the results by answer, offers the next level at 8 of
// the last 10, answers from the keys, and keeps its settings on the device.
import { CHORDS, chordRound, INTERVALS, intervalRound, inverted, pickKey, renderClip, rootFor } from '/js/ear-rounds.js';
import { QUALITIES } from '/js/theory.js';
import { DIRECTIONS, LEVEL_WORDS, LEVELS as INTERVAL_LEVELS, mountIntervalTrainer, WORDS as INTERVAL_WORDS } from '/js/public/tools/interval-trainer.js';
import { mountChordTrainer, STYLES, WORDS as CHORD_WORDS } from '/js/public/tools/chord-trainer.js';
import { QUIZ_WORDS } from '/js/public/tools/ear-quiz.js';
import { assert, test } from './harness.js';

/** A fixed sequence for `random`, cycling. */
const seq = (...values) => { let i = 0; return () => values[i++ % values.length]; };

test('ear rounds: an interval round is the lessons\' shape, its notes in the range', () => {
    for (let n = 0; n < 200; n++) {
        const way = ['up', 'down', 'together'][n % 3];
        const round = intervalRound({ chosen: Object.keys(INTERVALS), way, low: 48, high: 60 });
        const clip = round.clips[0];
        const steps = [...clip.notes, ...(clip.voices ?? []).flat()].map((note) => round.base + note.step);
        assert(Math.min(...steps) >= 48 && Math.max(...steps) <= 60, `${round.key} ${way}: ${steps}`);
        assert(Math.max(...steps) - Math.min(...steps) === INTERVALS[round.key], `the interval's size: ${round.key}`);
        assert(round.options[round.answer] === round.key, 'answer indexes the options');
        if (way === 'down') assert(clip.notes[0].step > clip.notes[1].step, 'down starts high');
        if (way === 'together') assert(clip.voices?.length === 1 && clip.notes[0].beat === clip.voices[0][0].beat, 'together: two voices at once');
    }
    assert(rootFor(12, { low: 50, high: 55, random: () => 0.99 }) === 50, 'a shape wider than the range starts at its bottom');
    const fixed = intervalRound({ key: 'P5', way: 'up', base: 60 });
    assert(fixed.base === 60 && fixed.clips[0].notes[1].step === 7, 'a given key and base');
});

test('ear rounds: a chord round has its quality\'s shape, together, up or down, any inversion', () => {
    const together = chordRound({ key: 'dom7', style: 'together', random: seq(0.5) });
    const sung = [together.clips[0].notes, ...together.clips[0].voices].map((line) => line[0].step);
    assert(JSON.stringify(sung) === JSON.stringify(QUALITIES.dom7), `dom7: ${sung}`);
    const up = chordRound({ key: 'min', style: 'up', random: seq(0.5) });
    assert(up.clips[0].notes.length === 3 && !up.clips[0].voices && up.clips[0].notes[1].beat > 0 && up.clips[0].notes[1].step > up.clips[0].notes[0].step, 'up: a note at a time, rising');
    const down = chordRound({ key: 'min', style: 'down', random: seq(0.5) });
    assert(down.clips[0].notes[0].step > down.clips[0].notes[2].step, 'down: falling');
    assert(JSON.stringify(inverted([0, 4, 7], 1)) === '[0,3,8]' && JSON.stringify(inverted([0, 4, 7], 2)) === '[0,5,9]', 'first and second inversions');
    const turned = chordRound({ key: 'maj', inversion: 'any', random: seq(0.9, 0.5) });
    assert(turned.inversion === 2, `any inversion: ${turned.inversion}`);
    assert(CHORDS.every((key) => QUALITIES[key]), 'every quality is the app\'s');
});

test('ear rounds: voices together are one sound, about as loud as one; missed answers come up more often', () => {
    const one = renderClip({ notes: [{ beat: 0, length: 1, step: 0 }] }, 57, { tempo: 60, sampleRate: 8000 });
    const two = renderClip({ notes: [{ beat: 0, length: 1, step: 0 }], voices: [[{ beat: 0, length: 1, step: 7 }]] }, 57, { tempo: 60, sampleRate: 8000 });
    const peak = (s) => s.reduce((m, v) => Math.max(m, Math.abs(v)), 0);
    assert(two.length >= one.length && peak(two) > 0 && peak(two) < peak(one) * 1.6, `${peak(one)} ${peak(two)}`);
    let random = 0;
    const counts = { a: 0, b: 0 };
    for (let i = 0; i < 1000; i++) counts[pickKey(['a', 'b'], { missed: { b: 2 }, random: () => ((random = (random + 0.6180339) % 1)) })]++;
    assert(counts.b > counts.a * 3, JSON.stringify(counts));
});

const words = (list) => ({ ...Object.fromEntries(list.map((key) => [key, `[${key}]`])), right: 'Right', youSaid: 'You said :answer', level: 'Level :n: :name', goLevel: 'Go to level :n', song: 'Like :song', semitones: ':n semitones', toNext: ':right of :need', statRight: ':accuracy right', statStreak: ':accuracy right · :streak in a row' });
const mount = (fn, options) => {
    const el = document.createElement('div');
    document.body.append(el);
    return { el, trainer: fn(el, { store: false, ...options }), card: el.querySelector('[data-ui=tool]') };
};
const pill = (card) => card.querySelector('[data-ui=tool-status]').textContent.replace(/\s+/g, ' ').trim();
const label = (card) => card.querySelector('[data-ui=tool-label]').textContent;
const stat = (card) => card.querySelector('[data-ui=tool-stat]').textContent;
const play = (card) => card.querySelector('[data-ui=tool-primary]').click();

test('ear quiz: a round plays, one answer counts, marked by sign, Yours beside Right, a song to remember', () => {
    const skin = document.documentElement.dataset.skin;
    document.documentElement.dataset.skin = 'game';
    const played = [];
    const { el, trainer, card } = mount(mountIntervalTrainer, { words: words(INTERVAL_WORDS), play: (round) => played.push(round), random: seq(0.1, 0.3, 0.7), songs: { up: { M2: 'Happy Birthday', M3: 'Kumbaya', P4: 'Amazing Grace', P5: 'Twinkle', P8: 'Somewhere' } } });
    assert([...card.querySelectorAll('[data-answer]')].filter((b) => !b.hidden).length === 5, 'the five starter intervals');
    assert(label(card) === 'Level 1: [level1]', label(card));
    assert(card.querySelectorAll('[data-ui=tool-modes] [data-mode]').length === 3, 'Up, Down, Together as the modes');
    assert(card.querySelector('[data-ui=tool-key] [data-step-value]').textContent === '1', 'the level as the key control');
    assert(card.querySelector('[data-answer=P8]').classList.contains('col-span-2'), 'the odd one out across both columns');
    assert(card.querySelector('[data-answer=P5] [data-sub]').textContent === '7 semitones', 'each tile its size');
    assert(card.querySelectorAll('[data-ui=level-segment]').length === 10, 'ten segments');
    play(card);
    const first = trainer.current();
    assert(played.length === 1 && first.way === 'up', 'played, going up');
    card.querySelector(`[data-answer="${first.key}"]`).click();
    assert(stat(card) === '100% right · 1 in a row', stat(card));
    assert(pill(card).startsWith('Right') && pill(card).includes(' · '), `right, and its notes: ${pill(card)}`);
    assert(card.querySelector('[data-hero]').textContent === `[${first.key}]`, 'the answer, big');
    assert(card.querySelector('[data-ui=level-segment]').dataset.result === 'right', 'the first segment filled');
    assert(card.querySelector(`[data-answer="${first.key}"] [data-mark]`).textContent === '✓', 'marked right by sign');
    assert(card.querySelector('[data-song]').textContent.startsWith('Like '), card.querySelector('[data-song]').textContent);
    assert(card.querySelector('[data-compare]').hidden, 'nothing to compare when right');
    // A wrong pick: both marks, and Yours plays the pick on the same notes as Right.
    play(card);
    const second = trainer.current();
    const wrong = INTERVAL_LEVELS[0].find((k) => k !== second.key);
    card.querySelector(`[data-answer="${wrong}"]`).click();
    assert(card.querySelector(`[data-answer="${wrong}"] [data-mark]`).textContent === '✗' && card.querySelector(`[data-answer="${second.key}"] [data-mark]`).textContent === '✓', 'both marks');
    assert(pill(card).startsWith(`You said [${wrong}]`), pill(card));
    assert(card.querySelectorAll('[data-ui=level-segment]')[1].dataset.result === 'wrong' && card.querySelectorAll('[data-ui=level-segment]')[1].className.includes('border'), 'a miss hollow, not only red');
    assert(stat(card) === '50% right · 0 in a row', 'the streak broken');
    assert(!card.querySelector('[data-compare]').hidden, 'Yours and Right offered');
    card.querySelector('[data-yours]').click();
    const yours = played.at(-1);
    assert(yours.key === wrong && yours.base === second.base && yours.way === second.way, JSON.stringify([yours.key, yours.base, second.base]));
    card.querySelector('[data-right-one]').click();
    assert(played.at(-1) === second, 'Right plays the question again');
    assert(trainer.stats()[second.key].missed === 1, JSON.stringify(trainer.stats()));
    assert(card.querySelector('[data-results-rows]').children.length === 2, 'results by interval');
    trainer.stop();
    el.remove();
    if (skin) document.documentElement.dataset.skin = skin; else delete document.documentElement.dataset.skin;
});

test('ear quiz: the streak shows in game mode only (serious mode is calm); chords light a keyboard', async () => {
    const skin = document.documentElement.dataset.skin;
    document.documentElement.dataset.skin = 'serious';
    const { el, trainer, card } = mount(mountChordTrainer, { words: words(CHORD_WORDS), play: () => {} });
    assert(!card.querySelector('[data-ui=keyboard]').hidden, 'the keyboard');
    trainer.next();
    trainer.answer(trainer.current().key);
    assert(stat(card) === '100% right', `no streak in serious: ${stat(card)}`);
    document.documentElement.dataset.skin = 'game';
    await new Promise((r) => setTimeout(r, 0));
    assert(stat(card) === '100% right · 1 in a row', `a streak in game mode: ${stat(card)}`);
    const lit = [...card.querySelectorAll('[data-ui=key][data-lit=true]')];
    assert(lit.length === 3, `the chord's three notes lit: ${lit.length}`);
    trainer.stop();
    el.remove();
    if (skin) document.documentElement.dataset.skin = skin; else delete document.documentElement.dataset.skin;
});

test('ear quiz: the next level is offered at 8 of the last 10, the key control steps the ladder, and the keys answer', () => {
    const { el, trainer, card } = mount(mountChordTrainer, { words: words(CHORD_WORDS), play: () => {}, random: Math.random });
    assert([...card.querySelectorAll('[data-answer]')].filter((b) => !b.hidden).map((b) => b.dataset.answer).join() === 'maj,min', 'major or minor first');
    for (let i = 0; i < 9; i++) {
        trainer.next();
        trainer.answer(trainer.current().key);
        assert(card.querySelector('[data-level-up]').hidden, `not yet at ${i + 1}`);
    }
    trainer.next();
    const index = trainer.current().key === 'maj' ? '1' : '2';
    document.dispatchEvent(new KeyboardEvent('keydown', { key: index, bubbles: true }));
    assert(trainer.score().total === 10 && trainer.score().right === 10, JSON.stringify(trainer.score()));
    assert(!card.querySelector('[data-level-up]').hidden && card.querySelector('[data-level-up]').textContent === 'Go to level 2', 'level 2 offered');
    card.querySelector('[data-level-up]').click();
    assert(trainer.level() === 1 && [...card.querySelectorAll('[data-answer]')].filter((b) => !b.hidden).length === 4, 'level 2: plus diminished and augmented');
    card.querySelector('[data-ui=tool-key] [data-step="1"]').click();
    assert(trainer.level() === 2 && card.querySelector('[data-ui=tool-key] [data-step-value]').textContent === '3', 'the key control steps up');
    card.querySelector('[data-ui=tool-key] [data-step="-1"]').click();
    card.querySelector('[data-ui=tool-key] [data-step="-1"]').click();
    assert(trainer.level() === 0 && card.querySelector('[data-ui=tool-key] [data-step="-1"]').disabled, 'and down, stopping at 1');
    document.dispatchEvent(new KeyboardEvent('keydown', { key: ' ', code: 'Space', bubbles: true }));
    assert(trainer.current() && card.dataset.phase === 'asking', 'space plays the next');
    trainer.stop();
    el.remove();
});

test('ear quiz: your own set, one always stays, and the settings are kept on the device', () => {
    localStorage.removeItem('stackapella.tools.test-chords');
    let { el, trainer, card } = mount(mountChordTrainer, { words: words(CHORD_WORDS), play: () => {}, store: 'test-chords' });
    const box = (key) => card.querySelector(`[data-choose] input[value="${key}"]`);
    box('dom7').click();
    assert(label(card) === '[ownSet]' && card.querySelector('[data-ui=level-bar]').hidden, 'your own set: no ladder');
    box('maj').click();
    box('min').click();
    box('dom7').click();
    assert(box('dom7').checked && !card.querySelector('[data-need-one]').classList.contains('hidden'), 'the last one stays, said why');
    card.querySelector('[data-ui=tool-modes] [data-mode=up]').click();
    card.querySelector('[data-toggle=inversions]').click();
    trainer.stop();
    el.remove();
    ({ el, trainer, card } = mount(mountChordTrainer, { words: words(CHORD_WORDS), play: () => {}, store: 'test-chords' }));
    trainer.next();
    assert(trainer.current().key === 'dom7' && trainer.current().way === 'up', JSON.stringify([trainer.current().key, trainer.current().way]));
    assert(card.querySelector('[data-toggle=inversions]').checked, 'inversions kept');
    trainer.stop();
    el.remove();
    localStorage.removeItem('stackapella.tools.test-chords');
});

test('ear trainers: the words written out in full are still the quiz\'s, the intervals or chords, the styles and the levels', () => {
    const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
    assert(same(INTERVAL_WORDS, [...QUIZ_WORDS, ...Object.keys(INTERVALS), ...DIRECTIONS, ...LEVEL_WORDS, 'semitones', 'showSemitones']), 'the interval trainer\'s words');
    assert(same(CHORD_WORDS, [...QUIZ_WORDS, ...CHORDS, ...STYLES, ...LEVEL_WORDS, 'inversions', 'promptMajorMinor']), 'the chord trainer\'s words');
});
