// Then and now for a play step (js/components/then-and-now.js): lesson 1 keeps its first heard take
// once, never over an earlier one, and the finale finds it, or nothing on a device that never sang it.
import { keepFirstTake, loadKept } from '/js/components/then-and-now.js';
import { assert, test } from './harness.js';

function fakeStore() {
    const rows = new Map();
    return {
        rows,
        get: async (store, key) => rows.get(`${store}/${key}`),
        put: async (store, value, key) => rows.set(`${store}/${key}`, value),
        putAudio: async (key, samples) => rows.set(`audio/${key}`, samples),
        getAudio: async (key) => rows.get(`audio/${key}`),
    };
}

const take = (value) => ({ samples: new Float32Array([value, value, value]), sampleRate: 48000 });

test('then and now: the first heard take is kept, and a later one never replaces it', async () => {
    const store = fakeStore();
    assert(await keepFirstTake('folk.first', take(0.25), store), 'the first take is kept');
    assert(!(await keepFirstTake('folk.first', take(0.75), store)), 'a second take is not kept');
    const kept = await loadKept('folk.first', store);
    assert(kept && kept.sampleRate === 48000 && kept.samples[0] === 0.25, 'the first one is what comes back');
    assert(typeof kept.at === 'number', 'with when it was sung');
    assert(store.rows.has('audio/course:folk.first') && store.rows.has('learning/course:folk.first'), 'under course:<id>, as the other steps keep theirs');
});

test('then and now: nothing kept, nothing to show', async () => {
    const store = fakeStore();
    assert((await loadKept('pop.first', store)) === null, 'a device that never sang lesson 1');
    assert(!(await keepFirstTake('pop.first', { samples: new Float32Array(0), sampleRate: 48000 }, store)), 'an empty take is never kept');
    assert(!(await keepFirstTake('', take(0.25), store)), 'no id, nothing kept');
    assert((await loadKept('pop.first', store)) === null, 'still nothing');
});
