"""Every free tool's lesson line (site/tool.blade.php's data-tool-lesson, shown in the tool's bar once it has a result)
leads to a lesson a searcher can open: signed out, on a device that has never run the app, it opens at its first step,
never the free-account ask (a guest gets each course's first lesson: courses.js guestLesson) or a locked unit. The
targets are read from the tools' words (lang/en/site.php, each tool's `lesson.to`), so a new or changed line is checked
as it lands. Both skins (FLOW_SKINS=game,serious)."""
import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SITE = Path(__file__).resolve().parents[3] / "server/lang/en/site.php"
I18N = "(await import('/js/i18n.js'))"


def targets():
    """Each tool's lesson address, from its `'lesson' => [... 'to' => '/learn/<id>']` words."""
    found = sorted(set(re.findall(r"'lesson'\s*=>\s*\[[^\]]*?'to'\s*=>\s*'(/learn/[\w-]+)'", SITE.read_text())))
    assert found, "no tool has a lesson line"
    return found


def journey(f):
    for path in targets():
        f.go("/icons/favicon.svg")
        f.run("localStorage.clear(); sessionStorage.clear();")
        f.go(f"{path}?api=mock&as=signedOut&ref=tool-test", "document.querySelector('#app main')")
        locked = f.js(f"{I18N}.t('course.locked')")
        f.wait(f"document.querySelector('[data-step-bars]') || document.querySelector('[data-needs-account]') || document.querySelector('#app main')?.innerText.includes({json.dumps(locked)})", what=f"{path} opened")
        assert not f.js("!!document.querySelector('[data-needs-account]')"), f"{path}: a guest is asked for an account"
        assert locked not in f.js("document.querySelector('#app main').innerText"), f"{path}: the unit is locked for a guest"
        assert f.js("!!document.querySelector('[data-step-bars]')"), f"{path}: the lesson's steps"
    f.shot("lesson")


if __name__ == "__main__":
    run("tool_lessons", journey, sizes=("phone",))
