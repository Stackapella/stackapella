// The tap tempo tool (js/public/tools/tap-tempo.js) on the metronome's own reading (metronome-beats.js
// tap() and tapReading()): taps half a second apart read 120, Allegro, steady; one fumbled tap doesn't
// move it; Half, Double and a bar's beats; a 2-second pause starts again; the handoff to the metronome.
import { tap, tapReading, tapTempo } from '/js/public/metronome-beats.js';
import { mountTapTempo, WORDS } from '/js/public/tools/tap-tempo.js';
import { assert, test } from './harness.js';

const words = { ...Object.fromEntries(WORDS.map((k) => [k, `[${k}]`])), taps: ':n taps', aBarOf: 'a bar of :n', perBeat: ':beat ms a beat, :eighth an eighth' };

test('tap tempo: the reading is the median gap, so one fumbled tap doesn\'t move it, and says how steady', () => {
    const steady = tapReading([0, 0.5, 1, 1.5, 2, 2.5]);
    assert(Math.round(steady.bpm) === 120 && steady.steadiness === 'steady' && steady.count === 6, JSON.stringify(steady));
    const fumbled = tapReading([0, 0.5, 1, 1.38, 2, 2.5, 3]);
    assert(Math.round(fumbled.bpm) === 120, `one fumble: ${fumbled.bpm}`);
    const loose = tapReading([0, 0.42, 1, 1.58, 2, 2.45]);
    assert(loose.steadiness !== 'steady', loose.steadiness);
    assert(tapReading([0, 0.5]) === null, 'not under 3 taps');
    let state = tapTempo();
    for (let i = 0; i < 40; i++) state = tap(state, i * 0.5, { keep: 32 }).state;
    assert(state.taps.length === 32, 'keeps 32 for the tool');
    let quick = tapTempo();
    for (let i = 0; i < 40; i++) quick = tap(quick, i * 0.5).state;
    assert(quick.taps.length === 6, 'the metronome keeps its six');
});

test('tap tempo: the tool reads 120 Allegro, halves, doubles, counts bars, and hands the tempo to the metronome', () => {
    try {
        localStorage.removeItem('stackapella.tools.tap-tempo');
    } catch (error) {
        console.warn('storage', error);
    }
    const el = document.createElement('div');
    const slot = document.createElement('div');
    document.body.append(slot, el);
    let went = null;
    const tool = mountTapTempo(el, { words, keySlot: slot, metronome: '/tools/metronome', go: (url) => (went = url) });
    const card = el.querySelector('[data-ui=tool]');
    const bpm = () => card.querySelector('[data-bpm]').textContent;
    const pill = () => card.querySelector('[data-ui=tool-status]').textContent;
    const step = (by) => slot.querySelector(`[data-step="${by}"]`).click();
    assert(card.querySelector('[data-ui=tool-primary]').disabled && slot.querySelector('[data-step="1"]').disabled, 'nothing to hand over yet');
    for (let i = 0; i < 8; i++) tool.tap(100 + i * 0.5);
    assert(bpm() === '120', bpm());
    assert(pill().includes('Allegro') && pill().includes('[steady]'), pill());
    assert(card.querySelector('[data-ui=tool-status]').dataset.tone === 'good', 'steady is good');
    assert(card.querySelector('[data-ui=tool-stat]').textContent === '8 taps', card.querySelector('[data-ui=tool-stat]').textContent);
    card.querySelector('[data-ui=tool-primary]').click();
    assert(went === '/tools/metronome?bpm=120', `the handoff: ${went}`);
    step(-1);
    assert(bpm() === '60' && slot.querySelector('[data-step-value]').textContent === '½×', 'half');
    step(1);
    step(1);
    assert(bpm() === '240', 'double');
    step(1);
    step(1);
    assert(slot.querySelector('[data-step="1"]').disabled, 'four times is the most');
    step(-1);
    step(-1);
    const perTap = card.querySelector('[data-per-tap]');
    perTap.value = '4';
    perTap.dispatchEvent(new Event('change'));
    assert(bpm() === '480', bpm());
    perTap.value = '1';
    perTap.dispatchEvent(new Event('change'));
    card.querySelector('[data-decimal]').click();
    assert(bpm() === '120.0', 'a decimal');
    assert(card.querySelector('[data-per-beat]').textContent === '500 ms a beat, 250 an eighth', card.querySelector('[data-per-beat]').textContent);
    // A pause of more than 2 seconds: the next tap starts a new count, at the time as tapped.
    step(1);
    tool.tap(110);
    assert(bpm() === '–' && card.querySelector('[data-ui=tool-stat]').textContent === '1 taps', 'started again');
    assert(slot.querySelector('[data-step-value]').textContent === '1×', 'the time back to as tapped');
    assert(JSON.parse(localStorage.getItem('stackapella.tools.tap-tempo')).settings.decimal === true, 'the decimal remembered');
    tool.stop();
    assert(!slot.children.length, 'the key control goes with the tool');
    localStorage.removeItem('stackapella.tools.tap-tempo');
    el.remove();
    slot.remove();
});
