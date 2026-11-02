"""Settings' photo: one that won't load (offline, or gone from the store) shows the name's letter, as
Home does, never a broken picture. Against the mock.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

PROFILE = "document.querySelector('form#profile')"


def journey(f):
    f.expected_errors += ["/no-such-photo.jpg"]  # the photo that won't load, on purpose
    f.go("/settings?api=mock&scenario=&as=member", PROFILE)
    f.run("const { account } = await import('/js/stores/account.js'); account.me = { ...account.me, avatarUrl: '/no-such-photo.jpg' };")
    f.wait(f"!{PROFILE}.querySelector('img') && {PROFILE}.querySelector('span.rounded-full')?.textContent.trim() === 'A'", what="the letter in place of a broken photo")
    f.shot("avatar-fallback")


if __name__ == "__main__":
    run("settings_avatar", journey)
