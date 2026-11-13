// Be the voice's send (js/be-the-voice.js, components/share-voice.js): a take already saved to the account (the ladder's
// share rung comes only once it is) offered as its part's guide voice, by its take id, with the agreement's version and
// the credit chosen and "I'm 18 or over". Nothing uploads twice: the server has the take (docs/open-guide-voices-plan.md,
// voices.share; it replaces voices.join: the acceptance is recorded with the share).

import { api } from './api.js';

/** Offers `part`'s current take (a session part, saved to the account) as the guide for its part of `project`'s song. */
export async function sendVoiceTake(part, { project, credit, agreementVersion, adult }) {
    if (!part?.takeId || part.syncedTakeId !== part.takeId) throw new Error('The take is not saved to the account yet');
    return api.voices.share({
        takeId: part.takeId,
        templateId: project.template.id,
        templateVersion: project.template.version,
        partKey: part.templatePartKey,
        credit,
        linksProfile: false,
        agreementVersion,
        adult, // "I'm 18 or over", ticked in the sheet
    });
}
