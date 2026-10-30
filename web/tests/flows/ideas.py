"""Ideas, slice 1 (docs/ideas-app-plan.md): closed, the shelf says so and nothing else shows; open, the
shelf lists tunes waiting for voices with their parts, a part's chip asks for those that need it, and
Your tunes shows the writer's own; a tune's page has its writer and note, its parts with Sing this
part, the voices so far, and Report; Sing this part starts the tune as your own song on that part,
carrying the idea.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects, sung_takes  # noqa: E402

LIST = "document.querySelector('[data-ideas-list]')"


def journey(f):
    f.go("/ideas?api=mock&as=member&scenario=ideas.list:not_found", "document.querySelector('#app h1')")
    f.wait("document.querySelector('[class*=empty], main')?.innerText.length > 0", what="the closed shelf")
    f.wait_text(f.js("(await import('/js/i18n.js')).t('ideas.closed')"))
    assert not f.js(f"!!{LIST}"), "closed: no tunes"

    f.go("/ideas?api=mock&as=member&scenario=", LIST)
    assert f.js("document.querySelectorAll('[data-idea]').length") >= 1, "the shelf lists tunes"
    f.no_sideways("the Ideas shelf")
    f.shot("shelf")
    f.run("[...document.querySelectorAll('.filter-chip')].find((b) => b.getAttribute('aria-pressed') !== null).click()")
    f.wait(f"{LIST} || document.querySelector('[data-ideas-empty]')", what="the shelf, by the part it needs")
    assert f.js("document.querySelector('.filter-chip[aria-pressed=true]') !== null"), "the chip shows it's chosen"

    first = f.js("document.querySelector('[data-idea]').dataset.idea")
    f.run("document.querySelector('[data-idea]').click()")
    f.wait("document.querySelector('[data-idea-part]')", what="the tune's page")
    assert f.js("document.querySelectorAll('[data-idea-part]').length") >= 2, "its parts"
    assert f.js("document.querySelectorAll('[data-idea-voice]').length") >= 1, "its voices so far"
    f.no_sideways("a tune's page")
    f.shot("idea")
    f.run("document.querySelector('[data-idea-report-open]').click()")
    f.wait("document.querySelector('[data-idea-report]')", what="the report sheet")
    f.shot("report")
    f.press("Escape")
    f.wait("!document.querySelector('[data-idea-report]')", what="the report sheet closed")

    clear_projects(f)
    f.wait("!document.querySelector('[data-idea-part=tenor] button').disabled", what="the tune read")
    f.run("document.querySelector('[data-idea-part=tenor] button').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing, on the new song")
    project = f.js("(await import('/js/db.js')).get('projects', location.pathname.split('/')[2])")
    assert project["idea"]["id"] == first and project["idea"]["partKey"] == "tenor", f"the song carries the idea: {project.get('idea')}"

    # Slice 3: a take of the tenor, added to the idea (create, the WAV up, complete), from beat zero.
    f.wait("(await import('/js/stores/session.js')).session.parts.length > 0", what="the song open")
    sung_takes(f, ["tenor"])
    sent = f.js("""(async () => {
        const s = await import('/js/stores/session.js'); const { IdeaVoice } = await import('/js/components/idea-voice.js');
        const part = s.session.parts.find((p) => p.templatePartKey === 'tenor');
        const me = { idea: s.session.project.idea, part, state: 'idle', error: null };
        await IdeaVoice.methods.send.call(me);
        return { state: me.state, error: me.error };
    })()""")
    assert sent["state"] == "sent", f"the voice is added: {sent}"
    # The card itself, as Sing shows it under the take.
    f.run("""const { createApp } = await import('vue'); const { t } = await import('/js/i18n.js'); const s = await import('/js/stores/session.js');
        const { IdeaVoice } = await import('/js/components/idea-voice.js'); const part = s.session.parts.find((p) => p.templatePartKey === 'tenor');
        const box = document.createElement('div'); box.id = 'idea-voice-shot'; box.className = 'fixed inset-x-0 top-0 z-50 bg-ink p-4'; document.body.append(box);
        const app = createApp(IdeaVoice, { idea: s.session.project.idea, part, partName: 'Tenor' }); app.config.globalProperties.t = t; app.mount(box);""")
    f.wait("document.querySelector('#idea-voice-shot [data-idea-voice-rights]')", what="the add-your-voice card")
    f.shot("add-voice")
    f.run("document.getElementById('idea-voice-shot').remove()")
    clear_projects(f)

    # Slice 2: Share as an idea, on a song of your own that's backed up.
    f.run("""const s = await import('/js/stores/templates.js'); const ex = (await (await fetch('/contract/examples/templates.show.200.json')).json()).data.template;
        await s.loadMine(); await s.saveTemplate({ ...ex, id: 'b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901', title: 'Our Song for Gran' });
        // Backed up to the account (the mock's save stays on the device).
        const db = await import('/js/db.js'); const r = await db.get('templates', 'b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901'); await db.put('templates', { ...r, state: 'saved', savedVersion: 1 });""")
    f.go("/t/b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901?api=mock&scenario=&as=member", "document.querySelector('[data-share-idea-open]')")
    f.run("document.querySelector('[data-share-idea-card]').scrollIntoView()")
    f.shot("share-card")
    f.run("document.querySelector('[data-share-idea-open]').click()")
    f.wait("document.querySelector('[data-share-idea]')", what="the share sheet")
    share = "[...document.querySelectorAll('[data-share-idea] button[type=submit]')][0]"
    assert f.js(f"{share}.disabled"), "Share waits for the rights confirmation"
    f.run("const n = document.querySelector('[data-share-idea] textarea'); n.value = 'Find me at www.example.com'; n.dispatchEvent(new Event('input'));")
    f.run("document.querySelector('[data-idea-rights]').click()")
    assert f.js(f"{share}.disabled"), "a note with a link can't be shared"
    f.run("const n = document.querySelector('[data-share-idea] textarea'); n.value = 'Who wants the bass?'; n.dispatchEvent(new Event('input'));")
    f.wait(f"!{share}.disabled", what="Share, once confirmed")
    f.shot("share-sheet")
    f.run(f"{share}.click()")
    f.wait("/^\\/ideas\\/[\\w-]+$/.test(location.pathname)", what="the tune's own page")
    f.go("/t/b2c3d4e5-6f70-4182-93a4-b5c6d7e8f901?api=mock&scenario=ideas.create:adult_required&as=member", "document.querySelector('[data-share-idea-open]')")
    f.run("document.querySelector('[data-share-idea-open]').click()")
    f.wait("document.querySelector('[data-idea-rights]')", what="the share sheet")
    f.run("document.querySelector('[data-idea-rights]').click()")
    f.run("document.querySelector('[data-share-idea] button[type=submit]').click()")
    f.wait_text(f.js("(await import('/js/i18n.js')).t('ideas.share.refused.adult')"))


    # Slice 4: the way in from New song, the email switch, and the admin's queue.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-new-song]')")
    f.run("document.querySelector('[data-new-song]').click()")
    f.wait("document.querySelector('[data-new-ideas]')", what="Sing someone's tune, in New song")
    f.run("document.querySelector('[data-new-ideas]').click()")
    f.wait("location.pathname === '/ideas'", what="the shelf")
    f.go("/settings?api=mock&scenario=&as=member", "document.querySelector('[data-idea-emails] [role=switch]')")
    f.run("document.querySelector('[data-idea-emails] [role=switch]').click()")
    f.wait("document.querySelector('[data-idea-emails] [role=switch]').getAttribute('aria-checked') === 'false' || document.querySelector('[role=status], [data-toast]')", what="the switch saved")
    f.go("/admin/ideas?api=mock&scenario=&as=admin", "document.querySelector('[data-held-idea]')")
    f.shot("admin")
    f.run("document.querySelector('[data-held-idea] .btn-primary').click()")
    f.wait("!document.querySelector('[data-held-idea] .btn-primary')?.disabled", what="approved")


run("ideas", journey)
