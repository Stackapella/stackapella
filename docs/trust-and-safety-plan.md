# Trust and safety: spam and abuse, handled from day one

**Status:** planned, not started (2026-10-26). Each piece is placed in a milestone of
[roadmap.md](roadmap.md) (under "Where each piece goes"), so the launch is protected without a
scramble afterwards.

## Context

The plans had a few protections spread across them:
- a honeypot on sign-up;
- named rate limiters on sign-in and uploads;
- a word list for comments (for the gallery);
- reports and suspension;
- XP caps for leagues.

Read together, that isn't enough to launch with. Anything that's free and lets people upload, share
or send something gets abused within days:
- bots making accounts;
- our emails sent to strangers;
- free storage used as a file host;
- share links used for spam or pirated music;
- stolen cards tested on our checkout;
- expensive jobs (video renders) run up as a bill.

This plan covers every surface:
- **at launch:** accounts, email, uploads, share links, videos, payments and the API;
- **at launch, too:** the marketplace (without its directory), choir groups and collaboration;
- **when the network features open:** the gallery, comments, leagues and requests.

It also gives Hillel the controls to act in minutes.

**The principles** (they follow the vision):
1. **Built in from the first line, not bolted on.** Every endpoint has a named limit, and every
   piece of user text has a rule, before it ships.
2. **Real singers never notice.** Limits sit well above what a person singing does. A singer is
   recognised by what they do (practice, recorded parts), which bots don't fake cheaply.
3. **No analytics, no tracking.** Signals come from our own records:
   - account age, verified email, practice;
   - rate-limit hits, reports, payments.

   There's no device fingerprinting and no third-party trackers. IP addresses are kept short-term,
   for abuse only.
4. **Hillel can act in minutes:** one screen, one-click actions, and switches that stop a whole
   feature during an attack. Every action is audit-logged.
5. **Kind to people, firm with abuse.** A person who hits a limit gets plain words and a time
   ("You can share again in 20 minutes"), never a silent failure.

## What we're protecting against

| Threat | Where | When |
|---|---|---|
| Bot sign-ups (farming free storage, posting spam) | Accounts | Launch |
| **Email bombing:** sign-ups or resets with someone else's address, so we email them | Accounts, email | Launch |
| Credential stuffing and password guessing | Sign-in | Launch |
| Free storage used as a file host | Uploads | Launch |
| **Share links carrying spam, offensive audio, pirated music or scam text** | Sharing | Launch |
| Running up costs (video renders, uploads, exports) | Jobs | Launch |
| Card testing (bots trying stolen cards), chargebacks, repeated trials | Billing | Launch |
| Scraping the Plus library through one paid account | The API | Launch |
| Handles or profiles impersonating Stackapella or others; offensive names | Accounts | Launch |
| Spam through the contact form, Tell Hillel, reports | Forms | Launch |
| An admin account taken over | Admin | Launch |
| Comment spam, follow and like farming, fake engagement | The gallery | When it opens |
| Fake coaches, fake reviews, off-platform payment scams | The marketplace | Launch (M8) |
| Cheating on leaderboards | Leagues | When they open |
| Request and vote spam | Song requests | When they open |

## Trust levels (from our own records)

**Every account has a level, computed by `Support/Trust/TrustLevel` and shown to Hillel in admin.**
Limits and permissions read it. It goes up by itself and never needs the person to do anything
unusual.

| Level | Reached by | Can |
|---|---|---|
| **0: New** | Signing up with email and password, not yet verified | Learn and record on the device. No cloud save, no sharing, no Checkout |
| **1: Verified** | Verifying the email, or signing in with Google or Apple (they verify) | Cloud save, Checkout, **share links at low limits** |
| **2: Singer** | Verified, the account at least 3 days old, **and** real singing: 30 minutes of practice or 2 recorded parts (from `account.learning` and takes) | Normal limits. After launch: publish publicly (the first two still reviewed), comment, follow |
| **3: Trusted** | A paid Plus subscription that's been charged once without dispute, or set by Hillel | Higher limits. Once the gallery opens: no review hold |

