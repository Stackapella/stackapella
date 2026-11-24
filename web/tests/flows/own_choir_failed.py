"""Hear yourself as a choir, when a song didn't come out (the coordinator's failure spec, for Hillel: "the main button most
likely won't work the second time; the link should be the main action"). The way on follows what went wrong:
- the take's own problem (quiet, loud, noisy, notes, edge, short): Sing again with its one fix, step 1 a number (not a
  check), "Use my earlier voice instead" only with an older voice, which opens it retitled with its date;
- ours with the voice saved (Hillel's choir error design, 2026-11-24): its own title, "a problem on our side, not with
  your singing", step 1 Your voice is saved (a check), step 2 The choir didn't come out (!) with Try again and Sing again
  instead; Try again shows Trying again (step 2 a spinner), then It worked this time with Listen;
- ours with nothing kept, or the saved voice gone first: Sing again only;
- the second take problem in a row: the next fix (headphones, else another tune) and Contact us;
- ours twice in a row: Back to the song, Try once more the link.
Each failure is said by the page's title and the line under it (no alert box), what it cost only where it didn't count,
and no quota line.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

PAGE = "/t/amazing-grace/choir?api=mock&scenario=&as=member"
FLAGS = ["stackapella.mockFailure", "stackapella.mockFailureDetail", "stackapella.mockNoVoice", "stackapella.mockCounted", "stackapella.mockFailureHints", "stackapella.mockChoir", "stackapella.mockChoirState"]


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def failed(f, failure=None, detail=None, voice=True, run_of=None, counted=False, hints=None):
    """The example's failed Amazing Grace, as `failure` (its detail), with or without the older voice; `run_of` a kind
    whose failure came just before this one on this device."""
    sets = "".join(f"sessionStorage.setItem({json.dumps(k)}, {json.dumps(v)});" for k, v in (("stackapella.mockFailure", failure), ("stackapella.mockFailureDetail", detail), ("stackapella.mockNoVoice", None if voice else "1"), ("stackapella.mockCounted", "1" if counted else None), ("stackapella.mockFailureHints", json.dumps(hints) if hints is not None else None)) if v)
    clears = "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS)
    run = f"localStorage.setItem('stackapella.choirFailRun', JSON.stringify({{ kind: {json.dumps(run_of)}, ids: ['RqBefore'] }}));" if run_of else "localStorage.removeItem('stackapella.choirFailRun');"
    f.run(f"{clears} sessionStorage.setItem('stackapella.mockOwnChoir', '1'); {sets} {run}")
    f.go(PAGE, "document.querySelector('[data-choir-failed]') && document.querySelector('[data-choir-steps]')")


def alert(f):
    return f.js("document.querySelector('[data-choir-failed]').innerText")


def page_title(f):
    return f.js("document.querySelector('#app h1').textContent.trim()")


def step(f):
    return f.js("document.querySelector('[data-choir-step-state]').dataset.choirStepState")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    clear_projects(f)

    # A take problem (edge): its title and one fix, no box; Sing again the one main action, step 1 a number; the older
    # voice a quiet link; what it cost; no quota line, and never "deleted" beside "saved".
    failed(f, detail="edge")
    assert f.js("document.querySelector('[data-choir-failed]').dataset.choirFailKind") == "take"
    assert not f.js("document.querySelector('[data-choir-failed]').closest('.card, .alert-inline')"), "the failure is in a box"
    assert page_title(f) == t(f, "ownChoir.failed.title.take"), page_title(f)
    words = alert(f)
    assert t(f, "ownChoir.failed.detail.edge") in words, words
    assert f.js("!!document.querySelector('[data-choir-fail-cost]')"), "what it cost isn't said"
    assert step(f) == "sing", f"step 1 is {step(f)}"
    assert f.js("!document.querySelector('[data-choir-step=\"1\"] [data-ui=step-dot] svg')"), "step 1 is a check after a failed take"
    assert f.js("document.querySelector('[data-choir-start]').textContent.trim()") == t(f, "ownChoir.record.again")
    assert f.js("!!document.querySelector('[data-choir-use-earlier]')"), "no way to the older voice"
    assert not f.js("document.querySelector('[data-choir-quota]')"), "the quota line on a failure"
    assert "deleted" not in f.js("document.querySelector('#app').innerText"), "deleted said on the failure"
    f.no_sideways("a take problem")
    f.shot("take-edge")
    # The older voice: retitled with its date; Make my choir from it, Sing again to go back.
    f.run("document.querySelector('[data-choir-use-earlier]').click()")
    f.wait("document.querySelector('[data-choir-again]')", what="the earlier voice")
    assert step(f) == "saved"
    title = f.js("document.querySelector('[data-choir-step=\"1\"] h2').textContent.trim()")
    assert title == t(f, "ownChoir.failed.earlier.title"), title
    date = f.js("(async () => { const { formatDate } = await import('/js/i18n.js'); const { ownChoir } = await import('/js/own-choir.js'); return formatDate(ownChoir.data.voice.savedAt, { day: 'numeric', month: 'long' }); })()")
    assert f.js("document.querySelector('[data-choir-step=\"1\"] [data-step-body]').textContent.trim()") == t(f, "ownChoir.failed.earlier.body", {"date": date})
    f.shot("take-earlier")
    f.run("document.querySelector('[data-choir-resing]').click()")
    f.wait("document.querySelector('[data-choir-start]') && document.querySelector('[data-choir-use-earlier]')", what="back to singing")
    # Sing again: the fix goes with the next take, over its count-in, and only then.
    f.run("document.querySelector('[data-choir-start]').click()")
    f.wait("document.querySelector('[data-first-note-usual]')", what="the first-note step")
    f.run("document.querySelector('[data-first-note-usual]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname) && document.querySelector('[data-settings-open]')", what="Sing on the take's project")
    assert f.js("sessionStorage.getItem('stackapella.choirFix')") == "ownChoir.failed.detail.edge"
    f.run("const audio = await import('/js/audio.js'); await audio.unlock(); audio.engine.setLoopback(true); (await import('/js/stores/session.js')).startRecording()")
    f.wait("document.querySelector('[data-take-tip]') && document.querySelector('[data-count-big] span')", what="the fix over the count-in")
    assert f.js("document.querySelector('[data-take-tip]').textContent.trim()") == t(f, "ownChoir.failed.detail.edge")
    f.shot("take-tip")
    f.wait("!document.querySelector('[data-take-tip]')", timeout=30, what="the fix gone once the singing starts")
    f.run("await (await import('/js/stores/session.js')).finishRecording({ interrupted: true })")

    # A failure that counted (the server's `counted`): nothing said about what it cost.
    failed(f, detail="notes", counted=True)
    assert not f.js("document.querySelector('[data-choir-fail-cost]')"), "it didn't count, said of one that did"

    # Noise, with the server's hints (failureHints): close to passing, a steady hum like a fan; sung quietly beside the
    # room, sing louder or closer first (both at once: the easier fix leads); no hint, the quieter room as before.
    for hints, words in ((["near"], "ownChoir.failed.detail.noisyNear"), (["near", "quietSinger"], "ownChoir.failed.detail.noisyQuiet"), ([], "ownChoir.failed.detail.noisy")):
        failed(f, detail="noisy", hints=hints)
        fix = f.js("document.querySelector('[data-choir-fail-fix]').textContent.trim()")
        assert fix == t(f, words), f"{hints}: {fix}"
        if hints == ["near"]:
            f.shot("noisy-hum")

    # No older voice: Sing again alone.
    failed(f, detail="quiet", voice=False)
    assert f.js("!document.querySelector('[data-choir-use-earlier]') && !document.querySelector('[data-choir-use-saved]')"), "a link to a voice there isn't"
    assert t(f, "ownChoir.failed.detail.quiet") in alert(f)

    # The second take problem in a row: the next fix (another tune, with headphones on), and Contact us.
    failed(f, detail="edge", run_of="take")
    assert f.js("document.querySelector('[data-choir-failed]').hasAttribute('data-choir-fail-repeat')"), "not seen as the second"
    nexts = [t(f, "ownChoir.failed.next.tune"), t(f, "ownChoir.failed.next.headphones")]
    assert any(n in alert(f) for n in nexts), alert(f)
    assert f.js("document.querySelector('[data-choir-contact]').getAttribute('href').startsWith('/contact?from=')"), "no Contact us"
    assert not f.js("document.querySelector('[data-choir-use-earlier]')"), "the older voice offered the second time"
    f.shot("take-twice")

    # Ours, the voice saved (Hillel's choir error design): its title and why; step 1 saved (a check), step 2 didn't come
    # out (!) with Try again, Sing again instead and the help link; what it cost.
    failed(f, failure="render_failed")
    assert f.js("document.querySelector('[data-choir-failed]').dataset.choirFailKind") == "oursKept"
    assert page_title(f) == t(f, "ownChoir.failed.title.ours"), page_title(f)
    assert t(f, "ownChoir.failed.why") + " " in alert(f) and (t(f, "ownChoir.failed.cost.free") in alert(f) or t(f, "ownChoir.failed.cost.minutes") in alert(f)), alert(f)
    assert step(f) == "saved"
    assert f.js("!!document.querySelector('[data-choir-step=\"1\"] [data-ui=step-dot] svg')"), "step 1 isn't a check"
    assert f.js("document.querySelector('[data-choir-step=\"2\"]').dataset.choirStepState") == "failed"
    assert f.js("document.querySelector('[data-choir-step=\"2\"] [data-step-body]').textContent.trim()") == t(f, "ownChoir.failed.step.body")
    assert f.js("document.querySelector('[data-choir-again]').textContent.trim()") == t(f, "common.tryAgain")
    assert f.js("document.querySelector('[data-choir-resing]').textContent.trim()") == t(f, "ownChoir.voice.record")
    assert f.js("!!document.querySelector('[data-choir-step=\"2\"] [data-choir-help-failed]')"), "no help link under the actions"
    f.no_sideways("ours with the voice saved")
    f.shot("ours-kept")
    # Try again: Trying again while it's made (step 2 a spinner), then It worked this time with Listen, then the song.
    f.run("sessionStorage.setItem('stackapella.mockChoirMaking', '1'); document.querySelector('[data-choir-again]').click()")
    f.wait("document.querySelector('[data-choir-step=\"2\"]')?.dataset.choirStepState === 'building'", what="Trying again")
    assert page_title(f) == t(f, "ownChoir.failed.retrying.title"), page_title(f)
    assert f.js("document.querySelector('[data-choir-retry]').textContent.trim()") == t(f, "ownChoir.failed.retrying.body")
    assert f.js("!document.querySelector('[data-choir-failed]')"), "still said as failed while trying again"
    f.shot("ours-retrying")
    f.run("sessionStorage.removeItem('stackapella.mockChoirMaking')")
    f.wait("document.querySelector('[data-choir-step=\"2\"]')?.dataset.choirStepState === 'done'", timeout=30, what="It worked this time")
    assert page_title(f) == t(f, "ownChoir.title"), page_title(f)
    assert f.js("document.querySelector('[data-choir-retry]').textContent.trim()") == t(f, "ownChoir.failed.worked.body")
    f.shot("ours-worked")
    f.run("document.querySelector('[data-choir-listen]').click()")
    f.wait("document.querySelector('[data-choir-play]')", what="the song, after Listen")

    # Ours twice in a row: back to the song, once more the link.
    failed(f, failure="render_failed", run_of="ours")
    assert f.js("!!document.querySelector('[data-choir-back]') && !!document.querySelector('[data-choir-again]')"), "not Back to the song with Try once more"
    assert f.js("document.querySelector('[data-choir-again]').textContent.trim()") == t(f, "ownChoir.failed.onceMore")
    f.shot("ours-twice")
    f.run("document.querySelector('[data-choir-back]').click()")
    f.wait("location.pathname === '/'", what="back to the song")  # Home opens the song's sheet from ?song= and tidies the address

    # Ours with nothing kept (the clip didn't arrive), and the saved voice gone first: Sing again only.
    for failure, kind in (("bad_clip", "oursLost"), ("reference_gone", "gone")):
        failed(f, failure=failure, voice=failure != "reference_gone")
        assert f.js("document.querySelector('[data-choir-failed]').dataset.choirFailKind") == kind
        assert step(f) == "sing", f"{failure}: step 1 is {step(f)}"
        assert f.js("!document.querySelector('[data-choir-use-earlier]') && !document.querySelector('[data-choir-use-saved]') && !document.querySelector('[data-choir-contact]')"), f"{failure}: a second way"
    f.shot("gone")
    f.run("localStorage.removeItem('stackapella.choirFailRun'); sessionStorage.removeItem('stackapella.choirFix');" + "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS))


if __name__ == "__main__":
    run("own_choir_failed", journey)
