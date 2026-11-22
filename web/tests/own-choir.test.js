// Hear yourself as a choir (js/own-choir.js): the clip is the take from the song's beat 0, at most 30 seconds, a 16-bit
// mono WAV, with where beat 0 falls in it; a take that started after beat 0 says so (null).
import { clipFrom, cutAt, MAX_SECONDS } from '/js/own-choir.js';
import { assert, test } from './harness.js';

const RATE = 8000;
const take = (seconds) => new Float32Array(Math.round(seconds * RATE)).fill(0.25);

test('own choir: the clip starts at the song’s beat 0 and is at most 30 seconds', () => {
    // The take started 1 s before beat 0 (a count-in): the clip starts at beat 0, which is its frame 0.
    const early = clipFrom(take(40), RATE, -1);
    assert(early.offsetFrames === 0, `beat 0 at the clip’s start: ${early.offsetFrames}`);
    assert(early.frames === MAX_SECONDS * RATE, `at most MAX_SECONDS: ${early.frames}`);
    assert(early.wav.size === 44 + early.frames * 2, 'a 16-bit mono WAV');
    // A short take from beat 0: all of it.
    const short = clipFrom(take(12), RATE, 0);
    assert(short.offsetFrames === 0 && Math.abs(short.seconds - 12) < 1e-9, `${short.seconds}`);
});

test('own choir: a take that started after beat 0 has no beat 0 in it', () => {
    const late = clipFrom(take(20), RATE, 5);
    assert(late.offsetFrames === null, `${late.offsetFrames}`);
    assert(late.frames === 20 * RATE, 'all of it, from its start');
});

// Part-minutes as the server counts them (its `needed`): each part's last note end, in minutes at the tempo, summed.
test('own choir: part-minutes sum each part’s own end, and songs left round down', async () => {
    const { partMinutes, songsLeft } = await import('/js/own-choir.js');
    const song = { tempo: 60, parts: [{ guide: { notes: [{ beat: 0, length: 30 }, { beat: 30, length: 30 }] } }, { guide: { notes: [{ beat: 0, length: 90 }] } }, { guide: { notes: [] } }] };
    assert(Math.abs(partMinutes(song) - 2.5) < 1e-9, `1 + 1.5 minutes: ${partMinutes(song)}`);
    assert(songsLeft({ unit: 'minutes', minutesLeft: 12 }, song) === 4, 'about 4 songs in 12 minutes');
    assert(songsLeft({ unit: 'songs', monthLeft: 2 }, song) === null, 'not counted in minutes');
    assert(partMinutes({ parts: [] }) === 0 && songsLeft({ unit: 'minutes', minutesLeft: 5 }, { tempo: 60, parts: [] }) === null, 'nothing to count');
});

test('own choir: each refusal from making a song has its own words', async () => {
    const { choirRefusal } = await import('/js/own-choir.js');
    const { t } = await import('/js/i18n.js');
    assert(choirRefusal({ code: 'email_unverified' }) === t('ownChoir.email.needed'), 'unconfirmed email');
    assert(choirRefusal({ code: 'validation_failed', errors: { email: ['A lasting address, please.'] } }) === t('ownChoir.email.lasting'), 'a throwaway address');
    assert(choirRefusal({ code: 'turnstile_failed' }) === t('ownChoir.check.failed') && choirRefusal({ code: 'turnstile_required' }) === t('ownChoir.check.failed'), 'the check');
    assert(choirRefusal({ code: 'validation_failed', errors: { frames: ['A clip is 5 to 30 seconds.'] } }) === t('ownChoir.record.failed'), 'anything else: it didn’t work');
    assert(choirRefusal({ code: 'quota_reached', body: { which: 'day' } }) === t('ownChoir.limit.day'), 'today’s');
});

test('own choir: a song with more than 8 parts, or a part over 5 minutes, is too long to make', async () => {
    const { tooLong } = await import('/js/own-choir.js');
    const part = (beats) => ({ guide: { notes: [{ beat: 0, length: beats }] } });
    assert(!tooLong({ tempo: 60, parts: [part(120), part(120)] }), 'two parts of 2 minutes');
    assert(!tooLong({ tempo: 60, parts: Array.from({ length: 8 }, () => part(60)) }), 'eight parts');
    assert(tooLong({ tempo: 60, parts: Array.from({ length: 9 }, () => part(60)) }), 'nine parts');
    assert(tooLong({ tempo: 60, parts: [part(301)] }) && !tooLong({ tempo: 60, parts: [part(300)] }), 'over 5 minutes, at 60 bpm');
});


test('own choir: a clip tune is cut where its take stops, as the server cuts what the worker gets', () => {
    const notes = [{ beat: 0, length: 2, pitch: 60 }, { beat: 34, length: 4, pitch: 62 }, { beat: 36, length: 1, pitch: 64 }];
    const template = { parts: [{ key: 'soprano', guide: { notes } }], chords: [{ beat: 0 }, { beat: 36 }], sections: [{ startBeat: 0, endBeat: 48, phrases: [[0, 18], [18, 36], [36, 48]] }] };
    const cut = cutAt(template, 36);
    assert(cut.parts[0].guide.notes.length === 2 && cut.parts[0].guide.notes[1].length === 4, 'only notes before the stop, each unchanged');
    assert(cut.sections[0].endBeat === 38 && cut.sections[0].phrases.length === 2, `the section ends with the last note: ${cut.sections[0].endBeat}`);
    assert(cut.chords.length === 1, 'the chords end there too');
    assert(template.parts[0].guide.notes.length === 3, 'the template itself unchanged');
    assert(cutAt(template, null) === template, 'no stop: the whole tune');
});

test('own choir: the first note sung moves the tune to start there, never more than a fifth either way', async () => {
    const { shiftTo } = await import('/js/components/first-note.js');
    assert(shiftTo(62, 60) === 2, 'two up');
    assert(shiftTo(55, 60) === -5, 'five down');
    assert(shiftTo(69, 60) === -3, 'nine up is three down an octave higher (the worker finds the octave)');
    assert(shiftTo(50, 60) === 2, 'ten down is two up');
    assert(shiftTo(null, 60) === 0 && shiftTo(62, null) === 0, 'nothing heard: as written');
});
