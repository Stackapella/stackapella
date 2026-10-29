# Launch checklist: go or no-go, 30 November

**Status (2026-10-28): written by the backend agent.** Each line is either done, or says exactly
what's missing and who does it. The code side is ours. Hillel's side is accounts, keys, legal and
drills, each with its exact step. The day itself is at the end. This replaces nothing: the
runbook's "The launch checklist" is the short version of the same list, and `contract/log.md`'s
"Needs Hillel" has the history behind each item.

## The code (agents)

| # | Item | Status |
|---|---|---|
| 1 | Server tests, Pint and PHPStan green on `main`; the web's headless tests green | **Done**, and gated: every server landing runs the server's three first, and the frontend agent's landings run the web's |
| 2 | The pre-launch security review | **Done** ([security-review.md](security-review.md)): 9 findings fixed with tests, and the JSON-LD escaping (3e6a793d) |
| 3 | Performance and load | **Done locally** ([performance.md](performance.md)): every threshold passed; the sync page, gallery and bin fixed. **Missing:** the load test on the beta (Hillel, H22: it runs against real infrastructure), its numbers written under "Results" |
| 4 | Readiness checks (`php artisan app:doctor`) | **Done**: every check exists, with its fix. Run on production on the day (Hillel's side, H1) |
| 5 | Backups and the restore drill | **Done**: daily backup, monthly drill, both scheduled and in the doctor; the drill's flaky test is fixed. Run once on production before go (H2) |
| 6 | Error reporting (Sentry), scrubbed of people | **Done** in code (`ScrubEvent`, the browser tunnel). **Missing:** the DSNs (H6) |
| 7 | The launch domain, redirects, the beta kept as staging | **Done** in code (`APP_URL`, `PUBLIC_URL`, `APP_REDIRECT_HOSTS`, the canonical-host redirect). Set on the day (D2) |
| 8 | Coming soon off, search engines on | **Done**: `COMING_SOON` and `SITE_INDEXING`, two settings. Flipped on the day (D3) |
| 9 | Plus through Stripe; coaching through Stripe Connect | **Done** and tested in Stripe's test mode. **Missing:** live keys and webhooks (H7) |
| 10 | Plus bought in the apps (App Store, Google Play) | **Done**: notifications verified (Apple's chain, Google's Pub/Sub token). **Missing:** the keys (H8) |
| 11 | Email | **Done**: Cloudflare Email Service sends; account mail goes to each account's own address. **Missing:** the bounce Worker (H9); the mudeo email's own domain (H10) |
| 12 | Push to the native apps | **Done**, off until `APP_PUSH_ENABLED=true`. **Missing:** the APNs and FCM keys (H11); the Android FCM package (needs Hillel's OK: a new dependency, mobile's side) |
| 13 | Practice reminders (web push) | **Done**: production's VAPID keys are set |
| 14 | Real instruments | **Done**: 11 CC0 sets built (`brushes` and `drums` at version 2), each checked by `app:instruments:upload --check`. Off until `INSTRUMENTS_ENABLED=true`. **Missing:** uploading them (H12) |
| 15 | The landing's film | **Done**: `app:showreel:upload`, the render checked (17 files); its address comes from `CDN_URL`, no setting. **Missing:** the upload (H13) |
| 16 | Compact peaks (`peaks8`) | **Done** on the server and the web; the app's switch (mobile 9fda19b) ships in the coordinator's next mobile push, with the server's drop of the numbers |
| 17 | Open source, prepared | **Done**: the export script, `CONTRIBUTING.md`, `SECURITY.md`, `TRADEMARK.md`, `THIRD-PARTY-NOTICES.md`, the self-host settings (`SELF_HOSTED`), the pages behind the `open_source` flag. The AAL credit is in the public footer (9eff3b52) and the app's Settings. **Missing:** the clearsigned `LICENSE.asc` (H16); everything in [open-source-plan.md](open-source-plan.md) 6 on its own day |
| 18 | The scheduled jobs | **Done**: each in the runbook's table, with its alert; `app:check-cloudflare-ranges` runs only behind Cloudflare |
| 19 | No third-party analytics or tracking | **Done**: anonymous first-party daily counts only (`counts.create`); the only outside loads are jsDelivr (pinned, SRI), Google Fonts, and Turnstile in shield mode |
| 20 | The abuse test (`server/tool/abuse-test.php`) | **Built.** **Missing:** a run against the beta (H15) |
| 21 | The Operations and Legal duties audit (roadmap L) | **Done on the backend** ([launch-open-gaps.md](launch-open-gaps.md)): Plus refunds, saved replies, security.txt, the secrets list, notices and appeals; the legal texts say what the app does (the terms cover open parts, the virtual choir and Ideas being 18+; the accessibility statement's timing fallback and captions); the network panel counts only opt-ins that can list, says its numbers in the digest, and counts N5 and N6 before they open. **Missing:** the frontend's screens (item 22); the scope-cut check (backend); a refund ending Plus (H24) |
| 22 | The admin screens still to build (frontend) | The People screen's suspend, recover, trust and refund (the API is ready); the appeals queue and its decide screen; the `/notice?token=` page, and the in-app notices with "Ask for a review"; the network panel (`admin.network.show`) and the six network flags' labels in Switches; the share sheet sending `inGallery`; Send diagnostics; the legal links in Settings. **Open** |

## Hillel's side

H1, H2, H9 and H15 step by step, with what to expect: [launch-dry-runs.md](launch-dry-runs.md).

Each step names where it's done, and the setting it ends in. Secrets go in App Platform's
encrypted environment, never in the repo.

| # | What | The exact step | Status |
|---|---|---|---|
| H1 | The readiness checks pass on production | In App Platform's console: `php artisan app:doctor`. No FAIL; each WARN explained | Open |
| H2 | A backup and the restore drill on production | `php artisan app:backup:run`, then `php artisan app:backup:drill`; the doctor shows both | Open |
| H3 | `SQIDS_ALPHABET` isn't the default | Check it's set in App Platform (it is listed as a console secret); never change it once links are out | Confirm |
| H4 | The private bucket allows the launch domain | Spaces, `stackapella-private`, CORS: add `https://stackapella.com` for GET, HEAD and PUT, as for the beta (item 21) | Open |
| H5 | Cost alerts | DigitalOcean billing alert, and the app's and database's alerts (`docs/runbook.md`, "Cost alerts") | Check |
| H6 | Sentry | Make the project; set `SENTRY_LARAVEL_DSN` and `SENTRY_BROWSER_DSN`; throw a test error and see it arrive with nothing personal in it | Open |
| H7 | Stripe live | Step by step, with the test-mode dry run first and exactly what to paste: [stripe-live.md](stripe-live.md) | Open |
| H8 | The stores | App Store Connect: an In-App Purchase key (`APP_STORE_ISSUER_ID`, `APP_STORE_KEY_ID`, `APP_STORE_PRIVATE_KEY`) and Server Notifications to `/webhooks/app-store`. Google Cloud: a service account with Play's financial access (`GOOGLE_PLAY_SERVICE_ACCOUNT`, `GOOGLE_PLAY_PACKAGE`) and a Pub/Sub push subscription to `/webhooks/google-play` with authentication (`GOOGLE_PLAY_PUSH_AUDIENCE`, `GOOGLE_PLAY_PUSH_EMAIL`) | Open |
| H9 | Bounces | Deploy the bounce Worker; set `MAIL_WEBHOOK_SECRET` on both sides; the doctor's "Bounce webhook" passes | Open |
| H10 | The mudeo email | Sent from hillel@stackapella.com (no domain of its own; SPF and DKIM already cover it, checked 2026-10-29). In admin, Mudeo: Send a test email, check it arrives and isn't in spam, then send the batches | Open |
| H11 | Push to the apps | An APNs key (`APNS_KEY_ID`, `APNS_TEAM_ID`, `APNS_PRIVATE_KEY`, `APNS_TOPIC`); a Firebase project and service account (`FCM_PROJECT_ID`, `FCM_SERVICE_ACCOUNT`); then `APP_PUSH_ENABLED=true` | Open |
| H12 | The instruments | The runbook's "The 11 sets, step by step": the MVP's four first (piano, upright bass, drums, nylon guitar), then the other seven, each with one command; then `INSTRUMENTS_ENABLED=true`. Until both, the apps play the synth | Open |
| H13 | The film | `php artisan app:showreel:upload --from ~/Code/stackapella-showreel/server/public/videos/showreel`; deploy (no setting: the film's address is `CDN_URL/showreel`) | Open |
| H14 | The lawyer | Terms, privacy (naming Apple's and Google's push services), refunds, community guidelines, copyright (with the DMCA agent), the coach agreement, the marketplace terms, accessibility, the security policy, `CONTENT-LICENSE`, `TRADEMARK.md`, the self-hosting terms; then `reviewed: true` in each. The content licence is now CC BY-SA 4.0 (Hillel, 2026-10-29: "Being stingy may reflect poorly"): the lawyer reviews it and the terms' section 5 to match, not a blocker. | Open |
| H15 | The abuse test | from `server/`: `php tool/abuse-test.php https://beta.stackapella.com` (`docs/abuse-test.md`); every attack stopped where it says, then the checks by hand | Open |
| H16 | `LICENSE.asc` | `gpg --clearsign --output LICENSE.asc LICENSE` with his key, committed beside `LICENSE` | Open |
| H17 | The DMCA designated agent | Register with the US Copyright Office; the agent's name, address and email in `server/resources/legal/en/copyright.md` | Open |
| H18 | The musician's review | **Waived** by Hillel (2026-10-28: "let's skip musician review, I trust you. We'll see if the first beta complains, they'll be the reviewer"): every official template is `reviewed: true` after the arranger's fixes. The beta singers' feedback is the check | Done |
| H19 | The last device pass | Every device in the operations plan: an iPhone (Safari and installed), Android Chrome, Safari and Firefox on a Mac, Chrome on Windows | Open |
| H20 | The security contact reaches a person | `security.txt` answers; mail to `contact@stackapella.com` (`SECURITY_CONTACT`) arrives | Check |
| H21 | Decisions waiting | Valkey or Redis (only if the beta's p95 misses: [performance.md](performance.md)); the Android FCM package; dropping the old "13 or over" once the apps ask the birth month and year, and what happens to an account that says later it's under 13 | Open |
| H22 | The load test on the beta | With k6 (the release binary, or `brew install k6`): `k6 run -e BASE=https://beta.stackapella.com -e TOKENS=sat_a,sat_b server/tool/load/beta.js` (tokens: app sign-ins of the beta's test accounts); watch `/up`, the database's CPU and the slow-query log; write the numbers under "Results" in [performance.md](performance.md) | Open |
| H23 | Cloudflare's browser cache | Caching, Browser Cache TTL: "Respect Existing Headers", then purge everything, so the app's no-cache reaches the browser and a page never mixes two deploys | Done (2026-10-28) |
| H24 | A refund that ends Plus | Decided (2026-10-29, "Always ends Plus"): any refund from admin, full or partial, ends Plus at once; store refunds follow the store | Done |

## The day, in order

Step by step, with every switch, what to check after it and how to undo it: [launch-day.md](launch-day.md).

| # | Step |
|---|---|
| D1 | Freeze `main`; the tests green in the same command as the push |
| D2 | Set `APP_URL=https://stackapella.com`, `PUBLIC_URL`, and the other names in `APP_REDIRECT_HOSTS`; the beta stays as staging (its own deployment) |
| D3 | `COMING_SOON=false`, `SITE_INDEXING=true` (on the launch domain only); deploy |
| D4 | `php artisan app:doctor` on production: no FAIL |
| D5 | Sign in, sign up, record, share and buy Plus (a real card, refunded after) on a phone, signed out first |
| D6 | Watch the error rate, the slow-query log, the queue's depth (`/up`) and the inbox for the first hours |
| D7 | Hillel says go |

The open-source launch is its own day ([open-source-plan.md](open-source-plan.md) 6), after the
blockers there.

---

[Back: The runbook](runbook.md) · [Docs guide](../README.md#a-guide-to-the-docs)
