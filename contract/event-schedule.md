# What Stackapella needs from Event Schedule

The contract between Stackapella's server and Event Schedule (ES), for roadmap M9
(`docs/event-schedule-integration-plan.md`, G4). The changes are built in ES's own repo, to its own
rules, by its own agent; Stackapella builds against a fake of exactly this
(`server/tests/Support/FakeEventSchedule.php`) until they're live. Where ES already has something
(the appointment types, slots and idempotent booking of `routes/api.php`), this keeps its shape.

**Conventions (as ES's API already does):** JSON, `{ "data": ..., "meta": ... }`, ES's own encoded
ids, times in UTC ISO 8601 with `Z`, snake_case fields. Authentication is an OAuth 2.0 bearer token
(below), or, for a selfhosted install, the user's `X-API-Key`.

## 1. OAuth 2.0 provider (G4 item 1)

- **Authorization code with PKCE (S256), and refresh tokens.** Stackapella is a confidential client
  (`client_id`, `client_secret`), registered by ES's admin.
- `GET /oauth/authorize?response_type=code&client_id=&redirect_uri=&scope=&state=&code_challenge=&code_challenge_method=S256`:
  ES's sign-in and consent screen, listing the scopes in plain words, then a redirect to
  `redirect_uri?code=&state=`. Stackapella's `redirect_uri` is
  `https://<stackapella domain>/connect/event-schedule/callback` (connecting), and
  `https://<stackapella domain>/auth/event-schedule/callback` for Sign in with Event Schedule
  (asking for the `profile` scope only; its token is revoked as soon as Stackapella has the id).
- `POST /oauth/token` (form-encoded): `grant_type=authorization_code` with `code`, `redirect_uri`,
  `code_verifier`, `client_id`, `client_secret`; or `grant_type=refresh_token` with
  `refresh_token`. Answers `{ "access_token", "refresh_token", "expires_in", "token_type": "Bearer",
  "scope" }`. A refresh rotates the refresh token.
- `POST /oauth/revoke` (`token`): Stackapella calls it on disconnect.
- **Scopes:** `profile`, `schedules:read`, `appointments:read`, `appointments:write`,
  `events:read`, `events:write`, `webhooks:manage`, `members:read`. A call without the scope answers
  `403 {"error": "insufficient_scope"}`.
- `GET /api/me`: `{ "data": { "id", "name", "email" } }` (the `profile` scope): Stackapella keeps
  `id` (never matches accounts by email).
- **Connected apps** in ES settings list Stackapella and revoke it (its tokens stop at once).
  Revoking keeps the webhooks the app registered while bookings it made are still to come, so a
  cancellation of one still reaches Stackapella (which refunds by it); they stop after the last one.

## 2. Reading schedules, types and slots (exists; scoped)

- `GET /api/schedules` (`schedules:read`): the user's schedules, each `{ "subdomain", "name",
  "type", "timezone" }`.
- `GET /api/schedules/{subdomain}/appointment-types` (`appointments:read`): as today
  (`id, name, duration_minutes, price, currency, payment, location_type, host_name, timezone,
  requires_approval, questions, next_available, booking_url`), plus `location_url` (the meeting link)
  for the schedule's owner's own token.
- `GET /api/appointment-types/{id}/slots?from=YYYY-MM-DD&days=14` (`appointments:read`): as today,
  `[{ "starts_at", "date", "label" }]`. Stackapella caches it for 30 seconds.

## 3. Booking as a trusted app (G4 item 4)

- `POST /api/appointment-types/{id}/bookings` (`appointments:write`), with `Idempotency-Key` (as
  today), and new fields:
  - `external_id` (Stackapella's booking id, unique per connected app);
  - `payment: "external"`: paid elsewhere by the connected app, so ES neither asks for payment nor
    invoices it (G4 item 10), and the booking is confirmed at once (unless the type needs approval);
  - `name`, `email` (the singer's: ES sends its confirmation and reminders, item 5),
    `guest_timezone`, `notes`, `answers`, `slot` (as today).
- **Answers** `201 { "data": { "id", "event_id", "status": "confirmed" | "pending_approval",
  "starts_at", "ends_at", "timezone", "manage_url", "ical_url", "location_url", "external_id" } }`.
  A slot no longer free: `409 { "error": "slot_taken", "alternatives": [...] }`.
- `GET /api/bookings?external_id=&status=&from=&to=` and `GET /api/bookings/{id}`: the same shape.
- `POST /api/bookings/{id}/cancel` (`{ "reason" }`) and `POST /api/bookings/{id}/reschedule`
  (`{ "slot" }`): answer the booking as it is now; both send ES's usual emails and webhooks.

## 4. Webhooks (G4 items 2 and 3)

- **Registered by the connected app** (`webhooks:manage`): `POST /api/webhooks` `{ "url",
  "events": ["appointment.*"], "schedule": "<subdomain>" }` answers `{ "data": { "id", "secret" } }`
  (the secret once); `DELETE /api/webhooks/{id}`.
- **Where they go:** Stackapella registers `https://<stackapella domain>/webhooks/event-schedule/<its
  connection's id>`, one per connected schedule, so each delivery is checked with its own secret.
- **Events:** `appointment.booked`, `appointment.confirmed`, `appointment.rescheduled`,
  `appointment.cancelled`, `appointment.completed`. Body:
  `{ "id": "<delivery id>", "type", "created_at", "data": { "booking": { ...as in 3 },
  "changed_by": "owner" | "guest" | "app", "reason": null } }`.
- **The v2 signature:** header `ES-Signature: t=<unix seconds>,v2=<hex HMAC-SHA256 of "<t>.<raw body>"
  with the webhook's secret>`. Stackapella refuses a signature older than 5 minutes and handles each
  delivery `id` once. Deliveries retry with backoff for a day; the delivery log shows them.
- `appointment.completed` is sent when the owner marks it, or an hour after the end with no
  cancellation.

## 5. Members (G4 item 6)

- `GET /api/schedules/{subdomain}/members` (`members:read`): `[{ "id", "name", "email", "role" }]`,
  paginated by `cursor`. Stackapella shows them to the choir's director to send the group's link;
  it never emails them.

## 6. Events for choirs (exists; scoped)

- `GET /api/events?schedule=<subdomain>&from=&to=` (`events:read`): `[{ "id", "name", "starts_at",
  "ends_at", "timezone", "url", "description" }]`. Stackapella shows a choir's next rehearsals and
  concerts with what to practise.
- **Players on event pages (G4 item 7):** ES renders a Stackapella link in an event's description
  (`https://<stackapella domain>/h/<id>`; `/t/<id>` later, once templates have public pages) as a player, from Stackapella's oEmbed
  endpoint, `GET https://<stackapella domain>/oembed?url=<link>&format=json` (Stackapella's side:
  `{ "type": "rich", "version": "1.0", "title", "provider_name": "Stackapella", "html": "<iframe
  src=\"https://<stackapella domain>/embed/h/<id>\" ...>", "width", "height" }`).

## 7. The rest

- **Mail for integration bookings on hosted** (item 5): ES sends the booking's confirmation and
  reminders from its own sender even without the schedule's mail settings.
- **Embeddable booking pages** (item 8, `?embed=1`) and **the plan rule** for integration use (item
  9) are ES's to decide; Stackapella doesn't depend on them.

## Stackapella's side, for reference

`/connect/event-schedule` (start), `/connect/event-schedule/callback`, `POST
/webhooks/event-schedule/{connection}`, `GET /oembed`, `GET /embed/h/<id>`. The API the app uses is in
`contract/api.md` ("Event Schedule together (M9)").
