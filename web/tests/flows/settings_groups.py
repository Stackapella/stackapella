"""Settings grouped by goal (the consultant's UX review, item 7): Learning (Game mode, language and note names, your
range, starting the course over, how you sing, reminders, warm-ups, tools), Choir and groups (groups, the saved choir
voice, Lend your voice), Sound and voice care (sounds, voice care notes), and Account and device (profile, sign-in, plan,
your data, this device). The side nav has one stop a group; the old anchors still work. A guest sees Learning, Sound
and This device, never an empty group.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

GROUPS = "[...document.querySelectorAll('[data-group]')].map((g) => g.dataset.group)"


def has(group, hook):
    return f"!!document.querySelector('[data-group={group}] {hook}')"


def journey(f):
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-group=learning]')")
    f.wait(f"{GROUPS}.length === 4", what="the four groups")
    assert f.js(GROUPS) == ["learning", "choir", "sound", "settings-account"], f.js(GROUPS)
    for group, hook in (("learning", "#game [data-game-mode]"), ("learning", "[data-settings-range]"), ("learning", "[data-restart]"), ("sound", "[data-sounds]"), ("sound", "[data-voice-notes]"), ("settings-account", "#profile"), ("settings-account", "#data"), ("settings-account", "[data-wipe]"), ("choir", 'a[href=\\"/groups\\"]')):
        assert f.js(has(group, hook)), f"{hook} isn't in {group}"
    if f.size == "desktop":
        assert f.js("document.querySelectorAll('nav li a[href^=\"#\"]').length") == 4, "the side nav isn't one stop a group"
    f.no_sideways("Settings by goal")
    f.shot("member")
    # A guest: no empty Choir group.
    f.go("/settings?api=mock&scenario=&as=signedOut", "document.querySelector('[data-group=learning]')")
    assert f.js(GROUPS) == ["learning", "sound", "settings-account"], f.js(GROUPS)
    f.shot("guest")


if __name__ == "__main__":
    run("settings_groups", journey)
