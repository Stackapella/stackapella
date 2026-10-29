"""The item get (docs/game-elements-plan.md; the proposal's idea 12, after A Link to the Past): in game
mode a new badge's toast is reskinned with a star gem held up at its start, bobbing twice, in an
arcade frame; the words are the toast's own. Celebrations off and serious mode give the plain toast.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from game_frames import dark, frames, light  # noqa: E402
from help_shots import t  # noqa: E402

GEM = "getComputedStyle(document.querySelector('[data-toast-tone=badge]'), '::before').content !== 'none'"
TOAST = """const { toast } = await import('/js/components/ui.js'); const { t } = await import('/js/i18n.js');
    toast(t('badges.new', { name: 'First notes' }), { seconds: 30, tone: 'badge' });"""


def journey(f):
    f.go("/settings?api=mock&as=signedOut", "document.querySelector('#game-heading')")
    f.run("localStorage.removeItem('stackapella.gameEffects')")
    f.run(TOAST)
    f.wait("document.querySelector('[data-toast-tone=badge]')", what="the badge's toast")
    if f.skin == "serious":
        assert not f.js(GEM), "serious mode: the plain toast"
        return
    assert f.js(GEM), "the gem held up"
    frames(f, "item-get", [0, 300, 550, 1100])
    f.shot("item-get")
    light(f)
    f.shot("item-get-light")
    dark(f)
    f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    f.wait(f"!({GEM})", what="Celebrations off: the plain toast")
    f.run("localStorage.removeItem('stackapella.gameEffects')")


if __name__ == "__main__":
    run("game_badge", journey)
