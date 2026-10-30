// The only module that calls /api (docs/parallel-agents-plan.md, "Testing the contract from both
// sides"). One function per contract operation, named after it (`api.admin.flags.update`), and the
// contract's general rules in one place (contract/api.md, "The general rules"):
// - JSON both ways, with `Accept` and `X-App-Version` on every call;
// - the session cookie, and Laravel's CSRF token (the `XSRF-TOKEN` cookie, sent back as
//   `X-XSRF-TOKEN`) on every change; a 419 refreshes it with a GET and retries once;
// - a refusal becomes an ApiError with the contract's stable `code`, which the screens word.
//
// `?api=mock` on this machine answers from the contract's own examples (api-mock.js) instead of a
// server, so screens are built and tested against the same JSON the backend is checked against.

import { mockFetch, mockWanted } from './api-mock.js';

/** The app's version, sent as X-App-Version; the server answers 426 when it's too old. */
export const APP_VERSION = '2026.10.26';

/** Each operation's method and path, as in contract/api.md's "Operations" table (a test compares them). */
export const OPERATIONS = {
    'admin.me': ['GET', '/api/admin/me'],
    'admin.flags.list': ['GET', '/api/admin/flags'],
    'admin.flags.update': ['PUT', '/api/admin/flags/{name}'],
    'admin.limits.list': ['GET', '/api/admin/limits'],
    'admin.limits.update': ['PUT', '/api/admin/limits/{limiter}/{rule}'],
    'admin.blocklist.list': ['GET', '/api/admin/blocklist'],
    'admin.blocklist.create': ['POST', '/api/admin/blocklist'],
    'admin.blocklist.delete': ['DELETE', '/api/admin/blocklist/{id}'],
    'admin.securityEvents.list': ['GET', '/api/admin/security-events'],
    'admin.audit.list': ['GET', '/api/admin/audit'],
    // M2: the catalogue and the beta singers.
    'templates.catalogue': ['GET', '/api/templates'],
    'beta.invite.show': ['GET', '/api/beta/invites/{token}'],
    'beta.invite.accept': ['POST', '/api/beta/invites/{token}/accept'],
    'beta.me.show': ['GET', '/api/beta/me'],
    'beta.agreement.show': ['GET', '/api/beta/agreement'],
    'beta.consent.create': ['POST', '/api/beta/consent'],
    'beta.credit.update': ['PUT', '/api/beta/credit'],
    'beta.parts.list': ['GET', '/api/beta/parts'],
    // M6: sharing (contract/api.md, "Sharing: harmonies, videos and reports")
    'harmonies.create': ['POST', '/api/harmonies'],
    'harmonies.complete': ['POST', '/api/harmonies/{id}/complete'],
    'harmonies.list': ['GET', '/api/harmonies'],
    'harmonies.show': ['GET', '/api/harmonies/{id}'],
    'harmonies.update': ['PATCH', '/api/harmonies/{id}'],
    'harmonies.delete': ['DELETE', '/api/harmonies/{id}'],
    'videos.create': ['POST', '/api/harmonies/{id}/videos'],
    'videos.show': ['GET', '/api/videos/{id}'],
    'reports.create': ['POST', '/api/reports'],
    'admin.reports.list': ['GET', '/api/admin/reports'],
    'admin.harmonies.hide': ['POST', '/api/admin/harmonies/{id}/hide'],
    'admin.harmonies.restore': ['POST', '/api/admin/harmonies/{id}/restore'],
    'admin.harmonies.remove': ['POST', '/api/admin/harmonies/{id}/remove'],
    // M9: Event Schedule together (contract/api.md, "Event Schedule together")
    'eventSchedule.connection.show': ['GET', '/api/event-schedule/connection'],
    'eventSchedule.connect.create': ['POST', '/api/event-schedule/connect'],
    'eventSchedule.connect.finish': ['POST', '/api/event-schedule/connect/finish'],
    'eventSchedule.connect.key': ['POST', '/api/event-schedule/connect/key'],
    'eventSchedule.connection.update': ['PATCH', '/api/event-schedule/connection'],
    'eventSchedule.connection.delete': ['DELETE', '/api/event-schedule/connection'],
    'eventSchedule.schedules.list': ['GET', '/api/event-schedule/schedules'],
    'eventSchedule.types.list': ['GET', '/api/event-schedule/appointment-types'],
    'coaches.slots': ['GET', '/api/coaches/{handle}/offerings/{id}/slots'],
    'groups.eventSchedule.update': ['PUT', '/api/groups/{id}/event-schedule'],
    'groups.eventSchedule.delete': ['DELETE', '/api/groups/{id}/event-schedule'],
    'groups.eventSchedule.members': ['GET', '/api/groups/{id}/event-schedule/members'],
    'groups.rehearsals.list': ['GET', '/api/groups/{id}/rehearsals'],
    'groups.rehearsals.songs': ['PUT', '/api/groups/{id}/rehearsals/{event}/songs'],
    'admin.eventSchedule.show': ['GET', '/api/admin/event-schedule'],
    // M8: coaching, without the directory (contract/api.md, "Coaching, without the directory")
    'coach.application.show': ['GET', '/api/coach/application'],
    'coach.application.create': ['POST', '/api/coach/application'],
    'coach.profile.show': ['GET', '/api/coach/profile'],
    'coach.profile.update': ['PATCH', '/api/coach/profile'],
    'coach.connect.create': ['POST', '/api/coach/connect'],
    'coach.connect.show': ['GET', '/api/coach/connect'],
    'coach.offerings.list': ['GET', '/api/coach/offerings'],
    'coach.offerings.create': ['POST', '/api/coach/offerings'],
    'coach.offerings.update': ['PATCH', '/api/coach/offerings/{id}'],
    'coach.packs.list': ['GET', '/api/coach/packs'],
    'coach.packs.save': ['PUT', '/api/coach/packs/{id}'],
    'coach.packs.submit': ['POST', '/api/coach/packs/{id}/submit'],
    'coaches.show': ['GET', '/api/coaches/{handle}'],
    'engagements.create': ['POST', '/api/engagements'],
    'engagements.list': ['GET', '/api/engagements'],
    'engagements.show': ['GET', '/api/engagements/{id}'],
    'engagements.accept': ['POST', '/api/engagements/{id}/accept'],
    'engagements.decline': ['POST', '/api/engagements/{id}/decline'],
    'engagements.cancel': ['POST', '/api/engagements/{id}/cancel'],
    'engagements.report': ['POST', '/api/engagements/{id}/report'],
    'reviews.create': ['POST', '/api/engagements/{id}/reviews'],
    'reviews.save': ['PUT', '/api/reviews/{id}'],
    'reviews.deliver': ['POST', '/api/reviews/{id}/deliver'],
    'reviews.unsend': ['POST', '/api/reviews/{id}/unsend'],
    'reviews.followup': ['POST', '/api/reviews/{id}/follow-up'],
    'reviews.answer': ['POST', '/api/reviews/{id}/answer'],
    'reviews.media.create': ['POST', '/api/reviews/{id}/media'],
    'reviews.media.complete': ['POST', '/api/review-media/{id}/complete'],
    'messages.list': ['GET', '/api/engagements/{id}/messages'],
    'messages.create': ['POST', '/api/engagements/{id}/messages'],
    'ratings.create': ['POST', '/api/engagements/{id}/rating'],
    'ratings.reply': ['POST', '/api/ratings/{id}/reply'],
    'packs.mine': ['GET', '/api/packs/mine'],
    'teacher.links.create': ['POST', '/api/teacher-links'],
    'teacher.links.show': ['GET', '/api/teacher/{token}'],
    'teacher.links.accept': ['POST', '/api/teacher/{token}'],
    'admin.coaching.applications.list': ['GET', '/api/admin/coaching/applications'],
    'admin.coaching.applications.decide': ['POST', '/api/admin/coaching/applications/{id}'],
    'admin.coaching.coaches.update': ['PUT', '/api/admin/coaching/coaches/{id}'],
    'admin.coaching.packs.list': ['GET', '/api/admin/coaching/packs'],
    'admin.coaching.packs.decide': ['POST', '/api/admin/coaching/packs/{id}'],
    'admin.coaching.disputes.list': ['GET', '/api/admin/coaching/disputes'],
    'admin.coaching.refund': ['POST', '/api/admin/coaching/engagements/{id}/refund'],
    // M7: making your own, together (contract/api.md, "Making your own, together")
    'templates.mine': ['GET', '/api/templates/mine'],
    'templates.save': ['PUT', '/api/templates/{id}'],
    'templates.update': ['PATCH', '/api/templates/{id}'],
    'templates.delete': ['DELETE', '/api/templates/{id}'],
    'templates.recordings.create': ['POST', '/api/templates/{id}/recordings'],
    'templates.recordings.complete': ['POST', '/api/template-recordings/{id}/complete'],
    'templates.recordings.delete': ['DELETE', '/api/template-recordings/{id}'],
    'projects.shared': ['GET', '/api/projects/shared'],
    'projects.members.list': ['GET', '/api/projects/{id}/members'],
    'projects.members.delete': ['DELETE', '/api/projects/{id}/members/{memberId}'],
    'invites.create': ['POST', '/api/projects/{id}/invites'],
    'invites.list': ['GET', '/api/projects/{id}/invites'],
    'invites.revoke': ['DELETE', '/api/invites/{id}'],
    'invites.show': ['GET', '/api/join/{token}'],
    'invites.accept': ['POST', '/api/join/{token}'],
    'groups.list': ['GET', '/api/groups'],
    'groups.create': ['POST', '/api/groups'],
    'groups.show': ['GET', '/api/groups/{id}'],
    'groups.update': ['PATCH', '/api/groups/{id}'],
    'groups.delete': ['DELETE', '/api/groups/{id}'],
    'groups.link.create': ['POST', '/api/groups/{id}/link'],
    'groups.join.show': ['GET', '/api/choir/{token}'],
    'groups.join': ['POST', '/api/choir/{token}'],
    'groups.members.delete': ['DELETE', '/api/groups/{id}/members/{memberId}'],
    'groups.songs.create': ['POST', '/api/groups/{id}/songs'],
    'groups.songs.delete': ['DELETE', '/api/groups/{id}/songs/{templateId}'],
    'groups.assignments.update': ['PUT', '/api/groups/{id}/songs/{templateId}/assignments'],
    'beta.submissions.create': ['POST', '/api/beta/submissions'],
    'beta.submissions.complete': ['POST', '/api/beta/submissions/{id}/complete'],
    'beta.feedback.create': ['POST', '/api/beta/feedback'],
    'admin.overview': ['GET', '/api/admin/overview'],
    'admin.beta.singers.list': ['GET', '/api/admin/beta/singers'],
    'admin.beta.singers.create': ['POST', '/api/admin/beta/singers'],
    'admin.beta.singers.update': ['PATCH', '/api/admin/beta/singers/{id}'],
    'admin.beta.singers.link': ['POST', '/api/admin/beta/singers/{id}/link'],
    'admin.beta.singers.revoke': ['POST', '/api/admin/beta/singers/{id}/revoke'],
    'admin.beta.singers.paid': ['POST', '/api/admin/beta/singers/{id}/paid'],
    'admin.beta.singers.forget': ['POST', '/api/admin/beta/singers/{id}/forget'],
    'admin.beta.assignments.create': ['POST', '/api/admin/beta/singers/{id}/assignments'],
    'admin.beta.assignments.delete': ['DELETE', '/api/admin/beta/assignments/{id}'],
    'admin.beta.submissions.list': ['GET', '/api/admin/beta/submissions'],
    'admin.beta.submissions.show': ['GET', '/api/admin/beta/submissions/{id}'],
    'admin.beta.submissions.open': ['POST', '/api/admin/beta/submissions/{id}/open'],
    'admin.beta.submissions.adjust': ['PATCH', '/api/admin/beta/submissions/{id}'],
    'admin.beta.submissions.accept': ['POST', '/api/admin/beta/submissions/{id}/accept'],
    'admin.beta.submissions.retake': ['POST', '/api/admin/beta/submissions/{id}/retake'],
    'admin.beta.submissions.reject': ['POST', '/api/admin/beta/submissions/{id}/reject'],
    'admin.beta.submissions.publish': ['POST', '/api/admin/beta/submissions/{id}/publish'],
    'admin.beta.guides.list': ['GET', '/api/admin/beta/guides'],
    'admin.beta.guides.default': ['POST', '/api/admin/beta/guides/{id}/default'],
    'admin.beta.guides.delete': ['DELETE', '/api/admin/beta/guides/{id}'],
    'admin.beta.feedback.list': ['GET', '/api/admin/beta/feedback'],
    'admin.beta.feedback.read': ['POST', '/api/admin/beta/feedback/{id}/read'],
    // M3: accounts and sign-in, and the admin's People.
    'admin.people.list': ['GET', '/api/admin/people'],
    'admin.people.countries': ['GET', '/api/admin/people/countries'],
    'counts.create': ['POST', '/api/counts'],
    'admin.counts.show': ['GET', '/api/admin/counts'],
    'admin.funnel.show': ['GET', '/api/admin/funnel'],
    'admin.people.show': ['GET', '/api/admin/people/{id}'],
    'admin.people.suspend': ['POST', '/api/admin/people/{id}/suspend'],
    'admin.people.unsuspend': ['POST', '/api/admin/people/{id}/unsuspend'],
    'admin.people.trust.update': ['PUT', '/api/admin/people/{id}/trust'],
    'admin.people.recover': ['POST', '/api/admin/people/{id}/recover'],
    'auth.config': ['GET', '/api/auth/config'],
    'auth.register': ['POST', '/api/auth/register'],
    'auth.verify': ['POST', '/api/auth/verify'],
    'auth.verification.resend': ['POST', '/api/auth/verification'],
    'auth.signin': ['POST', '/api/auth/sign-in'],
    'auth.signout': ['POST', '/api/auth/sign-out'],
    'auth.signout.everywhere': ['POST', '/api/auth/sign-out-everywhere'],
    'auth.password.forgot': ['POST', '/api/auth/password/forgot'],
    'auth.password.reset': ['POST', '/api/auth/password/reset'],
    'account.show': ['GET', '/api/account'],
    'account.update': ['PATCH', '/api/account'],
    'account.terms.accept': ['POST', '/api/account/terms'],
    'account.handle.check': ['GET', '/api/account/handle'],
    'account.email.update': ['POST', '/api/account/email'],
    'account.email.confirm': ['POST', '/api/account/email/confirm'],
    'account.email.revert': ['POST', '/api/account/email/revert'],
    'account.password.update': ['PUT', '/api/account/password'],
    'account.methods.delete': ['DELETE', '/api/account/methods/{method}'],
    'account.avatar.update': ['POST', '/api/account/avatar'],
    'account.avatar.delete': ['DELETE', '/api/account/avatar'],
    'account.export.create': ['POST', '/api/account/export'],
    'account.export.show': ['GET', '/api/account/export'],
    'account.delete': ['DELETE', '/api/account'],
    // M4: cloud save.
    'sync.pull': ['GET', '/api/sync'],
    'projects.show': ['GET', '/api/projects/{id}'],
    'projects.save': ['PUT', '/api/projects/{id}'],
    'projects.delete': ['DELETE', '/api/projects/{id}'],
    'projects.restore': ['POST', '/api/projects/{id}/restore'],
    'parts.save': ['PUT', '/api/parts/{id}'],
    'parts.delete': ['DELETE', '/api/parts/{id}'],
    'takes.create': ['POST', '/api/takes'],
    'takes.complete': ['POST', '/api/takes/{id}/complete'],
    'takes.show': ['GET', '/api/takes/{id}'],
    'takes.restore': ['POST', '/api/takes/{id}/restore'],
    'takes.delete': ['DELETE', '/api/takes/{id}'],
    'parts.takes.list': ['GET', '/api/parts/{id}/takes'],
    'parts.takes.use': ['POST', '/api/parts/{id}/takes/{takeId}/use'],
    'bin.list': ['GET', '/api/bin'],
    'usage.show': ['GET', '/api/usage'],
    'account.learning.show': ['GET', '/api/account/learning'],
    'account.learning.update': ['PUT', '/api/account/learning'],
    // M5: Plus and billing, the score history, practice reminders.
    'templates.show': ['GET', '/api/templates/{id}'],
    'admin.beta.clips.list': ['GET', '/api/admin/beta/clips'],
    'courses.clips.list': ['GET', '/api/courses/{courseId}/clips'],
    'billing.prices': ['GET', '/api/billing/prices'],
    'billing.show': ['GET', '/api/billing'],
    'billing.checkout': ['POST', '/api/billing/checkout'],
    'billing.portal': ['POST', '/api/billing/portal'],
    'projects.editable.update': ['PUT', '/api/projects/editable'],
    'scores.create': ['POST', '/api/scores'],
    'scores.list': ['GET', '/api/scores'],
    'reminders.show': ['GET', '/api/reminders'],
    'reminders.update': ['PUT', '/api/reminders'],
    'reminders.devices.create': ['POST', '/api/reminders/devices'],
    'reminders.devices.update': ['PUT', '/api/reminders/devices/{id}'],
    'reminders.devices.delete': ['DELETE', '/api/reminders/devices/{id}'],
    'admin.billing.disputes.list': ['GET', '/api/admin/billing/disputes'],
    // M10: books (Invoice Ninja) and our own posts
    'invoiceNinja.connection.show': ['GET', '/api/coach/invoice-ninja'],
    'invoiceNinja.connect': ['POST', '/api/coach/invoice-ninja'],
    'invoiceNinja.connection.update': ['PATCH', '/api/coach/invoice-ninja'],
    'invoiceNinja.connection.delete': ['DELETE', '/api/coach/invoice-ninja'],
    'invoiceNinja.records.list': ['GET', '/api/coach/invoice-ninja/records'],
    'invoiceNinja.records.retry': ['POST', '/api/coach/invoice-ninja/records/{id}/retry'],
    'admin.social.list': ['GET', '/api/admin/social'],
    'admin.social.feature': ['POST', '/api/admin/social/harmonies/{id}'],
    'admin.social.approve': ['POST', '/api/admin/social/{id}/approve'],
    'admin.social.cancel': ['POST', '/api/admin/social/{id}/cancel'],
    'admin.social.retry': ['POST', '/api/admin/social/{id}/retry'],
    'admin.books.show': ['GET', '/api/admin/books'],
    'admin.books.connect': ['POST', '/api/admin/books'],
    'admin.books.delete': ['DELETE', '/api/admin/books'],
    'admin.books.records.list': ['GET', '/api/admin/books/records'],
    'admin.books.records.retry': ['POST', '/api/admin/books/records/{id}/retry'],
    'admin.books.plusInvoices.create': ['POST', '/api/admin/books/plus-invoices'],
    // L: support, notices and refunds
    'support.create': ['POST', '/api/support'],
    'notices.list': ['GET', '/api/notices'],
    'notices.show': ['GET', '/api/notices/{id}'],
    'notices.appeal': ['POST', '/api/notices/{id}/appeal'],
    'admin.support.list': ['GET', '/api/admin/support'],
    'admin.support.show': ['GET', '/api/admin/support/{id}'],
    'admin.support.reply': ['POST', '/api/admin/support/{id}/reply'],
    'admin.support.update': ['PATCH', '/api/admin/support/{id}'],
    'admin.appeals.list': ['GET', '/api/admin/appeals'],
    'admin.appeals.decide': ['POST', '/api/admin/appeals/{id}'],
    'admin.people.refund': ['POST', '/api/admin/people/{id}/refund'],
    'admin.people.payment': ['GET', '/api/admin/people/{id}/payment'],
    // L: song finishers and the network features
    'templates.finishers': ['GET', '/api/templates/{id}/finishers'],
    'admin.network.show': ['GET', '/api/admin/network'],
    'admin.network.update': ['PUT', '/api/admin/network/{feature}'],
    'earlyAccess.show': ['GET', '/api/early/{code}'],
    'admin.earlyAccess.show': ['GET', '/api/admin/early-access'],
    'admin.earlyAccess.update': ['PATCH', '/api/admin/early-access'],
    'admin.earlyAccess.replace': ['POST', '/api/admin/early-access/replace'],
    'admin.mudeo.show': ['GET', '/api/admin/mudeo'],
    'admin.mudeo.update': ['PATCH', '/api/admin/mudeo'],
    'admin.mudeo.import': ['POST', '/api/admin/mudeo/import'],
    'admin.mudeo.next': ['GET', '/api/admin/mudeo/next'],
    'admin.mudeo.batches.create': ['POST', '/api/admin/mudeo/batches'],
    'admin.mudeo.batches.undo': ['DELETE', '/api/admin/mudeo/batches/latest'],
    'admin.mudeo.mark': ['POST', '/api/admin/mudeo/mark'],
    'admin.mudeo.contacts': ['GET', '/api/admin/mudeo/contacts'],
    'admin.mudeo.send': ['POST', '/api/admin/mudeo/send'],
    'admin.mudeo.test': ['POST', '/api/admin/mudeo/test'],
    'admin.preview.show': ['GET', '/api/admin/preview'],
    'admin.preview.update': ['PATCH', '/api/admin/preview'],
    'admin.preview.replace': ['POST', '/api/admin/preview/replace'],
    'explore.list': ['GET', '/api/explore'],
    'profiles.show': ['GET', '/api/profiles/{handle}'],
    'profiles.harmonies': ['GET', '/api/profiles/{handle}/harmonies'],
    'likes.create': ['PUT', '/api/harmonies/{id}/like'],
    'likes.delete': ['DELETE', '/api/harmonies/{id}/like'],
    'comments.list': ['GET', '/api/harmonies/{id}/comments'],
    'comments.create': ['POST', '/api/harmonies/{id}/comments'],
    'comments.delete': ['DELETE', '/api/comments/{id}'],
    'follows.create': ['PUT', '/api/profiles/{handle}/follow'],
    'follows.delete': ['DELETE', '/api/profiles/{handle}/follow'],
    'blocks.create': ['PUT', '/api/profiles/{handle}/block'],
    'blocks.delete': ['DELETE', '/api/profiles/{handle}/block'],
    'blocks.list': ['GET', '/api/account/blocks'],
    'harmonies.consent.ask': ['POST', '/api/harmonies/{id}/consent'],
    'consentRequests.list': ['GET', '/api/consent-requests'],
    'consentRequests.decide': ['POST', '/api/consent-requests/{id}'],
    'notifications.list': ['GET', '/api/notifications'],
    'notifications.read': ['POST', '/api/notifications/read'],
    'admin.gallery.held': ['GET', '/api/admin/gallery/held'],
    'admin.gallery.decide': ['POST', '/api/admin/gallery/{kind}/{id}'],
    'admin.harmonies.feature': ['PUT', '/api/admin/harmonies/{id}/feature'],
    'admin.gallery.guides.create': ['POST', '/api/admin/gallery/harmony/{id}/guides'],
    'leagues.current': ['GET', '/api/leagues/current'],
    'leagues.join': ['POST', '/api/leagues/membership'],
    'leagues.leave': ['DELETE', '/api/leagues/membership'],
    'friends.leaderboard': ['GET', '/api/leagues/friends'],
    'library.list': ['GET', '/api/library'],
    'templates.publish': ['PUT', '/api/templates/{id}/publication'],
    'templates.unpublish': ['DELETE', '/api/templates/{id}/publication'],
    'templates.rate': ['PUT', '/api/templates/{id}/rating'],
    'templates.unrate': ['DELETE', '/api/templates/{id}/rating'],
    'admin.library.held': ['GET', '/api/admin/library/held'],
    'admin.library.decide': ['POST', '/api/admin/library/{id}'],
    'admin.library.feature': ['PUT', '/api/admin/library/{id}/feature'],
    'ideas.list': ['GET', '/api/ideas'],
    'ideas.mine': ['GET', '/api/ideas/mine'],
    'ideas.show': ['GET', '/api/ideas/{id}'],
    'ideas.create': ['POST', '/api/ideas'],
    'ideas.delete': ['DELETE', '/api/ideas/{id}'],
    'ideas.voices.create': ['POST', '/api/ideas/{id}/voices'],
    'ideas.voices.complete': ['POST', '/api/idea-voices/{id}/complete'],
    'ideas.voices.delete': ['DELETE', '/api/idea-voices/{id}'],
    'admin.ideas.held': ['GET', '/api/admin/ideas/held'],
    'admin.ideas.decide': ['POST', '/api/admin/ideas/{id}'],
    'coaches.list': ['GET', '/api/coaches'],
    'admin.coaches.feature': ['PUT', '/api/admin/coaches/{handle}/feature'],
    'harmonies.open': ['PUT', '/api/harmonies/{id}/open'],
    'harmonies.stems': ['GET', '/api/harmonies/{id}/stems'],
    'harmonies.fills.list': ['GET', '/api/harmonies/{id}/fills'],
    'choirs.show': ['GET', '/api/choirs/{song}'],
    'choirs.voices.list': ['GET', '/api/choirs/{song}/voices'],
    'choirs.join': ['POST', '/api/choirs/{song}/voices'],
    'choirs.leave': ['DELETE', '/api/choirs/{song}/voices/mine'],
    'admin.choirs.voices.remove': ['POST', '/api/admin/choir-voices/{id}/remove'],
    'inviteLink.show': ['GET', '/api/invite/{code}'],
    'admin.invite.show': ['GET', '/api/admin/invite'],
    'admin.invite.update': ['PATCH', '/api/admin/invite'],
    'admin.invite.replace': ['POST', '/api/admin/invite/replace'],
    'requests.list': ['GET', '/api/requests'],
    'requests.create': ['POST', '/api/requests'],
    'requests.vote': ['PUT', '/api/requests/{id}/vote'],
    'requests.unvote': ['DELETE', '/api/requests/{id}/vote'],
    'admin.requests.list': ['GET', '/api/admin/requests'],
    'admin.requests.update': ['PATCH', '/api/admin/requests/{id}'],
    'admin.beta.bar.show': ['GET', '/api/admin/beta/bar'],
    'admin.beta.bar.update': ['PATCH', '/api/admin/beta/bar'],
    'admin.choirs.voices.held': ['GET', '/api/admin/choir-voices/held'],
    'admin.choirs.voices.approve': ['POST', '/api/admin/choir-voices/{id}/approve'],
    'billing.store.sync': ['POST', '/api/billing/store'],
    'admin.people.storeTester.update': ['PUT', '/api/admin/people/{id}/store-tester'],
    'admin.storeTesters.list': ['GET', '/api/admin/store-testers'],
    'account.learning.reset': ['POST', '/api/account/learning/reset'],
    'account.learning.undoReset': ['POST', '/api/account/learning/reset/undo'],
    'auth.link.send': ['POST', '/api/auth/link'],
    'auth.link.open': ['POST', '/api/auth/link/open'],
    'bin.purge': ['DELETE', '/api/bin/{kind}/{id}'],
    'bin.purgeTakes': ['DELETE', '/api/projects/{id}/older-takes'],
    'boards.scores.submit': ['POST', '/api/boards/scores'],
    'boards.show': ['GET', '/api/boards/{template}/{part}'],
    'boards.global.update': ['PUT', '/api/boards/global'],
    'boards.challenges.create': ['POST', '/api/boards/challenges'],
    'boards.challenges.show': ['GET', '/api/boards/challenges/{token}'],
    'boards.challenges.accept': ['POST', '/api/boards/challenges/{token}/accept'],
    'boards.daily.show': ['GET', '/api/boards/daily'],
    'boards.ghost.show': ['GET', '/api/boards/{template}/{part}/ghost'],
    'boards.ghost.update': ['PUT', '/api/boards/{template}/{part}/ghost'],
    'admin.boards.flags.list': ['GET', '/api/admin/boards/flags'],
    'admin.boards.scores.review': ['POST', '/api/admin/boards/scores/{id}/review'],
    'admin.boards.scores.delete': ['DELETE', '/api/admin/boards/scores/{id}'],
    'admin.boards.reset': ['POST', '/api/admin/people/{id}/boards/reset'],
    'admin.diagnostics.country': ['GET', '/api/admin/diagnostics/country'],
};

