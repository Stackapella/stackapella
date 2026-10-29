# Privacy accuracy audit

**Status (2026-10-28): done by the backend agent, before the lawyer sees the privacy policy.** Every
claim in `server/resources/legal/en/privacy.md` (and the data parts of the terms) was checked
against the code by two independent, read-only reviewers. What the code does now is what the
policy says: where they differed, the code was fixed when it was ours to fix, and otherwise the text
was. What's left is for the lawyer, in the policy's `for_lawyer` note.

## What was checked

| Part of the policy | Checked against |
|---|---|
| What we keep, and why | Every migration and model with a person in it |
| Who can see what | The gallery, profiles, share pages, blocks, groups, coaching, choirs, ideas, leagues, the admin |
| The services that handle data | Every outbound call in the server, the web app and the native apps; the CSP; config |
| How long we keep it | Every scheduled purge and prune, every retention constant, the backups |
| Deleting an account | `DeleteAccount`, `PersonalData`, the foreign keys' delete rules |
| Downloading your data | `BuildExport` |
| Cookies and storage | Every cookie the server and the app set; the app's IndexedDB, Cache Storage, localStorage and sessionStorage |
| Emails | Every mailable and notification |
| Children and ages | `Age`, the 18-and-over gates, the sign-up and sign-in paths |
| Keeping it safe | Signed link lifetimes, encrypted columns, the admin's second factor |

## Fixed in the code

| What the policy said | What the code did | Fix |
|---|---|---|
| Under-18s never make anything public, featuring included | Featuring had no age check: an under-18 could say yes, and an admin could post it | `mayFeature: true` refused under 18 (`403 adult_required`); the admin's feature action skips such a harmony (`SocialPostsTest`) |
| Our rate limits never store raw IP addresses | The early-access limiter keyed its cache entry by the raw address | Keyed by the network's HMAC (`InviteLinkTest`) |
| (Failed jobs) | Never pruned, and their payloads name people and things | `prune-failed-jobs`, daily, after 30 days (`FailedJobsTest`) |
| Children answer the age question | An older "13 or over" tick box still worked | Hillel's call: the birth month and year are the only answer (decisions, "the old 13 or over dropped") |

## Fixed in the text

The policy was rewritten where it was wrong or left things out. In short:
- **Cookies:** "one cookie" was wrong. The session and its CSRF cookie are set when the app starts
  for every visitor, and there are six more (a "remember me", a device code for new-sign-in
  emails, the pre-launch pass, and `sa_app`, `sa_theme` and `sa_mudeo`). All are listed, with
  what they're for and how long they last.
- **Processors:** added Apple's and Google's push services for the native apps (APNs and FCM),
  the App Store's and Google Play's purchases, the CDN, Event Schedule as a way to sign in,
  Invoice Ninja's other books, what Stripe, Turnstile, Sentry, X and Bluesky really get, and the
  app (not only the pages) loading Google Fonts.
- **What we keep:** added the devices and apps you're signed in on, Apple's token, sign-in links,
  the look and notification settings, the referral link, the automatic trust level, the checkout
  country and consent, app purchases, disputes, the session's IP and browser, the bell, social
  and community records, coaching bookings and reports, coaches' own records, beta singers, and
  the apps' push tokens.
- **Who can see what:**
  - a share page shows its singers' names and pictures, card and video, and can be embedded;
  - profiles show follow counts, not names, though the person followed (or whose harmony is liked)
    is told;
  - leagues, choirs, ideas, open parts and library songs are shown as they are;
  - blocks apply while signed in;
  - directors also see pictures, join dates and when statuses changed;
  - the admin's changes are logged, not its views.
- **Emails:** added sign-in links, "someone tried to sign up with your address", the new-app
  sign-in, the change of email to the old address, recovered by support, the data download, and
  the one mudeo email.
- **How long we keep it:** added security records (a year), sessions, browsers, app sign-ins,
  one-time links, share videos, unfinished uploads and changed handles. The file backups' "30
  days" became "within six weeks" (the weekly run sees the deletion first). Records with no end
  date in the code (admin changes, reports, our posts, payments) say so, for the lawyer to set.
- **Deleting an account:**
  - comments were never shown as "deleted": they're deleted, with likes;
  - Plus bought in an app is cancelled by the person in the store;
  - shared harmonies with your voice come down too;
  - reports about you go, and yours stay without your name;
  - Event Schedule and Apple are told;
  - invoices already sent keep the name.
- **Your rights:** the download is a file plus links to the recordings, both 7 days. It leaves out
  secrets and our own security records.
- **Children:**
  - under 13 is refused at sign-up with nothing kept;
  - an account made through Google or Apple first is deleted by the app when they say so;
  - every 18-and-over feature is named;
  - "no location" became "no precise location": we keep the time zone, the country you paid from
    and IP addresses.
- **Keeping it safe:** "links that expire within minutes" became "within hours" (the download's,
  7 days). What's shared is at an address nobody can guess.
- **The terms:** backup copies clear "within six weeks", not "a short while", and the director's
  view is described as it is.

## For the lawyer (in the policy's `for_lawyer` note)

- Whether the preference and functional cookies (`sa_app`, `sa_theme`, `sa_mudeo`) and the
  device-code cookie need consent or a banner.
- Whether the description of the automatic trust level is enough, as profiling that decides some
  limits.
- How long payment and tax records, the admin's change log, reports and our posts are kept (the
  code keeps them without an end).
- A deleted account's Stripe customer record and invoices already in Invoice Ninja keep the name.
- An account made by signing in with Google, Apple or a link, whose holder then says they're under
  13, is deleted by the app when they confirm, not by the server on its own. Hillel's decision on
  accounts that later say they're under 13 is open (launch checklist H21).
- The questions already there: the controller, legal bases, transfers, an EU or UK representative,
  the minimum age, the 12 monthly dumps, inactive accounts, the right to complain.

---

[Back: The security review](security-review.md) · [Docs guide](../README.md#a-guide-to-the-docs)
