"""M4's cloud save, against the real server (API=http://127.0.0.1:8000 tool/flows.sh
sync_integration), with two devices (two browser profiles): a phone and a Mac.

A new account signs in on the phone, records a part and earns a lesson's stars; the Mac signs in,
has the stars, and opens the project, take and all. Both change the title before hearing from the
other: the second to save is asked "Changed on another device" and keeps theirs. The phone deletes
the project; the Mac sees it go, and brings it back from "Recently deleted". A fourth project past
the free plan stays on the device, marked "Not saved to your account". Needs the queue worker (the take's processing is a job).
"""
import json
import subprocess
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import Flow, run  # noqa: E402
from accounts_integration import LOG, PASSWORD, sign_in, t  # noqa: E402

SERVER = LOG.parents[2]

PROJECTS = "(await import('/js/stores/projects.js'))"
CLOUD = "(await import('/js/stores/cloud.js'))"
DB = "(await import('/js/db.js'))"
LEARNING = "(await import('/js/stores/learning.js'))"
LESSON = "1-comfortable"
EXPECTED = [
    "401 (Unauthorized) http://127.0.0.1",
    "419 (Unknown Status Code) http://127.0.0.1",
    "404 (Not Found) http://127.0.0.1",
    "409 (Conflict) http://127.0.0.1",  # the stale title, on purpose
    "403 (Forbidden) http://127.0.0.1",  # the fourth project, on purpose
]


def synced(f, what):
    """Runs the sync and waits until nothing is waiting."""
    f.run(f"await {CLOUD}.syncNow()")
    f.wait(f"{CLOUD}.cloud.pending === 0", timeout=60, what=what)


