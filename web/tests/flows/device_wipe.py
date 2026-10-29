"""Delete everything on this device (Settings, This device): a guest's songs, a course voice take kept
for Then and now and the app's keys in the browser's storage are all counted in the confirm, then
gone, and the app opens fresh on Home. Signed in, it signs out here first. The Storage page's narrower
row deletes only the course voice takes, and the stars stay.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

DB = "(await import('/js/db.js'))"
I18N = "(await import('/js/i18n.js'))"
SEED = f"""const db = {DB};
    await db.put('projects', {{ id: 'wipe-song', title: 'Kept here', bpm: 90, key: 'C', timeSignature: '4/4', countInBars: 1, metronome: true, createdAt: Date.now(), updatedAt: Date.now() }});
    await db.putAudio('course:wipe-first', new Float32Array(4800));
    await db.put('learning', {{ sampleRate: 48000, line: [], trace: [], at: Date.now() }}, 'course:wipe-first');
    localStorage.setItem('stackapella.wipeProbe', '1');"""
COURSE_KEYS = f"(await {DB}.keys('learning')).filter((k) => String(k).startsWith('course:')).length"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def details(f):
    return dict(f.js("[...document.querySelectorAll('[role=dialog] dl > div')].map((row) => [row.querySelector('dt').innerText.trim(), Number(row.querySelector('dd').innerText)])"))


def wipe(f, signed_in):
    f.go(f"/settings?api=mock&as={'member' if signed_in else 'signedOut'}", "document.querySelector('[data-wipe-start]')")
    f.run(SEED)
    f.run("document.getElementById('device').scrollIntoView()")
    f.no_sideways("the This device section")
    f.run("document.querySelector('[data-wipe-start]').click()")
    f.wait("document.querySelector('[role=dialog] dl')", what="the confirm, with what goes")
    rows = details(f)
    assert rows.get(t(f, "settings.wipe.songs"), 0) >= 1, f"the songs are counted: {rows}"
    assert rows.get(t(f, "settings.wipe.courseTakes")) == 1, f"the course voice take is counted: {rows}"
    assert (t(f, "settings.wipe.notBackedUp") in rows) == signed_in, f"not backed up, only signed in: {rows}"
    f.shot("confirm-member" if signed_in else "confirm-guest")
    f.click_t("settings.wipe.action")
    f.wait("location.pathname === '/' && !localStorage.getItem('stackapella.wipeProbe') && document.querySelector('h1')", what="a fresh Home")
    assert f.js(f"(await {DB}.all('projects')).length") == 0, "no songs left"
    assert f.js(COURSE_KEYS) == 0, "no course voice takes left"
    assert f.js(f"(await {DB}.keys('audio')).length") == 0, "no audio left"


def course_takes(f):
    f.go("/storage?api=mock&as=member", "document.querySelector('h1')")
    f.run(SEED + " const l = await import('/js/stores/learning.js'); await l.loadLearning(); await l.finishLesson('1-match', { percent: 80, stars: 2 });")
    f.go("/storage?api=mock&as=member", "document.querySelector('[data-course-takes]')")
    f.run("document.querySelector('[data-course-takes]').scrollIntoView()")
    f.shot("storage-course-takes")
    f.run("document.querySelector('[data-course-takes] button').click()")
    f.click_t("cloud.storage.courseTakesAction")
    f.wait("!document.querySelector('[data-course-takes]')", what="the row gone once they're deleted")
    assert f.js(COURSE_KEYS) == 0, "the course voice takes are deleted"
    assert f.js(f"(await {DB}.all('projects')).some((p) => p.id === 'wipe-song')"), "the songs stay"
    assert f.js("(await (await import('/js/stores/learning.js')).loadLearning()).progress.lessons['1-match']?.stars") == 2, "the stars stay"


def journey(f):
    course_takes(f)
    wipe(f, signed_in=False)
    wipe(f, signed_in=True)


run("device_wipe", journey)
