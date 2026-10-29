# Event Schedule and Stackapella together

**Status:** planned, not started (2026-10-26). Built in M9 (2026-10-27) on Stackapella's side, against a fake; Event Schedule's side waits for its agent.

Part of [backend-plan.md](backend-plan.md), which holds the shared context, dependencies, open
decisions and verification. Its place in the order of work is in [roadmap.md](roadmap.md).

**When:** at launch, in roadmap M9 (it needs no network): live lessons first, then choirs across both
apps.

**What ES has today** (explored in `~/Code/eventschedule`):
- **A REST API:**
  - `X-API-Key`, one key per user, with no scopes;
  - Pro on hosted;
  - `{data, meta}`, Sqids;
  - `openapi.json`;
  - appointment types, slots, and idempotent booking;
  - events with `external_id` and upsert.
- **Signed webhooks:** HMAC SHA-256, set per user, with a delivery log.
- **Appointments:** `AppointmentService`, with DST-aware slots that count calendar-synced busy
  time, secret manage, reschedule and cancel links, emails and iCal.
- **Calendar sync:** Google, Outlook and CalDAV. Stripe Connect payments go to the schedule owner.

**What's missing for a seamless integration:**
- no OAuth (Stackapella would have to hold each coach's all-powerful key);
- no webhook on a cancelled appointment, and no appointment fields in webhooks;
- no API to list, cancel or reschedule bookings, or to book as already paid;
- booking pages can't be embedded;
- no member list (for choirs);
- the timestamp isn't part of the webhook signature (so replays aren't caught);
- on hosted, appointment emails need the schedule's own mail settings.

## G1. Connecting accounts
- **ES becomes an OAuth 2.0 provider** (an ES change):
  - the authorization code flow with PKCE, refresh tokens, and a consent screen;
  - scopes: `profile`, `schedules:read`, `appointments:read`, `appointments:write`,
    `events:read`, `events:write`, `webhooks:manage`;
  - "Connected apps" in ES settings can revoke access.
- **In Stackapella,** Settings, then Connected apps, then **Connect Event Schedule**: a sign-in and
  approve on ES, then pick a schedule. Tokens are stored encrypted, server-side only, and never sent
  to the PWA.
- **Later: "Sign in with Event Schedule"** on Stackapella's sign-in screen, once ES is a
  provider. It's matched on the ES user ID, and never merged by email.
- **Selfhosted ES installs:** connect with an API key pasted into Settings (an advanced option,
  with a warning that the key acts as the whole account), plus the install's base URL.

## G2. Live lessons (coaching)
1. **The coach's setup:**
   - connects ES and picks their talent schedule;
   - picks the appointment types to offer as lessons;
   - availability, buffers, calendar sync and reminders all stay in ES, where they're already
     built.
