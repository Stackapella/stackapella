# The API contract

The one thing the backend and frontend agents share (`docs/parallel-agents-plan.md`). The backend
checks every response against it (`assertMatchesContract`), and the app's mock serves its examples,
so neither side can drift without a test failing.

- `schemas/<name>.json`: the shape of a response (or request), as JSON Schema in the small subset
  below.
- `examples/<operation>.<case>.json`: a real-looking response for each case. Every example passes
  its schema (a test checks).
- `log.md`: where things stand (the Now block), and every agreed change, newest first.

## The general rules

### Requests
- **Same origin, session cookie.** The app and the API are served from one origin. Auth is
  Laravel's session cookie; there are no tokens.
- **CSRF:** every `/api` response and every server page (the admin's sign-in and second factor)
  sets the `XSRF-TOKEN` cookie; the app's own files and its `index.html` set no cookie at all. So
  the app's first call is a `GET` (for example `admin.me`) before any change. Every request that
  changes something (`POST`, `PUT`, `PATCH`, `DELETE`) sends the cookie back in the `X-XSRF-TOKEN`
  header. A missing or stale token is a `419` with the code `csrf_mismatch`: the app makes any
  `GET` (which sets a fresh cookie) and retries once.
- **Headers the app sends on every call:**
  - `Accept: application/json`;
  - `X-App-Version: <the app's version>`. A version the server no longer supports gets `426` with
    `app_outdated`, and the app says "Update the app".
- **Request bodies** are JSON (`Content-Type: application/json`), with the same naming as
  responses.
- **No data-changing `GET`.**

### Responses
- **JSON only,** in API Resources' envelope:
  - one record: `{ "data": { ... } }`;
  - a list: `{ "data": [ ... ], "meta": { "nextCursor": "..." } }`, with cursor pagination. Pass
    `?cursor=<nextCursor>` for the next page; `nextCursor` is `null` on the last page.
- **Field names are camelCase,** as in the template format.
- **IDs are strings, never numbers:**
  - records made on the device keep their UUID (projects, parts, takes, device templates);
  - everything else is a Sqid (users, harmonies, template versions, invites, submissions...);
  - `docs/architecture.md`, "Identity and IDs".
- **A delete** answers `204 No Content`, with no body.
- **Times** are ISO 8601 in UTC with a `Z` (`2026-10-26T14:05:00Z`). **Durations** are numbers
  with the unit in the name (`durationSeconds`, `offsetFrames`).
- **No sentences for the app to show.** The API sends stable codes, and the app words them in the
  person's language. The one exception is a `422`'s field messages, which Laravel translates on
  the server.
- **Caching.** Answers are `Cache-Control: no-store`, except the public, read-mostly reads
  (`templates.catalogue`, `explore.list`, `profiles.show`, `profiles.harmonies`, `comments.list`,
  `harmonies.fills.list`, `ideas.list`, `ideas.show`, `choirs.show`, `choirs.voices.list`,
  `billing.prices`): those are `private, no-cache` with an `ETag`, and a request with
  `If-None-Match` set to it answers `304 Not Modified` with no body when nothing changed. Browsers
  do this by themselves; an app that sends `If-None-Match` keeps the last body and reuses it on a
  `304`.

### Errors

Every error response has this shape (`schemas/error.json`):

```json
{ "message": "Too many attempts.", "code": "rate_limited", "retryAfter": 1200 }
```

- **`code`** is stable, and is what the app reads. **`message`** is for logs and people reading the
  network tab; the app never shows it.
- **`errors`** (a `422` only) maps each field to its translated messages, as Laravel does.
- **`retryAfter`** (a `429` only) is the number of seconds to wait, the same as the `Retry-After`
  header, so the app can say "You can try again in 20 minutes".

| Status | Code | When | The app |
|---|---|---|---|
| 400 | `bad_request` | A request the server can't read at all (broken JSON, for example), as opposed to one that fails validation | Says something went wrong, and to try again |
| 401 | `unauthenticated` | Signed out, or the admin session ended | Asks the person to sign in |
| 403 | `forbidden` | Not allowed, with no more specific code | Says it isn't allowed |
| 403 | *(a specific code)* | A refusal with a reason: `not_admin`, `second_factor_required`, `sudo_required`, `blocked`, `project_limit`, `project_read_only`, `storage_limit`, `email_unverified`, `plus_required`, `consent_required`... | Words that code |
| 404 | `not_found` | No such record, or one the person can't see | Says it's gone |
| 409 | `version_conflict` | The record changed since the app read it (its `version`); `current` is the server's copy | Asks "Keep mine or Use theirs" (or merges, for a project's `settings`) |
| 409 | *(a specific code)* | The record's state doesn't allow it: `binned`, `part_gone`, `project_missing`, `part_missing`, `upload_incomplete`, `upload_mismatch`... | Words or handles that code |
| 410 | *(a specific code)* | Expired or revoked: `invite_expired`, `invite_revoked`, `cursor_expired`... | Words or handles that code |
| 419 | `csrf_mismatch` | The CSRF token is missing or stale | Refreshes the token and retries once |
| 422 | `validation_failed` | The request didn't pass validation; `errors` says why | Shows each field's message |
| 426 | `app_outdated` | The app is too old for the API | "Update the app" |
| 429 | `rate_limited` | Too many requests; `retryAfter` and `Retry-After` say how long to wait | Says when to try again |
| 423 | `paused` | An attack switch has paused this feature (`docs/trust-and-safety-plan.md`) | Says it's paused for now, and keeps the work on the device |
| 423 | `read_only` | Read-only mode during an incident | Keeps the work on the device for later |
| 503 | `unavailable` | Stackapella is being updated (maintenance mode), or something it depends on is down. The only 503: the hosting's edge doesn't pass a 503's body through, so every refusal the app must read is a 4xx | Says to try again in a minute, keeping the work on the device |
| 500 | `server_error` | Something broke on the server. Never a raw exception message | Says something went wrong |

### The version (`/up`)

`GET /up` (not under `/api`, and outside the operations: App Platform's health check) answers
`{ "status": "ok", "build": "<the full commit>", "builtAt": "2026-10-26T19:42:07Z" }` to anyone,
limited per network. The app reads it at start and when it comes to the front: a different
`build` from the one it loaded means a new version ("Reload"), and the admin's version badge shows
`build`'s first 7 characters and `builtAt` in the person's time (`account.json`'s `staff` says
who sees the badge). `status` is `degraded` when something needs a look, `error` (a `503`) when
the database is down.

### Uploads (from M2)

Audio never passes through the API. The app asks the API for an upload (`*.create`), gets back a
presigned `PUT` to Spaces, sends the file there, then tells the API it's done (`*.complete`):
- **the `PUT`** carries the headers the API returned (`Content-Type`; the browser sets
  `Content-Length` itself from the body). Spaces doesn't hold a presigned `PUT` to a size, so the
  server checks it: `*.complete` copies the file on the bucket to a key no upload link points at,
  and checks and processes only that copy (so a `PUT` sent again after it changes nothing). It
  refuses a file of the wrong size or hash, and deletes it at once, and an upload that's never
  completed is deleted within the hour. A second `*.complete` while the first is still checking
  answers as it is (still `uploading`): call it again in a moment. The private bucket's CORS
  allows the `PUT` from the app's origin;
- **locally,** the same flow runs against a signed `PUT /dev/upload/{key}` on the Laravel server,
  so nothing changes in the app;
- **a failed `PUT`** (network, expiry, a 4xx) is not resumed: the take stays in the device's
  outbox and the whole flow starts again when the connection is back.

### Changing the contract
- **Additive by default:** a new field or a new operation. The other agent agrees by message,
  and the change is committed with `CONTRACT_OK=1` and a line in `log.md`.
- **Breaking** (renaming, removing, changing a type or meaning): both agents agree, and the reason
  is logged in `docs/decisions.md`.

## JSON Schema: the subset we use

Small enough to check without a library (`server/tests/Contract/SchemaChecker.php`, and
`web/js/schema.js` in the app):

| Keyword | Meaning |
|---|---|
| `type` | `object`, `array`, `string`, `integer`, `number`, `boolean`, `null`, or a list of them |
| `properties`, `required` | An object's fields, and the ones that must be there |
| `additionalProperties: false` | No fields beyond `properties` |
| `items` | The schema of every item in an array |
| `enum` | The value is one of these |
| `format` | `date-time` (ISO 8601 with a time zone) or `uri`; checked only on strings |
| `$ref` | Another schema: `other.json`, `other.json#/$defs/x`, or `#/$defs/x` in the same file |
| `$defs` | Schemas named for `$ref` |

`$id`, `title` and `description` are notes for people; the checkers ignore them.

## Operations

Each operation is one function in `web/js/api.js` named after it, and one route in
`server/routes/api.php`. A test on each side checks this table against the code.

| Operation | Method and path | Who | Since |
|---|---|---|---|
| `admin.me` | `GET /api/admin/me` | An admin or editor who has passed the second factor | M1 |
| `admin.flags.list` | `GET /api/admin/flags` | An admin | M1 |
| `admin.flags.update` | `PUT /api/admin/flags/{name}` | An admin, in sudo | M1 |
| `admin.limits.list` | `GET /api/admin/limits` | An admin | M1 |
| `admin.limits.update` | `PUT /api/admin/limits/{limiter}/{rule}` | An admin, in sudo | M1 |
| `admin.blocklist.list` | `GET /api/admin/blocklist` | An admin | M1 |
| `admin.blocklist.create` | `POST /api/admin/blocklist` | An admin, in sudo | M1 |
| `admin.blocklist.delete` | `DELETE /api/admin/blocklist/{id}` | An admin, in sudo | M1 |
| `admin.securityEvents.list` | `GET /api/admin/security-events` | An admin | M1 |
| `admin.audit.list` | `GET /api/admin/audit` | An admin | M1 |
| `admin.people.list` | `GET /api/admin/people` | An admin | M3 |
| `admin.people.countries` | `GET /api/admin/people/countries` | An admin | L |
| `admin.people.show` | `GET /api/admin/people/{id}` | An admin | M3 |
| `admin.people.suspend` | `POST /api/admin/people/{id}/suspend` | An admin, in sudo | M3 |
| `admin.people.unsuspend` | `POST /api/admin/people/{id}/unsuspend` | An admin, in sudo | M3 |
| `admin.people.trust.update` | `PUT /api/admin/people/{id}/trust` | An admin, in sudo | M3 |
| `admin.people.storeTester.update` | `PUT /api/admin/people/{id}/store-tester` | An admin | Apps |
| `admin.storeTesters.list` | `GET /api/admin/store-testers` | An admin | Apps |
| `admin.people.recover` | `POST /api/admin/people/{id}/recover` | An admin, in sudo | M3 |
| `templates.catalogue` | `GET /api/templates` | Anyone | M2 |
| `courses.clips.list` | `GET /api/courses/{courseId}/clips` | Anyone | L |
| `beta.invite.show` | `GET /api/beta/invites/{token}` | Anyone with an invite link | M2 |
| `beta.invite.accept` | `POST /api/beta/invites/{token}/accept` | Anyone signed in, with the link | M2 |
| `beta.me.show` | `GET /api/beta/me` | A beta singer | M2 |
| `beta.agreement.show` | `GET /api/beta/agreement` | Anyone signed in | M2 |
| `beta.consent.create` | `POST /api/beta/consent` | A beta singer | M2 |
| `beta.credit.update` | `PUT /api/beta/credit` | A beta singer who has consented | M2 |
| `beta.parts.list` | `GET /api/beta/parts` | A beta singer | M2 |
| `beta.submissions.create` | `POST /api/beta/submissions` | A beta singer who has consented | M2 |
| `beta.submissions.complete` | `POST /api/beta/submissions/{id}/complete` | The singer who made it | M2 |
| `beta.feedback.create` | `POST /api/beta/feedback` | A beta singer | M2 |
| `admin.overview` | `GET /api/admin/overview` | An admin or editor | M2 |
| `admin.beta.singers.list` | `GET /api/admin/beta/singers` | An admin | M2 |
| `admin.beta.singers.create` | `POST /api/admin/beta/singers` | An admin | M2 |
| `admin.beta.singers.update` | `PATCH /api/admin/beta/singers/{id}` | An admin | M2 |
| `admin.beta.singers.link` | `POST /api/admin/beta/singers/{id}/link` | An admin | M2 |
| `admin.beta.singers.revoke` | `POST /api/admin/beta/singers/{id}/revoke` | An admin | M2 |
| `admin.beta.singers.paid` | `POST /api/admin/beta/singers/{id}/paid` | An admin, in sudo | M2 |
| `admin.beta.singers.forget` | `POST /api/admin/beta/singers/{id}/forget` | An admin, in sudo | M2 |
| `admin.beta.assignments.create` | `POST /api/admin/beta/singers/{id}/assignments` | An admin | M2 |
| `admin.beta.assignments.delete` | `DELETE /api/admin/beta/assignments/{id}` | An admin | M2 |
| `admin.beta.submissions.list` | `GET /api/admin/beta/submissions` | An admin or editor | M2 |
| `admin.beta.submissions.show` | `GET /api/admin/beta/submissions/{id}` | An admin or editor | M2 |
| `admin.beta.submissions.open` | `POST /api/admin/beta/submissions/{id}/open` | An admin or editor | M2 |
| `admin.beta.submissions.adjust` | `PATCH /api/admin/beta/submissions/{id}` | An admin | M2 |
| `admin.beta.submissions.accept` | `POST /api/admin/beta/submissions/{id}/accept` | An admin | M2 |
| `admin.beta.submissions.retake` | `POST /api/admin/beta/submissions/{id}/retake` | An admin or editor | M2 |
| `admin.beta.submissions.reject` | `POST /api/admin/beta/submissions/{id}/reject` | An admin | M2 |
| `admin.beta.submissions.publish` | `POST /api/admin/beta/submissions/{id}/publish` | An admin | M2 |
| `admin.beta.guides.list` | `GET /api/admin/beta/guides` | An admin or editor | M2 |
| `admin.beta.clips.list` | `GET /api/admin/beta/clips` | An admin or editor | L |
| `admin.beta.bar.show` | `GET /api/admin/beta/bar` | An admin | M2 |
| `admin.beta.bar.update` | `PATCH /api/admin/beta/bar` | An admin | M2 |
| `admin.beta.guides.default` | `POST /api/admin/beta/guides/{id}/default` | An admin | M2 |
| `admin.beta.guides.delete` | `DELETE /api/admin/beta/guides/{id}` | An admin, in sudo | M2 |
| `admin.beta.feedback.list` | `GET /api/admin/beta/feedback` | An admin or editor | M2 |
| `admin.beta.feedback.read` | `POST /api/admin/beta/feedback/{id}/read` | An admin or editor | M2 |
| `auth.config` | `GET /api/auth/config` | Anyone | M3 |
| `auth.register` | `POST /api/auth/register` | Anyone (signed out) | M3 |
| `auth.link.send` | `POST /api/auth/link` | Anyone (signed out) | M3 |
| `auth.link.open` | `POST /api/auth/link/open` | Anyone | M3 |
| `auth.verify` | `POST /api/auth/verify` | Anyone with the link | M3 |
| `auth.verification.resend` | `POST /api/auth/verification` | Anyone | M3 |
| `auth.signin` | `POST /api/auth/sign-in` | Anyone (signed out) | M3 |
| `auth.signout` | `POST /api/auth/sign-out` | Anyone signed in | M3 |
| `auth.signout.everywhere` | `POST /api/auth/sign-out-everywhere` | Anyone signed in | M3 |
| `auth.password.forgot` | `POST /api/auth/password/forgot` | Anyone | M3 |
| `auth.password.reset` | `POST /api/auth/password/reset` | Anyone with the link | M3 |
| `auth.app.signin` | `POST /api/auth/app/sign-in` | An app (signed out) | Apps |
| `auth.app.google` | `POST /api/auth/app/google` | An app | Apps |
| `auth.app.apple` | `POST /api/auth/app/apple` | An app | Apps |
| `auth.app.verify` | `POST /api/auth/app/verify` | An app, with the link | Apps |
| `auth.app.refresh` | `POST /api/auth/app/refresh` | An app's device (its refresh token) | Apps |
| `auth.app.signout` | `POST /api/auth/app/sign-out` | An app's device | Apps |
| `account.devices.list` | `GET /api/account/devices` | An app, signed in | Apps |
| `account.devices.delete` | `DELETE /api/account/devices/{id}` | An app, signed in | Apps |
| `account.devices.push.update` | `PUT /api/account/devices/current/push` | An app, signed in | Apps |
| `account.devices.push.delete` | `DELETE /api/account/devices/current/push` | An app, signed in | Apps |
| `appPass.claim` | `POST /api/app-pass` | An app | Apps |
| `account.show` | `GET /api/account` | Anyone signed in | M3 |
| `account.update` | `PATCH /api/account` | Anyone signed in | M3 |
| `account.terms.accept` | `POST /api/account/terms` | Anyone signed in | L |
| `account.handle.check` | `GET /api/account/handle` | Anyone signed in | M3 |
| `account.email.update` | `POST /api/account/email` | Anyone signed in | M3 |
| `account.email.confirm` | `POST /api/account/email/confirm` | Anyone with the link | M3 |
| `account.email.revert` | `POST /api/account/email/revert` | Anyone with the link | M3 |
| `account.password.update` | `PUT /api/account/password` | Anyone signed in | M3 |
| `account.methods.delete` | `DELETE /api/account/methods/{method}` | Anyone signed in | M3 |
| `account.avatar.update` | `POST /api/account/avatar` | Anyone signed in | M3 |
| `account.avatar.delete` | `DELETE /api/account/avatar` | Anyone signed in | M3 |
| `account.export.create` | `POST /api/account/export` | Anyone signed in | M3 |
| `account.export.show` | `GET /api/account/export` | Anyone signed in | M3 |
| `account.delete` | `DELETE /api/account` | Anyone signed in | M3 |
| `sync.pull` | `GET /api/sync` | Anyone signed in | M4 |
| `projects.show` | `GET /api/projects/{id}` | The owner | M4 |
| `projects.save` | `PUT /api/projects/{id}` | The owner (or anyone signed in, to make one) | M4 |
| `projects.delete` | `DELETE /api/projects/{id}` | The owner | M4 |
| `projects.restore` | `POST /api/projects/{id}/restore` | The owner | M4 |
| `parts.save` | `PUT /api/parts/{id}` | The project's owner | M4 |
| `parts.delete` | `DELETE /api/parts/{id}` | The project's owner | M4 |
| `takes.create` | `POST /api/takes` | The project's owner | M4 |
| `takes.complete` | `POST /api/takes/{id}/complete` | The project's owner | M4 |
| `takes.show` | `GET /api/takes/{id}` | The project's owner | M4 |
| `takes.restore` | `POST /api/takes/{id}/restore` | The project's owner | M4 |
| `takes.delete` | `DELETE /api/takes/{id}` | The project's owner, or the singer who recorded it | L |
| `parts.takes.list` | `GET /api/parts/{id}/takes` | The project's owner or a singer on it | L |
| `parts.takes.use` | `POST /api/parts/{id}/takes/{takeId}/use` | The project's owner, or the part's maker | L |
| `bin.list` | `GET /api/bin` | Anyone signed in | M4 |
| `bin.purge` | `DELETE /api/bin/{kind}/{id}` | Anyone signed in (their own) | M4 |
| `bin.purgeTakes` | `DELETE /api/projects/{id}/older-takes` | Anyone signed in (their own takes) | M4 |
| `usage.show` | `GET /api/usage` | Anyone signed in | M4 |
| `account.learning.show` | `GET /api/account/learning` | Anyone signed in | M4 |
| `account.learning.update` | `PUT /api/account/learning` | Anyone signed in | M4 |
| `account.learning.reset` | `POST /api/account/learning/reset` | Anyone signed in | Course |
| `account.learning.undoReset` | `POST /api/account/learning/reset/undo` | Anyone signed in | Course |
| `templates.show` | `GET /api/templates/{id}` | Anyone who may start the song (signed out too, for the song of the month) | M5 |
| `billing.prices` | `GET /api/billing/prices` | Anyone | M5 |
| `billing.show` | `GET /api/billing` | Anyone signed in | M5 |
| `billing.checkout` | `POST /api/billing/checkout` | Anyone signed in, verified | M5 |
| `billing.portal` | `POST /api/billing/portal` | A subscriber | M5 |
| `billing.store.sync` | `POST /api/billing/store` | Anyone signed in (an app) | Apps |
| `projects.editable.update` | `PUT /api/projects/editable` | Anyone signed in | M5 |
| `scores.create` | `POST /api/scores` | A Plus account | M5 |
| `scores.list` | `GET /api/scores` | A Plus account | M5 |
| `reminders.show` | `GET /api/reminders` | Anyone signed in | M5 |
| `reminders.update` | `PUT /api/reminders` | Anyone signed in | M5 |
| `reminders.devices.create` | `POST /api/reminders/devices` | Anyone signed in | M5 |
| `reminders.devices.update` | `PUT /api/reminders/devices/{id}` | The device, by its token | M5 |
| `reminders.devices.delete` | `DELETE /api/reminders/devices/{id}` | The device, by its token | M5 |
| `admin.billing.disputes.list` | `GET /api/admin/billing/disputes` | An admin | M5 |
| `harmonies.create` | `POST /api/harmonies` | Anyone signed in, verified | M6 |
| `harmonies.complete` | `POST /api/harmonies/{id}/complete` | The owner | M6 |
| `harmonies.list` | `GET /api/harmonies` | Anyone signed in | M6 |
| `harmonies.show` | `GET /api/harmonies/{id}` | The owner | M6 |
| `harmonies.update` | `PATCH /api/harmonies/{id}` | The owner | M6 |
| `harmonies.delete` | `DELETE /api/harmonies/{id}` | The owner | M6 |
| `videos.create` | `POST /api/harmonies/{id}/videos` | The owner | M6 |
| `videos.show` | `GET /api/videos/{id}` | The owner | M6 |
| `reports.create` | `POST /api/reports` | Anyone (signed out too) | M6 |
| `admin.reports.list` | `GET /api/admin/reports` | An admin | M6 |
| `admin.harmonies.hide` | `POST /api/admin/harmonies/{id}/hide` | An admin | M6 |
| `admin.harmonies.restore` | `POST /api/admin/harmonies/{id}/restore` | An admin | M6 |
| `admin.harmonies.remove` | `POST /api/admin/harmonies/{id}/remove` | An admin, in sudo | M6 |
| `templates.mine` | `GET /api/templates/mine` | Anyone signed in | M7 |
| `templates.save` | `PUT /api/templates/{id}` | The owner (or anyone signed in, to make one) | M7 |
| `templates.update` | `PATCH /api/templates/{id}` | The owner | M7 |
| `templates.delete` | `DELETE /api/templates/{id}` | The owner | M7 |
| `templates.recordings.create` | `POST /api/templates/{id}/recordings` | The owner, with Plus | M7 |
| `templates.recordings.complete` | `POST /api/template-recordings/{id}/complete` | The owner | M7 |
| `templates.recordings.delete` | `DELETE /api/template-recordings/{id}` | The owner | M7 |
| `projects.shared` | `GET /api/projects/shared` | Anyone signed in | M7 |
| `projects.members.list` | `GET /api/projects/{id}/members` | The owner and the project's singers | M7 |
| `projects.members.delete` | `DELETE /api/projects/{id}/members/{memberId}` | The owner, or the singer themselves | M7 |
| `invites.create` | `POST /api/projects/{id}/invites` | The owner | M7 |
| `invites.list` | `GET /api/projects/{id}/invites` | The owner | M7 |
| `invites.revoke` | `DELETE /api/invites/{id}` | The owner | M7 |
| `invites.show` | `GET /api/join/{token}` | Anyone with the link | M7 |
| `invites.accept` | `POST /api/join/{token}` | Anyone signed in, with the link | M7 |
| `groups.list` | `GET /api/groups` | Anyone signed in | M7 |
| `groups.create` | `POST /api/groups` | Anyone signed in, with Plus | M7 |
| `groups.show` | `GET /api/groups/{id}` | The director and the members | M7 |
| `groups.update` | `PATCH /api/groups/{id}` | The director | M7 |
| `groups.delete` | `DELETE /api/groups/{id}` | The director | M7 |
| `groups.link.create` | `POST /api/groups/{id}/link` | The director | M7 |
| `groups.join.show` | `GET /api/choir/{token}` | Anyone with the link | M7 |
| `groups.join` | `POST /api/choir/{token}` | Anyone signed in, with the link | M7 |
| `groups.members.delete` | `DELETE /api/groups/{id}/members/{memberId}` | The director, or the member themselves | M7 |
| `groups.songs.create` | `POST /api/groups/{id}/songs` | The director | M7 |
| `groups.songs.delete` | `DELETE /api/groups/{id}/songs/{templateId}` | The director | M7 |
| `groups.assignments.update` | `PUT /api/groups/{id}/songs/{templateId}/assignments` | The director | M7 |
| `coach.application.show` | `GET /api/coach/application` | Anyone signed in | M8 |
| `coach.application.create` | `POST /api/coach/application` | Anyone signed in, verified | M8 |
| `coach.profile.show` | `GET /api/coach/profile` | An approved coach | M8 |
| `coach.profile.update` | `PATCH /api/coach/profile` | An approved coach | M8 |
| `coach.connect.create` | `POST /api/coach/connect` | An approved coach | M8 |
| `coach.connect.show` | `GET /api/coach/connect` | An approved coach | M8 |
| `coach.offerings.list` | `GET /api/coach/offerings` | An approved coach | M8 |
| `coach.offerings.create` | `POST /api/coach/offerings` | An approved coach | M8 |
| `coach.offerings.update` | `PATCH /api/coach/offerings/{id}` | An approved coach | M8 |
| `coach.packs.list` | `GET /api/coach/packs` | An approved coach | M8 |
| `coach.packs.save` | `PUT /api/coach/packs/{id}` | An approved coach | M8 |
| `coach.packs.submit` | `POST /api/coach/packs/{id}/submit` | An approved coach | M8 |
| `coaches.show` | `GET /api/coaches/{handle}` | Anyone | M8 |
| `engagements.create` | `POST /api/engagements` | Anyone signed in, verified | M8 |
| `engagements.list` | `GET /api/engagements` | Anyone signed in | M8 |
| `engagements.show` | `GET /api/engagements/{id}` | Either side | M8 |
| `engagements.accept` | `POST /api/engagements/{id}/accept` | The coach | M8 |
| `engagements.decline` | `POST /api/engagements/{id}/decline` | The coach | M8 |
| `engagements.cancel` | `POST /api/engagements/{id}/cancel` | The singer (or either side, for a teacher) | M8 |
| `engagements.report` | `POST /api/engagements/{id}/report` | Either side | M8 |
| `reviews.create` | `POST /api/engagements/{id}/reviews` | The singer | M8 |
| `reviews.save` | `PUT /api/reviews/{id}` | The coach | M8 |
| `reviews.deliver` | `POST /api/reviews/{id}/deliver` | The coach | M8 |
| `reviews.unsend` | `POST /api/reviews/{id}/unsend` | The coach | M8 |
| `reviews.followup` | `POST /api/reviews/{id}/follow-up` | The singer | M8 |
| `reviews.answer` | `POST /api/reviews/{id}/answer` | The coach | M8 |
| `reviews.media.create` | `POST /api/reviews/{id}/media` | The coach | M8 |
| `reviews.media.complete` | `POST /api/review-media/{id}/complete` | The coach | M8 |
| `messages.list` | `GET /api/engagements/{id}/messages` | Either side | M8 |
| `messages.create` | `POST /api/engagements/{id}/messages` | Either side | M8 |
| `ratings.create` | `POST /api/engagements/{id}/rating` | The singer | M8 |
| `ratings.reply` | `POST /api/ratings/{id}/reply` | The coach | M8 |
| `packs.mine` | `GET /api/packs/mine` | Anyone signed in | M8 |
| `teacher.links.create` | `POST /api/teacher-links` | A Plus account | M8 |
| `teacher.links.show` | `GET /api/teacher/{token}` | Anyone with the link | M8 |
| `teacher.links.accept` | `POST /api/teacher/{token}` | Anyone signed in, with the link | M8 |
| `admin.coaching.applications.list` | `GET /api/admin/coaching/applications` | An admin | M8 |
| `admin.coaching.applications.decide` | `POST /api/admin/coaching/applications/{id}` | An admin | M8 |
| `admin.coaching.coaches.update` | `PUT /api/admin/coaching/coaches/{id}` | An admin, in sudo | M8 |
| `admin.coaching.packs.list` | `GET /api/admin/coaching/packs` | An admin | M8 |
| `admin.coaching.packs.decide` | `POST /api/admin/coaching/packs/{id}` | An admin | M8 |
| `admin.coaching.disputes.list` | `GET /api/admin/coaching/disputes` | An admin | M8 |
| `admin.coaching.refund` | `POST /api/admin/coaching/engagements/{id}/refund` | An admin, in sudo | M8 |
| `eventSchedule.connection.show` | `GET /api/event-schedule/connection` | Anyone signed in | M9 |
| `eventSchedule.connect.create` | `POST /api/event-schedule/connect` | Anyone signed in | M9 |
| `eventSchedule.connect.finish` | `POST /api/event-schedule/connect/finish` | Anyone signed in | M9 |
| `eventSchedule.connect.key` | `POST /api/event-schedule/connect/key` | Anyone signed in | M9 |
| `eventSchedule.connection.update` | `PATCH /api/event-schedule/connection` | Anyone signed in, connected | M9 |
| `eventSchedule.connection.delete` | `DELETE /api/event-schedule/connection` | Anyone signed in, connected | M9 |
| `eventSchedule.schedules.list` | `GET /api/event-schedule/schedules` | Anyone signed in, connected | M9 |
| `eventSchedule.types.list` | `GET /api/event-schedule/appointment-types` | Anyone signed in, connected | M9 |
| `coaches.slots` | `GET /api/coaches/{handle}/offerings/{id}/slots` | Anyone | M9 |
| `groups.eventSchedule.update` | `PUT /api/groups/{id}/event-schedule` | The group's director | M9 |
| `groups.eventSchedule.delete` | `DELETE /api/groups/{id}/event-schedule` | The group's director | M9 |
| `groups.eventSchedule.members` | `GET /api/groups/{id}/event-schedule/members` | The group's director | M9 |
| `groups.rehearsals.list` | `GET /api/groups/{id}/rehearsals` | The group's members | M9 |
| `groups.rehearsals.songs` | `PUT /api/groups/{id}/rehearsals/{event}/songs` | The group's director | M9 |
| `admin.eventSchedule.show` | `GET /api/admin/event-schedule` | An admin | M9 |
| `invoiceNinja.connection.show` | `GET /api/coach/invoice-ninja` | An approved coach | M10 |
| `invoiceNinja.connect` | `POST /api/coach/invoice-ninja` | An approved coach | M10 |
| `invoiceNinja.connection.update` | `PATCH /api/coach/invoice-ninja` | An approved coach, connected | M10 |
| `invoiceNinja.connection.delete` | `DELETE /api/coach/invoice-ninja` | An approved coach, connected | M10 |
| `invoiceNinja.records.list` | `GET /api/coach/invoice-ninja/records` | An approved coach, connected | M10 |
| `invoiceNinja.records.retry` | `POST /api/coach/invoice-ninja/records/{id}/retry` | An approved coach, connected | M10 |
| `admin.social.list` | `GET /api/admin/social` | An admin | M10 |
| `admin.social.feature` | `POST /api/admin/social/harmonies/{id}` | An admin | M10 |
| `admin.social.approve` | `POST /api/admin/social/{id}/approve` | An admin | M10 |
| `admin.social.cancel` | `POST /api/admin/social/{id}/cancel` | An admin | M10 |
| `admin.social.retry` | `POST /api/admin/social/{id}/retry` | An admin | M10 |
| `admin.books.show` | `GET /api/admin/books` | An admin | M10 |
| `admin.books.connect` | `POST /api/admin/books` | An admin, in sudo | M10 |
| `admin.books.delete` | `DELETE /api/admin/books` | An admin, in sudo | M10 |
| `admin.books.records.list` | `GET /api/admin/books/records` | An admin | M10 |
| `admin.books.records.retry` | `POST /api/admin/books/records/{id}/retry` | An admin | M10 |
| `admin.books.plusInvoices.create` | `POST /api/admin/books/plus-invoices` | An admin, in sudo | M10 |
| `counts.create` | `POST /api/counts` | Anyone | L |
| `support.create` | `POST /api/support` | Anyone | L |
| `notices.list` | `GET /api/notices` | Anyone signed in | L |
| `notices.show` | `GET /api/notices/{id}` | The person, or anyone with the link | L |
| `notices.appeal` | `POST /api/notices/{id}/appeal` | The person, or anyone with the link | L |
| `admin.support.list` | `GET /api/admin/support` | An admin | L |
| `admin.support.show` | `GET /api/admin/support/{id}` | An admin | L |
| `admin.support.reply` | `POST /api/admin/support/{id}/reply` | An admin | L |
| `admin.support.update` | `PATCH /api/admin/support/{id}` | An admin | L |
| `admin.appeals.list` | `GET /api/admin/appeals` | An admin | L |
| `admin.appeals.decide` | `POST /api/admin/appeals/{id}` | An admin, in sudo | L |
| `admin.people.payment` | `GET /api/admin/people/{id}/payment` | An admin | L |
| `admin.people.refund` | `POST /api/admin/people/{id}/refund` | An admin, in sudo | L |
| `templates.finishers` | `GET /api/templates/{id}/finishers` | Anyone | L |
| `explore.list` | `GET /api/explore` | Anyone | N1 |
| `profiles.show` | `GET /api/profiles/{handle}` | Anyone | N1 |
| `profiles.harmonies` | `GET /api/profiles/{handle}/harmonies` | Anyone | N1 |
| `likes.create` | `PUT /api/harmonies/{id}/like` | Anyone signed in | N1 |
| `likes.delete` | `DELETE /api/harmonies/{id}/like` | Anyone signed in | N1 |
| `comments.list` | `GET /api/harmonies/{id}/comments` | Anyone | N1 |
| `comments.create` | `POST /api/harmonies/{id}/comments` | Anyone signed in | N1 |
| `comments.delete` | `DELETE /api/comments/{id}` | Its author, or the harmony's owner | N1 |
| `follows.create` | `PUT /api/profiles/{handle}/follow` | Anyone signed in | N1 |
| `follows.delete` | `DELETE /api/profiles/{handle}/follow` | Anyone signed in | N1 |
| `blocks.create` | `PUT /api/profiles/{handle}/block` | Anyone signed in | N1 |
| `blocks.delete` | `DELETE /api/profiles/{handle}/block` | Anyone signed in | N1 |
| `blocks.list` | `GET /api/account/blocks` | Anyone signed in | N1 |
| `harmonies.consent.ask` | `POST /api/harmonies/{id}/consent` | The owner | N1 |
| `consentRequests.list` | `GET /api/consent-requests` | Anyone signed in | N1 |
| `consentRequests.decide` | `POST /api/consent-requests/{id}` | The friend asked | N1 |
| `notifications.list` | `GET /api/notifications` | Anyone signed in | N1 |
| `notifications.read` | `POST /api/notifications/read` | Anyone signed in | N1 |
| `admin.gallery.held` | `GET /api/admin/gallery/held` | An admin | N1 |
| `admin.gallery.decide` | `POST /api/admin/gallery/{kind}/{id}` | An admin | N1 |
| `admin.harmonies.feature` | `PUT /api/admin/harmonies/{id}/feature` | An admin, in sudo | N1 |
| `admin.gallery.guides.create` | `POST /api/admin/gallery/harmony/{id}/guides` | An admin, in sudo | N1 |
| `leagues.current` | `GET /api/leagues/current` | Anyone signed in | N2 |
| `leagues.join` | `POST /api/leagues/membership` | Anyone signed in | N2 |
| `leagues.leave` | `DELETE /api/leagues/membership` | Anyone signed in | N2 |
| `friends.leaderboard` | `GET /api/leagues/friends` | Anyone signed in | N2 |
| `boards.scores.submit` | `POST /api/boards/scores` | Anyone signed in | L |
| `boards.show` | `GET /api/boards/{template}/{part}` | Anyone signed in | L |
| `boards.global.update` | `PUT /api/boards/global` | Anyone signed in | L |
| `boards.challenges.create` | `POST /api/boards/challenges` | Anyone signed in | L |
| `boards.challenges.show` | `GET /api/boards/challenges/{token}` | Anyone with the link | L |
| `boards.challenges.accept` | `POST /api/boards/challenges/{token}/accept` | Anyone signed in | L |
| `admin.boards.flags.list` | `GET /api/admin/boards/flags` | An admin | L |
| `admin.boards.scores.review` | `POST /api/admin/boards/scores/{id}/review` | An admin | L |
| `admin.boards.scores.delete` | `DELETE /api/admin/boards/scores/{id}` | An admin, in sudo | L |
| `admin.boards.reset` | `POST /api/admin/people/{id}/boards/reset` | An admin, in sudo | L |
| `library.list` | `GET /api/library` | Anyone | N3 |
| `templates.publish` | `PUT /api/templates/{id}/publication` | The owner | N3 |
| `templates.unpublish` | `DELETE /api/templates/{id}/publication` | The owner | N3 |
| `templates.rate` | `PUT /api/templates/{id}/rating` | Anyone signed in who sang it | N3 |
| `templates.unrate` | `DELETE /api/templates/{id}/rating` | Anyone signed in | N3 |
| `admin.library.held` | `GET /api/admin/library/held` | An admin | N3 |
| `admin.library.decide` | `POST /api/admin/library/{id}` | An admin | N3 |
| `admin.library.feature` | `PUT /api/admin/library/{id}/feature` | An admin, in sudo | N3 |
| `ideas.list` | `GET /api/ideas` | Anyone | Ideas |
| `ideas.mine` | `GET /api/ideas/mine` | Anyone signed in | Ideas |
| `ideas.show` | `GET /api/ideas/{id}` | Anyone | Ideas |
| `ideas.create` | `POST /api/ideas` | Anyone signed in | Ideas |
| `ideas.delete` | `DELETE /api/ideas/{id}` | Its writer | Ideas |
| `ideas.voices.create` | `POST /api/ideas/{id}/voices` | Anyone signed in | Ideas |
| `ideas.voices.complete` | `POST /api/idea-voices/{id}/complete` | Its singer | Ideas |
| `ideas.voices.delete` | `DELETE /api/idea-voices/{id}` | Its singer, or the idea's writer | Ideas |
| `admin.ideas.held` | `GET /api/admin/ideas/held` | An admin | Ideas |
| `admin.ideas.decide` | `POST /api/admin/ideas/{id}` | An admin | Ideas |
| `coaches.list` | `GET /api/coaches` | Anyone | N4 |
| `admin.coaches.feature` | `PUT /api/admin/coaches/{handle}/feature` | An admin, in sudo | N4 |
| `harmonies.open` | `PUT /api/harmonies/{id}/open` | The owner | N5 |
| `harmonies.stems` | `GET /api/harmonies/{id}/stems` | Anyone signed in | N5 |
| `harmonies.fills.list` | `GET /api/harmonies/{id}/fills` | Anyone | N5 |
| `choirs.show` | `GET /api/choirs/{song}` | Anyone | N6 |
| `choirs.voices.list` | `GET /api/choirs/{song}/voices` | Anyone | N6 |
| `choirs.join` | `POST /api/choirs/{song}/voices` | Anyone signed in | N6 |
| `choirs.leave` | `DELETE /api/choirs/{song}/voices/mine` | Anyone signed in | N6 |
| `admin.choirs.voices.held` | `GET /api/admin/choir-voices/held` | An admin | N6 |
| `admin.choirs.voices.approve` | `POST /api/admin/choir-voices/{id}/approve` | An admin | N6 |
| `admin.choirs.voices.remove` | `POST /api/admin/choir-voices/{id}/remove` | An admin | N6 |
| `requests.list` | `GET /api/requests` | Anyone | N7 |
| `requests.create` | `POST /api/requests` | Anyone signed in | N7 |
| `requests.vote` | `PUT /api/requests/{id}/vote` | Anyone signed in | N7 |
| `requests.unvote` | `DELETE /api/requests/{id}/vote` | Anyone signed in | N7 |
| `admin.requests.list` | `GET /api/admin/requests` | An admin | N7 |
| `admin.requests.update` | `PATCH /api/admin/requests/{id}` | An admin | N7 |
| `earlyAccess.show` | `GET /api/early/{code}` | Anyone | L |
| `admin.earlyAccess.show` | `GET /api/admin/early-access` | An admin | L |
| `admin.earlyAccess.update` | `PATCH /api/admin/early-access` | An admin | L |
| `admin.earlyAccess.replace` | `POST /api/admin/early-access/replace` | An admin, in sudo | L |
| `admin.preview.show` | `GET /api/admin/preview` | An admin | L |
| `admin.preview.update` | `PATCH /api/admin/preview` | An admin | L |
| `admin.preview.replace` | `POST /api/admin/preview/replace` | An admin, in sudo | L |
| `inviteLink.show` | `GET /api/invite/{code}` | Anyone | L |
| `admin.invite.show` | `GET /api/admin/invite` | An admin | L |
| `admin.invite.update` | `PATCH /api/admin/invite` | An admin | L |
| `admin.invite.replace` | `POST /api/admin/invite/replace` | An admin, in sudo | L |
| `admin.mudeo.show` | `GET /api/admin/mudeo` | An admin | L |
| `admin.mudeo.update` | `PATCH /api/admin/mudeo` | An admin, in sudo | L |
| `admin.mudeo.import` | `POST /api/admin/mudeo/import` | An admin, in sudo | L |
| `admin.mudeo.send` | `POST /api/admin/mudeo/send` | An admin, in sudo | L |
| `admin.mudeo.test` | `POST /api/admin/mudeo/test` | An admin | L |
| `admin.mudeo.next` | `GET /api/admin/mudeo/next` | An admin | L |
| `admin.mudeo.batches.create` | `POST /api/admin/mudeo/batches` | An admin | L |
| `admin.mudeo.batches.undo` | `DELETE /api/admin/mudeo/batches/latest` | An admin | L |
| `admin.mudeo.mark` | `POST /api/admin/mudeo/mark` | An admin | L |
| `admin.mudeo.contacts` | `GET /api/admin/mudeo/contacts` | An admin | L |
| `admin.network.show` | `GET /api/admin/network` | An admin | L |
| `admin.counts.show` | `GET /api/admin/counts` | An admin | L |
| `admin.funnel.show` | `GET /api/admin/funnel` | An admin | L |
| `admin.diagnostics.country` | `GET /api/admin/diagnostics/country` | An admin | L |
| `admin.network.update` | `PUT /api/admin/network/{feature}` | An admin, in sudo | L |

**Every `admin.*` operation** may also answer `401 unauthenticated`, `403 not_admin` and `403
second_factor_required`, as `admin.me` does, and one marked "in sudo" answers `403 sudo_required`
when the last second-factor check is older than the sudo window: the app sends the person to
`/admin/second-factor?sudo=1`, which brings them back. These cases aren't repeated in each
operation's table, and the mock makes them for any `admin.*` operation.

### `admin.me`

Who is signed in to the admin, so the admin screens can show their name and which tools their role
has. The admin signs in on the server's own pages (`/admin/sign-in`, then the second factor at
`/admin/second-factor`), which send them on to `/admin` (the app). If this call refuses, the app
sends the person to `/admin/sign-in?intended=<the admin page they were on>`.

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.me.json` | `admin.me.200.json` |
| 401 | `unauthenticated` | `error.json` | `admin.me.unauthenticated.json` |
| 403 | `not_admin` | `error.json` | `admin.me.not_admin.json` |
| 403 | `second_factor_required` | `error.json` | `admin.me.second_factor_required.json` |

- **`role`** is `admin` or `editor`. An editor works the queues; money, bans, deletes, settings and
  switches are the admin's (`docs/operations-plan.md`, "A moderator role").
- **`sudoUntil`** is when the last second-factor check stops counting for sensitive actions
  (`null` once it has). The app can say "Confirm it's you" before a sensitive action instead of
  waiting for the refusal.

### The trust and safety screen (`admin.flags.*`, `admin.limits.*`, `admin.blocklist.*`, `admin.securityEvents.list`, `admin.audit.list`)

The admin's controls for an attack (`docs/trust-and-safety-plan.md`, "Admin controls"). Names,
kinds, values, limiters, rules and types are stable keys that the app words; `reason` fields are
the admin's own words, shown as they are. Every change is audit-logged with its reason.

#### `admin.flags.list`

Every switch and dark feature, in the order of `server/config/flags.php`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.flags.list.json` | `admin.flags.list.200.json` |

