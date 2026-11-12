// The session store (js/stores/session.js): what the studio and the Sing screens share.
import { useDatabase } from '/js/db.js';
import { loadTemplate } from '/js/templates.js';
import { loadProjects, notesSeconds, openProject, startFromTemplate } from '/js/stores/projects.js';
import { closeSession, levels, listenThenRepeat, openSession, practise, selectPhrase, session, setMode, stopPlayback } from '/js/stores/session.js';
import { guideId } from '/js/practice.js';
import { assert, close, test } from './harness.js';
import { useTransport } from '/js/api.js';
import { mockFetch } from '/js/api-mock.js';

/** A database of these tests' own, empty, so the free limit of 3 projects never gets in the way. */
async function freshDatabase() {
    useDatabase('stackapella-test-session');
    const { loadProjects: load, projects, deleteProject } = await import('/js/stores/projects.js');
    await load();
    for (const p of [...projects.list]) await deleteProject(p.id);
    useTransport(mockFetch);
}

test('session: opening a template project gives its parts, phrases, lines and progress', async () => {
    await freshDatabase();
    const project = await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 });
    await openSession(project.id);
    assert(session.isTemplate && session.parts.length === 4, `${session.parts.length} parts`);
    assert(session.phraseList.length === 4, `${session.phraseList.length} phrases`);
    close(session.beatSeconds, 0.75, 1e-9, 'beat at 80 BPM');
    assert(session.progress.done === 0 && session.progress.total === 4, JSON.stringify(session.progress));
    assert(session.focusLines.length === 0, 'no part in focus, no lines');
});

test('session: practising a part focuses it, and the modes set what you hear', async () => {
    const soprano = session.parts.find((p) => p.templatePartKey === 'soprano');
    practise(soprano);
    assert(session.focusPart === soprano && session.focusLines.length === 4, 'the soprano is in focus, with its 4 lines');
    const heard = () => [...levels()].filter(([id, level]) => id.endsWith(':guide') && level.audible).map(([id]) => id);
    assert(heard().join() === guideId(soprano.id), `Listen: ${heard()}`);
    setMode('own');
    assert(heard().length === 3 && !heard().includes(guideId(soprano.id)), `On your own: ${heard()}`);
    // Listen then repeat on your own: the listen half has your guide (the engine mutes it for your turn).
    selectPhrase(0);
    await listenThenRepeat();
    assert(session.playing && heard().includes(guideId(soprano.id)), `listening, on your own: ${heard()}`);
    await stopPlayback();
    assert(!heard().includes(guideId(soprano.id)), `stopped: own again: ${heard()}`);
    selectPhrase(2);
    close(session.position, session.phraseList[2].startSeconds, 1e-9, 'the playhead moves to the phrase');
    const id = session.project.id;
    try {
        await closeSession(); // the pending practice change is saved as it closes
        assert(session.project === null && session.parts.length === 0, 'closed');
        const saved = await (await import('/js/db.js')).get('projects', id);
        assert(saved.practice.focus === soprano.id && saved.practice.mode === 'own' && saved.practice.phrase === 2, JSON.stringify(saved.practice));
    } finally {
        useDatabase('stackapella-test');
    }
});

test('session: a project opened while another is still loading wins, and the first never shows', async () => {
    await freshDatabase();
    try {
        const { startFromTemplate: start } = await import('/js/stores/projects.js');
        const a = await start(await loadTemplate('are-you-sleeping'), { singers: 2 });
        const b = await start(await loadTemplate('amazing-grace'), { singers: 3 });
        const first = openSession(a.id);
        const second = openSession(b.id);
        await Promise.all([first, second]);
        assert(session.project.id === b.id && session.parts.length === 3, `${session.project?.title}, ${session.parts.length} parts`);
        await closeSession();
    } finally {
        useDatabase('stackapella-test');
    }
});

test('session: a real voice from the catalogue plays as the guide, only in its key, and the synth when chosen', async () => {
    const { catalogue, loadCatalogue } = await import('/js/stores/catalogue.js');
    const { setVoice } = await import('/js/stores/session.js');
    const { changeKey } = await import('/js/stores/session.js');
    await freshDatabase();
    try {
        await loadCatalogue();
        const template = await loadTemplate('amazing-grace');
        catalogue.list = [{ id: template.id, version: template.version, parts: [{ key: 'alto', guide: { partKey: 'alto', url: `${location.origin}/tests/fixtures/guide-48000.m4a`, sampleRate: 48000, frames: 144000, offsetFrames: 24000, credit: 'Sarah K.' } }] }];
        const project = await startFromTemplate(template, { singers: 4 });
        await openSession(project.id);
        await new Promise((resolve) => setTimeout(resolve, 50));
        while (session.preparing) await new Promise((resolve) => setTimeout(resolve, 20));
        const alto = session.parts.find((p) => p.templatePartKey === 'alto');
        const soprano = session.parts.find((p) => p.templatePartKey === 'soprano');
        // No part chosen yet: every real voice arrives after the synth has the song ready (renderGuides).
        for (let i = 0; i < 200 && session.guideVoices[alto.id]?.kind !== 'real'; i++) await new Promise((resolve) => setTimeout(resolve, 20));
        const voice = session.guideVoices[alto.id];
        assert(voice?.kind === 'real' && voice.credit === 'Sarah K.', JSON.stringify(voice));
        close(voice.offsetSeconds, 0.5, 1e-9, 'the guide starts half a second in');
        close(alto.guideSeconds, 0.5 + 3, 1e-6, 'its length on the lane');
        assert(session.guideVoices[soprano.id].kind === 'synth' && session.guideVoices[soprano.id].why === null, 'no real soprano: the synth, with nothing to say');
        await setVoice(alto, 'synth');
        assert(session.guideVoices[alto.id].kind === 'synth' && session.guideVoices[alto.id].why === 'chosen', JSON.stringify(session.guideVoices[alto.id]));
        assert(JSON.stringify(session.practice.voices.alto) === '["synth"]', 'remembered in the project, as a list');
        await setVoice(alto, 'real');
        session.project.transpose = 2;
        await changeKey();
        assert(session.guideVoices[alto.id].kind === 'synth' && session.guideVoices[alto.id].why === 'key', JSON.stringify(session.guideVoices[alto.id]));
        await closeSession();
        catalogue.list = [];
    } finally {
        useDatabase('stackapella-test');
    }
});

