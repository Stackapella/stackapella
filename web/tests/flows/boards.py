"""Part leaderboards (components/boards.js, views/challenge.js), the minimum, against the mock: a
counted take shows your best and your place among friends, and an adult is asked once about the
worldwide board; a take that doesn't count says why; under 18 is never asked; the song page's board
for a part, Friends and Everyone, with an empty state for each board (what it is, how to get on it) and "Make an
account to compete" for a guest; a challenge link opens the challenge and drops you into the part;
Settings has the worldwide switch. Screenshots for Hillel go to NEW_UI when it's set.
"""
import json
import os
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, SIZES, run  # noqa: E402
from help_shots import t  # noqa: E402
from game_frames import every_theme  # noqa: E402

NEW_UI = os.environ.get("NEW_UI")

MOUNT = """const { createApp, h } = await import('vue');
    const { t } = await import('/js/i18n.js');
    const { TakeBest } = await import('/js/components/boards.js');
    document.querySelector('#best-test')?.remove();
    const box = Object.assign(document.createElement('div'), { id: 'best-test', className: 'card m-6 p-6' });
    document.querySelector('#app').prepend(box);
    const app = createApp({ render: () => h(TakeBest, { templateId: 'amazing-grace', templateVersion: 3, partKey: 'alto', partName: 'Alto', songTitle: 'Amazing Grace', score: 87, guideMuted: true }) });
    app.config.globalProperties.t = t;
    app.mount(box);"""

ANSWER = """const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
    window.answers = %s;
    api.useTransport(async (url, init = {}) => window.answers[init.operation]
        ? new Response(JSON.stringify(window.answers[init.operation]), { status: 200, headers: { 'Content-Type': 'application/json' } })
        : mockFetch(url, init));"""


def keep(f, label, name):
    """The last shot, kept for Hillel under `name`."""
    f.shot(label)
    if NEW_UI:
        Path(NEW_UI).mkdir(parents=True, exist_ok=True)
        shot = sorted(SHOTS.glob(f"{f.name}-{f.size}-*-{label}.png"))[-1]
        shutil.copy(shot, Path(NEW_UI) / f"{name}-{f.size}.png")


def as_adult(f, adult=True, on_global=False):
    f.run(f"const {{ account }} = await import('/js/stores/account.js'); await (await import('/js/stores/account.js')).loadAccount(); account.me.isAdult = {json.dumps(adult)}; account.me.boardsGlobal = {json.dumps(on_global)}; localStorage.removeItem('stackapella.boards.asked');")


