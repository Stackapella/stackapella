"""Choir groups (M7), against the mock: the groups you direct or sing in; a group as its director
sees it (the link, the songs with who sings which part and how far they've got, the singers),
assigning a part; Open opening the song on their part; and the group's link page.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/groups?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    f.wait_text("Thursday Singers")
    f.wait_text(t(f, "groups.youDirectCount", {"count": 2}))
    f.shot("groups")
    f.click("Thursday Singers", "a")
    f.wait("location.pathname === '/groups/Gp4rQs'", what="the group")
    f.wait_text("Sam Lee")
    f.wait_text(t(f, "groups.status.recorded"))
    f.wait_text(t(f, "groups.covered", {"count": 2, "total": 4}))
    # Invite singers shows the link card (the mock's link was made before: a new one to send).
    f.click_t("groups.invite")
    f.wait_text(t(f, "groups.linkHint"))
    f.shot("director")
    f.click_t("groups.assign")
    f.wait("document.querySelector('select[aria-label]')", what="the part pickers")
    f.run("const s = document.querySelector('select[aria-label]'); s.value = 'soprano'; s.dispatchEvent(new Event('change'));")
    f.wait(f"!document.body.innerText.includes({json.dumps(t(f, 'groups.refused.other'))})", what="assigned")
    f.click_t("groups.doneAssigning")
    # Open: a project from the song, on to Sing.
    f.run(f"[...document.querySelectorAll('button')].find((b) => b.textContent.trim() === {json.dumps(t(f, 'groups.openSong'))}).click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing, on the song")
    # The group's link page.
    f.go("/choir/Hk3nB8vQ2pL7sD5wR9tY1cX4?api=mock&as=member", "document.querySelector('h1')")
    f.wait_text(t(f, "invite.from", {"name": "Ana Ruiz"}))
    f.shot("join")
    f.click_t("groups.join")
    f.wait("location.pathname.startsWith('/groups/')", what="joined")


if __name__ == "__main__":
    run("groups", journey)
