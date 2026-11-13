// What a statement of reasons says, in the app's own words (views/notices.js, admin/appeals.js): a title and a sentence for
// each kind of notice, a sentence for each rule (server/lang/en/notices.php's, so the email and the app agree). The
// server's `what` and `why` are its English, the fallback for a kind or rule added later.
// LAWYER TO REVIEW: these words are a draft (views/notices.js DRAFT).

import { tk } from './i18n.js';

// The app's own words for each kind and rule (the server's `what` and `why` are its English, a fallback).
export const KINDS = {
    harmony_hidden: { title: tk('notices.kind.harmony_hidden'), what: tk('notices.what.harmony_hidden') },
    harmony_removed: { title: tk('notices.kind.harmony_removed'), what: tk('notices.what.harmony_removed') },
    account_limited: { title: tk('notices.kind.account_limited'), what: tk('notices.what.account_limited') },
    account_suspended: { title: tk('notices.kind.account_suspended'), what: tk('notices.what.account_suspended') },
    coach_paused: { title: tk('notices.kind.coach_paused'), what: tk('notices.what.coach_paused') },
    coach_suspended: { title: tk('notices.kind.coach_suspended'), what: tk('notices.what.coach_suspended') },
    comment_hidden: { title: tk('notices.kind.comment_hidden'), what: tk('notices.what.comment_hidden') },
    choir_voice_removed: { title: tk('notices.kind.choir_voice_removed'), what: tk('notices.what.choir_voice_removed') },
    idea_hidden: { title: tk('notices.kind.idea_hidden'), what: tk('notices.what.idea_hidden') },
    idea_voice_hidden: { title: tk('notices.kind.idea_voice_hidden'), what: tk('notices.what.idea_voice_hidden') },
    template_hidden: { title: tk('notices.kind.template_hidden'), what: tk('notices.what.template_hidden') },
};
export const RULES = {
    community_guidelines: tk('notices.why.community_guidelines'),
    copyright: tk('notices.why.copyright'),
    reports: tk('notices.why.reports'),
    terms: tk('notices.why.terms'),
    coach_agreement: tk('notices.why.coach_agreement'),
};
