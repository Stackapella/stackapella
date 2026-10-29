"""Read-only projects (M5): a project past the free plan's limit after a downgrade plays but takes no
new recording (the banner says why, and recording says so too), and Storage lets the person choose
which stay editable.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&as=member", "document.querySelector('#app header')")
    project_id = f.js("""(async () => {
        const store = await import('/js/stores/projects.js');
        const db = await import('/js/db.js');
        const project = await store.createProject({ title: 'Locked song', bpm: 100 });
        await db.put('projects', { ...(await db.get('projects', project.id)), version: 4, readOnly: 'limit' });
        return project.id;
    })()""")
    f.go(f"/p/{project_id}?api=mock&as=member", "document.querySelector('#app')")
    f.wait_text(t(f, "locked.limit"))
    f.shot("studio")
    f.js("document.querySelector('footer button.glass-record').click()")
    f.wait(f"document.querySelectorAll('[role=status]').length && [...document.body.innerText.matchAll({json.dumps(t(f, 'locked.limit'))})].length >= 2", what="the toast saying why")
    assert not f.js("(await import('/js/stores/session.js')).session.recording"), "it recorded"
    f.go("/storage?api=mock&as=member", "document.querySelector('h1')")
    f.wait_text(t(f, "locked.editableTitle"), timeout=20)
    f.shot("storage")


if __name__ == "__main__":
    run("locked", journey)
