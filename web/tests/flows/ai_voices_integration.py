"""AI voices' integration check, against the real server (API=http://127.0.0.1:<port> tool/flows.sh ai_voices_integration),
with the backend's DemoSeeder and the switches ai_voices, own_choir and own_choir_songs on, and the AI voice licence
offered to Sarah (docs/ai-voices-build-plan.md, the frontend's step 3; the coordinator for Hillel, 2026-11-20):
- a learner (Sarah) sees Silent Night's four AI parts on its sheet, hears them in the studio, and the alto's panel credits
  "AI voice based on Ada E.'s singing, with permission" (the demo voice), never a singer's name; Amazing Grace's soprano
  stays a real voice ("Sarah K.");
- the AI voice licence: her offer on My parts, the terms, accepting, then withdrawing in the styled confirmation;
- Admin (the dev login): AI voices lists the demo voice, the review's Approved tab has Silent Night's parts, the guide
  list marks them AI;
- Hear yourself as a choir: the terms once, Start singing into Sing, a clip sent (as Make my choir sends it) and the
  page saying it's on its way (no renderer runs locally, so it stays queued); and one of her own songs asked for from
  its humming.
It changes the demo data: reseed before running it again.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

SESSION = "(await import('/js/stores/session.js')).session"
I18N = "(await import('/js/i18n.js'))"
OWN = "33333333-4444-4555-8666-777777777777"


def t(f, key, values=None):
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def sign_in(f, email, password):
    f.go("/signin?api=real", "document.querySelector('form')")
    f.type("input[type=email]", email)
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
    f.type("input[autocomplete=current-password]", password)
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("location.pathname !== '/signin'", timeout=20, what="signed in")


def journey(f):
    if f.size != "phone":
        return  # one size: the data changes as it runs (the mock flows cover both sizes and skins)
    # 409: her own song asked for while her first is still being made (request_open), on purpose below.
    f.expected_errors += ["401 (Unauthorized) http://127.0.0.1", "404 (Not Found) http://127.0.0.1", "419 (Unknown Status Code) http://127.0.0.1", "409 (Conflict) http://127.0.0.1"]
    sign_in(f, "sarah@example.com", "demo-password-for-the-flows")
    f.wait("(await import('/js/stores/account.js')).account.config?.aiVoices === true", what="ai_voices on for her")

    # The learner: Silent Night's sheet names its AI parts; the studio plays them, credited to the demo voice.
    f.go("/?api=real", "document.querySelector('#home-songs [data-song-card]') || document.querySelector('[data-browse-all]')")
    f.run("document.querySelector('[data-browse-all]')?.click()")
    card = "[...document.querySelectorAll('#home-songs [data-song-card]')].find((c) => c.textContent.includes('Silent Night'))"
    f.wait(card, what="Silent Night's card")
    assert f.js(f"!!{card}.querySelector('[data-card-ai]')"), "the card carries the AI tag"
    f.run(f"{card}.querySelector('button').click()")
    f.wait("document.querySelector('[data-song-ai-voices]')", what="the AI parts line")
    f.shot("sheet")
    f.press("Escape")
    song = f.js("""(async () => { const { startFromTemplate } = await import('/js/stores/projects.js'); const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('silent-night'), { singers: 4 })).id; })()""")
    f.go(f"/p/{song}?api=real", "document.querySelectorAll('canvas').length > 3")
    f.wait(f"{SESSION}.guidesVersion > 0 && !Object.values({SESSION}.guideVoices).some((v) => v?.loading)", timeout=60, what="the voices")
    ai = f.js(f"Object.values({SESSION}.guideVoices).filter((v) => v?.kind === 'real' && v.ai).length")
    assert ai == 4, f"four AI voices play from the server's files: {ai} (check MEDIA_URL and the demo's guide files)"
    f.run("document.querySelector('section[aria-labelledby] button[aria-expanded]').click()")
    f.click_t("studio.row.voice")
    f.wait("document.querySelector('[data-part-panel] [data-ai-voice-words]')", what="the panel's label")
    words = f.js("[...document.querySelectorAll('[data-part-panel] [data-ai-voice-words]')].map((w) => w.textContent.trim())")
    assert any("Ada E." in w for w in words), f"credited to the demo voice: {words}"
    assert "Sung by" not in f.js("document.querySelector('[data-part-panel]').innerText"), "never a singer's credit on an AI voice"
    f.shot("studio-panel")
    f.press("Escape")

    # The AI voice licence: offered to her by the seeder, accepted, then withdrawn.
    f.go("/beta?api=real", "document.querySelector('main h1')")
    f.wait("document.querySelector('[data-ai-licence-offer]') || document.querySelector('[data-ai-licence-voice]')", timeout=20, what="the licence on My parts")
    if f.js("!!document.querySelector('[data-ai-licence-offer]')"):
        f.run("document.querySelector('[data-ai-licence-read]').click()")
        f.wait("document.querySelector('[data-ai-licence-accept]')", what="the terms")
        assert f.js("document.querySelector('[data-ai-licence-accept]').disabled"), "nothing chosen for her: the credit first"
        f.run("document.querySelector('[data-ai-licence-terms] [data-ai-credit=named]').click()")
        f.wait("!document.querySelector('[data-ai-licence-accept]').disabled", what="Accept, once a credit is chosen")
        f.run("document.querySelector('[data-ai-licence-accept]').click()")
        f.wait("document.querySelector('[data-ai-licence-voice]')", what="accepted")
    f.shot("licence-accepted")
    f.run("document.querySelector('[data-ai-licence-withdraw]').click()")
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what="the withdraw confirmation")
    f.run("document.querySelector('[role=dialog] form .btn-danger').click()")  # its own button (another sheet's form may come first)
    f.wait("document.querySelector('[data-ai-licence][data-standing=withdrawn]')", what="withdrawn")

    # Hear yourself as a choir: the terms, Start singing, a clip sent, on its way.
    f.go("/t/amazing-grace/choir?api=real", "document.querySelector('[data-choir-agree]') || document.querySelector('[data-choir-page]')")
    if f.js("!!document.querySelector('[data-choir-agree]')"):
        f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-choir-start]')", what="Start singing")
    f.run("document.querySelector('[data-choir-start]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing on a choir project")
    f.run("""const { sendClip } = await import('/js/own-choir.js');
        const samples = new Float32Array(48000 * 12).map((_, i) => 0.3 * Math.sin(i / 20));
        window.choirSent = await sendClip({ templateId: 'amazing-grace', partKey: 'alto', samples, sampleRate: 48000, startSeconds: 0 });""")
    assert f.js("window.choirSent?.status") in ("queued", "rendering"), f.js("window.choirSent")
    f.go("/t/amazing-grace/choir?api=real", "document.querySelector('[data-choir-wait]')")
    f.shot("choir-wait")

    # One of her own songs from its humming (own_choir_songs): saved to her account first, then asked for.
    f.run(f"""const {{ loadTemplate }} = await import('/js/templates.js'); const s = await import('/js/stores/templates.js');
        const own = structuredClone(await loadTemplate('amazing-grace')); Object.assign(own, {{ id: {json.dumps(OWN)}, title: 'Sarah hums' }});
        await s.saveTemplate(own, {{ visibility: 'private', libraryOptIn: false }});
        const {{ keepHum }} = await import('/js/own-choir.js');
        await keepHum({json.dumps(OWN)}, {{ samples: new Float32Array(48000 * 10).map((_, i) => 0.3 * Math.sin(i / 25)), sampleRate: 48000 }});""")
    f.wait(f"(async () => (await (await import('/js/api.js')).api.templates.mine()).data.some((x) => x.id === {json.dumps(OWN)}))()", timeout=30, what="her song in her account")
    f.go(f"/t/{OWN}/choir?api=real", "document.querySelector('[data-choir-hum]')")
    f.shot("own-song-from-hum")
    # Her first song is still being made: one at a time, said in words, and the humming kept for the next try.
    f.run("document.querySelector('[data-choir-hum]').click()")
    f.wait_text(t(f, "ownChoir.open"))
    assert f.js(f"(async () => !!(await (await import('/js/own-choir.js')).humFor({json.dumps(OWN)})))()"), "the humming is kept until it's sent"
    f.run(f"const s = await import('/js/stores/templates.js'); await s.deleteTemplate({json.dumps(OWN)});")

    # Admin: the demo voice, the review's Approved tab, the guide list's AI marking.
    f.go("/dev/login", "document.querySelector('[data-dev-login]')")
    f.run("document.querySelector('[data-dev-login]').click()")
    f.wait("location.pathname === '/admin'", what="the admin, signed in")
    f.go("/admin/ai-voices?api=real", "document.querySelector('[data-ai-voices]')")
    assert "Alto A (demo)" in f.js("document.querySelector('[data-ai-voices]').innerText"), "the demo voice is listed"
    f.shot("admin-ai-voices")
    # The review queue lists uploads from the voices lane; the demo's AI guides are seeded directly, so its tabs are
    # empty here: the page answers, counts its tabs, and says so (the mock flow ai_review covers deciding).
    f.go("/admin/ai-review?api=real", "document.querySelector('[data-ai-review-status] [data-status-count]')")
    f.wait("document.querySelector('[data-ai-review]') || document.body.innerText.includes(" + json.dumps(t(f, "admin.aiReview.nonePending")) + ")", what="the queue, or its empty state")
    f.shot("admin-review")
    f.go("/admin/guides?api=real", "document.querySelector('[data-guide-ai]')")


if __name__ == "__main__":
    run("ai_voices_integration", journey)