def journey(phone):
    phone.expected_errors += EXPECTED
    email = f"sync-{int(time.time())}@example.com"

    # A new, verified account (made by the server's own factory: sign-ups are limited to 5 an hour),
    # and the phone signs in.
    make = f'$u = App\\Models\\User::factory()->create(["email" => "{email}", "password" => bcrypt("{PASSWORD}")]); echo $u->id;'
    subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True)
    sign_in(phone, email, PASSWORD)
    phone.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="the phone signed in")

    # A project with one recorded part (a second of a 220 Hz tone).
    project_id = phone.js(f"""(async () => {{
        const store = {PROJECTS};
        const project = await store.createProject({{ title: 'Phone title', bpm: 90 }});
        const samples = new Float32Array(48000).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 220 * i) / 48000));
        await store.savePart({{ id: crypto.randomUUID(), projectId: project.id, name: 'Lead', color: '#f2a06b', order: 0, volume: 1, pan: 0, muted: false, solo: false, createdAt: Date.now(),
            offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: null }}, samples);
        return project.id;
    }})()""")
    synced(phone, "the project and its take went up")
    # And a lesson's stars, for the course progress.
    phone.run(f"const l = {LEARNING}; await l.loadLearning(); await l.finishLesson(LESSON, {{ percent: 95 }}); await l.syncLearning();".replace("LESSON", json.dumps(LESSON)))
    phone.wait(f"(await {DB}.all('parts')).every((p) => p.syncedTakeId)", timeout=60, what="the take confirmed")
    phone.go("/?api=real", "document.querySelector('#band-projects')")
    phone.shot("phone-recorded")

    # The Mac signs in: the project comes down, with its take.
    mac = Flow(phone.base, "desktop", phone.name)
    mac.expected_errors += EXPECTED
    try:
        sign_in(mac, email, PASSWORD)
        mac.wait("(await import('/js/stores/account.js')).account.me", timeout=20, what="the Mac signed in")
        mac.wait(f"(await {DB}.get('projects', {json.dumps(project_id)}))", timeout=60, what="the project on the Mac")
        mac.wait(f"(await {DB}.all('parts')).some((p) => p.projectId === {json.dumps(project_id)} && p.frames === 48000)", timeout=60, what="the part on the Mac")
        mac.wait(f"(await {DB}.getAudio((await {DB}.all('parts'))[0].id))?.length === 48000", timeout=60, what="the take's audio on the Mac")
        mac.wait(f"(await {LEARNING}.loadLearning()).progress.lessons[{json.dumps(LESSON)}]?.stars === 3", timeout=30, what="the phone's stars on the Mac")
        mac.go(f"/p/{project_id}?api=real", "document.querySelector('#app')")
        mac.wait("(await import('/js/stores/session.js')).session.project?.title === 'Phone title'", timeout=20, what="the project open on the Mac")
        mac.shot("mac-opened")

        # Both rename it; the phone saves first, so the Mac (in the studio, not yet pulled) is asked, and keeps theirs.
        phone.run(f"const store = {PROJECTS}; const p = await {DB}.get('projects', {json.dumps(project_id)}); await store.saveProject({{ ...p, title: 'Phone renamed' }});")
        synced(phone, "the phone's rename went up")
        mac.run(f"const store = {PROJECTS}; const p = await {DB}.get('projects', {json.dumps(project_id)}); await store.saveProject({{ ...p, title: 'Mac renamed' }});")
        mac.run(f"await {CLOUD}.syncNow()")
        mac.wait_text(t(mac, "cloud.conflict.title"), timeout=30)
        mac.shot("mac-conflict")
        mac.click_t("cloud.conflict.theirs")
        mac.wait(f"(await {DB}.get('projects', {json.dumps(project_id)})).title === 'Phone renamed'", timeout=30, what="the Mac took the phone's title")

        # The phone deletes it; the Mac sees it go, and restores it from Recently deleted.
        phone.run(f"await {PROJECTS}.deleteProject({json.dumps(project_id)})")
        synced(phone, "the delete went up")
        mac.run(f"await {CLOUD}.syncNow()")
        mac.wait(f"!(await {DB}.get('projects', {json.dumps(project_id)}))", timeout=30, what="the project gone from the Mac")
        mac.go("/storage?api=real", "document.querySelector('#bin-heading')")
        mac.wait_text("Phone renamed", timeout=20)
        mac.shot("mac-bin")
        mac.click_t("cloud.bin.restore")
        mac.wait(f"(await {DB}.get('projects', {json.dumps(project_id)}))", timeout=60, what="the project back on the Mac")
        mac.wait(f"(await {DB}.all('parts')).some((p) => p.projectId === {json.dumps(project_id)} && p.frames === 48000)", timeout=60, what="its part back on the Mac")
        mac.shot("mac-restored")

        # Two more fill the free plan; a fourth, made past the device's own check, stays on the Mac.
        mac.run(f"const store = {PROJECTS}; await store.createProject({{ title: 'Second', bpm: 90 }}); await store.createProject({{ title: 'Third', bpm: 90 }});")
        synced(mac, "two more projects went up")
        mac.run(f"""const now = Date.now(); const p = {{ id: crypto.randomUUID(), title: 'Fourth', bpm: 90, key: null, timeSignature: '4/4', createdAt: now, updatedAt: now }};
            await {DB}.put('projects', p); await {CLOUD}.changed('project', p);""")
        synced(mac, "the fourth was refused")
        mac.wait(f"(await {DB}.all('projects')).some((p) => p.title === 'Fourth' && p.notSaved === 'limit')", timeout=30, what="the fourth marked not saved")
        mac.go("/?api=real", "document.querySelector('#band-projects')")
        mac.wait_text(t(mac, "cloud.notSaved"), timeout=20)
        mac.shot("mac-not-saved")
        mac.go("/storage?api=real", "document.querySelector('#bin-heading')")
        mac.wait_text(t(mac, "cloud.status.saved"), timeout=20)
        mac.shot("mac-storage")
        mac.close()
    except Exception:
        mac.shot("mac-failed")
        mac.chrome.close()
        raise


if __name__ == "__main__":
    run("sync_integration", journey, sizes=("phone",))
