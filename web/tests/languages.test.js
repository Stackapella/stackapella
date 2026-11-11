// Languages, slice 1 (docs/languages-plan.md): note names by language, a drafted language hidden until it's switched
// on, and a translation made from older English never shown.
import { midiName } from '/js/dsp.js';
import { coverage, inLanguage, isLive, loadLanguage, locale, preferredLanguages, t } from '/js/i18n.js';
import { chooseNaming, isSolfa, nameNote, naming, namingSample, noteNames } from '/js/note-names.js';
import { assert, test } from './harness.js';

const same = (actual, expected, what = '') => assert(actual === expected, `${what ? what + ': ' : ''}expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);

test('languages: note names in letters, fixed Do and German, octaves scientific in each', () => {
    same([60, 61, 63, 66, 70, 71, 57].map((m) => nameNote(m, 'letters')).join(' '), 'C4 C#4 Eb4 F#4 Bb4 B4 A3');
    same([60, 61, 63, 66, 67, 70, 71].map((m) => nameNote(m, 'fixed')).join(' '), 'Do4 Do#4 Mib4 Fa#4 Sol4 Sib4 Si4');
    same([60, 61, 63, 66, 70, 71].map((m) => nameNote(m, 'german')).join(' '), 'C4 Cis4 Es4 Fis4 B4 H4');
    same(namingSample('fixed'), 'Do Re Mi');
    same(namingSample('letters'), 'C D E');
});

test('languages: every note name follows the naming: auto by the language, a choice over it', async () => {
    const was = noteNames.choice;
    try {
        await loadLanguage(['en']);
        chooseNaming('auto');
        same(naming(), 'letters');
        same(midiName(62), 'D4', 'English: letters');
        chooseNaming('fixed');
        same(midiName(62), 'Re4', 'chosen: fixed Do, everywhere midiName is');
        chooseNaming('german');
        same(midiName(71), 'H4');
    } finally {
        chooseNaming(was);
    }
});

test('languages: a sung solfège syllable is told from a word', () => {
    for (const word of ['do', 'Mi', 'sol', 'so', 'ti', 'fi', 'te', 'mi-', 'Rè']) assert(isSolfa(word), word);
    for (const word of ['love', 'dove', 'la la', 'Amazing', '', null]) assert(!isSolfa(word), String(word));
});

test('languages: a drafted language opens only once switched on, or when asked for in the address', () => {
    const live = locale.live;
    try {
        locale.live = ['en'];
        assert(isLive('en') && isLive('en-GB') && !isLive('es') && !isLive('es-MX'), 'English alone');
        assert(!preferredLanguages().includes('es') || new URLSearchParams(location.search).get('lang') === 'es', 'a Spanish browser stays English');
        locale.live = ['en', 'es'];
        assert(isLive('es-MX'), 'Spanish on: every region');
    } finally {
        locale.live = live;
    }
});

test('languages: a key translated from older English shows the new English; content says when it is English', async () => {
    await loadLanguage(['en']);
    const saved = { lang: locale.lang, messages: locale.messages, source: locale.source };
    try {
        const english = locale.english;
        locale.lang = 'es';
        locale.messages = { studio: { recordNew: 'Grabar una parte nueva' }, common: { undo: 'Deshacer' } };
        locale.source = { studio: { recordNew: english.studio.recordNew }, common: { undo: 'An older Undo' } };
        same(t('studio.recordNew'), 'Grabar una parte nueva', 'current: translated');
        same(t('common.undo'), english.common.undo, 'its English changed since: the new English');
        same(t('common.cancel'), english.common.cancel, 'not translated yet: English');
        assert(coverage() > 0 && coverage() < 0.01, String(coverage()));
        assert(inLanguage({ en: 'Vibrato', es: 'Vibrato' }) && !inLanguage({ en: 'Vibrato' }) && inLanguage('plain'), 'content by language');
    } finally {
        Object.assign(locale, saved);
    }
    same(coverage(), 1, 'English is all there');
});
