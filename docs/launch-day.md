# Launch day

**Status:** written 2026-10-29 by the backend agent (step 2 rewritten the same day for Hillel's one-app launch: the beta's app is promoted), with the site's switches from the landing agent
and the app's from the frontend. One runbook for the day, in order. Each step says what to do, what
to check after, and how to undo it. The switches are in Admin, Switches (in sudo); settings are in
App Platform (encrypted where they're secrets) and take a deploy.

**Before the day:** the launch checklist's Hillel rows are done, above all Stripe's live setup
([stripe-live.md](stripe-live.md), with its test-mode dry run), the lawyer (H14), Sentry (H6), the
readiness check, backup and drill on production (H1, H2, [launch-dry-runs.md](launch-dry-runs.md)),
the film's upload (H13) and the instruments' upload (H12).

## 1. Freeze, and a green main (D1)

- **Do:** the coordinator stops landings; the last push runs the tests in the same command.
- **Check:** CI's last run on main is green (tests, web and secrets jobs).
- **Undo:** nothing to undo; landings resume after the day.

## 2. The launch domain: one app, promoted (D2, D3)

Hillel's choice (2026-10-29): no separate production app. The App Platform app that served the beta
becomes production: the public site moves from `beta.stackapella.com` to `stackapella.com`; the app
stays on `app.stackapella.com` (`APP_URL` doesn't change, so sign-in, email links, OAuth callbacks
and uploads don't either). The beta host stays served as an **old public host**, never a plain
redirect (step 2f).

**2a. The code (before anything else).** The deploy must include `425bd870` (the old public host:
`OLD_PUBLIC_HOSTS`). Check: Admin's version badge shows a build from after it.

**2b. App Platform: add the domains.** Settings, Domains: add `stackapella.com` and
`www.stackapella.com` (keep `app.stackapella.com` and `beta.stackapella.com`). App Platform shows
the DNS target for each.
- Check: both listed as "Pending" with their target.
- Undo: remove them.

**2c. Cloudflare: point the names at the app.** In DNS, set `stackapella.com` (apex, CNAME
flattened) and `www` to App Platform's target, the same way `app` and `beta` are set (same proxy
setting). Then remove whatever made the apex 302 to the beta (Rules: a Redirect Rule or a Page
Rule on `stackapella.com`), or the apex keeps going to the beta.
- Check: App Platform shows both domains "Active" with a certificate; `curl -sI https://stackapella.com/up`
  answers 200 from the app (not a 302, not Cloudflare's own page).
- Undo: put the redirect rule back.

**2d. App Platform: the settings, in one go** (Settings, App-level environment variables; one
deploy):

| Setting | Before | After |
|---|---|---|
| `PUBLIC_URL` | `https://beta.stackapella.com` | `https://stackapella.com` |
| `OLD_PUBLIC_HOSTS` | (unset) | `beta.stackapella.com` |
| `APP_REDIRECT_HOSTS` | (whatever it is) | `www.stackapella.com` (never the beta host) |
| `APP_REDIRECT_TEMPORARY` | `true`, if set | delete it (permanent 301s from now on) |
| `COMING_SOON` | `true` | `false` |
| `SITE_INDEXING` | `false` | `true` |
| `BETA_PASS_DOMAIN` | `.stackapella.com` | delete it (the pass gates nothing once COMING_SOON is off) |
| `APP_URL` | `https://app.stackapella.com` | unchanged |
| `SESSION_DOMAIN` | unset | unset (the session belongs to the app's host alone) |
| `IS_NEXUS` | unset | unset (worked out from the URLs: ours) |

What follows from them by itself: the CORS origin for Report is the new `PUBLIC_URL`; passkeys
allow the app, the public site and the bare domain (the relying party stays `stackapella.com`, so
admins' passkeys keep working); the sitemap and canonical URLs use `stackapella.com`; `robots.txt`
allows search on `stackapella.com` and disallows it on the app's host and the beta host.
- Check:
  - signed out, `https://stackapella.com` is the landing (no coming soon), and its Start goes to
    `https://app.stackapella.com/learn/...`; the app opens with no early-access gate
    (auth.config `comingSoon` false, `prelaunch` false: no draft songs);
  - `https://www.stackapella.com/pricing` 301s to `https://stackapella.com/pricing`;
  - `https://beta.stackapella.com/` and `/h/<an id>` 301 to `stackapella.com` with the same path;
    `https://beta.stackapella.com/?app` shows "Stackapella has moved";
  - `https://stackapella.com/robots.txt` allows search with the sitemap;
    `https://beta.stackapella.com/robots.txt` and `https://app.stackapella.com/robots.txt` say
    `Disallow: /`.
- Undo: put the "Before" column back and deploy.

**2e. The private bucket's CORS.** Spaces, `stackapella-private`, Settings, CORS: add
`https://stackapella.com` for GET, HEAD and PUT, as `beta.stackapella.com` and
`app.stackapella.com` have (keep them).
- Check: a song's guide plays on `stackapella.com`'s landing hero and song pages (no CORS error in
  the console).
- Undo: remove the rule.

**2f. The webhooks: move each to the app's host, which never changes again.** They keep working on
the beta host meanwhile (it still answers `/webhooks/*`), so this can be done one at a time, after
the launch if need be. For each: add the new endpoint, send a test, then remove the old one.
- **Stripe, live:** made fresh on `https://app.stackapella.com/webhooks/stripe` and
  `/webhooks/stripe-connect` ([stripe-live.md](stripe-live.md)); their signing secrets go in
  `STRIPE_WEBHOOK_SECRET` and `STRIPE_CONNECT_WEBHOOK_SECRET`.
- **Stripe, test mode** (the beta's): leave, or point at the app's host the same way.
- **The mail Worker** (bounces): its target URL to `https://app.stackapella.com/webhooks/mail`.
- **App Store and Google Play** server notifications, if set: `/webhooks/app-store` and
  `/webhooks/google-play` on the app's host.
- **Invoice Ninja and Event Schedule:** registered per connection by the server, on the URL it
  had then; old ones keep arriving on the beta host. Nothing to do.
- Check: Stripe's Events show each test delivered with a 2xx; the mail Worker's logs show a 2xx.
- Undo: the old endpoints still work while the beta host is an old public host.

**2g. Around it.**
- **Cloudflare Turnstile:** add `stackapella.com` to the widget's hostnames (it's used on the
  public site's forms).
- **Google Search Console:** add `stackapella.com` and submit `https://stackapella.com/sitemap.xml`.
- **Google and Apple sign-in, the email links:** on the app's host, unchanged. Check one sign-in.

**Afterwards, the beta host.** It stays as `OLD_PUBLIC_HOSTS` (and keeps its App Platform domain
and DNS record) while old installs move and the webhooks are re-pointed. Only when every webhook is on the
app's host, and old installs have had a few months to open once (each opening moves it), does it
become a plain redirect: move it from `OLD_PUBLIC_HOSTS` to `APP_REDIRECT_HOSTS`. A guest's songs in an install
that never opened again are lost then; until then, nothing is.

## 3. The readiness check on production (D4)

- **Do:** in the web component's console, `php artisan app:doctor`.
- **Check:** no FAIL. The WARNs left should only be things you chose to leave.
- **Undo:** nothing; fix what a FAIL names before going on.

## 4. Plus on sale

- **Do:** with [stripe-live.md](stripe-live.md)'s live keys, webhooks and prices pasted
  (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_CONNECT_WEBHOOK_SECRET`,
  `STRIPE_PRICE_MONTH`, `STRIPE_PRICE_YEAR`), deploy. In Switches, `pause_checkout` is off.
- **Check:** `/pricing` has no "Coming soon" badge and shows "Try Plus free" with the 7-day trial
  line; the pricing FAQ's trial answer reads "Yes: 7 days of Plus..."; the landing's plans show "Try
  Plus free for 7 days"; `/features` shows choir groups' "With Plus" without "Plus isn't open yet".
  In the app, Start Plus reaches Stripe's checkout. The doctor's three Stripe checks pass.
- **Undo:** `pause_checkout` on (checkout stops at once, everything else stays), or unset the price
  ids and deploy.

## 5. The Plus courses (`plus_courses` on)

- **Do:** Switches, `plus_courses` on.
- **Check:** a Plus account sees the courses in the app (account `plusCourses`); `/courses/vibrato`
  says "Open on Plus" with "Try the first lesson free" linking to `/learn/c/vibrato`, and the link
  works; `/courses` marks Vibrato open and drops its Coming soon heading; `/courses/counterpoint`
  still says Coming soon (its status in `web/course/plus/courses.json`).
- **Undo:** `plus_courses` off (staff still see them).

## 6. Hum a tune (`your_tune` on)

- **Do:** Switches, `your_tune` on.
- **Check:** `/features/your-tune` leads with "Hum it, and Stackapella writes the notes and suggests
  the other parts." with no Coming soon pill; "Sharing it as an idea" still says Coming soon; no
  "Start today in the creator" section; the landing's On the way says "Hum yours now". (The app's own
  screens come when the frontend builds them; nothing to flip there today.)
- **Undo:** `your_tune` off.

## 7. Coaches can sign up (`coach_signup` on)

- **Do:** Switches, `coach_signup` on. Leave `coach_directory` off.
- **Check:** `/for/coaches` shows "Sign-ups open" and "Apply to coach" linking to the app's
  `/coach/apply`; auth.config `coachSignup` is true; no coach is listed anywhere (`/coaches` is
  gone, search finds none).
- **Undo:** `coach_signup` off (applications answer `409 coach_signup_closed`).

## 8. The film and the instruments

- **The film:** the landing plays it under the hero, from `CDN_URL/showreel` (no setting). If it
  doesn't show: `php artisan app:showreel:upload --check` in the console says what's missing; the
  page simply has no film until it's there.
- **The instruments:** once uploaded (H12), set `INSTRUMENTS_ENABLED=true` and deploy. Check:
  auth.config's `instrumentsIndexUrl` isn't null, and a song with an instrument part plays it in
  the app. Undo: `INSTRUMENTS_ENABLED=false`, deploy (the apps play the synth).

## 9. The first real purchase (D5)

- **Do:** signed out, on a phone: sign up, record, share, then buy Plus with a real card; refund it
  from Admin, People (in sudo). Refunding ends Plus at once (H24).
- **Check:** Stripe's live events show each one delivered with a 2xx; the account was Plus, then
  free after the refund.

## 10. The mudeo email

- **Do:** Admin, Mudeo: Send a test email to yourself; check it arrives from hillel@stackapella.com,
  not in spam, with the early-access link working. Then Send the next batch, 50 at a time, within the
  daily cap.
- **Check:** the screen's By day table (sent, bounced, opened the link, joined, started singing);
  the batch's bounce rate stays low (sending stops by itself on a high one).
- **Undo:** Undo latest batch for one that hasn't gone yet; stop sending by not sending the next.

## 11. The first hour: what to watch

| Watch | Where | What's wrong |
|---|---|---|
| Errors | Sentry (server and browser) | A new error repeating, or any 500 on sign-up, checkout or upload |
| The queue | `/up` (its queue depth), and the doctor's Queue check | Jobs waiting more than a few minutes |
| Payments | Stripe, Developers, Events | A webhook delivery failing (not 2xx) |
| Mail | Cloudflare Email, and the bounce Worker's logs | Sends failing, or bounces arriving fast |
| Slow requests | App Platform's runtime logs (the slow-query lines) and its CPU graph | The same slow query again and again, or CPU pinned |
| Sign-ups and limits | Admin, Trust and safety (refusals), the Usage panel | A flood of refusals from one network: shield mode is Switches, `shield_mode` |
| The next morning | The daily digest | Anything it lists as waiting |

If something's wrong and it's one switch's fault, turn that switch back (each step's Undo). If the
app itself is wrong, `read_only` in Switches keeps everyone reading while it's fixed.

---

[Back: The launch checklist](launch-checklist.md) · [The launch plan](launch-plan.md)