/** A refusal or failure, with the contract's stable code (never a sentence to show). */
export class ApiError extends Error {
    // current: the server's copy, on a 409 version_conflict (contract/api.md, "Projects and cloud save")
    // body: the refusal as sent, for the extra fields some carry (a daily limit's counts)
    constructor({ status, code, errors = null, retryAfter = null, operation, current = null, body = null }) {
        super(`${operation}: ${status} ${code}`);
        Object.assign(this, { status, code, errors, retryAfter, operation, current, body });
    }
}

const transport = { fetch: (...args) => (mockWanted() ? mockFetch(...args) : fetch(...args)) };

/** Tests only: answer with another fetch. */
export function useTransport(fetchLike) {
    transport.fetch = fetchLike;
}

/**
 * Calls `operation`: `params` fill the path's {placeholders}, `query` becomes the query string
 * (empty values left out), `body` is sent as JSON (a FormData as multipart). Resolves with the
 * parsed JSON (null for 204). `origin` sends it to the app on another host (the public site's
 * Report, docs/app-subdomain-plan.md): no cookies and no CSRF token, which only such routes allow.
 */
/** A take's peaks from `peaks8`: base64, one byte a peak, each byte / 255 (contract/api.md "Peaks, compact"). */
export function decodePeaks8(text) {
    const bytes = atob(text);
    const peaks = new Array(bytes.length);
    for (let i = 0; i < bytes.length; i++) peaks[i] = bytes.charCodeAt(i) / 255;
    return peaks;
}

