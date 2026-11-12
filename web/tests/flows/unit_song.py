"""A unit's song at its end (retention row 8; js/unit-song.js): at unit 1's challenge sung with stars, one quiet card under
the next lesson offers Michael, Row the Boat Ashore's tune; it opens that part straight in Sing along with the other
voices, made once and kept. Counted once a device a place (us_shown, us_tap). A unit that sings on "oo" gets the
singer's part on it, the other parts with their words.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

# The lesson view's own instance (the app mounted as it is): its `done` is set as finishing the lesson sets it.
FIND = """(() => { const seen = new Set();
    const walk = (vnode) => { if (!vnode || typeof vnode !== 'object' || seen.has(vnode)) return null; seen.add(vnode);
        const c = vnode.component; if (c) { if (c.proxy && typeof c.proxy.openUnitSong === 'function') return c; const r = walk(c.subTree); if (r) return r; }
        for (const list of [vnode.children, vnode.dynamicChildren]) if (Array.isArray(list)) for (const ch of list) { const r = walk(ch); if (r) return r; }
        if (vnode.suspense) { const r = walk(vnode.suspense.activeBranch); if (r) return r; }
        return null; };
    return walk(document.querySelector('#app')?._vnode); })()"""  # the root's vnode, kept by Vue in every build
COUNTED = "JSON.parse(localStorage.getItem('stackapella.unitSong.counted') || '{}')"


def t(f, key, values=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('#app header')")
    f.run("localStorage.removeItem('stackapella.unitSong.counted')")
    f.go("/learn/1-challenge?api=mock&scenario=&as=member", "document.querySelector('main h1, main h2')")
    f.wait("(async () => (await import('/js/stores/learning.js')).learning.course?.units?.[0]?.song)()", what="the course, with unit 1's song")
    song = json.loads(f.js("(async () => JSON.stringify((await import('/js/stores/learning.js')).learning.course.units[0].song))()"))  # a reactive object comes back empty: as JSON
    assert song["template"] == "michael-row" and song["part"] == "melody", song
    # Unit 1 sung through, its challenge last, with stars: the lesson's end as finishing it shows it.
    f.wait(f"!!{FIND}", what="the lesson view")
    if f.skin == "game":
        # Game mode counts the end's pieces in (Celebrations) once a lesson really ends; set straight here, it shows them at once.
        f.run("(await import('/js/game/effects.js')).effects.celebrations = false")
    f.run("""const { learning } = await import('/js/stores/learning.js');
        for (const l of learning.course.units[0].lessons) learning.progress.lessons[l.id] = { stars: 3, bestPercent: 92, at: Date.now() };
        const { endLine, levelWord } = await import('/js/results.js');
        const { levelFor } = await import('/js/course.js');
        const view = """ + FIND + """.proxy;
        view.done = { stars: 3, xp: 30, percent: 92, word: levelWord(3, 92), line: endLine({ stars: 3, before: 0, scored: true }), label: null, tip: null, fresh: true, streak: 0, streakNew: false, place: 5, total: 5, unit: 1, sharpened: false, levelledUp: false, capped: false, level: levelFor(learning.progress.xp?.total ?? 0) };""")
    f.wait("document.querySelector('[data-unit-song][data-place=u1]')", what="unit 1's song at its end")
    card = "document.querySelector('[data-unit-song]')"
    title = song["game"]["title"]["en"] if f.skin == "game" and song.get("game") else song["title"]["en"]
    assert f.js(f"{card}.querySelector('p').textContent.trim()") == title, f.js(f"{card}.innerText")
    # The next lesson (unit 2's first) stays the main button; the song is the quiet second one under it.
    order = f.js(f"(() => {{ const main = document.querySelector('[data-end-next] .btn-primary'); return !!main && !!(main.compareDocumentPosition({card}) & Node.DOCUMENT_POSITION_FOLLOWING); }})()")
    assert order, "the main button first, the song after it"
    assert f.js(f"{card}.querySelector('button').classList.contains('btn-secondary')"), "a quiet button"
    assert f.js(COUNTED).get("us_shown:u1"), f.js(COUNTED)
    # Game mode's lesson title card ends on a tap (it's over the page while it plays).
    if f.js("!!document.querySelector('[data-game-title]')"):
        f.run("document.querySelector('[data-game-title]').click()")
        f.wait("!document.querySelector('[data-game-title]')", what="the title card gone")
    f.run(f"{card}.scrollIntoView({{ block: 'center' }})")
    f.no_sideways("the lesson end with the unit's song")
    f.shot("unit-song")
    # Sing it: the tune's part, straight in Sing along, the other voices singing.
    f.run(f"{card}.querySelector('[data-unit-song-open]').click()")
    f.wait("/^\\/p\\/[^/]+\\/sing$/.test(location.pathname)", what="Sing")
    f.wait("(async () => { const { session } = await import('/js/stores/session.js'); return session.project?.course?.unitSong && session.focusPart?.templatePartKey === session.project.course.partKey && session.practice?.sing?.way === 'parts'; })()", what="the tune's part, sung part by part")
    melody = f.js("(async () => { const { session } = await import('/js/stores/session.js'); return session.project.template.parts.find((p) => p.melody).key; })()")
    assert f.js("(async () => (await import('/js/stores/session.js')).session.project.course.partKey)()") == melody, "the tune"
    assert f.js(COUNTED).get("us_tap:u1"), f.js(COUNTED)
    f.shot("unit-song-sing")
    # A unit that sings on "oo": the singer's part on it, the other parts with their words; made once, then kept.
    oo = f.js("""(async () => { const { unitSongProject } = await import('/js/unit-song.js');
        const p = await unitSongProject({ template: 'amazing-grace', part: 'melody', syllable: 'oo' }, { lessonId: '2-challenge', unitId: 'test-oo', place: 'u2' });
        const again = await unitSongProject({ template: 'amazing-grace', part: 'melody', syllable: 'oo' }, { lessonId: '2-challenge', unitId: 'test-oo', place: 'u2' });
        const parts = p.template.parts;
        return { same: p.id === again.id, sung: [...new Set(parts.find((x) => x.melody).guide.notes.map((n) => n.lyric).filter(Boolean))], others: parts.filter((x) => !x.melody).some((x) => x.guide.notes.some((n) => n.lyric && n.lyric !== 'oo')) }; })()""")
    assert oo == {"same": True, "sung": ["oo"], "others": True}, oo


if __name__ == "__main__":
    run("unit_song", journey)
