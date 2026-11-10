// The free tools' settings store (js/public/tool-prefs.js): one record per tool, cleaned against its
// defaults, saved with a version and the time, announced to a listener (a future sync), reset, and a
// value from the link that wins for the visit without being saved.
import { allToolPrefs, cleanSettings, onToolPrefs, toolPrefs } from '/js/public/tool-prefs.js';
import { assert, test } from './harness.js';

const KEY = 'stackapella.tools.prefs-test';
const defaults = { mode: 'voice', a4: 440, flats: false, accents: ['accent', 'normal'], trainer: { on: false, add: 5 } };
const valid = { mode: (v) => ['voice', 'instrument'].includes(v), a4: (v) => Number.isFinite(v) && v >= 415 && v <= 466 };

test('tool prefs: bad, unknown and old values are dropped, good ones kept, types checked key by key', () => {
    const cleaned = cleanSettings(defaults, { mode: 'banjo', a4: 442, flats: 'yes', accents: 'x', trainer: { on: true, add: 'lots' }, extra: 1 }, valid);
    assert(JSON.stringify(cleaned) === JSON.stringify({ mode: 'voice', a4: 442, flats: false, accents: ['accent', 'normal'], trainer: { on: true, add: 5 } }), JSON.stringify(cleaned));
    assert(JSON.stringify(cleanSettings(defaults, null)) === JSON.stringify(defaults), 'nothing stored is the defaults');
    assert(cleanSettings({ n: 1 }, { n: NaN }).n === 1, 'a number must be a real one');
});

test('tool prefs: saved with a version and the time, read back, announced, and forgotten by reset', () => {
    localStorage.removeItem(KEY);
    const heard = [];
    const off = onToolPrefs((slug, record) => slug === 'prefs-test' && heard.push(record));
    try {
        const prefs = toolPrefs('prefs-test', { defaults, valid, version: 2 });
        prefs.value.mode = 'instrument';
        prefs.value.trainer.on = true;
        prefs.save();
        const record = JSON.parse(localStorage.getItem(KEY));
        assert(record.v === 2 && record.updatedAt > 0 && record.settings.mode === 'instrument', JSON.stringify(record));
        assert(heard.length === 1 && heard[0].settings.trainer.on, 'a save is announced');
        assert(allToolPrefs()['prefs-test']?.settings.mode === 'instrument', 'listed for a sync');
        const again = toolPrefs('prefs-test', { defaults, valid, version: 2 });
        assert(again.value.mode === 'instrument' && again.value.trainer.on, 'read back');
        again.reset();
        assert(localStorage.getItem(KEY) === null && again.value.mode === 'voice' && heard.at(-1) === null, 'reset forgets and announces');
    } finally {
        off();
        localStorage.removeItem(KEY);
    }
});

test('tool prefs: a tool\'s old bare settings are read, a cross-value fix is applied, the link wins for the visit only', () => {
    localStorage.setItem(KEY, JSON.stringify({ mode: 'instrument', a4: 443 })); // before this store: bare
    try {
        const prefs = toolPrefs('prefs-test', { defaults, valid, clean: (s) => ({ ...s, accents: s.accents.slice(0, 1) }) });
        assert(prefs.value.mode === 'instrument' && prefs.value.a4 === 443 && prefs.value.accents.length === 1, JSON.stringify(prefs.value));
        prefs.visit({ a4: 432 });
        assert(prefs.value.a4 === 432, 'the link\'s value shows');
        prefs.value.flats = true;
        prefs.save();
        assert(JSON.parse(localStorage.getItem(KEY)).settings.a4 === 443, 'saving something else keeps the stored A4');
        prefs.value.a4 = 430;
        prefs.save();
        assert(JSON.parse(localStorage.getItem(KEY)).settings.a4 === 430, 'changing it saves it');
        prefs.visit({ a4: 999 });
        assert(prefs.value.a4 === 430, 'a bad value from the link is refused');
    } finally {
        localStorage.removeItem(KEY);
    }
});