/** A take with `peaks8` gets its `peaks` back as numbers; one without keeps the numbers it came with. */
function unpackPeaks(take) {
    if (take && typeof take.peaks8 === 'string') {
        take.peaks = decodePeaks8(take.peaks8);
        delete take.peaks8;
    }
}

/** The takes in a response (a list under `takes`, or the take itself), with their peaks decoded. */
function withPeaks(response) {
    const data = response?.data;
    if (Array.isArray(data?.takes)) data.takes.forEach(unpackPeaks);
    else unpackPeaks(data);
    return response;
}

/**
 * Whether the server answers at all: any answer will do, past the mock and the worker (which never
 * stores /api), so a refusal can say "you're offline" when that's the reason.
 */
export function serverReachable() {
    return fetch(OPERATIONS['auth.config'][1], { cache: 'no-store', credentials: 'same-origin' }).then(() => true, () => false);
}

export async function call(operation, { params = {}, query = {}, body = undefined, headers: extra = {}, origin = null } = {}) {
    const [method, pattern] = OPERATIONS[operation] ?? [];
    if (!method) {
        throw new Error(`No such operation: ${operation}`);
    }
    const path = pattern.replace(/\{(\w+)\}/g, (_, name) => {
        if (params[name] === undefined || params[name] === null) throw new Error(`${operation} needs ${name}`);
        return encodeURIComponent(params[name]);
    });
    // toString(), not .size: URLSearchParams.size is missing before Safari 17.
    const search = new URLSearchParams(Object.entries(query).filter(([, value]) => value !== undefined && value !== null && value !== '')).toString();
    const url = search ? `${path}?${search}` : path;
    if (origin) return read(await send(method, new URL(url, origin).href, body, operation, extra, { crossOrigin: true }), operation);
    let response = await send(method, url, body, operation, extra);
    if (response.status === 419) {
        // A missing or stale CSRF token: auth.config (the one anonymous call that starts a session)
        // sets a fresh cookie, then once more.
        await transport.fetch(OPERATIONS['auth.config'][1], { headers: headers('GET'), credentials: 'same-origin', operation: 'auth.config' }).catch(() => null);
        response = await send(method, url, body, operation, extra);
    }
    const result = await read(response, operation);
    return result;
}

