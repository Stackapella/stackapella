// The creator's plain parts (js/creator.js) and the person's own templates on the device
// (js/stores/templates.js): lines placed on the bar grid with their pickup, the melody's words and
// phrases, copies, import and export, and saving versions and drafts.
import { copyOf, fixFor, melodyFromLines, newTemplate, partOf, phrasesOf, placeLine, readImport, sectionFrom, exportFile } from '/js/creator.js';
import * as db from '/js/db.js';
import { useTransport } from '/js/api.js';
import { mockFetch, mockWanted } from '/js/api-mock.js';
import { account } from '/js/stores/account.js';
import { discardDraft, loadDraft, loadMine, mine, ownTemplate, saveDraft, saveTemplate, deleteTemplate } from '/js/stores/templates.js';
import { loadTemplate, validateTemplate } from '/js/templates.js';
import { assert, test } from './harness.js';

const notes = (list) => list.map(([beat, length, pitch]) => ({ beat, length, pitch }));

test('creator: a line keeps its place in the bar, moved to start after the line before', () => {
    // Sung with its pickup in the lead-in bar (beat 3 of 0 to 4), its downbeat at 4.
    const line = notes([[3, 1, 62], [4, 2, 67], [6, 2, 71]]);
    const first = placeLine(line, { after: 0, perBar: 4 });
    assert(first[0].beat === 3 && first[1].beat === 4, JSON.stringify(first));
    // The next line, sung the same way, goes after beat 8 and keeps its pickup on a beat 4.
    const next = placeLine(line, { after: 8, perBar: 4 });
    assert(next[0].beat === 11 && next[1].beat === 12, JSON.stringify(next));
    // A line without a pickup, on its downbeat: the lead-in bar's silence is closed up.
    const plain = placeLine(notes([[4, 4, 60]]), { after: 0, perBar: 4 });
    assert(plain[0].beat === 0, JSON.stringify(plain));
});

test('creator: the melody from the lines starts at beat 0, with its pickup, words and a phrase a line', () => {
    const lines = [
        { notes: notes([[2, 1, 62], [3, 2, 67], [5, 1, 71]]), lyrics: ['A-', 'maz-', 'ing'] },
        { notes: notes([[3, 3, 67]]), lyrics: ['grace,'] },
    ];
    const { notes: melody, pickupBeats, phrases } = melodyFromLines(lines, '3/4');
    assert(pickupBeats === 1, `pickup ${pickupBeats}`);
    assert(melody[0].beat === 0 && melody[0].br && melody[0].lyric === 'A-', JSON.stringify(melody[0]));
    assert(melody[3].br && melody[3].lyric === 'grace,' && melody[3].beat === 4, JSON.stringify(melody[3]));
    assert(JSON.stringify(phrases) === '[[0,4],[4,7]]', JSON.stringify(phrases));
    const template = { ...newTemplate({ tempo: 80, timeSignature: '3/4' }), title: 'Test', pickupBeats, sections: sectionFrom(phrases, 'Verse', 7), parts: [partOf('soprano', 'Soprano', melody, { melody: true })] };
    validateTemplate(template);
    assert(JSON.stringify(phrasesOf(template)) === JSON.stringify(phrases), 'phrases come back from the lines');
});

test('creator: a copy has a new id at version 1; export and import come back the same', async () => {
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const copy = copyOf(song, 'My Grace');
    assert(copy.id !== song.id && /^[a-f0-9-]{36}$/.test(copy.id) && copy.version === 1 && copy.title === 'My Grace', JSON.stringify([copy.id, copy.version]));
    validateTemplate(copy);
    const { blob, name } = exportFile(copy);
    assert(name === 'my-grace.stackapella.json', name);
    const back = readImport(await blob.text());
    assert(JSON.stringify(back.template) === JSON.stringify(copy), 'the same template');
    // A clash gets a new id; a broken file says what's wrong and can still be opened; not JSON is unreadable.
    assert(readImport(await blob.text(), { taken: () => true }).template.id !== copy.id, 'a new id');
    const broken = readImport(JSON.stringify({ ...copy, parts: [{ ...copy.parts[0], guide: { type: 'notes', notes: [{ beat: 2, length: 1, pitch: 60 }, { beat: 1, length: 1, pitch: 60 }] } }] }));
    assert(broken.problems?.some((p) => p.includes('starts before the previous note ends')) && broken.template, JSON.stringify(broken.problems));
    assert(readImport('not json').unreadable && readImport('{"a":1}').unreadable, 'unreadable');
});

