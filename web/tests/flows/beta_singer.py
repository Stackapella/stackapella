"""M2 (with accounts, Hillel's decision 83e64a4), the beta singer's way in on the mock: the invite
link opens its page and leaves the address bar; Make my account carries the invite through the
sign-up; the email's link verifies the account and accepts the invite; the welcome, then consent
with the agreement, a credit from the profile name and "18 or over"; My parts with every status
worded and Hillel's note on a retake; Tell Hillel refusing an empty note; a link that's unknown;
an account that isn't a singer; signed out.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from pseudo_language import UNTRANSLATED  # noqa: E402

I18N = "(await import('/js/i18n.js'))"
CONTRACT = Path(__file__).resolve().parents[3] / "contract"


def t(f, key, values=None):
    import json
    return f.js(f"{I18N}.t({json.dumps(key)}, {json.dumps(values or {})})")


def journey(f):
    f.go("/beta/demo-token?api=mock&scenario=&as=signedOut", "document.querySelector('main h1')")
    f.wait("location.pathname === '/beta'", what="the token gone from the address bar")
    f.wait_text(t(f, "beta.welcome.title", {"name": "Sarah Kim"}))
    f.shot("invite")
    f.click_t("beta.invite.makeAccount")
    f.wait("location.pathname === '/signin'", what="the sign-up")
    f.wait_text(t(f, "auth.inviteIntro"))
    f.type("input[autocomplete=name]", "Sarah Kim")
    f.type("input[type=email]", "sarah@example.com")
    f.type("input[autocomplete=new-password]", "a long enough password")
    f.run("const [m, y] = document.querySelectorAll('form fieldset select'); m.value = '4'; m.dispatchEvent(new Event('change')); y.value = '1990'; y.dispatchEvent(new Event('change'));")
    f.shot("sign-up")
    f.click_t("auth.createAccount")
    f.wait_text(t(f, "auth.sent.title"))
    f.shot("check-email")
    # The link in the email: verified, and the invite accepted on the way.
    f.go("/verify?token=demo-verify-token&api=mock", "document.querySelector('h1')")
    f.wait_text(t(f, "auth.verified.title"))
    f.wait("!location.search.includes('token')", what="the token gone from the address bar")
    f.click_t("auth.continue")
    f.wait("location.pathname === '/beta'", what="back to the beta")
    f.wait_text(t(f, "beta.welcome.asked"))
    f.shot("welcome")
    f.click_t("beta.welcome.continue")
    f.wait("document.querySelector('form [type=checkbox]')", what="consent")
    f.wait_text(t(f, "beta.consent.draft"))
    f.wait_text(t(f, "beta.credit.fromProfile"))
    f.shot("consent")
    f.run("document.querySelector('form [type=checkbox]').click()")
    f.click_t("beta.consent.accept")
    f.wait_text(t(f, "beta.parts.title"))
    # Hillel's 91.png: counted at the top, sorted by what needs you, Hillel's note as a note, one
    # status a card (a song not checked yet says so, with no email promised), and one credit card.
    for status in ("guide", "retake", "accepted", "notOpen"):
        f.wait_text(t(f, f"beta.status.{status}"))
    # The headings are capitals on screen (innerText says so): read their words as written.
    for heading in ("needsYou", "comingUp", "library", "creditTitle"):
        f.wait(f"[...document.querySelectorAll('main h2')].some((h) => h.textContent.trim() === {t(f, 'beta.parts.' + heading)!r})", what=heading)
    f.wait_text(t(f, "beta.parts.count.retake", {"count": 1}))
    f.wait_text("Lovely tone.")
    f.wait_text(t(f, "beta.parts.notOpen"))
    f.wait_text(t(f, "beta.parts.hearLastTake"))
    f.wait_text(t(f, "beta.parts.style.straight"))  # how to sing it, from the part's style
    f.wait("[...document.querySelectorAll('main button')].some((b) => /Practise phrase \\d/.test(b.textContent))", what="Practise phrase N")
    order = f.js("[...document.querySelectorAll('main section[aria-labelledby^=parts-] h2')].map((h) => h.id).join()")
    assert order.startswith("parts-needs,parts-coming,parts-library"), order
    assert "email" not in f.js("document.querySelector('main').innerText.toLowerCase()"), "no email promised"
    f.shot("my-parts")
    # A course clip (the example's first item): its own card under Course clips, with the course, the
    # brief, where it sits against the comfortable note, and a recorder right in the card. Recorded
    # over the test's mic, it stops by itself after the count-in and the clip's seconds, then says
    # whether it's ready (the device's check, js/beta-clip.js; tests/beta-clip.test.js has its cases).
    clip = json.loads((CONTRACT / "examples/beta.parts.list.200.json").read_text())["data"][0]["clip"]
    f.wait("document.getElementById('clips-heading')", what="Course clips")
    f.wait_text(t(f, "beta.clips.onHome"))
    f.wait_text(clip["sing"][:40])
    f.mic_checked()
    f.run("document.getElementById('clips-heading').scrollIntoView()")
    f.click_t("beta.clips.record")
    f.wait_text(t(f, "beta.clips.singNow"), timeout=10)
    f.wait(f"[...document.querySelectorAll('main button')].some((b) => b.textContent.includes({t(f, 'beta.clips.hearBack')!r}))", timeout=clip["seconds"] + 10, what="the clip's check")
    f.shot("course-clip")
    # The credit's link to a public profile: off while profiles are closed (the example), offered once
    # they're open and the profile is public, and never for an anonymous credit.
    linked = json.loads((CONTRACT / "examples/beta.me.show.200.json").read_text())
    linked["data"].update(creditLinksProfile=True, hasPublicProfile=True, profilesOpen=True)
    f.run(f"""const api = await import('/js/api.js'); const {{ mockFetch }} = await import('/js/api-mock.js');
        api.useTransport(async (url, init = {{}}) => init.operation === 'beta.credit.update'
            ? new Response({json.dumps(json.dumps(linked))}, {{ status: 200, headers: {{ 'Content-Type': 'application/json' }} }})
            : mockFetch(url, init));""")
    f.click_t("beta.parts.editCredit")
    f.wait("document.querySelector('[data-credit-link] [role=switch]')?.disabled", what="the link switch, off while profiles are closed")
    f.wait_text(t(f, "beta.credit.linkClosed"))
    f.run("document.querySelector('[role=dialog] input[value=anonymous]').click()")
    f.wait("!document.querySelector('[data-credit-link]')", what="no link for an anonymous credit")
    f.run("document.querySelector('[role=dialog] input[value=name]').click()")
    f.click_t("common.save")
    f.wait_text(t(f, "beta.parts.creditLinked"))
    f.click_t("beta.parts.editCredit")
    f.wait("document.querySelector('[data-credit-link] [role=switch]')?.getAttribute('aria-checked') === 'true' && !document.querySelector('[data-credit-link] [role=switch]').disabled", what="the link switch, on")
    f.wait_text(t(f, "beta.credit.linkHint"))
    f.shot("credit-link")
    f.click_t("common.cancel")
    f.click_t("beta.tell.button")
    f.wait("document.querySelector('[role=dialog] textarea')", what="Tell Hillel")
    f.click_t("common.send")
    f.wait_text(t(f, "beta.tell.empty"))
    f.type("[role=dialog] textarea", "The timing check failed twice")
    f.click_t("common.send")
    f.wait_text(t(f, "beta.tell.sent"))
    # The same pages in the pseudo-language.
    f.go("/beta?api=mock&scenario=&as=singer&lang=pseudo", "document.querySelector('main h1')")
    f.wait("document.querySelector('main ul li')", what="My parts")
    left = f.js(UNTRANSLATED)
    assert not left, f"My parts: not translated: {left}"
    # A link that's unknown; an account that isn't a singer; signed out.
    f.go("/beta/old-token?api=mock&scenario=beta.invite.show:invite_not_found&as=signedOut&lang=en", "document.querySelector('main h1')")
    f.wait_text(t(f, "beta.ended.not_found"))
    f.shot("not-found")
    f.go("/beta?api=mock&scenario=&as=member", "document.querySelector('main h1')")
    f.wait_text(t(f, "beta.notSinger.title"))
    f.go("/beta?api=mock&scenario=&as=signedOut", "document.querySelector('main h1')")
    f.wait_text(t(f, "beta.signIn.title"))
    f.click_t("auth.signIn")
    f.wait("location.pathname === '/signin' && location.search.includes('next')", what="the sign-in, coming back to the beta")

    # Hillel's rule: a singer never sees money (rates, what's owed or paid) or the minutes paid per
    # part, and their pages never call the admin's operations.
    f.go("/beta?api=mock&scenario=&as=singer", "document.querySelector('main h1')")
    f.wait("document.querySelector('main ul li')", what="My parts again")
    for path in ("/beta", "/settings"):
        if path != "/beta":
            f.go(f"{path}?api=mock&scenario=&as=singer", "document.querySelector('#app *')")
            f.wait("document.querySelector('form') || document.querySelector('main')", what=path)
        money = f.js(r"(document.body.innerText.match(/[$€£₪¥]\s?\d|\d[.,]\d\d\s?(USD|EUR|GBP|ILS)|\b(USD|EUR|GBP|ILS)\b/) || [null])[0]")
        assert money is None, f"{path} shows money to a singer: {money}"
        minutes = f.js(r"(document.body.innerText.match(/\bminutes?\b|\bmin\b/i) || [null])[0]")
        assert minutes is None, f"{path} shows the minutes per part to a singer: {minutes}"
        admin = f.js("(globalThis.mockCalls ?? []).filter((op) => op.startsWith('admin.'))")
        assert not admin, f"{path} called the admin's operations: {admin}"


if __name__ == "__main__":
    run("beta_singer", journey)