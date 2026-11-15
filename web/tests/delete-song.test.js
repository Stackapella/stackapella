// Deleting a song from Your songs (components/confirm.js deleteSong): one with nothing recorded yet goes at once, with
// no question, and keeps the toast's Undo (Hillel, 2026-11-15); one with takes, or whose count isn't known, still asks.
import { useTransport } from '/js/api.js';
import { mockFetch } from '/js/api-mock.js';
import { ConfirmHost, deleteSong } from '/js/components/confirm.js';
import { toasts } from '/js/components/ui.js';
import * as db from '/js/db.js';
import { t } from '/js/i18n.js';
import { createProject, loadProjects, projects } from '/js/stores/projects.js';
import { assert, test } from './harness.js';

const { state } = ConfirmHost.setup(); // the open question, as the host shows it
const keepIt = () => ConfirmHost.methods.answer(false);
const tick = () => new Promise((resolve) => queueMicrotask(resolve));

async function freshDatabase(name) {
    db.useDatabase(name);
    useTransport(mockFetch);
    const { deleteProject } = await import('/js/stores/projects.js');
    await loadProjects();
    for (const p of [...projects.list]) await deleteProject(p.id);
}

test('delete song: one with nothing recorded goes at once, with Undo in the toast', async () => {
    try {
        await freshDatabase('stackapella-test-delete-song');
        const made = await createProject({ title: 'Nothing yet' });
        const summary = projects.list.find((p) => p.id === made.id);
        assert(summary?.recordedCount === 0, `recorded ${summary?.recordedCount}`);
        toasts.list = [];
        const going = deleteSong(summary);
        await tick();
        assert(!state.open, `a question was asked: ${state.open?.title}`); // checked before waiting, so asking fails, not hangs
        assert((await going) === true, 'deleteSong said it went');
        assert(!(await db.get('projects', made.id)), 'the song is still stored');
        const shown = toasts.list.at(-1);
        assert(shown?.message === t('confirm.deleteProject.done', { title: 'Nothing yet' }), `toast: ${shown?.message}`);
        const undo = shown.actions.find((a) => a.label === t('common.undo'));
        assert(undo, 'the toast has no Undo');
        await undo.run();
        assert(await db.get('projects', made.id), 'Undo did not bring it back');
    } finally {
        if (state.open) keepIt();
        toasts.list = [];
        db.useDatabase('stackapella-test');
    }
});

for (const [what, recordedCount] of [['with takes', 2], ['whose count is unknown', undefined]]) {
    test(`delete song: one ${what} still asks first, and Keep it keeps it`, async () => {
        try {
            await freshDatabase('stackapella-test-delete-song');
            const made = await createProject({ title: 'Asks first' });
            const going = deleteSong({ ...projects.list.find((p) => p.id === made.id), recordedCount });
            await tick();
            assert(state.open?.title === t('confirm.deleteProject.title', { title: 'Asks first' }), `asked: ${state.open?.title ?? 'nothing'}`);
            keepIt();
            assert((await going) === false, 'deleteSong went ahead');
            assert(await db.get('projects', made.id), 'the song was deleted');
        } finally {
            if (state.open) keepIt();
            db.useDatabase('stackapella-test');
        }
    });
}
