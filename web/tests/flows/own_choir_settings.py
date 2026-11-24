"""Choir settings on the ready choir page (the coordinator for Hillel, 2026-11-22): everyone sees it; without Plus every
control opens the Plus sheet (choir_settings) and changes nothing. With Plus: Sound is free and instant (a room and each
part's balance, kept with requests.sound and heard through the app's mix), How it's sung is a remake (feel, voicing,
breaths, timing and pitch between singers, quality) with Remake my choir saying the exact minutes for the quality
chosen; while it's remade, the current version keeps playing and the panel says so.
"""
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from flow import run  # noqa: E402
from help_shots import clear_projects  # noqa: E402

PAGE = "/t/silent-night/choir?api=mock&scenario=&as={who}"


def t(f, key, params=None):
    return f.js(f"(await import('/js/i18n.js')).t({json.dumps(key)}, {json.dumps(params or {})})")


def journey(f):
    f.go("/?api=mock&scenario=&as=member", "document.querySelector('h1')")
    f.run("sessionStorage.setItem('stackapella.mockOwnChoir', '1'); sessionStorage.removeItem('stackapella.mockChoirState'); sessionStorage.removeItem('stackapella.mockChoir');")
    f.run("const p = JSON.parse(localStorage.getItem('stackapella.singPrefs') || '{}'); delete p.choirRoom; localStorage.setItem('stackapella.singPrefs', JSON.stringify(p));")
    clear_projects(f)

    # Free: the panel shows, and a tap opens the Plus sheet; nothing is kept.
    f.go(PAGE.format(who="member"), "document.querySelector('[data-choir-settings][data-plus=no]')")
    assert f.js("!!document.querySelector('[data-settings-plus-badge]') && !!document.querySelector('[data-settings-locked]')"), "the locked state isn't shown"
    f.run("document.querySelector('[data-choir-settings]').scrollIntoView({ block: 'start' })")
    f.shot("settings-locked")
    f.run("document.querySelector('[data-settings-room] [role=radio]:nth-child(4)').click()")
    f.wait("document.querySelector('[data-plus-moment][data-moment=choir_settings]')", what="the Plus sheet")
    assert t(f, "plus.moment.choirSettings") in f.js("document.querySelector('[data-plus-moment]').innerText")
    assert not f.js("globalThis.mockChoirSound"), "a free account's sound was kept"
    f.press("Escape")

    # Plus: the room at once (kept), a part's balance, then a remake at Fine quality with its exact minutes.
    f.go(PAGE.format(who="plus"), "document.querySelector('[data-choir-settings][data-plus=yes]')")
    # Nothing changed yet: Remake waits, and says so.
    assert f.js("document.querySelector('[data-remake]').disabled"), "Remake is open with nothing changed"
    assert f.js("document.querySelector('[data-remake-cost]').textContent.trim()") == t(f, "ownChoir.settings.remakeNeedsChange")
    assert not f.js("document.querySelector('[data-settings-reset]')"), "Back to the defaults shows with nothing changed"
    # Small room by default (Hillel, 2026-11-24), nothing picked yet on this device.
    assert f.js("document.querySelector('[data-settings-room] [role=radio]:nth-child(2)').getAttribute('aria-checked')") == "true", "Small room isn't the default"
    f.run("document.querySelector('[data-settings-room] [role=radio]:nth-child(4)').click()")
    f.wait("globalThis.mockChoirSound?.room === 'chapel'", what="the room kept")
    # Remembered: a choir with no room of its own now opens in the room they picked last.
    assert f.js("JSON.parse(localStorage.getItem('stackapella.singPrefs') || '{}').choirRoom") == "chapel", "the room wasn't remembered"
    f.run("(async () => { const own = await import('/js/own-choir.js'); for (const r of own.ownChoir.data.requests) if (r.status === 'ready') r.settings = { ...(r.settings ?? {}), soundSaved: false, sound: { room: 'small', balance: [] } }; })()")
    f.wait("document.querySelector('[data-settings-room] [role=radio]:nth-child(4)').getAttribute('aria-checked') === 'true'", what="a choir with no room of its own in the room they picked last")
    assert f.js("document.querySelector('[data-settings-room] [role=radio]:nth-child(4)').getAttribute('aria-checked')") == "true"
    f.run("""const input = document.querySelector('[data-balance=alto] input'); input.value = '-6'; input.dispatchEvent(new Event('change'));""")
    f.wait("globalThis.mockChoirSound?.balance?.find((b) => b.partKey === 'alto')?.db === -6", what="the alto's balance kept")
    # Heard through the app's mix: a shaped sound renders one stereo part.
    shaped = f.js("(async () => (await import('/js/own-choir-player.js')).shaped(globalThis.mockChoirSound))()")
    assert shaped is True, "the sound isn't shaped"
    f.run("document.querySelector('[data-sung=quality] [role=radio]:nth-child(2)').click()")
    fine = f.js("document.querySelector('[data-remake-cost]').textContent.trim()")
    left = f.js("(async () => String(Math.floor((await import('/js/own-choir.js')).ownChoir.data.quota.minutesLeft)))()")
    minutes = f.js("(async () => (await import('/js/own-choir.js')).ownChoir.data.requests.find((r) => r.templateId === 'silent-night').remakeMinutes.fine)()")
    assert fine == t(f, "ownChoir.settings.remakeOf", {"minutes": minutes, "left": left}), fine
    # Breaths, timing and pitch sit under the fold (Hillel's Choir Result design): opened, then pitch set wide.
    assert not f.js("document.querySelector('[data-sung=pitchSpread]')"), "fine-tuning shown before the fold is opened"
    f.run("document.querySelector('[data-settings-fine]').click()")
    f.wait("document.querySelector('[data-sung=pitchSpread]')", what="the fine-tuning")
    f.run("document.querySelector('[data-sung=pitchSpread] [role=radio]:nth-child(3)').click()")
    # Something changed: the remake bar sits at the foot of the screen, with Undo.
    assert f.js("document.querySelector('[data-remake-bar]').hasAttribute('data-changed') && !!document.querySelector('[data-remake-undo]')"), "the remake bar isn't the changed one" 
    # Vibrato and Natural variation in place of Humanize (Hillel via the coordinator), both at Natural unset.
    assert not f.js("document.querySelector('[data-humanize]')"), "the old Humanize slider is back"
    for name, natural in (("vibrato", 3), ("variation", 2)):
        assert f.js(f"document.querySelector('[data-sung={name}] [role=radio]:nth-child({natural})').getAttribute('aria-checked')") == "true", f"{name} isn't Natural"
    f.run("document.querySelector('[data-sung=vibrato] [role=radio]:nth-child(4)').click()")
    f.run("document.querySelector('[data-sung=variation] [role=radio]:nth-child(1)').click()")
    f.run("document.querySelector('[data-remake]').scrollIntoView({ block: 'end' })")
    f.no_sideways("Choir settings")
    f.shot("settings")
    f.run("document.querySelector('[data-remake]').click()")
    f.wait("document.querySelector('[data-remaking]')", what="remaking, the old version still playing")
    sung = f.js("globalThis.mockChoirRemake")
    assert sung["quality"] == "fine" and sung["pitchSpread"] == "wide", sung
    assert sung["vibrato"] == "strong" and sung["variation"] == "steady" and "humanize" not in sung, sung
    assert f.js("!!document.querySelector('[data-choir-play]')"), "the current version can't be played while remaking"
    f.shot("remaking")
    # A song whose words aren't English: its AI parts are sung on "oo", and the page says so (and not otherwise).
    assert not f.js("document.querySelector('[data-choir-on-oo]')"), "sung on oo for an English song"
    f.run("sessionStorage.setItem('stackapella.mockSongLanguage', 'de'); (await import('/js/templates.js')).forgetTemplates?.()")
    f.go(PAGE.format(who="plus"), "document.querySelector('[data-choir-track]')")
    f.wait("document.querySelector('[data-choir-on-oo]')", what="sung on oo")
    f.run("sessionStorage.removeItem('stackapella.mockSongLanguage')")
    # In Spanish, the panel (a phone look in both skins).
    if f.size == "phone":
        f.go(PAGE.format(who="plus") + "&lang=es", "document.querySelector('[data-choir-settings]')")
        f.run("document.querySelector('[data-choir-settings]').scrollIntoView({ block: 'start' })")
        f.no_sideways("Choir settings in Spanish")
        f.shot("settings-es")
    clear_projects(f)


run("own_choir_settings", journey)
