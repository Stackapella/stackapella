import { REFUSAL_ROUND, refusedAsItIs } from '/js/stores/templates.js';
import { lengthBeats, listTemplates, loadTemplate, lyricLines, phrases, repairTemplate, TemplateError, templatePartName, validateTemplate } from '/js/templates.js';
import { renderGuide } from '/js/guides.js';
import { detectPitch, hzToMidi } from '/js/dsp.js';
import { beatToSeconds } from '/js/synth.js';
import { assert, close, test } from './harness.js';

function refuses(template, wording) {
    try {
        validateTemplate(template);
    } catch (error) {
        assert(error instanceof TemplateError, `threw ${error}`);
        assert(error.message.includes(wording), `"${error.message}" doesn't mention "${wording}"`);
        return;
    }
    throw new Error(`accepted a template that should fail: ${wording}`);
}

async function amazing() {
    return structuredClone(await loadTemplate('amazing-grace'));
}

test('templates: every official template loads, passes validation, and is in four parts (SATB)', async () => {
    const all = await listTemplates({ drafts: true, library: false });
    assert(all.length >= 4, `found ${all.length}`);
    all.forEach((t) => assert(t.parts.map((p) => p.key).join() === 'soprano,alto,tenor,bass', `${t.id}: ${t.parts.map((p) => p.key)}`));
    all.forEach((t) => assert(t.parts.filter((p) => p.melody).length === 1, `${t.id} should mark one melody part`));
});

test('templates: drafts stay off the shelf unless asked for', async () => {
    const reviewed = await listTemplates({ drafts: false, library: false });
    assert(reviewed.every((t) => t.reviewed), 'a draft is on the shelf');
});

test('templates: Amazing Grace starts on a one-beat pickup and lasts 16 bars of 3/4', async () => {
    const t = await amazing();
    assert(t.pickupBeats === 1, `pickup ${t.pickupBeats}`);
    assert(lengthBeats(t) === 48, `length ${lengthBeats(t)}`);
    const first = t.parts.find((p) => p.melody).guide.notes;
    assert(first[0].beat === 0 && first[0].lyric === 'A-' && first[1].beat === 1 && first[1].lyric === 'maz-', 'bar 1 should land on "maz-"');
});

test('templates: lyrics come back as lines, and melismas add no syllables', async () => {
    const lines = lyricLines((await amazing()).parts[0]);
    assert(lines.length === 4, `${lines.length} lines`);
    assert(lines[0].syllables.map((s) => s.text).join(' ') === 'A- maz- ing grace, how sweet the sound', lines[0].syllables.map((s) => s.text).join(' '));
    assert(lines[3].syllables.at(-1).text === 'see.', 'last word');
});

test('templates: a part that comes in mid-word shows the word\'s start, borrowed from the part that sang it', async () => {
    const t = await amazing();
    const alto = t.parts.find((p) => p.key === 'alto');
    const first = lyricLines(alto, t.parts)[0].syllables;
    assert(first[0].text === 'A-' && first[0].borrowed && first[1].text === 'maz-' && !first[1].borrowed, first.map((s) => s.text).join(' '));
    assert(lyricLines(alto)[0].syllables[0].text === 'maz-', 'without the others, as written');
    const melody = lyricLines(t.parts[0], t.parts)[0].syllables;
    assert(!melody.some((s) => s.borrowed), 'the melody sings its own pickup');
    const later = lyricLines(alto, t.parts).slice(1).flatMap((l) => l.syllables).filter((s) => s.borrowed);
    later.forEach((s) => assert(s.text.endsWith('-'), `only word starts are borrowed: ${s.text}`));
});

test('templates: phrases are listed in order with their sections', async () => {
    const list = phrases(await loadTemplate('are-you-sleeping'));
    assert(list[0].section === 'Soprano alone' && list.at(-1).endBeat === 88, JSON.stringify(list.at(-1)));
    list.slice(1).forEach((p, i) => assert(p.startBeat >= list[i].endBeat, 'phrases out of order'));
});

test('templates: overlapping notes in one part are refused', async () => {
    const t = await amazing();
    t.parts[0].guide.notes[1].beat = 0.5;
    refuses(t, 'one note at a time');
});

