// How the app looks (docs/decisions.md, "themes"): Mode (Light, Dark, or Match my device) and
// Colour (Stage, Warm, Midnight or Graphite, each named by mode: Stage and Spotlight, Ember and Warm,
// Midnight and Sky, Graphite and Paper), any with any. Plum went (Hillel, 2026-10-27): a choice of it
// shows as the mode's own colour. Kept on this device (js/theme-boot.js
// applies it before the page paints) and, signed in, on the account, so a new device picks it up.
// Until the person chooses, the app is dark, and a colour not chosen is the mode's own (Stage in
// dark, Warm in light).

import { reactive } from 'vue';
import { api } from '../api.js';
import { local } from '../storage.js';
import { account } from './account.js';
import { count } from '../usage.js';

export const MODES = ['light', 'dark', 'system'];
export const FAMILIES = ['stage', 'warm', 'midnight', 'graphite'];
/** The colour of each mode until one is chosen (docs/decisions.md, "themes and songs, adjusted"). */
export const DEFAULT_COLOUR = { dark: 'stage', light: 'warm' };
const KEY = 'stackapella.appearance';

/** The choice the boot script applied (this address's, or the other host's if newer), or null. */
function stored() {
    const choice = window.stackapellaTheme?.chosen?.();
    if (choice?.mode || choice?.colour) return choice;
    try {
        return JSON.parse(local.get(KEY) || 'null');
    } catch (error) {
        console.warn('The remembered look was unreadable', error);
        return null;
    }
}

/**
 * `mode`, `colour` and `accent` as chosen (null before a choice; a null accent is the palette's own),
 * `theme` the one showing ("warm-dark"), `version` bumped on each change.
 */
export const appearance = reactive({ mode: stored()?.mode ?? null, gameMode: stored()?.gameMode ?? null, colour: stored()?.colour ?? null, accent: stored()?.accent ?? null, customAccent: stored()?.customAccent ?? null, theme: document.documentElement.dataset.theme || 'warm-dark', skin: document.documentElement.dataset.skin === 'serious' ? 'serious' : 'game', version: 0 });

// The boot script applies every change (and the system's, in Match my device): canvases redraw.
document.addEventListener('stackapella:theme', (event) => {
    appearance.theme = event.detail;
    appearance.skin = document.documentElement.dataset.skin === 'serious' ? 'serious' : 'game';
    appearance.gameMode = window.stackapellaTheme?.chosen?.().gameMode ?? null;
    appearance.version++;
});

/**
 * Game mode (docs/skins-plan.md; Hillel's "two skins for one app"): the arcade look and the game
 * rewards (XP, stars, badges, combos, boards). Off, the serious skin: a plain practice tool. What's
 * earned is kept either way.
 */
export const game = () => appearance.skin !== 'serious';

/** Game mode on or off: shown at once (the boot script sets <html data-skin>), kept on this device and, signed in, on the account. */
export async function setSkin(skin) {
    const was = appearance.skin;
    appearance.skin = skin === 'serious' ? 'serious' : 'game';
    if (appearance.skin !== was) count(appearance.skin === 'game' ? 'game_on' : 'game_off');
    if (window.stackapellaTheme?.choose) window.stackapellaTheme.choose({ skin: appearance.skin });
    else local.set(KEY, JSON.stringify({ ...(stored() ?? {}), skin: appearance.skin, at: Date.now() }));
    if (!account.me) return;
    saving++;
    try {
        account.me = (await api.account.update({ appearance: { skin: appearance.skin } })).data;
    } catch (error) {
        console.warn('Game mode was not saved to the account', error); // kept here; sent with the next choice
    } finally {
        saving--;
    }
}

let saving = 0; // choices on their way to the account

/**
 * Game mode's own light or dark (dark until chosen): kept on this device, apart from serious mode's,
 * so turning game mode on is dark again unless light was chosen in it (Hillel: "auto set to dark but
 * show toggle to return to light").
 */