def journey(f):
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    # A counted take: your best, your place among friends, and (an adult) the worldwide question once.
    as_adult(f)
    f.run("window.mockCalls = [];")
    f.run(MOUNT)
    f.wait_text(t(f, "boards.best", {"score": 87}))
    f.wait_text(t(f, "boards.rankFriends", {"rank": 2}))
    f.wait("document.querySelector('[data-boards-optin]')", what="the worldwide question")
    assert f.js("document.activeElement?.textContent.trim()") == t(f, "boards.optIn.notNow"), "Not now is the safe, focused answer"
    keep(f, "optin", "boards-optin")
    f.click_t("boards.optIn.yes")
    f.wait("window.mockCalls.includes('boards.global.update') && !document.querySelector('[data-boards-optin]')", what="the worldwide board turned on")
    f.wait(f"!document.body.innerText.includes({json.dumps(t(f, 'boards.optIn.done'))})", timeout=15, what="the toast gone, for the picture")
    keep(f, "best", "boards-take-best")
    # Asked once: another counted take doesn't ask again.
    f.run(MOUNT)
    f.wait_text(t(f, "boards.best", {"score": 87}))
    assert not f.js("!!document.querySelector('[data-boards-optin]')"), "asked only once"

    # A take that doesn't count says why, with no rank.
    f.run(ANSWER % json.dumps({"boards.scores.submit": {"data": {"counted": False, "why": "key", "best": 87, "improved": False}}}))
    f.run(MOUNT)
    f.wait_text(t(f, "boards.why.key"))
    assert not f.js(f"document.querySelector('#best-test').innerText.includes({json.dumps(t(f, 'boards.rankFriends', {'rank': 2}))})"), "no rank for a take that didn't count"
    keep(f, "not-counted", "boards-take-not-counted")

    # Under 18: never asked about the worldwide board.
    f.run(ANSWER % "{}")
    as_adult(f, adult=False)
    f.run(MOUNT)
    f.wait_text(t(f, "boards.rankFriends", {"rank": 2}))
    assert not f.js("!!document.querySelector('[data-boards-optin]')"), "under 18 isn't asked"

    # The song page: a part's board, Friends then Everyone; empty, it invites you to be first.
    # (Serious mode has no boards: docs/skins-plan.md row 14.)
    if f.skin != "game":
        f.go("/t/amazing-grace?api=mock&scenario=&as=member", "document.querySelector('main h1, h1')")
        f.js("new Promise((r) => setTimeout(r, 800))")
        assert not f.js("!!document.querySelector('[data-part-board]')"), "a board in serious mode"
        return
    f.go("/t/amazing-grace?api=mock&scenario=&as=member", "document.querySelector('[data-part-board] button')")
    f.run("document.querySelector('[data-part-board] button').click()")
    f.wait("document.querySelectorAll('[data-part-board] [data-board-entry]').length === 2", what="the friends' board")
    # The button pressed is replaced by the card's own: focus moves to it, never lost to the page.
    f.wait("document.activeElement?.getAttribute('aria-expanded') === 'true' && !!document.activeElement.closest('[data-part-board]')", what="focus on the open board's button")
    # One board on the page: the picked part's (docs/leaderboard-redesign-plan.md), no link under every part.
    assert f.js("document.querySelectorAll('[data-part-board]').length") == 1, "a board under every part"
    f.run("document.querySelector('[data-part-board]').scrollIntoView({ block: 'center' })")
    # The switch stays one row, even at a phone's width in the arcade type.
    tops = f.js("[...document.querySelectorAll('[data-part-board] [data-board-scope]')].map((b) => Math.round(b.getBoundingClientRect().top))")
    assert len(set(tops)) == 1, f"the board's switch stacked: {tops}"
    # Every short name shows whole, even on a 360 px phone: the YOU tag wraps under it rather than cutting it.
    narrow = SIZES[f.size][2]
    if narrow:
        f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": 360, "height": 780, "deviceScaleFactor": 2, "mobile": True})
        f.js("new Promise((r) => setTimeout(r, 300))")
    cut = f.js("[...document.querySelectorAll('[data-part-board] [data-board-entry] [translate=no]')].filter((n) => n.scrollWidth > n.clientWidth + 1).map((n) => n.textContent)")
    assert not cut, f"names cut short on the board: {cut}"
    if narrow:
        w, h, _ = SIZES[f.size]
        f.chrome.send("Emulation.setDeviceMetricsOverride", {"width": w, "height": h, "deviceScaleFactor": 2, "mobile": True})
        f.js("new Promise((r) => setTimeout(r, 300))")
    keep(f, "board", "boards-song-board")
    every_theme(f, "the part's board")  # the arcade pass, at AA in all eight themes
    f.click_t("boards.everyone")
    f.wait("document.querySelectorAll('[data-part-board] [data-board-entry]').length === 2", what="the worldwide board")
    # Report this name: on someone else's entry only, a reason, then thanks.
    assert f.js("document.querySelectorAll('[data-report-entry]').length") == 1, "Report on every name but yours"
    f.run(ANSWER % json.dumps({"reports.create": {}}))
    f.run("document.querySelector('[data-report-entry]').click()")
    f.wait("document.querySelector('[data-board-report]')", what="the report sheet")
    f.run("document.querySelector('[data-board-report] input[value=personal]').click()")
    keep(f, "report", "boards-report")
    f.run("document.querySelector('[data-board-report] button[type=submit]').click()")
    f.wait_text(t(f, "boards.report.thanks"))
    f.wait("!document.querySelector('[data-board-report]')", what="the sheet closed")
    # An earlier arrangement's board, read only, and back (the example lists version 2).
    f.click_t("boards.versions.version", {"version": 2})
    f.wait("document.querySelector('[data-board-version]')", what="version 2's board, read only")
    f.wait_text(t(f, "boards.versions.readOnly", {"version": 2}))
    f.click_t("boards.versions.back")
    f.wait("document.querySelector('[data-board-versions]')", what="back to the current board")
    # Further down than tenth: a gap row, then your own row marked You.
    below = {"templateId": "amazing-grace", "templateVersion": 3, "partKey": "alto", "scope": "global", "scoringVersion": 1, "onGlobal": True,
             "entries": [{"id": "e1", "rank": 1, "name": "Ana", "initials": None, "score": 95, "at": "2026-10-30T10:00:00Z", "you": False}],
             "you": {"score": 71, "rank": 14, "initials": None}}
    f.run(ANSWER % json.dumps({"boards.show": {"data": below}}))
    f.click_t("boards.friends")
    f.click_t("boards.everyone")
    f.wait("document.querySelector('[data-part-board] [data-ui=board-gap]') && document.querySelector('[data-part-board] [data-you]')", what="your row after the gap")
    assert f.js("document.querySelector('[data-part-board] [data-you] [data-ui=board-score]').textContent.trim()") == "71"
    keep(f, "below", "boards-song-below")
    every_theme(f, "the board with the gap")
    f.run(ANSWER % json.dumps({"boards.show": {"data": {"templateId": "amazing-grace", "templateVersion": 3, "partKey": "alto", "scope": "friends", "scoringVersion": 1, "entries": [], "you": {"score": 87, "rank": None, "initials": None}, "onGlobal": False}}}))
    f.click_t("boards.friends")
    # Empty: each board says what it is and how to get on it (no friends yet is not "be the first").
    f.wait("document.querySelector('[data-board-empty][data-scope=friends]')", what="the friends' empty state")
    f.wait_text(t(f, "boards.emptyFriends.title"))
    # With a best of yours on this part: Challenge a friend, right there.
    f.wait("document.querySelector('[data-board-empty] [data-ui=board-challenge]')", what="Challenge a friend in the empty board")
    keep(f, "empty", "boards-song-empty")
    every_theme(f, "the empty friends' board")
    f.click_t("boards.everyone")
    f.wait("document.querySelector('[data-board-empty][data-scope=global]')", what="everyone's empty state")
    f.wait_text(t(f, "boards.empty"))
    f.run(ANSWER % "{}")

    # A song the boards don't keep (templates.show's boards false): no board link at all.
    f.run("""const api = await import('/js/api.js'); const { mockFetch } = await import('/js/api-mock.js');
        api.useTransport(async (url, init = {}) => { const answer = await mockFetch(url, init); if (init.operation !== 'templates.show') return answer;
            const json = await answer.json(); json.data.grant = 'link'; json.data.boards = false; return new Response(JSON.stringify(json), { status: 200, headers: { 'Content-Type': 'application/json' } }); });
        const { navigate } = await import('/js/router.js'); navigate('/'); await new Promise((r) => setTimeout(r, 300)); navigate('/t/amazing-grace');""")
    f.wait("document.querySelector('main h1, h1') && location.pathname === '/t/amazing-grace'", what="the song by its link")
    f.js("new Promise((r) => setTimeout(r, 800))")
    assert not f.js("!!document.querySelector('[data-part-board]')"), "no board link on a song the boards don't keep"
    f.run(ANSWER % "{}")

    # A guest: Make an account to compete, on the song page only.
    f.go("/t/amazing-grace?api=mock&scenario=&as=signedOut", "document.querySelector('[data-part-board] button')")
    f.run("document.querySelector('[data-part-board] button').click()")
    # A guest: the account wall, its reason and a way in.
    f.wait("document.querySelector('[data-part-board] [data-account-wall][data-reason=boards]')", what="the boards' account wall")
    f.wait_text(t(f, "wall.boards"))

    # A challenge link: who and what, then Sing the part, accepted and into the song.
    f.go("/challenge/demo-token?api=mock&scenario=&as=member", "document.querySelector('[data-challenge] h1')")
    f.wait_text(t(f, "boards.challenge.from", {"name": "Ana Ruiz", "score": 91, "part": "Alto", "song": "Amazing Grace"}))
    keep(f, "challenge", "boards-challenge")
    f.run("window.mockCalls = [];")
    # Accept (game mode) or Sing the part (serious): the same button, [data-challenge-accept].
    sing_button = "document.querySelector('[data-challenge-accept]')"
    f.wait(sing_button, what="Accept, or Sing the part")
    f.run(f"{sing_button}.click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing/.test(location.pathname)", timeout=20, what="the song, on that part")
    assert f.js("window.mockCalls.includes('boards.challenges.accept')"), "a signed-in singer accepts the challenge"

    # Settings: the worldwide switch, for an adult.
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('#profile')")
    as_adult(f)
    f.wait("document.querySelector('[data-boards-global]')", what="the worldwide switch")
    f.run("window.mockCalls = []; document.querySelector('[data-boards-global] button, [data-boards-global] [role=switch]').click();")
    f.wait("window.mockCalls.includes('boards.global.update')", what="the switch saved")


if __name__ == "__main__":
    run("boards", journey, sizes=("desktop", "phone"))
