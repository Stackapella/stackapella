"""A choir with friends (docs/choir-together-plan.md; behind own_choir_together), on the mock:
- the owner's ready choir page: Who sings, one row a part, each with its AI label and what sits high for them; one invite
  line naming those parts, which makes a link (shown, copied, or the device's share sheet) and can be cancelled;
- over the free song's one friend: the invite opens the Plus sheet (choir_friends), with the prices;
- a friend's parts: "AI voice made from Ben's singing", their name opening a styled confirmation that removes them and
  names exactly what goes and what plays again; waiting for the owner's minutes says how many and when;
- the owner deleting the song with a friend in it;
- the friend's join at /choir/join/<token>: who invited them to sing which parts; signed out, an account first, the link
  kept; signed in, three points and Show my name (off), then Agree and use my saved voice, or Agree and start singing (Sing
  on the invite's part, the 30 seconds sent to the invite);
- the friend afterwards on Home: the quiet ready card, Choirs you sing in, Listen, and Leave in a styled confirmation.
Off, nothing of it shows.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

FLAGS = ["stackapella.mockChoirFriend", "stackapella.mockChoirJoined", "stackapella.mockChoirFit", "stackapella.mockChoirState", "stackapella.mockChoir"]


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def tab(f, together=True, **flags):
    """The choir on for the tab (its example: Silent Night ready, made from their voice), together on or off, these flags."""
    clears = "".join(f"sessionStorage.removeItem({json.dumps(k)});" for k in FLAGS)
    sets = "".join(f"sessionStorage.setItem({json.dumps('stackapella.' + k)}, {json.dumps(v)});" for k, v in flags.items())
    f.run(f"sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.setItem('stackapella.mockTogether', '{'1' if together else '0'}'); {clears} {sets}")


def page(f, who="plus", wait="document.querySelector('[data-choir-play]')"):
    f.go(f"/t/silent-night/choir?api=mock&scenario=&as={who}", wait)


def confirm(f, what):
    f.wait("document.querySelector('[role=dialog] form .btn-danger')", what=what)
    title = f.js("document.querySelector('[role=dialog] h2')?.textContent ?? ''")
    body = f.js("document.querySelector('[role=dialog] form p')?.textContent ?? ''")
    return title, body


HOLES = r"""(() => { const text = document.querySelector('main')?.innerText ?? '';
    const holes = [...text.matchAll(/\{[^}]*\}|(?:^|[.!?:]\s+)(?:'s\b|never\b)|\bIn 's\b/g)].map((m) => m[0]);
    return holes; })()"""


def shoot(f, name):
    """Every screen shot here is checked first: no placeholder left unfilled, no sentence missing its name."""
    holes = f.js(HOLES)
    assert not holes, f"{name}: words with a hole: {holes}"
    f.shot(name)


def journey(f):
    f.go("/?api=mock&scenario=&as=plus", "document.querySelector('h1')")
    clear_projects(f)

    # Off: the ready page keeps its line; no Who sings, no invite.
    tab(f, together=False)
    page(f)
    assert not f.js("document.querySelector('[data-choir-who], [data-choir-invite]')"), "together shows while it's off"
    assert f.js("!!document.querySelector('[data-choir-progress]')"), "the really-you line is gone while together is off"

    # The owner (Plus): Who sings, the soprano sitting high for them, and the invite naming the high parts.
    tab(f)
    page(f, wait="document.querySelector('[data-choir-who]')")
    rows = f.js("document.querySelectorAll('[data-who-part]').length")
    assert rows == 4, f"one row a part: {rows}"
    assert f.js("[...document.querySelectorAll('[data-who-part]')].every((r) => r.querySelector('[data-ai-voice=tag]'))"), "a part without its AI chip"
    assert t(f, "ownChoir.together.who.high") in f.js("document.querySelector('[data-who-part=soprano]').textContent"), "the soprano sits high for them"
    assert not f.js("document.querySelector('[data-choir-progress]')"), "the really-you line beside Who sings"
    label = f.js("document.querySelector('[data-choir-invite]').textContent.trim()")
    # One part sits high (the soprano): it's named, not "the high parts", in both skins.
    assert label == t(f, "ownChoir.together.invite.parts", {"count": 1, "parts": "soprano"}), label
    f.no_sideways("Who sings")
    shoot(f, "owner-who-sings")
    # No share sheet here: the link is copied, and shown to copy again or cancel.
    f.run("Object.defineProperty(navigator, 'share', { value: undefined, configurable: true }); navigator.clipboard.writeText = async (text) => { window.__copied = text; };")
    f.run("document.querySelector('[data-choir-invite]').click()")
    f.wait("document.querySelector('[data-choir-invite-sent] input')", what="the invite's link")
    url = f.js("document.querySelector('[data-choir-invite-sent] input').value")
    assert "/choir/join/" in url and url in f.js("window.__copied ?? ''"), url
    assert t(f, "ownChoir.together.invite.shareParts", {"count": 1, "parts": "soprano", "song": "Silent Night"}) in f.js("window.__copied"), f.js("window.__copied")
    shoot(f, "owner-invite-sent")
    f.run("document.querySelector('[data-choir-invite-cancel]').click()")
    f.wait("!document.querySelector('[data-choir-invite-sent]') && document.querySelector('[data-choir-invite]')", what="the invite cancelled")

    # A friend's parts: their row, the AI label with their name; removing them names what goes and what plays again.
    tab(f, mockChoirFriend="ready")
    page(f, wait="document.querySelector('[data-who-friend]')")
    friend = f.js("document.querySelector('[data-who-part=soprano]').textContent")
    assert t(f, "ownChoir.together.who.friendVoice", {"name": "Ben"}) in friend and f.js("!!document.querySelector('[data-who-part=soprano] [data-ai-voice=tag]')"), friend
    assert t(f, "ownChoir.together.listen.withFriends", {"names": "Ben's"}) == f.js("document.querySelector('[data-choir-label]').textContent.trim()") or f.js("document.documentElement.lang") != "en", "the panel line names Ben"
    shoot(f, "owner-friend")
    f.run("document.querySelector('[data-who-remove]').click()")
    title, body = confirm(f, "the remove confirmation")
    assert title == t(f, "ownChoir.together.remove.title", {"name": "Ben", "song": "Silent Night"}), title
    assert "soprano" in body.lower(), body
    shoot(f, "owner-remove")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-who-friend]')", what="Ben gone, the soprano theirs again")

    # Waiting for the owner's minutes: the row says so, and the line says how many more and when they come back.
    tab(f, mockChoirFriend="minutes")
    page(f, wait="document.querySelector('[data-choir-minutes]')")
    assert "Ben" in f.js("document.querySelector('[data-who-part=soprano]').textContent") and f.js("!!document.querySelector('[data-who-part=soprano] [data-ai-voice=tag]')")
    assert "3" in f.js("document.querySelector('[data-choir-minutes]').textContent") and "soprano" in f.js("document.querySelector('[data-choir-minutes]').textContent")
    shoot(f, "owner-minutes")

    # Over the free song's one friend: the invite opens the Plus sheet, with the prices (the alto sits high too, so there's
    # a part left to invite for).
    tab(f, mockChoirFriend="ready", mockChoirFit="two")
    page(f, who="member", wait="document.querySelector('[data-choir-invite]')")
    f.run("document.querySelector('[data-choir-invite]').click()")
    f.wait("document.querySelector('[data-plus-moment][data-moment=choir_friends]')", what="the Plus sheet")
    assert t(f, "plus.moment.choirFriendsUsed", {"name": "Ben"}) in f.js("document.querySelector('[data-plus-moment]').innerText")
    f.wait("document.querySelector('[data-plus-moment] [data-plus-price]')", what="the prices")
    shoot(f, "owner-plus")
    f.press("Escape")

    # The owner deletes the song with a friend in it: the usual styled confirmation, and it's gone.
    page(f, who="plus", wait="document.querySelector('[data-choir-delete]')")
    f.run("document.querySelector('[data-choir-delete]').click()")
    confirm(f, "the delete confirmation")
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-choir-delete]')", what="the song deleted")

    # A new invite's token is 32 characters (the server makes them 24 to 32): the app's route takes it.
    tab(f)
    f.go("/choir/join/" + "a" * 32 + "?api=mock&scenario=&as=signedOut", "document.querySelector('[data-join-title]') || document.querySelector('[data-join-off]')")

    # The friend's join, signed out: who invited them to sing which parts, and an account first, the link kept.
    tab(f)
    f.go("/choir/join/tok-1?api=mock&scenario=&as=signedOut", "document.querySelector('[data-join-title]')")
    title = f.js("document.querySelector('[data-join-title]').textContent.trim()")
    assert "Ann" in title and "Silent Night" in title, title
    f.wait("document.querySelector('[data-account-wall][data-reason=choirJoin]')", what="an account first")
    assert "/choir/join/tok-1" in f.js("decodeURIComponent(document.querySelector('[data-account-wall]').innerHTML)"), "the link isn't kept"
    f.no_sideways("the join, signed out")
    shoot(f, "join-signed-out")

    # Signed in, with a saved voice: three points, Show my name (off), Agree and use my saved voice.
    f.go("/choir/join/tok-1?api=mock&scenario=&as=member", "document.querySelector('[data-join-agree]')")
    assert f.js("document.querySelectorAll('[data-join-agree] li').length") == 3, "three points"
    assert f.js("(document.querySelector('[data-join-show-name][role=switch]') ?? document.querySelector('[data-join-show-name] [role=switch]'))?.getAttribute('aria-checked')") == "false", "Show my name starts off"
    f.no_sideways("the join's yes")
    shoot(f, "join-agree")
    f.run("document.querySelector('[data-join-saved]').click()")
    f.wait("document.querySelector('[data-join-done]')", what="joined: on its way")
    sent = f.js("globalThis.mockChoirJoin")
    assert sent["useSavedVoice"] is True and sent["showName"] is False, sent

    # Afterwards on Home: the quiet ready card, Choirs you sing in, Listen, then Leave in a styled confirmation.
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('[data-joined]')")
    f.wait("document.querySelector('[data-joined-new]') && document.querySelector('[data-joined-listen]')", what="Ready on its row, and Listen")
    assert not f.js("document.querySelector('[data-choir-notice]')?.textContent.includes('Ann')"), "the choir they sing in shows twice"
    shoot(f, "friend-home")
    f.run("document.querySelector('[data-joined]').scrollIntoView({ block: 'start' })")
    shoot(f, "friend-home-joined")
    f.run("(await import('/js/audio.js')).unlock(); document.querySelector('[data-joined-listen]').click()")
    f.wait("(async () => (await import('/js/audio.js')).engine.playing)()", timeout=30, what="the choir playing")
    f.run("document.querySelector('[data-joined-listen]').click()")
    f.run("document.querySelector('[data-joined-leave]').click()")
    title, body = confirm(f, "the leave confirmation")
    assert "Ann" in title, title
    f.run("document.querySelector('[role=dialog] form').requestSubmit()")
    f.wait("!document.querySelector('[data-joined]')", what="left")

    # Tune first (exact consent): a new singer sings the tune (kept on this device), then the yes names exactly the parts a
    # voice like theirs sings, by the octave they sang in; nothing is sent until Agree and send.
    tab(f, mockChoirState="fresh")
    f.go("/choir/join/tok-2?api=mock&scenario=&as=member", "document.querySelector('[data-choir-agree]') || document.querySelector('[data-join-sing-step]')")
    # A new singer meets the own-choir terms (18 and over) once, before singing.
    if f.js("!!document.querySelector('[data-choir-agree]')"):
        shoot(f, "join-terms")
        f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-join-sing-step] [data-join-sing]')", what="sing the tune first")
    assert not f.js("document.querySelector('[data-join-agree]')"), "the yes before singing, without a saved voice"
    assert f.js("!!document.querySelector('[data-join-tune]')"), "the join doesn't say to sing the tune"
    how = t(f, "ownChoir.together.join.how", {"owner": "Ann"})
    assert how not in f.js("document.querySelector('main').innerText"), "the sing step's lead says it twice"
    shoot(f, "join-sing-first")
    f.run("document.querySelector('[data-join-sing]').click()")
    f.wait("/^\\/p\\/[\\w-]+\\/sing$/.test(location.pathname)", what="Sing on the tune")
    project = f.js("location.pathname.split('/')[2]")
    tune = f.js("(async () => (await import('/js/own-choir.js')).tunePart(await (await import('/js/templates.js')).loadTemplate('silent-night')))()")
    join = f.js(f"(await import('/js/own-choir.js')).joinFor({json.dumps(project)})")
    assert join and join["token"] == "tok-2" and join["partKey"] == tune, join
    # As Next does after singing: the take kept on this device (an octave down), and back to the yes. Nothing sent yet.
    f.run(f"""const {{ keepJoinClip }} = await import('/js/own-choir.js');
        keepJoinClip('tok-2', {{ samples: new Float32Array(48000 * 20).map((_, i) => 0.3 * Math.sin(i / 20)), sampleRate: 48000, startSeconds: 0, partKey: {json.dumps(tune)}, octave: -12, projectId: {json.dumps(project)} }});
        (await import('/js/router.js')).navigate('/choir/join/tok-2?sung=1');""")
    f.wait("document.querySelector('[data-join-agree] [data-join-send]')", what="the yes, after singing")
    assert not f.js("globalThis.mockChoirJoin"), "something was sent before Agree"
    point = f.js("document.querySelector('[data-join-agree] li').textContent")
    assert "alto" in point and "soprano" not in point, f"the yes doesn't name exactly their parts: {point}"
    assert f.js("!!document.querySelector('[data-join-local]')"), "the yes doesn't say the take stays on the device"
    found = f.js("document.querySelector('[data-join-found]')?.textContent ?? ''")
    assert "alto" in found and "soprano" in found, f"the yes doesn't say what the singing found: {found}"
    shoot(f, "join-agree-after-singing")
    f.run("document.querySelector('[data-join-send]').click()")
    f.wait("document.querySelector('[data-join-done]')", what="sent: on its way")
    sent = f.js("globalThis.mockChoirJoin")
    assert sent["useSavedVoice"] is False and sent["partKey"] == tune and sent["octave"] == -12 and sent["parts"] == ["alto"] and len(sent["sha256"]) == 64, sent
    assert not f.js("globalThis.mockChoirClip"), "the join's 30 seconds went to a song of their own"
    assert not f.js("(async () => (await import('/js/own-choir.js')).joinClipFor('tok-2'))()"), "the take kept on the device after sending"
    clear_projects(f)

    # In Spanish: the owner's Who sings, the join's yes, and the friend's Home.
    tab(f, mockChoirFriend="ready")
    f.go("/t/silent-night/choir?api=mock&scenario=&as=plus&lang=es", "document.querySelector('[data-choir-who]')")
    assert t(f, "ownChoir.together.who.title") in f.js("document.querySelector('[data-choir-who]').textContent")
    f.no_sideways("Who sings, in Spanish")
    shoot(f, "es-owner")
    tab(f, mockChoirJoined="ready")
    f.go("/choir/join/tok-3?api=mock&scenario=&as=member&lang=es", "document.querySelector('[data-join-agree]') || document.querySelector('[data-join-done]')")
    f.no_sideways("the join, in Spanish")
    shoot(f, "es-join")
    # In Spanish too, tune first: the sing step, then the yes after singing an octave down.
    tab(f, mockChoirState="fresh")
    f.go("/choir/join/tok-4?api=mock&scenario=&as=member&lang=es", "document.querySelector('[data-choir-agree]') || document.querySelector('[data-join-sing-step]')")
    if f.js("!!document.querySelector('[data-choir-agree]')"):
        f.run("document.querySelector('[data-choir-agree]').click()")
    f.wait("document.querySelector('[data-join-sing-step]')", what="the sing step, in Spanish")
    shoot(f, "es-join-sing-first")
    # Leaving the join discards a take, so: away, then the take kept (as Next does from Sing), then back.
    f.run(f"""const {{ navigate }} = await import('/js/router.js'); navigate('/'); await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));
        const {{ keepJoinClip, tunePart }} = await import('/js/own-choir.js'); const {{ loadTemplate }} = await import('/js/templates.js');
        keepJoinClip('tok-4', {{ samples: new Float32Array(48000 * 20), sampleRate: 48000, startSeconds: 0, partKey: tunePart(await loadTemplate('silent-night')), octave: -12, projectId: null }});
        (await import('/js/router.js')).navigate('/choir/join/tok-4?sung=1');""")
    f.wait("document.querySelector('[data-join-found]')", what="what the singing found, in Spanish")
    f.no_sideways("the yes after singing, in Spanish")
    shoot(f, "es-join-agree-after-singing")
    tab(f, mockChoirJoined="ready")
    f.go("/?api=mock&scenario=&as=member&lang=es", "document.querySelector('[data-joined]')")
    f.no_sideways("Home, in Spanish")
    shoot(f, "es-friend-home")
    tab(f, together=False)


run("own_choir_together", journey)