- **Going down:**
  - an upheld report drops an account to level 1 for 30 days;
  - a chargeback drops it to 0;
  - Hillel can set any level, with a note.
- **Levels are never shown to the person.** They see only the limit they hit, in plain words.

## The limits (proposed numbers, all in one place)

**How they work:**
- they're named limiters in `AppServiceProvider` (the conventions);
- **the client's real IP address comes first.** Behind Cloudflare and App Platform's load
  balancer, `$request->ip()` would otherwise be a proxy's, and every visitor would share one limit.
  So `bootstrap/app.php` trusts only the known proxies:
  - when App Platform's `do-connecting-ip` is in Cloudflare's published ranges, the client is
    `CF-Connecting-IP`;
  - otherwise it's `do-connecting-ip` itself (a request that skipped Cloudflare);
  - the Cloudflare ranges are kept in config and refreshed by a scheduled check;
  - direct requests to the app's `ondigitalocean.app` address redirect to the real domain;
  - **a feature test proves the limiters key off the real client;**
- they're keyed by the user, and by an HMAC of the IP address and the date (ES's pattern), so raw
  IPs aren't kept in the cache;
- the defaults are in `config/limits.php`, and **Hillel can change any of them live in admin**
  (stored as overrides in a `settings` table), without a deploy;
- a refusal is a 429 with `Retry-After` and the stable code `rate_limited`, and the app says when
  to try again.

| Action | Limit (per account unless noted) | Notes |
|---|---|---|
| Sign up | 5 an hour and 20 a day per IP | Honeypot, plus a form filled in under 2 seconds counts as a bot |
| Verification email | 1 a minute, 3 an hour and 6 a day per address | **"Resend" waits 60 seconds;** an unverified address gets nothing else from us |
| Sign in | 10 a minute per IP and address together, 30 a minute per IP | After 10 failures for one account, a 15-minute wait for that account (any IP) |
| Password reset | 3 an hour per address, 10 an hour per IP | The same reply whether or not the address exists |
| Email change | 3 a day | The old address is told, with "This wasn't me" |
| Take uploads | 60 an hour, 400 a day | Plus the storage allowance and a 10-minute take cap |
| Uploaded bytes | 2 GB a day (free), 10 GB a day (Plus) | Stops a run on storage before the allowance does |
| Share (publish unlisted) | Level 1: 3 a day. Level 2: 20 a day. Level 3: 50 a day | |
| Share videos | 3 an hour and 10 a day; **2 renders at once in total** across everyone | The global cap protects the bill |
| Plus songs downloaded | 30 a day | Above that, flagged for Hillel (scraping) |
| Checkout sessions | 5 an hour | Card testing |
| Data export | 3 an hour (exists) | |
| Reports | 10 an hour | Reports against your own content are ignored |
| Contact, Tell Hillel | 5 an hour per IP | Honeypot |
| The API overall | 300 requests a minute per account, 60 per IP signed out | A backstop |
| **When the gallery opens:** comments | Level 2: 10 an hour; level 3: 30 an hour | Plus the text rules |
| **When the gallery opens:** follows, likes | 50 and 200 an hour | Duplicates are no-ops |
| **When requests open:** song requests | 3 a day; one vote per request | |
| Coach messages (M8) | 30 an hour per thread | |

## Rules for anything people type

These apply to titles, descriptions, handles, names, bios, comments and requests:
- **No links at launch.** URLs in user text aren't turned into links, and titles and handles refuse
  them ("Links aren't allowed in titles"). When the gallery opens, bios at level 2 and above may have one
  link, rendered `rel="nofollow ugc"`.
- **The text filter** (`Support/Moderation/TextFilter`, after ES `SpamWatch`) holds text for review
  when it matches:
  - a word list (slurs, scams, spam phrases);
  - phone numbers or emails (in marketplace messages it warns instead);
  - repeated characters;
  - many links.