test('session: a real voice that cannot load (offline) falls back to the synth, and says so', async () => {
    const { catalogue, loadCatalogue } = await import('/js/stores/catalogue.js');
    await freshDatabase();
    try {
        await loadCatalogue();
        const template = await loadTemplate('amazing-grace');
        catalogue.list = [{ id: template.id, version: template.version, parts: [{ key: 'tenor', guide: { partKey: 'tenor', url: `${location.origin}/tests/fixtures/missing.m4a`, sampleRate: 48000, frames: 48000, offsetFrames: 0, credit: null } }] }];
        const project = await startFromTemplate(template, { singers: 4 });
        await openSession(project.id);
        await new Promise((resolve) => setTimeout(resolve, 50));
        while (session.preparing) await new Promise((resolve) => setTimeout(resolve, 20));
        const tenor = session.parts.find((p) => p.templatePartKey === 'tenor');
        assert(session.guideVoices[tenor.id].kind === 'synth' && session.guideVoices[tenor.id].why === 'failed', JSON.stringify(session.guideVoices[tenor.id]));
        await closeSession();
        catalogue.list = [];
    } finally {
        useDatabase('stackapella-test');
    }
});

test('session: a song started from its sheet opens without rendering its guides first, and keeps their outline once its session renders them', async () => {
    try {
        await freshDatabase();
        const template = await loadTemplate('amazing-grace');
        const project = await startFromTemplate(template, { singers: 4, renderGuides: false });
        const started = (await openProject(project.id)).parts;
        assert(started.every((p) => p.guidePeaks === null), 'no outline yet: Start no longer waits for four renders');
        const soprano = template.parts.find((p) => p.key === 'soprano');
        close(started.find((p) => p.templatePartKey === 'soprano').guideSeconds, notesSeconds(soprano, template.tempo), 1e-9, 'the length from the notes');
        assert(notesSeconds({ guide: { notes: [{ beat: 0, length: 1 }, { beat: 6, length: 2 }] } }, 120) === 4, 'the last note ends at beat 8: 4 s at 120');
        await openSession(project.id);
        await new Promise((resolve) => setTimeout(resolve, 50));
        while (session.preparing) await new Promise((resolve) => setTimeout(resolve, 20));
        let kept = [];
        for (let i = 0; i < 50 && kept.length < 4; i++) {
            kept = (await openProject(project.id)).parts.filter((p) => p.guidePeaks?.length);
            if (kept.length < 4) await new Promise((resolve) => setTimeout(resolve, 20));
        }
        assert(kept.length === 4, `the first render's outline kept on ${kept.length} of 4 parts, for Home's thumbnail`);
    } finally {
        closeSession();
        useDatabase('stackapella-test');
    }
});

test('session: your part plays its real voice from the first note; the others start on the synth and swap in as theirs arrive', async () => {
    const { catalogue, loadCatalogue } = await import('/js/stores/catalogue.js');
    const { ready } = await import('/js/stores/session.js');
    await freshDatabase();
    try {
        await loadCatalogue();
        const template = await loadTemplate('amazing-grace');
        const guide = (key, id) => ({ id, partKey: key, url: `${location.origin}/tests/fixtures/guide-48000.m4a?${key}`, sampleRate: 48000, frames: 144000, offsetFrames: 24000, credit: 'Sarah K.' });
        catalogue.list = [{ id: template.id, version: template.version, parts: ['alto', 'tenor'].map((key, i) => ({ key, guide: guide(key, `g${i}`), versions: [{ ...guide(key, `g${i}`), default: true }] })) }];
        const project = await startFromTemplate(template, { singers: 4, focusKey: 'alto' });
        await openSession(project.id);
        await ready();
        const alto = session.parts.find((p) => p.templatePartKey === 'alto');
        const tenor = session.parts.find((p) => p.templatePartKey === 'tenor');
        assert(session.guideVoices[alto.id]?.kind === 'real' && !session.guideVoices[alto.id].loading, `your part, real at once: ${JSON.stringify(session.guideVoices[alto.id])}`);
        for (let i = 0; i < 200 && session.guideVoices[tenor.id]?.kind !== 'real'; i++) await new Promise((resolve) => setTimeout(resolve, 20));
        assert(session.guideVoices[tenor.id]?.kind === 'real', `the tenor's real voice arrives after: ${JSON.stringify(session.guideVoices[tenor.id])}`);
    } finally {
        catalogue.list = [];
        closeSession();
        useDatabase('stackapella-test');
    }
});
