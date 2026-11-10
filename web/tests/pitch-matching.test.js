// The pitch matching game's rounds (js/public/tools/pitch-matching-rounds.js): a note judged once it's held
// steady (the scoop at its start not counted), in any octave unless strict, the verdict in words, and the
// notes in the voice's range, never the same twice running.
import { fold, judge, pickNote, steadiness, VOICES } from '/js/public/tools/pitch-matching-rounds.js';
import { mountPitchMatching, ownVoice, WORDS as SCREEN_WORDS } from '/js/public/tools/pitch-matching.js';
import { audio, hz } from '/js/public/voices.js';
import { assert, test } from './harness.js';

const frames = (list) => list.map(([at, cents]) => ({ at, cents, clear: cents !== null }));

test('pitch matching: judged once held steady, the scoop at the start not counted', () => {
    const scoop = [];
    for (let i = 0; i < 10; i++) scoop.push([i * 0.02, -150 + i * 15]); // sliding up into it
    const held = [];
    for (let i = 0; i < 40; i++) held.push([0.2 + i * 0.02, 8 + (i % 3)]); // then 0.8 s on it, 8 to 10 cents sharp
    const done = steadiness(frames([...scoop, ...held]));
    assert(done.share === 1 && Math.abs(done.cents - 9) <= 1, JSON.stringify(done));
    const early = steadiness(frames([...scoop, ...held.slice(0, 10)]));
    assert(early.share > 0 && early.share < 1 && early.cents === null, `not yet: ${JSON.stringify(early)}`);
    assert(steadiness(frames([[0, null], [0.1, null]])).share === 0, 'nothing heard');
    const octave = steadiness(frames(held.map(([at, c]) => [at, c - 1200])));
    assert(Math.abs(octave.cents - 9) <= 1, 'an octave down is the note');
    assert(Math.abs(steadiness(frames(held.map(([at, c]) => [at, c - 1200])), { strict: true }).cents + 1191) <= 1, 'strict: the octave counts');
});

test('pitch matching: the verdict in words, and notes in the voice\'s range, never twice running', () => {
    assert(judge(6) === 'spot' && judge(-30) === 'close' && judge(48) === 'nearly' && judge(-120) === 'off', 'the four words');
    assert(fold(1190) === -10 && fold(-1205) === -5, 'folded to the nearest octave');
    let last = null;
    for (let i = 0; i < 200; i++) {
        const note = pickNote(VOICES.low, last);
        assert(note >= VOICES.low.low && note <= VOICES.low.high && note !== last, `${note} after ${last}`);
        last = note;
    }
});

test('pitch matching: a held note is judged on the audio clock, in any octave unless strict, and the round is scored', async () => {
    try {
        localStorage.removeItem('stackapella.tools.pitch-matching');
    } catch (error) {
        console.warn('storage', error);
    }
    const words = Object.fromEntries(SCREEN_WORDS.map((k) => [k, `[${k}]`]));
    Object.assign(words, { score: ':n / :total', round: ':n of :total', centsFlat: ':n flat' });
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    const frames = [];
    let goal = null;
    const fake = async () => ({ target: (m) => (goal = m), since: (at) => frames.filter((f) => f.at >= at), stop() {}, deviceId: () => '', use: async () => {} });
    let onFrame = null;
    const open = async (options) => {
        onFrame = options.onFrame;
        return fake();
    };
    const game = mountPitchMatching(el, { words, keySlot: slot, open, random: () => 0.5 });
    const card = el.querySelector('[data-ui=tool]');
    slot.querySelector('[data-step="-1"]').click(); // level 1: sing straight back
    assert(slot.querySelector('[data-step-value]').textContent === '1' && card.querySelector('[data-stage=wait]').hidden, 'no wait at level 1');
    // Sings `cents` off the note (plus `octaves`), held for a second, from now on the audio clock.
    const sing = (cents, octaves = 0) => {
        const from = audio().currentTime;
        for (let i = 0; i < 60; i++) {
            const midi = goal + octaves * 12 + cents / 100;
            const f = { at: from + i * 0.02, hz: hz(midi), midi, cents: octaves * 1200 + cents, clear: true, loudness: -24 };
            frames.push(f);
            onFrame(f);
            if (card.dataset.stage !== 'sing') break;
        }
    };
    await game.play();
    assert(card.dataset.stage === 'sing' && goal === 65, `${card.dataset.stage} ${goal}`);
    sing(-6);
    assert(card.dataset.stage === 'result' && card.dataset.verdict === 'spot', card.dataset.verdict);
    assert(card.querySelector('[data-sub]').textContent === '6 flat', card.querySelector('[data-sub]').textContent);
    assert(card.querySelector('[data-ui=tool-stat]').textContent === '1 / 1', 'scored');
    await game.next();
    sing(4, -1);
    assert(card.dataset.verdict === 'spot' && card.querySelector('[data-sub]').textContent.includes('[octaveLower]'), card.querySelector('[data-sub]').textContent);
    card.querySelector('[data-strict]').click();
    await game.next();
    sing(4, -1);
    assert(card.dataset.verdict === 'off', `strict: ${card.dataset.verdict}`);
    assert([...card.querySelectorAll('[data-dots] span')].filter((d) => /bg-(good|warn)/.test(d.className)).length === 3, 'three dots coloured');
    game.stop();
    el.remove();
    slot.remove();
});

test('pitch matching: your own range gives notes from its middle, a little in from each end', () => {
    assert(JSON.stringify(ownVoice({ low: 48, high: 69 })) === '{"low":50,"high":67}', JSON.stringify(ownVoice({ low: 48, high: 69 })));
    assert(JSON.stringify(ownVoice({ low: 60, high: 70 })) === '{"low":60,"high":70}', 'a narrow range kept whole');
    assert(ownVoice(null) === null, 'none saved');
});