/** What the app does about an answer whatever the screen: an app too old for the API says so once. */
export const apiEvents = { outdated: null, ageUnconfirmed: null };

/** Sends the request; no connection at all is an ApiError with the code `offline`. */
async function send(method, url, body, operation, extra = {}, { crossOrigin = false } = {}) {
    try {
        return await transport.fetch(url, {
            method,
            headers: { ...headers(crossOrigin ? 'GET' : method, body !== undefined && !(body instanceof FormData)), ...extra },
            body: body === undefined || body instanceof FormData ? body : JSON.stringify(body),
            credentials: crossOrigin ? 'omit' : 'same-origin',
            operation, // for the mock
        });
    } catch (error) {
        if (error instanceof ApiError) throw error;
        throw new ApiError({ status: 0, code: 'offline', operation });
    }
}

function headers(method, json = false) {
    const out = { Accept: 'application/json', 'X-App-Version': APP_VERSION };
    if (json) out['Content-Type'] = 'application/json';
    if (method !== 'GET') {
        const token = cookie('XSRF-TOKEN');
        if (token) out['X-XSRF-TOKEN'] = token;
    }
    return out;
}

function cookie(name) {
    const found = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`));
    return found ? decodeURIComponent(found.slice(name.length + 1)) : null;
}

async function read(response, operation) {
    if (response.status === 204) {
        return null;
    }
    const text = await response.text();
    let json = null;
    try {
        json = text ? JSON.parse(text) : null;
    } catch {
        // Not JSON (a proxy's error page): handled below as a server error.
    }
    if (response.ok && json !== null) {
        return json;
    }
    const retryAfter = json?.retryAfter ?? (Number.parseInt(response.headers.get('Retry-After') ?? '', 10) || null);
    if (response.status === 426) {
        apiEvents.outdated?.();
    }
    // The age question wasn't answered on this account: the app asks it again (then the person retries).
    if (json?.code === 'age_unconfirmed') apiEvents.ageUnconfirmed?.();
    throw new ApiError({
        status: response.status,
        code: json?.code ?? (response.ok ? 'bad_response' : 'server_error'),
        errors: json?.errors ?? null,
        retryAfter,
        operation,
        current: json?.current ?? null,
        body: json,
    });
}

// ---- One function per operation ------------------------------------------------------------------

/** A function for an operation that takes `call`'s own options ({ params, query, body }) as they are. */
const op = (operation) => (options = {}) => call(operation, options);

export const api = {
    harmonies: {
        create: (fields) => call('harmonies.create', { body: fields }),
        complete: (id) => call('harmonies.complete', { params: { id } }),
        list: ({ cursor, projectId } = {}) => call('harmonies.list', { query: { cursor, projectId } }),
        show: (id) => call('harmonies.show', { params: { id } }),
        update: (id, fields) => call('harmonies.update', { params: { id }, body: fields }),
        delete: (id) => call('harmonies.delete', { params: { id } }),
        consent: {
            ask: (id) => call('harmonies.consent.ask', { params: { id } }),
        },
    },
    // The gallery (N1, behind its flag): profiles, Explore, likes, comments, follows, blocks, friends'
    // agreement to going public, and the bell.
    explore: {
        list: ({ tab = 'new', song, q, cursor } = {}) => call('explore.list', { query: { tab, song, q, cursor } }),
    },
    profiles: {
        show: (handle) => call('profiles.show', { params: { handle } }),
        harmonies: (handle, { cursor } = {}) => call('profiles.harmonies', { params: { handle }, query: { cursor } }),
    },
    likes: {
        create: (id) => call('likes.create', { params: { id } }),
        delete: (id) => call('likes.delete', { params: { id } }),
    },
    comments: {
        list: (harmonyId, { cursor } = {}) => call('comments.list', { params: { id: harmonyId }, query: { cursor } }),
        create: (harmonyId, { body }) => call('comments.create', { params: { id: harmonyId }, body: { body } }),
        delete: (id) => call('comments.delete', { params: { id } }),
    },
    follows: {
        create: (handle) => call('follows.create', { params: { handle } }),
        delete: (handle) => call('follows.delete', { params: { handle } }),
    },
    blocks: {
        create: (handle) => call('blocks.create', { params: { handle } }),
        delete: (handle) => call('blocks.delete', { params: { handle } }),
        list: () => call('blocks.list'),
    },
    consentRequests: {
        list: () => call('consentRequests.list'),
        decide: (id, { agree }) => call('consentRequests.decide', { params: { id }, body: { agree } }),
    },
    // The template library (N3), Ideas (your tune, sung by others) and the coach directory (N4),
    // each behind its flag: `call`'s options as they are.
    library: { list: op('library.list') },
    ideas: {
        list: op('ideas.list'),
        mine: op('ideas.mine'),
        show: op('ideas.show'),
        create: op('ideas.create'),
        delete: op('ideas.delete'),
        voices: { create: op('ideas.voices.create'), complete: op('ideas.voices.complete'), delete: op('ideas.voices.delete') },
    },
    coaches: { list: op('coaches.list') },
    // Leagues (N2, behind its flag): a weekly league of about 30 by XP, and friends' leaderboard.
    leagues: {
        current: () => call('leagues.current'),
        join: (fields = {}) => call('leagues.join', { body: fields }),
        leave: () => call('leagues.leave'),
    },
    friends: {
        leaderboard: () => call('friends.leaderboard'),
    },
    notifications: {
        list: ({ cursor } = {}) => call('notifications.list', { query: { cursor } }),
        read: ({ all = false, upTo = null } = {}) => call('notifications.read', { body: all ? { all: true } : { upTo } }),
    },
    videos: {
        create: (harmonyId, fields) => call('videos.create', { params: { id: harmonyId }, body: fields }),
        show: (id) => call('videos.show', { params: { id } }),
    },
    reports: {
        create: (fields) => call('reports.create', { body: fields }),
    },
    // mudeo's early-access link (docs/mudeo-transition-plan.md): what a code is for.
    earlyAccess: {
        show: (code) => call('earlyAccess.show', { params: { code } }),
    },
    // Hillel's shared invite link: whether it lets people in now.
    inviteLink: {
        show: (code) => call('inviteLink.show', { params: { code } }),
    },
    admin: {
        me: () => call('admin.me'),
        library: { held: op('admin.library.held'), decide: op('admin.library.decide'), feature: op('admin.library.feature') },
        ideas: { held: op('admin.ideas.held'), decide: op('admin.ideas.decide') },
        coaches: { feature: op('admin.coaches.feature') },
        gallery: {
            held: () => call('admin.gallery.held'),
            decide: (kind, id, { decision, note = null }) => call('admin.gallery.decide', { params: { kind, id }, body: { decision, note } }),
            guides: {
                create: (harmonyId, fields) => call('admin.gallery.guides.create', { params: { id: harmonyId }, body: fields }),
            },
        },
        harmonies: {
            feature: (id, { featured }) => call('admin.harmonies.feature', { params: { id }, body: { featured } }),
        },
        // The one email to mudeo's old users, and its early-access link.
        mudeo: {
            show: () => call('admin.mudeo.show'),
            update: (fields) => call('admin.mudeo.update', { body: fields }),
            import: (form) => call('admin.mudeo.import', { body: form }),
            next: () => call('admin.mudeo.next'),
            send: () => call('admin.mudeo.send'),
            test: () => call('admin.mudeo.test'),
            contacts: ({ q } = {}) => call('admin.mudeo.contacts', { query: { q } }),
            mark: (fields) => call('admin.mudeo.mark', { body: fields }),
            batches: {
                create: (fields) => call('admin.mudeo.batches.create', { body: fields }),
                undo: () => call('admin.mudeo.batches.undo'),
            },
        },
        // The preview link: lets someone look around before launch, with no account.
        // Hillel's one shared invite link: Copy, Pause, Replace.
        invite: {
            show: () => call('admin.invite.show'),
            update: (fields) => call('admin.invite.update', { body: fields }),
            replace: () => call('admin.invite.replace'),
        },
        preview: {
            show: () => call('admin.preview.show'),
            update: (fields) => call('admin.preview.update', { body: fields }),
            replace: () => call('admin.preview.replace'),
        },
        earlyAccess: {
            show: () => call('admin.earlyAccess.show'),
            update: (fields) => call('admin.earlyAccess.update', { body: fields }),
            replace: () => call('admin.earlyAccess.replace'),
        },
        reports: {
            list: ({ status, cursor } = {}) => call('admin.reports.list', { query: { status, cursor } }),
        },
        social: {
            list: ({ status, cursor } = {}) => call('admin.social.list', { query: { status, cursor } }),
            approve: (id) => call('admin.social.approve', { params: { id } }),
            cancel: (id) => call('admin.social.cancel', { params: { id } }),
            retry: (id) => call('admin.social.retry', { params: { id } }),
            feature: (id) => call('admin.social.feature', { params: { id } }),
        },
        harmonies: {
            hide: (id, fields) => call('admin.harmonies.hide', { params: { id }, body: fields }),
            restore: (id, fields = {}) => call('admin.harmonies.restore', { params: { id }, body: fields }),
            remove: (id, fields = {}) => call('admin.harmonies.remove', { params: { id }, body: fields }),
        },
        flags: {
            list: () => call('admin.flags.list'),
            update: (name, { value, reason }) => call('admin.flags.update', { params: { name }, body: { value, reason } }),
        },
        limits: {
            list: () => call('admin.limits.list'),
            update: (limiter, rule, { max, reason }) => call('admin.limits.update', { params: { limiter, rule }, body: { max, reason } }),
        },
        blocklist: {
            list: ({ type, cursor } = {}) => call('admin.blocklist.list', { query: { type, cursor } }),
            create: ({ type, value, reason, expiresAt = null }) => call('admin.blocklist.create', { body: { type, value, reason, expiresAt } }),
            delete: (id) => call('admin.blocklist.delete', { params: { id } }),
        },
        securityEvents: {
            list: ({ type, user, cursor } = {}) => call('admin.securityEvents.list', { query: { type, user, cursor } }),
        },
        audit: {
            list: ({ action, user, cursor } = {}) => call('admin.audit.list', { query: { action, user, cursor } }),
        },
        overview: () => call('admin.overview'),
        beta: {
            // The course clips beta singers record (web/course/plus/clips.json), for the admin.
            clips: { list: () => call('admin.beta.clips.list') },
            singers: {
                list: () => call('admin.beta.singers.list'),
                create: (singer) => call('admin.beta.singers.create', { body: singer }),
                update: (id, fields) => call('admin.beta.singers.update', { params: { id }, body: fields }),
                link: (id, { expiresAt = null } = {}) => call('admin.beta.singers.link', { params: { id }, body: { expiresAt } }),
                revoke: (id) => call('admin.beta.singers.revoke', { params: { id } }),
                paid: (id) => call('admin.beta.singers.paid', { params: { id } }),
                forget: (id) => call('admin.beta.singers.forget', { params: { id } }),
            },
            assignments: {
                create: (singerId, { templateId, partKey, clipId, minutes }) => call('admin.beta.assignments.create', { params: { id: singerId }, body: clipId ? { clipId, minutes } : { templateId, partKey, minutes } }),
                delete: (id) => call('admin.beta.assignments.delete', { params: { id } }),
            },
            submissions: {
                list: ({ status, template, part, singer, cursor } = {}) => call('admin.beta.submissions.list', { query: { status, template, part, singer, cursor } }),
                show: (id) => call('admin.beta.submissions.show', { params: { id } }),
                open: (id) => call('admin.beta.submissions.open', { params: { id } }),
                adjust: (id, fields) => call('admin.beta.submissions.adjust', { params: { id }, body: fields }),
                accept: (id, { makeDefault = true } = {}) => call('admin.beta.submissions.accept', { params: { id }, body: { makeDefault } }),
                retake: (id, { note, beat = null }) => call('admin.beta.submissions.retake', { params: { id }, body: { note, beat } }),
                reject: (id, { note = null } = {}) => call('admin.beta.submissions.reject', { params: { id }, body: { note } }),
                publish: (id) => call('admin.beta.submissions.publish', { params: { id } }),
            },
            guides: {
                list: ({ template } = {}) => call('admin.beta.guides.list', { query: { template } }),
                default: (id) => call('admin.beta.guides.default', { params: { id } }),
                delete: (id) => call('admin.beta.guides.delete', { params: { id } }),
            },
            feedback: {
                list: ({ unread, cursor } = {}) => call('admin.beta.feedback.list', { query: { unread: unread ? 1 : undefined, cursor } }),
                read: (id) => call('admin.beta.feedback.read', { params: { id } }),
            },
        },
        billing: {
            disputes: {
                list: ({ cursor } = {}) => call('admin.billing.disputes.list', { query: { cursor } }),
            },
        },
        people: {
            list: ({ q, suspended, adult, plan, role, sort, country, cursor } = {}) => call('admin.people.list', { query: { q, suspended, adult, plan, role, sort, country, cursor } }),
            countries: () => call('admin.people.countries'),
            show: (id) => call('admin.people.show', { params: { id } }),
            suspend: (id, { reason }) => call('admin.people.suspend', { params: { id }, body: { reason } }),
            unsuspend: (id, { reason }) => call('admin.people.unsuspend', { params: { id }, body: { reason } }),
            trust: {
                update: (id, { level, note }) => call('admin.people.trust.update', { params: { id }, body: { level, note } }),
            },
            recover: (id, { email, reason }) => call('admin.people.recover', { params: { id }, body: { email, reason } }),
            refund: (id, { amountCents, note }) => call('admin.people.refund', { params: { id }, body: { amountCents, note } }),
            // The latest Plus payment through Stripe (null when none): loaded only when the refund panel opens.
            payment: (id) => call('admin.people.payment', { params: { id } }),
            storeTester: {
                update: (id, { tester, note }) => call('admin.people.storeTester.update', { params: { id }, body: { tester, note } }),
            },
        },
        counts: {
            show: ({ days } = {}) => call('admin.counts.show', { query: { days } }),
        },
        funnel: {
            show: ({ experiment, days } = {}) => call('admin.funnel.show', { query: { experiment, days } }),
        },
        storeTesters: {
            list: () => call('admin.storeTesters.list'),
        },
    },
    // Anonymous usage counts: js/usage.js sends them itself (sendBeacon, so a page closing still sends).
    counts: {
        create: (body) => call('counts.create', { body }),
    },
    auth: {
        config: () => call('auth.config'),
        register: (fields) => call('auth.register', { body: fields }),
        verify: (token) => call('auth.verify', { body: { token } }),
        // Sign in by a link sent by email: always 202 (it never says whether an address has an account).
        link: {
            send: ({ email, intended = null, invite = null, early = null, turnstileToken = null }) => call('auth.link.send', { body: { email, intended, invite, early, turnstileToken } }),
            open: (token) => call('auth.link.open', { body: { token } }),
        },
        verification: {
            resend: ({ email, turnstileToken = null }) => call('auth.verification.resend', { body: { email, turnstileToken } }),
        },
        signin: ({ email, password, remember = true, turnstileToken = null }) => call('auth.signin', { body: { email, password, remember, turnstileToken } }),
        signout: Object.assign(() => call('auth.signout'), {
            everywhere: () => call('auth.signout.everywhere'), // this device too
        }),
        password: {
            forgot: ({ email, turnstileToken = null }) => call('auth.password.forgot', { body: { email, turnstileToken } }),
            reset: ({ token, email, password }) => call('auth.password.reset', { body: { token, email, password } }),
        },
    },
    account: {
        show: () => call('account.show'),
        update: (fields) => call('account.update', { body: fields }),
        /** Agrees to the terms' `version` shown (409 terms_changed when a newer one came: current.version). */
        acceptTerms: (version) => call('account.terms.accept', { body: { version } }),
        handle: {
            check: (handle) => call('account.handle.check', { query: { handle } }),
        },
        email: {
            update: ({ email, password = null }) => call('account.email.update', { body: { email, password } }),
            confirm: (token) => call('account.email.confirm', { body: { token } }),
            revert: (token) => call('account.email.revert', { body: { token } }),
        },
        password: {
            update: ({ currentPassword = null, password }) => call('account.password.update', { body: { currentPassword, password } }),
        },
        methods: {
            delete: (method) => call('account.methods.delete', { params: { method } }),
        },
        avatar: {
            update: (file) => {
                const form = new FormData();
                form.append('avatar', file);
                return call('account.avatar.update', { body: form });
            },
            delete: () => call('account.avatar.delete'),
        },
        export: {
            create: () => call('account.export.create'),
            show: () => call('account.export.show'),
        },
        delete: ({ password = null } = {}) => call('account.delete', { body: { password } }),
        learning: {
            show: () => call('account.learning.show'),
            update: (learning) => call('account.learning.update', { body: learning }),
        },
    },
    // Takes' peaks come compact (?peaks=8) and are decoded here, so the rest of the app sees numbers.
    sync: {
        pull: async ({ cursor } = {}) => withPeaks(await call('sync.pull', { query: { cursor, peaks: 8 } })),
    },
    projects: {
        editable: {
            update: (ids) => call('projects.editable.update', { body: { ids } }),
        },
        show: async (id) => withPeaks(await call('projects.show', { params: { id }, query: { peaks: 8 } })),
        save: (id, project) => call('projects.save', { params: { id }, body: project }),
        delete: (id, { version, changeId }) => call('projects.delete', { params: { id }, query: { version, changeId } }),
        restore: (id) => call('projects.restore', { params: { id } }),
        shared: ({ cursor } = {}) => call('projects.shared', { query: { cursor } }),
        members: {
            list: (id) => call('projects.members.list', { params: { id } }),
            delete: (id, memberId, { keepParts = true } = {}) => call('projects.members.delete', { params: { id, memberId }, query: { keepParts: keepParts ? 1 : 0 } }),
        },
    },
    invites: {
        create: (projectId, fields = {}) => call('invites.create', { params: { id: projectId }, body: fields }),
        list: (projectId) => call('invites.list', { params: { id: projectId } }),
        revoke: (id) => call('invites.revoke', { params: { id } }),
        show: (token) => call('invites.show', { params: { token } }),
        accept: (token) => call('invites.accept', { params: { token } }),
    },
    groups: {
        list: () => call('groups.list'),
        create: (fields) => call('groups.create', { body: fields }),
        show: (id) => call('groups.show', { params: { id } }),
        update: (id, fields) => call('groups.update', { params: { id }, body: fields }),
        delete: (id) => call('groups.delete', { params: { id } }),
        link: {
            create: (id) => call('groups.link.create', { params: { id } }),
        },
        // Joining by the choir link; `join.show` is the link's page before joining.
        join: Object.assign((token, fields = {}) => call('groups.join', { params: { token }, body: fields }), {
            show: (token) => call('groups.join.show', { params: { token } }),
        }),
        members: {
            delete: (id, memberId) => call('groups.members.delete', { params: { id, memberId } }),
        },
        songs: {
            create: (id, fields) => call('groups.songs.create', { params: { id }, body: fields }),
            delete: (id, templateId) => call('groups.songs.delete', { params: { id, templateId } }),
        },
        assignments: {
            update: (id, templateId, assignments) => call('groups.assignments.update', { params: { id, templateId }, body: assignments }),
        },
    },
    parts: {
        save: (id, part) => call('parts.save', { params: { id }, body: part }),
        delete: (id, { version, changeId }) => call('parts.delete', { params: { id }, query: { version, changeId } }),
        // A part's kept takes (Hillel: "keep all takes so users can swap to try"), newest first.
        takes: {
            list: async (id) => {
                const response = await call('parts.takes.list', { params: { id } });
                response?.data?.forEach((item) => unpackPeaks(item.take));
                return response;
            },
            use: (id, takeId) => call('parts.takes.use', { params: { id, takeId } }),
        },
    },
    takes: {
        create: (take) => call('takes.create', { body: take }),
        complete: (id) => call('takes.complete', { params: { id } }),
        show: async (id) => withPeaks(await call('takes.show', { params: { id }, query: { peaks: 8 } })),
        restore: (id, { nudgeFrames = 0 } = {}) => call('takes.restore', { params: { id }, body: { nudgeFrames } }),
        /** A kept take, not the current one, to Recently deleted. */
        delete: (id) => call('takes.delete', { params: { id } }),
    },
    bin: {
        list: () => call('bin.list'),
        /** Recently deleted's Delete now: one item for good (kind: project or take). */
        purge: (kind, id) => call('bin.purge', { params: { kind, id } }),
        /** A song's older takes (its binned ones), for good: { freedBytes }. */
        purgeTakes: (projectId) => call('bin.purgeTakes', { params: { id: projectId } }),
    },
    usage: {
        show: () => call('usage.show'),
    },
    // A Plus course's real-singer clips, accepted ones only (web/course/plus/clips.json's slots).
    courses: {
        clips: { list: (courseId) => call('courses.clips.list', { params: { courseId } }) },
    },
    templates: {
        catalogue: () => call('templates.catalogue'),
        show: (id, { version } = {}) => call('templates.show', { params: { id }, query: { version } }),
        finishers: (id) => call('templates.finishers', { params: { id } }),
        mine: ({ cursor } = {}) => call('templates.mine', { query: { cursor } }),
        save: (id, template) => call('templates.save', { params: { id }, body: template }),
        update: (id, fields) => call('templates.update', { params: { id }, body: fields }),
        delete: (id) => call('templates.delete', { params: { id } }),
        // The public library (N3).
        publish: op('templates.publish'),
        unpublish: op('templates.unpublish'),
        rate: op('templates.rate'),
        unrate: op('templates.unrate'),
        recordings: {
            create: (templateId, fields) => call('templates.recordings.create', { params: { id: templateId }, body: fields }),
            complete: (id) => call('templates.recordings.complete', { params: { id } }),
            delete: (id) => call('templates.recordings.delete', { params: { id } }),
        },
    },
    billing: {
        prices: () => call('billing.prices'),
        show: () => call('billing.show'),
        checkout: ({ interval, currency, trial = false, moment = 'settings' }) => call('billing.checkout', { body: { interval, currency, trial, moment } }),
        portal: () => call('billing.portal'),
    },
    scores: {
        create: (score) => call('scores.create', { body: score }),
        list: ({ since, cursor } = {}) => call('scores.list', { query: { since, cursor } }),
    },
    reminders: {
        show: ({ endpoint } = {}) => call('reminders.show', { query: { endpoint } }),
        update: (settings) => call('reminders.update', { body: settings }),
        devices: {
            create: (subscription) => call('reminders.devices.create', { body: subscription }),
            // By the device's own token: from the service worker too, with no session.
            update: (id, token, subscription) => call('reminders.devices.update', { params: { id }, body: subscription, headers: { 'X-Device-Token': token } }),
            delete: (id, token) => call('reminders.devices.delete', { params: { id }, headers: { 'X-Device-Token': token } }),
        },
    },
    beta: {
        invite: {
            show: (token) => call('beta.invite.show', { params: { token } }),
            accept: (token) => call('beta.invite.accept', { params: { token } }),
        },
        me: {
            show: () => call('beta.me.show'),
        },
        agreement: {
            show: ({ lang } = {}) => call('beta.agreement.show', { query: { lang } }),
        },
        consent: {
            create: ({ version, adult, creditMode }) => call('beta.consent.create', { body: { version, adult, creditMode } }),
        },
        credit: {
            update: ({ creditMode }) => call('beta.credit.update', { body: { creditMode } }),
        },
        parts: {
            list: () => call('beta.parts.list'),
        },
        submissions: {
            create: (take) => call('beta.submissions.create', { body: take }),
            complete: (id) => call('beta.submissions.complete', { params: { id } }),
        },
        feedback: {
            create: ({ text, templateId = null, partKey = null, beat = null }) => call('beta.feedback.create', { body: { text, templateId, partKey, beat } }),
        },
    },
};

// Every operation has its function: one not written above takes `call`'s own options ({ params,
// query, body }) as they are, at its name's path (api.harmonies.open for harmonies.open).
for (const operation of Object.keys(OPERATIONS)) {
    const path = operation.split('.');
    let node = api;
    for (const part of path.slice(0, -1)) node = node[part] ??= {};
    node[path.at(-1)] ??= op(operation);
}

/**
 * Sends a file to a presigned upload (contract/api.md, "Uploads"): exactly the method and headers
 * the API gave, except Content-Length, which the browser sets from the body. Spaces is another
 * origin, so no cookies go there; locally the upload is on this origin (/dev/upload). A failed
 * PUT is an ApiError (`upload_failed`, or `offline`), and the take stays in the outbox.
 */
export async function uploadTo(upload, body) {
    const headers = Object.fromEntries(Object.entries(upload.headers ?? {}).filter(([name]) => name.toLowerCase() !== 'content-length'));
    const sameOrigin = new URL(upload.url, location.origin).origin === location.origin;
    let response;
    try {
        response = await transport.fetch(upload.url, { method: upload.method ?? 'PUT', headers, body, credentials: sameOrigin ? 'same-origin' : 'omit', operation: 'upload' });
    } catch {
        throw new ApiError({ status: 0, code: 'offline', operation: 'upload' });
    }
    if (!response.ok) {
        throw new ApiError({ status: response.status, code: 'upload_failed', operation: 'upload' });
    }
}

/**
 * Signs out of the admin: the server's own action (not an API operation), with the CSRF token.
 * The caller then goes to the sign-in page.
 */
export async function adminSignOut() {
    if (mockWanted()) return;
    const response = await fetch('/admin/sign-out', { method: 'POST', headers: headers('POST'), credentials: 'same-origin', redirect: 'manual' });
    // The server answers with a redirect to the sign-in page (opaque here) once it has signed out.
    if (response.type !== 'opaqueredirect' && !response.ok) {
        throw new ApiError({ status: response.status, code: response.status === 419 ? 'csrf_mismatch' : 'server_error', operation: 'admin.signOut' });
    }
}

/** The function for `operation` ('admin.flags.update'), or undefined. For the contract test. */
// An operation with no hand-written function yet (its screen isn't built) gets a plain one, so the
// whole contract is callable: call(operation, { params, query, body }).
for (const operation of Object.keys(OPERATIONS)) {
    const parts = operation.split('.');
    let node = api;
    for (const part of parts.slice(0, -1)) node = node[part] ??= {};
    node[parts.at(-1)] ??= (request = {}) => call(operation, request);
}

export function functionFor(operation) {
    return operation.split('.').reduce((node, part) => node?.[part], api);
}
