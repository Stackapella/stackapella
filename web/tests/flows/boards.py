"""Part leaderboards (components/boards.js, views/challenge.js), the minimum, against the mock: a
counted take shows your best and your place among friends, and an adult is asked once about the
worldwide board; a take that doesn't count says why; under 18 is never asked; the song page's board
for a part, Friends and Everyone, with "Be the first on this part" when it's empty and "Make an
account to compete" for a guest; a challenge link opens the challenge and drops you into the part;
Settings has the worldwide switch. Screenshots for Hillel go to NEW_UI when it's set.
"""
import json
import os
import shutil
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, run  # noqa: E402
from help_shots import t  # noqa: E402

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
    f.wait("document.querySelectorAll('[data-part-board] ol li').length === 2", what="the friends' board")
    f.run("document.querySelector('[data-part-board]').scrollIntoView({ block: 'center' })")
    keep(f, "board", "boards-song-board")
    f.click_t("boards.everyone")
    f.wait("document.querySelectorAll('[data-part-board] ol li').length === 2", what="the worldwide board")
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
    f.run(ANSWER % json.dumps({"boards.show": {"data": {"templateId": "amazing-grace", "templateVersion": 3, "partKey": "alto", "scope": "friends", "scoringVersion": 1, "entries": [], "you": None, "onGlobal": False}}}))
    f.click_t("boards.friends")
    f.wait_text(t(f, "boards.empty"))
    keep(f, "empty", "boards-song-empty")
    f.run(ANSWER % "{}")

    # A guest: Make an account to compete, on the song page only.
    f.go("/t/amazing-grace?api=mock&scenario=&as=signedOut", "document.querySelector('[data-part-board] button')")
    f.run("document.querySelector('[data-part-board] button').click()")
    f.wait_text(t(f, "boards.guest"))

    # A challenge link: who and what, then Sing the part, accepted and into the song.
    f.go("/challenge/demo-token?api=mock&scenario=&as=member", "document.querySelector('[data-challenge] h1')")
    f.wait_text(t(f, "boards.challenge.from", {"name": "Ana Ruiz", "score": 91, "part": "Alto", "song": "Amazing Grace"}))
    keep(f, "challenge", "boards-challenge")
    f.run("window.mockCalls = [];")
    sing_button = f"[...document.querySelectorAll('[data-challenge] button')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'boards.challenge.sing', {'part': 'Alto'}))})"
    f.wait(sing_button, what="Sing the part (after game mode's challenger banner)")
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
