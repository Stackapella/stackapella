"""M7's friends on a project and own templates, against the real server
(API=http://127.0.0.1:8000 tool/flows.sh together_integration): Ana starts Amazing Grace and makes
an invite for the tenor; Sam joins by the link, adds a tenor of their own and sings it; Ana's studio
gets Sam's part with Sam's name; Ana's own template, made unlisted, opens for Sam by its link; and
Ana removes Sam, whose project says so kindly. Needs the queue worker (takes are processed).
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
ERRORS = ["401 (Unauthorized) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1", "404 (Not Found) http://127.0.0.1", "409 (Conflict) http://127.0.0.1", "410 (Gone) http://127.0.0.1"]
CLOUD = "(await import('/js/stores/cloud.js'))"
SESSION = "(await import('/js/stores/session.js'))"


def account(name, email):
    make = f'$u = App\\Models\\User::factory()->create(["name" => "{name}", "email" => "{email}", "password" => bcrypt("{PASSWORD}")]); echo $u->id;'
    subprocess.run(["php", "artisan", "tinker", "--execute", make], cwd=SERVER, check=True, capture_output=True)


def signed_in(f, email):
    sign_in(f, email, PASSWORD)
    f.wait(f"(await import('/js/stores/account.js')).account.me?.email === {json.dumps(email)}", timeout=20, what="signed in")


def open_together(f):
    """The studio's Sing together dialog, from its More menu (the header's button shows on a wide screen only)."""
    title = t(f, "together.title")
    f.run(f"const d = document.querySelector('header details'); d.open = true; [...d.querySelectorAll('button')].find((b) => b.textContent.trim() === {json.dumps(title)}).click();")


def synced(f, what):
    f.wait(f"(async () => {{ await {CLOUD}.syncNow(); const db = await import('/js/db.js'); return (await db.all('outbox')).length === 0; }})()", timeout=90, what=what)


def journey(f):
    f.expected_errors += ERRORS
    stamp = int(time.time())
    ana, sam = f"ana-{stamp}@example.com", f"sam-{stamp}@example.com"
    account("Ana Flow", ana)
    account("Sam Flow", sam)

    # Ana: a song on the account, and an invite for the tenor.
    signed_in(f, ana)
    project_id = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    synced(f, "Ana's project saved to the account")
    f.go(f"/p/{project_id}?api=real", "document.querySelector('header')")
    f.wait(f"{SESSION}.session.parts.length === 4", what="the studio")
    open_together(f)
    f.wait_text(t(f, "together.inviteTitle"))
    f.run(f"const s = document.querySelector('[role=dialog] select'); s.value = 'tenor'; s.dispatchEvent(new Event('change'));")
    f.click_t("together.makeLink")
    f.wait("document.querySelector('[role=dialog] code')", what="the invite link")
    link = f.js("document.querySelector('[role=dialog] code').textContent")
    path = "/join/" + link.split("/join/")[1]
    f.shot("invite")

    sam_flow = Flow(f.base, "desktop", "together_integration-sam")
    g = sam_flow
    g.expected_errors += ERRORS
    try:
        # Sam: the link, Join, and a tenor of their own, sung.
        signed_in(g, sam)
        g.go(path + "?api=real", "document.querySelector('h1')")
        g.wait_text(t(g, "invite.from", {"name": "Ana Flow"}))
        g.wait_text(t(g, "join.yourPart", {"part": "Tenor"}))
        g.shot("join")
        g.click_t("invite.accept")
        g.wait(f"location.pathname === '/p/{project_id}'", what="the shared project")
        g.wait_text(t(g, "together.banner", {"owner": "Ana Flow"}))
        g.wait(f"{SESSION}.session.parts.length === 4", what="Ana's parts on Sam's device")
        g.js("(await import('/js/audio.js')).unlock()")
        g.run(f"""const s = {SESSION}; const tenor = await s.addMySlot('tenor');
            const samples = new Float32Array(48000 * 3).map((_, i) => 0.3 * Math.sin((2 * Math.PI * 196 * i) / 48000));
            const {{ peaks }} = await import('/js/dsp.js');
            await s.storeTake(tenor, {{ offsetSeconds: 0, nudgeSeconds: 0, sampleRate: 48000, frames: samples.length, framesPerPeak: 512, peaks: peaks(samples, 512) }}, samples);""")
        g.wait(f"{SESSION}.session.parts.filter((p) => p.createdBy?.name === 'Sam Flow').length === 1", what="Sam's own tenor")
        synced(g, "Sam's tenor sent")
        g.shot("sam-sang")
        # Ana: Sam's tenor arrives, with Sam's name.
        f.wait(f"(async () => {{ await {CLOUD}.syncNow(); return (await (await import('/js/db.js')).all('parts')).some((p) => p.projectId === {json.dumps(project_id)} && p.createdBy?.name === 'Sam Flow' && p.frames > 0); }})()", timeout=120, what="Sam's tenor on Ana's device")
        f.go(f"/p/{project_id}?api=real", "document.querySelector('header')")
        f.wait_text("Sam Flow")
        f.shot("ana-sees-sam")

        # Ana's own template, unlisted: Sam opens it by its link.
        template_id = f.js("""(async () => {
            const { copyOf } = await import('/js/creator.js');
            const { saveTemplate } = await import('/js/stores/templates.js');
            const song = await (await fetch('/templates/amazing-grace.json')).json();
            return (await saveTemplate(copyOf(song, 'Ana flow grace'), { visibility: 'unlisted' })).id;
        })()""")
        f.wait(f"(await import('/js/stores/templates.js')).mine.list.find((r) => r.id === {json.dumps(template_id)})?.state === 'saved'", timeout=30, what="the template on the account")
        g.go(f"/t/{template_id}?api=real", "document.querySelector('h1')")
        g.wait_text("Ana flow grace")
        g.wait_text(t(g, "template.hearAll"))
        g.shot("template-link")

        # Ana removes Sam, keeping the tenor; Sam's copy says so.
        open_together(f)
        f.wait_text(t(f, "together.who"))
        remove = t(f, "together.remove")
        f.wait(f"[...document.querySelectorAll('[role=dialog] button')].some((b) => b.textContent.trim() === {json.dumps(remove)})", what="Sam in the list, with Remove")
        f.click_t("together.remove")
        f.click_t("together.keepTheirs")
        f.wait_text(t(f, "together.removed", {"name": "Sam Flow"}))
        g.go(f"/p/{project_id}?api=real", "document.querySelector('header')")
        g.wait_text(t(g, "together.noLonger"), timeout=30)
        g.shot("removed")
        g.close()
    except Exception:
        try:
            g.shot("failed")
            g.chrome.close()
        except Exception:
            pass
        raise


if __name__ == "__main__":
    run("together_integration", journey, sizes=("desktop",))
