// A free tool's variant preset (docs/seo-growth-plan.md, item 3): the tuner's preset as visit values, and the simple
// settings store (kit.js remember(), the drone's, pitch pipe's and forks') holding a visit value without saving it
// over the person's own until they change it.
import { presetOf } from '/js/public/tools/tuner.js';
import { remember } from '/js/public/tools/kit.js';
import { assert, test } from './harness.js';

const KEY = 'stackapella.tools.preset-test';

test('tool presets: the tuner\'s instrument implies Instrument, Voice stays Voice, an unknown instrument changes nothing', () => {
    assert(JSON.stringify(presetOf({ instrument: 'ukulele' })) === JSON.stringify({ instrument: 'ukulele', mode: 'instrument' }), 'the ukulele tuner');
    assert(JSON.stringify(presetOf({ mode: 'voice' })) === JSON.stringify({ mode: 'voice' }), 'the voice tuner');
    assert(JSON.stringify(presetOf({ instrument: 'theremin' })) === JSON.stringify({ instrument: 'theremin' }), 'no mode for an unknown instrument (the store refuses the instrument)');
    assert(JSON.stringify(presetOf({})) === '{}', 'no preset');
});

test('tool presets: a visit value shows but isn\'t saved over the person\'s own, until they change it', () => {
    localStorage.setItem(KEY, JSON.stringify({ mode: 'drone', volume: 0.5 }));
    try {
        const saved = remember('preset-test', { mode: 'drone', volume: 0.8, pace: 1 });
        saved.visit({ mode: 'tanpura', volume: 'loud', extra: 1 });
        assert(saved.value.mode === 'tanpura' && saved.value.volume === 0.5, 'the preset shows; a wrong type or unknown key is ignored');
        saved.save({ pace: 2 });
        // The shared store's record (tool-prefs.js): { v, updatedAt, settings }.
        let stored = JSON.parse(localStorage.getItem(KEY)).settings;
        assert(stored.mode === 'drone' && stored.pace === 2, `saving something else keeps theirs: ${JSON.stringify(stored)}`);
        saved.save({ mode: 'tanpura' });
        stored = JSON.parse(localStorage.getItem(KEY)).settings;
        assert(stored.mode === 'tanpura', 'their own pick is saved, even the same value');
    } finally {
        localStorage.removeItem(KEY);
    }
});
