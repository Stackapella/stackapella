"""M2's integration check, against the real server (API=http://127.0.0.1:8000 tool/flows.sh
beta_integration), end to end: Nora's invite link is opened, the demo account Ada signs in with
it and accepts it (singers have accounts: Hillel's decision 83e64a4), agrees and records Amazing
Grace's alto over loopback, then sends it; Hillel signs in through /dev/login, finds her take in the review queue,
plays it in the mix and accepts it; once it's published, a learner opening Amazing Grace hears the
alto as a real voice, credited to her. Needs the backend's demo data (php artisan db:seed) and its
queue worker (`queue:work --queue=long,default`, for ffmpeg). It changes the demo data: reseed
before running it again.
"""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
AUDIO = "(await import('/js/audio.js'))"
CREDIT = "Ada Lovelace (demo)"  # the account's name is the credit


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.expected_errors.append("401 (Unauthorized) http://127.0.0.1")  # signed-out checks before signing in
    f.expected_errors.append("404 (Not Found) http://127.0.0.1")  # a part with no real voice yet
    f.expected_errors.append("419 (Unknown Status Code) http://127.0.0.1")  # a first CSRF token, retried
    # The singer: her link, the agreement, then the alto.
    f.go("/beta/demo-singer-link-for-the-flow-scripts-0001?api=real", "document.querySelector('main h1')")
    # On a second run the invite is already Ada's: the page asks her to sign in instead.
    f.wait(f"document.body.innerText.includes({I18N}.t('beta.invite.haveAccount')) || document.body.innerText.includes({I18N}.t('beta.signIn.title'))", what="the invite page")
    f.click_t("beta.invite.haveAccount" if f.js(f"document.body.innerText.includes({I18N}.t('beta.invite.haveAccount'))") else "auth.signIn")
    f.wait("location.pathname === '/signin'", what="the sign-in, with the invite")
    f.type("input[type=email]", "ada@example.com")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("document.querySelector('input[autocomplete=current-password]')", what="the password step")
    f.type("input[autocomplete=current-password]", "demo-password-for-the-flows")
    f.run("document.querySelector('form').requestSubmit()")
    f.wait("location.pathname === '/beta'", timeout=20, what="back to the beta, the invite accepted")
    if f.js(f"document.body.innerText.includes({I18N}.t('beta.welcome.continue'))"):
        f.click_t("beta.welcome.continue")
        f.wait("document.querySelector('form [type=checkbox]')", what="the agreement")
        f.run("document.querySelector('form [type=checkbox]').click()")
        f.click_t("beta.consent.accept")
    f.wait_text(t(f, "beta.parts.title"))
    f.run("[...document.querySelectorAll('li.card')].find((li) => li.textContent.includes('Amazing Grace')).querySelector('.btn-primary').click()")
    f.wait("location.pathname.startsWith('/p/') && document.querySelectorAll('canvas').length > 3", timeout=30, what="the studio in beta mode")
    alto = t(f, "parts.alto")
    f.js(f"{AUDIO}.unlock()")
    f.js(f"{AUDIO}.engine.setLoopback(true)")
    f.click_t("studio.recordNamed", {"name": alto})
    f.wait("document.querySelector('[role=dialog]')", what="the headphones question")
    f.click_t("headphones.start")
    f.wait("document.querySelector('[role=dialog] select')", what="the timing check")
    f.click_t("timing.start")
    f.wait(f"document.body.innerText.includes({I18N}.t('timing.startRecording'))", timeout=40, what="the timing measured")
    f.click_t("timing.startRecording")
    if f.js(f"document.body.innerText.includes({I18N}.t('beta.sound.title'))"):
        f.click_t("beta.sound.start")
        f.wait(f"document.body.innerText.includes({I18N}.t('beta.sound.done'))", timeout=30, what="the sound check")
        f.click_t("beta.sound.done")
    f.wait(f"{AUDIO}.engine.playing?.recording", timeout=20, what="recording")
    f.wait(f"{AUDIO}.engine.ctx.currentTime > {AUDIO}.engine.playing.startTime + 4", timeout=20, what="4 s recorded")
    f.click_t("studio.stopRecording")
    f.wait_text(t(f, "beta.studio.recorded", {"name": alto}))
    f.click_t("beta.studio.checkAndSend")
    f.wait(f"document.body.innerText.includes({I18N}.t('beta.report.send'))", timeout=30, what="the report")
    f.click_t("beta.report.send")
    f.wait("location.pathname === '/beta'", what="back to My parts")
    f.wait("(await (await import('/js/db.js')).all('betaTakes')).length === 0", timeout=60, what="the take on the server")
    f.shot("sent")

    # Hillel: the review queue, her take, accepted.
    f.go("/dev/login", "document.querySelector('[data-dev-login]')")
    f.run("document.querySelector('[data-dev-login]').click()")
    f.wait("location.pathname === '/admin'", what="the admin, signed in")
    f.go("/admin/review?api=real", "document.querySelector('main')")
    f.wait("[...document.querySelectorAll('main ul a')].some((a) => a.textContent.includes('Alto'))", timeout=60, what="her take in the queue (processed)")
    f.run("[...document.querySelectorAll('main ul a')].find((a) => a.textContent.includes('Alto')).click()")
    f.wait("location.pathname.startsWith('/admin/review/')", what="her take")
    f.wait("!document.querySelector('main .btn-primary').disabled", timeout=30, what="the take loaded")
    f.click_t("admin.review.play")
    f.wait(f"{AUDIO}.engine.playing && {AUDIO}.engine.parts.has('review:take')", what="playing in the mix")
    f.click_t("studio.stop")
    f.shot("review")
    f.click_t("admin.review.accept")
    f.wait("document.querySelector('[role=dialog] form')", what="the accept sheet")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait_text(t(f, "admin.review.isGuide"), timeout=90)
    f.shot("published")

    # A learner: Amazing Grace's alto is her voice now, with her credit.
    credit = f.js("""(async () => {
        const { loadCatalogue, recordingFor } = await import('/js/stores/catalogue.js');
        await loadCatalogue({ fresh: true });
        return recordingFor('amazing-grace', 'alto')?.credit ?? null;
    })()""")
    assert credit == CREDIT, f"the catalogue's alto credit is {credit!r}"
    project = f.js("""(async () => {
        const { startFromTemplate } = await import('/js/stores/projects.js');
        const { loadTemplate } = await import('/js/templates.js');
        return (await startFromTemplate(await loadTemplate('amazing-grace'), { singers: 4 })).id;
    })()""")
    f.go(f"/p/{project}?api=real", "document.querySelectorAll('canvas').length > 3")
    f.run(f"{AUDIO}.unlock()")
    f.run("(await import('/js/stores/session.js')).ready()")
    f.wait_text(t(f, "studio.badge.realVoiceBy", {"name": CREDIT}), timeout=30)
    f.shot("learner")
    f.run(f"(await import('/js/stores/projects.js')).deleteProject('{project}')")


if __name__ == "__main__":
    run("beta_integration", journey, sizes=("desktop",))
