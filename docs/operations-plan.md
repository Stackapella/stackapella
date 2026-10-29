# Operations: running Stackapella day to day

**Status:** planned, not started (2026-10-26). Its pieces are placed in [roadmap.md](roadmap.md)
(under "Where each piece goes").

## Context

Every plan adds a queue that lands on Hillel:
- beta reviews, moderation, coach approvals;
- support, refunds, account recovery;
- new songs every month.

No plan said who does the work, how fast, at what cost, or what keeps a signed-out singer's songs
safe. This plan covers:
- support;
- a moderator role;
- Hillel's weekly time;
- keeping songs safe;
- practice reminders;
- device checks;
- security housekeeping.

## Support

- **One way in:** "Contact us" (and Tell Hillel during the beta), from the app and the help pages,
  into the admin inbox. It's protected by the trust and safety limits.
- **Every message carries context automatically** (with the person's OK on the form):
  - the account;
  - the app version, browser and device;
  - the last timing check result;
  - the page they were on.
- **"Send diagnostics"** (on the help pages and after an audio problem): the same details, plus the
  last few audio errors, sent only when the person taps it.
- **Saved replies** in admin for the common cases:
  - the mic is silent;
  - takes are out of time;
  - installing on iPhone;
  - refunds;
  - cancelling;
  - a lost account.
- **Response target:** 2 working days, and 1 for anything about payments or a lost account. The
  daily digest lists messages over the target.
- **Refunds:**
  - in admin (behind sudo, audit-logged), using Stripe;
  - the refund policy (a legal page) sets the rules: for example, a yearly plan refunded in full
    within 14 days;
  - in the EU and UK, the law's withdrawal rules come first ("Legal duties" in
    [trust-and-safety-plan.md](trust-and-safety-plan.md)).

## Getting back into an account

