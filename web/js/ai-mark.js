// The machine-readable marking for a file holding an AI-made voice (EU AI Act Article 50): the one wording, from
// auth.config's `aiMark` ({ text, generator, digitalSourceType }), so the app never words it itself. Null until the
// settings say it (the server marks what it serves either way).

import { account } from './stores/account.js';

/** The marking to write into an export that holds an AI voice, or null. */
export function aiMark() {
    const mark = account.config?.aiMark;
    return mark?.text ? { text: mark.text, generator: mark.generator ?? 'Stackapella', digitalSourceType: mark.digitalSourceType ?? null } : null;
}
