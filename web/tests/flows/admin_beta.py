"""M2, Hillel's side of the beta on the mock API: the home's numbers; the review queue, a take
played in the mix over the fixtures' audio, A/B against the synth, a light fix saved, a phrase
picked on the chart, another take asked for, then a second visit refused kindly once decided;
inviting a singer (the link shown once) and copying a status message; the real voices per part
and its progress; and the feedback inbox. (pseudo_language.py checks their words.)
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/admin?api=mock&scenario=", "document.querySelector('main')")
    f.wait_text(t(f, "admin.home.beta"))
    f.shot("home")

    # The queue, then the take.
    f.click_t("admin.nav.review")
    f.wait_text("Amazing Grace")
    f.shot("queue")
    f.run("document.querySelector('main ul a').click()")
    f.wait("location.pathname === '/admin/review/Tz4Kq8'", what="the take's page")
    f.wait_text(t(f, "admin.review.singerSays"))
    f.wait_text(t(f, "admin.review.status.in_review"))  # opened: the singer sees In review
    f.wait("!document.querySelector('main .btn-primary').disabled", timeout=30, what="the audio loaded")
    f.wait("document.querySelectorAll('svg polyline').length > 0", timeout=30, what="the singer's line on the chart")
    f.wait_text(t(f, "admin.review.realVoices", {"parts": t(f, "parts.bass")}))
    f.shot("take")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("admin.review.play")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('review:take') && {AUDIO}.engine.parts.has('review:bass')", what="the take in the mix")
    # A/B: the synth guide in place of the take, then back, with the B key.
    f.click_t("admin.review.hearSynth")
    f.wait(f"{AUDIO}.engine.parts.has('review:synth:alto') && !{AUDIO}.engine.parts.has('review:take')", what="the synth for B")
    f.run("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'b' }))")
    f.wait(f"{AUDIO}.engine.parts.has('review:take')", what="B again: the take")
    f.click_t("studio.stop")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped")
    # A light fix: the nudge, saved.
    f.run("const range = document.querySelector('input[type=range]'); range.value = 20; range.dispatchEvent(new Event('input')); range.dispatchEvent(new Event('change'));")
    f.click_t("admin.review.saveFixes")
    f.wait_text(t(f, "admin.review.fixesSaved"))
    # A phrase picked on the chart, then another take asked for, pinned to it.
    f.run("const svg = document.querySelector('main svg[role=img]'); const box = svg.getBoundingClientRect(); svg.dispatchEvent(new MouseEvent('click', { clientX: box.left + box.width * 0.3, clientY: box.top + 20, bubbles: true }));")
    f.click_t("admin.review.askRetake")
    f.wait("document.querySelector('[role=dialog] textarea')", what="the retake sheet")
    assert f.js("document.querySelector('[role=dialog] select').value !== ''"), "the picked phrase isn't chosen in the sheet"
    f.type("[role=dialog] textarea", "Lovely tone. Phrase 3 rushed a little")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "admin.review.retakeSent"))
    f.wait_text(t(f, "admin.review.status.retake"))
    f.shot("asked")
    # Decided: accept is no longer offered; A does nothing.
    assert t(f, "admin.review.accept") not in f.js("document.querySelector('main').innerText"), "accept still offered"
    f.run("document.dispatchEvent(new KeyboardEvent('keydown', { key: 'a' }))")
    assert not f.js("document.querySelector('[role=dialog]')"), "A opened a sheet on a decided take"
    f.click_t("admin.review.backToQueue")
    f.wait_text(t(f, "admin.review.noneWaiting"))

    # Singers: invite, the link shown once.
    f.click_t("admin.nav.group.people")
    f.click_t("admin.nav.singers")
    f.wait_text("Sarah Kim")
    f.wait("!document.querySelector('main .btn-primary').disabled", what="the songs for invites")
    f.click_t("admin.singers.invite")
    f.wait("document.querySelector('[role=dialog] input')", what="the invite form")
    f.type("[role=dialog] input", "Sarah Kim")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "admin.singers.linkOnce"))
    assert "beta.stackapella.com/beta/" in f.js("document.querySelector('#singer-link').value"), "no link shown"
    f.shot("link")
    f.run("document.querySelector('[role=dialog] header button').click()")
    f.wait("!document.querySelector('[role=dialog]')", what="the link sheet closed")
    f.click_t("admin.singers.copyMessage")
    f.wait(f"document.body.innerText.includes({I18N}.t('admin.singers.messageCopied')) || document.body.innerText.includes({I18N}.t('admin.copyFailed'))", what="the status message copied")
    f.click_t("admin.singers.forget")
    f.wait_text(t(f, "admin.singers.forgetCannotUndo"))
    assert f.js("document.querySelector('[role=dialog] .btn-danger').disabled"), "forget allowed without typing the name"
    f.run("document.querySelector('[role=dialog] header button').click()")
    # Picking the best singers: each card's numbers, best on pitch first, and one singer's takes.
    f.wait_text(t(f, "admin.singers.stat.accepted"))
    f.shot("singers-stats")
    f.click_t("admin.singers.theirTakes")
    f.wait("location.pathname === '/admin/review' && location.search.includes('singer=')", what="the queue for one singer")
    f.wait(f"document.body.innerText.includes({I18N}.t('admin.review.everyone'))", what="the one-singer filter")
    f.click_t("admin.review.everyone")
    f.wait("!location.search.includes('singer=')", what="everyone again")

    # Real voices: the default switched, and each version's pitch score.
    f.click_t("admin.nav.group.content")
    f.click_t("admin.nav.guides")
    f.wait_text(t(f, "admin.guides.progress", {"done": 2, "total": 4}))
    f.wait_text(t(f, "admin.review.onPitch", {"percent": 91}))
    f.shot("guides")

    # Feedback: marked read.
    f.click_t("admin.nav.group.today")
    f.click_t("admin.nav.feedback")
    f.wait_text("feels low for the alto")
    f.click_t("admin.feedback.markRead")
    f.wait_text(t(f, "admin.feedback.read"))
    f.shot("feedback")
    # Out of the admin, back to the app.
    f.click_t("admin.backToApp")
    f.wait("location.pathname === '/'", what="back to the projects page")


if __name__ == "__main__":
    run("admin_beta", journey)
