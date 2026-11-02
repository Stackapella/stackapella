"""Admin > System > Code watch (docs/code-watch-plan.md), on the contract's example: a repository with
our markers and a website calling our API, each with when it was seen (and the calls), a note, and
Dismiss into the dismissed list.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402


def journey(f):
    f.go("/admin/code-watch?api=mock&scenario=", "document.querySelector('[data-code-watch] [data-hit]')")
    assert f.js("document.querySelectorAll('[data-hit]').length") == 2
    assert "214" in f.js("document.querySelector('[data-hit][data-kind=domain]').textContent"), "the calls"
    assert f.js("!!document.querySelector('[data-hit][data-kind=domain] [data-hit-note]')"), "its note"
    f.shot("code-watch")
    f.run("document.querySelector('[data-hit][data-kind=marker] [data-hit-dismiss]').click()")
    f.wait("true", what="sent")


if __name__ == "__main__":
    run("admin_code_watch", journey)
