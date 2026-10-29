"""Admin > Posts (Hillel: "where are the posts to review?"), against the mock: the posts waiting for
approval with their text, network and song, a count by Posts in the nav; Approve sends one (it leaves
Waiting), Cancel asks first in a styled sheet, then turns the other down. Shot in Warm light.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import light_first, t  # noqa: E402

POSTS = "document.querySelectorAll('[data-post]').length"


def journey(f):
    f.go("/admin/posts?api=mock&scenario=&as=admin", "document.querySelector('main')")
    light_first(f)
    f.wait(f"{POSTS} === 2", what="the two posts waiting")
    f.wait("document.querySelector('[data-nav-count]')?.textContent.trim() === '2'", what="the count by Posts in the nav")
    page = f.js("document.querySelector('main').innerText")
    for words in ["Danny Boy", "#harmony", t(f, "admin.posts.network.bluesky"), t(f, "admin.posts.network.x"), t(f, "admin.posts.status.awaiting")]:
        assert words in page, f"{words!r} isn't on the screen"
    f.shot("waiting")
    f.run("document.querySelector('[data-post] [data-approve]').click()")
    f.wait_text(t(f, "admin.posts.approved"))
    f.wait(f"{POSTS} === 1", what="the approved post leaving Waiting")
    f.run("document.querySelector('[data-post] [data-cancel]').click()")
    f.wait("document.querySelector('[role=dialog]')", what="the styled confirm")
    assert t(f, "admin.posts.cancelHint") in f.js("document.querySelector('[role=dialog]').innerText"), "the confirm doesn't say what Cancel does"
    f.shot("cancel")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "admin.posts.cancelled"))
    f.wait(f"{POSTS} === 0", what="the cancelled post leaving Waiting")


if __name__ == "__main__":
    run("admin_posts", journey)