test('templates: a note outside its part range is refused', async () => {
    const t = await amazing();
    t.parts[2].guide.notes[0].pitch = 80;
    refuses(t, 'outside the part');
});

test('templates: a pickup of a whole bar or more is refused', async () => {
    const t = await amazing();
    t.pickupBeats = 3;
    refuses(t, 'pickupBeats');
});

test('templates: phrases outside their section are refused', async () => {
    const t = await amazing();
    t.sections[0].phrases.push([40, 60]);
    refuses(t, 'inside the section');
});

test('templates: an order naming a missing part is refused', async () => {
    const t = await amazing();
    t.order.push('descant');
    refuses(t, 'descant');
});

test('guides: the worker renders a part, at the right pitches, and caches it', async () => {
    const t = await amazing();
    const bass = t.parts.find((p) => p.key === 'bass');
    const options = { templateId: t.id, templateVersion: t.version, part: bass, tempo: t.tempo, sampleRate: 48000 };
    const first = renderGuide(options);
    assert(renderGuide(options) === first, 'not cached');
    const audio = await first;
    const note = bass.guide.notes[2]; // C3
    const at = Math.round((beatToSeconds(note.beat, t.tempo) + 0.1) * 48000);
    const found = detectPitch(audio.subarray(at, at + 4096), 48000, { minHz: 50 });
    close(hzToMidi(found.hz), note.pitch, 0.06, 'bass pitch');
});

test('templates: each can be sung by 2, 3 or 4, and every version keeps the melody', async () => {
    const { partsFor, singerOptions } = await import('/js/templates.js');
    for (const t of await listTemplates({ drafts: true, library: false })) {
        assert(singerOptions(t).join() === '2,3,4', `${t.id}: ${singerOptions(t)}`);
        for (const n of [2, 3, 4]) {
            const parts = partsFor(t, n);
            assert(parts.length === n && parts.some((p) => p.melody), `${t.id} for ${n}: ${parts.map((p) => p.key)}`);
        }
    }
});

test('templates: a version without the melody, or with the wrong number of parts, is refused', async () => {
    const t = await amazing();
    t.voicings['2'] = ['alto', 'bass'];
    refuses(t, 'must include the melody');
    const u = await amazing();
    u.voicings['3'] = ['soprano', 'alto'];
    refuses(u, 'exactly 3 parts');
});

test('templates: drafts show on this machine, and stay on once ?drafts=1 was opened', async () => {
    const { showDrafts } = await import('/js/templates.js');
    assert(showDrafts('beta.stackapella.com'), 'on the beta drafts show');
    assert(!showDrafts('stackapella.com'), 'on the launch domain drafts hide');
    // The app's own host: drafts before the launch (the server says), and always for an admin.
    const { setDraftsFor } = await import('/js/templates.js');
    assert(!showDrafts('app.stackapella.com'), 'after the launch, on the app host, drafts hide');
    let heard = 0;
    const { onDraftsChange } = await import('/js/templates.js');
    const stop = onDraftsChange(() => heard++);
    setDraftsFor({ prelaunch: true });
    assert(showDrafts('app.stackapella.com') && heard === 1, 'before the launch they show, and the shelf hears it');
    setDraftsFor({ prelaunch: false, admin: true });
    assert(showDrafts('app.stackapella.com'), 'an admin sees them');
    setDraftsFor({ prelaunch: false, admin: false });
    assert(!showDrafts('app.stackapella.com') && heard === 3, 'hidden again, and each change heard');
    stop();
    assert(showDrafts(), 'on 127.0.0.1 drafts show');
    const { local } = await import('/js/storage.js');
    history.replaceState({}, '', '?drafts=1');
    showDrafts();
    assert(local.get('stackapella.drafts') === '1', 'remembered');
    history.replaceState({}, '', '?drafts=0');
    showDrafts();
    assert(local.get('stackapella.drafts') === '', 'forgotten');
    history.replaceState({}, '', location.pathname);
});