2. **The singer books in Stackapella, with no hop to another site:**
   - the coach's profile shows the next slots, in the singer's time zone (Stackapella's server
     calls ES's slots API, cached for 30 seconds);
   - the singer picks a time, pays through Stackapella's Connect checkout (F3), and Stackapella
     books it in ES:
     - with `Idempotency-Key`;
     - `external_id` set to the Stackapella booking;
     - and **`payment: external`** (an ES change: paid elsewhere, by a trusted connected app).
3. **After booking:**
   - the lesson appears in the singer's Coaching tab and the coach's dashboard, with the join
     link (the type's `location_url`, a Zoom or Meet link), "Add to calendar" (ES's iCal), and
     **ES's manage link** for rescheduling;
   - ES sends its confirmations and reminders. **An ES change** lets integration bookings send
     mail without the schedule's own mail settings.
4. **Changes flow back by webhook.** An ES change adds `appointment.booked`, `.confirmed`,
   `.rescheduled`, `.cancelled` and `.completed`, with the type, start and end times, time zone,
   `external_id`, and who made the change. Stackapella then:
   - moves or cancels the `lesson_booking`;
   - refunds under the coach's policy (F3);
   - notifies in the app.
5. **Around the lesson:**
   - before it, the singer can attach a take ("what I want to work on");
   - after it, the coach can add notes, voice notes and exercises: the same review workspace,
     attached to the lesson;
   - **the singer rates the lesson** once ES reports it completed, or a day after its end with no
     cancellation.
6. **The reconcile job** (hourly) compares upcoming lessons with ES's bookings API, in case a
   webhook was lost. A mismatch goes to the integration health screen.

`lesson_bookings`: the engagement, the ES connection, ES booking and event IDs, the start, end and
time zone, status, the manage URL, and the payment and refund.

## G3. Choirs and groups (both apps together)
A choir runs its season in ES (a talent schedule, with rehearsals and concerts as events) and its
practice in Stackapella.
- **Practice tracks on an ES event:**
  - in Stackapella, a choir director links a template or project to an ES event ("Spring
    concert: Danny Boy, Amazing Grace");
  - **ES shows "Practise the songs" on the event page,** with each song's player and a "Learn your
    part" link into Stackapella. This is Stackapella's embeddable player (`/embed/h/<sqid>`, the
    only Stackapella page that can be framed) and an oEmbed endpoint. The ES change is to render
    Stackapella links as players.
- **Rehearsals in Stackapella:**
  - a choir group in Stackapella shows the next rehearsals and concerts from the linked ES
    schedule;
  - with them, what to practise: "Rehearsal Thursday: the alto in Danny Boy, 2 phrases to go";
  - it's read through the API (or the public iCal for public schedules).
- **Members:** an ES change adds a members endpoint (with the `members:read` scope). A director can
  invite the schedule's members to the Stackapella group in one step, and assign them parts.
  - Choir groups and assigned parts are the "Share templates privately with a group" Plus feature
    (pricing plan), so this phase builds them.
- **Performers:** a Stackapella profile can show "Upcoming shows" from the linked ES schedule, and
  an ES talent page can show "Harmonies" from Stackapella (public data only, through each app's
  public API).
- **Concert recordings:** later, a harmony can be linked back to the event it was made for.

## G4. The changes in Event Schedule (built in that repo, to its own rules)
1. **OAuth 2.0 provider with scopes** (a dependency decision there: `laravel/passport` or a small
   one of our own), with Connected apps in settings.
2. **Appointment webhooks** with appointment fields, including owner and guest cancellations, and
   **webhooks registered by a connected app** for a schedule.
3. **A v2 webhook signature** that signs the timestamp too (the old one stays for existing users).
4. **A bookings API:**
   - list, get, cancel and reschedule;
   - booking with `external_id`;
   - `payment: external` for trusted apps.
5. **Mail for integration bookings** on hosted.
6. **A members endpoint.**
7. **Rendering Stackapella links** as players (oEmbed) on event pages.
8. **Embeddable booking pages** (`?embed=1`): a fallback for coaches who'd rather link than
   integrate, and for other apps.
9. **The plan rule** for integration use (see Open decisions).
10. **No double invoicing:** a booking marked `payment: external` is never invoiced through ES's own
    Invoice Ninja connection. Stackapella records it
    ([invoice-ninja-integration-plan.md](invoice-ninja-integration-plan.md)).

## G5. Stackapella's side
- **`Services/EventSchedule/EventScheduleClient`:**
  - raw `Http`, with the token refreshed on 401;
  - timeouts and retries for reads only;
  - `Idempotency-Key` on writes.
- **`es_connections`:**
  - the user, the base URL, the ES user ID;
  - encrypted access and refresh tokens, scopes, the schedule;
  - the webhook ID and secret, and its status.
- **`POST /webhooks/event-schedule`:**
  - checks the v2 signature and a 5-minute timestamp window;
  - is idempotent by delivery ID;
  - queues the handling.
- **The readiness check and the health screen:**
  - the connections with failing refreshes or webhooks;
  - the coach is told in the app ("Reconnect Event Schedule"), and their lesson offering is
    paused until they do.
- **Disconnecting:** the token is revoked, upcoming lessons stay booked in ES, and the lesson
  offering is hidden. The singer is told nothing changes for booked lessons.

---

[Back: The coaching marketplace](coaching-marketplace-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Invoice Ninja](invoice-ninja-integration-plan.md)
