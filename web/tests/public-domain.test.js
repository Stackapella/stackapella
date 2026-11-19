// The public domain roll's filters (js/public/public-domain.js): which people a chip shows, and how many the page says
// show, by the words' own plural forms.
import { assert, test } from './harness.js';
import { countWords, inChip } from '/js/public/public-domain.js';

test('public domain: a chip shows its people; already everyone\'s takes the long shared too', () => {
    assert(inChip('all', 'coming', '0') && inChip('all', 'long', '1'), 'everyone is everyone');
    assert(inChip('coming', 'coming', '0') && !inChip('coming', 'joined', '1'), 'still to come');
    assert(inChip('joined', 'joined', '0') && inChip('joined', 'long', '0') && !inChip('joined', 'coming', '1'), 'already everyone\'s');
    assert(inChip('site', 'coming', '1') && !inChip('site', 'joined', '0'), 'on our site');
});

test('public domain: the count says one, many or none in its own words', () => {
    const words = '{0} No one shown|{1} One person shown|[2,*] :count people shown';
    assert(countWords(words, 0) === 'No one shown', countWords(words, 0));
    assert(countWords(words, 1) === 'One person shown', countWords(words, 1));
    assert(countWords(words, 12) === '12 people shown', countWords(words, 12));
});
