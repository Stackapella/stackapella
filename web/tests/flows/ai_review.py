"""The AI review queue (docs/ai-voices-build-plan.md, phase 1c; js/admin/ai-review.js): new AI guides by song, each part
heard as the AI voice, its written part or both (on the audio clock), its checks beside it with a flag on a weak one,
approved one by one or the whole song, rejected with a note in a styled sheet; the status tabs count each, and the
choices are remembered. On the mock: Silent Night's four pending parts, the tenor weak.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

AUDIO = "(await import('/js/audio.js'))"


def journey(f):
    f.go("/admin/ai-review?api=mock&scenario=&as=admin", "document.querySelector('[data-ai-review]')")
    f.run("localStorage.removeItem('stackapella.admin.aiReview.status'); localStorage.removeItem('stackapella.admin.aiReview.hear');")
    f.go("/admin/ai-review?api=mock&scenario=&as=admin", "document.querySelector('[data-ai-review-song=silent-night]')")
    parts = f.js("document.querySelectorAll('[data-ai-review-song=silent-night] [data-ai-review-part]').length")
    assert parts == 4, f"four parts to review: {parts}"
    flagged = f.js("[...document.querySelectorAll('[data-ai-review-flag]')].map((c) => c.closest('[data-ai-review-part]').dataset.aiReviewPart)")
    assert flagged == ["tenor"], f"only the weak tenor is flagged: {flagged}"
    assert "34" in f.js("document.querySelector('[data-ai-review-part=tenor] [data-ai-review-checks]').textContent"), "its worst note"
    f.no_sideways("the AI review")
    f.shot("queue")

    # Hear the alto with its written part: both play on the engine, and stop by themselves at the end.
    f.run(f"{AUDIO}.unlock()")
    f.run("document.querySelector('[data-hear=both]').click(); document.querySelector('[data-ai-review-part=alto] [data-ai-review-play]').click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('ai-review:ai') && {AUDIO}.engine.parts.has('ai-review:written')", timeout=30, what="the alto and its written part playing")
    f.run("document.querySelector('[data-hear=ai]').click()")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('ai-review:ai') && !{AUDIO}.engine.parts.has('ai-review:written')", timeout=30, what="the AI voice alone, carrying on")
    f.run("document.querySelector('[data-ai-review-part=alto] [data-ai-review-play]').click()")
    f.wait(f"!{AUDIO}.engine.playing", what="stopped")

    # Reject the tenor: a note first, in a styled sheet that names it.
    f.run("document.querySelector('[data-ai-review-part=tenor] [data-ai-review-reject]').click()")
    f.wait("document.querySelector('[data-ai-review-reject-body]')", what="the reject sheet")
    form = "document.querySelector('[data-ai-review-reject-body]').closest('form')"
    assert f.js(f"{form}.querySelector('button[type=submit]').disabled"), "a note first"
    assert f.js(f"{form}.querySelector('button[type=submit]').textContent.trim()") == "Reject the Tenor"
    f.type("[role=dialog] textarea", "Bar 9's high note is flat")
    f.shot("reject")
    f.run(f"{form}.requestSubmit()")
    f.wait("document.querySelector('[data-ai-review-song=silent-night]') && !document.querySelector('[data-ai-review-part=tenor]')", what="the tenor gone from the queue")

    # Approve the rest of the song at once.
    f.run("document.querySelector('[data-ai-review-approve-song]').click()")
    f.wait("document.querySelector('[data-ai-review]') === null && document.querySelector('[data-status=pending] [data-status-count]')?.textContent.trim() === '0'", what="the song approved, nothing left to review")
    f.run("document.querySelector('[data-status=rejected]').click()")
    f.wait("document.querySelector('[data-ai-review-part=tenor] [data-ai-review-note]')", what="the tenor among the rejected, with its note")
    assert "Bar 9" in f.js("document.querySelector('[data-ai-review-note]').textContent")
    f.go("/admin/ai-review?api=mock&scenario=&as=admin", "document.querySelector('[data-status=rejected][aria-checked=true]')")


if __name__ == "__main__":
    run("ai_review", journey)