test('templates: a note without a pitch and chords past the end are put right before saving', () => {
    const template = {
        parts: [{ key: 'soprano', range: { low: NaN, high: NaN }, guide: { type: 'notes', notes: [{ beat: 0, length: 1, pitch: 60 }, { beat: 1, length: 1, pitch: NaN }, { beat: 2, length: 1, pitch: undefined }, { beat: 3, length: 1, pitch: 64 }] } }],
        chords: [{ beat: 0, root: 0, quality: 'maj' }, { beat: 2, root: 7, quality: 'maj' }, { beat: 4, root: 5, quality: 'maj' }, { beat: 8, root: 0, quality: 'maj' }],
    };
    const { template: fixed, changed } = repairTemplate(template);
    const part = fixed.parts[0];
    assert(changed && part.guide.notes.map((n) => n.pitch).join() === '60,64', JSON.stringify(part.guide.notes));
    assert(part.range.low === 60 && part.range.high === 64, JSON.stringify(part.range));
    assert(fixed.chords.map((c) => c.beat).join() === '0,2', JSON.stringify(fixed.chords));
    assert(!repairTemplate(fixed).changed, 'a second repair changes nothing');
});

test('templates: the server\'s rules too (TemplateRules.php): six parts, the key pattern, kind, names up to 40', async () => {
    const six = await amazing();
    six.parts = [...six.parts, ...six.parts.slice(0, 3).map((p, i) => ({ ...p, key: `extra-${i}`, melody: false }))];
    refuses(six, 'parts: at most 6');
    const key = await amazing();
    key.parts[1].key = '1alto';
    key.order = key.order?.map((k) => (k === 'alto' ? '1alto' : k));
    key.voicings = {};
    refuses(key, 'key must be lower-case letters');
    const kind = await amazing();
    kind.parts[1].kind = 'drum';
    refuses(kind, 'kind must be voice or instrument');
    const piano = await amazing();
    piano.parts[1].kind = 'instrument';
    validateTemplate(piano);
    const long = await amazing();
    long.parts[1].name = 'x'.repeat(41);
    refuses(long, 'name is missing or over 40 characters');
});

test('templates: chords in order inside the song, and the builder\'s record checked as the server does', async () => {
    const chords = await amazing();
    chords.chords = [{ beat: 4, root: 0, quality: 'maj' }, { beat: 2, root: 5, quality: 'maj' }];
    refuses(chords, 'beats must be ascending');
    const builder = (record) => Object.assign(structuredClone(base), { builder: record });
    const base = await amazing();
    validateTemplate(builder({ progression: { bars: [['I'], ['IV', 'V']], swing: 0.5 }, mix: { band: 0.8 }, sections: [{ bars: [['I']] }] }));
    refuses(builder({ progression: { bars: [] } }), 'the progression needs 1 to 64 bars');
    refuses(builder({ progression: { bars: Array.from({ length: 65 }, () => ['I']) } }), 'the progression needs 1 to 64 bars');
    refuses(builder({ progression: { bars: [['I'], []] } }), 'the progression needs 1 to 64 bars');
    refuses(builder({ progression: { bars: [['Iaaaaaaaaaaaa']] } }), 'up to 12 characters');
    refuses(builder({ progression: { bars: [['I']], swing: 1.5 } }), 'swing must be 0 to 1');
    refuses(builder({ mix: { band: 2 } }), 'each mix level must be 0 to 1');
    refuses(builder({ sections: Array.from({ length: 17 }, () => ({ bars: [['I']] })) }), 'at most 16 sections');
});

// A beta singer: "in the tones, there is no place for breath". The synthesized guide breathes before
// a new line (`br`): the last half beat of the note before it is silent, as scoring forgives it.
test('guides: the practice voice leaves a breath before each new line', async () => {
    const rate = 8000;
    const part = { key: 'tenor', guide: { notes: [{ beat: 0, length: 2, pitch: 57 }, { beat: 2, length: 2, pitch: 59, br: true }, { beat: 4, length: 2, pitch: 60 }] } };
    const samples = await renderGuide({ templateId: 'breath-test', templateVersion: 1, part, tempo: 60, sampleRate: rate });
    const loudest = (from, to) => samples.slice(Math.round(from * rate), Math.round(to * rate)).reduce((m, v) => Math.max(m, Math.abs(v)), 0);
    assert(loudest(1.0, 1.4) > 0.05, 'the note before the breath sounds');
    assert(loudest(1.6, 1.95) < 0.001, `a breath before the new line: ${loudest(1.6, 1.95)}`);
    assert(loudest(3.6, 3.95) > 0.05, 'no breath where the next note is no new line');
});