- **Handles:**
  - reserved words are refused: "stackapella", "admin", "support", "official", "moderator",
    "hillel", and the names of coaches and partners;
  - so are near-copies of reserved words (character swaps, accents, and confusable letters like
    "0" for "o");
  - so is the offensive word list.
  - A deleted account's handle is held (the backend plan).
- **Plain text everywhere,** shown with `{{ }}` and never `v-html` (the conventions), with lengths
  capped (title 80, description 300, bio 300, comment 500).
- **No user text in our emails.** Emails never carry text that someone else wrote, so our address
  can't be used to send spam or phishing. Invites are links people send themselves.

## Uploads and shared audio

- **Every upload is checked:**
  - the presigned URL is signed for the **exact `Content-Length`** and content type, so it can't be
    used for a bigger file;
  - `complete` checks the size and SHA-256;
  - ffmpeg probes the file, so anything that isn't the audio it claims to be is refused;
  - takes are capped at 10 minutes.
- **Takes are private** (`temporaryUrl()` only), so storage can't serve files to the public.
- **A shared harmony must look like one made in the app.** The server checks, before
  `ProcessHarmony` runs:
  - the project has recorded takes;
  - the mix's length matches the project's (plus the reverb tail);
  - it's within the length cap.
  - **Later,** a cheap check that the mix correlates with the sum of the stored takes.
- **Shared pages:**
  - `noindex` while unlisted;
  - a **Report** button on every page, with no account needed (with a honeypot and the IP limit);
  - **3 reports from different people hide the page until Hillel looks** (proposed);
  - a DMCA notice hides it at once (the copyright policy).
- **Free storage abuse:**
  - the 1 GB allowance;
  - the daily byte cap;
  - level 0 can't upload at all;
  - inactive free accounts' recordings removed after 12 months, with an email first (pricing
    decision #8, proposed).

## Payments

- **Stripe Radar** on every charge (Checkout includes it), with rules:
  - block when the card's country and the IP's country differ and the card is prepaid;
  - review a high risk score.
- **One trial per card**, in two steps, because Stripe only reveals the card after Checkout:
  1. **before Checkout:**
     - an account that has had a trial gets no trial;
     - **a trial without a card** (if the open decision allows it) needs trust level 2 (Singer),
       so bots can't farm trials without singing;
  2. **after Checkout** (`customer.subscription.created`):
     - the server reads the payment method's card fingerprint (from Stripe, never the number);
     - **if that card has had a trial before,** the new trial is cancelled at once with no charge,
       and the app says "This card has already had a free trial. Subscribe without the trial?";
     - fingerprints that have had trials are kept in `trial_fingerprints`.
- **Card testing:**
  - the Checkout limit;
  - Checkout only for verified accounts (level 1);
  - an alert when many payments fail in an hour.
- **Chargebacks:**
  - the account drops to level 0;
  - Plus ends;
  - the case appears in admin.

  Stripe's dispute process is followed (backend plan D8).
- **In the marketplace (M8):**
  - Stripe holds a coach's first payouts for 7 days;
  - **self-dealing is flagged:** a buyer whose card fingerprint, or whose IP hash in the last 30
    days, matches the coach's own account;
  - ratings only come from distinct paying accounts;
  - **off-platform payment** asks in messages (emails, phone numbers, payment app names) show the
    singer a warning and flag the thread.

## Admin controls (in `/admin`, behind the second factor)

### The trust and safety screen
- **One queue:**
  - reports;
  - held text and pages;
  - flagged accounts;
  - DMCA notices;
  - payment disputes.

  Oldest first, with keyboard shortcuts.
