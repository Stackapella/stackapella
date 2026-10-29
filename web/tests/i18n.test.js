// The app's words (js/i18n.js): messages, plurals, the pseudo-language and Intl formatting.
import { format, loadLanguage, locale, localized, pseudo, t, timeAgo } from '/js/i18n.js';
import { slotName, templatePartName } from '/js/templates.js';
import { defaultPartName, keyLabel } from '/js/theme.js';
import { assert, test } from './harness.js';

const same = (actual, expected) => assert(actual === expected, `expected ${JSON.stringify(expected)}, got ${JSON.stringify(actual)}`);

test('i18n: messages fill in their arguments, and numbers are formatted for the language', async () => {
    await loadLanguage(['en']);
    same(t('studio.recordNamed', { name: 'Alto' }), 'Record Alto');
    same(format('{count} singers', { count: 1234 }), '1,234 singers');
    same(t('no.such.key'), ''); // never a raw key on screen
});

test('i18n: plurals pick the case by Intl.PluralRules, with exact matches first', async () => {
    same(t('studio.partCount', { count: 1 }), '1 part');
    same(t('studio.partCount', { count: 3 }), '3 parts');
    same(t('studio.countInLabel', { count: 0 }), 'No count-in');
    same(t('studio.countInLabel', { count: 2 }), 'Count-in 2 bars');
    same(t('confirm.reduceSingers.body', { parts: 'Alto and Tenor', takes: 2 }), 'The Alto and Tenor have recordings, and they go. You can undo it straight after.');
    same(t('confirm.reduceSingers.body', { parts: 'Alto', takes: 1 }), 'The Alto has a recording, and it goes. You can undo it straight after.');
});

test('i18n: the pseudo-language accents every word, makes it 40% longer, and keeps the arguments', () => {
    const message = pseudo('Record {name}, {count, plural, one {# part} other {# parts}}');
    assert(message.startsWith('[Réçörd {name}') && message.includes('{count, plural, one {# pàrt} other {# pàrtš}}'), message);
    assert(message.endsWith('·]'), 'padded');
    same(format(message, { name: 'Alto', count: 2 }).includes('Alto'), true);
});

test('i18n: the pseudo-language turns on with ?lang=pseudo, and English comes back', async () => {
    await loadLanguage(['pseudo']);
    assert(locale.pseudo && t('studio.recordNew').startsWith('[Réçörd'), t('studio.recordNew'));
    await loadLanguage(['en']);
    assert(!locale.pseudo && t('studio.recordNew') === 'Record new part', 'back to English');
    same(document.documentElement.lang, 'en');
});

test('i18n: a language we have no file for falls back to English', async () => {
    await loadLanguage(['xx-YY', 'xx']);
    same(locale.lang, 'en');
    same(t('common.save'), 'Save');
});

test('i18n: times ago go through Intl', () => {
    const now = Date.UTC(2026, 9, 26, 12);
    same(timeAgo(now - 20000, now), 'just now');
    same(timeAgo(now - 3 * 60000, now), '3 minutes ago');
    same(timeAgo(now - 26 * 3600000, now), 'yesterday');
});

test('i18n: content keeps its words by language, falling back to English', () => {
    same(localized({ en: 'Find your voice', fr: 'Trouve ta voix' }), 'Find your voice');
    same(localized('plain'), 'plain');
    same(localized(null), '');
});

test('i18n: part names are keys; a renamed part keeps its own name', () => {
    const template = { parts: [{ key: 'alto', name: 'Alto' }] };
    same(templatePartName(template.parts[0]), 'Alto');
    same(slotName({ templatePartKey: 'alto', name: 'Alto' }, template), 'Alto');
    same(slotName({ templatePartKey: 'alto', name: 'Low line' }, template), 'Low line');
    same(slotName({ name: 'Beatbox' }, null), 'Beatbox');
    // Hillel: a new track is "Track 1", never Melody or Harmony.
    same(defaultPartName(0), 'Track 1');
    same(defaultPartName(9), 'Track 10');
    same(keyLabel('Em'), 'E minor');
    same(keyLabel('G'), 'G');
});

test('i18n: a key the words in hand lack shows nothing, and the words are fetched again', async () => {
    const { locale, refreshWords, t } = await import('/js/i18n.js');
    const saved = { english: locale.english, messages: locale.messages };
    try {
        // An old copy of the words, from before the key was added.
        const old = JSON.parse(JSON.stringify(locale.english));
        delete old.common.cancel;
        locale.english = old;
        locale.messages = old;
        assert(t('common.cancel') === '', 'a raw key shown');
        await refreshWords();
        assert(t('common.cancel') === 'Cancel', `after fetching again: ${t('common.cancel')}`);
    } finally {
        Object.assign(locale, saved);
    }
});
