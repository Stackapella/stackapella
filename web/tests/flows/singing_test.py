"""The singing test (docs/singing-test-v1.md), on Your voice, every answer as on speakers (the prompt's tail heard first):
- hidden in the first week (no card; its address goes back to Your voice);
- an account: the start screen's early-version line, the warm-up (skipped), the note found (a held A3 by loopback),
  the three parts (a synthetic voice standing in for the turns, with the mock API only, as the engine's unit tests do),
  a note map after each part, then the result: band, total, what went well, one thing to work on, the early line;
- the first test marked a warm-up, and counted again;
- a second device: the history comes back from the account;
- a noisy room: nothing heard, three retries, kept as practice ("somewhere quieter");
- a guest: kept on the device only.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import AUDIO  # noqa: E402

FLAGS = ["stackapella.mockSingingTest", "stackapella.mockSingingTests"]

# The stand-in turn: an answer `stCents` cents off (or nothing at all, `stQuiet`), as js/singing-test/play.js's `turn`
# answers: `{ track, promptEnd }`.
STAND_IN = """globalThis.stSingingTurn = async (notes, { answerSeconds, onAnswer }) => {
    const { engine } = await import('/js/audio.js');
    onAnswer(engine.ctx?.currentTime ?? 0);
    const rate = 100, hz = [], clarity = [], loudness = [];
    const push = (seconds, midi, off = globalThis.stCents ?? 12) => { for (let i = 0; i < Math.round(seconds * rate); i++) { const on = midi !== null && !globalThis.stQuiet;
        hz.push(on ? 440 * 2 ** ((midi - 69) / 12 + off / 1200 + (Math.sin(i * 1.7) * 2) / 1200) : 0); clarity.push(on ? 0.95 : 0); loudness.push(on ? -20 : -80); } };
    // As on speakers: the prompt's last note still sounding as the answer window opens (from 0.3 s), fading, then a breath.
    const last = notes.at(-1);
    for (let i = 0; i < 55; i++) { hz.push(440 * 2 ** ((last - 69) / 12)); clarity.push(0.9); loudness.push(-22 - (18 * i) / 55); }
    push(0.15, null);
    // A pattern's notes alternately sharp and flat, so its own key can't cancel the error away.
    if (notes.length === 4) notes.forEach((m, i) => { push(0.45, m, (i % 2 ? -1 : 1) * (globalThis.stCents ?? 12)); push(0.12, null); });
    else push(Math.max(0.5, answerSeconds - 0.6), notes[0]);
    return { track: { rate, hz, clarity, loudness }, promptEnd: 0.3 };
};"""


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def started(f, days_ago):
    """Course progress begun `days_ago` days ago (the first week hides the test)."""
    f.run(f"""const {{ emptyProgress }} = await import('/js/course.js'); const db = await import('/js/db.js');
        await db.put('learning', {{ ...emptyProgress(), startedAt: Date.now() - {days_ago} * 86400000 }}, 'progress');""")


def take(f, cents=12, quiet=False, shots=False):
    """One whole test from Your voice's card to the result."""
    f.go("/voice", "document.querySelector('[data-st-card]')")
    f.run("document.querySelector('[data-st-take]').click()")
    f.wait("document.querySelector('[data-st-start]')", what="the start")
    assert f.js("document.querySelector('[data-st-start] [data-st-early]').textContent.trim()") == t(f, "singingTest.early"), "the early-version line"
    if shots:
        f.no_sideways("the start")
        f.shot("start")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.run(STAND_IN + f" globalThis.stCents = {cents}; globalThis.stQuiet = {json.dumps(quiet)};")
    f.run("document.querySelector('[data-st-begin]').click()")
    f.wait("document.querySelector('[data-st-warmup]')", what="the warm-up")
    if shots:
        f.shot("warmup")
    f.run("document.querySelector('[data-st-skip]').click()")
    f.wait("['listening', 'hearing', 'holding'].includes(document.querySelector('[data-listening]')?.dataset.phase)", what="listening for their note")
    f.js("(await import('/js/creator-audio.js')).hearNotes([{ beat: 0, length: 6, pitch: 57 }], { tempo: 60 })")
    for event in ("match", "back", "hold"):
        f.wait(f"document.querySelector('[data-st-intro][data-event={event}]')", timeout=30, what=f"{event}'s start")
        if shots and event == "match":
            f.shot("intro")
        f.run("document.querySelector('[data-st-event-start]').click()")
        if shots and event == "match":
            f.wait("document.querySelector('[data-st-items]')", what="the items")
            f.shot("item")
        f.wait(f"document.querySelector('[data-st-map][data-event={event}]')", timeout=90, what=f"{event}'s note map")
        if shots:
            f.shot(f"map-{event}")
        f.run("document.querySelector('[data-st-next]').click()")
    f.wait("document.querySelector('[data-st-result]')", timeout=30, what="the result")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.mic_checked()
    f.run("localStorage.removeItem('stackapella.singingTests'); sessionStorage.setItem('stackapella.mockSingingTest', '1'); sessionStorage.removeItem('stackapella.mockSingingTests');")

    # The first week: no card, and the address goes back to Your voice.
    started(f, 2)
    f.go("/voice", "document.querySelector('[data-voice-range]')")
    assert not f.js("document.querySelector('[data-st-card]')"), "the test shown in the first week"
    f.go("/voice/test", "location.pathname === '/voice'")

    # An account: a whole test, scored, with what went well and one thing to work on.
    started(f, 10)
    take(f, cents=12, shots=True)
    assert f.js("document.querySelector('[data-st-result]').dataset.status") == "counted", "not counted"
    band = f.js("document.querySelector('[data-st-result]').dataset.band")
    assert band in ("precise", "inTune"), band
    assert f.js("!!document.querySelector('[data-st-total]') && !!document.querySelector('[data-st-well]')"), "the total and what went well"
    assert f.js("document.querySelector('[data-st-result] [data-st-early]').textContent.trim()") == t(f, "singingTest.early"), "the result's early line"
    f.wait("JSON.parse(localStorage.getItem('stackapella.singingTests') || '[]')[0]?.synced === true", what="sent to the account")
    f.no_sideways("the result")
    f.shot("result")

    # The first test can be marked a warm-up, and counted again.
    f.run("document.querySelector('[data-st-warmup-mark]').click()")
    f.wait("document.querySelector('[data-st-result]').dataset.status === 'warmup'", what="marked a warm-up")
    f.run("document.querySelector('[data-st-warmup-mark]').click()")
    f.wait("document.querySelector('[data-st-result]').dataset.status === 'counted'", what="counted again")

    # A second device: nothing kept here, the history comes from the account.
    f.run("localStorage.removeItem('stackapella.singingTests')")
    f.go("/voice", "document.querySelector('[data-st-card]')")
    f.wait("document.querySelector('[data-st-latest]')", what="the latest result, from the account")
    f.shot("card")
    # Game mode's boards on the card (the part boards' look), from the contract's example; calm mode has none.
    if f.skin == "game":
        f.run("sessionStorage.setItem('stackapella.mockSingingTestBoards', '1')")
        f.go("/voice", "document.querySelector('[data-st-card]')")
        f.wait("document.querySelectorAll('[data-st-board] [data-board-entry]').length > 0", what="the boards")
        f.shot("board")
        f.run("sessionStorage.removeItem('stackapella.mockSingingTestBoards')")
    else:
        assert not f.js("document.querySelector('[data-st-board]')"), "boards in calm mode"

    # A noisy room: nothing heard, offered again three times, kept as practice.
    take(f, quiet=True)
    assert f.js("document.querySelector('[data-st-result]').dataset.status") == "practice", "not practice"
    assert f.js("!!document.querySelector('[data-st-practice]') && !document.querySelector('[data-st-total]')"), "practice says so, with no score"
    f.shot("practice")

    # A mid score: Nearly in tune, the breakdown, one thing to work on with its lesson, and about the same as last time.
    take(f, cents=45)
    assert f.js("document.querySelector('[data-st-result]').dataset.band") == "nearly", f.js("document.querySelector('[data-st-result]').dataset.band")
    assert f.js("document.querySelectorAll('[data-st-breakdown] [data-st-row]').length") == 3, "the breakdown's three parts"
    assert f.js("!!document.querySelector('[data-st-work] [data-st-lesson]')"), "one thing to work on, with its lesson"
    assert f.js("document.querySelector('[data-st-progress]').textContent.trim()") == t(f, "singingTest.result.same"), "about the same as last time"
    f.no_sideways("a mid result")
    f.shot("result-mid")

    # How it's scored.
    f.go("/voice/test/how", "document.querySelectorAll('[data-how-event]').length === 3")
    f.no_sideways("How it's scored")
    f.shot("how")

    # Admin > Safety > Singing tests: the held tests with why; Looks fine takes one off the list.
    f.go("/admin/safety?tab=singingTests&api=mock&scenario=&as=admin", "document.querySelector('[data-singing-test-holds]')")
    f.wait("document.querySelector('[data-held-test]')", what="a held test")
    f.run("document.querySelector('[data-held-review]').click()")
    f.shot("admin-holds")

    # A guest: kept on this device only.
    f.run("localStorage.removeItem('stackapella.singingTests')")
    f.go("/?api=mock&scenario=&as=signedOut", "document.querySelector('h1')")
    started(f, 10)
    take(f, cents=40)
    assert f.js("JSON.parse(localStorage.getItem('stackapella.singingTests') || '[]').every((x) => !x.synced)"), "a guest's test sent"
    f.run("localStorage.removeItem('stackapella.singingTests');" + "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS))
    f.js(f"{AUDIO}.engine.setLoopback(false)")


if __name__ == "__main__":
    run("singing_test", journey)
