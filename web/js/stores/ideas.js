// Ideas: a tune shared for real voices (docs/ideas-app-plan.md; contract/api.md, "Ideas"). Behind
// the server's `ideas` flag, which auth.config says (`ideas`): closed, or an older server that doesn't
// say, the app shows no way in. Never asked of ideas.list itself: while closed it answers 404, which
// the browser logs as an error on every page that asked.

import { reactive } from 'vue';
import { api } from '../api.js';
import { loadAuthConfig } from './account.js';

export const ideas = reactive({ open: null }); // null until the settings are in; then true or false

let asking = null;

/** Whether Ideas is open, from auth.config (a failed load, offline say, is asked again later). */
export function ideasOpen() {
    asking ??= loadAuthConfig()
        .then((config) => (ideas.open = config?.ideas === true))
        .catch((error) => {
            console.info('Ideas could not be checked', error?.message ?? error);
            asking = null;
            return false;
        });
    return asking;
}

/** The shelf: `tab` 'open' or 'complete', `needs` a part key, `cursor` for the next page. */
export async function listIdeas({ tab = 'open', needs = null, cursor = null } = {}) {
    return api.ideas.list({ query: { tab, needs: needs || undefined, cursor: cursor || undefined } });
}

/** The signed-in person's own ideas, held and hidden ones too. */
export async function myIdeas({ cursor = null } = {}) {
    return api.ideas.mine({ query: { cursor: cursor || undefined } });
}

export async function showIdea(id) {
    return (await api.ideas.show({ params: { id } })).data;
}

export async function deleteIdea(id) {
    await api.ideas.delete({ params: { id } });
}

export async function deleteVoice(id) {
    await api.ideas.voices.delete({ params: { id } });
}