#### `admin.flags.update`

Turn a switch on or off, or open a dark feature. **Request:** `{ "value": "on", "reason": "A wave
of bot sign-ups" }`: `value` is one of the flag's `values`; `reason` is required (up to 500
characters).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.flags.update.json` | `admin.flags.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.flags.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.flags.update.validation_failed.json` |

#### `admin.limits.list`

Every limiter's rules, in the order of `server/config/limits.php`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.limits.list.json` | `admin.limits.list.200.json` |

#### `admin.limits.update`

Change one rule's maximum live, or put it back. **Request:** `{ "max": 2, "reason": "..." }`:
`max` is a whole number from 1 to 100000, or `null` for the default; `reason` is required.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.limits.update.json` | `admin.limits.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.limits.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.limits.update.validation_failed.json` |

#### `admin.blocklist.list`

The blocklist, newest first, 50 a page, including expired entries (`expiresAt` in the past).
**Request:** `?type=` (one of the block types) and `?cursor=`, both optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.blocklist.list.json` | `admin.blocklist.list.200.json` |

#### `admin.blocklist.create`

**Request:** `{ "type": "ip_range", "value": "203.0.113.0/24", "reason": "...", "expiresAt": null }`:
- `type` is one of `email`, `email_domain`, `ip`, `ip_range`, `card_fingerprint`;
- `value` must be a valid one of its type (an email, a domain, an IP address, a CIDR range, a
  fingerprint), and is stored lower-cased for emails and domains;
- `reason` is required (up to 500 characters); `expiresAt` is optional, in the future.

The same type and value twice is a `422` on `value`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 | | `admin.blocklist.create.json` | `admin.blocklist.create.201.json` |
| 422 | `validation_failed` | `error.json` | `admin.blocklist.create.validation_failed.json` |

#### `admin.blocklist.delete`

Lift a block. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `admin.blocklist.delete.not_found.json` |

#### `admin.securityEvents.list`

What happened, newest first, 50 a page. **Request:** `?type=`, `?user=<Sqid>` and `?cursor=`, all
optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.securityEvents.list.json` | `admin.securityEvents.list.200.json` |

#### `admin.audit.list`

Every admin, moderation and money action, newest first, 50 a page. **Request:** `?action=` (a
prefix: `flag.`, `block.`, `limit.`), `?user=` (an account's id: the entries about them or done by
them) and `?cursor=`, all optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.audit.list.json` | `admin.audit.list.200.json` |

### People (M3)

Finding someone, and the admin's actions on their account (`docs/backend-plan.md` A2;
`docs/operations-plan.md`, "Getting back into an account"). Every change is audit-logged with its
reason. Staff accounts (admins and editors) can't be suspended or recovered here (`409
staff_account`): that's the console's. A person's sign-in history is
`admin.securityEvents.list?user=<id>`.

#### `admin.people.list`

Accounts, newest first, 50 a page. **Request:** `?q=` (part of an email, a handle or a name),
`?suspended=1` (or `0`), `?adult=1` (or `0`: under 18; no birth date matches neither), `?plan=plus`
(or `free`), `?role=singer` (a beta singer) or `tester` (a store tester), `?sort=newest` (or
`active`: last seen first) and `?cursor=`, all optional. Each row has `adult` (true, false, or
null with no birth date; never the date), `betaSinger` and `lastActiveAt`; the person page
(`admin.people.show` and its actions) adds `plan` (`plus`, `source`, `until`) and `counts`
(`publicHarmonies`, `groups`), never their recordings or private songs. `stats` is how much they use
the app, as counts and times only: every row has `songs`, `lessonsDone`, `streakWeeks` and
`lastActivity` (`kind`: `take`, `practice`, `song`, `share` or `sign_in`, and `at`; no titles); the
person page adds `takes`, `stars`, `xp`, `level`, `practiceMinutes` (`lastWeek`, `total`) and
`storageBytes`. `country` is the country they last signed in from (Cloudflare's two letters, null
when not known); `?country=GB` lists one country's people.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.list.json` | `admin.people.list.200.json` |

#### `admin.people.countries`

How many people last signed in from each country, most first: counts only, with `country` null for
those not known. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.countries.json` | `admin.people.countries.200.json` |

#### `admin.people.show`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.show.200.json` |
| 404 | `not_found` | `error.json` | `admin.people.show.not_found.json` |

#### `admin.people.suspend`

