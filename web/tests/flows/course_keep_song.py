"""Keep the finale (js/keep-song.js), as staff: Counterpoint's last lesson sung over loopback, then on its
lesson end "Keep this as a song" makes a song of your own, its tracks your line and the cantus, which
opens in the studio. Shot in both skins at both sizes with OUT=<dir>:

    OUT=~/Code/shots-review/keep-song PORT=8432 tool/flows.sh course_keep_song
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import compress  # noqa: E402
from plus_courses import picture, t  # noqa: E402

STORE = "(await import('/js/stores/learning.js'))"
ROUTER = "(await import('/js/router.js'))"
AUDIO = "(await import('/js/audio.js'))"
DB = "(await import('/js/db.js'))"
FINALE = "cp-5-challenge"


def journey(f):
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.mic_checked()
    f.go("/?api=mock&scenario=&as=admin", "document.querySelector('#app header')")
    f.run("localStorage.setItem('stackapella.installDismissed', '1');")
    f.go("/learn", "document.querySelector('[data-course-map]')")
    f.wait(f"{STORE}.learning.courses?.counterpoint || {STORE}.learning.catalogue.some((c) => c.id === 'counterpoint')", what="Counterpoint")
    # The last unit opens on the unit before's stars: every earlier lesson done first.
    f.run(f"""
        const store = {STORE};
        const data = store.learning.courses.counterpoint ?? await (await fetch('/course/plus/counterpoint/course.json')).json();
        for (const unit of data.units.slice(0, -1)) for (const lesson of unit.lessons) await store.finishLesson(lesson.id, {{ percent: 95, stars: 3 }});
    """)
    f.run(f"{ROUTER}.navigate('/learn/{FINALE}')")
    f.wait_text("every note is your choice")
    f.click_t("course.continue")
    f.wait_text("Then hear your duet")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("course.tap.sing")
    f.click_t("headphones.on")
    f.wait(f"{AUDIO}.engine.playing?.recording", what="recording the finale")
    f.wait("document.querySelector('[data-take-result]')", timeout=150, what="the finale's result")
    f.js(f"{AUDIO}.engine.setLoopback(false)")
    f.click_t("course.finish")

    # The lesson end offers to keep it; keeping makes a song with your line and the cantus.
    f.wait("document.querySelector('[data-keep-song-button]')", what="Keep this as a song")
    f.no_sideways("the finale's lesson end")
    # Headed by what you'd keep, the song's name; the button is the action.
    heading = f.js("document.querySelector('[data-keep-song] p').textContent.trim()")
    assert heading == "My counterpoint", heading
    picture(f, "keep-song-offer", scroll="document.querySelector('[data-keep-song]').scrollIntoView({ block: 'center' })")
    f.click(t(f, "keepSong.keep"), "[data-keep-song-button]")
    f.wait_text(t(f, "keepSong.kept"))
    song = f.js(f"""(async () => {{
        const projects = (await {DB}.all('projects')).filter((p) => p.fromLesson === '{FINALE}');
        const parts = (await {DB}.allBy('parts', 'projectId', projects[0].id)).sort((a, b) => a.order - b.order);
        return {{ count: projects.length, title: projects[0].title, parts: parts.map((p) => [p.name, p.frames > 0]) }};
    }})()""")
    assert song["count"] == 1 and song["title"] == "My counterpoint", song
    assert song["parts"] == [["My line", True], ["Cantus", True]], song
    picture(f, "keep-song-kept", scroll="document.querySelector('[data-keep-song]').scrollIntoView({ block: 'center' })")

    # The serious skin says the same, in the same words.
    f.run("window.stackapellaTheme.choose({ skin: 'serious' })")
    f.wait("document.documentElement.dataset.skin === 'serious'", what="the serious skin")
    f.run("(await import('/js/components/ui.js')).toasts.list.splice(0)")
    f.wait_text(t(f, "keepSong.kept"))
    f.shot("keep-song-kept-serious")
    f.run("window.stackapellaTheme.choose({ skin: 'game' })")

    # It opens in the studio as a song of your own, its two tracks there.
    f.click(t(f, "keepSong.open"), "[data-keep-song-open]")
    f.wait("/^\\/p\\/[^/]+$/.test(location.pathname)", what="the studio")
    f.wait_text("My line")
    f.wait_text("Cantus")
    f.no_sideways("the kept song in the studio")
    picture(f, "keep-song-in-studio")
    compress()


if __name__ == "__main__":
    run("course_keep_song", journey)