test('creator: own templates save on the device, a change after the account has it is a new version, drafts come and go, and a discard undoes', async () => {
    await new Promise((resolve) => {
        const request = indexedDB.deleteDatabase('stackapella-test-templates');
        request.onsuccess = request.onerror = resolve;
    });
    db.useDatabase('stackapella-test-templates');
    const song = await (await fetch('/templates/amazing-grace.json')).json();
    const copy = copyOf(song, 'Ours');
    await saveTemplate(copy);
    await loadMine({ fresh: true });
    assert(mine.list.length === 1 && mine.list[0].state === 'device' && mine.list[0].visibility === 'private', JSON.stringify(mine.list.map((r) => r.state)));
    // Not on the account yet: a change replaces version 1.
    await saveTemplate({ ...copy, title: 'Ours again' });
    assert((await ownTemplate(copy.id)).version === 1, 'still version 1');
    // Once the account has version 1, a change is version 2.
    await db.put('templates', { ...(await db.get('templates', copy.id)), savedVersion: 1, pending: false });
    await saveTemplate({ ...copy, title: 'Ours, version 2' });
    const own = await ownTemplate(copy.id);
    assert(own.version === 2 && own.title === 'Ours, version 2', `${own.version} ${own.title}`);
    // loadTemplate finds it on the device.
    assert((await loadTemplate(copy.id)).title === 'Ours, version 2', 'loadTemplate');
    await saveDraft({ id: 'draft-1', step: 'words', template: copy, lines: [{ text: 'Amazing grace', notes: [{ beat: 0, length: 1, pitch: 60 }] }] });
    assert(mine.drafts[0].id === 'draft-1' && (await loadDraft('draft-1')).step === 'words', 'a draft');
    const before = JSON.stringify(await loadDraft('draft-1'));
    const undo = await discardDraft('draft-1');
    assert(!mine.drafts.length && !(await loadDraft('draft-1')), 'discarded');
    // Undo puts it back exactly: the same record, its sung lines and its time.
    await undo();
    assert(JSON.stringify(await loadDraft('draft-1')) === before && mine.drafts[0]?.id === 'draft-1', 'undone, as it was');
    assert((await discardDraft('draft-1')) && (await discardDraft('draft-1')) === null, 'nothing left to undo the second time');
    await deleteTemplate(copy.id);
    assert(!mine.list.length && !(await ownTemplate(copy.id)), 'deleted');
    db.useDatabase('stackapella');
});

test("creator: a template the account refuses (422) says why once, and waits for a change instead of going again", async () => {
    await new Promise((resolve) => {
        const request = indexedDB.deleteDatabase('stackapella-test-refused');
        request.onsuccess = request.onerror = resolve;
    });
    db.useDatabase('stackapella-test-refused');
    const signedInAs = account.me;
    account.me = { id: 'ac1', name: 'Ada' };
    let saves = 0;
    useTransport(async (url, init = {}) => {
        const json = (status, body) => new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } });
        if (init.method === 'PUT' && url.includes('/api/templates/')) {
            saves++;
            return json(422, { message: 'Unprocessable Content.', code: 'validation_failed', errors: { template: ['chord 9: beats must be ascending, from 0, inside the song'] } });
        }
        if (url.includes('/api/templates/mine')) return json(200, { data: [], meta: { nextCursor: null } });
        return json(404, { message: 'Not found', code: 'not_found' });
    });
    try {
        const song = await (await fetch('/templates/amazing-grace.json')).json();
        const copy = copyOf(song, 'Refused');
        const record = await saveTemplate(copy);
        assert(saves === 1 && record.state === 'invalid' && record.invalid.reasons[0].startsWith('chord 9'), JSON.stringify([saves, record.state, record.invalid]));
        await loadMine({ fresh: true });
        await new Promise((resolve) => setTimeout(resolve, 50));
        assert(saves === 1, `sent again unchanged: ${saves}`);
        await saveTemplate({ ...copy, title: 'Refused, put right' });
        assert(saves === 2, `a change goes again: ${saves}`);
    } finally {
        account.me = signedInAs;
        useTransport((...args) => (mockWanted() ? mockFetch(...args) : fetch(...args))); // as api.js has it
        db.useDatabase('stackapella');
    }
});

