// The backup file (js/backup.js): everything only on this device out as a ZIP and back in on another,
// added to what's there. In the tests' own databases, never the person's.
import * as db from '/js/db.js';
import { backupName, makeBackup, readBackup, restoreBackup } from '/js/backup.js';
import { assert, test } from './harness.js';

async function fresh(name) {
    await new Promise((resolve, reject) => {
        const request = indexedDB.deleteDatabase(name);
        request.onsuccess = () => resolve();
        request.onerror = () => reject(request.error);
    });
    db.useDatabase(name);
}

const lesson = (stars, at) => ({ stars, bestPercent: stars * 30, at });

test('backup: songs, takes, progress and course takes come back on another device, added to its own', async () => {
    try {
        await fresh('stackapella-backup-a');
        const take = Float32Array.from({ length: 4800 }, (_, i) => 0.5 * Math.sin(i / 9));
        await db.put('projects', { id: 'song-1', title: 'Kept', updatedAt: 100 });
        await db.put('projects', { id: 'song-2', title: 'Older here', updatedAt: 100 });
        await db.put('parts', { id: 'part-1', projectId: 'song-1', name: 'Lead', peaks: Float32Array.of(0.1, 0.5), frames: 4800, sampleRate: 48000 });
        await db.putAudio('part-1', take);
        await db.put('learning', { lessons: { a: lesson(3, 5), b: lesson(1, 5) }, xp: { total: 40, days: {} } }, 'progress');
        await db.put('learning', { line: [1, 2] }, 'course:a');
        await db.putAudio('course:a', take.slice(0, 480));
        const blob = await makeBackup(new Date(2026, 9, 31));
        assert(backupName(new Date(2026, 9, 31)) === 'stackapella-backup-2026-10-31.zip', backupName(new Date(2026, 9, 31)));

        await fresh('stackapella-backup-b');
        await db.put('projects', { id: 'song-2', title: 'Newer here', updatedAt: 200 });
        await db.put('learning', { lessons: { b: lesson(3, 9), c: lesson(2, 9) }, xp: { total: 10, days: {} } }, 'progress');
        const restored = await restoreBackup(await readBackup(new Uint8Array(await blob.arrayBuffer())));
        assert(restored.songs === 1 && restored.lessons === 2, `brought back ${JSON.stringify(restored)}`);
        assert((await db.get('projects', 'song-1'))?.title === 'Kept', 'the song');
        assert((await db.get('projects', 'song-2')).title === 'Newer here', 'a newer copy here is kept');
        const part = await db.get('parts', 'part-1');
        assert(part.peaks instanceof Float32Array && part.peaks.length === 2, 'the part, peaks as they were');
        const back = await db.getAudio('part-1');
        assert(back?.length === 4800 && Math.abs(back[100] - take[100]) < 1e-4, 'the take, sample for sample');
        const progress = await db.get('learning', 'progress');
        assert(progress.lessons.a.stars === 3 && progress.lessons.b.stars === 3 && progress.lessons.c.stars === 2 && progress.xp.total === 40, 'progress merged, the best of each');
        assert((await db.get('learning', 'course:a'))?.line?.length === 2 && (await db.getAudio('course:a'))?.length === 480, 'the course take and its line');
    } finally {
        db.useDatabase('stackapella');
    }
});

test('backup: a file that is not one, or from a newer app, is refused', async () => {
    const codeOf = (promise) => promise.then(() => null, (error) => error.code);
    assert((await codeOf(readBackup(new TextEncoder().encode('hello')))) === 'not_backup', 'any other file');
    const { zip } = await import('/js/zip.js');
    const newer = zip([{ name: 'stackapella-backup.json', bytes: new TextEncoder().encode(JSON.stringify({ app: 'stackapella', kind: 'backup', format: 99, records: {} })) }]);
    assert((await codeOf(readBackup(new Uint8Array(await newer.arrayBuffer())))) === 'newer_backup', 'a newer format');
});
