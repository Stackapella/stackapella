// My parts records a staged next version (decisions 2026-11-13: Amazing Grace's corrected v5 is recorded before it becomes
// the song's own): when beta.parts.list names a version other than the app's copy, the part's own version comes from
// templates.show?version=, and the project (and so every take) names it. A version the server doesn't have either is
// still "the notes changed".
import { useTransport } from '/js/api.js';
import { mockFetch } from '/js/api-mock.js';
import { startBetaRecording, TemplateChanged } from '/js/beta.js';
import { useDatabase } from '/js/db.js';
import { loadTemplate } from '/js/templates.js';
import { assert, test } from './harness.js';

/** The mock, with templates.show for amazing-grace answering `serve(version)` and recording what it was asked. */
function serving(serve) {
    const asked = [];
    useTransport(async (input, init) => {
        const url = new URL(typeof input === 'string' ? input : input.url, location.href);
        if (url.pathname === '/api/templates/amazing-grace') {
            asked.push(url.searchParams.get('version'));
            const template = await serve(url.searchParams.get('version'));
            return new Response(JSON.stringify({ data: { template, voices: [] } }), { headers: { 'Content-Type': 'application/json' } });
        }
        return mockFetch(input, init);
    });
    return asked;
}

async function freshDatabase(name) {
    useDatabase(name);
    const { loadProjects, projects, deleteProject } = await import('/js/stores/projects.js');
    await loadProjects();
    for (const p of [...projects.list]) await deleteProject(p.id);
}

const part = (version) => ({ id: `staged-${version}`, partKey: 'alto', template: { id: 'amazing-grace', version }, latestSubmission: null, guides: [] });

test("beta: a part on a staged next version records that version's notes, fetched by version", async () => {
    const here = location.pathname + location.search;
    try {
        await freshDatabase('stackapella-test-beta-staged');
        const bundled = await loadTemplate('amazing-grace');
        const next = bundled.version + 1;
        const asked = serving(async (version) => ({ ...structuredClone(bundled), version: Number(version) }));
        const project = await startBetaRecording(part(next));
        assert(asked.join() === String(next), `asked for ${asked.join() || 'nothing'}`);
        assert(project.template.version === next, `the project's notes are version ${project.template.version}`);
        assert(project.beta.templateVersion === next, `its takes name version ${project.beta.templateVersion}`);
    } finally {
        useTransport(mockFetch);
        history.replaceState(null, '', here);
    }
});

test("beta: a part on the app's own version never asks the server for the notes", async () => {
    const here = location.pathname + location.search;
    try {
        await freshDatabase('stackapella-test-beta-staged-same');
        const bundled = await loadTemplate('amazing-grace');
        const asked = serving(async () => bundled);
        const project = await startBetaRecording(part(bundled.version));
        assert(asked.length === 0, `asked for ${asked.join()}`);
        assert(project.beta.templateVersion === bundled.version, `its takes name version ${project.beta.templateVersion}`);
    } finally {
        useTransport(mockFetch);
        history.replaceState(null, '', here);
    }
});

test("beta: a version the server doesn't have either is still the notes changed", async () => {
    const here = location.pathname + location.search;
    try {
        await freshDatabase('stackapella-test-beta-staged-gone');
        const bundled = await loadTemplate('amazing-grace');
        serving(async () => bundled); // answers with the current version, whatever was asked
        let refused = null;
        try {
            await startBetaRecording(part(bundled.version + 7));
        } catch (error) {
            refused = error;
        }
        assert(refused instanceof TemplateChanged, `refused with ${refused}`);
    } finally {
        useTransport(mockFetch);
        history.replaceState(null, '', here);
    }
});