export function setGameMode(mode) {
    appearance.gameMode = mode;
    if (window.stackapellaTheme?.choose) window.stackapellaTheme.choose({ gameMode: mode });
    else local.set(KEY, JSON.stringify({ ...(stored() ?? {}), gameMode: mode, at: Date.now() }));
}

/** The light or dark chosen for the skin showing: game mode's own, or serious mode's. */
export const modeChosen = () => (game() ? appearance.gameMode ?? 'dark' : appearance.mode ?? 'dark');

/** The mode showing now (Match my device resolved). */
export const shownMode = () => (appearance.theme.endsWith('-light') ? 'light' : 'dark');

/** The colour showing now (the chosen one, or the mode's own). */
export const shownColour = () => appearance.theme.split('-')[0];

/**
 * Chooses a mode, colour or accent: shown at once, kept here, and sent to the account. A colour
 * left unchosen (null) stays the mode's own; a null accent is the palette's own.
 */
export async function setAppearance({ mode = appearance.mode ?? 'dark', colour = appearance.colour, accent = appearance.accent, customAccent = appearance.customAccent } = {}) {
    // A custom accent (accent 'custom') carries its colour; any other choice drops it.
    const custom = accent === 'custom' ? customAccent ?? null : null;
    Object.assign(appearance, { mode, colour: colour ?? null, accent: accent ?? null, customAccent: custom });
    // Kept here and shared with Stackapella's other host by the boot script (one writer for every page).
    // Game mode's own light or dark goes along unchanged (theme-boot.js gives a lone `mode` to the skin showing).
    const choice = { mode, gameMode: appearance.gameMode, colour: colour ?? null, accent: accent ?? null, customAccent: custom };
    if (window.stackapellaTheme?.choose) window.stackapellaTheme.choose(choice);
    else local.set(KEY, JSON.stringify({ ...choice, at: Date.now() }));
    if (!account.me) return;
    saving++;
    try {
        account.me = (await api.account.update({ appearance: { mode, ...(colour ? { colour } : {}), accent: accent ?? null, ...(custom ? { customAccent: custom } : {}) } })).data;
    } catch (error) {
        console.warn('The look was not saved to the account', error); // kept here; sent with the next choice
    } finally {
        saving--;
    }
}

/**
 * Signed in: the account's choice wins (Hillel's "the theme follows you"), and goes into this
 * device's choice and the shared cookie, so the public pages follow too. The account's default
 * (nothing chosen yet) changes nothing.
 */
export function adoptAccountAppearance(theirs) {
    // null until chosen (contract/api.md, account.show's `appearance`).
    if (!theirs || saving) return; // an answer to this device's own save, with another choice on its way
    const mine = stored() ?? {};
    // Game mode follows the account too (an appearance saved before it reads as game).
    const skin = theirs.skin === 'serious' ? 'serious' : 'game';
    if (skin !== appearance.skin) {
        appearance.skin = skin;
        window.stackapellaTheme?.choose?.({ skin });
    }
    if (!theirs.mode) return; // only the skin chosen on the account: this device's look stays
    const same = (mine.mode ?? null) === (theirs.mode ?? null) && (mine.colour ?? null) === (theirs.colour ?? null) && (mine.accent ?? null) === (theirs.accent ?? null) && (mine.customAccent ?? null) === (theirs.customAccent ?? null);
    if (same) return;
    // The account's look is serious mode's; game mode's own light or dark stays this device's.
    const choice = { mode: theirs.mode, gameMode: mine.gameMode ?? null, colour: theirs.colour ?? null, accent: theirs.accent ?? null, customAccent: theirs.accent === 'custom' ? theirs.customAccent ?? null : null };
    Object.assign(appearance, choice);
    if (window.stackapellaTheme?.choose) window.stackapellaTheme.choose(choice);
    else local.set(KEY, JSON.stringify({ ...choice, at: Date.now() }));
}
