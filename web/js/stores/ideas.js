// Ideas: a tune shared for real voices (docs/ideas-app-plan.md; contract/api.md, "Ideas"). Behind
// the server's `ideas` flag, which `auth.config` doesn't name: closed, `ideas.list` answers 404, so
// the app asks it once a session and shows no way in until it's open.

import { reactive } from 'vue';
import { api, ApiError } from '../api.js';

export const ideas = reactive({ open: null }); // null until asked; then true or false

let asking = null;

/** Whether Ideas is open: asked once a session (a failed ask, offline say, is asked again later). */
export function ideasOpen() {
    asking ??= api.ideas
        .list({ query: { tab: 'open' } })
        .then(() => (ideas.open = true))
        .catch((error) => {
            if (error instanceof ApiError && error.status === 404) return (ideas.open = false);
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
