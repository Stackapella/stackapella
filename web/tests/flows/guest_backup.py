"""Download a backup (docs/account-nudges-plan.md, Hillel's answer 3): a guest's Settings card has a
quiet way to keep their work without an account. A backup is made, everything on the device is
deleted, and restoring the file brings the song and its take back, said in a toast after the reload.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SETTINGS = "/settings?api=mock&scenario=&as=signedOut"
CARD = "document.querySelector('[data-guest-settings]')"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go(SETTINGS, "document.querySelector('#app header')")
    f.run("""(async () => { const s = await import('/js/stores/projects.js'); const p = await s.createProject({ title: 'Backup test' });
        await s.savePart({ id: crypto.randomUUID(), projectId: p.id, name: 'Lead', color: '#f59e6b', order: 0, volume: 1, pan: 0, muted: false, solo: false,
            offsetSeconds: 0, frames: 4800, sampleRate: 48000, peaks: [0.2, 0.4], createdAt: Date.now() }, new Float32Array(4800).fill(0.25));
        window.backupProject = p.id; })()""")
    f.wait("window.backupProject", what="a recorded song")
    f.go(SETTINGS, f"{CARD}?.querySelector('[data-backup]')")
    f.run(f"{CARD}.scrollIntoView({{ block: 'center' }})")
    f.shot("guest-card")
    # Download: the file is made and handed to the browser (its link is caught here).
    f.run("window.downloads = []; const click = HTMLAnchorElement.prototype.click; HTMLAnchorElement.prototype.click = function () { if (this.download) window.downloads.push({ name: this.download, href: this.href }); else click.call(this); };")
    f.run("document.querySelector('[data-backup-download]').click()")
    f.wait("window.downloads.length === 1", what="the backup downloaded")
    assert f.js("window.downloads[0].name").startswith("stackapella-backup-"), f.js("window.downloads[0].name")
    f.wait_text(t(f, "settings.guest.backupMade"))
    f.run("window.backupBytes = await (await fetch(window.downloads[0].href)).arrayBuffer();")
    # Everything here deleted (the database only, so the page keeps the file), then restored from it.
    f.run("""const db = await import('/js/db.js'); const id = (await db.all('projects')).find((x) => x.title === 'Backup test').id; await db.remove('projects', id);
        for (const part of await db.allBy('parts', 'projectId', id)) { await db.remove('parts', part.id); await db.remove('audio', part.id); }""")
    f.run("""const input = document.querySelector('[data-backup-restore] input'); const dt = new DataTransfer();
        dt.items.add(new File([window.backupBytes], 'backup.zip', { type: 'application/zip' })); input.files = dt.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));""")
    f.wait(f"{CARD} && document.body.innerText.includes({I18N}.t('settings.guest.restored', {{ songs: 1, lessons: 0 }}))", what="the restored toast after the reload")
    f.shot("restored")
    assert f.js("""(async () => { const db = await import('/js/db.js'); const p = (await db.all('projects')).find((x) => x.title === 'Backup test');
        if (!p) return false; const part = (await db.allBy('parts', 'projectId', p.id))[0]; const audio = part && await db.getAudio(part.id);
        return !!audio && audio.length === 4800 && Math.abs(audio[10] - 0.25) < 0.001; })()"""), "the song and its take came back"
    # Any other file is refused, plainly.
    f.run("""const input = document.querySelector('[data-backup-restore] input'); const dt = new DataTransfer();
        dt.items.add(new File(['not a backup'], 'notes.zip')); input.files = dt.files; input.dispatchEvent(new Event('change', { bubbles: true }));""")
    f.wait_text(t(f, "settings.guest.notBackup"))
    f.run("(async () => { const s = await import('/js/stores/projects.js'); await s.loadProjects(); for (const p of s.projects.all.filter((x) => x.title === 'Backup test')) await s.deleteProject(p.id); })()")


if __name__ == "__main__":
    run("guest_backup", journey)