They can't sign in by any route, and every session ends. **Request:** `{ "reason": "..." }` (up to
500 characters). Suspending someone already suspended changes nothing.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.suspend.200.json` |
| 409 | `staff_account` | `error.json` | `admin.people.suspend.staff_account.json` |
| 422 | `validation_failed` | `error.json` | `admin.people.suspend.validation_failed.json` |

#### `admin.people.unsuspend`

**Request:** `{ "reason": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.unsuspend.200.json` |

#### `admin.people.trust.update`

Set the trust level by hand (0 to 3), or `null` to go back to the earned one. **Request:**
`{ "level": 1, "note": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.trust.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.people.trust.update.validation_failed.json` |

#### `admin.people.storeTester.update`

Mark an account as a store tester, or not: its sandbox App Store and Google Play purchases (App
Review's demo account, TestFlight testers) give Plus; everyone else's sandbox purchases give
nothing. Audit-logged. **Request:** `{ "tester": true, "note": "App Review" }` (`note` optional).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.storeTester.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.people.storeTester.update.validation_failed.json` |

#### `admin.storeTesters.list`

The accounts marked as store testers, most recent first. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.storeTesters.list.json` | `admin.storeTesters.list.200.json` |

#### `admin.people.recover`

Someone who lost their Google or Apple account, once support has checked it's them with what only
the owner knows (a recent receipt, their projects' titles, the email on the account): the account
gets the address they can read now (verified), that address gets a link to choose a password,
the old address is told, and every session ends. **Request:** `{ "email": "...", "reason":
"how it was checked" }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.people.show.json` | `admin.people.recover.200.json` |
| 409 | `email_taken` | `error.json` | `admin.people.recover.email_taken.json` |
| 409 | `staff_account` | `error.json` | `admin.people.recover.staff_account.json` |
| 422 | `validation_failed` | `error.json` | `admin.people.recover.validation_failed.json` |

### Templates and the beta (M2)

The catalogue and the beta singers (`docs/beta-singers-plan.md`, and Hillel's decision "beta
singers have accounts"). **A beta singer is an account** (Google, Apple, or email and password)
with a singer record, which an invite link attaches to it once:
- the link (`/beta/<token>`) opens the invite page (`beta.invite.show`): who it's for, then Sign up
  or Sign in with the token carried through (`invite` on `auth.register` and on the Google or Apple
  sign-in navigation). An open invite gets that one sign-up through paused sign-ups;
- the server accepts the invite itself once the account can: at `auth.verify` (or the set-password
  link) for an email sign-up, at once for Google or Apple. Someone already signed in, or whose
  automatic accept couldn't happen, accepts on the invite page (`beta.invite.accept`);
- only an account with a proven address (verified email, or Google or Apple) can accept; an invite
  works once, lasts 14 days unless Hillel says otherwise, and can be revoked;
- `account.json`'s `betaSinger` says the account is a live singer.

Every `beta.*` operation but `beta.invite.show`, `beta.invite.accept` and `beta.agreement.show`
answers `401 unauthenticated` signed out, `403 not_beta_singer` for an account without a singer
record, and `403 singer_revoked` once Hillel revoked or forgot it; those cases aren't repeated in
each table, and the mock makes them for any `beta.*` operation. **The credit** is the account's
name, read whenever it's shown (renamed in the profile, it shows on the next catalogue fetch), or
nothing for a singer who chose to stay anonymous.

**A take's timing.** Frame 0 of the uploaded WAV is the template's beat 0: the device has already
removed its measured round trip, which it sends as `latencyFrames` for the record (the timing
check's result, at the file's sample rate). The count-in is never in the file; a guide's recording
always starts at beat 0, pickup included. Hillel's nudge (`offsetMs`) is the only shift the server
applies, so a published guide's frame 0 sits `offsetFrames` after beat 0 (after its trimmed start
and the nudge). Frame counts are always at the file's own `sampleRate`; scale them to the
AudioContext's rate.

**The upload** is a mono, 16-bit PCM WAV, at most 10 minutes, at one of 16000, 22050, 24000, 32000,
44100, 48000, 88200 or 96000 Hz. `bytes` and `sha256` are of the whole file.

#### `courses.clips.list`

A course's published clips, for its steps: a step names its clip by slot (`"clip": "vibrato.natural"`)
and plays the url, crediting `credit` ("Sung by Sarah K.", none when it's null), else its own
fallback. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `courses.clips.list.json` | `courses.clips.list.200.json` |

#### `templates.catalogue`

Every song in the library, current versions only, for anyone (signed in or not). Never notes, words
or part audio of a song the caller can't start: a part's `guide` and `versions` are filled only for
a free song, the song of the month, and (from M5) a Plus account. The app's bundled templates are
the offline fallback. **Request:** none.

**Voices** (Hillel's decision, "published versions"): every accepted recording of a part is a
published version (`versions`, the default first, then the newest), and `guide` is the default
Hillel chose (or null: the synth plays). A learner may choose another version as their guide.
The app keeps each part's choice as **a list** of version ids (one item today; Hillel's decision:
the later custom choir is the same picker with several choices, sung together), in the project's
`settings.voices` (`{ "alto": ["Gd7Kp2"] }`) and in the same shape in `account.learning` for the
learner's own default. `"synth"` is the reserved id for the practice voice (version ids are Sqids
of at least 6 characters, so they never collide with it). An empty list means `guide`, and ids no
longer in `versions` drop out. When the default is removed, the version Hillel made default most
recently takes over, else the newest, else the synth: never a broken part.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.catalogue.json` | `templates.catalogue.200.json` |

#### `beta.invite.show`

The invite page: who the link is for, and whether it can still be accepted (`status`: `open`,
`accepted`, `expired` or `revoked`). A token that was never ours is `404`, and nothing else is told.
The app takes the token out of the address bar (`history.replaceState`) and keeps it for the
sign-up. **Request:** none. Limited (`beta-link`, per IP) so tokens can't be guessed.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.invite.show.json` | `beta.invite.show.200.json` |
| 404 | `invite_not_found` | `error.json` | `beta.invite.show.invite_not_found.json` |

#### `beta.invite.accept`

Attach the invite's singer record (the role, the agreement, the parts and takes) to this account.
Accepting again from the same account answers `200`. **Request:** none. Limited (`beta-link`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.me.json` | `beta.invite.accept.200.json` |
| 403 | `email_unverified` | `error.json` | `beta.invite.accept.email_unverified.json` |
| 404 | `invite_not_found` | `error.json` | `beta.invite.accept.invite_not_found.json` |
| 409 | `invite_used` | `error.json` | `beta.invite.accept.invite_used.json` |
| 409 | `already_singer` | `error.json` | `beta.invite.accept.already_singer.json` |
| 410 | `invite_expired` | `error.json` | `beta.invite.accept.invite_expired.json` |
| 410 | `invite_revoked` | `error.json` | `beta.invite.accept.invite_revoked.json` |

#### `beta.me.show`

The signed-in singer: the welcome, consent and My parts read it. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.me.json` | `beta.me.show.200.json` |

#### `beta.agreement.show`

The beta agreement, current version, as text blocks the app shows with `{{ }}` (never HTML).
`reviewed: false` means the lawyer hasn't checked it yet: the app says it's a draft. **Request:**
`?lang=` (optional; English, the only language at launch, otherwise).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.agreement.show.json` | `beta.agreement.show.200.json` |

#### `beta.consent.create`

Accept the agreement. **Request:** `{ "version": 1, "adult": true, "creditMode": "name" }`:
`version` is the one shown; `adult` must be `true` (18 or over); `creditMode` is `name` (credited by
the account's name, "Change it in your profile") or `anonymous`. A `version` older than the current
one is a `409 agreement_changed`: show the agreement again.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.me.json` | `beta.consent.create.200.json` |
| 409 | `agreement_changed` | `error.json` | `beta.consent.create.agreement_changed.json` |
| 422 | `validation_failed` | `error.json` | `beta.consent.create.validation_failed.json` |

#### `beta.credit.update`

Credited by name or anonymous, at any time; learners see it at once. **Request:** `{ "creditMode":
"anonymous" }`, `{ "creditLinksProfile": true }`, or both (at least one). The name itself is the
account's (`account.update`). `creditLinksProfile` links the credit to their public profile: a
guide's `creditHandle` is set only when they're credited by name, chose this, have a public profile
(`hasPublicProfile`) and profiles are open (`profilesOpen`); otherwise it's null and the credit is
the name alone.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.me.json` | `beta.credit.update.200.json` |
| 403 | `consent_required` | `error.json` | `beta.credit.update.consent_required.json` |
| 422 | `validation_failed` | `error.json` | `beta.credit.update.validation_failed.json` |

#### `beta.parts.list`

My parts: the singer's assigned parts, newest first, each with its status, its latest take and
Hillel's note (`adminNoteAt`: when he wrote it), and the template version's real voices so far.
A course clip (`kind: "clip"`) comes first, by course, lesson, step and slot: `template` and
`partKey` are null, `guides` is empty, and `clip` is its brief (`sing`, `pitch` relative to the
singer's own comfortable note as `{ step }` or `{ low, high }`, `seconds`, `style`, `voices`), with
`courseTitle`. It's recorded with the same take (`beta.submissions.create` without `templateVersion`
or `key`; there's no pitch or timing bar for a clip, but `scores.verdict` must be `ready`).
A take's `url` is their own recording to hear again, signed for 15 minutes, once it's processed.
There's no email: a part whose song isn't reviewed yet shows that here, and opens for recording when
it is (docs/beta-singers-plan.md, "No automatic email"). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.parts.list.json` | `beta.parts.list.200.json` |

#### `beta.submissions.create`

Start sending a take: the server records what the device measured and gives back a presigned PUT
for the WAV (contract/api.md, "Uploads"). **Request:**

```json
{ "assignmentId": "Ab3Cd9", "templateVersion": 2, "key": "G", "sampleRate": 48000,
  "frames": 2304000, "latencyFrames": 960, "bytes": 4608044, "sha256": "<64 hex>",
  "device": "iPhone 15, Safari 26 (installed)", "mic": "EarPods",
  "scores": { "pitchPercent": 92, "timingPercent": 88, "verdict": "ready", "tries": 3,
    "...": "what the quality check found" }, "note": "optional, to Hillel" }
```

`scores.timingPercent` (0 to 100, or null when there were too few moments to time),
`scores.verdict` (`ready` only: the app offers Send only then) and `scores.tries` (checked takes of
this part on this device, this one included). **The bar** (docs/decisions.md, "beta singers'
scores"; `beta.me` and `beta.invite.show` carry it as `bar`, set by `admin.beta.bar.*`): a take
whose `pitchPercent` is missing or under `bar.pitchPercent`, or whose `timingPercent` is under
`bar.timingPercent`, is `422 below_bar`, with `bar` in the error.

- `403 consent_required`: the current agreement isn't accepted yet;
- `403 not_yours`: the assignment isn't this singer's;
- `403 not_recordable`: the template isn't reviewed yet;
- `409 template_changed`: `templateVersion` isn't the current one, so the notes changed; reload My parts;
- `422`: the numbers don't add up (`bytes` must be a 16-bit mono WAV of `frames`, at most 10 minutes;
  `key` must be the template's original key);
- limited (`take-uploads`) and pausable (`423 paused`, by the uploads switch).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `beta.submissions.create.json` | `beta.submissions.create.201.json` |
| 403 | `consent_required` | `error.json` | `beta.submissions.create.consent_required.json` |
| 403 | `not_yours` | `error.json` | `beta.submissions.create.not_yours.json` |
| 403 | `not_recordable` | `error.json` | `beta.submissions.create.not_recordable.json` |
| 409 | `template_changed` | `error.json` | `beta.submissions.create.template_changed.json` |
| 422 | `below_bar` | `error.json` | `beta.submissions.create.below_bar.json` |
| 422 | `validation_failed` | `error.json` | `beta.submissions.create.validation_failed.json` |

#### `beta.submissions.complete`

The PUT is done: the server checks the file's size and SHA-256, then processes it (the take shows
as `processing`, then `submitted`, or `failed` with its `failure`). `409 upload_incomplete` (no
file, or the wrong size) and `409 upload_mismatch` (a different file) mean: send the file again
from the outbox, then call this again. Calling it again for a take that's already past `uploading`
answers the take as it is. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `beta.submissions.complete.json` | `beta.submissions.complete.200.json` |
| 409 | `upload_incomplete` | `error.json` | `beta.submissions.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `beta.submissions.complete.upload_mismatch.json` |

#### `beta.feedback.create`

Tell Hillel. **Request:** `{ "text": "...", "templateId": "amazing-grace", "partKey": "alto",
"beat": 33.5 }`: `text` up to 2000 characters, the rest optional. Limited (`contact`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `beta.feedback.create.json` | `beta.feedback.create.201.json` |
| 422 | `validation_failed` | `error.json` | `beta.feedback.create.validation_failed.json` |

#### `admin.overview`

The numbers that matter, from our own records (never analytics). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.overview.json` | `admin.overview.200.json` |

#### `admin.beta.singers.list`

Every beta singer, newest first, with their parts, consent, credit, the account that accepted the
invite (`account`, null until then) and when (`acceptedAt`), and what's owed. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.singers.list.json` | `admin.beta.singers.list.200.json` |

#### `admin.beta.singers.create`

Invite a singer. The app sends nothing: `link` is shown once, for Hillel to copy or share himself.
It works once and, unless `expiresAt` says otherwise, for 14 days.
**Request:** `{ "name": "Sarah Kim", "email": null, "note": null, "expiresAt": null,
"hourlyRateCents": 3000, "currency": "USD", "parts": [{ "templateId": "amazing-grace", "partKey":
"alto", "minutes": 25 }] }`: `name` required (up to 120); every part must exist in the template.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `admin.beta.singer.link.json` | `admin.beta.singers.create.201.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.singers.create.validation_failed.json` |

#### `admin.beta.singers.update`

**Request:** any of `name`, `email`, `note`, `expiresAt`, `hourlyRateCents`, `currency`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.singer.update.json` | `admin.beta.singers.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.singers.update.validation_failed.json` |

#### `admin.beta.singers.link`

A new link, before the invite is accepted: the old one stops working at once, a revocation is
lifted, and `expiresAt` (optional, 14 days otherwise) is set. Once accepted there's no link to
replace (`409 already_accepted`): the singer signs in. **Request:** `{ "expiresAt": null }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.singer.link.json` | `admin.beta.singers.link.200.json` |
| 409 | `already_accepted` | `error.json` | `admin.beta.singers.link.already_accepted.json` |

#### `admin.beta.singers.revoke`

End the singer role (or the invite, before it's accepted): the beta operations answer `403
singer_revoked`. The account, takes and guides stay. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.singer.update.json` | `admin.beta.singers.revoke.200.json` |

#### `admin.beta.singers.paid`

Mark every accepted, unpaid part of this singer as paid (audit-logged; the payment itself happens
outside the app). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.singer.update.json` | `admin.beta.singers.paid.200.json` |

#### `admin.beta.singers.forget`

Removal on request (the beta agreement): every guide of theirs comes off the CDN (purged from its
caches), their masters and takes are deleted, their Tell Hillel notes go, their name, email, note
and credit are cleared, and the singer record leaves the account. Payment records stay (amounts and
dates only). It can't be undone. Deleting the account does the same. A forgotten singer's `name` is empty: the app words it. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.beta.singer.update.json` | `admin.beta.singers.forget.200.json` |

#### `admin.beta.assignments.create`

Ask the singer to record another part. **Request:** `{ "templateId": "silent-night", "partKey":
"tenor", "minutes": 30 }`, or a course clip in place of the part: `{ "clipId": "vibrato.natural",
"minutes": 10 }` (a clip not in clips.json any more, or already theirs, is a `422` on `clipId`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `admin.beta.assignment.create.json` | `admin.beta.assignments.create.201.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.assignments.create.validation_failed.json` |

#### `admin.beta.assignments.delete`

Take a part off the singer's list; refused once they've sent a take for it. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 409 | `has_submissions` | `error.json` | `admin.beta.assignments.delete.has_submissions.json` |

#### `admin.beta.submissions.list`

The review queue, newest first, 50 a page. **Request:** `?status=` (`waiting` for submitted and in
review, or one status), `?template=`, `?part=`, `?singer=` (a singer's id: their takes, with your
notes, from the Singers screen), `?cursor=`, all optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submissions.list.json` | `admin.beta.submissions.list.200.json` |

#### `admin.beta.submissions.show`

One take with what the review needs: the master WAV for 15 minutes, the outline, and the
template's other real voices for the mix. `paidAt` is the admin's only: null for the editor.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.detail.json` | `admin.beta.submissions.show.200.json` |

#### `admin.beta.submissions.open`

Hillel opened it: a submitted take shows as In review to its singer. Any other status is left as
it is. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.update.json` | `admin.beta.submissions.open.200.json` |

#### `admin.beta.submissions.adjust`

Light fixes, applied when it's published: **Request:** any of `offsetMs` (-500 to 500, a nudge
later or earlier), `trimStartMs`, `trimEndMs` (0 to 60000), `gainDb` (-12 to 12). The trims and
the nudge must leave at least a second of the take (a `422` otherwise). A take can be adjusted
while it's waiting for review, or once accepted (then publish it again with
`admin.beta.submissions.publish`); any other status is a `409 not_reviewable`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.update.json` | `admin.beta.submissions.adjust.200.json` |
| 409 | `not_reviewable` | `error.json` | `admin.beta.submissions.adjust.not_reviewable.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.submissions.adjust.validation_failed.json` |

#### `admin.beta.submissions.accept`

Accept it as the guide: the server publishes it (trims, 10 ms fades, gain, levelled to about -20
dBFS, the offset applied, an AAC with its edit list on the CDN) and, unless `makeDefault` is
`false`, makes it the part's default voice for everyone. `guide` fills in once published (moments
later). Only a take that's `submitted` or `in_review` can be decided: `409 not_reviewable`
otherwise. **Request:** `{ "makeDefault": true }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.update.json` | `admin.beta.submissions.accept.200.json` |
| 409 | `not_reviewable` | `error.json` | `admin.beta.submissions.accept.not_reviewable.json` |

#### `admin.beta.submissions.retake`

Ask for another take, with a note the singer sees, pinned to a beat. **Request:** `{ "note": "A
little rushed in phrase 3", "beat": 24 }`: `note` required (up to 1000), `beat` optional.
`409 not_reviewable` as for accept.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.update.json` | `admin.beta.submissions.retake.200.json` |
| 409 | `not_reviewable` | `error.json` | `admin.beta.submissions.retake.not_reviewable.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.submissions.retake.validation_failed.json` |

#### `admin.beta.submissions.reject`

Not used, with an optional kind note. **Request:** `{ "note": null }`. `409 not_reviewable` as for
accept.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.submission.update.json` | `admin.beta.submissions.reject.200.json` |
| 409 | `not_reviewable` | `error.json` | `admin.beta.submissions.reject.not_reviewable.json` |

#### `admin.beta.submissions.publish`

Publish an accepted take again, with its current adjustments: its guide is replaced (keeping its
place as the part's default voice if it had it). For a take whose `publish` is `failed`, or to
change the trims of a guide. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.beta.submission.update.json` | `admin.beta.submissions.publish.200.json` |
| 409 | `not_publishable` | `error.json` | `admin.beta.submissions.publish.not_publishable.json` |

#### `admin.beta.bar.show`

The bar a beta singer's take must clear to be sent: `{ "pitchPercent": 90, "timingPercent": 80 }`
(those by default). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.bar.json` | `admin.beta.bar.show.200.json` |

#### `admin.beta.bar.update`

**Request:** `{ "pitchPercent": 92, "timingPercent": 80 }`, either or both, each 50 to 100.
Audited.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.bar.json` | `admin.beta.bar.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.beta.bar.update.validation_failed.json` |

#### `admin.beta.clips.list`

Every course clip (Hillel: "re-use the singer feature in admin to request singers to record these
missing parts"): the short real-voice clips a course step plays, defined by the courses in
`web/course/plus/clips.json` and loaded on every deploy, each with its brief, its `status` (`open`,
`assigned`, `submitted`, `published`), who has it, and what's published. A clip gone from the file is
`retired`: kept for what was recorded, never assigned again. Assign one with
`admin.beta.assignments.create` `clipId`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.clips.list.json` | `admin.beta.clips.list.200.json` |

#### `admin.beta.guides.list`

Every template's real voices, per part (default first, then alternates), and how many are left
from older versions: "3 of 4 parts are real voices". **Request:** `?template=` (optional).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.guides.list.json` | `admin.beta.guides.list.200.json` |

#### `admin.beta.guides.default`

Make this the part's default voice; learners hear it at once. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.guide.update.json` | `admin.beta.guides.default.200.json` |

#### `admin.beta.guides.delete`

Remove a guide (a singer asked for removal): its file goes from the CDN, and the part falls back to
an alternate or the synth. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

#### `admin.beta.feedback.list`

The Tell Hillel inbox, newest first, 50 a page. **Request:** `?unread=1` and `?cursor=`, both
optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.feedback.list.json` | `admin.beta.feedback.list.200.json` |

#### `admin.beta.feedback.read`

Mark a note as read. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.beta.feedback.read.json` | `admin.beta.feedback.read.200.json` |

### Accounts and sign-in (M3)

An account is never needed to learn or record; the app asks for one only to save to the cloud,
share, buy, or at the free limit (`docs/roadmap.md`, M3). Sign-in uses the same session cookie as
everything else. **Every `account.*` operation answers `401 unauthenticated` when signed out**
(not repeated in each table).

**No account enumeration.** Sign-up, sign-in, reset and resending the verification answer the same
whether or not an address has an account: sign-up, reset and resend always say `check_email`, and
a wrong address or password is one `401 invalid_credentials`. An unverified address gets nothing
from us but the verification email, at most once a minute, 3 an hour and 6 a day.

**Shield mode** (an attack switch): while `auth.config`'s `turnstileSiteKey` is set, sign-up,
sign-in, resend and reset need `turnstileToken` (from Cloudflare Turnstile); without one they
answer `403 turnstile_required`, and with a bad one `403 turnstile_failed`. **A blocked address
or network** answers `403 blocked`. **Paused sign-ups** answer `423 paused`. **A suspended
account** answers `403 suspended` to any way of signing in (to `auth.signin` only with the right
password).

**Passwords:** at least 10 characters, and not one of the 10,000 most common.

**A recent sign-in** (within 10 minutes) is needed to change the email or password without the
current password, to remove a sign-in method, and to delete the account: `403 recent_sign_in_required` means "sign in again
first" (an account without a password signs in with Google or Apple again).

**Sign-in navigations** (pages, not JSON). Google and Apple sign in by a form `POST` that leaves
the app. A form can't send the `X-XSRF-TOKEN` header, so it carries the token as a `_token` field
(the value of the `XSRF-TOKEN` cookie, URL-decoded); a stale or missing one sends the person back
to `intended` with `?authError=expired` (to `/admin/sign-in` for an admin path), never to a 419
page:
- `POST /auth/google` (or `/auth/apple`) with `intended` (a path in the app, default `/`),
  `link=1` from Settings to add the method to the signed-in account, and `invite` (a beta invite's
  token: an open one gets a new account through paused sign-ups, and is accepted at once);
- the provider sends the person back (Apple by a `POST` to `/auth/apple/callback`), and the server
  redirects to `intended` with `?signedIn=google` (or `apple`), or `?authError=<code>`:
  `cancelled`, `expired`, `failed`, `unverified` (Google hasn't verified the email), `suspended`,
  `blocked`, `paused`, `linked_elsewhere` (that Google or Apple account belongs to another
  Stackapella account; accounts are never merged by email), or `recent_sign_in_required` (adding
  a method with `link=1` needs a sign-in in the last 10 minutes);
- a first sign-in with Google or Apple makes the account (`birthGiven: false`: ask "When were you
  born?" once, then `account.update`). Until it's answered, uploading, sharing, joining a project
  or a choir, and buying coaching answer `403 age_unconfirmed` (the app asks first).

**Links in emails** open the app, which calls the operation:
- `/verify?token=...` calls `auth.verify` (and signs the person in);
- `/reset?token=...&email=...` shows the new-password form, then calls `auth.password.reset`;
- `/email/confirm?token=...` calls `account.email.confirm`;
- `/email/revert?token=...` calls `account.email.revert` (the undo link sent to the old address).

#### `auth.config`

What the sign-in screens need. `comingSoon` is whether this visitor sees "coming soon" (false
for anyone let in); `prelaunch` is whether it's before launch at all, for everyone (the app shows
the draft songs until then). `instrumentsIndexUrl` is where the real-instrument samples are
listed (`instruments/index.json` on the media CDN: `{ "instruments": [{ "name", "version",
"manifest", "updatedAt" }] }`, cached for five minutes), or null where instruments are off, when the
apps use the synth. A version's files never change (cache them by URL for good); each manifest's
`files` give every sample's `path` (relative to the manifest), `bytes` and `sha256`, the format in
`docs/instrument-samples.md`. `selfHosted` is true on a self-hosted install (every account has Plus there:
no Plus page or Get Plus), false on stackapella.com. `sentryDsn` is the browser error reporter's DSN
(public by design; its envelopes go to `/log/errors`), or null when it isn't set up, when the app
sends nothing. `usageCounts` says whether to send the anonymous usage counts (`counts.create`).
`experiment` is the funnel's running A/B test, `{ "id": "onb1", "arms": ["a", "b"], "enrol": true }`,
or null: a new device draws an arm (`crypto.getRandomValues`) at its first open only while `enrol`
is true, keeps it on the device, and sends `"<id>-<arm>"` as `counts.create`'s `variant`.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.config.json` | `auth.config.200.json` |

#### `auth.register`

Make an account with an email and a password. It isn't signed in yet: the verification link does
that. **Request:** `{ "name": "Ada", "email": "ada@example.com", "password": "...",
"birthMonth": 5, "birthYear": 1990, "ref": "hn", "elapsedMs": 41250, "nickname": "",
"invite": null, "turnstileToken": null }`:
- `birthMonth` (1 to 12) and `birthYear`: when they were born ("When were you born?", month and
  year only). A birthday counts as the last day of its month, so someone is 13 (or 18) once their
  whole birth month has passed. Under 13 answers `422 too_young` (the code alone, nothing about the
  age given) and nothing is kept. 18 and over makes the account an adult (`isAdult`); a
  17-year-old becomes one on their own when their birth month ends. Required: the old
  `ageConfirmed` ("13 or over") is refused (`422`);
- `nickname` is the honeypot (always empty), and `elapsedMs` is how long the form was open,
  timed on the device (`performance.now()`, so no clock skew): under 2000 is treated as a bot (the
  same `202`, and nothing happens);
- `ref` (optional, up to 40 letters, digits and dashes) is where they came from (`?ref=` on our
  links), kept with the account;
- `invite` (optional) is a beta invite's token: an open one gets this sign-up through paused
  sign-ups, and is accepted when the address is verified ("Templates and the beta");
- `early` (optional) is the early-access link's code (`/early/<code>`, "The mudeo email"): an
  open one gets this sign-up through paused sign-ups too, and the account is marked "from mudeo".
  The Google and Apple sign-in forms take `early` as well;
- limited (`sign-up`, per IP).

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `auth.accepted.json` | `auth.register.202.json` |
| 403 | `blocked` | `error.json` | `auth.register.blocked.json` |
| 403 | `turnstile_required` | `error.json` | `auth.register.turnstile_required.json` |
| 422 | `validation_failed` | `error.json` | `auth.register.validation_failed.json` |
| 422 | `too_young` | `error.json` | `auth.register.too_young.json` |
| 423 | `paused` | `error.json` | `auth.register.paused.json` |

#### `auth.link.send`

Sign in by a link sent by email ("Accept invite" with just an address): a one-time link that
signs in, or makes the account for an address that has none. **Request:** `{ "email":
"ana@example.com", "intended": "/choir/<token>", "invite": "...", "early": "...",
"turnstileToken": "..." }` (all but `email` optional):
- `intended` is a path in the app to open after (checked as sign-in's are: another site, or
  anything that isn't one of our paths, becomes `/`);
- `invite` and `early` do what they do at sign-up: they let a new account through paused
  sign-ups, and the beta invite is accepted with it;
- always `202` with the same body as `auth.register`'s, so it never says whether an address has
  an account. The link isn't sent (and the answer is the same) for a suspended account, a new
  address while sign-ups are paused and nothing given opens them, or past the address's limits
  (as the verification email's);
- the link works for 15 minutes, once; it opens `/signin/link?token=<token>` in the app, which
  posts it to `auth.link.open`;
- limited (`sign-up`, per IP); `403 blocked` as sign-up; Turnstile in shield mode.

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `auth.accepted.json` | `auth.link.send.202.json` |
| 403 | `blocked` | `error.json` | `auth.link.send.blocked.json` |
| 422 | `validation_failed` | `error.json` | `auth.link.send.validation_failed.json` |

#### `auth.link.open`

The link from the email, posted by the app's `/signin/link`. **Request:** `{ "token": "..." }`.
An address with an account is signed in to it (its address verified if it wasn't); an address
without one gets its account, verified by the link, with no name, password or age yet. Answers
the account and:
- `created`: whether the account was just made;
- `intended`: where to go next (`/` if none was given);
- `needsProfile`: true while the account has no name, or neither a birth month and year nor
  "13 or over": the app asks them first ("a bit about you"), as after a first Google sign-in.

`410 link_used` for a link opened before; `410 link_expired` for one past its 15 minutes, or
unknown; `403 suspended`; `423 paused` when sign-ups closed after it was sent and nothing it
carries opens them.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.link.open.json` | `auth.link.open.200.json` |
| 403 | `suspended` | `error.json` | `auth.link.open.suspended.json` |
| 410 | `link_used` | `error.json` | `auth.link.open.link_used.json` |
| 410 | `link_expired` | `error.json` | `auth.link.open.link_expired.json` |
| 422 | `validation_failed` | `error.json` | `auth.link.open.validation_failed.json` |
| 423 | `paused` | `error.json` | `auth.link.open.paused.json` |

#### `auth.verify`

The link in the verification email: verifies the address and signs the person in. **Request:**
`{ "token": "..." }`. A link lasts 24 hours and works once.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `auth.verify.200.json` |
| 410 | `link_expired` | `error.json` | `auth.verify.link_expired.json` |

#### `auth.verification.resend`

Send the verification email again ("Resend" waits 60 seconds in the app). **Request:**
`{ "email": "..." }`, plus `turnstileToken` in shield mode.

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `auth.accepted.json` | `auth.verification.resend.202.json` |

#### `auth.signin`

**Request:** `{ "email": "...", "password": "...", "remember": true, "turnstileToken": null }`.
After 10 wrong passwords for one account, it waits 15 minutes whatever the network (`429`).
`403 email_unverified` comes only with the right password: send the verification again.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `auth.signin.200.json` |
| 401 | `invalid_credentials` | `error.json` | `auth.signin.invalid_credentials.json` |
| 403 | `email_unverified` | `error.json` | `auth.signin.email_unverified.json` |
| 403 | `blocked` | `error.json` | `auth.signin.blocked.json` |
| 403 | `suspended` | `error.json` | `auth.signin.suspended.json` |
| 403 | `turnstile_failed` | `error.json` | `auth.signin.turnstile_failed.json` |

#### `auth.signout`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

#### `auth.signout.everywhere`

Sign out on every device (and forget every "remember me"), this one included. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

#### `auth.password.forgot`

Send a reset link (valid 60 minutes). **Request:** `{ "email": "..." }`, plus `turnstileToken`
in shield mode. Limited (`password-reset`: per address and per IP).

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `auth.accepted.json` | `auth.password.forgot.202.json` |

#### `auth.password.reset`

**Request:** `{ "token": "...", "email": "...", "password": "..." }`. Signs the person in, and
signs out every other session.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `auth.password.reset.200.json` |
| 403 | `suspended` | `error.json` | `auth.password.reset.suspended.json` |
| 410 | `link_expired` | `error.json` | `auth.password.reset.link_expired.json` |
| 422 | `validation_failed` | `error.json` | `auth.password.reset.validation_failed.json` |

#### `account.show`

The signed-in account. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.show.200.json` |

#### `account.update`

**Request:** any of `name` (1 to 80), `handle`, `bio` (up to 300), `locale`, `timezone`, `xHandle`,
`blueskyHandle`, `heardFrom` (one of the enum, asked once at the end of the first run),
`birthMonth` and `birthYear` (as `auth.register`'s, once: while the account has none, for a first
Google or Apple sign-in and for older accounts that ticked "13 or over"; a later answer doesn't
replace it; under 13 answers `422 too_young`; the old `ageConfirmed` is refused, `422`), `publicProfile` (N1: a public profile in the gallery; a handle and 18
or over, `403 adult_required`), `hideProfile` (Hide profile: everyone else sees "Anonymous" with no
handle, picture or profile page; it overrides `publicProfile` while on and never blocks sharing,
recording or the gallery, where a hidden adult's songs are listed as Anonymous), `ideaEmails` (Ideas: emailed about new voices on your tunes), `pushTypes` (which of the bell's
kinds are pushed to the apps, `{ "like": false, ... }`; the kinds sent change, the rest stay),
`quietHours` (`{ "start": "22:00", "end": "08:00" }` in the account's time zone, or null for none), `appearance` (the theme picker: `{ "mode": "system" | "light" |
"dark", "colour": "warm" | "midnight" | "graphite" | "plum" | "stage" | null, "accent": "coral" |
"amber" | "green" | "teal" | "sky" | "indigo" | "violet" | "rose" | "custom" | null, "customAccent":
"#rrggbb" | null, "skin": "game" | "serious" }`, any of them (`skin`, the game or serious skin, is `game` until chosen); `customAccent` is required with `accent: "custom"` (a `422` on
`appearance.customAccent` without it) and cleared with any other accent, and the app fits it to each
theme for contrast; the first choice
fills an unsent mode with `system` and an unsent colour or accent with null, which means not
chosen: the mode's own, Stage in dark and Warm in light, and the palette's own accent). No links in names, handles or bios. A
handle that's taken, reserved (Stackapella's own names, and look-alikes such as "stackape11a") or
not allowed is a `422` on `handle`. The account's `appearance` is null until the person chooses
one: the device's own choice stands until then, and a new device takes the account's.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.update.200.json` |
| 403 | `adult_required` | `error.json` | `account.update.adult_required.json` |
| 422 | `validation_failed` | `error.json` | `account.update.validation_failed.json` |
| 422 | `too_young` | `error.json` | `account.update.too_young.json` |

#### `account.terms.accept`

The terms of use, accepted: the version the person was shown. Every new account agrees to the
current version when it's made (each sign-up says "By continuing you agree to the Terms of use and
the Privacy policy"), so this is for a new version: when `account.json` `terms.accepted` isn't
`terms.current`, the app asks once. **Request:** `{ "version": 2 }`. Another version than the current
answers `409 terms_changed` with `current` (`{ "version": 3 }`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.terms.accept.200.json` |
| 409 | `terms_changed` | `error.json` | `account.terms.accept.terms_changed.json` |
| 422 | `validation_failed` | `error.json` | `account.terms.accept.validation_failed.json` |

#### `account.handle.check`

As the person types a handle. **Request:** `?handle=`. Limited like the API.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.handle.json` | `account.handle.check.200.json` |

#### `account.email.update`

Change the email: a confirmation link goes to the new address (it shows as `pendingEmail` until
then), and the old address is told, with "This wasn't me". **Request:** `{ "email": "...",
"password": "..." }` (the password, or a recent sign-in). Limited (`email-change`: 3 a day).

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `auth.accepted.json` | `account.email.update.202.json` |
| 403 | `recent_sign_in_required` | `error.json` | `account.email.update.recent_sign_in_required.json` |
| 422 | `validation_failed` | `error.json` | `account.email.update.validation_failed.json` |

#### `account.email.confirm`

The link sent to the new address. **Request:** `{ "token": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.email.confirm.200.json` |
| 410 | `link_expired` | `error.json` | `account.email.confirm.link_expired.json` |

#### `account.email.revert`

The undo link sent to the old address when the email changes, for a change the person didn't
make. The old address comes back (verified), every session and "remember me" ends, so whoever
changed it is signed out, and the password is cleared (they may know it): the page then offers
"Choose a new password" (`auth.password.forgot`, to the `email` it answers with: the address the
link was sent to). The link lasts 7 days, and a later change's undo link never cancels it.
**Request:** `{ "token": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.email.revert.json` | `account.email.revert.200.json` |
| 409 | `email_taken` | `error.json` | `account.email.revert.email_taken.json` |
| 410 | `link_expired` | `error.json` | `account.email.revert.link_expired.json` |

#### `account.password.update`

Set a password (an account that has none adds one, after a recent sign-in), or change it.
**Request:** `{ "currentPassword": "...", "password": "..." }`. Signs out every other session.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.password.update.200.json` |
| 403 | `recent_sign_in_required` | `error.json` | `account.password.update.recent_sign_in_required.json` |
| 422 | `validation_failed` | `error.json` | `account.password.update.validation_failed.json` |

#### `account.methods.delete`

Remove a sign-in method: `google`, `apple`, `event-schedule` (or `event_schedule`, as
`auth.config` spells it) or `password`. The last one can't be removed
(`409 last_method`). It needs a recent sign-in. Adding Google or Apple is a sign-in navigation
with `link=1`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.methods.delete.200.json` |
| 403 | `recent_sign_in_required` | `error.json` | `account.methods.delete.recent_sign_in_required.json` |
| 409 | `last_method` | `error.json` | `account.methods.delete.last_method.json` |

#### `account.avatar.update`

**Request:** `multipart/form-data` with `avatar` (a JPEG, PNG or WebP, up to 2 MB and 3000 pixels
a side). The server
makes a square JPEG for the CDN.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.avatar.update.200.json` |
| 422 | `validation_failed` | `error.json` | `account.avatar.update.validation_failed.json` |

#### `account.avatar.delete`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.show.json` | `account.avatar.delete.200.json` |

#### `account.export.create`

Download my data: prepared in the background (every take, harmony and review as links, and the
account's records), then an email says it's ready. **Request:** none. Limited (`data-export`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `account.export.json` | `account.export.create.202.json` |

#### `account.export.show`

The latest export: `url` is a signed link valid for 7 days. `failed` means it couldn't be made
(asking again starts a new one). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.export.json` | `account.export.show.200.json` |

#### `account.delete`

Delete the account (it signs out). Recordings go through the bin purge; nothing can be restored
afterwards. **Request:** `{ "password": "..." }` (or a recent sign-in). An account that hasn't
answered the age question yet (`birthGiven: false`, and no old "13 or over") needs neither: the
under-13 answer may come long after a Google or Apple sign-in.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 403 | `recent_sign_in_required` | `error.json` | `account.delete.recent_sign_in_required.json` |

### The apps: device tokens

The native apps (iOS, Android, desktop; `~/Code/stackapella-mobile`) sign in to this same API with
tokens instead of the session cookie, agreed between the backend and the mobile agent
(2026-10-27). Nothing here changes the web. Operations whose "Who" starts with "An app" are the
apps' only: the web's `api.js` doesn't have them.

- **Bearer, not cookies.** An app sends `Authorization: Bearer <access token>` on every call. A
  bearer request never gets a `Set-Cookie` and is never a `419`: there's no CSRF without cookies.
  It sends `X-App-Version` as the web does. (The device's platform is the one its sign-in sent.)
- **Every other operation answers a bearer request exactly as it answers a session:** the same
  request, responses and codes.
- **A device** is one sign-in in an app: Settings' "Signed in on". Its **access token** (`sat_...`)
  lasts an hour; its **refresh token** (`srt_...`) lasts 90 days from when it was last used, and
  each `auth.app.refresh` replaces it. A replaced refresh token still works for 60 seconds (a lost
  answer, two requests racing); after that it signs the device out (`401 token_reused`). The
  server keeps only hashes of both.
- **What a 401 means to an app:** `token_expired` (a known access token past its hour): refresh,
  then repeat the call. `unauthenticated` (an unknown token, or its device signed out),
  `refresh_invalid` or `token_reused`: signed out on this device, keeping what's on it.
- **Sign-in in an app** takes `device`: `{ "name": "Ada's iPhone", "platform": "ios", "model":
  "iPhone17,1", "appVersion": "0.1.0", "replace": null }`. `replace` is the app's own device id,
  to sign in again on it (a new pair of tokens, and a new `signedInAt`, the recent sign-in clock),
  honoured only when the call carries that device's access token as its bearer (even past its
  hour); otherwise it's a new device, and the account is emailed about it as any new one is.
  Linking Google or Apple in Settings gives a new pair but keeps `signedInAt`.
- **Shield mode, blocks, paused sign-ups, suspension and the age question** are as on the web. In
  shield mode the app shows Turnstile in a web view and sends its token as `turnstileToken`.
- **Limits:** sign-ins share the web's limits and counters for the same address and network;
  `auth.app.refresh` allows 60 an hour per account (and 1,200 an hour per network). Bearer tokens
  that fail are counted per network (30 a minute, then `429`); a valid one always passes.
- **Before launch** an app has no beta pass cookie: `appPass.claim` gives a pass it sends as
  `X-App-Pass`, and `auth.config` then says `comingSoon: false`. A device signed in needs none.
- **Deleting the account** (`account.delete`, as it is) signs out every device and revokes, with
  Apple, the refresh token kept from an app's Sign in with Apple. `auth.signout.everywhere` signs
  out every device too.

#### `auth.app.signin`

Email and password. **Request:** `{ "email": "...", "password": "...", "device": { ... },
"turnstileToken": null }`. The same answers and limits as `auth.signin` (the wait after 10 wrong
passwords included), with tokens instead of a session.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.app.tokens.json` | `auth.app.signin.200.json` |
| 401 | `invalid_credentials` | `error.json` | `auth.app.signin.invalid_credentials.json` |
| 403 | `email_unverified` | `error.json` | `auth.app.signin.email_unverified.json` |
| 403 | `blocked` | `error.json` | `auth.app.signin.blocked.json` |
| 403 | `suspended` | `error.json` | `auth.app.signin.suspended.json` |
| 403 | `turnstile_required` | `error.json` | `auth.app.signin.turnstile_required.json` |
| 403 | `turnstile_failed` | `error.json` | `auth.app.signin.turnstile_failed.json` |

#### `auth.app.google`

Google Sign-In on the device gives an ID token, which the server checks (Google's keys, `iss`,
`exp`, `aud` one of the apps' client IDs, `email_verified`). **Request:** `{ "idToken": "...",
"nonce": "..." | null, "device": { ... }, "invite": null, "early": null, "link": false,
"turnstileToken": null }`. `nonce` (32 to 128 characters) is the one the app gave Google's SDK
for this launch, when it gave one; a token that carries a nonce must come with it (`401
invalid_token` otherwise). Each ID token signs in once: after a lost reply, get a new one. A
first sign-in makes the account as the web's does (`birthGiven: false`). `early` is an
early-access or shared invite code, `invite` a beta invite's token, as on the web. `link: true`,
with a bearer token and a recent sign-in, adds Google to the signed-in account (Settings).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.app.tokens.json` | `auth.app.google.200.json` |
| 401 | `invalid_token` | `error.json` | `auth.app.google.invalid_token.json` |
| 403 | `unverified` | `error.json` | `auth.app.google.unverified.json` |
| 403 | `blocked` | `error.json` | `auth.app.google.blocked.json` |
| 403 | `suspended` | `error.json` | `auth.app.google.suspended.json` |
| 403 | `turnstile_required` | `error.json` | `auth.app.google.turnstile_required.json` |
| 403 | `recent_sign_in_required` | `error.json` | `auth.app.google.recent_sign_in_required.json` |
| 409 | `linked_elsewhere` | `error.json` | `auth.app.google.linked_elsewhere.json` |
| 423 | `paused` | `error.json` | `auth.app.google.paused.json` |

#### `auth.app.apple`

Sign in with Apple on the device gives an identity token and an authorisation code. The server
checks the token (Apple's keys, `aud` the app's bundle id) and exchanges the code for Apple's
refresh token, which it keeps to revoke when the account is deleted (App Store Guideline
5.1.1(v)). Apple gives the name only the first time, so the app passes it on. **Request:** `{
"identityToken": "...", "authorizationCode": "...", "nonce": "...", "name": "Ada" | null,
"device": { ... }, "invite": null, "early": null, "link": false, "turnstileToken": null }`.
`nonce` (required, 32 to 128 characters) is new for each sign-in: the app gives Apple its SHA-256
as lowercase hex, sends the nonce itself here, and the token must carry that hash (`401
invalid_token` otherwise). Each identity token signs in once.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.app.tokens.json` | `auth.app.apple.200.json` |
| 401 | `invalid_token` | `error.json` | `auth.app.apple.invalid_token.json` |
| 403 | `blocked` | `error.json` | `auth.app.apple.blocked.json` |
| 403 | `suspended` | `error.json` | `auth.app.apple.suspended.json` |
| 403 | `turnstile_required` | `error.json` | `auth.app.apple.turnstile_required.json` |
| 403 | `recent_sign_in_required` | `error.json` | `auth.app.apple.recent_sign_in_required.json` |
| 409 | `linked_elsewhere` | `error.json` | `auth.app.apple.linked_elsewhere.json` |
| 423 | `paused` | `error.json` | `auth.app.apple.paused.json` |

#### `auth.app.verify`

The verification link (`/verify?token=...`) opens the app, which verifies the address and signs
in on this device. Sign-up itself is `auth.register`. **Request:** `{ "token": "...", "device": {
... } }`. A link lasts 24 hours and works once.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.app.tokens.json` | `auth.app.verify.200.json` |
| 410 | `link_expired` | `error.json` | `auth.app.verify.link_expired.json` |

#### `auth.app.refresh`

A new access token and refresh token (`account` left out). **Request:** `{ "refreshToken": "..."
}`, with no bearer token. Limited (30 an hour per device).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `auth.app.tokens.json` | `auth.app.refresh.200.json` |
| 401 | `refresh_invalid` | `error.json` | `auth.app.refresh.refresh_invalid.json` |
| 401 | `token_reused` | `error.json` | `auth.app.refresh.token_reused.json` |
| 429 | `rate_limited` | `error.json` | `auth.app.refresh.rate_limited.json` |

#### `auth.app.signout`

Signs this device out. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

#### `account.devices.list`

Settings' "Signed in on": the person's devices in the apps, the most recently used first;
`current` is the one asking. The web's own sessions aren't listed. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.devices.json` | `account.devices.list.200.json` |

#### `account.devices.delete`

Signs a device out; the current one is the same as `auth.app.signout`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `account.devices.delete.not_found.json` |

#### `account.devices.push.update`

The push token the app's device got from Apple (APNs) or Google (FCM), so the bell is pushed to
it. **Request:** `{ "platform": "apns", "token": "<the token>", "environment": "production" }`:
`platform` `apns` or `fcm`; `environment` (`sandbox` or `production`) only for APNs, whose tokens
are for one (TestFlight's and the App Store's are `production`). Only for the device asking (the
bearer's): a browser's session answers `403 app_only`. A device holds one token, and a new one
replaces it; signing the device out clears it; the same token on another device (a reinstall) is
taken off that one. Answers the device (`push: true`). Limited (20 an hour).

What's pushed, while push is on (the server's `APP_PUSH_ENABLED`): one push for each new bell item
(`bellItem.json`'s kinds; a day's likes on one harmony, or voices on one idea, push once, when the
first comes), to each of the account's devices with a token, if `account.pushTypes` has its kind
on. During the account's `quietHours` (in its `timezone`) a push waits until they end; one that
couldn't go within 12 hours is dropped. No badge counts.
- APNs: `{ "aps": { "alert": { "title", "body" }, "sound": "default", "thread-id": "<kind>" },
  "type": "<kind>", "url": "<path>" }`.
- FCM: `notification: { title, body }`, `data: { type, url }`.
- `title` and `body` are worded by the server in the account's language; `url` is an app path,
  the web's own: `/h/<id>` (a harmony), `/ideas/<id>`, `/@<handle>`, `/songs/<slug>`, or `/`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.device.json` | `account.devices.push.update.200.json` |
| 403 | `app_only` | `error.json` | `account.devices.push.update.app_only.json` |
| 422 | `validation_failed` | `error.json` | `account.devices.push.update.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `account.devices.push.update.rate_limited.json` |

#### `account.devices.push.delete`

No more pushes to the device asking (its token forgotten). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 403 | `app_only` | `error.json` | `account.devices.push.delete.app_only.json` |

#### `appPass.claim`

The beta pass for an app: a preview, early-access, shared invite or beta invite link opens the
app, which claims a pass with the link's code. The pass is a signed expiry, as the cookie is (400
days), sent as `X-App-Pass`. A preview gives a pass, never an account; early-access and invite
codes also go in `auth.register`, `auth.app.google` and `auth.app.apple` as `early`, a beta
invite's as `invite`, exactly as on the web. **Request:** `{ "kind": "preview" | "early" | "invite"
| "beta", "code": "..." }`. A link that isn't open now (paused, full, closed, or a code that isn't
one) is `410 link_closed`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `appPass.json` | `appPass.claim.200.json` |
| 410 | `link_closed` | `error.json` | `appPass.claim.link_closed.json` |
| 422 | `validation_failed` | `error.json` | `appPass.claim.validation_failed.json` |

### Projects and cloud save (M4)

**The device first** (`docs/backend-plan.md` C5). IndexedDB stays the working copy; while signed
in, the outbox sends each change here, and `sync.pull` brings other devices' changes back. Every
operation here answers `401 unauthenticated` signed out, and `404 not_found` for a project, part or
take that belongs to someone else (never `403`, so IDs can't be probed). Beta singers' recording
projects never sync, and don't count toward the limit.

- **IDs:** projects, parts and takes keep the UUID the device gave them (`crypto.randomUUID()`), so
  a record made offline needs no remapping. The API addresses them by it.
- **Versions:** every project and part carries `version`. A save or a delete sends the `version`
  it was based on (`0` to make one) and gets the next back. A stale one answers `409
  version_conflict` with the server's copy in `current`: the app asks, per part, "Changed on
  another device: Keep mine or Use theirs" (keeping mine saves again with `current.version`). A
  project's `settings` are mostly one device's practice state, so the app may merge them without
  asking.
- **Replays:** every save and delete carries a `changeId` (a UUID per outbox entry). The record
  keeps its last one, so a request repeated after a lost answer answers `200` (or `204`) with the
  record as it is, never a conflict.
- **Order:** a project before its parts, a part before its takes, and `takes.create` before the
  `parts.save` that points at the take. A part whose project the server doesn't have answers `409
  project_missing`, a take whose part it doesn't have `409 part_missing`: send the parent first.
- **Deleted is deleted:** a part that was deleted answers `409 part_gone` to a save (even with
  `version: 0`) and to `takes.create`; the app can offer to keep the person's work as a new part.
  Deleting again answers `204`.
- **Takes are immutable,** `offsetFrames` included (where it was recorded, at its own rate), so an
  Undo plays an old take where it was. The part's `nudgeFrames` moves its current take (at that
  take's rate; the app sets it to 0 for a new take). Audio never conflicts, only settings.
- **`settings`** on a project or a part is the app's own (practice progress, the template copy, the
  views, `useGuide`...), stored and returned as sent: at most 256 KB for a project, 16 KB for a part.
- **The bin** (C3): a deleted project waits 30 days with its parts and takes; a take waits 30 days
  once its part plays another `ready` take (so a replacement that never arrives leaves the old one
  playing), or once its part is deleted. Then they go for good. The bin doesn't count toward
  storage.
- **Limits** (`usage.show`): the free plan's 3 projects (binned ones don't count) and its storage
  allowance (1 GB; Plus: unlimited projects and 50 GB). `403 project_limit` and `403
  storage_limit` say which. A project beyond the limit after a downgrade stays readable, and changes
  to it answer `403 project_read_only` ("Plus and billing"); nothing is deleted. An unverified account (trust level 0)
  saves projects and parts, but its takes wait (`403 email_unverified`) until it's verified.
- **Moving browser projects in** (first sign-in): the app saves each with `version: 0`; one past the
  limit answers `403 project_limit` and stays on the device, marked "not saved to your account".
- **Audio** goes up as a 16-bit mono WAV by presigned `PUT` ("Uploads"), at any rate from 8000 to
  192000 Hz, and comes down as FLAC (lossless, at the take's `sampleRate`: decode it at that rate,
  not the AudioContext's, so frame counts stay exact). Peaks are the absolute maximum of each
  `framesPerPeak` frames, 0 to 1, as the app works them out: 512, or for a long take a larger
  power of two, so a take has at most 16384 peaks. Draw a take by its own `framesPerPeak`.
- **Peaks** (`peaks8` on every take): base64, one byte per peak (the same encoding as a share's
  tracks), about a quarter of the size of numbers, which matters on a first sync (a two-minute take
  has some 11,000 peaks). `?peaks=8`, which the web and the apps sent while numbers were the
  default, is still accepted and changes nothing. **Byte for byte:** each peak is clamped to 0..1,
  times 255, rounded to the nearest whole number with halves away from zero (JavaScript's
  `Math.round`, Dart's `round()`), so 0.5 is 128; decode as byte / 255. Peaks
  `[0, 0.5, 1, 0.25]` are the bytes `[0, 128, 255, 64]`, `peaks8` `"AID/QA=="`. Peaks only come
  down: the server makes them from the uploaded WAV, and no request takes them.
- **A pending upload:** a take never completed keeps its row (`uploading`) while its part exists,
  and its upload file is deleted after a day; `takes.create` with the same `id` gives a fresh
  `upload`. A `failed` take is final: the app sends the recording again under a new `id`.

#### `sync.pull`

Everything that changed since `cursor` (omit it for everything), in the order it changed:
projects (binned ones too, with `binnedAt`), parts, takes, and `removed` (a part deleted, or a
project or take gone for good). `meta.more` means call again with `meta.cursor` straight away; a
part may arrive before its project across pages, so apply a pull once `more` is `false`. The cursor
is a per-account change number given inside the change's own transaction, so a change that commits
late is never skipped. Deletions are remembered 90 days: an older cursor answers `410
cursor_expired`, and the app pulls again without one, where any synced record not listed (and not
in its outbox) is gone. A page also ends early once its takes' peaks get heavy (about 1.5 MB).
**Request:** `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `sync.pull.json` | `sync.pull.200.json` |
| 410 | `cursor_expired` | `error.json` | `sync.pull.cursor_expired.json` |
| 422 | `validation_failed` | `error.json` | `sync.pull.validation_failed.json` |

#### `projects.show`

A project with its parts and their current takes (for opening one that isn't on this device yet).
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `projects.show.json` | `projects.show.200.json` |
| 404 | `not_found` | `error.json` | `projects.show.not_found.json` |
| 410 | `removed_from_project` | `error.json` | `projects.show.removed_from_project.json` |

#### `projects.save`

Make or change a project. **Request:** `{ "changeId": "<uuid>", "version": 3, "title": "Amazing
Grace", "bpm": 80, "key": "G", "timeSignature": "3/4", "template": { "id": "amazing-grace",
"version": 2 }, "settings": {...}, "createdAt": "<when the device made it>" }`:
- `title` 0 to 120 characters: blank until the person names it in the studio (the app shows
  "Untitled song" for a blank one, in lists, exports and shares); `bpm` 20 to 300; `key` a key name or `null`; `timeSignature` like
  `4/4`; `template` `null` for a project not from a template;
- a binned project answers `409 binned` (restore it first), here and for its parts and takes;
- `version` must be `0` to make one; a project that's gone (purged) answers `404 not_found`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `project.saved.json` | `projects.save.200.json` |
| 403 | `project_limit` | `error.json` | `projects.save.project_limit.json` |
| 403 | `project_read_only` | `error.json` | `projects.save.project_read_only.json` |
| 409 | `version_conflict` | `error.json` | `projects.save.version_conflict.json` |
| 409 | `binned` | `error.json` | `projects.save.binned.json` |
| 422 | `validation_failed` | `error.json` | `projects.save.validation_failed.json` |
| 403 | `owner_only` | `error.json` | `projects.save.owner_only.json` |

#### `projects.delete`

To the bin for 30 days, with its parts and takes. **Request:** `?version=&changeId=`. Deleting one
already in the bin answers `204`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 409 | `version_conflict` | `error.json` | `projects.delete.version_conflict.json` |
| 403 | `owner_only` | `error.json` | `projects.delete.owner_only.json` |

#### `projects.restore`

Back from the bin, as it was (one that isn't in the bin answers as it is). Restoring past the
project limit answers `403 project_limit`, and past the storage allowance (its takes count again)
`403 storage_limit`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `project.saved.json` | `projects.restore.200.json` |
| 403 | `project_limit` | `error.json` | `projects.restore.project_limit.json` |
| 403 | `storage_limit` | `error.json` | `projects.restore.storage_limit.json` |
| 403 | `owner_only` | `error.json` | `projects.restore.owner_only.json` |

#### `parts.save`

Make or change a part. **Request:** `{ "changeId": "<uuid>", "projectId": "<uuid>", "version": 0,
"order": 1, "name": "Alto", "color": "#e4a94b", "volume": 1, "pan": 0, "muted": false, "solo":
false, "nudgeFrames": 0, "trimStartFrames": 0, "trimEndFrames": 0, "templatePartKey": "alto",
"kind": "voice", "currentTakeId": "<uuid or null>", "settings": {...}, "createdAt": "<when the device
made it>" }`:
- `kind` `voice` or `instrument` (for the credits' "played by"), optional: left out, the stored one
  stays, and a new part is a voice;
- `name` 1 to 60 characters; `color` lower-case `#rrggbb`; `order` 0 or more (ties are broken by
  `id`); `volume` 0 to 2, `pan` -1 to 1; `nudgeFrames` any integer; the trims 0 or more;
- `currentTakeId` must be one of this part's takes (any status, so the device can point at a take
  still uploading, and a binned one comes out of the bin: an offline Undo is just this save). The
  take it replaces is kept (see "A part's kept takes"), and so is the one a `null` leaves;
- a part's project can't change.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `parts.show.json` | `parts.save.200.json` |
| 403 | `project_read_only` | `error.json` | `parts.save.project_read_only.json` |
| 403 | `storage_limit` | `error.json` | `parts.save.storage_limit.json` |
| 409 | `version_conflict` | `error.json` | `parts.save.version_conflict.json` |
| 409 | `binned` | `error.json` | `parts.save.binned.json` |
| 409 | `part_gone` | `error.json` | `parts.save.part_gone.json` |
| 409 | `project_missing` | `error.json` | `parts.save.project_missing.json` |
| 422 | `validation_failed` | `error.json` | `parts.save.validation_failed.json` |
| 403 | `not_your_part` | `error.json` | `parts.save.not_your_part.json` |

#### `parts.delete`

The part goes; its takes go to the bin. **Request:** `?version=&changeId=`. Deleting one already
gone answers `204`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 409 | `version_conflict` | `error.json` | `parts.delete.version_conflict.json` |
| 403 | `not_your_part` | `error.json` | `parts.delete.not_your_part.json` |

#### `takes.create`

Start sending a take. **Request:** `{ "id": "<uuid>", "partId": "<uuid>", "sampleRate": 48000,
"frames": 2304000, "offsetFrames": -960, "bytes": 4608044, "sha256": "<64 hex>", "scores": {...} }`:
- `bytes` must be a 16-bit mono WAV of `frames` (44-byte header), at most 10 minutes; `offsetFrames`
  any integer, at `sampleRate`; `scores` (optional, at most 16 KB) is the score summary, kept for
  the history;
- `403 email_unverified`: an unverified account's takes wait until it's verified;
- `403 storage_limit`: it doesn't fit in the allowance, counting the part's current take as gone
  (so re-recording at the limit works);
- the `upload` URL works for 10 minutes; the same `id` again answers the take as it is, with a fresh
  `upload` while it's `uploading`; the
  same `id` with other numbers (another `sha256`) is a `422` on `id`;
- limited (`take-uploads`), and by bytes a day per account (2,000 MB free, 10,000 MB with Plus):
  `429 rate_limited` with `retryAfter`; pausable (`423 paused`, by the uploads switch).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `takes.create.json` | `takes.create.201.json` |
| 403 | `age_unconfirmed` | `error.json` | `takes.create.age_unconfirmed.json` |
| 403 | `email_unverified` | `error.json` | `takes.create.email_unverified.json` |
| 403 | `project_read_only` | `error.json` | `takes.create.project_read_only.json` |
| 403 | `storage_limit` | `error.json` | `takes.create.storage_limit.json` |
| 409 | `binned` | `error.json` | `takes.create.binned.json` |
| 409 | `part_gone` | `error.json` | `takes.create.part_gone.json` |
| 409 | `part_missing` | `error.json` | `takes.create.part_missing.json` |
| 422 | `validation_failed` | `error.json` | `takes.create.validation_failed.json` |
| 403 | `not_your_part` | `error.json` | `takes.create.not_your_part.json` |
| 429 | `rate_limited` | `error.json` | `takes.create.rate_limited.json` |

#### `takes.complete`

The `PUT` is done: the server checks the size and SHA-256, then makes the FLAC and the peaks
(`processing`, then `ready`, or `failed` with its `failure`). `409 upload_incomplete` and `409
upload_mismatch` mean: send the file again, then call this again. Calling it again later answers
the take as it is. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `takes.show.json` | `takes.complete.200.json` |
| 409 | `upload_incomplete` | `error.json` | `takes.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `takes.complete.upload_mismatch.json` |

#### `takes.show`

A take, with `url` (the FLAC, valid 15 minutes) once it's `ready`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `takes.show.json` | `takes.show.200.json` |
| 404 | `not_found` | `error.json` | `takes.show.not_found.json` |

#### `takes.restore`

Back from the bin as its part's current take, at `nudgeFrames` (default 0); the current one is
kept (see "A part's kept takes"). This is Undo across devices (offline, the same is a `parts.save`). One that's already
current answers as it is. A take whose part was deleted can't come back (`409 part_gone`).
**Request:** `{ "nudgeFrames": 0 }`, optional.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `parts.show.json` | `takes.restore.200.json` |
| 403 | `project_read_only` | `error.json` | `takes.restore.project_read_only.json` |
| 403 | `storage_limit` | `error.json` | `takes.restore.storage_limit.json` |
| 409 | `part_gone` | `error.json` | `takes.restore.part_gone.json` |
| 409 | `binned` | `error.json` | `takes.restore.binned.json` |
| 403 | `not_your_part` | `error.json` | `takes.restore.not_your_part.json` |


#### A part's kept takes

Hillel: "add an undo option to go back to the last take if it was better. Maybe keep all takes so
users can swap to try." A take a part stops playing is **kept**, never binned: when a new take
replaces it (`takes.create`, a `parts.save` that changes `currentTakeId`, `takes.restore`), the old
one stays the part's, counting toward storage, until it's deleted. (`takes.create`'s storage check
still counts the current take as gone, so re-recording at the limit works; the kept one then counts,
which can only stop the next new take.)

#### `parts.takes.list`

Every kept take of a part, newest first: binned and failed ones are left out. Each item is the take
(`take.json`, with its `url` once ready, valid 15 minutes), whether it's `current`, `recordedBy`
(a singer on a shared song's name, null for the owner's own), `pitchPercent` (from the scores
`takes.create` sent, else null) and `durationSeconds`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `parts.takes.list.json` | `parts.takes.list.200.json` |
| 404 | `not_found` | `error.json` | `parts.takes.list.not_found.json` |

#### `parts.takes.use`

One of the part's kept takes becomes its current one, whole: the part's `nudgeFrames`,
`trimStartFrames` and `trimEndFrames` go back to 0, as for a new take. The take it replaces is
kept. Already current, it answers the part as it is. **Request:** none. Answers the part, as
`parts.save`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `parts.show.json` | `parts.takes.use.200.json` |
| 403 | `not_your_part` | `error.json` | `parts.takes.use.not_your_part.json` |
| 403 | `project_read_only` | `error.json` | `parts.takes.use.project_read_only.json` |
| 404 | `not_found` | `error.json` | `parts.takes.use.not_found.json` |
| 409 | `take_not_ready` | `error.json` | `parts.takes.use.take_not_ready.json` |

#### `takes.delete`

A kept take goes to Recently deleted (`bin.list`, 30 days, back with `takes.restore`). Never the
part's current take (`409 take_current`: pick another first). One already there answers `204` too.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `takes.delete.not_found.json` |
| 409 | `take_current` | `error.json` | `takes.delete.take_current.json` |
#### `bin.list`

"Recently deleted": binned projects and takes, newest first, each with when it goes for good.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `bin.list.json` | `bin.list.200.json` |

#### `bin.purge`

"Delete now": one item of "Recently deleted" goes for good at once, as it would 30 days on (its
files too, and a tombstone for other devices). `kind` is `project` (one they own, with its parts
and takes) or `take` (one they recorded, outside a deleted project). One that's already gone, or
isn't in the bin, answers `404`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `bin.purge.not_found.json` |

#### `bin.purgeTakes`

"Clear older takes": every take they recorded on this project that's in the bin goes for good at
once. `freedBytes` is what it freed (0 when there was nothing to clear). A project they neither
own nor sing on, or one in the bin, answers `404`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `bin.purgeTakes.json` | `bin.purgeTakes.200.json` |
| 404 | `not_found` | `error.json` | `bin.purgeTakes.not_found.json` |

#### `usage.show`

The plan, and what counts toward it. `storage.projects` is each project (outside the bin) with
takes they recorded, largest first: `bytes` its current takes, `binBytes` its older takes in the
bin (which don't count, and `bin.purgeTakes` clears). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `usage.show.json` | `usage.show.200.json` |

#### `account.learning.show`

The course's progress (stars, XP, levels, badges, the weekly goal), as the app keeps it: an object
of at most 256 KB, with its `version` (`0` before the first save). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.learning.json` | `account.learning.show.200.json` |

#### `account.learning.update`

**Request:** `{ "changeId": "<uuid>", "version": 4, "epoch": 0, "progress": {...} }`. A stale version
answers `409 version_conflict` with the server's copy in `current`: the app merges (the best stars,
the most XP) and saves again. Progress from an older `epoch` (from before the course was started
over, or its undo; no `epoch` counts as 0) answers `409 learning_reset` with `current`: the device
replaces its progress with it, never merges, then saves again.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.learning.json` | `account.learning.update.200.json` |
| 409 | `version_conflict` | `error.json` | `account.learning.update.version_conflict.json` |
| 409 | `learning_reset` | `error.json` | `account.learning.update.learning_reset.json` |
| 422 | `validation_failed` | `error.json` | `account.learning.update.validation_failed.json` |

#### `account.learning.reset`

"Start the course over": the lessons and their stars go, and units opened early (`lessons` and
`testedOut` emptied); the comfortable note, the range, XP, practice, the goal, badges and the Plus
courses' progress (`courses`) stay. A
new `epoch`, so every device takes it. The progress before is kept to undo for 30 days
(`undoUntil`); resetting again while it can still be undone keeps that first copy. **Request:** `{ "changeId": "<uuid>" }` (the same again answers the same).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.learning.json` | `account.learning.reset.200.json` |

#### `account.learning.undoReset`

The lessons and units opened early from before the reset, back, within 30 days, at a new `epoch`
again (a device holding the reset copy takes it). What was earned since (XP, practice, badges,
the Plus courses' `courses`) stays. **Request:** `{ "changeId": "<uuid>" }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `account.learning.json` | `account.learning.undoReset.200.json` |
| 409 | `nothing_to_undo` | `error.json` | `account.learning.undoReset.nothing_to_undo.json` |
| 410 | `undo_expired` | `error.json` | `account.learning.undoReset.undo_expired.json` |

### Plus and billing (M5)

**Plus** (`docs/pricing-plan.md`; `docs/backend-plan.md` D8): Stripe Checkout and the billing
portal, through Cashier, with Stripe Tax; monthly or yearly, in USD, EUR or GBP (chosen at the
first Checkout: Stripe ties a customer to one currency, so the portal can't change it); a 7-day
trial, once per account and once per card. The app never sees a card: it opens Checkout's or the
portal's `url` (in the installed app, the system browser).
- **Coming back from Checkout:** Stripe sends the person to `/plus/return?session={CHECKOUT_SESSION_ID}`,
  a server page that works signed out (on an iPhone, Stripe opens outside the installed app):
  "You're on Plus. Go back to the app", or "Still confirming..." while Stripe hasn't told us yet.
  The app re-reads `billing.show` when it comes to the foreground; `status` is `pending` from a
  Checkout made in the last 30 minutes until the webhook arrives (then it's the subscription's
  status), and after those 30 minutes it's whatever the account is.
- **Plus is kept while a payment is retried** (`past_due`, until `pastDueUntil`); after the last
  retry fails, the account is free (`endedReason: payment_failed`). Cancelling (in the portal) keeps
  Plus to the end of the paid period (`canceled`, until `endsAt`).
- **Nothing is deleted by a downgrade:** a project is `readOnly` `"limit"` (beyond the free limit:
  the person picks which stay editable with `projects.editable.update`, the most recently edited by
  default) or `"plus_song"` (made from a Plus song: back with Plus). A read-only project is
  readable, exportable as WAV, and still saves its practice `settings` (a `projects.save` that
  changes nothing else); any other change answers `403 project_read_only` with its `projectId` and
  `reason`, and the app keeps the entry in its outbox. A project becoming read-only or editable
  gets a new sync change number but keeps its `version`, so queued saves don't conflict.
- **Plus songs** come from `templates.show`, never in the app's files: to a Plus account
  (`grant: "plus"`), to anyone, signed out too, for the song of the month (`"month"`, with
  `freeUntil`), and for good to anyone who started a project from a song while it was the song of
  the month (`"kept"`: "finish your song"), to Hillel and the editor (`"admin"`, reviewing beta
  takes and guides; never counted toward the daily cap), and to a beta singer for the songs they're
  assigned (`"beta"`). Offline, a Plus song stays usable until
  `billing.show`'s `plusOfflineUntil` (14 days after the last check, but never past the end of a
  cancelled plan), then read-only until the app is online again.
- **Refusals:** `403 plus_required` (a Plus song or feature on a free account), `403
  project_read_only`, `403 email_unverified` (Checkout needs a verified address), `409
  already_plus`, `409 trial_used`, `409 no_subscription`, `423 paused` (Checkout paused during an
  attack, or before Stripe is set up), `429 rate_limited` (the Checkout limit, and the Plus download cap).

#### `templates.show`

A song's full template (`template.json`: notes, words, parts) with its parts' published voices, for
the current version or `?version=` (a project made on an older one), and why the caller may have it
(`grant`). It sends an `ETag`: a `304`, or the same song again the same day, doesn't count toward
the daily cap (`plus-downloads`, per account, or per network signed out; past it, `429`, and Hillel
is told). **Request:** `?version=` (optional).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.show.json` | `templates.show.200.json` |
| 403 | `plus_required` | `error.json` | `templates.show.plus_required.json` |
| 404 | `not_found` | `error.json` | `templates.show.not_found.json` |
| 429 | `rate_limited` | `error.json` | `templates.show.rate_limited.json` |

#### `billing.prices`

Plus's prices and the trial, for anyone (the pricing page, signed out). `startNowConsent` is true
for a visitor in the EU, EEA or UK: the upgrade sheet then asks, before Checkout, for consent to
start Plus at once, which affects their 14-day right of withdrawal (the words are the app's, at
the version the server records; the lawyer confirms them). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `billing.prices.json` | `billing.prices.200.json` |

#### `billing.show`

The account's plan and subscription, what its trial would be, and the offline grace. **Request:**
none.
- `notice: "card_had_trial"`: the card used at Checkout had a trial before, so the new
  subscription was cancelled at once with no charge ("This card has already had a free trial.
  Subscribe without the trial?"); it clears at the next Checkout, or after 7 days.
- `notice: "payment_failed"`: while `past_due` ("Update your card by `pastDueUntil`").
- `notice: "plus_twice"`: Plus is paid both on the web and in a store, and both renew; the app says
  so and points to cancelling one (the store's own subscription settings, or the portal).
- `source` says where Plus comes from; `store` is the App Store or Google Play subscription giving it;
  `storeAccountToken` is what the apps give the stores (billing.store.sync).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `billing.show.json` | `billing.show.200.json` |

#### `billing.checkout`

Start Checkout. **Request:** `{ "interval": "year", "currency": "usd", "trial": true, "moment":
"plus_song" }`:
- `interval` `month` or `year`; `currency` `usd`, `eur` or `gbp` (a returning subscriber keeps their
  first currency);
- `trial` asks for the trial `billing.show` offers (its `trial.intervals`); one this account has
  had, or not offered for this interval, answers `409 trial_used`;
- `moment` is the upgrade moment that led here, counted on the server, never in the browser:
  `plus_song`, `course_unit7`, `workout`, `project_limit`, `storage_limit`, `mp3`, `stems`,
  `history`, `gym`, `private_template`, `settings` or `plus_course` (anything else is kept as `other`);
- `startNowConsent` (optional): true when the person ticked the consent to start at once. Where
  `billing.prices` says it's needed and it isn't true, `422 validation_failed` on
  `startNowConsent`. The server records it with the checkout (when, the words' version, the
  country).

Stripe's page says that Plus renews, at what price, and how to cancel (with a trial, the date it
starts charging).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `billing.redirect.json` | `billing.checkout.201.json` |
| 403 | `email_unverified` | `error.json` | `billing.checkout.email_unverified.json` |
| 409 | `already_plus` | `error.json` | `billing.checkout.already_plus.json` |
| 409 | `trial_used` | `error.json` | `billing.checkout.trial_used.json` |
| 422 | `validation_failed` | `error.json` | `billing.checkout.validation_failed.json` |
| 423 | `paused` | `error.json` | `billing.checkout.paused.json` |
| 429 | `rate_limited` | `error.json` | `billing.checkout.rate_limited.json` |

#### `billing.portal`

Stripe's billing portal: change plan or card, see invoices, cancel. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `billing.redirect.json` | `billing.portal.201.json` |
| 409 | `no_subscription` | `error.json` | `billing.portal.no_subscription.json` |
| 423 | `paused` | `error.json` | `billing.portal.paused.json` |

#### `billing.store.sync`

Plus bought in an app (docs/app-purchases-plan.md): the same call after a purchase and to
restore. **Request:** iOS and macOS (the Mac app buys the same App Store products)
`{ "store": "apple", "transactions": ["<signedTransactionInfo JWS>", ...] }`, one after a purchase or
every current entitlement on restore; Android `{ "store": "google", "purchases": [{ "productId":
"plus", "purchaseToken": "..." }] }`. Only purchased ones are sent, never pending ones (Ask to Buy,
Play's pending payments). The server checks each: Apple's signature and certificate chain, then
the App Store Server API for the transaction's own environment, whose answer is what counts
(Sandbox ones, from TestFlight and App Review, are kept but give Plus only to accounts marked as
store testers); Google's token with the Play Developer API, which it
acknowledges itself. The app sets `storeAccountToken` (billing.show) as Apple's
`appAccountToken` and Google's `obfuscatedAccountId`. Renewals, grace, expiry and refunds come
from the stores' notifications after that; the app only reads billing.show. Answers billing.show.
- `200`: at least one purchase sent was linked to this account (others in the same call may have
  been another account's, or failed the checks).
- `409 store_linked_elsewhere`: none was linked, and at least one is another Stackapella account's
  (linked to it, or Apple's or Google's current record of it names that account's token); the
  body's `billing` is billing.show's data.
- `422 invalid_purchase`: none was linked, none was another account's, and at least one fails the
  checks or isn't one of our products.
- `423 paused`: that store isn't set up on the server yet (for Apple, its Server API key).
- `429 rate_limited`: too many calls (30 an hour per account); `retryAfter` says when to try again.
- `503 unavailable`: Apple or Google didn't answer; try again later.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `billing.show.json` | `billing.store.sync.200.json` |
| 409 | `store_linked_elsewhere` | `error.json` | `billing.store.sync.store_linked_elsewhere.json` |
| 422 | `invalid_purchase` | `error.json` | `billing.store.sync.invalid_purchase.json` |
| 422 | `validation_failed` | `error.json` | `billing.store.sync.validation_failed.json` |
| 423 | `paused` | `error.json` | `billing.store.sync.paused.json` |
| 429 | `rate_limited` | `error.json` | `billing.store.sync.rate_limited.json` |
| 503 | `unavailable` | `error.json` | `billing.store.sync.unavailable.json` |

#### `projects.editable.update`

After a downgrade, which projects stay editable (at most the free limit; the rest are `readOnly:
"limit"`). **Request:** `{ "ids": ["<project uuid>", "..."] }`. A `422` on `ids` for more than the
limit, and on `ids.N` for an unknown, binned or Plus-song project.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `projects.editable.json` | `projects.editable.update.200.json` |
| 422 | `validation_failed` | `error.json` | `projects.editable.update.validation_failed.json` |

#### `scores.create`

Keep an exercise's score summary in the history: numbers only, never audio. A take's report comes
with the take itself (`takes.create`'s `scores`, kept under the take's id), so this is for scored
exercises; a free account's are kept too, and `scores.list` shows the history once it's on Plus.
The same `id` again answers `200` as it is; the same `id` with another body is a `422` on `id`.
**Request:** `{ "id": "<uuid>", "lesson": "u4.l2.ex1", "templateId": null, "templateVersion": null,
"partKey": null, "recordedAt": "...", "summary": {...} }` (`summary` at most 16 KB, as the app
keeps it).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `scores.show.json` | `scores.create.201.json` |
| 422 | `validation_failed` | `error.json` | `scores.create.validation_failed.json` |

#### `scores.list`

The history (take reports and exercises), newest first, 100 a page. Plus. **Request:**
`?templateId=&partKey=&lesson=&since=&cursor=`, all optional (`since`: kept after that time, for a
device catching up).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `scores.list.json` | `scores.list.200.json` |
| 403 | `plus_required` | `error.json` | `scores.list.plus_required.json` |

#### `reminders.show`

The practice reminder (web push, opt-in; `docs/operations-plan.md`): the days and time, the
server's public VAPID key for `pushManager.subscribe`, and with `?endpoint=` whether this device
is one of them (`thisDevice`). **Request:** `?endpoint=` (optional).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `reminders.show.json` | `reminders.show.200.json` |

#### `reminders.update`

**Request:** `{ "enabled": true, "days": [1, 3, 5], "time": "18:30", "timezone": "Europe/London" }`
(`days` 0 to 6, Sunday first; `time` local, 24-hour, on a quarter hour: the job runs every 15
minutes). At most one reminder a day, only on those days. On a clock change, a local time that
doesn't exist that day fires at the next one that does, and a repeated one fires once.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `reminders.show.json` | `reminders.update.200.json` |
| 422 | `validation_failed` | `error.json` | `reminders.update.validation_failed.json` |

#### `reminders.devices.create`

This device's push subscription (`PushSubscription.toJSON()`); the same `endpoint` again replaces
it. Answers the device's `id` and a `token`, which the service worker keeps (in IndexedDB): it
changes or removes the device by that token alone, with no session or CSRF (a service worker
can't send them). **The push** carries `reminders.push.json` (`{ "kind": "practice", "url":
"/learn" }`, never anyone's text): the service worker words the notification in the person's
language and shows it every time (Safari revokes a subscription that gets a push without one); a
tap opens `url`. There are no action buttons (iOS shows none): "Turn off" is in Settings
(`/settings/reminders`). When the push service answers that a subscription has gone (`404` or
`410`), the server forgets that device. **Request:** `{ "endpoint": "https://...", "keys": {
"p256dh": "...", "auth": "..." } }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `reminders.device.json` | `reminders.devices.create.201.json` |
| 422 | `validation_failed` | `error.json` | `reminders.devices.create.validation_failed.json` |

#### `reminders.devices.update`

A new subscription for the same device (the service worker's `pushsubscriptionchange`).
**Request:** the device's token in `X-Device-Token`, and `{ "endpoint": "...", "keys": {...} }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `reminders.devices.update.not_found.json` |

#### `reminders.devices.delete`

Stop reminders on this device. The app calls it before signing out, and from Settings.
**Request:** the device's token in `X-Device-Token`. An unknown device answers `204` too.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `admin.billing.disputes.list`

Chargebacks, newest first (the account drops to trust level 0 and Plus ends at once). **Request:**
`?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.billing.disputes.list.json` | `admin.billing.disputes.list.200.json` |

### Sharing: harmonies, videos and reports (M6)

**A harmony is a finished mix the person shares** (`docs/backend-plan.md` D6, the unlisted parts).
The mix is rendered on the device (Make it sound good included) and uploaded like a take; the
server never remixes it. `ProcessHarmony` checks it, makes the AAC, the waveform and the share
card, and the link `/h/<id>` plays it.

- **IDs:** a harmony's `id` (in `/h/<id>`, here and in `reports.create`) is 12 random letters and
  digits, not a Sqid: for an unlisted harmony, the id is the link's only secret, so it can't be
  found by counting. Videos and reports keep Sqids.
- **Visibility:** `private` (only the owner hears it, in the app) or `unlisted` (anyone with the
  link). There's no public gallery yet, so `public` isn't accepted. An unlisted page is never
  indexed (`noindex`). Turning a harmony `private` takes its files off the CDN at once; turning it
  back gives it new file addresses (the page address stays).
- **Who:** signed in and verified (trust level 1). A trust level 0 account answers `403
  email_unverified`. The project must be cloud saved (the app sends its outbox first): one the
  server doesn't have answers `409 project_missing`.
- **The mix check** (`docs/trust-and-safety-plan.md`, "Uploads and shared audio"): the project (or,
  for just my part, that part) has a `ready` take (`409 no_takes` otherwise), and the mix is no
  longer than its longest take plus 15 seconds for the reverb's tail (`409 mix_mismatch`), and at
  most 10 minutes. `ProcessHarmony` checks the file is the WAV it was declared to be (else
  `failed`, with `failure`).
- **Just my part:** `partId` names the one part in the mix ("Share just my part",
  `docs/more-features-plan.md` feature 12). It's the same flow, rendered on the device.
- **Limits:** becoming unlisted (made unlisted, or changed to it) counts toward the share limit
  by trust level (`shares`: level 1 3 a day, level 2 20, level 3 50; `429 rate_limited`), and
  `423 paused` while sharing is paused (existing links keep working). Making a `private` one
  counts toward nothing.
- **Titles:** 0 to 120 characters, plain text. A link in a title is refused (`422`, "Links aren't
  allowed here"). A title that matches the word list is published as "Untitled song" until
  Hillel looks (`titleHeld`). A blank title shows as "Untitled song" everywhere.
- **Moderation:** `moderation` is `null`, `under_review` (3 reports from different people hid the
  page until Hillel looks), `hidden` (taken down by Hillel, a copyright notice included, or the
  account is suspended) or `removed` (its files are gone for good). A moderated harmony can't be
  made unlisted or turned into a video (`409 harmony_unavailable`); it can still be deleted.
- **Deleting** a harmony takes its page, files and videos away at once. Deleting the project
  doesn't: the harmony is its own record, with `projectId` and `partId` set to `null` once the
  project or part is gone for good.
- **Credits** on the page come from the server, never from the request: the owner's name, then
  the friends whose takes were in the mix (the project's singers), the song's credits ("Arranged by Stackapella" for our templates), and the beta singers whose
  published voices are in the mix (`guides`).
- **Sign-ups from a share:** the page's links carry `?ref=h-<harmony id>`, and `auth.register`'s
  `ref` (and Google or Apple's) records the sign-up with the harmony.

#### `harmonies.create`

Start sharing a mix. `partId` must be one of the project's parts (`409 part_missing` otherwise).
**Request:**
`{ "changeId": "<uuid>", "projectId": "<uuid>", "partId": null, "title": "Silent Night for Mum",
"visibility": "unlisted", "sampleRate": 48000, "channels": 2, "frames": 8640000, "bytes":
34560044, "sha256": "<64 hex>", "beatZeroSeconds": 2.25, "guides": ["<guide version id>"],
"tracks": [{ "name": "Alto", "color": "#e4a94b", "peaks": "<base64>" }] }`:
- `parentId` and `fillPart` (N5, both or neither): a fill of someone's open part (`409
  fill_mismatch` unless the part is open on a public original, the project is from the same song
  at its tempo with takes on that part only, and nobody is blocked); the mix may run as long as
  the original's stems;
- `changeId`: a UUID per share; the same one again answers the same harmony (an outbox retry),
  with a fresh `upload` while it's `uploading`;
- the mix is a 16-bit WAV, mono or stereo, at 8000 to 48000 Hz: `bytes` is `44 + frames ×
  channels × 2`; at most 10 minutes;
- `beatZeroSeconds`: where the template's beat 0 falls in the mix (0 to 60), for the video's
  words; `null` for a project not from a template, or to leave the words out;
- `guides`: the published voices heard in the mix (`guide.recording`'s `id`, at most 16), for the
  credits. Ids the server doesn't know, or of another song, are ignored;
- `tracks`: each part in the mix, in order (1 to 12): `name` (1 to 60 characters), `color` (lower
  case `#rrggbb`), `kind` (optional, `voice` or `instrument`: the page, card and posts then say
  "Played by" or "Sung and played by") and `peaks`, base64 of one byte (0 to 255) per 50 ms of the mix from its start,
  the loudest that part gets in it, as heard (after its volume). They draw the video's waveforms
  and the card's;
- the `upload` URL works for 10 minutes from when it's given (`Content-Type: audio/wav`); then
  `harmonies.complete`. A mix that waits in the outbox (for Wi-Fi, say) calls `harmonies.create`
  again with the same `changeId` just before sending, for a fresh `upload`: that works for a day,
  after which an upload never completed is cleared and the share starts again;
- the mix counts toward the storage allowance (the WAV while it's on its way, then the AAC), so
  one that doesn't fit answers `403 storage_limit`; limited like takes (`take-uploads`) and
  paused with uploads (`423 paused`), as well as by the share limit when it's unlisted.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `harmonies.create.json` | `harmonies.create.201.json` |
| 403 | `age_unconfirmed` | `error.json` | `harmonies.create.age_unconfirmed.json` |
| 403 | `email_unverified` | `error.json` | `harmonies.create.email_unverified.json` |
| 403 | `storage_limit` | `error.json` | `harmonies.create.storage_limit.json` |
| 409 | `project_missing` | `error.json` | `harmonies.create.project_missing.json` |
| 409 | `part_missing` | `error.json` | `harmonies.create.part_missing.json` |
| 409 | `binned` | `error.json` | `harmonies.create.binned.json` |
| 409 | `no_takes` | `error.json` | `harmonies.create.no_takes.json` |
| 409 | `fill_mismatch` | `error.json` | `harmonies.create.fill_mismatch.json` |
| 409 | `mix_mismatch` | `error.json` | `harmonies.create.mix_mismatch.json` |
| 422 | `validation_failed` | `error.json` | `harmonies.create.validation_failed.json` |
| 423 | `paused` | `error.json` | `harmonies.create.paused.json` |
| 429 | `rate_limited` | `error.json` | `harmonies.create.rate_limited.json` |

#### `harmonies.complete`

The `PUT` is done: the server checks the size and SHA-256, then `ProcessHarmony` runs
(`processing`, then `ready` or `failed`). As `takes.complete`: `409 upload_incomplete` and `409
upload_mismatch` mean send the file again, then call this again; calling it later answers the
harmony as it is. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.show.json` | `harmonies.complete.200.json` |
| 409 | `upload_incomplete` | `error.json` | `harmonies.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `harmonies.complete.upload_mismatch.json` |

#### `harmonies.list`

The person's harmonies, newest first. **Request:** `?projectId=<uuid>` (optional: only that
project's, for "already shared"), `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.list.json` | `harmonies.list.200.json` |

#### `harmonies.show`

One harmony, with `audioUrl` and `cardUrl` (valid 15 minutes) once it's `ready`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.show.json` | `harmonies.show.200.json` |
| 404 | `not_found` | `error.json` | `harmonies.show.not_found.json` |

#### `harmonies.update`

Rename it, or change who can hear it. **Request:** `{ "title": "...", "visibility": "private" }`,
either or both. Becoming `unlisted` counts toward the share limit, and is refused while sharing
is paused, when the harmony is moderated (`409 harmony_unavailable`), or when its mix `failed`
(`409 harmony_not_ready`). A new title makes a new share card. `mayFeature: true` and
`inGallery: true` are refused while friends sang on it (`409 singers_must_agree`): each of them
agrees first (N1 asks them). `mayFeature: true` is for 18 and over, as the gallery is
(`403 adult_required`). A harmony with a friend's voice comes down when that friend leaves the
project taking their parts, or deletes their account.

In the gallery (N1): `rightsConfirmed: true` and `commentsOff`, and `inGallery`'s rules once it
opens ("The gallery (N1)"): `403 adult_required`, `403 trust_required`, `409 profile_required`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.show.json` | `harmonies.update.200.json` |
| 403 | `adult_required` | `error.json` | `harmonies.update.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `harmonies.update.email_unverified.json` |
| 403 | `trust_required` | `error.json` | `harmonies.update.trust_required.json` |
| 409 | `harmony_not_ready` | `error.json` | `harmonies.update.harmony_not_ready.json` |
| 409 | `harmony_unavailable` | `error.json` | `harmonies.update.harmony_unavailable.json` |
| 409 | `profile_required` | `error.json` | `harmonies.update.profile_required.json` |
| 409 | `singers_must_agree` | `error.json` | `harmonies.update.singers_must_agree.json` |
| 422 | `validation_failed` | `error.json` | `harmonies.update.validation_failed.json` |
| 423 | `paused` | `error.json` | `harmonies.update.paused.json` |
| 429 | `rate_limited` | `error.json` | `harmonies.update.rate_limited.json` |

#### `harmonies.delete`

The page, the files and its videos go at once. Deleting one already gone answers `204`.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `videos.create`

Share as a video (`docs/more-features-plan.md` feature 4): a vertical MP4 (1080 x 1920, H.264 and
AAC, under about 15 MB) of a section of the mix, with the title and singers, each part's
waveform in its colour, the words karaoke style (from the template, for a template's project
with `beatZeroSeconds`), and a 2-second end card with the link. **Request:** `{ "startSeconds":
42.5, "endSeconds": 98.0, "look": "dark", "words": true }`:
- the section is 3 to 60 seconds, inside the mix (the app picks the chorus, or the whole song
  when it's shorter);
- `look` is `dark` (the part colours on dark, the default) or `light`;
- the harmony must be `unlisted` (the end card shows its link): a private one answers `409
  harmony_private`, and the app asks first, then makes it unlisted (`harmonies.update`) and tries
  again. It must be `ready` (`409 harmony_not_ready`) and not moderated (`409
  harmony_unavailable`);
- the same choices again, while that video is queued, rendering or ready, answer it (`200`)
  without counting toward the limit;
- limited (`share-videos`: 3 an hour, 10 a day). Rendering is on the `long` queue, at most 2 at
  once for everyone; while videos are paused, new ones wait `queued`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `videos.show.json` | `videos.create.200.json` |
| 202 |  | `videos.show.json` | `videos.create.202.json` |
| 403 | `age_unconfirmed` | `error.json` | `videos.create.age_unconfirmed.json` |
| 404 | `not_found` | `error.json` | `videos.create.not_found.json` |
| 409 | `harmony_private` | `error.json` | `videos.create.harmony_private.json` |
| 409 | `harmony_not_ready` | `error.json` | `videos.create.harmony_not_ready.json` |
| 409 | `harmony_unavailable` | `error.json` | `videos.create.harmony_unavailable.json` |
| 422 | `validation_failed` | `error.json` | `videos.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `videos.create.rate_limited.json` |

#### `videos.show`

A video's progress: poll it every 2 seconds while `queued` or `rendering` (`progress` is 0 to
1). Once `ready`, `url` downloads the MP4 (valid 15 minutes, as an attachment named after the
title). It's kept 7 days (`expiresAt`), then `expired`: make it again. `failed` has its
`failure`: `render_error`, or `timed_out` when it waited more than an hour. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `videos.show.json` | `videos.show.200.json` |
| 404 | `not_found` | `error.json` | `videos.show.not_found.json` |

#### `reports.create`

Report a shared harmony, with or without an account (the Report button on `/h/<id>`; the DSA's
notice route). **Request:** `{ "harmonyId": "<id>", "reason": "copyright", "note": "...",
"website": "" }`:
- in place of `harmonyId`, once the gallery is open (N1): `commentId` (a comment; two different
  people's reports hold it for review) or `handle` (a public profile); once the library is open
  (N3), `templateId` (a public template; enough people's reports hold it for review, as for a
  page);
  `ideaId` (an idea), `choirVoiceId` (a voice in a virtual choir, N6: enough people's reports
  take it out of the next mix) or `requestId` (a song request, N7);
- or `boardEntryId` (a part leaderboard's entry, `boards.show` `entries[].id`: "report this name"),
  signed in only: `204` for a live entry (your own and repeats ignored), `404` otherwise; 2
  different people's reports hold it off the global board until an admin looks
  (`admin.boards.flags.list`, `reason: "reported"`);
- `reason`: `copyright`, `offensive`, `spam`, `personal` (someone's private information, or a
  recording of someone without their OK) or `other`; `note` 0 to 500 characters, plain text;
- `website` is the honeypot (left empty; a filled one is ignored), and in shield mode
  `turnstileToken` is needed (`403 turnstile_required`, `turnstile_failed`);
- always `204` for a harmony whose page is live, even when the report is ignored (your own
  harmony, a repeat from the same person, the honeypot), so nothing can be learned from it; any
  other id answers `404`, as its page does. Reports from 3 different people hide the page until
  Hillel looks: counted one per network, and never from an account that hasn't verified its email;
- limited (`reports`: 10 an hour, per account or network).

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `turnstile_required` | `error.json` | `reports.create.turnstile_required.json` |
| 403 | `turnstile_failed` | `error.json` | `reports.create.turnstile_failed.json` |
| 404 | `not_found` | `error.json` | `reports.create.not_found.json` |
| 422 | `validation_failed` | `error.json` | `reports.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `reports.create.rate_limited.json` |

#### `admin.reports.list`

The trust and safety queue's harmonies: each with open reports, hidden by reports, or with a held
title, oldest first, with its reports. `?status=closed` lists those dealt with, newest first.
**Request:** `?status=open|closed`, `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.reports.list.json` | `admin.reports.list.200.json` |

#### `admin.harmonies.hide`

Take a page down (it answers "not available" and its files leave the CDN), upholding its open
reports: the owner drops to trust level 1 for 30 days (lifted again if Hillel restores it and
nothing else of theirs was taken down meanwhile). **Request:** `{ "reason": "dmca", "note":
"Notice from ... on 2026-10-26" }`; `reason` is `dmca` (a copyright notice) or `admin`, `note` 1 to
500 characters. Audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.harmony.item.json` | `admin.harmonies.hide.200.json` |
| 404 | `not_found` | `error.json` | `admin.harmonies.hide.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.harmonies.hide.validation_failed.json` |

#### `admin.harmonies.restore`

Put it back as the owner left it, dismissing its open reports and releasing a held title (on a
harmony that isn't hidden, this is Dismiss). A removed harmony can't come back (`409
harmony_removed`). **Request:** `{ "note": "..." }` (optional). Audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.harmony.item.json` | `admin.harmonies.restore.200.json` |
| 404 | `not_found` | `error.json` | `admin.harmonies.restore.not_found.json` |
| 409 | `harmony_removed` | `error.json` | `admin.harmonies.restore.harmony_removed.json` |

#### `admin.harmonies.remove`

The takedown made final: its audio, card and videos are deleted, the page answers "removed",
and its open reports are upheld. The record stays, for repeat infringers (`ownerTakedowns`).
**Request:** `{ "reason": "dmca", "note": "..." }`, as for hide. In sudo; audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.harmony.item.json` | `admin.harmonies.remove.200.json` |
| 404 | `not_found` | `error.json` | `admin.harmonies.remove.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.harmonies.remove.validation_failed.json` |

### Making your own, together: templates, collaboration and choir groups (M7)

#### Your own templates

**Templates people make** (`docs/template-creator-plan.md`, `docs/templates-plan.md` Phase 2) sync
to the account as private or unlisted templates. There's no public library yet, so neither is
listed anywhere; "List this template in the library when it opens" is an opt-in kept for later
(`libraryOptIn`).

- **IDs:** a template made on the device keeps its UUID (the template JSON's `id`), here and in a
  project's `template.id`. It's also an unlisted template's link (`/t/<id>` in the app), so it's
  never a Sqid.
- **Versions:** each save is a new immutable version (the JSON's `version`, 1 and up, each higher
  than the last). Saving a version that exists answers it as it is when it's the same JSON, and
  `409 version_exists` otherwise. Projects keep the version they started from.
- **The format** is `template.json`, checked on the server as in the app, at most 512 KB. The
  title and credits refuse links (`422`). `reviewed` is the creator's to set.
- **The limit:** a free account keeps 1 private or unlisted template on the server; Plus keeps any
  number (`403 template_limit`). A device template past the limit stays on the device, marked "not
  saved to your account", as projects do. After a downgrade, templates over the limit stay
  usable and readable but take no new versions (`403 template_read_only`); nothing is deleted.
- **Who may open one** (`templates.show`): the owner always; anyone with the link while it's
  `unlisted` (signed out too); and members of a choir group it's shared with. Anyone else gets
  `404 not_found`, the same as a template that isn't there.
- **The creator's own recorded guides** (Plus): one per part, uploaded like a take (16-bit mono
  WAV, presigned `PUT`, then `complete`), made into AAC and kept in `private`. `templates.show`
  lists them in `voices`, with `url` valid for two hours (its ETag changes every hour, so a cached
  answer's links still work), and they count toward the owner's storage.

#### `templates.mine`

The person's own templates, newest first (deleted ones aren't listed). **Request:** `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.mine.json` | `templates.mine.200.json` |

#### `templates.save`

Make a template, or add a version. **Request:** `{ "changeId": "<uuid>", "template": { ...the
whole template.json, with its id and version }, "visibility": "private", "libraryOptIn": false }`;
the path's id must be the JSON's `id`. A person's template may have 1 to 6 parts with keys of their own
(lower-case letters, digits and dashes, starting with a letter, up to 40), names of up to 40
characters, and `kind: "instrument"` for an instrument (docs/instrument-parts-plan.md); official
templates stay soprano, alto, tenor and bass, or barbershop's tenor, lead, baritone and bass (never mixed), all voices. A song made in the builder carries its
`builder` record (the progression, sound, mix and sections), checked and kept as sent. `visibility` and `libraryOptIn` are optional after the
first save (they stay as they were).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.mine.item.json` | `templates.save.200.json` |
| 403 | `email_unverified` | `error.json` | `templates.save.email_unverified.json` |
| 403 | `template_limit` | `error.json` | `templates.save.template_limit.json` |
| 403 | `template_read_only` | `error.json` | `templates.save.template_read_only.json` |
| 404 | `not_found` | `error.json` | `templates.save.not_found.json` |
| 409 | `version_exists` | `error.json` | `templates.save.version_exists.json` |
| 422 | `validation_failed` | `error.json` | `templates.save.validation_failed.json` |

#### `templates.update`

Who may open it, and the library opt-in. **Request:** `{ "visibility": "unlisted",
"libraryOptIn": true }`, either or both.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.mine.item.json` | `templates.update.200.json` |
| 403 | `template_limit` | `error.json` | `templates.update.template_limit.json` |
| 404 | `not_found` | `error.json` | `templates.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `templates.update.validation_failed.json` |

#### `templates.delete`

Gone for everyone with the link, and from groups it was shared with. Projects already made from
it keep their copy. Deleting one already gone answers `204`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

**`templates.show`, changed in M7:** 
As before for the official songs. For a person's own template, `grant` is `own` (the owner),
`link` (unlisted, anyone with the link), `group` (shared with a choir group the caller is in) or
`project` (a singer on a project made from it, while that project isn't binned);
`voices` holds the creator's recorded guides; `?version=` picks an older version. A choir group's
Plus song gives its members `grant: "group"` too, while the group's director has Plus.

#### `templates.recordings.create`

Start sending the creator's guide for one part (Plus). **Request:** `{ "id": "<uuid>", "partKey":
"alto", "sampleRate": 48000, "frames": 2304000, "offsetFrames": 0, "bytes": 4608044, "sha256":
"<64 hex>" }`, as for `takes.create` (a 16-bit mono WAV, at most 10 minutes). It replaces that
part's earlier recording once it's ready. The `upload` works for 10 minutes.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `templates.recording.create.json` | `templates.recordings.create.201.json` |
| 403 | `age_unconfirmed` | `error.json` | `templates.recordings.create.age_unconfirmed.json` |
| 403 | `plus_required` | `error.json` | `templates.recordings.create.plus_required.json` |
| 403 | `storage_limit` | `error.json` | `templates.recordings.create.storage_limit.json` |
| 404 | `not_found` | `error.json` | `templates.recordings.create.not_found.json` |
| 422 | `validation_failed` | `error.json` | `templates.recordings.create.validation_failed.json` |

#### `templates.recordings.complete`

As `takes.complete` (`uploading`, `processing`, then `ready` or `failed`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.recording.show.json` | `templates.recordings.complete.200.json` |
| 409 | `upload_incomplete` | `error.json` | `templates.recordings.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `templates.recordings.complete.upload_mismatch.json` |
| 404 | `not_found` | `error.json` | `templates.recordings.complete.not_found.json` |

#### `templates.recordings.delete`

That part goes back to the synth. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### Collaboration: friends on a project

**Taking turns on one project** (`docs/backend-plan.md` C7; no real-time editing). The owner
invites friends by link to sing; each **singer** adds and replaces their own parts, and hears
everyone's. The owner can change anything.

- **Capacity follows the owner** (`docs/pricing-plan.md`, "Who a feature belongs to"): the
  project, its storage (everyone's takes in it count toward the owner's allowance) and its singers
  (a free owner's project takes 2 singers, a Plus owner's 20: `403 invite_limit`). A singer's own
  Plus features (Plus songs, MP3) follow the singer.
- **Shared projects aren't in a singer's `sync.pull`** (that stays the person's own). They're in
  `projects.shared`, and `projects.show` brings one up to date: the app calls it when the project
  is opened, and when the app comes back to the front. The owner's devices see the singers' parts
  and takes in their own `sync.pull`, as usual.
- **What a singer may do,** with the same endpoints as the owner:
  - `parts.save` to make a part (it's theirs) or change their own; another's part answers `403
    not_your_part`;
  - `parts.delete` and `takes.create`, `takes.restore` on their own parts;
  - `projects.show` and `takes.show` for every part;
  - not `projects.save`, `projects.delete` or `projects.restore` (`403 owner_only`): the project's
    title, tempo, key and settings are the owner's;
  - everything else about a shared project answers as for the owner (`409 binned` when the owner
    binned it, `403 project_read_only` when the owner's plan made it read-only).
- **A part's `createdBy`** (in `part.json`): `null` for the owner's parts, or the singer's id and
  name, so the app can show "Sam's part" and lock the others.
- **Leaving** (or being removed by the owner): the person's parts stay or go, as they choose
  (`keepParts`); kept parts become the owner's. Once removed by the owner, `projects.show` answers `410
  removed_from_project` (not `404`), so the app can say so kindly and keep the singer's own takes
  on their device; joining again by a new invite clears it.
- **Invites** are links people send themselves (no email from us): `/join/<token>`, valid 14 days,
  until the owner revokes them. Each can name the part it's for (`partKey` and a name to show), and
  is used once.

#### `projects.shared`

The projects the person sings in but doesn't own (not the ones the owner has binned), most recently
changed first (a singer's part counts as a change): each project as
`projects.show` gives it (with parts and current takes), plus `owner` and `role`. **Request:**
`?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `projects.shared.json` | `projects.shared.200.json` |

#### `projects.members.list`

Who's on the project: the owner first, then the singers. Anyone on the project may see it.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `projects.members.json` | `projects.members.list.200.json` |
| 404 | `not_found` | `error.json` | `projects.members.list.not_found.json` |

#### `projects.members.delete`

The owner removes a singer, or a singer leaves (their own id). **Request:** `?keepParts=0` to delete
their parts; they're kept (as the owner's) unless asked, so leaving never loses someone's singing
by accident. The owner can't leave
(`403 owner_only`; they delete the project instead).

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `owner_only` | `error.json` | `projects.members.delete.owner_only.json` |
| 404 | `not_found` | `error.json` | `projects.members.delete.not_found.json` |

#### `invites.create`

The owner makes an invite link. **Request:** `{ "partKey": "tenor", "partName": "Tenor" }`, both
optional. The answer has the link's `url` (with its token): shown once, as the server keeps only a
hash.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `invite.created.json` | `invites.create.201.json` |
| 403 | `invite_limit` | `error.json` | `invites.create.invite_limit.json` |
| 403 | `owner_only` | `error.json` | `invites.create.owner_only.json` |
| 404 | `not_found` | `error.json` | `invites.create.not_found.json` |
| 409 | `binned` | `error.json` | `invites.create.binned.json` |

#### `invites.list`

The project's open invites (not used, revoked or expired), for the owner. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invites.list.json` | `invites.list.200.json` |
| 403 | `owner_only` | `error.json` | `invites.list.owner_only.json` |

#### `invites.revoke`

**Request:** none. Revoking one already used or revoked answers `204`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `invites.show`

What an invite link is for, to anyone holding it (signed out too): the project's title, the
owner's name, the part, and the song. Limited per network (`invite-links`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invite.json` | `invites.show.200.json` |
| 404 | `not_found` | `error.json` | `invites.show.not_found.json` |
| 410 | `invite_expired` | `error.json` | `invites.show.invite_expired.json` |
| 410 | `invite_revoked` | `error.json` | `invites.show.invite_revoked.json` |
| 410 | `invite_used` | `error.json` | `invites.show.invite_used.json` |

#### `invites.accept`

Join, signed in (a new person signs up first, keeping the link). Accepting your own project's
invite, or one you've already joined, answers the project as it is. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `projects.show.json` | `invites.accept.200.json` |
| 403 | `age_unconfirmed` | `error.json` | `invites.accept.age_unconfirmed.json` |
| 403 | `invite_limit` | `error.json` | `invites.accept.invite_limit.json` |
| 410 | `invite_expired` | `error.json` | `invites.accept.invite_expired.json` |
| 410 | `invite_revoked` | `error.json` | `invites.accept.invite_revoked.json` |
| 410 | `invite_used` | `error.json` | `invites.accept.invite_used.json` |
| 404 | `not_found` | `error.json` | `invites.accept.not_found.json` |

#### Choir groups

**A director's group** (Plus; `docs/pricing-plan.md`, "A choir plan"): members join by the group's
link, the director shares songs (official, or their own templates) and assigns parts, and sees who
has recorded theirs.

- **Who pays:** the director's Plus covers the group; members join free. While they're in it,
  members may learn the group's songs, Plus songs included (`grant: "group"`), and their projects
  from them stay editable, as long as the director has Plus (a lapsed director's group gets
  `403 plus_required` for its Plus songs, as anyone without Plus would).
- **Sizes:** up to 60 members a group, 5 groups a director (`403 group_limit`).
- **18 and over, for now:** until Hillel decides how members under 18 take part, making or joining
  a group asks once that the person is 18 or over (`"adult": true` in the request, kept on the
  account; without it, `403 adult_required`, and the app asks).
- **What the director sees of members:** their name, each assignment's status (`none`, `started`
  when they have a project from the song, `recorded` once that project's part for the assigned
  `partKey` has a ready take) and when. Never their recordings or contact details.
- **Leaving:** a member leaves, or the director removes them; their projects stay theirs (a Plus
  song's project goes read-only again, unless they have Plus). If the director's Plus ends, the
  group stays but is read-only: no new songs, members or assignments (`403 plus_required`), and
  members keep learning what's shared until it's deleted.
- **The group's link** (`/choir/<token>`) is one per group, made again by the director (the old one
  stops); no email from us.

#### `groups.list`

The groups the person directs or sings in. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.list.json` | `groups.list.200.json` |

#### `groups.create`

**Request:** `{ "name": "Thursday Singers", "adult": true }` (the name 1 to 80 characters, no links).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `groups.show.json` | `groups.create.201.json` |
| 403 | `plus_required` | `error.json` | `groups.create.plus_required.json` |
| 403 | `group_limit` | `error.json` | `groups.create.group_limit.json` |
| 422 | `validation_failed` | `error.json` | `groups.create.validation_failed.json` |
| 403 | `adult_required` | `error.json` | `groups.create.adult_required.json` |

#### `groups.show`

A group as its director or a member sees it: the songs, the members (the director sees each
member's assignments and their status; a member sees their own), and, for the director, `link`
(the join link, once made). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.show.json` | `groups.show.200.json` |
| 404 | `not_found` | `error.json` | `groups.show.not_found.json` |

#### `groups.update`

Rename it (director). **Request:** `{ "name": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.show.json` | `groups.update.200.json` |
| 403 | `director_only` | `error.json` | `groups.update.director_only.json` |
| 422 | `validation_failed` | `error.json` | `groups.update.validation_failed.json` |
| 404 | `not_found` | `error.json` | `groups.update.not_found.json` |

#### `groups.delete`

The director ends the group; members' projects stay theirs. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `director_only` | `error.json` | `groups.delete.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.delete.not_found.json` |

#### `groups.link.create`

A new join link (the old one stops working). The answer has `url`; the director sees it again in
`groups.show`'s `link.url` (a link made before links were kept shows `url: null`, and the app
offers Reset link). A member never sees it. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `group.link.json` | `groups.link.create.201.json` |
| 403 | `director_only` | `error.json` | `groups.link.create.director_only.json` |
| 403 | `plus_required` | `error.json` | `groups.link.create.plus_required.json` |

#### `groups.join.show`

What a group link is for, to anyone holding it: the group's name, the director's name and how many
sing in it. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `group.join.json` | `groups.join.show.200.json` |
| 404 | `not_found` | `error.json` | `groups.join.show.not_found.json` |

#### `groups.join`

Join, signed in, with `{ "adult": true }` the first time. Already a member: the group as it is. A group whose director's Plus has ended takes no new members (`403 plus_required`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.show.json` | `groups.join.200.json` |
| 403 | `age_unconfirmed` | `error.json` | `groups.join.age_unconfirmed.json` |
| 403 | `group_full` | `error.json` | `groups.join.group_full.json` |
| 403 | `plus_required` | `error.json` | `groups.join.plus_required.json` |
| 404 | `not_found` | `error.json` | `groups.join.not_found.json` |
| 403 | `adult_required` | `error.json` | `groups.join.adult_required.json` |

#### `groups.members.delete`

The director removes a member, or a member leaves (their own id). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `director_only` | `error.json` | `groups.members.delete.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.members.delete.not_found.json` |

#### `groups.songs.create`

Share a song with the group (director): an official song's slug, or one of the director's own
templates. **Request:** `{ "templateId": "amazing-grace" }`. Sharing it again answers the group as
it is.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.show.json` | `groups.songs.create.200.json` |
| 403 | `director_only` | `error.json` | `groups.songs.create.director_only.json` |
| 403 | `plus_required` | `error.json` | `groups.songs.create.plus_required.json` |
| 404 | `not_found` | `error.json` | `groups.songs.create.not_found.json` |

#### `groups.songs.delete`

Stop sharing it (and its assignments go). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `director_only` | `error.json` | `groups.songs.delete.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.songs.delete.not_found.json` |

#### `groups.assignments.update`

Who sings which part of a song (director): the whole list for that song, replacing the last.
**Request:** `{ "assignments": [{ "memberId": "<id>", "partKey": "alto" }] }`; a member may have
one part of a song.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.show.json` | `groups.assignments.update.200.json` |
| 403 | `director_only` | `error.json` | `groups.assignments.update.director_only.json` |
| 403 | `plus_required` | `error.json` | `groups.assignments.update.plus_required.json` |
| 422 | `validation_failed` | `error.json` | `groups.assignments.update.validation_failed.json` |
| 404 | `not_found` | `error.json` | `groups.assignments.update.not_found.json` |

### Coaching, without the directory (M8)

**Coaches sell to the people who come through their own profile link** (`docs/coaching-marketplace-plan.md`;
the directory waits for a network). Approved coaches sell take reviews, ongoing coaching (a monthly
package) and exercise packs; singers pay in the app; Stackapella keeps a commission
(`config/coaching.php`, 15%, proposed). Live lessons come with Event Schedule (M9).

- **Who:** coaching is 18 and over, both sides (`adult: true` on the application and the first
  purchase, kept on the account). A coach needs an approved application and a Stripe Express
  account that can take charges (`charges_enabled`) before anything of theirs can be bought
  (`409 coach_not_ready`). An approved, active coach has Plus free.
- **Money:**
  - **a take review** is paid when it's requested, on Stackapella's account; the coach has 48
    hours to accept, then their turnaround (2 to 7 days) to deliver. A decline, no answer, a
    missed deadline or the singer cancelling before it's accepted refunds in full, automatically.
    **The coach's share is transferred when the review is delivered** (the commission stays);
  - **ongoing coaching** is a monthly subscription (Stripe moves the coach's share each month, less
    the commission): a number of reviews a month (unused ones don't roll over) and messages; the
    singer cancels any time, and it lasts to the end of the month;
  - **an exercise pack** is free or a one-off price (the coach's share moves at once); Hillel
    reviews each before it can be sold;
  - prices are whole cents in the coach's currency (`usd`, `eur` or `gbp`), at least 5.00;
  - Checkout opens on Stripe (`checkoutUrl`), and the app comes back to `/coaching/return?engagement=<id>`;
    the webhook makes it real. While Stripe isn't set up (no keys), buying answers `423 paused`.
- **The review workspace's data:** the singer sends a take from a project (the server keeps its
  own copy, so deleting the take later doesn't touch the review), the template's id and version and
  the part, the pitch track and scores from the device, kept and given back as sent (`pitchTrack`,
  at most 1 MB as JSON: `{ "version": 1, "rate": 100, "startSeconds", "hz": "<base64 Float32>",
  "clarity": "<base64 Uint8>", "notes": [...], "beatSeconds", "transpose" }`, and `scores`, the
  scorer's own result), and a question. The coach works on a draft: pinned `comments` (each with the device's `id`, a
  UUID, made by the server when it's left out; `atSeconds`, optional `noteIndex`, `text`, or a
  voice note's `mediaId`), a `summary` (`working`, `toWorkOn` (2 or 3), `practise`), assigned
  `exercises` (`{ "packId": null, "exerciseId": "breath-4-4" }`: the app's own library when
  `packId` is null, else one of a pack's), and a demonstration take. Voice notes and demo
  takes upload like takes (`reviews.media.*`) and come back as AAC. Delivering is final after a
  10-minute `reviews.unsend` window. The singer gets one follow-up question, and the coach one answer.
- **Access:** each side sees only what was sent; the coach's access ends 90 days after the
  engagement ends (an active package keeps it open). Text people write (comments, messages,
  answers) is plain text; a message that asks to pay outside the app (an email, a phone number, a
  payment app's name) is delivered with a warning to the singer and flagged for Hillel.
- **Ratings:** after a delivered review, or a month of coaching: 1 to 5 stars and an optional text
  (500 characters), the coach's one public reply; editable for 30 days; shown on the profile as an
  average once there are 3 (before that, "New coach").
- **Send to my teacher** (Plus): a singer invites their own teacher by a link (`/teacher/<token>`);
  the teacher signs in (any account, no approval) and reviews the takes the singer sends, in the
  same workspace, with no money, commission or rating. Either side can end it.
- **Report a problem** on any engagement goes to Hillel, who can refund in full or in part.
- **Suspending or pausing a coach** stops their offerings; suspending also refunds their open,
  undelivered reviews.

#### `coach.application.show`

The person's application, or `null` before one. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.application.show.json` | `coach.application.show.200.json` |

#### `coach.application.create`

Apply to coach. **Request:** `{ "experience": "...", "training": "...", "voiceTypes": ["alto"],
"styles": ["choral"], "languages": ["en"], "timezone": "Europe/London", "introUrl": "https://...",
"sampleReview": { "comments": [{ "atSeconds": 12.5, "text": "..." }], "summary": { "working": "...",
"toWorkOn": ["...", "..."], "practise": "..." } }, "agreementVersion": 1, "adult": true }`. The
sample review is of the sample take (`sampleTakeUrl` in `coach.application.show`). A handle is
needed first (`409 handle_required`). One open application at a time; a declined one can apply again after 30 days.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `coach.application.show.json` | `coach.application.create.201.json` |
| 409 | `handle_required` | `error.json` | `coach.application.create.handle_required.json` |
| 409 | `coach_signup_closed` | `error.json` | `coach.application.create.coach_signup_closed.json` |
| 409 | `application_open` | `error.json` | `coach.application.create.application_open.json` |
| 422 | `validation_failed` | `error.json` | `coach.application.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `coach.application.create.rate_limited.json` |

#### `coach.profile.show`

The coach's own profile, with whether they can sell yet. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.profile.json` | `coach.profile.show.200.json` |
| 404 | `not_found` | `error.json` | `coach.profile.show.not_found.json` |

#### `coach.profile.update`

**Request:** any of `bio` (1000 characters), `voiceTypes`, `styles`, `languages`, `timezone`,
`cancellationHours` (for lessons, 0 to 168). No links in the bio.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.profile.json` | `coach.profile.update.200.json` |
| 404 | `not_found` | `error.json` | `coach.profile.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `coach.profile.update.validation_failed.json` |

#### `coach.connect.create`

Start (or continue) Stripe Express onboarding: the answer's `url` opens Stripe, which comes back to
the app's `/coaching/connected` (beside `/coaching/return`; `/coach/<handle>` is the public page). **Request:** `{ "country": "GB" }` (the first time).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.connect.link.json` | `coach.connect.create.200.json` |
| 404 | `not_found` | `error.json` | `coach.connect.create.not_found.json` |
| 423 | `paused` | `error.json` | `coach.connect.create.paused.json` |

#### `coach.connect.show`

Where onboarding stands, asked of Stripe again: `chargesEnabled`, `payoutsEnabled`,
`detailsSubmitted`, and `dashboardUrl` (the coach's Express dashboard, once onboarded).
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.connect.json` | `coach.connect.show.200.json` |
| 404 | `not_found` | `error.json` | `coach.connect.show.not_found.json` |

#### `coach.offerings.list`

The coach's offerings (archived ones too, marked). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.offerings.list.json` | `coach.offerings.list.200.json` |

#### `coach.offerings.create`

**Request:** `{ "kind": "review", "title": "A take review", "description": "...", "priceCents":
2500, "currency": "gbp", "turnaroundDays": 5, "maxSeconds": 180 }`; a `package` has
`reviewsPerMonth` (1 to 20) and no `turnaroundDays` beyond each review's (still 2 to 7).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `coach.offering.show.json` | `coach.offerings.create.201.json` |
| 404 | `not_found` | `error.json` | `coach.offerings.create.not_found.json` |
| 409 | `event_schedule_required` | `error.json` | `coach.offerings.create.event_schedule_required.json` |
| 422 | `validation_failed` | `error.json` | `coach.offerings.create.validation_failed.json` |

#### `coach.offerings.update`

Any field but `kind`; a price change applies to purchases from now (a package already bought keeps
its price).
**Request:** the fields to change, or `{ "archived": true }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.offering.show.json` | `coach.offerings.update.200.json` |
| 404 | `not_found` | `error.json` | `coach.offerings.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `coach.offerings.update.validation_failed.json` |

#### `coach.packs.list`

The coach's exercise packs and where each is in review. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.packs.list.json` | `coach.packs.list.200.json` |

#### `coach.packs.save`

Make or change a pack (a draft, or one sent back): **Request:** `{ "id": "<uuid>", "title":
"...", "description": "...", "priceCents": 0, "currency": "usd", "exercises": [...] }` (the
warm-ups' exercise format, at most 30, 256 KB). Changing an approved pack sends it back to review.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.pack.show.json` | `coach.packs.save.200.json` |
| 404 | `not_found` | `error.json` | `coach.packs.save.not_found.json` |
| 422 | `validation_failed` | `error.json` | `coach.packs.save.validation_failed.json` |

#### `coach.packs.submit`

Send a draft for Hillel's review. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.pack.show.json` | `coach.packs.submit.200.json` |
| 404 | `not_found` | `error.json` | `coach.packs.submit.not_found.json` |

#### `coaches.show`

A coach's public profile, by handle, for anyone (signed out too): the profile, active offerings
with prices, approved packs, and ratings (the average once there are 3). Only approved, active
coaches; anyone else is `404`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coaches.show.json` | `coaches.show.200.json` |
| 404 | `not_found` | `error.json` | `coaches.show.not_found.json` |

#### `engagements.create`

Buy from a coach. **Request:** one of:
- a review: `{ "offeringId": "<id>", "takeId": "<uuid>", "question": "...", "pitchTrack": {...},
  "scores": {...}, "adult": true }`: the take must be the singer's own, `ready`, and no longer
  than the offering's `maxSeconds`;
- a package: `{ "offeringId": "<id>", "adult": true }`;
- a pack: `{ "packId": "<id>", "adult": true }` (a free pack is the singer's at once, `201`
  with no `checkoutUrl`).

The answer's `checkoutUrl` opens Stripe Checkout (valid 30 minutes); the engagement waits as
`pending_payment` until the webhook. Limited (`checkout`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `engagements.create.json` | `engagements.create.201.json` |
| 403 | `age_unconfirmed` | `error.json` | `engagements.create.age_unconfirmed.json` |
| 404 | `not_found` | `error.json` | `engagements.create.not_found.json` |
| 409 | `coach_not_ready` | `error.json` | `engagements.create.coach_not_ready.json` |
| 409 | `own_coaching` | `error.json` | `engagements.create.own_coaching.json` |
| 409 | `slot_taken` | `error.json` | `engagements.create.slot_taken.json` |
| 422 | `validation_failed` | `error.json` | `engagements.create.validation_failed.json` |
| 423 | `paused` | `error.json` | `engagements.create.paused.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `engagements.create.event_schedule_unavailable.json` |
| 429 | `rate_limited` | `error.json` | `engagements.create.rate_limited.json` |

#### `engagements.list`

The person's engagements, newest first: as the singer (`?as=singer`, the default) or the coach
(`?as=coach`), including Send to my teacher's. **Request:** `?as=`, `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.list.json` | `engagements.list.200.json` |

#### `engagements.show`

One engagement, as either side sees it: its reviews (each with its take's `url` and `pitchTrackUrl`, the pitch track's JSON as sent,
the review once delivered, or the draft for the coach), and what each side may do next
(`can`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `engagements.show.200.json` |
| 404 | `not_found` | `error.json` | `engagements.show.not_found.json` |

#### `engagements.accept`

The coach takes a requested review (within 48 hours). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `engagements.accept.200.json` |
| 404 | `not_found` | `error.json` | `engagements.accept.not_found.json` |
| 409 | `engagement_state` | `error.json` | `engagements.accept.engagement_state.json` |

#### `engagements.decline`

The coach declines a requested review: refunded in full. **Request:** `{ "reason": "..." }`
(optional, shown to the singer).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `engagements.decline.200.json` |
| 404 | `not_found` | `error.json` | `engagements.decline.not_found.json` |
| 409 | `engagement_state` | `error.json` | `engagements.decline.engagement_state.json` |

#### `engagements.cancel`

The singer cancels: a review not yet accepted is refunded in full; a package stops at the end of
its month; a teacher link ends (either side). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `engagements.cancel.200.json` |
| 404 | `not_found` | `error.json` | `engagements.cancel.not_found.json` |
| 409 | `engagement_state` | `error.json` | `engagements.cancel.engagement_state.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `engagements.cancel.event_schedule_unavailable.json` |

#### `engagements.report`

Report a problem to Hillel (either side). **Request:** `{ "text": "..." }` (1 to 2000). Limited
(`reports`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `engagements.report.not_found.json` |
| 422 | `validation_failed` | `error.json` | `engagements.report.validation_failed.json` |

#### `reviews.create`

Send another take on a package (one of the month's reviews) or to a teacher. **Request:** `{
"takeId": "<uuid>", "question": "...", "pitchTrack": {...}, "scores": {...} }`. A package with
none left this month answers `409 reviews_used`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `review.show.json` | `reviews.create.201.json` |
| 404 | `not_found` | `error.json` | `reviews.create.not_found.json` |
| 409 | `reviews_used` | `error.json` | `reviews.create.reviews_used.json` |
| 409 | `engagement_state` | `error.json` | `reviews.create.engagement_state.json` |
| 422 | `validation_failed` | `error.json` | `reviews.create.validation_failed.json` |

#### `reviews.save`

The coach's draft, saved as they work (the whole draft each time). **Request:** `{ "comments":
[...], "summary": {...}, "exercises": [{ "packId": null, "exerciseId": "breath-4-4" }],
"demoMediaId": null }` (comments at most 100, each text at most 1000).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.show.json` | `reviews.save.200.json` |
| 404 | `not_found` | `error.json` | `reviews.save.not_found.json` |
| 409 | `engagement_state` | `error.json` | `reviews.save.engagement_state.json` |
| 422 | `validation_failed` | `error.json` | `reviews.save.validation_failed.json` |

#### `reviews.deliver`

The coach delivers: the singer sees it, and (for a paid review) the coach's share moves after the
10-minute Unsend window. **Request:** none. A draft with no summary answers `422`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.show.json` | `reviews.deliver.200.json` |
| 404 | `not_found` | `error.json` | `reviews.deliver.not_found.json` |
| 409 | `engagement_state` | `error.json` | `reviews.deliver.engagement_state.json` |
| 422 | `validation_failed` | `error.json` | `reviews.deliver.validation_failed.json` |

#### `reviews.unsend`

Within 10 minutes of delivering: back to a draft. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.show.json` | `reviews.unsend.200.json` |
| 409 | `unsend_window` | `error.json` | `reviews.unsend.unsend_window.json` |

#### `reviews.followup`

The singer's one follow-up question, after delivery. **Request:** `{ "question": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.show.json` | `reviews.followup.200.json` |
| 409 | `engagement_state` | `error.json` | `reviews.followup.engagement_state.json` |

#### `reviews.answer`

The coach's one answer to it. **Request:** `{ "text": "...", "mediaId": null }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.show.json` | `reviews.answer.200.json` |
| 409 | `engagement_state` | `error.json` | `reviews.answer.engagement_state.json` |

#### `reviews.media.create`

Start sending a voice note or demonstration take for a review (the coach), as `takes.create`: a
16-bit mono WAV, a voice note at most 2 minutes, a demo at most 10. **Request:** `{ "id":
"<uuid>", "kind": "voice_note", "sampleRate": 48000, "frames": 480000, "bytes": 960044, "sha256":
"..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `review.media.create.json` | `reviews.media.create.201.json` |
| 404 | `not_found` | `error.json` | `reviews.media.create.not_found.json` |
| 422 | `validation_failed` | `error.json` | `reviews.media.create.validation_failed.json` |

#### `reviews.media.complete`

As `takes.complete`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `review.media.show.json` | `reviews.media.complete.200.json` |
| 409 | `upload_incomplete` | `error.json` | `reviews.media.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `reviews.media.complete.upload_mismatch.json` |

#### `messages.list`

An engagement's thread, oldest first (a package's, a teacher's, or a review's). **Request:**
`?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `messages.list.json` | `messages.list.200.json` |
| 404 | `not_found` | `error.json` | `messages.list.not_found.json` |

#### `messages.create`

**Request:** `{ "text": "...", "mediaId": null }` (text 1 to 2000, or a voice note sent first by
`reviews.media.*` on the engagement's latest review). Limited (`coach-messages`: 30 an hour a
thread). A message asking to pay outside the app is sent with `warning: "off_platform"`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `message.show.json` | `messages.create.201.json` |
| 404 | `not_found` | `error.json` | `messages.create.not_found.json` |
| 409 | `engagement_state` | `error.json` | `messages.create.engagement_state.json` |
| 422 | `validation_failed` | `error.json` | `messages.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `messages.create.rate_limited.json` |

#### `ratings.create`

The singer rates a finished engagement (or changes their rating, within 30 days). **Request:**
`{ "stars": 5, "text": "..." }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `rating.show.json` | `ratings.create.200.json` |
| 404 | `not_found` | `error.json` | `ratings.create.not_found.json` |
| 409 | `engagement_state` | `error.json` | `ratings.create.engagement_state.json` |
| 422 | `validation_failed` | `error.json` | `ratings.create.validation_failed.json` |

#### `ratings.reply`

The coach's one public reply. **Request:** `{ "text": "..." }` (1 to 500).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `rating.show.json` | `ratings.reply.200.json` |
| 404 | `not_found` | `error.json` | `ratings.reply.not_found.json` |
| 409 | `engagement_state` | `error.json` | `ratings.reply.engagement_state.json` |

#### `packs.mine`

The exercise packs the person has (bought or free), with their exercises, for the warm-ups
library. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `packs.mine.json` | `packs.mine.200.json` |

#### `teacher.links.create`

Invite your own teacher (Plus). The answer's `url` (`/teacher/<token>`) is shown once. Like all
coaching it's 18 and over on both sides: `"adult": true` the first time (`403 adult_required`
otherwise, and the app asks). **Request:** `{ "name": "Ms Okafor", "adult": true }` (who it's for,
for the singer's list).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `teacher.link.created.json` | `teacher.links.create.201.json` |
| 403 | `plus_required` | `error.json` | `teacher.links.create.plus_required.json` |
| 422 | `validation_failed` | `error.json` | `teacher.links.create.validation_failed.json` |
| 403 | `adult_required` | `error.json` | `teacher.links.create.adult_required.json` |

#### `teacher.links.show`

What a teacher link is for, to anyone holding it: the singer's name. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `teacher.link.json` | `teacher.links.show.200.json` |
| 404 | `not_found` | `error.json` | `teacher.links.show.not_found.json` |
| 410 | `invite_used` | `error.json` | `teacher.links.show.invite_used.json` |

#### `teacher.links.accept`

The teacher, signed in, takes it: an engagement of kind `teacher` begins. **Request:** `{ "adult":
true }` the first time.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `teacher.links.accept.200.json` |
| 404 | `not_found` | `error.json` | `teacher.links.accept.not_found.json` |
| 409 | `own_coaching` | `error.json` | `teacher.links.accept.own_coaching.json` |
| 410 | `invite_used` | `error.json` | `teacher.links.accept.invite_used.json` |
| 403 | `adult_required` | `error.json` | `teacher.links.accept.adult_required.json` |

#### `admin.coaching.applications.list`

Applications, waiting ones first, with the answers and the sample review. **Request:**
`?status=submitted|approved|declined`, `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.coaching.applications.list.json` | `admin.coaching.applications.list.200.json` |

#### `admin.coaching.applications.decide`

Approve or decline, with a note (shown to the applicant). **Request:** `{ "decision": "approve",
"note": "..." }`. Audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.coaching.application.json` | `admin.coaching.applications.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.coaching.applications.decide.not_found.json` |
| 409 | `engagement_state` | `error.json` | `admin.coaching.applications.decide.engagement_state.json` |

#### `admin.coaching.coaches.update`

Pause, suspend or reactivate a coach (suspending refunds their open, undelivered reviews).
**Request:** `{ "status": "suspended", "note": "..." }`. In sudo; audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.profile.json` | `admin.coaching.coaches.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.coaching.coaches.update.not_found.json` |

#### `admin.coaching.packs.list`

Packs waiting for review (or `?status=approved|rejected`). **Request:** `?status=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.packs.list.json` | `admin.coaching.packs.list.200.json` |

#### `admin.coaching.packs.decide`

**Request:** `{ "decision": "approve", "note": "..." }`. Audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coach.pack.show.json` | `admin.coaching.packs.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.coaching.packs.decide.not_found.json` |

#### `admin.coaching.disputes.list`

Reported engagements, open first, with each report and the engagement's money. **Request:**
`?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.coaching.disputes.list.json` | `admin.coaching.disputes.list.200.json` |

#### `admin.coaching.refund`

Refund an engagement in full or in part (and take the coach's share back, as far as it has
moved), closing its reports. **Request:** `{ "amountCents": 1250, "note": "..." }`. In sudo;
audit-logged.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `engagements.show.json` | `admin.coaching.refund.200.json` |
| 404 | `not_found` | `error.json` | `admin.coaching.refund.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.coaching.refund.validation_failed.json` |

### Event Schedule together: connections, live lessons and choirs (M9)

**Stackapella and Event Schedule (ES) work as one** (`docs/event-schedule-integration-plan.md`).
What Stackapella needs from ES is in `contract/event-schedule.md`; until ES has built it, the server
uses its fake (`EVENT_SCHEDULE_FAKE=true` locally), whose sign-in comes straight back.

- **Connecting:** Settings, then Connected apps, then **Connect Event Schedule**. The app calls
  `eventSchedule.connect.create` and opens the answer's `url` (ES's sign-in and approval) in the
  same tab. ES comes back to the PWA's page **`/connect/event-schedule/callback?code=&state=`**
  (or `?error=access_denied&state=` when the person says no), which posts `code` and `state` to
  `eventSchedule.connect.finish`, then picks a schedule (`eventSchedule.connection.update`). A
  selfhosted install connects with its address and an API key instead (`eventSchedule.connect.key`,
  with the warning that the key acts as the whole account). One connection per account; tokens never
  reach the app.
- **The connection's `status`:** `active`, or `needs_reconnect` when ES stopped accepting it (the
  app says "Reconnect Event Schedule" and starts again with `eventSchedule.connect.create`). A
  coach's lessons are on sale only while it's `active` with a schedule picked.
- **Live lessons:** a coach adds a `lesson` offering linked to one of their ES appointment types
  (`eventSchedule.types.list`), at their own price; ES keeps the length, availability and calendar.
  A singer sees the next free times (`coaches.slots`, in the singer's time zone), picks one and pays
  (`engagements.create`); the server then books it in ES, which emails the singer its confirmation
  and reminders. The engagement's `lesson` has the times, the join link, "Add to calendar" (ES's
  iCal) and ES's manage link (for rescheduling). Statuses:
  - `booking` (paid, being booked in ES; a few seconds), then `booked`, or `requested` while the
    coach approves it in ES (up to 48 hours, never past 2 hours before the start: then it's
    cancelled and refunded);
  - `delivered` once it's happened (ES says so after its end, or a day after its end with nothing
    cancelled; never before it ends, nor before the end it was bought for, whatever a move
    into the past says): the coach's share moves and the singer can rate it;
  - `refunded` (cancelled with a refund: by the coach, by the singer in time, `slot_taken` when
    the time went before it could be booked, `booking_failed`, `no_answer`), or `cancelled` (the
    singer cancelled too late for a refund under the coach's `cancellationHours` as they were when
    it was bought; `lesson.refundUntil` says until when, and only the coach's reschedule moves it
    later; a coach's move with less notice than the policy leaves it at the new start). On a selfhosted ES, every cancellation made there refunds in full.
  - Before it, the singer can send one take for the coach to review in the workspace
    (`reviews.create`); the lesson's messages close 30 days after it, as a review's do.
- **Choirs:** the director links the group to one of their ES schedules
  (`groups.eventSchedule.update`) and says which of the group's songs each rehearsal or concert is
  for (`groups.rehearsals.songs`). Everyone in the group sees the next rehearsals with their own
  part in each song and how far they've got (`groups.rehearsals.list`); the app counts the phrases
  still to learn from what it keeps. The director also sees the schedule's members in ES
  (`groups.eventSchedule.members`) to send them the group's link; Stackapella never emails them.
- **On ES's event pages,** a harmony's `/h/<id>` link plays in place (oEmbed and `/embed/h/<id>`,
  in "Public pages").
- **ES out of reach** answers `424 event_schedule_unavailable` (try again later), and a connection
  ES no longer accepts `409 reconnect_required`. While ES isn't set up on the server (no client),
  connecting answers `423 paused`.

#### `eventSchedule.connection.show`

The person's connection, or `null`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.connection.show.json` | `eventSchedule.connection.show.200.json` |

#### `eventSchedule.connect.create`

Start connecting (or reconnecting) by OAuth: open `url` in the same tab. Its `state` is good for 10
minutes. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.connect.json` | `eventSchedule.connect.create.200.json` |
| 423 | `paused` | `error.json` | `eventSchedule.connect.create.paused.json` |
| 429 | `rate_limited` | `error.json` | `eventSchedule.connect.create.rate_limited.json` |

#### `eventSchedule.connect.finish`

From `/connect/event-schedule/callback`: **Request:** `{ "code": "...", "state": "..." }`. The
state must be this person's, unused and under 10 minutes old (`410 connect_expired`: start again).
Reconnecting keeps the schedule and the lessons. Connecting a different ES account while booked
lessons are still to come on this one answers `409 lessons_to_come` (they're settled there first).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.connection.json` | `eventSchedule.connect.finish.200.json` |
| 410 | `connect_expired` | `error.json` | `eventSchedule.connect.finish.connect_expired.json` |
| 409 | `lessons_to_come` | `error.json` | `eventSchedule.connect.finish.lessons_to_come.json` |
| 422 | `validation_failed` | `error.json` | `eventSchedule.connect.finish.validation_failed.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `eventSchedule.connect.finish.event_schedule_unavailable.json` |

#### `eventSchedule.connect.key`

A selfhosted install: **Request:** `{ "baseUrl": "https://events.example.org", "apiKey": "..." }`.
The address must be `https` and public; the key is checked with ES at once (`422` on `apiKey` when
ES refuses it, on `baseUrl` when it can't be reached). `409 lessons_to_come` as for
`eventSchedule.connect.finish`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.connection.json` | `eventSchedule.connect.key.200.json` |
| 409 | `lessons_to_come` | `error.json` | `eventSchedule.connect.key.lessons_to_come.json` |
| 422 | `validation_failed` | `error.json` | `eventSchedule.connect.key.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `eventSchedule.connect.key.rate_limited.json` |

#### `eventSchedule.connection.update`

Pick the schedule for lessons (and the one a choir group is offered first). **Request:** `{
"schedule": "<subdomain>" }`, one of `eventSchedule.schedules.list`. Registers ES's webhook for it.
Moving to another schedule while booked lessons are still to come in this one answers `409
lessons_to_come`. Lessons linked to the old schedule's types come off sale.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.connection.json` | `eventSchedule.connection.update.200.json` |
| 404 | `not_found` | `error.json` | `eventSchedule.connection.update.not_found.json` |
| 409 | `lessons_to_come` | `error.json` | `eventSchedule.connection.update.lessons_to_come.json` |
| 409 | `reconnect_required` | `error.json` | `eventSchedule.connection.update.reconnect_required.json` |
| 422 | `validation_failed` | `error.json` | `eventSchedule.connection.update.validation_failed.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `eventSchedule.connection.update.event_schedule_unavailable.json` |

#### `eventSchedule.connection.delete`

Disconnect: the token is revoked in ES, the coach's lessons come off sale, and lessons already
booked stay booked (ES keeps them; the singer is told nothing changes). Choir groups linked to it
show no rehearsals. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `eventSchedule.connection.delete.not_found.json` |

#### `eventSchedule.schedules.list`

The person's schedules in ES. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.schedules.list.json` | `eventSchedule.schedules.list.200.json` |
| 404 | `not_found` | `error.json` | `eventSchedule.schedules.list.not_found.json` |
| 409 | `reconnect_required` | `error.json` | `eventSchedule.schedules.list.reconnect_required.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `eventSchedule.schedules.list.event_schedule_unavailable.json` |

#### `eventSchedule.types.list`

The picked schedule's appointment types, to link a lesson to. `linked` is the lesson offering
already using it, if any. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `eventSchedule.types.list.json` | `eventSchedule.types.list.200.json` |
| 404 | `not_found` | `error.json` | `eventSchedule.types.list.not_found.json` |
| 409 | `reconnect_required` | `error.json` | `eventSchedule.types.list.reconnect_required.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `eventSchedule.types.list.event_schedule_unavailable.json` |

#### `coaches.slots`

A lesson's free times, from ES (at most 30 seconds old). **Request:** `?from=YYYY-MM-DD` (today
by default, in `timezone`), `?days=` (1 to 31, 14 by default), `?timezone=` (the singer's IANA
zone; each `label` is in it). A lesson that isn't on sale (the coach's connection isn't working) is
`404`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coaches.slots.json` | `coaches.slots.200.json` |
| 404 | `not_found` | `error.json` | `coaches.slots.not_found.json` |
| 422 | `validation_failed` | `error.json` | `coaches.slots.validation_failed.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `coaches.slots.event_schedule_unavailable.json` |

#### `groups.eventSchedule.update`

Link the group to one of the director's ES schedules. **Request:** `{ "schedule": "<subdomain>" }`
(needs the director's own connection: `409 event_schedule_required`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.rehearsals.json` | `groups.eventSchedule.update.200.json` |
| 403 | `director_only` | `error.json` | `groups.eventSchedule.update.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.eventSchedule.update.not_found.json` |
| 409 | `event_schedule_required` | `error.json` | `groups.eventSchedule.update.event_schedule_required.json` |
| 409 | `reconnect_required` | `error.json` | `groups.eventSchedule.update.reconnect_required.json` |
| 422 | `validation_failed` | `error.json` | `groups.eventSchedule.update.validation_failed.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `groups.eventSchedule.update.event_schedule_unavailable.json` |

#### `groups.eventSchedule.delete`

Unlink it (the songs picked for each event go too). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 403 | `director_only` | `error.json` | `groups.eventSchedule.delete.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.eventSchedule.delete.not_found.json` |

#### `groups.eventSchedule.members`

The linked schedule's members in ES, for the director to send the group's link: names and
addresses, as ES lists them. **Request:** `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.eventSchedule.members.json` | `groups.eventSchedule.members.200.json` |
| 403 | `director_only` | `error.json` | `groups.eventSchedule.members.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.eventSchedule.members.not_found.json` |
| 409 | `reconnect_required` | `error.json` | `groups.eventSchedule.members.reconnect_required.json` |
| 424 | `event_schedule_unavailable` | `error.json` | `groups.eventSchedule.members.event_schedule_unavailable.json` |

#### `groups.rehearsals.list`

The group's next rehearsals and concerts (up to 10, in the next 60 days), each with the songs
picked for it and, for each song, the viewer's own assigned part and how far they've got (as in
`groups.show`). `schedule` is null when the group isn't linked; `available` is false when ES can't
be reached or the director's connection needs reconnecting (the events are then empty).
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.rehearsals.json` | `groups.rehearsals.list.200.json` |
| 404 | `not_found` | `error.json` | `groups.rehearsals.list.not_found.json` |

#### `groups.rehearsals.songs`

Which of the group's songs an event is for. **Request:** `{ "templateIds": ["danny-boy"] }` (each
one of the group's songs; `[]` clears it). The event must be one of the linked schedule's.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `groups.rehearsals.json` | `groups.rehearsals.songs.200.json` |
| 403 | `director_only` | `error.json` | `groups.rehearsals.songs.director_only.json` |
| 404 | `not_found` | `error.json` | `groups.rehearsals.songs.not_found.json` |
| 422 | `validation_failed` | `error.json` | `groups.rehearsals.songs.validation_failed.json` |

#### `admin.eventSchedule.show`

The integration's health: whether it's set up, the connections by status, those needing a
reconnect, and the lessons the reconcile couldn't match with ES (or that failed to book).
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.eventSchedule.json` | `admin.eventSchedule.show.200.json` |

#### Changes to M8's operations for lessons

- **`coach.offerings.create`:** a `lesson` is `{ "kind": "lesson", "appointmentTypeId": "<ES id>",
  "title": "A 45-minute lesson", "description": "...", "priceCents": 4000, "currency": "gbp" }`
  (no turnaround or limits); its `durationMinutes` comes from ES. Without a connection with a
  schedule: `409 event_schedule_required`. An offering's `kind` may be `lesson`, with
  `appointmentTypeId` and `durationMinutes` (null for the other kinds).
- **`coaches.show`:** lessons are listed only while they're on sale.
- **`engagements.create`:** a lesson is `{ "offeringId": "<id>", "slot": "2026-11-03T18:00:00Z",
  "timezone": "Europe/London", "notes": "...", "adult": true }`: the slot is checked with ES first
  (`409 slot_taken`: show the times again).
- **`engagements.cancel`:** a lesson is cancelled in ES too, by either side, while it's
  `requested` or `booked` and hasn't started: by the coach, or by the singer at least
  `cancellationHours` ahead, it's refunded in full; later, the singer's cancellation isn't refunded
  (the app says so first, from `lesson.refundUntil`).
- **`reviews.create`:** one take before a `requested` or `booked` lesson (`409 reviews_used` after).
- **An engagement's `lesson`** (null for the other kinds): `{ "startsAt", "endsAt", "timezone",
  "status": "booking" | "pending_approval" | "confirmed" | "cancelled" | "completed" | "failed",
  "joinUrl", "manageUrl", "icalUrl", "changedBy": "coach" | "singer" | null, "refundUntil" }`.
  `joinUrl` and the others are null until ES has them.

### Books, posts and signing in with Event Schedule (M10)

**Invoice Ninja keeps the books** (`docs/invoice-ninja-integration-plan.md`), connected directly
(nothing to do with Event Schedule's own connection). Without credentials locally, the server uses
its fake (`INVOICE_NINJA_FAKE=true`; a token starting `in-` is accepted).

- **A coach's books** (Coach settings, then Connect Invoice Ninja): a token, and for a selfhosted
  install its address (hosted is `invoicing.co`; a selfhosted one must be a public https address).
  It's checked at once ("Connected to Sarah's Studio"). From then on every sale is recorded as an
  invoice to the singer with its payment, Stackapella's commission as an expense, and each refund
  as a refund on that payment (with the commission on it given back as a negative expense). Connecting records the last 12 months once. Invoice emails are off
  unless the coach turns on "Also email singers an invoice from Invoice Ninja" (`sendEmail`).
  Recording happens in the background: the connection counts what's `recorded`, `waiting` and
  `notRecorded`, and the "not recorded" list (`invoiceNinja.records.list`) shows each with its
  reason in words (`problem`, English, a fallback) and as a stable code (`problemCode`:
  `unreachable`, `tls`, `bad_token`, `not_allowed`, `redirected`, `refused`, `paused` (waiting for
  a reconnect) or `waiting` (for its sale to be recorded first)), and **Retry**. What failed for a
  reason that passes is also tried again hourly. A token Invoice Ninja refuses sets `status: needs_reconnect`
  (connect again with a new token; what waited is tried again). At checkout, a coach with Invoice
  Ninja connected shows "Your coach keeps their records in Invoice Ninja" (`coaches.show`'s
  `keepsBooks`).
- **Stackapella's own books** (admin, in sudo): Plus payments (their tax on a line of its own) and
  refunds, and on the 1st each coach's month of commission (a month that came out negative is
  taken off the next).
- **Plus paid by invoice** (admin, in sudo): Hillel invoices an account for months of Plus; Invoice
  Ninja emails it; once payments that completed (not pending or failed) cover it, the account has
  Plus until `admin.account`'s `plusPaidUntil` (from now, or from when its paid time ends). It's
  checked when Invoice Ninja says a payment was made, and hourly.
- **Our own posts on X and Bluesky** (admin): new songs, the song of the month, and shared
  harmonies whose singers said yes (`harmonies.update` `mayFeature`). Each waits for Hillel's
  approval, then goes out 2 minutes later (undo until then), in production only. A post's `text` is
  what it would say now (null once it can't go: the song taken down, the harmony hidden or its yes
  withdrawn). X posts carry no link.
- **Sign in with Event Schedule:** a sign-in navigation like Google's (below); `auth.config` lists
  `event_schedule` while it's on offer, and `account.methods.eventSchedule` says it's linked. An
  account made this way is trusted as one made with Google or Apple (it records, shares and buys),
  but has no address (`account.email` is null) until the person adds one in Settings: until then no
  email reaches it (a data export is still listed in Settings), and there's no password to reset.

#### `invoiceNinja.connection.show`

The coach's books, or `data: null` when they haven't connected; `404` when the person isn't a
coach. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.connection.show.json` | `invoiceNinja.connection.show.200.json` |
| 404 | `not_found` | `error.json` | `invoiceNinja.connection.show.not_found.json` |

#### `invoiceNinja.connect`

Connect (or reconnect) Invoice Ninja. **Request:** `{ "token": "...", "url": null }` (`url` for a
selfhosted install). `422` on `token` when Invoice Ninja refuses it, on `url` when the address
can't be used or reached (each with its reason in words). Limited (`books-connect`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.connection.json` | `invoiceNinja.connect.200.json` |
| 404 | `not_found` | `error.json` | `invoiceNinja.connect.not_found.json` |
| 422 | `validation_failed` | `error.json` | `invoiceNinja.connect.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `invoiceNinja.connect.rate_limited.json` |

#### `invoiceNinja.connection.update`

**Request:** `{ "sendEmail": true }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.connection.json` | `invoiceNinja.connection.update.200.json` |
| 404 | `not_found` | `error.json` | `invoiceNinja.connection.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `invoiceNinja.connection.update.validation_failed.json` |

#### `invoiceNinja.connection.delete`

Disconnect: what's recorded stays in Invoice Ninja. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `invoiceNinja.connection.delete.not_found.json` |

#### `invoiceNinja.records.list`

What's been (or not been) recorded, newest first. **Request:** `?status=not_recorded` (the
default), `waiting`, `recorded` or `all`; `?cursor=`. Each row's `kind` is `sale`, `fee`
(Stackapella's commission), `refund`, and in admin also `plus`, `commission` or `plus_invoice`;
`problem` is the reason in words.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.records.list.json` | `invoiceNinja.records.list.200.json` |
| 404 | `not_found` | `error.json` | `invoiceNinja.records.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `invoiceNinja.records.list.validation_failed.json` |

#### `invoiceNinja.records.retry`

Try one again now. **Request:** none. `409 reconnect_required` while the connection needs a new
token; `409 record_state` when it isn't waiting or failed.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.record.json` | `invoiceNinja.records.retry.200.json` |
| 404 | `not_found` | `error.json` | `invoiceNinja.records.retry.not_found.json` |
| 409 | `reconnect_required` | `error.json` | `invoiceNinja.records.retry.reconnect_required.json` |
| 409 | `record_state` | `error.json` | `invoiceNinja.records.retry.record_state.json` |

#### `admin.books.show`

Stackapella's own books, or `null`; `paymentsWebhook` says Invoice Ninja will tell us of
payments (paying by invoice needs it). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.books.json` | `admin.books.show.200.json` |

#### `admin.books.connect`

As `invoiceNinja.connect`, for Stackapella's own company. **Request:** `{ "token", "url" }`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.books.connection.json` | `admin.books.connect.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.books.connect.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `admin.books.connect.rate_limited.json` |

#### `admin.books.delete`

**Request:** none. `409 plus_invoices_open` while a Plus invoice is still out (paid or cancelled
first, or it would never be seen paid).

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `admin.books.delete.not_found.json` |
| 409 | `plus_invoices_open` | `error.json` | `admin.books.delete.plus_invoices_open.json` |

#### `admin.books.records.list`

As `invoiceNinja.records.list`, for Stackapella's own books.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.records.list.json` | `admin.books.records.list.200.json` |
| 404 | `not_found` | `error.json` | `admin.books.records.list.not_found.json` |

#### `admin.books.records.retry`

As `invoiceNinja.records.retry`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `invoiceNinja.record.json` | `admin.books.records.retry.200.json` |
| 404 | `not_found` | `error.json` | `admin.books.records.retry.not_found.json` |
| 409 | `record_state` | `error.json` | `admin.books.records.retry.record_state.json` |

#### `admin.books.plusInvoices.create`

Invoice an account for Plus. **Request:** `{ "personId": "<account id>", "amountCents": 6000,
"currency": "gbp", "months": 12 }`. The row goes `waiting`, then `sent` (Invoice Ninja emailed
it), then `paid`. `409 books_not_connected` without Stackapella's books (and their payments
webhook); `409 email_required` for an account without an address.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `invoiceNinja.record.json` | `admin.books.plusInvoices.create.201.json` |
| 404 | `not_found` | `error.json` | `admin.books.plusInvoices.create.not_found.json` |
| 409 | `books_not_connected` | `error.json` | `admin.books.plusInvoices.create.books_not_connected.json` |
| 409 | `email_required` | `error.json` | `admin.books.plusInvoices.create.email_required.json` |
| 422 | `validation_failed` | `error.json` | `admin.books.plusInvoices.create.validation_failed.json` |

#### `admin.social.list`

Our posts, newest first, and the networks with credentials. **Request:** `?status=awaiting` (the
default), `queued`, `posting`, `posted`, `failed`, `cancelled` or `all`; `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.social.list.json` | `admin.social.list.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.social.list.validation_failed.json` |

#### `admin.social.approve`

Send it, 2 minutes from now (`scheduledFor`). `409 post_gone` when there's nothing to say any more
(it's cancelled). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.social.post.json` | `admin.social.approve.200.json` |
| 404 | `not_found` | `error.json` | `admin.social.approve.not_found.json` |
| 409 | `post_state` | `error.json` | `admin.social.approve.post_state.json` |
| 409 | `post_gone` | `error.json` | `admin.social.approve.post_gone.json` |

#### `admin.social.cancel`

Turn it down, or undo it within its 2 minutes. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.social.post.json` | `admin.social.cancel.200.json` |
| 404 | `not_found` | `error.json` | `admin.social.cancel.not_found.json` |
| 409 | `post_state` | `error.json` | `admin.social.cancel.post_state.json` |

#### `admin.social.retry`

A failed post, sent again now. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.social.post.json` | `admin.social.retry.200.json` |
| 404 | `not_found` | `error.json` | `admin.social.retry.not_found.json` |
| 409 | `post_state` | `error.json` | `admin.social.retry.post_state.json` |

#### `admin.social.feature`

Propose posts about a shared harmony (one per network), to approve. `409 feature_not_allowed`
unless it's live and its singer said yes. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `admin.social.posts.json` | `admin.social.feature.201.json` |
| 404 | `not_found` | `error.json` | `admin.social.feature.not_found.json` |
| 409 | `feature_not_allowed` | `error.json` | `admin.social.feature.feature_not_allowed.json` |

#### Changes to earlier operations (M10)

- **`harmonies.update`:** `mayFeature` (boolean): the owner's yes to our accounts featuring it,
  credited by their handle; `false` withdraws it, and any post not yet sent is cancelled. A
  harmony's `mayFeature` says which.
- **`coaches.show`:** `keepsBooks` (true when the coach has Invoice Ninja connected).
- **`account.show`** (and every account): `methods.eventSchedule`. **`admin.account`**:
  `plusPaidUntil` (Plus paid by invoice, or null).
- **`account.methods.delete`:** also `event-schedule`.
- **`auth.config`:** `methods` may include `event_schedule`.
- **Sign-in navigations:** `POST /auth/event-schedule` (as `/auth/google`, with `intended`,
  `link=1` and `invite`), back to `?signedIn=event_schedule` or `?authError=` (also `unavailable`
  while it isn't set up). Matched on the Event Schedule account only, never by email; a new
  account's address isn't taken from Event Schedule: it has none until the person adds one in
  Settings.

### Support, notices and refunds (L)

- **Contact us and Send diagnostics** (`support.create`, signed in or not): a category, the message,
  and, only when the person ticks "Include details about my device", the app's `context`
  (`appVersion`, `browser`, `device`, `page`, `timingCheck` (the last result, as the app keeps it),
  and for diagnostics `audioErrors`, the last few). Signed out, an `email` is needed for the
  answer. Answers come by email from Hillel. `website` is the honeypot.
- **Statements of reasons** (`notices.*`): when a shared harmony is hidden or taken down (by Hillel,
  by a copyright notice, or automatically after reports), when an account's sharing is limited or
  it's suspended, or when coaching is paused or suspended, the person gets a notice: `what` was
  done and `why` (in words, from `kind` and `rule`), Hillel's `note`, and whether it was
  `automatic`. They can **Ask for a review** once, within 6 months (`canAppeal`), and see the
  decision (`appeal.decision`: `kept` or `reversed`, which undoes it). A suspended person, who
  can't sign in, gets the notice by email as a private link: the PWA's **`/notice?token=<token>`**
  page (the app lifts the token out of the address, as for `/verify`), which calls `notices.show`
  and `notices.appeal` with the token (a signed-in person's own notices use their `id` instead).
  `kind` and `rule` are fixed lists, so the app words them itself; `what` and `why` are the
  server's English, a fallback.
- **In admin:** the support inbox (`admin.support.*`, with saved replies to start from), the
  appeals waiting (`admin.appeals.*`), and refunds of Plus (`admin.people.refund`, in sudo).

#### `support.create`

**Request:** `{ "category": "recording", "message": "...", "email": "ana@example.com", "context":
{ "appVersion": "...", "browser": "...", "device": "...", "page": "/sing", "timingCheck": {...},
"audioErrors": ["..."] }, "website": "" }`. `category` is `account`, `payments`, `recording`,
`coaching`, `other`, `diagnostics`, `bug` (something's broken) or `idea`. Limited (`support`); in shield mode it needs Turnstile's
`turnstileToken`, as sign-up does (`403 turnstile_required` or `turnstile_failed`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `support.received.json` | `support.create.201.json` |
| 403 | `turnstile_required` | `error.json` | `support.create.turnstile_required.json` |
| 403 | `turnstile_failed` | `error.json` | `support.create.turnstile_failed.json` |
| 422 | `validation_failed` | `error.json` | `support.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `support.create.rate_limited.json` |

#### `notices.list`

The signed-in person's notices, newest first. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `notices.list.json` | `notices.list.200.json` |

#### `notices.show`

One notice: `{notice}` is its private link's token, or its `id` for its own signed-in person.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `notice.show.json` | `notices.show.200.json` |
| 404 | `not_found` | `error.json` | `notices.show.not_found.json` |

#### `notices.appeal`

Ask for a review. **Request:** `{ "text": "..." }`. `409 appeal_closed` once asked, or after 6
months.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `notice.show.json` | `notices.appeal.200.json` |
| 404 | `not_found` | `error.json` | `notices.appeal.not_found.json` |
| 409 | `appeal_closed` | `error.json` | `notices.appeal.appeal_closed.json` |
| 422 | `validation_failed` | `error.json` | `notices.appeal.validation_failed.json` |

#### `admin.support.list`

**Request:** `?status=open` (the default), `answered`, `closed` or `all`; `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.support.list.json` | `admin.support.list.200.json` |

#### `admin.support.show`

The message in full, with its `context`, the answers sent, and `savedReplies` to start from. Its
`from.account` (when it came from an account) adds their `plan`, as `admin.people.show` has it.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.support.message.json` | `admin.support.show.200.json` |
| 404 | `not_found` | `error.json` | `admin.support.show.not_found.json` |

#### `admin.support.reply`

Answer it: emailed to the person and kept. **Request:** `{ "body": "...", "close": false }`
(paragraphs split by a blank line).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.support.message.json` | `admin.support.reply.200.json` |
| 404 | `not_found` | `error.json` | `admin.support.reply.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.support.reply.validation_failed.json` |

#### `admin.support.update`

**Request:** `{ "status": "closed" }` (`open`, `answered` or `closed`).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.support.message.json` | `admin.support.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.support.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.support.update.validation_failed.json` |

#### `admin.appeals.list`

Appeals waiting (the default, oldest first) or `?status=decided`; `?cursor=`. Each is a notice
with its `person`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.appeals.list.json` | `admin.appeals.list.200.json` |

#### `admin.appeals.decide`

**Request:** `{ "decision": "reversed", "note": "..." }`. Reversed undoes the restriction where it
can: the harmony restored, the account unsuspended or its trust back to earned, the coach approved
again. `409 appeal_decided` the second time; `409 cannot_reverse` when it can't be undone (a
removed harmony, or a later restriction on the same thing that still stands: decide that one).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.notice.json` | `admin.appeals.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.appeals.decide.not_found.json` |
| 409 | `appeal_decided` | `error.json` | `admin.appeals.decide.appeal_decided.json` |
| 409 | `cannot_reverse` | `error.json` | `admin.appeals.decide.cannot_reverse.json` |
| 422 | `validation_failed` | `error.json` | `admin.appeals.decide.validation_failed.json` |

#### `admin.people.payment`

The account's latest Plus payment through Stripe, what `admin.people.refund` refunds from (what's
left is `amountCents` less `refundedCents`), or `payment: null` when there's none. It asks Stripe,
so the People screen reads it only when the refund panel opens. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.people.payment.json` | `admin.people.payment.200.json` |
| 404 | `not_found` | `error.json` | `admin.people.payment.not_found.json` |

#### `admin.people.refund`

Refund the account's latest Plus payment, in full or in part. Any refund ends their Plus at once
(billing.show `status: "ended"`, `endedReason: "refunded"`), so the panel says "Refunding ends their
Plus now" before it's confirmed. **Request:** `{ "amountCents": 499, "note": "..." }` (at most
what's left of it: `422` says how much). `409 no_payment` when there's no paid Plus invoice.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.people.refund.json` | `admin.people.refund.200.json` |
| 409 | `no_payment` | `error.json` | `admin.people.refund.no_payment.json` |
| 422 | `validation_failed` | `error.json` | `admin.people.refund.validation_failed.json` |


### The gallery (N1)

Public harmonies, Explore, profiles, likes, comments, follows and blocks (`docs/backend-plan.md`
D6), behind the `gallery` flag: closed, its reads answer `404` and its writes `409
gallery_closed` (the bell answers either way). Opt-ins collected before it opens count, but each
waits for review (`galleryHeld`) unless its owner is level 3, and is listed only once its owner has
said `rightsConfirmed: true` (the app asks when the gallery opens).

- **18 and over:** a public profile, a harmony in the gallery, commenting and following
  (`403 adult_required`: the app asks the 18 or over question, as coaching does). Under-18s keep
  share links only (the UK Children's Code).
- **A public profile:** `account.update` `publicProfile: true` (a handle and 18 or over). Its
  page is `/@<handle>` (`public.profile`: `$profile`, `$harmonies`, `$meta`); Explore's is
  `/explore` (`public.explore`: `$tab`, `$harmonies`, `$meta`).
- **In the gallery:** a harmony that's unlisted, `inGallery`, live, not held or moderated, whose
  owner's profile is public. Putting it there while the gallery is open needs trust level 2
  (`403 trust_required`), a public profile (`409 profile_required`), `rightsConfirmed: true` (`422`
  otherwise; "I have the right to share this song, and its parts may be used as guide voices for
  other singers, credited to my profile"), and each friend's yes (`409 singers_must_agree`). A
  level-2 singer's first two wait for review (`galleryHeld`); level 3 never waits.
- **Guide voices from public songs** (Hillel's call): a part of a public song (featured, or in
  the gallery; never an unlisted link alone) may be chosen by Hillel as a voice of that template's
  part, always credited by profile name. It goes when the song stops being public, is deleted, or
  its account is.
- **account.show** has `publicProfile`, `hideProfile` and `gallery: { canPost, canComment, postsHeld }` (never
  a trust level). **harmonies.show** has `galleryHeld`, `rightsConfirmed`, `commentsOff` and
  `singers` (each friend's `consent`).

#### `explore.list`

The gallery's harmonies, newest first: `?tab=new` (the default), `featured` (those Hillel featured, newest featured first) or `following` (from profiles the caller follows; empty signed out); `?song=<slug>` and `?q=` (in the title or the song's) narrow it. Never anyone the caller is blocked with. Cursor-paginated (24). `404` while the gallery is closed. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `explore.list.json` | `explore.list.200.json` |
| 404 | `not_found` | `error.json` | `explore.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `explore.list.validation_failed.json` |

#### `profiles.show`

A public profile. `404` unless it's public (18 or over, a handle, `publicProfile` on, `hideProfile` off, not suspended), and to anyone it's blocked with. `canFollow` says whether the caller may follow it now. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `profiles.show.json` | `profiles.show.200.json` |
| 404 | `not_found` | `error.json` | `profiles.show.not_found.json` |

#### `profiles.harmonies`

That profile's harmonies in the gallery, newest first, cursor-paginated. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `profiles.harmonies.json` | `profiles.harmonies.200.json` |
| 404 | `not_found` | `error.json` | `profiles.harmonies.not_found.json` |

#### `likes.create`

Like a harmony in the gallery; twice is once. A verified account (`403 email_unverified`). Limited (`likes`, 200 an hour). The owner's bell groups likes per harmony a day. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `likes.json` | `likes.create.200.json` |
| 403 | `email_unverified` | `error.json` | `likes.create.email_unverified.json` |
| 404 | `not_found` | `error.json` | `likes.create.not_found.json` |
| 409 | `gallery_closed` | `error.json` | `likes.create.gallery_closed.json` |
| 429 | `rate_limited` | `error.json` | `likes.create.rate_limited.json` |

#### `likes.delete`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `likes.json` | `likes.delete.200.json` |
| 404 | `not_found` | `error.json` | `likes.delete.not_found.json` |

#### `comments.list`

Newest first, cursor-paginated (30): shown comments, the caller's own held ones (`held: true`), never anyone they're blocked with. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `comments.list.json` | `comments.list.200.json` |
| 404 | `not_found` | `error.json` | `comments.list.not_found.json` |

#### `comments.create`

**Request:** `{ "body": "..." }`: 1 to 500 characters, plain text; a link is a `422` on `body`. 18 or over (`403 adult_required`), trust level 2 (`403 trust_required`), not blocked by the owner (`403 blocked`), comments on (`409 comments_off`). One the word lists match is held (`held: true`), seen only by its author until Hillel looks. Limited (`comments`: 10 an hour at level 2, 30 at level 3). `canComment` on a gallery harmony says beforehand whether it would work.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `comments.create.json` | `comments.create.201.json` |
| 403 | `adult_required` | `error.json` | `comments.create.adult_required.json` |
| 403 | `blocked` | `error.json` | `comments.create.blocked.json` |
| 403 | `trust_required` | `error.json` | `comments.create.trust_required.json` |
| 404 | `not_found` | `error.json` | `comments.create.not_found.json` |
| 409 | `comments_off` | `error.json` | `comments.create.comments_off.json` |
| 409 | `gallery_closed` | `error.json` | `comments.create.gallery_closed.json` |
| 422 | `validation_failed` | `error.json` | `comments.create.validation_failed.json` |
| 423 | `paused` | `error.json` | `comments.create.paused.json` |
| 429 | `rate_limited` | `error.json` | `comments.create.rate_limited.json` |

#### `comments.delete`

By its author or the harmony's owner; anyone else finds nothing. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `comments.delete.not_found.json` |

#### `follows.create`

Follow a public profile; twice is once. 18 or over, not blocked. Limited (`follows`, 50 an hour). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `follows.json` | `follows.create.200.json` |
| 403 | `adult_required` | `error.json` | `follows.create.adult_required.json` |
| 403 | `blocked` | `error.json` | `follows.create.blocked.json` |
| 404 | `not_found` | `error.json` | `follows.create.not_found.json` |
| 409 | `gallery_closed` | `error.json` | `follows.create.gallery_closed.json` |
| 423 | `paused` | `error.json` | `follows.create.paused.json` |
| 429 | `rate_limited` | `error.json` | `follows.create.rate_limited.json` |

#### `follows.delete`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `follows.json` | `follows.delete.200.json` |
| 404 | `not_found` | `error.json` | `follows.delete.not_found.json` |

#### `blocks.create`

Block someone by their handle. Neither then sees the other's profile, harmonies or comments, follows between them end, and they can't follow or comment on each other. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `blocks.create.not_found.json` |
| 422 | `validation_failed` | `error.json` | `blocks.create.validation_failed.json` |

#### `blocks.delete`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `blocks.delete.not_found.json` |

#### `blocks.list`

Everyone the caller blocked. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `blocks.list.json` | `blocks.list.200.json` |

#### `harmonies.consent.ask`

Ask each friend who sang on the owner's harmony (its `singers`) whether it may go in the gallery and be featured, once each, in their bell. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.show.json` | `harmonies.consent.ask.200.json` |
| 404 | `not_found` | `error.json` | `harmonies.consent.ask.not_found.json` |

#### `consentRequests.list`

What the caller has been asked, newest first. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `consentRequests.list.json` | `consentRequests.list.200.json` |

#### `consentRequests.decide`

**Request:** `{ "agree": true }`. A no (now or later) takes the harmony out of the gallery and stops featuring; a no can become a yes. A yes puts their voice and name in public, so it needs 18 or over (`403 adult_required`; a no is always open).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `consentRequests.decide.json` | `consentRequests.decide.200.json` |
| 403 | `adult_required` | `error.json` | `consentRequests.decide.adult_required.json` |
| 404 | `not_found` | `error.json` | `consentRequests.decide.not_found.json` |
| 422 | `validation_failed` | `error.json` | `consentRequests.decide.validation_failed.json` |

#### `notifications.list`

The bell, newest first, cursor-paginated (30): `like` (grouped per harmony a day; `others` is how many more), `comment`, `follow`, `consent_request`, `consent_answer`, `featured`, and `board_beaten` (a friend, `actor`, beat your best on a part: `board` says the song, part and their score; once a day per friend and part, never by email). `meta.unread` counts the unread. It answers while the gallery is closed too. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `notifications.list.json` | `notifications.list.200.json` |

#### `notifications.read`

**Request:** `{ "all": true }`, or `{ "upTo": "<id>" }` (that item and older).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `notifications.read.json` | `notifications.read.200.json` |
| 422 | `validation_failed` | `error.json` | `notifications.read.validation_failed.json` |

#### `admin.gallery.held`

The harmonies and comments waiting for review, oldest first. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.gallery.held.json` | `admin.gallery.held.200.json` |

#### `admin.gallery.decide`

`{kind}` is `harmony` (its `id`) or `comment`. **Request:** `{ "decision": "approve" | "hide", "note": "..." }` (a note to hide: it's the statement of reasons). Hiding a harmony hides its page (`harmony_hidden`); hiding a comment gives its author a `comment_hidden` notice with an appeal. Answers the queue.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.gallery.held.json` | `admin.gallery.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.gallery.decide.not_found.json` |
| 409 | `not_held` | `error.json` | `admin.gallery.decide.not_held.json` |
| 422 | `validation_failed` | `error.json` | `admin.gallery.decide.validation_failed.json` |

#### `admin.gallery.guides.create`

A part of a public song (featured, or in the gallery: never an unlisted link alone; one of our
templates; every friend has said yes) becomes a voice of that template part, as a beta singer's
does: `guide.recording` with `credit` the singer's profile name. **Request:** `{ "partId": "<the project part's uuid>", "makeDefault": false }`.
Queued (`202`), and checked again when it runs. It goes when the song stops being public
(featuring and the gallery both off, a friend's yes withdrawn, hidden, deleted, or the account
gone). `409 not_public` (its owner must be 18 or over too), `not_official` (a template of someone's
own), `no_take` (the part has no ready take, isn't the song's part, or its take wasn't in the mix
that was shared: a take recorded since never becomes a guide). `403 adult_required`: whoever sang
it isn't confirmed 18 or over.

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `admin.gallery.guides.create.json` | `admin.gallery.guides.create.202.json` |
| 403 | `adult_required` | `error.json` | `admin.gallery.guides.create.adult_required.json` |
| 404 | `not_found` | `error.json` | `admin.gallery.guides.create.not_found.json` |
| 409 | `no_take` | `error.json` | `admin.gallery.guides.create.no_take.json` |
| 409 | `not_official` | `error.json` | `admin.gallery.guides.create.not_official.json` |
| 409 | `not_public` | `error.json` | `admin.gallery.guides.create.not_public.json` |
| 422 | `validation_failed` | `error.json` | `admin.gallery.guides.create.validation_failed.json` |

#### `admin.harmonies.feature`

**Request:** `{ "featured": true }`. Featured harmonies lead the Featured tab; the owner's bell says so.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.harmonies.feature.json` | `admin.harmonies.feature.200.json` |
| 404 | `not_found` | `error.json` | `admin.harmonies.feature.not_found.json` |


### Leagues (N2)

Leagues and friends' leaderboards (`docs/vocal-coaching-plan.md`, section 8), behind the `leagues`
flag: closed, the reads answer `404` and joining `409 leagues_closed`. Ranked by XP (effort, never
accuracy), from the course progress the app already syncs: `progress.xp.days`, each day capped at
300 (the app's own cap); a day over it, or in the future, is clamped and flagged for Hillel.
Weekly leagues of about 30 in a tier (`tier` 0 to `tiers` - 1); each Monday at 00:10 UTC the top
5 with any XP move up a tier (`movingUp` now, `lastWeek.movedUp` after), and nobody moves down.
Shown: handle, name, avatar, voice level (the app's `levelFor` of the capped total) and the
week's XP; never a score or a recording; never anyone blocked either way.

#### `leagues.current`

This week's league: `joined`, the week (its Monday) and when it ends, the person's `tier`,
`tiers`, `promote` (5), `lastWeek` (`{ tier, rank, movedUp }`, or null), and the `members`
ranked by XP. Not joined: `joined: false` and no members. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `leagues.current.json` | `leagues.current.200.json` |
| 404 | `not_found` | `error.json` | `leagues.current.not_found.json` |

#### `leagues.join`

Opt in ("Join a league"): 18 or over (`403 adult_required`; under-18s keep the Friends tab), a
handle (`422` on `handle`) and a verified account. Placed in a league of their tier this week
with room. Answers `leagues.current`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `leagues.current.json` | `leagues.join.200.json` |
| 403 | `adult_required` | `error.json` | `leagues.join.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `leagues.join.email_unverified.json` |
| 409 | `leagues_closed` | `error.json` | `leagues.join.leagues_closed.json` |
| 422 | `validation_failed` | `error.json` | `leagues.join.validation_failed.json` |

#### `leagues.leave`

Out of this week's league and the next; joining again keeps the tier. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

### Part leaderboards

Each song's parts have boards (docs/decisions.md, "part leaderboards"), free for everyone: a
person's best score per song version, part and scoring version. **Global:** our songs only, adults
who chose to appear (`boards.global.update`), the profile's public name. **Friends:** any song; the
people connected by an accepted challenge (two adults only), a song they sing on together, or a
shared group; someone under 18 by first name. Blocks hide each other; a take in the bin takes its
score off, and deleting it deletes the score. A new song version, or a new scoring version, starts
fresh boards.

#### `boards.scores.submit`

**Request:** `templateId` (the song's id), `templateVersion` (the current one), `partKey`,
`scoringVersion` and `pitchDetectorVersion` (the scorer's), `score` (0 to 100), and how it was sung:
`mode` (`own` or `record` count), `keyOffset` (0 counts), `speed` (1 counts), `guideMuted` (true
counts), `timingChecked` (this device's timing check passed: true counts) and `output` (`speaker`,
`wired`, `bluetooth` (doesn't count) or `unknown`); optional `takeId` (theirs, of that song and part:
pinned while the score stands), `appVersion`, `alignmentMs` (the alignment applied) and `notes` (the
per-note results, each `{ "lit": bool, "cents": int|null, "timingMs": int|null }`), all kept for a
later rescore. Keeps only the best; 30 a minute. A score that doesn't qualify is answered 200 with
`counted: false` and `why`, so the app can say what to change. `before` is their best on this board
before this take, whichever device set it (game mode's "up from 82"): null on a first score or one
that didn't count.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `boards.scores.submit.json` | `boards.scores.submit.200.json` |
| 404 | `not_found` | `error.json` | `boards.scores.submit.not_found.json` |
| 409 | `old_version` | `error.json` | `boards.scores.submit.old_version.json` |
| 409 | `scoring_outdated` | `error.json` | `boards.scores.submit.scoring_outdated.json` |

#### `boards.show`

**Request:** `?scope=global` or `friends`, and optionally `?version=` (an earlier version of the
song: its board, read only; `404` when it has no entries on this part). The top 10 and their own
best and place; `current` says whether it's the song's current arrangement, and `olderVersions`
lists the earlier versions with entries on this part, newest first.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `boards.show.json` | `boards.show.200.json` |
| 404 | `not_found` | `error.json` | `boards.show.not_found.json` |

#### `boards.global.update`

**Request:** `{ "on": true }` (18 and over) or `false`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `boards.global.update.json` | `boards.global.update.200.json` |
| 403 | `adult_required` | `error.json` | `boards.global.update.adult_required.json` |

#### `boards.challenges.create`

**Request:** `templateId`, `partKey`. The token is shown once; 20 an hour.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 | | `boards.challenges.create.json` | `boards.challenges.create.201.json` |

#### `boards.challenges.show`

What a challenge link shows before it's accepted: the song, the part, the challenger's best and
name (null for someone signed out or under 18). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `boards.challenges.show.json` | `boards.challenges.show.200.json` |
| 404 | `not_found` | `error.json` | `boards.challenges.show.not_found.json` |

#### `boards.challenges.accept`

The song and part to sing, and, between two adults, a connection both ways. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `boards.challenges.accept.json` | `boards.challenges.accept.200.json` |
| 404 | `not_found` | `error.json` | `boards.challenges.accept.not_found.json` |

#### `admin.boards.flags.list`

Scores held for a look, newest first (up to 100): a perfect 100, a jump of 25 or more over the
singer's own best on the part, or 15 or more above the global board's best once it has 5 entries.
For 48 hours from `recordedAt` (`holdEndsAt`) the global board leaves it out for everyone but its
singer, unless it's cleared sooner; friends' boards show it. An entry 2 different people reported
(`reason: "reported"`, `reports` open) is held until an admin looks, however long. Each has a
short-lived `takeUrl` to the take behind it. Only those not yet looked at or taken off. Clearing one
(`admin.boards.scores.review`) dismisses its reports; taking it off
(`admin.boards.scores.delete`) upholds them. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.boards.flags.list.json` | `admin.boards.flags.list.200.json` |

#### `admin.boards.scores.review`

Looked at and fine: the score shows on the global board at once (audited). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 404 | `not_found` | `error.json` | `admin.boards.scores.review.not_found.json` |

#### `admin.boards.scores.delete`

Takes one score off the boards (audited). **Request:** `reason`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |

#### `admin.boards.reset`

Removes all of a person's scores (audited). **Request:** `reason`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.boards.reset.json` | `admin.boards.reset.200.json` |

#### `friends.leaderboard`

The person and the people they follow who have joined leagues, ranked by this week's XP. Everyone
may see it, under-18s included. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `friends.leaderboard.json` | `friends.leaderboard.200.json` |
| 404 | `not_found` | `error.json` | `friends.leaderboard.not_found.json` |

### The template library (N3)

Templates people make, shared with everyone (`docs/templates-plan.md`, "The public library"),
behind the `template_library` flag: closed, `library.list` answers `404` and publishing `409
library_closed`. `libraryOptIn` stays what it was: a wish, never a publication (the app asks the
opted-in to publish when it opens).

- **Publishing** (`templates.publish`) needs 18 or over (`403 adult_required`), trust level 2
  (`403 trust_required`), a public profile (`409 profile_required`) and the **licensing
  declaration**: the song is the creator's own (`original`) or `public_domain` (with an optional
  `source`), and the arrangement and recordings are their own (`ownWork: true`). It's kept with
  the template (a version never changes), and each later version of a public template is saved
  with it again, kept with that version (`templates.save`'s `declaration`, `422` without it). Five publications a day at most (`429`).
- **Review:** a creator's first two public templates wait for review (`library.status: "held"`)
  unless they're level 3. Reports from enough people hold one too (the same rule as a shared
  page). Hillel approves, or hides it (`hidden`: out of the library and closed to everyone but its
  owner; projects already made from it keep their copy; the owner gets a `template_hidden`
  notice, which they can appeal). Banning is suspending the account, as now.
- **Public templates are free and unlimited:** they never count toward the free account's one
  private or unlisted template, and never go read-only after a downgrade.
- **Who may open it:** a listed public template, anyone (`templates.show` `grant: "public"`); held,
  as if unlisted (anyone with the link); hidden, its owner only.
- **Attribution:** a public template's `templates.show` has `library` (below), whose `creator`
  the app puts in exports' metadata and names, and on a project made from it.
- **Remix:** `templates.save`'s first save may name a `parentId`: a public template or an official
  song the person may open (`404` otherwise). The copy is the person's own, private, and links
  back (`parent`).
- **Reports:** `reports.create` takes `templateId` (copyright, offensive, other, as for a page).

A template's `library` (in `library.list` items, `templates.show` for a public one, and
`templates.mine` items): `{ "status": "listed" | "held" | "hidden", "creator": { "handle",
"name", "avatarUrl" }, "uses": 12, "rating": { "average": 4.5, "count": 8, "mine": 5 | null },
"featured": false, "publishedAt": "...", "parent": { "id", "title", "creator" } | null }`.
`uses` counts the different people who made a project from it (not its creator), recounted
nightly. `rating.average` is null until it has 3 ratings.

#### `library.list`

Public templates, listed only (never held or hidden), never anyone the caller is blocked with.
**Request:** `?q=` (title, description, credits), `difficulty` (`beginner`, `intermediate`,
`advanced`), `parts` (2, 3 or 4: it has a version for that many singers), `genre`, `key` (as the
template says it, like `G` or `Em`), `tempo` (`slow` under 80, `medium` 80 to 120, `fast` over
120), `low` and `high` (MIDI notes: one of its parts sits inside them, for "fits my voice"),
`creator` (a handle: their public templates, for the profile page), `sort` (`popular`, the
default, by `uses`; `newest`; `top`, by rating with few ratings counting less), `tab=featured`
(those Hillel featured, newest featured first; the app shows the official songs on the same
shelf), `cursor`. 24 a page. `404` while closed.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `library.list.json` | `library.list.200.json` |
| 404 | `not_found` | `error.json` | `library.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `library.list.validation_failed.json` |

#### `templates.publish`

Into the library. **Request:** `{ "song": "original" | "public_domain", "source": "Traditional,
from the Sacred Harp (1844)", "ownWork": true }` (`source` optional, up to 300). Publishing one
already public answers it as it is (its declaration updated).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.mine.item.json` | `templates.publish.200.json` |
| 403 | `adult_required` | `error.json` | `templates.publish.adult_required.json` |
| 403 | `trust_required` | `error.json` | `templates.publish.trust_required.json` |
| 404 | `not_found` | `error.json` | `templates.publish.not_found.json` |
| 409 | `library_closed` | `error.json` | `templates.publish.library_closed.json` |
| 409 | `profile_required` | `error.json` | `templates.publish.profile_required.json` |
| 422 | `validation_failed` | `error.json` | `templates.publish.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `templates.publish.rate_limited.json` |

#### `templates.unpublish`

Out of the library: it becomes `unlisted`, so its link keeps working (`templates.update` makes it
private). Its ratings and uses are kept for if it comes back. A hidden one stays hidden.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.mine.item.json` | `templates.unpublish.200.json` |
| 404 | `not_found` | `error.json` | `templates.unpublish.not_found.json` |

#### `templates.rate`

**Request:** `{ "stars": 1 to 5 }`. Only from someone who has made a project from it, signed in
with a verified email, and never its creator (`403 rating_not_allowed`). Rating again changes it.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.rating.json` | `templates.rate.200.json` |
| 403 | `rating_not_allowed` | `error.json` | `templates.rate.rating_not_allowed.json` |
| 404 | `not_found` | `error.json` | `templates.rate.not_found.json` |
| 422 | `validation_failed` | `error.json` | `templates.rate.validation_failed.json` |

#### `templates.unrate`

Takes the caller's rating back (none to take answers the same). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.rating.json` | `templates.unrate.200.json` |
| 404 | `not_found` | `error.json` | `templates.unrate.not_found.json` |

#### `admin.library.held`

The review queue: templates waiting for review (a new creator's, or reported), oldest first, each
with its open reports' reasons and count, its declaration and its creator. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.library.held.json` | `admin.library.held.200.json` |

#### `admin.library.decide`

**Request:** `{ "decision": "approve" | "hide" | "show", "note": "..." }` (a note is needed to
hide: it's what the owner's notice says). `approve` lists a held one and answers its reports;
`hide` closes it (upholding its reports); `show` reverses a hide (an appeal won). Audited. Answers
the queue as `admin.library.held` does.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.library.held.json` | `admin.library.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.library.decide.not_found.json` |
| 409 | `not_held` | `error.json` | `admin.library.decide.not_held.json` |
| 422 | `validation_failed` | `error.json` | `admin.library.decide.validation_failed.json` |

#### `admin.library.feature`

On or off the Featured shelf. **Request:** `{ "featured": true }`. Only a listed one can be
featured (`409 not_listed`). Audited.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `library.item.json` | `admin.library.feature.200.json` |
| 404 | `not_found` | `error.json` | `admin.library.feature.not_found.json` |
| 409 | `not_listed` | `error.json` | `admin.library.feature.not_listed.json` |

### Ideas: your tune, sung by real voices

`docs/your-tune-plan.md`, step 3 (Hillel's decision): a writer shares a tune as an **idea**, with
every part open (the melody too) and the suggested parts as its guides, on the **Ideas** shelf;
anyone may sing any part and add their voice to it; the writer hears about it in the bell and by
email. Free. Behind the `ideas` flag (it can open before the rest of the gallery): closed, the
reads answer `404` and the writes `409 ideas_closed`.

- **The tune is a template** the writer made (`templates.save`, private or unlisted is fine).
  While its idea is listed, anyone may open it (`templates.show` `grant: "idea"`). One idea per
  template: sharing it again answers the one there is.
- **The writer:** 18 or over (`403 adult_required`), a verified email (`403 email_unverified`),
  and the rights confirmation (`rightsConfirmed: true`, `422` otherwise: "This tune is mine, or
  traditional, and anyone may sing it and share what they make, credited to me"). Credited by
  name, and by handle when their profile is public. A writer's first two ideas wait for review
  (`status: "held"`) unless they're level 3.
- **A singer:** 18 or over, a verified email, never someone the writer is blocked with, and
  their own rights confirmation ("my part joins this tune, credited to me, under the rules for
  public songs' voices"). A voice is uploaded as a take is (a 16-bit mono WAV by presigned `PUT`,
  then `complete`), made into AAC, and heard by temporary URL (2 hours). Lined up as a take is:
  `offsetFrames` from the template's beat zero. Each singer keeps one voice per part: a new one
  replaces theirs. 20 an hour (`429`).
- **Complete** when every part of the idea's voicing has a ready voice: the writer is told once.
- **Telling the writer:** the bell at once (`idea_voice`, grouped per idea a day with `others`;
  `idea_complete` once), and email at most once a day per idea: "Sam sang the alto of your tune"
  (that day's new voices), and "Every part of your tune has a voice". `account.update`
  `ideaEmails: false` stops them (true by default), and each email's one-click unsubscribe does
  the same (`List-Unsubscribe`).
- **Reports** (`reports.create` `ideaId`): enough people (the shared-page rule) hold an idea for
  review. Hillel hides an idea (`idea_hidden` notice to its writer) or one voice
  (`idea_voice_hidden` to its singer), each appealable, or shows it again.
- **Gone** when the writer deletes it, its template, or their account (its voices go too); a
  singer's voice goes with their account, or when they or the writer delete it.

An idea: `{ "id": "<12 characters>", "title", "note", "writer": { "handle", "name", "avatarUrl"
}, "template": { "id", "version" }, "voicing": 4, "parts": [{ "key", "name", "range": { "low",
"high" }, "voices": 2 }], "voices": 3, "complete": false, "status": "listed" | "held" | "hidden",
"mine": false, "createdAt" }`. `parts` are the voicing's parts, melody first; `voices` on each is
how many ready voices it has. `ideas.show` adds `voiceList` and `song`: the tune itself, as
`templates.show` answers it (`grant: "idea"`, or `own` for its writer), so the idea's page is one
call.

A voice: `{ "id": "<uuid>", "partKey", "singer": { "handle", "name", "avatarUrl" }, "status":
"uploading" | "processing" | "ready" | "failed", "url": "..." | null, "sampleRate", "frames",
"offsetFrames", "framesPerPeak", "peaks", "mine": false, "createdAt" }`, its outline as a take's
(null until ready). Others see only ready ones; a singer also sees
their own on the way.

#### `ideas.list`

The Ideas shelf, newest first: `?tab=open` (the default: waiting for voices) or `complete`,
`needs=<partKey>` (those still without a voice on that part: "which songs need an alto?"), and
`cursor`. 24 a page. Listed ideas only, never anyone the caller is blocked with. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `ideas.list.json` | `ideas.list.200.json` |
| 404 | `not_found` | `error.json` | `ideas.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `ideas.list.validation_failed.json` |

#### `ideas.mine`

The caller's own ideas, newest first, held and hidden ones too (with `status`), so the writer
can follow each one's voices. Answers while the flag is closed. **Request:** `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `ideas.list.json` | `ideas.mine.200.json` |

#### `ideas.show`

One idea, with `voiceList`: its ready voices, newest first (and the caller's own on the way). A
held one opens for anyone with the link; a hidden one for its writer only. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `ideas.show.json` | `ideas.show.200.json` |
| 404 | `not_found` | `error.json` | `ideas.show.not_found.json` |

#### `ideas.create`

Share a tune as an idea. **Request:** `{ "templateId": "<the writer's template>", "note": "...",
"voicing": 4, "rightsConfirmed": true }` (`note` up to 300, no links; `voicing` one the template
has, its largest by default). `201` when new, `200` with the one there is. Ten a day. A tune Hillel
hid in the library can't be shared (`404`), and one whose last idea was hidden or held by reports
waits for review again.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `ideas.show.json` | `ideas.create.200.json` |
| 201 |  | `ideas.show.json` | `ideas.create.201.json` |
| 403 | `adult_required` | `error.json` | `ideas.create.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `ideas.create.email_unverified.json` |
| 404 | `not_found` | `error.json` | `ideas.create.not_found.json` |
| 409 | `ideas_closed` | `error.json` | `ideas.create.ideas_closed.json` |
| 422 | `validation_failed` | `error.json` | `ideas.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `ideas.create.rate_limited.json` |

#### `ideas.delete`

The idea and every voice on it go; its template stays the writer's. Deleting one already gone
answers `204`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `ideas.voices.create`

Add a voice. **Request:** `{ "id": "<uuid>", "partKey": "alto", "sampleRate": 48000, "frames":
480000, "offsetFrames": 0, "bytes": 960044, "sha256": "...", "rightsConfirmed": true }`. Answers
the voice (`data`) and where to `PUT` the WAV (`upload`, null when that id was already received),
as `templates.recordings.create` does. The idea must be listed (its writer may sing their own
while it's held).

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `ideas.voice.upload.json` | `ideas.voices.create.201.json` |
| 403 | `adult_required` | `error.json` | `ideas.voices.create.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `ideas.voices.create.email_unverified.json` |
| 404 | `not_found` | `error.json` | `ideas.voices.create.not_found.json` |
| 409 | `ideas_closed` | `error.json` | `ideas.voices.create.ideas_closed.json` |
| 422 | `validation_failed` | `error.json` | `ideas.voices.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `ideas.voices.create.rate_limited.json` |

#### `ideas.voices.complete`

The WAV is up: it's checked and made into AAC (`processing`, then `ready` or `failed`, as a
take). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `ideas.voice.json` | `ideas.voices.complete.200.json` |
| 404 | `not_found` | `error.json` | `ideas.voices.complete.not_found.json` |
| 409 | `upload_incomplete` | `error.json` | `ideas.voices.complete.upload_incomplete.json` |
| 409 | `upload_mismatch` | `error.json` | `ideas.voices.complete.upload_mismatch.json` |

#### `ideas.voices.delete`

By its singer, or the idea's writer. Gone already answers `204`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `admin.ideas.held`

Ideas waiting for review (a new writer's, or reported), oldest first, each with its open
reports' count and reasons. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.ideas.held.json` | `admin.ideas.held.200.json` |

#### `admin.ideas.decide`

**Request:** `{ "decision": "approve" | "hide" | "show", "note": "...", "voiceId": "<uuid>" }`
(`voiceId` to hide or show one voice instead of the idea; a note is needed to hide). Audited.
Answers the queue.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.ideas.held.json` | `admin.ideas.decide.200.json` |
| 404 | `not_found` | `error.json` | `admin.ideas.decide.not_found.json` |
| 409 | `not_held` | `error.json` | `admin.ideas.decide.not_held.json` |
| 422 | `validation_failed` | `error.json` | `admin.ideas.decide.validation_failed.json` |

### The coach directory (N4)

Finding a coach without their link (`docs/coaching-marketplace-plan.md`, F7), behind the
`coach_directory` flag: closed, `coaches.list` and `/coaches` answer `404`, and coaches are reached
by their own link as before. Only approved coaches who can take payment are listed, never one
suspended or blocked with the caller.

A coach in the directory: `{ "handle", "name", "avatarUrl", "bio" (up to 200 characters, cut at a
word), "voiceTypes", "styles", "languages", "timezone", "rating": { "average", "count" }, "newCoach",
"from": { "cents", "currency" } | null (their lowest price on sale), "turnaroundDays": 3 | null (their
fastest review), "nextLessonAt": "..." | null (their soonest free lesson time in the next 14 days),
"featured": false }`. Prices, turnaround and the next lesson are worked out hourly; `rating` and
`newCoach` are as on `coaches.show`.

#### `coaches.list`

**Request:** `?voiceType=alto`, `style=gospel`, `language=en`, `maxPrice=3000` with `currency=usd`
(their lowest price in that currency, at most that), `turnaround=7` (a review within that many
days), `lessons=1` (a free lesson time in the next 14 days), `rating=4` (an average of at least
that), `song=<template id>` (for suggestions: styles that match the song's genres come first),
`sort` (`suggested`, the default: featured, then matching the voice type and the song, then by
rating; `rating`; `price`, lowest first; `soonest`, by next lesson), `tab=featured` (Hillel's
choice only), and `cursor` (the page, as `meta.nextCursor` gives it). 24 a page. `meta` also has
`styles` and `languages`: those in use, for the filters.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coaches.list.json` | `coaches.list.200.json` |
| 404 | `not_found` | `error.json` | `coaches.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `coaches.list.validation_failed.json` |

#### `admin.coaches.feature`

On or off the Featured coaches. **Request:** `{ "featured": true }`. Only an approved coach
(`404` otherwise). Audited.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `coaches.directory.item.json` | `admin.coaches.feature.200.json` |
| 404 | `not_found` | `error.json` | `admin.coaches.feature.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.coaches.feature.validation_failed.json` |

The public page **`/coaches`** (`public.coaches`: `$coaches`, the first page, and `$meta`) is the
frontend's view, on the public host; until it exists the page answers `404`.

### Open parts (N5)

Finishing someone's harmony (`docs/more-features-plan.md`, 5), behind the `open_parts` flag (and,
for the shelf, the gallery's): closed, `harmonies.open` answers `409 open_parts_closed` and the
reads `404`.

- **Opening parts** (`harmonies.open`): the owner leaves some of a public harmony's parts open
  (public as for guide voices: featured or in the gallery, live, every friend's yes, the owner 18
  or over; `409 not_public` otherwise), on one of our songs or a template that's public (`409
  not_official`), at least one part still sung (`422`). The other parts' takes in its shared mix
  are copied as its **stems**, kept with the harmony and gone with it (`409 stems_missing` for a
  harmony shared before its mix's takes were kept). `{ "parts": [] }` closes them; the stems go.
- **Filling one** (`harmonies.stems`, then `harmonies.create`): the singer hears the stems while
  they sing the open part in their own project from the same song, at the original key and tempo,
  then shares their mix as usual with `parentId` (the original) and `fillPart` (the part they
  sang). It's refused (`409 fill_mismatch`) when the part isn't open, the song, key or tempo
  differ, or they're blocked with its owner. A fill is the singer's own harmony, private by
  default, under the gallery's rules; it credits the original's singers (`parent`).
- **The owner is told** in the bell (`fill`, once per singer) when a fill becomes public, and can
  feature one (`harmonies.update` `featuredFillId`, a public fill of theirs, or null).
- **On harmonies.show:** `openParts` (`[{ "key", "name" }]`, empty when none), `openPartsGuide`
  (whether the open part plays its guide in the published mix: the app's choice when it mixed),
  `parent` (`{ "id", "title", "owner", "part" }` on a fill, else null), `featuredFill` (`{ "id",
  "title", "owner" }` or null). Gallery items carry `openParts` too, and `explore.list?tab=open`
  is the **Open parts** shelf.

#### `harmonies.open`

**Request:** `{ "parts": ["tenor"], "guide": true }` (part keys of the song's parts sung in it;
`guide`: whether the open parts play their guide in its mix, kept for the page). Answers the
harmony. Copying the stems takes a moment: `harmonies.stems` answers `409 stems_not_ready` until
it's done.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.show.json` | `harmonies.open.200.json` |
| 404 | `not_found` | `error.json` | `harmonies.open.not_found.json` |
| 409 | `not_official` | `error.json` | `harmonies.open.not_official.json` |
| 409 | `not_public` | `error.json` | `harmonies.open.not_public.json` |
| 409 | `open_parts_closed` | `error.json` | `harmonies.open.open_parts_closed.json` |
| 409 | `stems_missing` | `error.json` | `harmonies.open.stems_missing.json` |
| 422 | `validation_failed` | `error.json` | `harmonies.open.validation_failed.json` |

#### `harmonies.stems`

What a filler sings with: the song (`template`: `{ "id", "version" }`), the original's `bpm`, its
open parts, and each stem (`{ "partKey", "singer": { "handle", "name", "avatarUrl" }, "url" (2
hours), "sampleRate", "frames", "offsetFrames", "trimStartFrames", "trimEndFrames", "volume",
"pan" }`, placed as the original's parts were). Signed in, 18 or over, a verified email, and not
blocked with its owner. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `harmonies.stems.json` | `harmonies.stems.200.json` |
| 403 | `adult_required` | `error.json` | `harmonies.stems.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `harmonies.stems.email_unverified.json` |
| 404 | `not_found` | `error.json` | `harmonies.stems.not_found.json` |
| 409 | `stems_not_ready` | `error.json` | `harmonies.stems.stems_not_ready.json` |

#### `harmonies.fills.list`

A harmony's public fills, the featured one first, then newest, as gallery items. Cursor-paginated
(24). `404` while closed. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `explore.list.json` | `harmonies.fills.list.200.json` |
| 404 | `not_found` | `error.json` | `harmonies.fills.list.not_found.json` |

### The virtual choir (N6)

Each of our songs has a choir (`docs/more-features-plan.md`, 6), behind the `virtual_choir` flag
(closed: reads `404`, `choirs.join` `409 choir_closed`). Anyone 18 or over with a verified email
adds their part; the server mixes the choir (the one place it mixes audio, logged in
decisions.md): at most hourly when its voices changed, up to 60 voices a part (the newest), each
levelled, spread across the stereo field by part, with the same hall as "Make it sound good".

- **Joining** sends a ready take of the caller's own from a project of that song at its original
  key and tempo (`409 key_or_tempo`), on the part they name, with the app's pitch score (0 to
  100; under 60 is `422 score`: the app's "Almost! Practise phrase 3 and try again") and their
  rights confirmation. A take with a stored report (its scores, kept under its id) is judged by
  that: its `pitchPercent` must be within 1 of the score sent, and a clipped or bleeding take is a
  `422` on `takeId`. Their part is copied into the choir (never shown on its own); joining again
  replaces it. `credit`: `handle` (their profile name, with their handle when it's public) or
  `anonymous` ("A singer").
- **Leaving** takes their voice out; the next mix drops it.
- **Reports** (`reports.create` `choirVoiceId`: a voice's id from `choirs.voices.list`) by the
  shared-page rule hold a voice out of the mix and the list until Hillel looks
  (`admin.choirs.voices.held`): he puts it back (`approve`), or takes it out for good (`remove`,
  with a `choir_voice_removed` notice the singer can appeal). A singer can always leave, held or
  taken out, and their recording goes. What they trimmed off their take stays out of the mix.
- The caller's own voice (`mine`) comes with its URL (2 hours) and placement, so **Hear my voice
  louder** plays it over the mix in the app.

#### `choirs.show`

The choir of one of our songs (`{song}` is its id): `{ "song": { "id", "title", "version" },
"bpm", "key", "mixUrl" | null, "mixedAt" | null, "voices": 214, "minScore": 60,
"maxVoicesPerPart": 60, "parts": [{ "key", "name", "voices" }], "mine": { "id", "partKey", "status": "processing" | "ready" | "failed", "inMix": true,
"credit", "url", "sampleRate", "frames", "offsetFrames" } | null }`. `inMix`: the current mix has
their voice ("You're one of 58 altos"). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `choirs.show.json` | `choirs.show.200.json` |
| 404 | `not_found` | `error.json` | `choirs.show.not_found.json` |

#### `choirs.voices.list`

Who sings in it, newest first, ready and not taken out: `{ "id", "partKey", "singer": { "handle",
"name", "avatarUrl" }, "createdAt" }` (an anonymous voice is "A singer", with no handle or avatar).
`?part=alto` narrows it. Cursor-paginated (50). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `choirs.voices.list.json` | `choirs.voices.list.200.json` |
| 404 | `not_found` | `error.json` | `choirs.voices.list.not_found.json` |

#### `choirs.join`

**Request:** `{ "takeId": "<uuid>", "partKey": "alto", "score": 82, "credit": "handle",
"rightsConfirmed": true }`. Answers the choir (`choirs.show`), with `mine` processing.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `choirs.show.json` | `choirs.join.201.json` |
| 403 | `adult_required` | `error.json` | `choirs.join.adult_required.json` |
| 403 | `email_unverified` | `error.json` | `choirs.join.email_unverified.json` |
| 404 | `not_found` | `error.json` | `choirs.join.not_found.json` |
| 409 | `choir_closed` | `error.json` | `choirs.join.choir_closed.json` |
| 409 | `key_or_tempo` | `error.json` | `choirs.join.key_or_tempo.json` |
| 422 | `validation_failed` | `error.json` | `choirs.join.validation_failed.json` |

#### `choirs.leave`

Their voice out, whatever it is now (held, taken out, the choirs closed or the song gone), and its
recording deleted; none to take answers the same. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |

#### `admin.choirs.voices.held`

Voices held by reports, oldest first, for Hillel to hear: `{ "id", "partKey", "singer": { "handle",
"name", "avatarUrl", "id" }, "song": { "id", "title" }, "url" (2 hours), "heldAt", "reports": {
"count", "reasons" } }`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.choirs.voices.held.json` | `admin.choirs.voices.held.200.json` |

#### `admin.choirs.voices.approve`

A held voice back in the choir, its reports answered. Audited. Answers the queue. **Request:**
none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.choirs.voices.held.json` | `admin.choirs.voices.approve.200.json` |
| 404 | `not_found` | `error.json` | `admin.choirs.voices.approve.not_found.json` |

#### `admin.choirs.voices.remove`

**Request:** `{ "note": "..." }` (the statement of reasons). Out of the choir (the next mix drops
it), and the singer told (`choir_voice_removed`, which they can appeal: a reversal brings it back).
Audited.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 |  |  |  |
| 404 | `not_found` | `error.json` | `admin.choirs.voices.remove.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.choirs.voices.remove.validation_failed.json` |

### Song requests (N7)

What people want to sing next (`docs/more-features-plan.md`, 8), behind the `song_requests` flag
(closed: reads `404`, writes `409 requests_closed`). From our own records only.

A request: `{ "id", "title", "writer" | null, "why" | null (the first asker's reason), "votes",
"voted", "mine" (the caller asked for it), "status": "requested" | "checking" | "arranging" |
"ready" | "cant", "template": { "id", "title" } | null (the song, once it's ready to learn),
"createdAt" }`. Asking counts as your
vote. When one becomes `ready`, everyone who asked or voted is told in the bell (`request_ready`,
with `request: { "id", "title" }`); never by email.

- **Text:** the title (1 to 120), who wrote it (up to 120) and why (up to 300) are plain text, no
  links (`422`); words the filter holds keep it out of the list until Hillel looks.
- **Limits and reports** as for comments: a verified email, a few an hour (`429`), and
  `reports.create` takes `requestId`.

#### `requests.list`

**Request:** `?q=` (titles like it: the app shows these first so people vote instead of asking
again), `status`, `sort` (`wanted`, the default, by votes; `newest`), `cursor`. 50 a page.
Hidden, held and merged ones never show.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `requests.list.json` | `requests.list.200.json` |
| 404 | `not_found` | `error.json` | `requests.list.not_found.json` |
| 422 | `validation_failed` | `error.json` | `requests.list.validation_failed.json` |

#### `requests.create`

**Request:** `{ "title": "Danny Boy", "writer": "Traditional", "why": "Our choir sings it at
Christmas" }`. The same title already asked for (ignoring case, spaces and punctuation) is voted
for instead, and answered as it is (`200`); a new one is `201`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `requests.show.json` | `requests.create.200.json` |
| 201 |  | `requests.show.json` | `requests.create.201.json` |
| 403 | `email_unverified` | `error.json` | `requests.create.email_unverified.json` |
| 409 | `requests_closed` | `error.json` | `requests.create.requests_closed.json` |
| 422 | `validation_failed` | `error.json` | `requests.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `requests.create.rate_limited.json` |

#### `requests.vote`

One vote an account (again answers the same). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `requests.show.json` | `requests.vote.200.json` |
| 403 | `email_unverified` | `error.json` | `requests.vote.email_unverified.json` |
| 404 | `not_found` | `error.json` | `requests.vote.not_found.json` |

#### `requests.unvote`

**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `requests.show.json` | `requests.unvote.200.json` |
| 404 | `not_found` | `error.json` | `requests.unvote.not_found.json` |

#### `admin.requests.list`

Every request, held first, then by votes, with `held`, `hidden`, `mergedInto` and its open reports'
count. **Request:** `?cursor=`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.requests.list.json` | `admin.requests.list.200.json` |

#### `admin.requests.update`

**Request:** any of `{ "status": "ready", "templateId": "danny-boy", "mergeInto": "<id>",
"hidden": true, "approve": true }`. `ready` needs one of our songs (`templateId`, `422`
otherwise) and tells everyone who asked or voted, once. Merging moves its votes to the other
request (one each) and hides it. `approve` lets a held one show. Audited. Answers the request as
`admin.requests.list` shows it.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.requests.item.json` | `admin.requests.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.requests.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.requests.update.validation_failed.json` |

### The mudeo email (L)

Hillel's one email to mudeo's users (`docs/mudeo-transition-plan.md`), sent through the app 50
at a time, one email each, from its own one-off sending domain (hillel@mudeo.stackapella.com,
`MAIL_MUDEO_FROM`; replies to contact@stackapella.com), with a secret early-access link and a one-click unsubscribe.

- **The link** is `/early/<code>` (a 40-character random code), served by the app with `noindex`
  and no referrer. Its code on `auth.register` (or the Google and Apple forms) as `early` lets
  someone make an account while `pause_signups` is on, as a beta invite does, without making them
  a beta singer. Each account made with an open code is marked with its link ("from mudeo"), paused
  or not. It holds: a cap on accounts (500 to start), five sign-ups a day from one IP address, a
  pause, and replacing the code (the old one then answers `closed`). Shield mode's check still
  applies. When it isn't open at the moment of sign-up, the answer is the usual `423 paused`.
- **The list** comes from mudeo's database as CSV (header `id,name,email,created_at,updated_at`).
  On import the addresses are lower-cased and trimmed, and the import skips: invalid ones,
  duplicates, test and placeholder domains (Apple's relay stays), suppressed addresses, addresses
  that already have a Stackapella account, and addresses already on the list. Batches go most
  recently active first. Opt-outs and bounces join the suppression list. The list is deleted 90
  days after its last batch, except the opt-outs. Every change is in the audit log.
- **The daily cap:** `sentToday` counts today's batches. `dailyCap` (and `limit`) is 200 to start,
  editable. The screen warns from `warnAt` (80% of it), and sending refuses past it. A batch's
  `bounceRate` above 0.05 means stop for the day.
- **The email** is personal ("Hi <first name>,", or "Hi,"), in plain text and simple HTML, with
  no images or tracking, and the words in `lang/en/mudeo.php`. Its footer and its
  `List-Unsubscribe` and `List-Unsubscribe-Post` headers carry a signed `/unsubscribe/<id>` link.
  GET shows one button, and POST (the button, or the mail provider's one click) suppresses the
  address. A permanent bounce Cloudflare reports when sending marks the contact `bounced`.
  Sending (and the test) refuses until the sending domain is onboarded (`409 news_not_set_up`,
  `newsReady`; set by `MAIL_MUDEO_READY` once a test passes SPF, DKIM and DMARC) and the footer's
  postal address (`409 postal_address_missing`) is set.
- **The copy fallback:** `admin.mudeo.next` and `admin.mudeo.batches.create` still hand out the
  next 50 to paste, and mark them sent by hand (counted toward the same cap).

#### `earlyAccess.show`

Whether the link lets people in now: `open`, `paused`, `full` or `closed`. An unknown or replaced
code is `closed`, so codes can't be told apart. Limited (`invite-links`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `earlyAccess.show.json` | `earlyAccess.show.200.json` |

#### `admin.earlyAccess.show`

The link to paste into the email (made the first time it's asked for), its cap, how many joined,
and the replaced ones. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.earlyAccess.show.200.json` |

#### `admin.earlyAccess.update`

**Request:** `{ "cap": 500, "paused": true }`, either or both (`cap` 1 to 100000).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.earlyAccess.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.earlyAccess.update.validation_failed.json` |

#### `admin.earlyAccess.replace`

A new code at once, keeping the cap and a pause. The old one answers `closed`, and its count
moves to `earlier`. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.earlyAccess.replace.200.json` |

#### `admin.preview.show`

Hillel's **preview link** (`/preview/<code>`): it opens the app before launch without an account,
for anyone it's shared with (`auth.config`'s `comingSoon` says the app is in this mode). It gives
the **beta pass**, a signed cookie that opens the app at `/`
while coming soon is on. Joining by an open beta invite or the early-access link gives the same
pass, and so does any API call while signed in. A session cookie alone no longer opens it, nor do
`/beta` or a made-up token. A preview code never lets a sign-up through paused sign-ups. The same
shape as `admin.earlyAccess.*`: `joined` counts the passes given, and `cap` is 100000.
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.preview.show.200.json` |

#### `admin.preview.update`

**Request:** `{ "paused": true }` (or `cap`). A paused or replaced code shows "coming soon".

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.preview.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.preview.update.validation_failed.json` |

#### `admin.preview.replace`

A new code at once; the old one stops opening the app (passes already given stay). **Request:**
none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.preview.replace.200.json` |

#### `inviteLink.show`

Hillel's **shared invite link** (`/invite/<code>` on the app's host; docs/decisions.md,
"invites"): one link he hands to anyone, untracked. Opening an open one gives the **beta pass**
(the app page, as `/early/` does), and a sign-up carrying its code (`auth.register`'s `early`, and
the same for Google and Apple) goes through paused sign-ups, with the 13 and over check as
always. It never marks anyone as from mudeo. Whether it lets people in now: `open`, `paused` or
`closed` (an unknown or replaced code is `closed`: the app says "this link has ended"). Limited
(`invite-links`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `earlyAccess.show.json` | `inviteLink.show.200.json` |

#### `admin.invite.show`

The shared invite link (made the first time it's asked for), with the same shape as
`admin.earlyAccess.*` (`cap` 100000; `joined` is only a count). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.invite.show.200.json` |

#### `admin.invite.update`

**Request:** `{ "paused": true }`. A paused code lets nobody in until it's unpaused.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.invite.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.invite.update.validation_failed.json` |

#### `admin.invite.replace`

A new code at once; the old one ends (`closed`). Audited. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.earlyAccess.json` | `admin.invite.replace.200.json` |

#### `admin.mudeo.show`

The list's counts, today's sends against the limit, the last batch, and how many joined through
the link. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.json` | `admin.mudeo.show.200.json` |

#### `admin.mudeo.update`

**Request:** `{ "dailyCap": 200 }` (1 to 2000).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.json` | `admin.mudeo.update.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.mudeo.update.validation_failed.json` |

#### `admin.mudeo.import`

**Request:** `multipart/form-data` with `file` (CSV, at most 20 MB). Importing again adds only new
addresses.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.import.json` | `admin.mudeo.import.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.mudeo.import.validation_failed.json` |

#### `admin.mudeo.send`

Send the next batch: up to 50, the most recently active first, held for this batch at once and
sent one by one by a queued job. The answer is the counts with `lastBatch.status` `sending`:
poll `admin.mudeo.show` until it's `sent` (or `failed`, with `error`). Anyone it couldn't reach
waits for a later batch. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `admin.mudeo.json` | `admin.mudeo.send.202.json` |
| 409 | `batch_sending` | `error.json` | `admin.mudeo.send.batch_sending.json` |
| 409 | `daily_limit` | `error.json` | `admin.mudeo.send.daily_limit.json` |
| 409 | `news_not_set_up` | `error.json` | `admin.mudeo.send.news_not_set_up.json` |
| 409 | `nothing_to_send` | `error.json` | `admin.mudeo.send.nothing_to_send.json` |
| 409 | `postal_address_missing` | `error.json` | `admin.mudeo.send.postal_address_missing.json` |

#### `admin.mudeo.test`

Send a test to me: the email as a person gets it, to the signed-in admin's address. Its
unsubscribe link only says it's a test. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 202 |  | `admin.mudeo.test.json` | `admin.mudeo.test.202.json` |
| 409 | `news_not_set_up` | `error.json` | `admin.mudeo.test.news_not_set_up.json` |
| 409 | `postal_address_missing` | `error.json` | `admin.mudeo.test.postal_address_missing.json` |

#### `admin.mudeo.next`

The next batch (up to 50, the most recently active first) as `ids`, `emails` and one
comma-separated `line` to paste into Bcc. `fits` is false when sending it would pass today's
limit, and `remaining` is how many still wait. With nothing left, the lists are empty. **Request:**
none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.next.json` | `admin.mudeo.next.200.json` |

#### `admin.mudeo.batches.create`

These were sent. **Request:** `{ "ids": ["<id>", ...] }` (1 to 50, from `admin.mudeo.next`).
Answers the counts. `409 daily_limit` (with `sentToday` and `limit`) when they'd pass today's
limit. `422` when one isn't on the list or was already sent, opted out or bounced.

| Status | Code | Schema | Example |
|---|---|---|---|
| 201 |  | `admin.mudeo.json` | `admin.mudeo.batches.create.201.json` |
| 409 | `daily_limit` | `error.json` | `admin.mudeo.batches.create.daily_limit.json` |
| 422 | `validation_failed` | `error.json` | `admin.mudeo.batches.create.validation_failed.json` |

#### `admin.mudeo.batches.undo`

The last batch marked by hand didn't go: its addresses wait again. One the app sent can't be
undone (`409 sent_by_app`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.json` | `admin.mudeo.batches.undo.200.json` |
| 404 | `not_found` | `error.json` | `admin.mudeo.batches.undo.not_found.json` |
| 409 | `sent_by_app` | `error.json` | `admin.mudeo.batches.undo.sent_by_app.json` |

#### `admin.mudeo.mark`

**Request:** `{ "emails": ["..."], "as": "opted_out" | "bounced" }` (1 to 500). They leave the
list and join the suppression list. `notFound` lists the addresses that weren't on it.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.mark.json` | `admin.mudeo.mark.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.mudeo.mark.validation_failed.json` |

#### `admin.mudeo.contacts`

Up to 20 addresses containing `?q=` (2 to 255 characters), with their status. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.mudeo.contacts.json` | `admin.mudeo.contacts.200.json` |
| 422 | `validation_failed` | `error.json` | `admin.mudeo.contacts.validation_failed.json` |

### Counts, the network panel and What's next (L)

Counted from our own records, never analytics. A **finished** song is a project from a template
whose parts (two or more) each have a ready take its owner sang. **Singers active in a week** are
signed-in people who recorded, practised (a score saved) or had a lesson in the last 7 days.

- **`admin.overview`** (M1) now has `harmonies.finished` and `harmonies.secondSongs` (no longer
  null), and `growth`: `signUps`, `upgrades` (Plus subscriptions started) and `activeSingers`, each
  over the last 7 days.
- **`harmonies.update`** takes `inGallery` (true or false): "show it in the gallery when it opens".
  Nothing is public until N1 opens; the network panel counts the opt-ins. A harmony's `inGallery`
  says which.

#### `templates.finishers`

What's next's numbers for an official song, counted nightly from our own records (no analytics):
`finishers` (null under 20, so a handful never shows) and `alsoLearned` (the songs its finishers
finished most besides, up to 3, each shared by at least 5 of them, so it never tells what one or
two people did).
**Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `templates.finishers.json` | `templates.finishers.200.json` |
| 404 | `not_found` | `error.json` | `templates.finishers.not_found.json` |

#### `counts.create`

Anonymous usage counts (Hillel's "all in the app itself"): how often each kind of thing happens, as
one number per day, kind, guest or account, and country (from Cloudflare). Nothing else is kept: no
account, address, browser or cookie, and the request starts no session, so the app can send it by
`navigator.sendBeacon`. Send it only while `auth.config`'s `usageCounts` is true. **Request:**
`{ "events": [{ "kind": "lesson_done", "n": 2 }], "signedIn": true }`. `kind` is one of `app_open`,
`lesson_start`, `lesson_done`, `unit_done`, `song_created`, `take_recorded`, `share_created`,
`harmonise_used`, `export_done`, `result_shared` (a lesson's result card shared), `leaderboard_viewed`,
`challenge_accepted`, `sign_up` (counted
by the server itself for every new account: the app doesn't send it), and the usage page's own (config/counts.php `kinds`: the skins, Plus extras, Hum a tune, the studio, onboarding's asks and melody first), and `<prefix>:<id>` for a course or lesson: `pl_s:` and `pl_d:` with a Plus course's `id` (web/course/plus/courses.json), `fl_s:` and `fl_d:` with a free lesson's `id` (web/course/course.json), counted only for a real id (others are dropped); `n` is 1 to 20 (more counts as
20); at most 20 events a request, 30 requests a minute from one connection (then 429).

**The onboarding funnel** (docs/onboarding-review.md, section 4): each a device's first, counted once
per device by the app, the landing's Start by the site: `f_site_start`, `f_arrive` or
`f_arrive_start` (one or the other, never both: arrivals are their sum), `f_mic`, `f_note`,
`f_note_skip`, `f_lesson1`, `f_chord`, `f_lesson2`, `f_lesson3`, `f_unit1`, `f_goal`, `f_install`,
`f_name`, `f_account`, `f_share`, `f_remind`, `f_take`, `f_harmony`, `f_day2`, `f_week1`,
`f_goal_met`; and each ask (`install`, `account`, `name`, `share`, `goal`, `remind`) as
`ask_<name>_shown` (first time only) and `ask_<name>_yes`. With them the request may carry the
device's experiment arm (each funnel kind counts once a request, whatever `n` says), `"variant": "onb1-b"` (`auth.config`'s `experiment` id, a dash, the arm):
`{ "events": [{ "kind": "f_lesson1" }], "signedIn": false, "variant": "onb1-b" }`. The server keeps
it only on funnel and ask kinds and only when it names a configured experiment's arm (anything else
is counted with no variant), and a row with a variant keeps no country. An unknown kind is dropped
on its own: the rest of the batch still counts.

| Status | Code | Schema | Example |
|---|---|---|---|
| 204 | | | |
| 422 | `validation_failed` | `error.json` | `counts.create.validation_failed.json` |
| 429 | `rate_limited` | `error.json` | `counts.create.rate_limited.json` |

#### `admin.funnel.show`

Admin > Funnel. **Request:** `?experiment=onb1`, or none: every device over `?days=7`, `30` (the
default) or `90`, as one arm `"all"` (then `split` is null, `enough` false, and every `difference`
null). `experiments` is every configured one, newest first, with when it started, when enrolment
stopped (`enrolUntil`: its `end` day, once passed; counts never stop it, so a flood can't end a test,
and `enough` says when to set the end) and when it can be
read (`readFrom`, a week later, so `f_day2` and `f_week1` have matured). `steps` is every funnel kind
in order, per arm: guest, account, total and `rate` (total over that arm's arrivals); `difference`
is the second arm's rate minus the first's in percentage points with its 95% interval (an interval
that excludes 0 is a real difference). `split` checks the arms' arrivals against 50/50 (`ok` false
below p 0.001 means a bucketing bug, not a result). `asks` is each ask's shown and yes per arm.
`days` is each day's totals per kind and arm. Totals only: nothing follows a device.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.funnel.show.json` | `admin.funnel.show.200.json` |

#### `admin.counts.show`

The Usage panel. **Request:** `?days=7`, `30` (the default) or `90`. `days` is each day's count per
kind (guest and account), `totals` the same summed over the period, and `countries` every kind
summed per country, most first (`country` null: not known).

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.counts.show.json` | `admin.counts.show.200.json` |

#### `admin.diagnostics.country`

What the admin's own request carried from Cloudflare (`CF-IPCountry`) and whether the app took a
country from it: booleans and the two letters, never an address. **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 | | `admin.diagnostics.country.json` | `admin.diagnostics.country.200.json` |

#### `admin.network.show`

The network features panel: one row per N1 to N7 (`docs/roadmap.md`, "After launch"), each with its
`numbers` against their thresholds and its `status` (`collecting` under half, `start_building` at
half, `ready_to_open` at the threshold, `open` when its feature flag is on). Opening one is its
flag (`admin.flags.update`). **Request:** none.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.network.json` | `admin.network.show.200.json` |

#### `admin.network.update`

New thresholds for one feature (`{feature}` is `N1` to `N7`). **Request:** `{ "thresholds": {
"harmonies": 200 } }`, keys from that row's `numbers`.

| Status | Code | Schema | Example |
|---|---|---|---|
| 200 |  | `admin.network.json` | `admin.network.update.200.json` |
| 404 | `not_found` | `error.json` | `admin.network.update.not_found.json` |
| 422 | `validation_failed` | `error.json` | `admin.network.update.validation_failed.json` |

### Public pages: `/coach/<handle>`: `public.coach`

An approved, active coach's profile (contract: `coaches.show`'s data), `noindex` until the directory
opens. No session, no cookies. Variables: `$coach` (as `coaches.show`'s `data`), `$meta` (`title`,
`description`, `url`, `image`: the avatar or null), `$links` (`app`: `/?coach=<handle>&ref=c-<handle>`,
the app's way in, for buying). Anything else is `public.coach-gone` (`404`), with `$links`.

### Public pages: the view models (M6)

The public pages are Blade, served by Laravel on our origin with **no session and no cookies**
(cacheable: `Cache-Control: public, max-age=300`). The frontend agent writes the markup in
`server/resources/views/public/`; the backend passes exactly these variables (its tests render
the landed view). Every word on the page comes from `__()`; the data below is already in the
page's language where it's ours ("Untitled song").

#### `/h/<id>`: `public.harmony`

An unlisted, ready harmony that isn't moderated. Headers: `X-Robots-Tag: noindex`, and
`<meta name="robots" content="noindex">` in the page.

```php
$harmony = [
    'id' => 'k3Xb9QmT7wLp',
    'title' => 'Silent Night for Mum',          // "Untitled song" when blank or held
    'singers' => [['name' => 'Ana Ruiz', 'avatarUrl' => 'https://.../a.jpg']], // the owner; avatarUrl may be null
    'guides' => [['name' => 'Maria Lopez', 'part' => 'Alto']], // beta singers whose voices are in it
    'song' => [                                   // null when the project isn't from a template
        'slug' => 'silent-night',
        'title' => 'Silent Night',
        'credits' => 'Words: Joseph Mohr (1816). Tune: Franz Xaver Gruber (1818)', // from the template, may be ''
        'arrangedBy' => 'Stackapella',
    ],
    'part' => null,                               // the part's name for "just my part", else null
    'durationSeconds' => 182.4,
    'audioUrl' => 'https://cdn.../h/abc.m4a',     // AAC in MP4, on the CDN
    'audioType' => 'audio/mp4',
    'peaks' => [0.12, 0.4, ...],                  // up to 600 values, 0 to 1, for the waveform
    'colors' => ['#e4a94b', '#6fa8dc'],           // the parts' colours, in order
    'creditLine' => 'Sung by Ana Ruiz',           // or "Played by ..." or "Sung and played by ...", from the parts' kinds
    'parts' => [['index' => 1, 'name' => 'Alto', 'color' => '#e4a94b', 'kind' => 'voice', 'peaks' => [0.1, 0.5, ...]]], // one waveform per part, in the studio's order: 120 values, 0 to 1 (fewer only for an empty part)
    'cardUrl' => 'https://cdn.../h/abc.jpg',      // 1200 x 630 JPEG
    'pageUrl' => 'https://stackapella.com/h/k3Xb9QmT7wLp',
    'createdAt' => '2026-10-26T14:05:00Z',
];
$meta = [                                         // for the <head>: OG and Twitter tags
    'title' => 'Silent Night for Mum',            // the page's <title> and og:title
    'description' => 'Ana Ruiz sang every part of Silent Night on Stackapella.',
    'image' => 'https://cdn.../h/abc.jpg', 'imageWidth' => 1200, 'imageHeight' => 630,
    'imageAlt' => 'Silent Night for Mum, sung by Ana Ruiz',
    'url' => 'https://stackapella.com/h/k3Xb9QmT7wLp',
    'audio' => 'https://cdn.../h/abc.m4a',        // og:audio
];
$links = [
    'learn' => 'https://app.stackapella.com/?song=silent-night&ref=h-k3Xb9QmT7wLp', // "Learn this harmony"; null without a song
    'app' => 'https://app.stackapella.com/?ref=h-k3Xb9QmT7wLp',                      // "Make your own"
    'api' => 'https://app.stackapella.com', // the app's origin for Report; null on one host
];
$report = ['harmonyId' => 'k3Xb9QmT7wLp'];              // the Report form posts reports.create
```

The links into the app are absolute on the app's host (relative when there's one host). With
`api` set, the Report form reads `{api}/api/auth/config` and posts `{api}/api/reports` across
origins, with credentials omitted and no CSRF header (that route needs none). With no `api`, it
makes a `GET` (for example `auth.config`) when the form opens for the CSRF cookie, then posts
`reports.create`.

#### Not there: `public.harmony-gone`

Any other `/h/<id>`: no such harmony, private, not ready, deleted or moderated. `404`, or `410`
when it was removed. The same headers, and `$reason` is `missing` (404) or `removed` (410), with
`$links = ['app' => '/']`.

#### `/embed/h/<id>`: `public.embed-harmony` (M9)

The player alone, for other sites to frame (Event Schedule's event pages, through oEmbed): the only
page any `https` site may frame (`frame-ancestors https:`). The same harmonies as `/h/<id>`, the
same headers, and the same `$harmony` (its `pageUrl` opens the full page in a new tab); no Report
form and no OG tags. Anything else is `public.embed-gone` (`404` or `410`, with `$reason`).

**oEmbed** (`GET /oembed?url=<a /h/<id> link>&format=json&maxwidth=&maxheight=`, JSON only,
cached 5 minutes): `{ "type": "rich", "version": "1.0", "title", "author_name",
"provider_name": "Stackapella", "provider_url", "thumbnail_url", "thumbnail_width": 1200,
"thumbnail_height": 630, "html": "<iframe src=\"https://<domain>/embed/h/<id>\" width=\"480\"
height=\"160\" ...></iframe>", "width": 480, "height": 160 }`; a link that isn't a live harmony is
`404`, and a `format` other than `json` is `501`. `public.harmony` gets `$meta['oembed']`, the
discovery link's address (`<link rel="alternate" type="application/json+oembed" href="...">`).

#### The landing, pricing and help pages (L)

The backend's pages (`resources/views/pages/`, on `public.layout`), the same headers as below.
**`/` is the landing** for a first-time visitor, and the app shell for anyone who has used the app
or asks for it: the app sets `sa_app=1` (a year, `SameSite=Lax`, no id) when its shell boots, the
installed app starts at `/?app`, and a session or remember cookie counts too. The landing is never
cached (`Cache-Control: no-cache`, `Vary: Cookie`). It sends visitors to `/learn/1-comfortable`.
`/pricing`, `/help` and `/help/<mic|headphones|timing|iphone>` are cached like the song pages.

#### Songs, guides and legal pages (L)

For search (`docs/go-to-market-plan.md`). No session, `Cache-Control: public, max-age=300`;
`X-Robots-Tag: noindex` until launch (`SITE_INDEXING=true` then). `robots.txt` and `sitemap.xml`
are the server's own.

**`/songs`: `public.songs`.** `$songs` (a list, by title, each as `$song` below), `$meta` (`title`,
`description`, `url`).

**`/songs/<slug>`: `public.song`.** Any official song with a published version.

```php
$song = [
    'slug' => 'silent-night',
    'title' => 'Silent Night',
    'description' => 'The gentle carol, for 2 to 4 singers: ...',
    'difficulty' => 'beginner',
    'genre' => ['carol', 'christmas'],
    'parts' => [['key' => 'soprano', 'name' => 'Soprano', 'low' => 'C4', 'high' => 'F5'], ...],
    'previewUrl' => 'https://cdn.../previews/silent-night.m4a', // or null; the 20 to 30 second clip
    'previewType' => 'audio/mp4',
    'words' => ['Silent night, holy night', 'All is calm, all is bright', ...], // the first verse, by line
    'credits' => ['Words: Joseph Mohr (1816); ...', 'Tune: Franz Xaver Gruber (1818)'],
    'plus' => false,                 // "On Plus" (false while it's the song of the month)
    'freeThisMonth' => false,
    'pageUrl' => 'https://stackapella.com/songs/silent-night',
    'startUrl' => '/?song=silent-night&ref=song-page', // "Start" (the app's way in)
];
$meta = ['title' => 'Learn the harmony to Silent Night, part by part', 'description' => '...', 'url' => '...'];
```

**`/guides/<slug>`: `public.guide`.** `how-to-sing-harmony`, `find-your-part`,
`practise-a-choir-part`, `a-round-for-beginners` (drafts in `server/resources/guides/en/`).
`$guide = ['slug', 'title', 'description', 'blocks', 'reviewed', 'song' => $song or null]` (the
song to try at the end), and `$meta`. `blocks` are as the beta agreement's (`beta.agreement`):
`heading` (with `level`), `paragraph` (`text`, `**bold**` marked) and `list` (`items`), never HTML.

**`/legal/<page>`: `public.legal`.** `terms`, `privacy`, `refunds`, `community-guidelines`,
`copyright`, `coach-agreement`, `marketplace-terms`, `accessibility` and `security`, each once its
text exists in `server/resources/legal/en/`. `$document = ['page', 'title', 'version', 'reviewed',
'blocks']` (a draft banner while `reviewed` is false), and `$meta`.

**Not there: `public.page-gone`** (`404`), with `$links = ['app' => '/songs' or '/']`.

## Shared formats

| Schema | What |
|---|---|
| `template.json` | A harmony template (one version), as in `web/templates/*.json` and, from M5, `server/database/templates/`. Both sides check every official template against it |
| `error.json` | Every error response |
| `meta.json` | A list's `meta` (the next page's cursor) |
| `admin.person.json` | Who did something in the admin (a Sqid and a name), or `null` |
| `guide.recording.json` | A real voice for one part of one template version (with a nullable form at `#/$defs/nullable`) |
| `account.json` | The signed-in person's own account |
| `upload.json` | A presigned PUT: the URL, the headers to send, and when it expires |
