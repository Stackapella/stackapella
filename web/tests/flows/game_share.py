"""The arcade share card (js/result-card.js; docs/game-elements-plan.md): in game mode with
Celebrations on, a lesson result's card is the same card in the game's type (the word in Bungee, the
score line and the dare in Press Start 2P, a CRT's faint lines), with NEW RECORD in the part colours
only when the take beat an earlier best. Celebrations off gives today's card; serious mode its own.
Each card is saved at full size beside the shots.
"""
import base64
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import SHOTS, run  # noqa: E402
from game_frames import dark, light  # noqa: E402
from result_share import MOUNT  # noqa: E402

CARD = "(async () => { const img = document.querySelector('[data-share-result] img'); const blob = await (await fetch(img.src)).blob(); const bytes = new Uint8Array(await blob.arrayBuffer()); let s = ''; for (const b of bytes) s += String.fromCharCode(b); return btoa(s); })()"


def card(f, label, record=False, story=False):
    f.press("Escape")
    f.run(MOUNT.replace("h(ShareResult, { result,", f"h(ShareResult, {{ record: {'true' if record else 'false'}, result,"))
    f.click_t("results.share.button")
    if story:
        f.wait("document.querySelector('[data-share-result] img')", what="the card")
        f.click_t("results.share.story")
    f.wait("document.querySelector('[data-share-result] img')", what="the card, made")
    SHOTS.mkdir(exist_ok=True)
    (SHOTS / f"{f.name}-{f.size}-card-{label}.png").write_bytes(base64.b64decode(f.js(CARD)))
    f.shot(label)


def journey(f):
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.gameEffects'); navigator.canShare = () => true; navigator.share = async () => {};")
    f.go("/?app&api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("navigator.canShare = () => true; navigator.share = async () => {};")
    if f.skin == "serious":
        card(f, "serious")
        return
    f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    card(f, "before")
    f.run("(await import('/js/game/effects.js')).effects.celebrations = true")
    card(f, "arcade")
    card(f, "arcade-record", record=True)
    card(f, "arcade-record-story", record=True, story=True)
    light(f)
    card(f, "arcade-record-light", record=True)
    dark(f)
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_share", journey, sizes=("desktop",))