- **Each item offers:**
  - **Hide** or **Restore** the content;
  - **Dismiss**;
  - **Warn** (an in-app notice to the person);
  - **Limit** (set level 0 or 1);
  - **Suspend** (sign out everywhere, hide everything they've shared, keep their data);
  - **Ban** (suspend, plus add their email, domain, IP or card fingerprint to the blocklist);
  - **Delete** (behind sudo).
- **Flagged automatically:**
  - many sign-ups from one IP hash in an hour;
  - an account hitting limits repeatedly;
  - 3 or more reports;
  - Plus song downloads over the cap;
  - failed payments in a burst;
  - self-dealing in the marketplace.

### A person's page
It shows:
- their trust level (with "Set level" and a note), sign-in methods, and account age;
- activity counts (practice, parts, shares, reports made and received);
- IP hashes from the last 30 days;
- **accounts linked by the same IP hash or card fingerprint;**
- payments and disputes;
- every admin action taken on them (the audit log).

### The blocklist (after ES `Blocklist`)
- **What it holds:** emails, email domains, IP addresses and ranges, and card fingerprints, each
  with a reason and an optional expiry.
- **Where it's checked:** sign-up, sign-in, Checkout, and the contact and report forms.
- **Disposable email domains:** a list kept in the repo (`config/disposable-domains.php`, updated
  by hand). Signing up with one is allowed, but stays at level 0 until the account signs in with
  Google or Apple (proposed).

### Switches for an attack (feature flags, behind sudo, audited, with a reason)

| Switch | Effect |
|---|---|
| **Shield mode** | Turns on **Cloudflare Turnstile** on sign-up, sign-in, reset, share, contact and report. Built and tested from M3 and off by default, which keeps the earlier decision "Turnstile only if abused" but makes it one click |
| Pause sign-ups | All, or email and password only (Google and Apple still work) |
| Pause sharing | No new share links; existing ones keep working |
| Pause share videos | Renders queue but don't start |
| Pause uploads | Takes stay safe on the device and upload later |
| Pause comments, follows, requests, messages | Each on its own |
| Read-only | Everything still plays; nothing new is saved (for an incident) |

### Alerts (email to Hillel at once, and the daily digest)
- Sign-ups an hour over a set number (proposed 50).
- Reports an hour over 10.
- The video queue over 20 waiting.
- **The email bounce or complaint rate** (Cloudflare Email Service) over 2% or 0.1%, or the
  daily sending quota over 70%.
- Failed payments over 10 an hour.
- Storage growth over 10 GB a day.
- Limiter refusals over 1,000 an hour.

Each alert links to the screen where it's handled.

## Accounts and admin, hardened

- **Sign-in:**
  - no account enumeration: sign-up, reset and sign-in give the same answers whether an address
    exists or not;
  - passwords at least 10 characters, with the 10,000 most common refused (a list in the repo; no
    outside call);
  - sessions rotate on sign-in; "sign out everywhere" (the backend plan).
- **Email:**
  - DKIM, SPF and DMARC on the sending domain;
  - Cloudflare suppresses bounced and complaining addresses at once, and our records follow
    (backend plan D9);
  - **a new sign-in from a new browser** sends a short email about the account (it's about
    something the person did, so it's allowed; proposed).
- **Admin:**
  - passkeys first, then TOTP;
  - sudo for anything destructive;
  - the admin session bound to the browser;
  - **admin sign-ins alert Hillel** (iaw `AdminSignedIn`);
  - no admin from a remember-me restore (all in backend plan A2).
- **The edge (proposed, open to veto): Cloudflare in front of the app** (the free plan, as iaw
  uses). It brings DDoS protection, bot filtering, and edge rate limits on sign-up and sign-in.
  Presigned uploads go straight to Spaces, so they don't pass through it.

## Legal duties for content, accounts and payments

The lawyer confirms each one before launch; this list makes sure none is forgotten.
- **The EU Digital Services Act** (we host people's audio through share links):
  - a way for anyone to report illegal content (the Report button, without an account);
  - **a statement of reasons:** when we hide or remove something, or limit or suspend an account,
    the person is told in the app (and by email if suspended) what was done, why, and under which
    rule;
  - **an appeal:** "Ask for a review" on that notice, answered by a person (Hillel or an Editor),
    with the result recorded;
  - a public point of contact (the contact page) and the terms explaining moderation in plain words.
- **Children:**
  - the age gate (13 and over, backend plan B);
  - **for under-18 accounts** (the UK Children's Code), private-by-default settings: no public
    profile, no public leagues (already planned), share links only, and no location;
  - coaching is 18 and over.
- **Consumer rules at checkout** (`docs/pricing-plan.md`):
  - **the EU and UK 14-day right of withdrawal:** Checkout asks for consent to start Plus at once,
    and says what that means for refunds;
  - **auto-renewal disclosures** (California and others): the price, the renewal, and how to
    cancel, shown before buying, with cancelling online in the billing portal;
  - **prices include tax** where the law expects it.
- **Accessibility as law:** the European Accessibility Act covers paid consumer apps sold in the
  EU. Our WCAG 2.2 AA target meets it. **An accessibility statement** is published with the legal
  pages, and the L accessibility pass is a legal check, not just a nice one.
- **Cookies:** only the session cookie, which is necessary, so no consent banner. The privacy
  policy says so, and that Turnstile runs only in shield mode.
- **What people may do with their recordings:** they own them and may share and monetise them,
  crediting "Arranged by Stackapella" (`docs/go-to-market-plan.md`). The terms say so.

## Privacy, kept

- **IP addresses:**
  - raw IPs are stored only in `security_events` (sign-ups, sign-ins, shares, reports, payments),
    for **30 days**, for investigating abuse;
  - after that only an HMAC is kept;
  - the limiters never store raw IPs.
- **No device fingerprinting,** no third-party scripts. Turnstile runs only in shield mode, and the
  privacy policy names it and Cloudflare.
- **Card fingerprints** come from Stripe and are never card details.
- **The privacy policy** lists what's kept for abuse, and for how long.

## The playbook (a runbook in `docs/` before launch)

| What Hillel sees | What to do |
|---|---|
| A spike of sign-ups from a few IPs | Shield mode on; ban the IPs; check the new accounts' linked page; clear them in bulk |
| Many reports on one shared page | It's already hidden at 3. Look, then Restore or Ban |
| Someone's verification emails bounce, or a complaint | Cloudflare has suppressed it; check the account; block the domain if it's a pattern |
| The video queue is full | Pause share videos; check who's rendering; limit them |
| A burst of failed payments | It's card testing: pause Checkout for level 1, turn on shield mode, check Radar |
| A DMCA notice | The page is already hidden; follow the copyright policy (counter-notice, repeat infringers) |
| One account downloading the Plus library | Check the flag; limit them, or end Plus and refund |

## Where each piece goes (roadmap)

| Milestone | Backend | Frontend |
|---|---|---|
| **M1** | **The real client IP behind Cloudflare and App Platform (trusted proxies, with a test)**; named limiters keyed by user and IP HMAC; `config/limits.php` with live overrides; `security_events`; the audit log (exists); feature flags with the switches; the blocklist; the trust and safety screen's frame; Cloudflare (if approved) | The plain message for any `rate_limited` reply, with its time |
| **M2** | The beta's limits (already planned) | |
| **M3** | Sign-up, sign-in, reset and email change protections; verification caps; no enumeration; the honeypot and timing check; the common-password list; disposable domains; handle rules; **trust levels 0 and 1**; **Turnstile built, behind shield mode**; new-sign-in emails | The forms' honeypot, the timing check, the resend wait, and Turnstile behind the flag |
| **M4** | Upload limits; the exact `Content-Length`; byte caps; level 0 can't upload; **trust level 2** | Uploads that wait politely when limited |
| **M5** | Radar rules; one trial per card; the Checkout limit; chargebacks; the Plus download cap and flag; **trust level 3** | Checkout only when verified |
| **M6** | Share limits by level; the mix check; `noindex`; Report without an account; hiding at 3 reports; DMCA hide; video limits with the global cap; the text filter for titles | The Report button; the limit messages; refusing links in titles |
| **M8** | The marketplace's protections: payout holds, self-dealing flags, ratings only from distinct payers, the off-platform warning | |
| **L** | Alerts; the digest's signals; **the runbook**; the privacy policy's abuse section; **an abuse test** (a script that tries each attack above against the beta domain); a review of every limit | |
| **When the network features open** | The gallery's rules (comments, follows, likes; trust-0 likes left out of rankings; the first public items held); the marketplace's protections; leagues' XP checks; requests' limits | Their messages and Report buttons |

## Review (misses, UX, code fit)

**Missed before, now covered:**
- **Email bombing** through sign-ups and resets: per-address caps, and nothing sent to an
  unverified address except one verification.
- **Free storage as a file host:** private takes, byte caps, level 0 can't upload, and shared
  harmonies checked as real.
- **Share links** carrying spam or piracy: limits by level, no links in text, `noindex`, hiding at
  3 reports, and DMCA.
- **Video renders running up the bill:** a global cap of 2 at once.
- **Card testing and repeated trials:** Radar, one trial per card fingerprint, and the Checkout
  limit.
- **Scraping the Plus library:** a daily download cap and a flag.
- **Impersonating handles:** reserved names, confusable letters.
- **Our emails carrying someone else's text:** never.
- **Limits that need a deploy to change:** live overrides in admin.
- **"Turnstile only if abused"** meant building it during an attack: now built from M3, off until
  needed.
- **Raw IPs kept forever:** 30 days, then a hash.

**UX for real singers:**
- **Limits are far above real use.** A singer records a few takes an hour and shares a few songs a
  week.
- **Every refusal says when to try again.**
- **Practice is what builds trust,** so singing naturally lifts every limit.
- **No captcha unless shield mode is on.**

**Code fit:**
- **It follows the conventions:** named limiters, the stable `code` on refusals, FormRequests with
  the honeypot, Actions, and the audit log.
- **It reuses patterns from:**
  - ES: `Blocklist`, `SpamWatch`, HMAC-of-IP limiter keys, `AccountBlockService`;
  - iaw: `ReportController`, Moderation Actions, `AdminSignedIn`, the digest, feature flags.
- **No new dependency.** The common-password and disposable-domain lists are data files, and
  Turnstile is an HTTP call. Cloudflare is a service decision for Hillel.

## Verification
- **PHPUnit:**
  - every named limiter refuses at its line with `rate_limited` and `Retry-After`;
  - a live override changes a limit;
  - trust levels move up and down by their rules;
  - level 0 can't upload, share or check out;
  - sign-up, sign-in and reset give the same answer for unknown addresses;
  - verification emails stop at their caps;
  - a presigned URL refuses a bigger file;
  - a shared harmony whose length doesn't match its project is refused;
  - the third report hides a page;
  - a reserved or confusable handle is refused;
  - a link in a title is refused;
  - the blocklist stops sign-up and Checkout;
  - the trial is refused for a card fingerprint seen before;
  - each switch stops its feature, and is audit-logged;
  - raw IPs are gone after 30 days.
- **Web tests:**
  - the `rate_limited` message shows its time;
  - the honeypot and timing check stop a scripted form;
  - Turnstile appears only in shield mode.
- **The abuse test (L),** a script against the beta domain, tries:
  - 50 sign-ups from one IP;
  - 20 resets for one address;
  - 100 sign-in guesses;
  - uploading a 20-minute file;
  - sharing 10 harmonies from a new account;
  - 20 video renders;
  - 10 Checkout attempts with Stripe's test cards that decline.

  Each should stop where this plan says, and show up in the trust and safety screen.
- **By Hillel:** turn each switch on and off on the beta domain, and work through the playbook once.

---

[Back: The backend](backend-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Operations](operations-plan.md)