test('creator: the first note drawn into a part that isn\'t there yet adds the part (Write it; 31\'s port)', async () => {
    const { withNotes } = await import('/js/creator.js');
    const empty = { id: 'draft', key: 'C', timeSignature: '4/4', pickupBeats: 0, tempo: 90, sections: [], parts: [] };
    const next = withNotes(empty, 'soprano', [{ beat: 0, length: 1, pitch: 67 }]);
    const soprano = next.parts.find((p) => p.key === 'soprano');
    assert(soprano && soprano.guide.notes.length === 1 && soprano.melody === true, 'the soprano, with its note, the tune');
    const alto = withNotes(next, 'alto', [{ beat: 0, length: 1, pitch: 60 }]).parts.find((p) => p.key === 'alto');
    assert(alto && !alto.melody && alto.name === 'Alto', 'a second part is not the tune');
});

test('creator: a progression in the other mode resets the key, and the key step chooses again (31\'s port)', async () => {
    const { ChordsStep, ChordsKeyStep } = await import('/js/components/create/chords.js');
    let emitted = null;
    const vm = { draft: { template: { key: 'G', tempo: 90, timeSignature: '4/4' }, keyChosen: true, keyMidi: 55 }, $emit: (_, fields) => (emitted = fields) };
    ChordsStep.methods.choose.call(vm, { id: 'sad', name: 'x', bars: [['i']], mode: 'minor' });
    assert(emitted.template.key === 'Am' && emitted.keyChosen === false && emitted.keyMidi === null, 'the placeholder key, not chosen');
    ChordsStep.methods.choose.call({ ...vm, draft: { ...vm.draft, template: { ...vm.draft.template, key: 'D' } } }, { id: 'pop', name: 'x', bars: [['I']], mode: 'major' });
    assert(emitted.keyChosen === undefined, 'the same mode keeps the key chosen');
    assert(!ChordsKeyStep.template.includes('v-if="!range"'), 'the voice chooser stays, so a voice picked can be changed');
});

test('creator: a note worth a listen gets a safe fix (an octave slip back, a note outside the key into it), else none', () => {
    const G = { tonic: 7, minor: false };
    const at = (pitches) => pitches.map((pitch, i) => ({ beat: i, length: 1, pitch }));
    const slip = fixFor(at([67, 83, 69]), 1, G); // G4, B5, A4: the B an octave up
    assert(slip?.pitch === 71 && slip.reason === 'octave', `the slip: ${JSON.stringify(slip)}`);
    const low = fixFor(at([67, 57, 69]), 1, G); // A3 between G4 and A4: up an octave
    assert(low?.pitch === 69 && low.reason === 'octave', `the low slip: ${JSON.stringify(low)}`);
    const sharp = fixFor(at([67, 68, 71]), 1, G); // G#4 in G major: either way is in the key, toward B4 goes up
    assert(sharp?.pitch === 69 && sharp.reason === 'key', `out of the key, going up: ${JSON.stringify(sharp)}`);
    const down = fixFor(at([67, 68, 64]), 1, G); // the next note below: G
    assert(down?.pitch === 67, `out of the key, going down: ${JSON.stringify(down)}`);
    assert(fixFor(at([67, 69, 71]), 1, G) === null, 'an A in G, with its neighbours close: nothing to fix');
    assert(fixFor(at([69, 71, 72]), 2, { tonic: 9, minor: true }) === null, 'C in A minor is in the key');
    assert(fixFor(at([69, 68, 69]), 1, { tonic: 9, minor: true }) === null, 'the raised seventh in A minor stays');
});
