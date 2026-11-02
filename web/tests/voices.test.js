// Lend your voice slices 3 to 5 (docs/lend-your-voice-plan.md): a clip's credit by its style, and the
// real voices' credits in an MP3 export's ID3 comment.
import { clipCredit } from '/js/course-clips.js';
import { id3Comment } from '/js/mp3.js';
import { assert, test } from './harness.js';

test('voices: a clip is credited by its style, else "Sung by"; an anonymous one not at all', () => {
    assert(clipCredit({ credit: 'Sam', creditStyle: 'beatbox' }) === 'Beatbox by Sam', clipCredit({ credit: 'Sam', creditStyle: 'beatbox' }));
    assert(clipCredit({ credit: 'Ana', creditStyle: 'yodel' }) === 'Yodel by Ana');
    assert(clipCredit({ credit: 'Kim', creditStyle: 'something-new' }).includes('Kim'), 'an unknown style: the plain credit');
    assert(clipCredit({ credit: null, creditStyle: 'beatbox' }) === '', 'anonymous: no line');
});

test('voices: an MP3 export carries the credits as an ID3 comment players read', () => {
    const tag = id3Comment('Real voices: Sarah K.');
    assert(String.fromCharCode(...tag.slice(0, 3)) === 'ID3' && tag[3] === 3, 'an ID3v2.3 tag');
    const size = (tag[6] << 21) | (tag[7] << 14) | (tag[8] << 7) | tag[9];
    assert(size === tag.length - 10, `its size (${size}) is the frame's`);
    assert(String.fromCharCode(...tag.slice(10, 14)) === 'COMM', 'one COMM frame');
    const text = new TextDecoder('utf-16le').decode(tag.slice(10 + 10 + 8 + 2));
    assert(text === 'Real voices: Sarah K.', JSON.stringify(text));
});