- **Email and password:** the reset email, as planned.
- **Google or Apple, if the person loses access:**
  - another method can be added while signed in (Settings);
  - otherwise, support verifies them with what only the owner knows: a recent Stripe receipt, the
    titles of their projects, and the email on the account;
  - then Hillel adds a sign-in method (behind sudo, audit-logged, and the account's email is told).
- **Apple's Hide My Email:** support can see the relay address on the account.
- **No recovery that bypasses this.** Recovery is how accounts get stolen.

## A moderator role

- **The role:** an **Editor** (as iaw's `EnsureEditor`) can work the queues:
  - reports and held items;
  - beta submissions (listen, ask for another take);
  - song requests;
  - support replies.
- **What an Editor can't do:** money actions, bans, deletes, settings or switches, which stay
  Hillel's, behind sudo.
- **The safeguards:** Editors need the second factor too, and every action is audit-logged.
- **When:** the role is built in M1 with the admin. Hillel decides when to add someone.

## Hillel's week (an honest budget, at launch)

| Work | Rough time a week | Can be shared with |
|---|---|---|
| Support | 2 to 4 hours | An Editor |
| Moderation (reports, holds, DMCA) | 1 to 2 hours | An Editor |
| Beta reviews (real voices) | 2 to 3 hours | An Editor (listening and asking); accepting stays Hillel's |
| New songs (2 a month: Claude drafts, the musician checks, singers record) | 2 to 3 hours of coordination | The musician |
| Outreach (`docs/go-to-market-plan.md`) | 3 to 5 hours | |
| Coach approvals and disputes (from M8) | 1 to 2 hours | |

The digest sorts each day's queue by urgency, so ten minutes each morning keeps it moving.

## Keeping songs safe before signing in

"An account is never needed to record" means a signed-out person's songs live only in the browser.
**Safari clears a website's storage after about 7 days without a visit, unless the app is
installed.** So:
- **`navigator.storage.persist()`** is requested on the first recording (the conventions already
  plan it), so other browsers keep the songs;
- **after the first recorded part, a gentle card:**
  - "Your songs are only on this phone. Install the app, or save them to a free account, so they're
    safe";
  - it has **Install** (the install card, `docs/go-to-market-plan.md`) and **Save to an account**;
  - it's shown again, at most once a week, while songs are unsaved;
- **the projects page marks unsaved songs** "On this device only", as the pricing plan already
  does for songs over the limit.

## Practice reminders (web push, opt-in, at launch)

Hillel's call (2026-10-26). This is the one reminder Stackapella sends, and never by email.
- **Offered once,** after the person sets a weekly goal (`docs/vocal-coaching-plan.md`): "Remind me
  on my practice days?" with a time.
- **At most one a day,** only on the days they chose. Kind wording: "Ready for 5 minutes of
  singing?", never "You'll lose your streak".
- **Off with one tap,** in Settings or from the notification.
- **No user text in a notification.**
- **On iPhone,** web push works only in the installed app (iOS 16.4 and later), which is one more
  reason for the install card.
- **How it works:**
  - the Push API, with the service worker showing the notification;
  - the server keeps the subscription, the chosen days and the time zone;
  - a scheduled job sends due reminders every 15 minutes, through the Web Push protocol (VAPID
    keys in `config/services.php`).
- **One new dependency** (needs Hillel's OK): `minishlink/web-push` (composer, MIT), for Web Push's
  payload encryption, which is security code best not hand-rolled.

## Devices: checked at every milestone

- **M0** gains **interruptions:**
  - a phone call during a take;
  - AirPods connecting or disconnecting;
  - the screen locking;
  - the app going to the background;
  - another app taking the audio.

  **The take is kept up to the interruption,** with "Recording stopped: your take is saved up to
  0:42", and the timing check is offered again when the output device changes.
- **Every milestone after M0** ends with the same short device pass:
  - an iPhone in Safari and installed;
  - Android Chrome;
  - Safari and Firefox on a Mac;
  - Chrome on Windows.

  It's a checklist in the milestone's exit check.

## Plus songs offline

- **A Plus song downloaded on a Plus account stays usable offline.**
- **If the subscription lapses while offline,** the app keeps Plus songs for **14 days** from the
  last check, then they become read-only until it's online again.

## Security housekeeping

- **`/.well-known/security.txt`** with a contact and a short disclosure policy (thanks in a hall of
  fame; no bounty at first).
- **A security review before launch (L),** of:
  - the trust and safety plan's abuse test;
  - the OWASP top ten against our routes;
  - the CSP;
  - the presigned uploads;
  - the admin.
- **Secrets:** a list (not the values) of every secret and where it lives (the App Platform
  console, Stripe, Cloudflare, Apple, VAPID), with how to rotate each, in `docs/` before launch.
- **Updates:**
  - `composer audit` in CI (planned);
  - the weekly `outdated.yml` for the CDN libraries and Tailwind;
  - a monthly pass by Hillel.
- **If Hillel is unavailable:** the accounts (DigitalOcean, Stripe, Cloudflare, Apple, the domain)
  have their recovery codes stored safely, and the runbooks are in `docs/`.

## Where each piece goes

| Milestone | Backend | Frontend |
|---|---|---|
| M0 | | The interruptions checklist |
| M1 | The Editor role, with the admin | |
| M3 | Account recovery support, adding sign-in methods | "Keeping songs safe": the card, `persist()`, "On this device only" |
| M5 | **Practice reminders:** subscriptions, the job, VAPID | The opt-in after the weekly goal; the service worker's notification |
| M5 | The 14-day offline grace for Plus songs | The same, in the app |
| L | The support inbox's context and saved replies; diagnostics; refunds in admin; `security.txt`; the security review; the secrets list; cost alerts | "Send diagnostics"; the help pages |
| Every milestone | | The device pass |

## Review

**Missed before, now covered:**
- **Who answers support,** how fast, and how refunds and lost accounts are handled.
- **One person's capacity:** the Editor role, and an honest budget.
- **Songs lost to Safari** before signing in.
- **Whether $4.99 pays its way:** fees, running costs, break-even, and when to raise the price.
- **People coming back without email:** opt-in practice reminders.
- **Audio interruptions,** and devices beyond M0.
- **Plus offline, security contact, secrets, continuity.**

---

[Back: Trust and safety](trust-and-safety-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Becoming a better singer](vocal-coaching-plan.md)