// Barbershop (Down by the Old Mill Stream): tenor, lead (the tune), baritone and bass, one set or the
// other, never mixed with SATB (as the server's TemplateRules).
test('templates: a barbershop song validates and names its parts; a mix of the two sets does not', async () => {
    const grace = await loadTemplate('amazing-grace');
    const renamed = { soprano: 'tenor', alto: 'lead', tenor: 'baritone', bass: 'bass' };
    const barbershop = JSON.parse(JSON.stringify(grace));
    barbershop.parts.forEach((p) => {
        p.key = renamed[p.key];
        p.melody = p.key === 'lead' || undefined;
        if (!p.melody) delete p.melody;
    });
    barbershop.order = ['lead', 'bass', 'baritone', 'tenor'];
    barbershop.voicings = { 2: ['lead', 'baritone'], 3: ['lead', 'baritone', 'bass'], 4: ['tenor', 'lead', 'baritone', 'bass'] };
    validateTemplate(barbershop);
    assert(barbershop.parts.map((p) => templatePartName(p)).join() === 'Tenor,Lead,Baritone,Bass', barbershop.parts.map((p) => templatePartName(p)).join());
    const mixed = JSON.parse(JSON.stringify(grace));
    mixed.parts[1].key = 'lead';
    mixed.order = mixed.order?.map((k) => (k === 'alto' ? 'lead' : k));
    mixed.voicings = Object.fromEntries(Object.entries(mixed.voicings ?? {}).map(([n, list]) => [n, list.map((k) => (k === 'alto' ? 'lead' : k))]));
    let refused = null;
    try {
        validateTemplate(mixed);
    } catch (error) {
        refused = error;
    }
    assert(refused instanceof TemplateError && /never a mix/.test(refused.message), `a soprano with a lead: ${refused?.message}`);
});

test('templates: a repair keeps only the schema fields of a note (the working sure mark never goes up)', () => {
    const template = { id: 'x', version: 1, title: 'T', tempo: 80, key: 'C', timeSignature: '4/4', parts: [{ key: 'soprano', range: { low: 60, high: 62 }, guide: { type: 'notes', notes: [{ beat: 0, length: 1, pitch: 60, lyric: 'mm', br: true, sure: false }, { beat: 1, length: 1, pitch: 62 }] } }] };
    const { template: fixed, changed } = repairTemplate(template);
    assert(changed, 'changed');
    assert(JSON.stringify(fixed.parts[0].guide.notes[0]) === JSON.stringify({ beat: 0, length: 1, pitch: 60, lyric: 'mm', br: true }), JSON.stringify(fixed.parts[0].guide.notes[0]));
    assert(fixed.parts[0].guide.notes[1] === template.parts[0].guide.notes[1], 'a clean note is the same note');
});

test('templates: a refusal from an earlier round is sent once more; one from this round waits for an edit', () => {
    const template = { id: 'x', version: 1, title: 'T', tempo: 80, key: 'C', timeSignature: '4/4', parts: [{ key: 'soprano', range: { low: 60, high: 60 }, guide: { type: 'notes', notes: [{ beat: 0, length: 1, pitch: 60 }] } }] };
    const refused = (round) => ({ state: 'invalid', template, invalid: { reasons: ['$.description: expected string, got null'], for: JSON.stringify(template), ...(round === undefined ? {} : { round }) } });
    assert(!refusedAsItIs(refused(undefined)), 'an old refusal, from before rounds, goes again');
    assert(!refusedAsItIs(refused(REFUSAL_ROUND - 1)), 'one from an earlier round goes again');
    assert(refusedAsItIs(refused(REFUSAL_ROUND)), 'one from this round waits');
    assert(!refusedAsItIs({ ...refused(REFUSAL_ROUND), template: { ...template, title: 'Changed' } }), 'a changed song goes again');
});
