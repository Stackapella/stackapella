"""Course clips in the beta singers' admin (docs/courses/clips-to-record.md, "For the frontend"):
Hillel asks a singer for one course clip, then for a whole recording session of them, from Add part;
and a clip's take (no song: kind "clip" with its brief) shows in the review queue and opens, with
what they were asked in place of the song and no version chip.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
SENT = "(globalThis.mockBodies ?? []).filter((c) => c.operation === 'admin.beta.assignments.create').map((c) => c.body)"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def add_part(f):
    f.click_t("admin.singers.addPart")
    f.wait("document.querySelector('[data-add-part]')", what="Add part")


def journey(f):
    # One clip.
    f.go("/admin/singers?api=mock&scenario=&as=admin", "document.querySelector('main')")
    f.wait_text(t(f, "admin.singers.addPart"))
    add_part(f)
    f.click_t("admin.clips.kind.clip")
    f.wait("document.querySelector('[data-clip-choice]')", what="the clip list")
    f.run("const s = document.querySelector('[data-clip-choice]'); s.value = 'vibrato.wobble'; s.dispatchEvent(new Event('change'));")
    f.wait_text("slow, wide wobble")  # its brief under the list
    f.shot("clip")
    f.run("globalThis.mockBodies = []; document.querySelector('[data-add-part]').requestSubmit();")
    f.wait(f"{SENT}.length === 1", what="the clip asked for")
    assert f.js(f"{SENT}[0]") == {"clipId": "vibrato.wobble", "minutes": 10}, f.js(f"{SENT}[0]")

    # A whole session: each of its open clips, sharing the session's 20 minutes.
    f.wait("!document.querySelector('[data-add-part]')", what="the sheet closed")
    add_part(f)
    f.click_t("admin.clips.kind.session")
    f.run("const s = document.querySelector('[data-session-choice]'); s.value = [...s.options].find((o) => o.textContent.includes('Vibrato')).value; s.dispatchEvent(new Event('change'));")
    f.wait_text("vibrato.wobble")  # the session's open clips, listed
    f.shot("session")
    f.run("globalThis.mockBodies = []; document.querySelector('[data-add-part]').requestSubmit();")
    f.wait(f"{SENT}.length === 1 && !document.querySelector('[data-add-part]')", what="the session asked for")
    assert f.js(f"{SENT}[0]") == {"clipId": "vibrato.wobble", "minutes": 20}, f.js(f"{SENT}[0]")

    # A clip's take in the queue: its slot and brief, no song, no version; it opens.
    f.run("sessionStorage.setItem('stackapella.mockClipTake', '1')")
    f.go("/admin/review?api=mock&scenario=&as=admin", "document.querySelector('main')")
    f.wait_text(t(f, "admin.review.clipTitle", {"slot": "vibrato.natural"}))
    f.wait("document.querySelector('[data-clip-brief]')", what="the clip's brief in the queue")
    f.shot("queue")
    f.run("document.querySelector('main ul a').click()")
    f.wait("document.querySelector('[data-clip-asked]')", what="what they were asked, on the take")
    f.wait("!document.querySelector('main .btn-primary').disabled", timeout=30, what="the audio loaded")
    assert not f.js("!!document.querySelector('#review-others')"), "a clip has no other parts to mix"
    f.shot("take")
    f.run("sessionStorage.removeItem('stackapella.mockClipTake')")


if __name__ == "__main__":
    run("admin_clips", journey)
