"""Making a warm-up (docs/warmup-builder-plan.md, the teacher's side): Settings' Practice row opens
/warmup with no code, the builder. Start from a preset, add and take out notes, pick the sound; Hear
it plays it; Share link gives the device's share sheet the link, and shows it to copy; Try it yourself
opens it as a student would, and Back finds the warm-up as it was. Against the mock.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
BUILDER = "document.querySelector('[data-warmup-builder]')"
PATTERN = "[...document.querySelectorAll('[data-warmup-pattern] button')].map((b) => b.textContent.trim())"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    # One quiet row in Settings' Practice: the way in, for everyone.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-make-warmup]')")
    f.run("document.querySelector('#practice').scrollIntoView({ block: 'start' })")
    f.shot("settings-practice")
    f.run("document.querySelector('[data-make-warmup]').click()")
    f.wait(BUILDER, what="the builder")
    assert f.js("location.pathname + location.hash") == "/warmup"
    f.wait_text(t(f, "warmup.make.title"))
    assert f.js(PATTERN) == ["do", "re", "mi", "fa", "so", "fa", "mi", "re", "do"], "the five-note scale to start"
    f.shot("builder")

    # Or a lesson's exercise to start from (slice 5): its notes in, named after the lesson; then a preset as before.
    f.wait("document.querySelectorAll('[data-warmup-from-lesson] option').length > 2", what="lessons to start from")
    f.run("const s = document.querySelector('[data-warmup-from-lesson] select'); s.value = s.options[1].value; s.dispatchEvent(new Event('change'))")
    f.wait("document.querySelector('[data-warmup-name-input]').value === document.querySelector('[data-warmup-from-lesson] option:nth-child(2)').textContent.trim()", what="named after the lesson")
    assert f.js("document.querySelectorAll('[data-warmup-pattern] li button').length") >= 3, "the lesson's notes in"
    f.shot("from-lesson")
    f.run("const i = document.querySelector('[data-warmup-name-input]'); i.value = ''; i.dispatchEvent(new Event('input'))")

    # A preset, a note added and one taken out; the sound.
    f.run("[...document.querySelectorAll('[data-warmup-preset]')][2].click()")
    f.wait(f"{PATTERN}.join(' ') === 'do mi so high do so mi do'", what="the arpeggio")
    assert f.js("document.querySelector('[data-warmup-name-input]').value") == t(f, "warmup.make.preset.arpeggio"), "a preset names it"
    f.run("[...document.querySelectorAll('[data-warmup-notes] button')][0].click()")
    f.wait(f"{PATTERN}.at(-1) === 'low so'", what="low so added")
    f.run("[...document.querySelectorAll('[data-warmup-pattern] button')].at(-1).click()")
    f.wait(f"{PATTERN}.length === 7", what="the note taken out")
    f.run("[...document.querySelectorAll('[data-warmup-sound] [role=radio]')][2].click()")
    f.run("const i = document.querySelector('[data-warmup-name-input]'); i.value = 'Arpeggio on oo'; i.dispatchEvent(new Event('input'))")

    # Hear it plays it, then the button reads Hear it again.
    f.js(f"{AUDIO}.unlock()")
    f.run(f"[...{BUILDER}.querySelectorAll('button')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'warmup.make.hear'))}).click()")
    f.wait(f"[...{BUILDER}.querySelectorAll('button')].some((b) => b.textContent.trim() === {json.dumps(t(f, 'studio.stop'))})", what="hearing it")
    f.wait(f"[...{BUILDER}.querySelectorAll('button')].some((b) => b.textContent.trim() === {json.dumps(t(f, 'warmup.make.hear'))})", timeout=30, what="heard to the end")

    # Share link: the share sheet gets the link, which is shown to copy as well.
    f.run("window.__shared = null; navigator.share = async (data) => { window.__shared = data; }")
    f.run("document.querySelector('[data-warmup-share]').click()")
    f.wait("document.querySelector('[data-warmup-link]') && window.__shared", what="the link shared")
    shared = f.js("window.__shared")
    assert shared["text"] == t(f, "warmup.make.shareText", {"name": "Arpeggio on oo"}), shared
    assert "/warmup#" in shared["url"] and shared["url"] == f.js("document.querySelector('#warmup-link').value"), shared
    warmup = f.js(f"(await import('/js/warmup.js')).decodeWarmup({json.dumps(shared['url'].split('#')[1])})")
    assert warmup["steps"] == [0, 4, 7, 12, 7, 4, 0] and warmup["sound"] == "oo" and warmup["name"] == "Arpeggio on oo", warmup
    # The QR code for a class (Hillel: yes), drawn from the pinned library, loaded only now.
    f.wait("document.querySelector('[data-warmup-qr] path')?.getAttribute('d')?.length > 100", what="the QR code", timeout=20)
    assert f.js("[...document.scripts].some((s) => s.src.includes('qrcode-generator@1.4.4') && s.integrity.startsWith('sha384-'))"), "the library pinned, with SRI"
    f.run("document.querySelector('[data-warmup-qr]').scrollIntoView({ block: 'center' })")
    f.shot("shared")

    # Try it yourself: the student's card; Back finds the draft as it was.
    f.run("document.querySelector('[data-warmup-try]').click()")
    f.wait("document.querySelector('[data-warmup-card]')", what="the student's card")
    assert f.js("document.querySelector('[data-warmup-name]').textContent.trim()") == "Arpeggio on oo"
    f.run("history.back()")
    f.wait(BUILDER, what="back to the builder")
    assert f.js(PATTERN) == ["do", "mi", "so", "high do", "so", "mi", "do"], "the draft kept"
    assert f.js("document.querySelector('[data-warmup-name-input]').value") == "Arpeggio on oo"


if __name__ == "__main__":
    run("warmup_builder", journey)
