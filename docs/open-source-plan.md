# Open-source release plan

**Status:** planned for launch (written 2026-10-27). Nothing is published, no repo's visibility has
changed, and no code is changed. Hillel decided to release the code under the **Attribution
Assurance License (AAL)** at launch, as Event Schedule is. This plan gets both repos ready for that
day: the secret scan, the licence audit, what stays out of the AAL, the clean-up, the pages, and
the launch-day checklist.

The repos:

| Repo | What | Today |
|---|---|---|
| `hillelcoren/stackapella` (`~/Code/stackapella`) | The Laravel API (`server/`), the Vue PWA (`web/`), the contract and the docs | Private; pushing `main` deploys the beta (`.do/app.yaml`) |
| `hillelcoren/stackapella-mobile` (`~/Code/stackapella-mobile`) | The Flutter apps and the audio engine (`packages/stack_engine`) | Private; to be PolyForm Strict 1.0.0 (Hillel, 2026-10-29), with the lawyer (H14) |
| `hillelcoren/stackapella-courses` | Every course and the songs (`course/`, `templates/`), their format and checks, pinned in this repo by `.do/content.lock` (docs/repos-plan.md) | Public under `CONTENT-LICENSE`, open to pull requests |

## In short

- **Secret scan: clean.** No live secret has ever been committed to either repo, on any branch or
  stash. Every hit is a test password, a contract example token or a placeholder (section 1).
- **Licences: no blocker.** Everything we ship or load is permissive (MIT, BSD, Apache-2.0, OFL,
  CC0), except lamejs (LGPL-3.0), which is fine as it's loaded unmodified from the CDN. Two
  notices need adding: SecLists (MIT) for the common-password list, and lamejs (section 2).
- **Not everything is AAL.** The songs, the courses, the guides and the screenshots are CC BY-SA
  4.0 (`CONTENT-LICENSE`; Hillel, 2026-10-29: "Being stingy may reflect poorly"); the legal texts,
  the singers' recordings and the brand stay ours (`TRADEMARK.md`) (section 3).
- **History: start fresh.** Recommended: publish one initial commit in new public repos, and keep
  today's private repos as the archive (section 4.6).
- **Pages:** `/open-source`, `/selfhost` and `/legal/self-hosting-terms`, behind an `open_source`
  flag. **No `/saas` at launch** (section 5).
- **Blockers before going public** (none are secrets):
  1. Two songs are withheld from the published tree. **Done.**
  2. `CONTENT-LICENSE` must be in the repo before the content goes public. **Done:** CC BY-SA 4.0.
  3. The self-host claims need some code first (section 5.5), or `/selfhost` can't go live.
  4. Confirm that production sets its own `SQIDS_ALPHABET` (section 1.4).
  5. The legal drafts carry `for_lawyer:` notes and name "Stackapella" as the contracting party, with the legal entity for the lawyer to confirm.
     Strip the notes, and give self-hosted installs their own legal pages.

## Decisions for Hillel

Each answer goes into `docs/decisions.md` when it's made. **Decided (Hillel, 2026-10-27): all nine as recommended.**

| # | Question | Recommendation |
|---|---|---|
| 1 | Keep the git history, or start the public repos from one commit? | One commit in new public repos; today's repos stay private as the archive (4.6) |
| 2 | Where the public repos live: `hillelcoren/*`, or a `stackapella` org (as `eventschedule/eventschedule`)? | A `stackapella` org: `stackapella/stackapella` and `stackapella/stackapella-mobile` |
| 3 | How the songs and the course are licensed, and whether a commercial install may use them | The custom `CONTENT-LICENSE` in 3.2: non-commercial installs may use them unmodified; commercial hosts need written permission | **Changed (Hillel, 2026-10-29): CC BY-SA 4.0 for the courses, the arrangements, the guides and the images; singers' recordings, the legal texts and the name stay ours.**
| 4 | Which docs leave the public tree, or get redacted | The list in 4.2 |
| 5 | Should `LICENSE` be GPG-signed? The AAL text says "this GPG-signed text", but Event Schedule's `LICENSE` has no signature block | Sign it (a clearsigned `LICENSE.asc` next to `LICENSE`), so the text means what it says |
| 6 | Self-hosted pages still load Vue and lamejs from jsDelivr and fonts from Google Fonts. Say so plainly, or offer an optional local copy? | Say so plainly at launch ("CDN, pinned with SRI" is the rule); offer a vendoring script later if self-hosters ask |
| 7 | Contributions: inbound = outbound (contributions are AAL), or a CLA? | Inbound = outbound, stated in `CONTRIBUTING.md`. The courses (`web/course/`) take issues and pull requests, inbound = outbound under `CONTENT-LICENSE` (Hillel, 2026-10-29: "people can improve the courses with PR's"); the other content folders take no pull requests |
| 8 | Who is the licensor in `LICENSE` and the self-hosting terms: "Stackapella", Hillel, or the legal entity that owns Stackapella (for the lawyer)? | Attribution to "Hillel Coren" and "Stackapella" as the AAL asks; the lawyer names the party for the terms |
| 9 | Does the mobile repo go public on the same day, before the apps are in the stores? | Yes, together, with "in development" in its README: one licence, one contract | **Changed (Hillel, 2026-10-28): the mobile repo stays closed source, so the stores don't fill with clone apps; only the main repo opens.**

---

## 1. Secret scan

### 1.1 What was scanned

| Repo | Commits | Refs covered |
|---|---|---|
| stackapella | 674 (gitleaks counts 675, with a stash's index commit) | `main`, `agent/backend`, `agent/frontend`, `agent/landing`, `api-skip`, `origin/main`, and the 3 stashes (`refs/stash`). GitHub has only `main` |
| stackapella-mobile | 107 (110 with gitleaks' extra objects) | `main`, `origin/main` |

Three passes, no new tools installed:

1. **A regex pass** over `git log -p --all` (every added line of every commit, binary files
   excluded), for: private key blocks, AWS (`AKIA`, `ASIA`), Stripe (`sk_`, `pk_`, `rk_`, `whsec_`),
   GitHub, Slack, Google API keys (`AIza`), Google OAuth secrets (`GOCSPX-`) and client IDs,
   DigitalOcean tokens (`dop_v1_`, `DO00` Spaces keys), SendGrid, Mailgun, OpenAI and Anthropic
   keys, Laravel `APP_KEY` (`base64:`), JWTs, URLs with credentials, VAPID-shaped keys (87 and 43
   characters), Turnstile (`0x4AAAA`), Resend, Sentry DSNs, 32 to 64 character hex, UUIDs, IPv4
   addresses, email addresses, and `NAME_KEY=`, `SECRET`, `TOKEN` and `PASSWORD` assignments.
2. **A path pass** over every file name ever committed (`git log --all --name-only`): `.env*`,
   `*.p8`, `*.p12`, `*.pem`, `*.key`, `*.jks`, keystores, `key.properties`, `google-services.json`,
   `GoogleService-Info.plist`, provisioning profiles, `id_rsa`, credentials files, `auth.json`,
   SQL dumps, CSVs, SQLite files and recordings.
3. **gitleaks 8.30.1** (already installed at `/opt/homebrew/bin/gitleaks`), in git mode with
   `--log-opts=--all` and `--redact`.

Commit metadata: the public repos' commits are by `Hillel Coren`, with his GitHub noreply
address. No commit carries a co-author or session trailer.

### 1.2 Findings

Values are masked: the first characters, `***`, the last two, and the length.

| # | Commit(s) | File(s) | Type | Live or placeholder |
|---|---|---|---|---|
| 1 | `80c2d5803`, `d4f79f4ee`, `89a2bfd17`, `8ec8f1fed`, `b2b68e1d5`, `43f35e2d5`, `3953ba165`, `a3c066517` | `server/database/factories/UserFactory.php`; `server/tests/Feature/Accounts/{SignUp,SignIn,Account,AppTokens}Test.php`, `Ops/InviteLinkTest.php`, `Ops/MudeoEmailTest.php`, `Sharing/SharingAccountsTest.php`, `Beta/InvitesTest.php` | Test password (gitleaks `generic-api-key`): `a-fi***42` (25) and per-test variants | **Placeholder.** Only ever used against the test database |
| 2 | `796e00826` | `server/tool/abuse-test.php` | A password for the scripted abuse test's throwaway accounts | **Placeholder** (made-up accounts on the beta) |
| 3 | `8ec8f1fed`; mobile `9222a8a3a`, `f9664a532` | `contract/examples/auth.app.{apple,google,signin,verify,refresh}.200.json`; mobile `docs/contract-draft/` and `test/data/accounts_test.dart` | App access and refresh tokens: `sat_3f***Za`, `srt_Vb***c8`, `sat_8G***Fz`, `srt_Mn***s7` (28 each) | **Placeholder.** Hand-written contract examples; real tokens are random and hashed at rest |
| 4 | `b9028f47a`; mobile `2ef799c82` | `contract/examples/billing.show.200.json`, `billing.store.sync.*.json` | Store account token (a UUID): `4c6a***91` (36) | **Placeholder** |
| 5 | `fb6741aae`; mobile `e39c1eb97` | `contract/examples/reminders.devices.create.201.json` | Device token: `dvc_8f***5n` (24) | **Placeholder** |
| 6 | `80c2d5803`, `2cc73bec4`, `60820d19e`, `a3c066517` | `server/database/seeders/DemoSeeder.php`, `web/tests/flows/*.py`, `README.md`, `server/tests/Feature/Beta/InvitesTest.php` | Demo verify, revert and invite tokens: `demo***01` (42), `an-i***01` (42) | **Placeholder.** Seeded into the local demo database only |
| 7 | 21 commits from `3472e232e` | `server/.env.example` | Every value ever in it was checked: only non-secret defaults (`DB_HOST=127.0.0.1`, `MAIL_MAILER=log`, bucket names `stackapell***`, product ids); every secret key is blank | **Placeholder** |
| 8 | `b4fd50e`, `651d3ad`, `32a4637`, `1cdac8e` | `.do/app.yaml` | Non-secret env values only; the secrets are listed by name in a comment and set in the App Platform console | **Not a secret** |
| 9 | `b9028f47a` | `server/resources/certs/AppleRootCA-G3.pem` | Apple's public root certificate | **Not a secret** (public, published by Apple) |
| 10 | `b9028f47a` | `server/tests/Support/StoreFakes.php` | A service-account JSON whose private key is generated at run time (`$pem`); `pubsub@stackapella.iam.gserviceaccount.com` and `play@...` | **Placeholder.** The addresses hint that a Google Cloud project is called `stackapella`; harmless |
| 11 | `500e935dc` | `server/tests/Feature/Ops/OpsTest.php` | Sentry DSN `https://key@o1.ingest.sentry.io/123` | **Placeholder** |
| 12 | mobile, in the tree | `ios/Runner.xcodeproj/project.pbxproj` | Apple Team ID `NPC4***98` | **Not a secret.** It's in every app signed by the team |
| 13 | various | contract examples, tests | IP addresses: only documentation ranges (`203.0.113.x`, `198.51.100.x`), example.com's address, Cloudflare's published ranges, the cloud metadata address in SSRF tests | **Placeholder.** No server's address appears anywhere |
| 14 | various | contract examples, tests, docs | Email addresses: `contact@`, `security@`, `hello@`, `review@stackapella.com`; the sending addresses `hillel@stackapella.com` and `hillel@news.stackapella.com`; package authors in `composer.lock` | **Not secrets.** Public or ours |
| 15 | various | `server/tests/**`, `contract/examples/**`, mobile `test/contract/**` | Made-up people on real consumer domains: `ana@gmail.com` (28 lines), `sam@yahoo.com` (10), `kim@aol.com` (8), `gone@aol.com` (6), `ada@gmail.com`, `kim@gmail.com`, `lee@aol.com`, `bounced@gmail.com`, `already@gmail.com`, `singer@gmail.com` and others | **Fictional, but real-looking.** Someone may own those addresses. Hygiene item 4.1 |
| 16 | `c1b09d6` onwards | `docs/mudeo-transition-plan.md` | Aggregate counts of the mudeo list (2,474 users, by year last active, Gmail share) and the path of Hillel's private copy | **No personal data in the repo.** The list itself (`~/.config/stackapella/mudeo-users.csv`) was never committed; no file with mudeo addresses exists in either history. The counts are business-private: item 4.2 |

The path pass found no `.env`, `.p8`, `.p12`, private key, keystore, `key.properties`,
`google-services.json` or `GoogleService-Info.plist` in either history. Android release signing
still uses the debug config (`android/app/build.gradle.kts`), so no upload key has ever been near
the repo.

**Verdict: no live secret in either repo's history. Nothing needs rotating.**

### 1.3 What the scan can't see

- **Images.** 135 committed images (`web/icons/help`: 76, `web/icons/og`: 59, plus their earlier
  versions) and 4 audio fixtures can't be read by a regex. The fixtures are generated chirps
  (`web/tool/make_guide_fixtures.sh`). The screenshots come from the demo seed, but each should be
  looked at once before launch for a real name, address or account.
- **Local refs.** `agent/*`, `api-skip` and the stashes were scanned and are clean. They are never
  pushed, and with a fresh public repo (4.6) they can't leak.

### 1.4 Not a secret, but check before going public

- **The Sqids alphabet.** `server/config/app.php` has a default alphabet (`kRwQ***rE`, 50
  characters) used when `SQIDS_ALPHABET` is unset. `.do/app.yaml` lists `SQIDS_ALPHABET` as a
  console-set secret, so production should have its own. **Confirm it does.** If production uses
  the default, the published code turns every public id back into a row number (how many
  harmonies exist, and in what order). Changing the alphabet later breaks every shared link, so
  this must be checked before the code is public, not after.
- Self-hosters must set their own `SQIDS_ALPHABET` and `APP_KEY`. The install steps say so (5.3).

### 1.5 Running it again on launch day

From the repo root, for each repo (the masked output is safe to paste into a log):

```bash
gitleaks git . --redact --no-banner --log-opts="--all" --exit-code 1
git log --all --name-only --format= | sort -u | grep -iE '(^|/)\.env|\.p8$|\.p12$|\.pem$|\.key$|\.jks$|keystore|key\.properties|google-services|GoogleService-Info|\.csv$|\.sql$'
git log -p --all -U0 --no-color | grep -E '^\+' | grep -oiE '[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}' | sort | uniq -c | sort -rn
gitleaks dir . --redact --no-banner   # the exact tree being published, untracked files included
```

Expected: the known placeholders above and nothing else. Anything new stops the launch until it's
explained.

---

## 2. Third-party licence audit

### 2.1 Loaded from CDNs (not in the repo)

| What | Version | Licence | Where | Notes |
|---|---|---|---|---|
| Vue | 3.5.44 | MIT | jsDelivr, `web/index.html` import map, with SRI | Fine |
| lamejs | 1.2.1 | **LGPL-3.0** | jsDelivr, loaded on demand by `web/js/mp3.js`, with SRI | Fine for us: loaded unmodified as a separate file, never bundled. A self-hoster who copies it locally must keep it a separate, replaceable file with its licence. List it in the notices |
| Figtree | variable, 300 to 800 | SIL OFL 1.1 | Google Fonts | Fine |
| Noto Music (a few glyphs, `text=`) | | SIL OFL 1.1 | Google Fonts | Fine |

### 2.2 In the repo

| What | Licence | Where | Notes |
|---|---|---|---|
| Figtree Bold and Medium (TTF) | SIL OFL 1.1 | `server/resources/fonts`, with `OFL.txt` | Fine; the OFL ships beside them |
| Figtree, five weights | SIL OFL 1.1 | mobile `assets/fonts`, with `OFL.txt` | Fine; also register it with Flutter's `LicenseRegistry` so the apps' licence page lists it |
| Bricolage Grotesque (removed, in history) | SIL OFL 1.1 | `server/resources/fonts` until `880b6a9` | Its OFL text was committed with it. Only matters if the history is kept |
| Tailwind CSS output | MIT | `web/css/app.css` (keeps its `/*! tailwindcss v4.3.3 | MIT License */` banner) | Fine. The standalone binary (`web/bin/`) is downloaded by `web/tool/tailwind.sh` with a SHA-256 check and is not committed |
| The 10,000 most common passwords | **MIT** (SecLists) | `server/resources/data/common-passwords.txt` | **Needs a notice:** MIT asks for the copyright line. Copy SecLists' `LICENSE` into `THIRD-PARTY-NOTICES.md` |
| Disposable email domains | CC0 | `server/resources/data/disposable-domains.txt` | Fine; credit it as a courtesy |
| `held-phrases.txt`, `offensive-words.txt` | Ours | `server/resources/data` | Fine (see 4.2 on whether to publish moderation lists) |
| Apple Root CA G3 | Apple's public certificate | `server/resources/certs` | Fine to redistribute |
| Icons and the mark | Ours, drawn by `web/tool/icons.py` | `web/icons`, `web/js/logo-mark.js` | Trademark, not AAL (3.1) |
| User guide screenshots, share images | Ours | `web/icons/help`, `web/icons/og` | Content, not AAL (3.1) |
| Flutter project scaffolding | BSD-3-Clause (Flutter's templates) | mobile `android/`, `ios/`, `linux/`, `macos/`, `windows/` | Fine |
| Flutter agent skills (10 files) | **Unverified** | mobile `.claude/skills/flutter-*` | Copied from `~/Code/admin`; their front matter suggests the Flutter team's published skills. **Check the upstream licence and add its notice, or leave `.claude/` out of the public repo** |
| minimp3 | CC0 | approved, not yet vendored | Keep its header when it's added |

No copied third-party code was found. Searching both trees for "adapted from", "ported from",
"copied from", Stack Overflow, and MIT, BSD or Apache notices found only our own ports (the audio
spike's DSP into `web/js/dsp.js`) and ideas borrowed from Hillel's own projects (iaw, Invoice
Ninja's admin), which are his to relicense.

### 2.3 Composer (server)

164 packages in `composer.lock`, 128 of them in production. **126 MIT, 31 BSD-3-Clause, 3
Apache-2.0, 2 BSD-2-Clause, and 2 dual BSD-3-Clause or GPL** (`nette/schema` and `nette/utils`,
used under BSD-3-Clause). Nothing copyleft-only.

- Apache-2.0: `aws/aws-sdk-php`, `aws/aws-crt-php`, `phpoption/phpoption`. Compatible with the AAL.
- `vendor/` isn't committed, so nothing is redistributed through the repo. If we ever ship a
  release zip with `vendor/` (as Event Schedule does), Composer keeps each package's `LICENSE` and
  `NOTICE`; don't strip them.
- The direct production packages: laravel/framework, cashier, passkeys, socialite, tinker
  (MIT); bacon/bacon-qr-code (BSD-2); league/flysystem-aws-s3-v3 (MIT); minishlink/web-push (MIT);
  pragmarx/google2fa (MIT); sentry/sentry-laravel (MIT); socialiteproviders/apple (MIT);
  sqids/sqids (MIT).

### 2.4 pub (mobile)

116 packages in `pubspec.lock`: **96 BSD, 8 MIT, 4 Apache-2.0** (`clock`, `fake_async`,
`material_color_utilities`, `webdriver`), 7 from the Flutter SDK, and our own `stack_engine`. The
direct ones: drift, sqlite3, sign_in_with_apple (MIT); go_router, http, path_provider,
flutter_secure_storage, google_sign_in, build_runner, flutter_lints (BSD-3). All compatible.
Flutter's licence page (`showLicensePage`) collects the packages' notices for the shipped apps; the
apps' About should link to it.

### 2.5 Content we use but don't hold in the repo

- **Instrument samples:** CC0 sets (VCSL, VSCO 2 CE, Virtuosity Drums, Karoryfer, FreePats), on
  our CDN, not in the repo (`docs/instrument-samples.md`). Two picks still wait on Jeff Learman's
  confirmation (the jRhodes GM subset's CC0 grant conflicts with the full set's NC licence). A
  credits page is planned as a courtesy.
- **Songs:** public-domain tunes and words; the arrangements are ours (each template's `licence`
  block).

### 2.6 Is anything incompatible with the AAL?

No. The AAL is a permissive BSD-style licence with an attribution duty. MIT, BSD, Apache-2.0, OFL
and CC0 all sit under it. lamejs (LGPL) is fine because it's a separate, unmodified file loaded at
run time. The notices to add are in `THIRD-PARTY-NOTICES.md` (6.2): SecLists (MIT, required),
lamejs (LGPL-3.0), Vue (MIT), Tailwind (MIT), Figtree and Noto Music (OFL), disposable email
domains (CC0), and the Flutter skills if they stay.

**One AAL detail for the apps:** clause 2 asks anyone who redistributes a binary to show the
attribution (name, "Stackapella", URL) each time it starts, as a splash or banner. Our own store
builds come from the Author, so it binds forks, not us. The apps' About should show the credit
anyway, so a fork inherits it.

---

## 3. The content carve-out

### 3.1 What stays out of the AAL

The AAL covers the software. These are content, and stay ours:

| What | Paths | Terms |
|---|---|---|
| The arrangements (the songs) | `server/database/templates/*.json` (18), `web/templates/*.json` (4 songs and `index.json`), mobile `assets/templates` | `CONTENT-LICENSE` |
| The course | `web/course/course.json`, mobile `assets/course` | `CONTENT-LICENSE` |
| The guides (articles) | `server/resources/guides/en/*.md` (12) | `CONTENT-LICENSE` |
| The help pages' text | Today inside `server/lang/en/pages.php` (`help` keys), mixed with interface strings | Move the help articles into their own folder (`server/resources/help/en/`, like the guides) so the carve-out is by path. Until then the whole of `pages.php` is AAL, help included |
| What's new | `server/resources/whats-new/` | `CONTENT-LICENSE` |
| User guide screenshots and share images | `web/icons/help` (76), `web/icons/og` (59), `web/icons/og-landing.png` | `CONTENT-LICENSE` |
| The legal texts | `server/resources/legal/en/*.md` | All rights reserved. A self-hoster writes their own (5.5) |
| Marketing copy | `docs/launch-kit/` (if it stays in the tree, see 4.2) | All rights reserved |
| The name and the mark | "Stackapella"; the four bars: `web/icons/icon.svg`, `favicon.svg`, `icon-*.png`, `apple-touch-icon.png`, `web/icons/app/`, the logo set in `web/icons/brand/` (drawn by `web/tool/brand.py`), the mark in `web/js/logo-mark.js`, the apps' AppIcon sets, mipmaps and launch images | `TRADEMARK.md`. The code that draws them (`web/tool/icons.py`) is AAL; the mark is a trademark either way |
| The singers' recordings | **Not in the repo.** They live in the private and media buckets, licensed to Stackapella under the beta agreement | Never redistributed. A self-hosted install has none; its guides fall back to the synthesized voice |
| Instrument samples | **Not in the repo.** CC0, on our CDN | Their own CC0 grant; a self-hoster uploads their own (`app:instruments:upload`) |
| People's own recordings and songs on stackapella.com | Not in the repo | The Terms of use |

Stays under the AAL: all code, the interface strings (`web/locales/*.json`, `server/lang/**`
except the help articles once moved), the contract and its examples, the tests and fixtures, the
demo data (`server/database/seeders/DemoSeeder.php`: fictional people on `example.com`), the docs
(except what 4.2 removes), and the tools.

**The code must run without the content.** A commercial host that may not use our songs deletes
those folders; the app should then start with empty states ("No songs yet") rather than errors,
and the seeders should skip what's missing. That's a task in 5.5, with a test.

### 3.2 `CONTENT-LICENSE` (draft for the lawyer)

**Superseded (Hillel, 2026-10-29: "Being stingy may reflect poorly"):** the content is CC BY-SA 4.0,
credited "Stackapella (https://stackapella.com)", with singers' recordings, the legal texts and the
name kept out (`CONTENT-LICENSE`). The draft below is kept for the record.

**Recommended: all rights reserved, with three grants.** It matches what the Terms of use already
promise people on stackapella.com ("share and monetise with 'Arranged by Stackapella', no
re-selling"), which a stock Creative Commons licence can't:

> **Stackapella content licence**
>
> Copyright (c) 2026 Hillel Coren (Stackapella, https://stackapella.com). All rights reserved,
> except as granted below.
>
> This licence covers the files listed in the "Content" section of the README: the arrangements,
> the course, the guides and help articles, the screenshots and share images, and the legal texts.
> The software in this repository is licensed separately, under the Attribution Assurance License
> in `LICENSE`. The Stackapella name and mark are covered by `TRADEMARK.md`.
>
> 1. **Inside Stackapella.** You may use the content, unmodified, inside an installation of
>    Stackapella that you run for a non-commercial purpose (your own choir, school, class or
>    household), with the attribution the software shows left in place. Running it as a paid
>    service, or inside a paid product, needs written permission.
> 2. **Singing it.** Anyone may perform, record and publish recordings of the arrangements,
>    including for money, with the credit "Arranged by Stackapella".
> 3. **Not the files themselves.** You may not copy, republish, sell or redistribute the content
>    outside an installation of Stackapella (as scores, data files, articles, course material or
>    images), or use it to train or build another product.
>
> The tunes and words of public-domain songs stay in the public domain. This licence covers our
> arrangements, text and images, not them.

**The alternative Hillel asked about: CC BY-NC-ND 4.0** for the arrangements and the course.
Simpler and well known, but it fits less well:

- NC forbids the paid performances and monetised videos the Terms of use allow, unless a second
  grant is added on top anyway.
- ND makes changing the arrangement unlawful, and the app itself transposes, slows down and lets a
  creator edit a copy, which arguably makes adapted material.
- It still needs the "inside an installation" grant for self-hosters.

So the custom licence above, reviewed by the lawyer, is the recommendation. If Hillel prefers a
standard licence anyway, use CC BY-NC-ND 4.0 for `templates` and `course` plus grant 2 above, and
all rights reserved for the rest.

### 3.3 `TRADEMARK.md` (draft)

> **The Stackapella name and mark**
>
> "Stackapella" and the four-bar mark are trademarks of Hillel Coren. The code is open source; the
> name isn't.
>
> **You may:** keep the attribution the licence requires (the name, "Stackapella" and the link);
> say your service is "Powered by Stackapella", "runs on Stackapella" or is "based on Stackapella"; run an unmodified install
> for your own choir, school or organisation and call it Stackapella inside it; write about the
> project.
>
> **You may not:** name a public service, app or app store listing "Stackapella" or anything
> confusingly close; use the mark as your logo or app icon; suggest that we endorse, run or
> support your service; use stackapella.com's words, screenshots, logo, showreel or singers' voices
> in your own marketing; or point a fork of the apps at stackapella.com's API without written
> permission.
>
> A fork that runs as a public service changes the name, the logo, the app icon and the apps'
> bundle identifiers. Questions: contact@stackapella.com.

This is the same line the AAL's clause 3 draws ("neither the name nor any trademark of the Author
may be used to endorse or promote products derived from this software"), made concrete.

---

## 4. Repo hygiene before going public

### 4.1 Test fixtures and examples

- **Real consumer domains in made-up addresses** (finding 15): replace `@gmail.com`, `@yahoo.com`
  and `@aol.com` in `server/tests`, `contract/examples` and the mobile's `test/contract` with
  `example.com`, `example.net` and `example.org`. Keep the special cases that test real behaviour
  (Apple's `privaterelay.appleid.com`, `mailinator.com` for the disposable list, `spam.example`).
  The contract examples are copied into the mobile repo by `tool/sync_from_web.sh`, so change them
  in `contract/` and sync.
- `docs/token-auth-plan.md` (mobile) names a device "Hillel's iPhone". Harmless; leave it.
- The demo seed already uses `example.com` and "(demo)" names. Nothing to do.

### 4.2 Docs: keep, redact or drop

Most of `docs/` is fine to publish and shows how the product was thought through. These hold
private business information or would help an attacker:

| Doc | What's in it | Recommendation |
|---|---|---|
| `docs/mudeo-transition-plan.md` | mudeo's user counts by year, the Gmail share, the private file's path, the sending plan | **Redact** "The list as exported" paragraph (counts and path). The launch kit's own rule is "mudeo never with numbers" |
| `docs/pricing-research.md` | A competitor price table | **Drop** from the public tree (keep it privately). Public facts, but it's strategy |
| `docs/pricing-plan.md` | Prices, and the intention to raise them ("toward $7.99 and $59.99") | **Redact** the future-price lines; the rest is fine |
| `docs/operations-plan.md` | The cost model, margins, monthly spend | **Redact** "The cost model at $4.99" |
| `docs/runbook.md` | Billing alert amounts, the store tester account, the playbook | **Keep**, redact the dollar thresholds. Secrets are named, never valued |
| `docs/radar-rules.md` | The Stripe fraud rules to paste into Radar | **Drop.** They exist nowhere in code, and publishing them tells card testers what to avoid |
| `docs/abuse-test.md` | Every scripted attack and where it stops | **Keep or drop: Hillel's call.** The limits are readable in the code anyway; the "done by hand" list is the sensitive part. Lean: drop |
| `docs/trust-and-safety-plan.md`, `server/resources/data/held-phrases.txt`, `offensive-words.txt` | How spam and abuse are caught | **Keep.** The code reveals it, and open moderation lists are normal |
| `docs/launch-kit/` | Draft posts, press release, outreach notes | **Drop** from the public tree before going public (it's posted the same day; drafts in the repo read oddly). Keep privately |
| `docs/beta-singers-plan.md`, `docs/beta-guide.md` | How singers are paid, contractor tax | **Keep.** No names or rates |
| `contract/log.md`, `docs/templates-review.md` | "Needs Hillel" items, including the lawyer's two-song question | **Keep**, but resolve item 11 first (2.5) |
| `server/resources/legal/en/*.md` | `for_lawyer:` notes in the front matter (open questions; the legal entity for the lawyer to name) | **Strip the `for_lawyer` blocks** once the lawyer has them, and keep `reviewed:` |
| `CLAUDE.md`, `docs/agents/`, `docs/parallel-agents-plan.md`, the design prompts | How the agents work | **Keep.** Honest about how it was built. Hillel's call |
| `web/spike_audio/` | The throwaway Flutter prototype | **Delete** before launch (the README calls it throwaway) |
| mobile `docs/questions-for-hillel.md` | Open questions (Apple team, devices) | **Keep** |

### 4.3 Hard-coded internal URLs and addresses

- **No IP address** of any server in either repo (finding 13).
- **Hostnames:** `app.stackapella.com`, `beta.stackapella.com`, and the bucket hosts
  `stackapella-media.nyc3.cdn.digitaloceanspaces.com` and `stackapella-private.nyc3...` in contract
  examples. All public already (the CDN serves pages). Fine.
- **`.do/app.yaml`:** our App Platform spec (repo, region, instance sizes, workers). Keep it: it's
  a working example of a production deploy. Update its `repo:` lines if the repo moves (4.6).
- **Defaults that assume stackapella.com** (fine for us, wrong for a self-hoster; fixed in 5.5):
  `config/site.php` `contact_email`, `founder`, `social`; `config/mail.php` (the mudeo and from
  addresses); `config/ops.php` `security_contact`; `config/services.php` the VAPID subject;
  `config/hosts.php` comments; mobile `lib/app/config.dart` `apiBase` (correct for our apps).
- **Local paths:** only `docs/mudeo-transition-plan.md` names `~/.config/stackapella/`, removed by
  the redaction in 4.2. The `~/Code/...` mentions in docs are harmless.

### 4.4 TODOs naming people

None. The web repo has no `TODO` or `FIXME` at all. The mobile repo has four, all from Flutter's
templates (the application id, the release signing config, two CMake notes). Hillel is named
throughout the docs and comments as the person who decided; that's intended.

### 4.5 Branches and stashes

`agent/backend`, `agent/frontend`, `agent/landing`, `api-skip` and three stashes exist only
locally. They're clean (1.1), but they never go to the public repo. Delete the merged ones after
launch.

### 4.6 The git history: keep it, or start fresh?

**Recommendation: publish one initial commit in new public repos, and keep today's repos private
as the archive.**

| | Keep the history (flip the repos public) | One commit in new public repos (recommended) |
|---|---|---|
| Secrets | Safe: the scan is clean | Safe |
| What we redact in 4.2 | Still readable in history: mudeo counts, cost model, Radar rules, lawyer notes, launch drafts, earlier legal drafts, the two songs | Gone from the public record |
| Removing anything later | Needs a history rewrite (git-filter-repo isn't installed), and GitHub can keep serving force-pushed-away commits by their SHA | Nothing to rewrite |
| Blame and bisect | Kept, publicly | Kept privately in the archive; public history starts at launch |
| The story | 674 commits in three days, visible | One "Stackapella is open source" commit; `docs/decisions.md` still explains every why |
| Effort | Flip a switch | New repos, repoint App Platform's source, re-point local clones |

Three days of history has little archaeology value for anyone else, and the archive keeps it for
Hillel. If Hillel would rather keep it, the condition is that every item in 4.2 is acceptable to
have been public, because redacting it now won't remove it from history.

**How (on launch day, step 6.5):**

1. On a clean `main`, after the hygiene commits: `git archive` (or a fresh `git init` and copy of
   the tracked files) into a new directory, one commit: "Stackapella is open source".
2. Create `stackapella/stackapella` (and `-mobile`) **private** first, push that commit, check the
   tree on GitHub, then make it public.
3. Rename today's repos to `stackapella-archive` and `stackapella-mobile-archive` (private).
4. Point App Platform's three `github.repo` entries in `.do/app.yaml` (and the console) at the new
   repo, deploy once, and check the beta.
5. Re-clone or re-point every local clone and worktree (`git remote set-url`), including the
   agents'.

---

## 5. The pages to build at launch

Modelled on Event Schedule's (`resources/views/marketing/open-source.blade.php`,
`selfhost.blade.php`, `saas.blade.php`, `white-label.blade.php`, `self-hosting-terms.blade.php`,
routed in its `routes/web.php` around lines 1422 and 1569 to 1573). Its patterns worth copying:

- one honest page per question (what's open, how to run it, the terms), linked to each other;
- `IS_HOSTED` (`config('app.hosted')`) as the single switch: off means every paid feature is on;
- real code quoted on the page by a small helper (`App\Utils\SourceExcerpt::take()`), so the claims
  can't drift from the code;
- a "what a fresh install connects to" list, and an honest "should you self-host?";
- `SoftwareSourceCode` structured data with the licence and the repo.

### 5.1 Hidden until launch

**Built 2026-10-27 (landing agent), hidden:** the flag, the three pages, the mentions and their test (`tests/Feature/Site/OpenSourceTest.php`). Turn it on only after 5.5 and the blockers above.

- **A new flag,** `open_source` (`kind: feature`, off), in `server/config/flags.php`.
- While it's off, the three pages answer **404**, not "Coming soon" (`config/site.php`'s pattern
  indexes the page and shows a label, which would announce it early). No nav, footer or sitemap
  link, and no mention on `/press` or `/about`.
- Turned on in admin on launch day, after the repos are public (6.7). No deploy needed.
- The paths join `Hosts::PUBLIC_PATHS` (`open-source`, `selfhost`; `legal/*` is already there).

### 5.2 `/open-source`

Route: `GET /open-source` in the public group of `server/bootstrap/app.php`, served by
`SitePagesController@openSource` (no session, `throttle:public-pages`, cacheable), view
`site/open-source.blade.php`, words in `server/lang/en/site.php`. The outline:

1. **Stackapella is open source.** The server, the web app and the native apps, under the
   Attribution Assurance License: use it, change it, run it, even sell it, as long as the credit
   stays. Buttons: View on GitHub, Run it yourself (`/selfhost`).
2. **What's open, and what isn't.** Open: all the code. Not open: the songs, the course, the
   guides, the singers' voices and the brand, and why (they're how the hosted Stackapella pays for
   itself). Link to `CONTENT-LICENSE` and `TRADEMARK.md`. Say it up front; HN will ask.
3. **How it works, in the code** (quoted with an excerpt helper like Event Schedule's): every part
   scheduled on the AudioContext clock (`web/engine/engine.js`); the recorder worklet taking
   exactly the frames asked for (`web/engine/recorder-worklet.js`); the timing check's chirps and
   cross-correlation (`web/js/dsp.js`); pitch on the device (YIN in a worker).
4. **Quiet by design.** No analytics, no telemetry, no licence check. What a fresh install
   connects to (the README list, 6.3).
5. **No npm.** Libraries from a CDN, pinned with SRI; Tailwind's standalone binary; templates
   compiled in the browser. The trade-offs, briefly.
6. **The licence in plain words.** What the AAL asks (the credit; not using the name to promote a
   fork), and that it's OSI-approved.
7. **Get involved.** Issues, the security policy, `CONTRIBUTING.md`. Links to `/selfhost` and the
   self-hosting terms.

### 5.3 `/selfhost`

Route: `GET /selfhost` (with 301s from `/self-host` and `/self-host-stackapella`),
`SitePagesController@selfHost`, view `site/selfhost.blade.php`. The outline, every line true on
the day (5.5 makes it so):

1. **Run Stackapella yourself.** Every Plus feature for every account, free, on your own server.
2. **What you need:**
   - PHP 8.3 or 8.4 with `gd`, `intl`, `pdo_mysql`, `bcmath`, `pcntl` and `zip`; Composer.
   - MySQL 8.4 (MySQL only; SQLite and MariaDB aren't supported).
   - ffmpeg (takes, shared harmonies and share videos are encoded on the server).
   - **HTTPS.** The browser only gives a page the microphone, AudioWorklet, the service worker,
     passkeys and push notifications in a secure context. `localhost` counts, for trying it out;
     anything else needs a certificate.
   - **S3-compatible object storage** with CORS and presigned uploads (DigitalOcean Spaces, AWS S3,
     Cloudflare R2, MinIO): three buckets or prefixes for media, private files and backups.
     (Local-disk uploads are dev-only today; see 5.5.)
   - **Two background processes:** the scheduler (`php artisan schedule:work`, or cron running
     `schedule:run` every minute, which also drains the `default` queue), and a resident worker
     for the `long` queue: `php artisan queue:work database_long --queue=long --timeout=300`.
   - Mail: any Laravel mailer (SMTP, SES, Postmark, Resend, Cloudflare).
   - Size: our beta runs on 1 vCPU and 1 GB for the web, 1 GB for the long worker and 0.5 GB for
     the scheduler.
3. **Install:** clone, `composer install`, `cp .env.example .env`, set the database, storage and
   mail, `php artisan key:generate`, set your own `SQIDS_ALPHABET`, `php artisan migrate`, point
   the web root at `server/public`, start the two processes, then `php artisan app:doctor` to see
   what's left (the readiness checks already exist).
4. **What's unlocked:** Plus for everyone (the songs library, projects, storage and sharing limits
   you set in `config/usage.php`), and the admin with its network features panel (gallery,
   leagues, virtual choir and the rest, each a flag you turn on).
5. **What's not included, honestly:**
   - The official songs and course are under the content licence: a non-commercial install may use
     them unmodified; a commercial one brings its own (Stackapella's creator makes songs).
   - The real singers' guide voices: your guides are the synthesized voice.
   - Real instruments: off until you upload CC0 sample sets (`app:instruments:upload`; sources in
     `docs/instrument-samples.md`).
   - The native apps talk to stackapella.com; on your server people use the web app (it installs
     from the browser).
   - Paid Plus, the app stores and the coaching marketplace are Stackapella's business, and stay
     off.
6. **Bring your own, optionally:** Google and Apple sign-in, Turnstile, Sentry (your own project),
   web push (`php artisan app:reminders:keys`), Event Schedule and Invoice Ninja connections,
   X and Bluesky posting. Each stays out of the way until its keys are set.
7. **Yours to run** (Event Schedule's checklist idea): HTTPS and renewals; backups and a restore
   test (`backup-database`, `backup-drill`); updates; mail deliverability; moderation if you open
   sharing; your own terms and privacy policy (voices are personal data, and your users may be
   13 or older).
8. **For choirs and schools.** One install for a whole choir, school or federation: the director
   makes a group, shares songs, and the singers practise their parts. This is where `/saas`'s
   audience is served (5.4).
9. **Should you self-host?** Honestly: most singers shouldn't; stackapella.com is free to start.
   Self-host when the recordings must stay on your own servers, or you're building on it.
10. Links: GitHub, the self-hosting terms, the README's install section.

### 5.4 `/saas`: not at launch

Event Schedule's `/saas` works because it's a multi-tenant platform (a subdomain per customer,
operator branding through `APP_LOGO_*`, operator billing), and its value is the software.
Stackapella is different:

- it's single-tenant, with no per-customer subdomains or operator billing;
- a choir organisation or music school is served by **one** install with groups, which is
  self-hosting, not SaaS;
- most of its value for a paying customer is the songs and the course, which aren't licensed for
  commercial hosting;
- audio storage, ffmpeg and microphone support make a hosted business costly to run well.

So: **no `/saas` page at launch.** `/selfhost` gets the "For choirs and schools" section instead.
Revisit if organisations ask to run it for others; that would need tenants, operator branding, and
a commercial content licence, each its own plan.

### 5.5 Before `/selfhost` can go live: the code it needs

Each is a normal backend or frontend task with tests, and the claims test (`docs/features.md`)
covers the new claims:

1. **`IS_HOSTED`** (`config('app.hosted')`, true only on our deploys). When it's off,
   `App\Support\Usage\Plans::plan()` answers `plus` for everyone (it's already the one place that
   decides), and the Plus, checkout and store screens are hidden.
2. **Uploads without S3,** or state S3-compatible storage as required (the page above does, for
   launch). Local uploads in production would replace `routes/uploads.php`'s dev-only stand-in.
3. **Gate `app:check-cloudflare-ranges`** behind a `BEHIND_CLOUDFLARE` setting (off by default),
   so a fresh install never calls out on its own.
4. **The operator's legal pages:** on a self-hosted install the legal routes show the operator's
   own texts (a `resources/legal/local/` override, or admin, as Event Schedule's LegalController
   does), with a plain "write your own terms" page by default, never our drafts naming Invoice
   Ninja LLC.
5. **Our addresses become settings:** the contact, security and from addresses, the VAPID subject,
   the founder and social links. The mudeo email, early access and the beta stay off unless
   configured.
6. **The AAL credit:** a small "Stackapella" credit linking to stackapella.com in the public pages'
   footer and the app's About, which a self-hosted install keeps (as Event Schedule's guest-page
   credit). The apps' About shows it too.
7. **Content-optional:** the app and the seeders work with the content folders deleted (3.1).
8. **Self-hosting terms text:** `server/resources/legal/en/self-hosting-terms.md`
   (`reviewed: false` until the lawyer), shown at `/legal/self-hosting-terms` once the flag is on.
9. **A README install section and a longer `docs/selfhost.md`.** (A release zip with `vendor/` and
   a setup wizard, as Event Schedule has, can come later.)

### 5.6 `/legal/self-hosting-terms`

Served by the existing `DocumentPagesController@legal` from a new Markdown file, gated by the flag
until launch. Event Schedule's ten clauses, adapted (the lawyer reviews; the party is whoever
decision 8 names):

1. **As is.** The code is under the AAL; these terms cover only our relationship around it
   (support, connected services, releases). You run it as is, with all faults.
2. **Data ownership.** Stackapella can't access, change or remove anything on your install. Your
   users' recordings are theirs and yours to look after.
3. **Changes to these terms,** with notice; the AAL for a version you have is permanent.
4. **Eligibility.** Operators are 18 or older and follow the law where they are.
5. **Your responsibilities.** Credentials, backups, privacy law (voices are personal data), the
   age rules for your users, and copyright in what your users upload.
6. **Support.** You support your own users; we answer issues on GitHub as we can.
7. **Data use.** Applies only to data your install sends us, and by default it sends none: no crash
   reports, no update checks, no listings. (The table on the page lists what crosses: nothing from
   your server; from your users' browsers, jsDelivr and Google Fonts.)
8. **Content and the name.** The songs, course and guides are under `CONTENT-LICENSE`; the name
   and mark under `TRADEMARK.md`.
9. **Sanctions and restricted use,** as Event Schedule's.
10. **Indemnity and liability,** as the AAL's disclaimer. Contact: contact@stackapella.com.

### 5.7 The credit link: what's required, and what's asked (draft, 2026-10-29)

Hillel: "make sure it's clear there are links in the app that are kept, but there is also a public
backlink on their web site (we appreciate dofollow), if possible/allowed/a good idea. The reason for
AAL SaaS is the backlink, it does us less good if Google can't see it and we don't let SaaS use our
marketing. Try to solve this nicely."

**What Google allows.** Google's spam policies (developers.google.com/search/docs/essentials/
spam-policies, "Link spam", last updated 2026-08-28) list as link spam: "Requiring a link as part of
a Terms of Service, contract, or similar arrangement without allowing a third-party content owner
the choice of qualifying the outbound link, should they wish." So we **may require a visible credit
link**, as long as the operator **may** mark it `rel="nofollow"` if they want; and we may **ask**
for a normal (followed) link, but never make it a condition. A required followed link could hurt
both sites' standing in search.

**The three rules, for anyone running an install for other people:**

1. **Keep the credit in the app (required, by the AAL).** Clause 2 asks for a prominent display of
   the attribution (Hillel Coren, the professional identification and https://stackapella.com) each
   time the program runs. On a web install that's the "Powered by Stackapella" credit in the app's
   About and in the footer of its public pages (shared harmonies, song pages, sign-in): kept, visible,
   linked, and not hidden from visitors or search engines (no `display: none`, no robots block, no
   login in front of the public pages that carry it). This is also crawlable from their domain, which
   is most of the value Hillel wants.
2. **One public credit link on your own website (required for a service run for others; see the
   lawyer's question below).** A single link to https://stackapella.com/ on the operator's public
   website (the footer, an About or credits page), visible to visitors and crawlers. **You choose
   whether to qualify it** with `rel="nofollow"`; we'd be grateful if you leave it as a normal link.
3. **Our marketing stays ours (required, by `TRADEMARK.md` and `CONTENT-LICENSE`).** Use our name as
   the credit ("Powered by Stackapella", "runs on Stackapella"), never as your brand; and don't use
   our site's words, screenshots, logo, showreel or singers' voices in your own marketing. Why, in
   one friendly line: people should never mistake your service for ours, or ours for yours.

**The box for `/selfhost` (and `/saas`, when it exists):**

> **Running your own? Here's the deal.**
> - Keep the "Powered by Stackapella" credit in the app and on its public pages. It's what the
>   licence asks for, and it's how people find the project.
> - If you run it for other people, add one link to stackapella.com on your own website, in the
>   footer or on your About page. You can mark it `nofollow` if you like; we'd be grateful if you
>   leave it as a normal link.
> - Call your service your own. Say it's "Powered by Stackapella", but please don't use our name as
>   your brand, or our words, screenshots, logo or videos in your marketing.
>
> The credit, ready to paste: `<a href="https://stackapella.com/">Powered by Stackapella</a>`

**Changes to the other drafts:**

- `TRADEMARK.md` (3.3), add to **You may:** say "Powered by Stackapella"; and to **You may not:** use
  stackapella.com's words, screenshots, logo, showreel or voices in your own marketing.
- `server/resources/legal/en/self-hosting-terms.md`: a new section, "Credit, and our marketing",
  saying the three rules as above (drafted with the backend, `reviewed: false`).
- 5.5 item 6 (the AAL credit in the footer and About) is what makes rule 1 true in the product. It
  renders as a plain crawlable link with no `rel` (`components/site/footer.blade.php`, 9eff3b52).

**For the lawyer (decision 8's party):**

1. Rule 2 isn't in the AAL's text, and the self-hosting terms say they don't change the licence. Can
   a website credit be **required** of someone running a service for others, and how: as a term of
   the self-hosting terms (which an operator may never accept), as a condition of using the content
   (`CONTENT-LICENSE`) or the name ("Powered by Stackapella" under `TRADEMARK.md`), or only asked?
   If it can't be required cleanly, the drafts say "We ask that you put one link..." instead, and
   rule 1 (which the AAL does require, and which already puts a crawlable link on their domain)
   carries the weight. The backend's point (2026-10-29): read as a condition on using the code, a
   required website link could clash with the AAL being an OSI licence (no extra restrictions), so
   the safe home for it is the trademark policy ("Powered by Stackapella") or a request.
2. Does "each time the resulting executable program is launched" cover a web service's public pages
   (our reading: yes, every page load is a launch of the program the visitor uses)?

---

## 6. Launch-day checklist

### Before the day (the week before)

- [ ] Hillel answers the decisions at the top; each is logged in `docs/decisions.md`.
- [ ] The lawyer reviews `CONTENT-LICENSE`, `TRADEMARK.md`, the self-hosting terms, and the two
      songs (2.5). Uncleared songs come out of the tree.
- [ ] Confirm production's `SQIDS_ALPHABET` isn't the default (1.4).
- [ ] The code in 5.5 built and tested; the three pages built behind `open_source`; the claims test
      passing.
- [ ] The hygiene commits: fixtures (4.1), docs redacted or removed (4.2), `web/spike_audio/`
      deleted, the legal front matter stripped, the Flutter skills' licence checked or `.claude/`
      left out of the mobile repo.
- [ ] Every screenshot and share image looked at once (1.3).
- [ ] `php artisan test --compact`, `vendor/bin/pint`, `vendor/bin/phpstan analyse` and the web's
      headless-browser tests green; `flutter analyze` and `flutter test` green in the mobile repo.

### On the day, in order

**6.1 Final scans.** Freeze `main`. Run 1.5 on both repos and on the exact tree being published.
Expected: only the known placeholders. `git ls-files` read top to bottom once for anything that
shouldn't be there. Anything new stops the launch.

**6.2 The licence files** (one commit per repo, "Licence: Stackapella is open source under the
AAL"):

- `LICENSE`: Event Schedule's AAL text with our attribution:

  > Copyright (c) 2026 by
  >
  > Stackapella * https://stackapella.com
  >
  > ATTRIBUTION ASSURANCE LICENSE (adapted from the original BSD license)
  >
  > ... These conditions require a modest attribution to Stackapella (the "Author") ...
  >
  > (a) Name ("Hillel Coren"),
  > (b) Professional identification ("Stackapella"), and
  > (c) URL ("https://stackapella.com").

  The rest word for word from `~/Code/eventschedule/LICENSE`. Clearsigned with Hillel's GPG key
  as `LICENSE.asc` if decision 5 says so. The same in the mobile repo.
- `CONTENT-LICENSE` (3.2) and `TRADEMARK.md` (3.3).
- `THIRD-PARTY-NOTICES.md` (2.6), with SecLists' MIT text in full.
- `SECURITY.md`: report privately to security@stackapella.com (the address `security.txt`
  already serves) or GitHub's private vulnerability reporting; what's in scope (the code, our
  hosted service); please don't test against other people's data; we reply within a few days.
  Invoice Ninja's `SECURITY.md` is the model, with OWASP's disclosure link.
- `CONTRIBUTING.md`: the house rules from `CLAUDE.md` and `docs/conventions.md` that matter to an
  outsider (no npm, MySQL only, the tests to run, "Area: outcome" commits, no em dashes in text),
  inbound = outbound under the AAL; the courses take pull requests under `CONTENT-LICENSE`, the other
  content folders none.

**6.3 The README** (both repos), modelled on Event Schedule's:

- A badge under the title: `license-AAL-blue` linking to `LICENSE`.
- **Run it yourself**, near the top, in Event Schedule's bullet style:
  - **Everything is unlocked.** Every Plus feature, for every account. No licence keys and no
    plans.
  - **No telemetry.** No analytics, no usage reporting, no licence check (below).
  - **Bring your own.** Storage, mail, sign-in, Sentry: each an environment variable, out of the
    way until set.
  - **Or skip the server.** The same app runs at [stackapella.com](https://stackapella.com), free
    to start.
- **Self-host:** the requirements and install steps from 5.3, linking to `/selfhost` and
  `docs/selfhost.md`. (The existing "Running the server" section stays for development.)
- **What a fresh install connects to**:

  > There's no usage reporting and no licence server. Left as installed, the server makes no
  > outbound request on its own. Everything else is opt-in:
  >
  > - Your storage, mail provider, Google and Apple sign-in, Turnstile, Sentry, Stripe, X,
  >   Bluesky, Event Schedule and Invoice Ninja are contacted only after you add their keys.
  > - Practice reminders go through the browsers' own push services, only for people who turn
  >   reminders on.
  >
  > Your users' browsers load Vue (and lamejs, when exporting an MP3) from jsDelivr, pinned to an
  > exact version with an SRI hash, and the Figtree and Noto Music fonts from Google Fonts.

  (True once 5.5 item 3 gates the Cloudflare check.)
- **Content:** what's under `CONTENT-LICENSE` and `TRADEMARK.md`, by path (3.1).
- **Licence:** "Stackapella is licensed under the [Attribution Assurance License (AAL)](LICENSE),
  an [OSI-approved](https://opensource.org/license/aal) BSD-style licence with an attribution
  requirement. In practice, a self-hosted install keeps a small Stackapella credit linking back to
  the project. The songs, course, guides and images are under [CONTENT-LICENSE](CONTENT-LICENSE),
  and the name and mark under [TRADEMARK.md](TRADEMARK.md)."
- Add this plan to the README's docs guide, and update "Status" (it still says templates are 8
  drafts and "projects are saved in the browser until the API exists").

**6.4 Green, then push.** All the tests green in the same command as the push (as every beta push
is). Push to the private repos as usual.

**6.5 Make the code public.** Following 4.6: the fresh repos created private with one commit,
checked on GitHub, then made public; today's repos renamed to `-archive`; App Platform's source
repointed and one deploy checked. (If decision 1 keeps the history instead: flip both repos to
public.) Check both repos from a signed-out browser.

**6.6 GitHub settings** (both repos):

- **About:** description "Learn harmony and stack your own voice in the browser. Laravel, Vue
  (no npm) and Web Audio." (mobile: "Stackapella's iOS, Android and desktop apps, in Flutter.");
  website `https://stackapella.com/?ref=github`; topics `a-cappella`, `harmony`, `singing`,
  `choir`, `music-education`, `web-audio`, `audioworklet`, `pwa`, `laravel`, `vue`, `self-hosted`
  (mobile: `flutter`, `dart`, `audio`, `ffi` and the music ones); a social preview image from
  `web/icons/og`.
- **Features:** Issues on; Wiki and Projects off; Discussions off at first (the inbox is enough).
- **Security:** `SECURITY.md`; private vulnerability reporting on; Dependabot alerts and security
  updates on; secret scanning and push protection on.
- **`.github/dependabot.yml`**, as Event Schedule's (`open-pull-requests-limit: 0`, so only
  security updates open PRs): `composer` in `/server`, `github-actions` in `/`; `pub` in the
  mobile repo. No `npm` entry, ever. The CDN pins stay with the weekly `outdated` workflow.
- **Issue templates** (`.github/ISSUE_TEMPLATE/`): a bug report asking for browser and version,
  device, headphones or speakers, the mic and its route, and the timing check's result; a feature
  request; `config.yml` with blank issues off and links to contact@stackapella.com for account
  help and to `SECURITY.md` for vulnerabilities. A short pull request template (the tests run,
  the area, the outcome).
- **Branch ruleset on `main`:** block force pushes and deletion; only Hillel may push directly
  (keeps "small commits straight to main"); pull requests from others need the `test` workflow
  green and Hillel's review.
- **Actions:** default workflow permissions read-only (the workflows already declare
  `contents: read`); require approval to run workflows from first-time and outside contributors;
  pin third-party actions by commit SHA.

**6.7 The pages.** Turn on `open_source` in admin (with its reason, as every flag change). Check
`/open-source`, `/selfhost` and `/legal/self-hosting-terms` on a phone and a desktop, signed out;
the footer link; the sitemap; links from `/about` and `/press`.

**6.8 The launch kit tie-in** (`docs/launch-kit/`, wherever it then lives):

- **Show HN** (`show-hn.md`): a short paragraph before "What I'd like to hear about": "The code is
  open source under the AAL (github.com/stackapella/stackapella), and you can run it yourself
  (stackapella.com/selfhost). The songs and the course aren't open; they're how the hosted
  version pays for itself." Title option: "Show HN: Stackapella, learn harmony and stack your own
  voice in the browser" (unchanged; HN readers find the repo from the post). Ready answers for:
  why AAL and not MIT or AGPL; why the songs aren't open; no npm; self-hosting without S3.
- **Product Hunt:** add the GitHub link and the "Open Source" topic.
- **Social:** one post in each launch thread linking the repo.
- **Press:** one sentence in the 100-word description ("open source under the AAL").
- **Communities:** a post for r/selfhosted on the day, linking `/selfhost?ref=reddit-selfhosted`
  (add the ref to the launch kit's table). awesome-selfhosted later: its rules want a project
  released a few months back, so note it for then.
- The kit's rules still hold: only true claims, no numbers, no em dashes.

**6.9 After.** Log the launch decisions in `docs/decisions.md`; update `docs/roadmap.md`; change
this plan's status to "done" with the date; watch the issues and the security inbox daily for the
first week; delete the merged local branches.

---

## 7. The tooling for the day (built 2026-10-28)

Built by the backend agent after Hillel's nine answers; nothing is published, no org or remote
exists, and nothing was pushed.

- **`server/tool/open-source/export.py`** makes the public tree as one commit in a new local repo
  (4.6, step 1): the tracked files at a commit, minus 4.2's drops (`pricing-research.md`,
  `radar-rules.md`, `abuse-test.md`, `launch-kit/`, `web/spike_audio/`), with 4.2's redactions
  (mudeo's counts, the first and future prices, the cost model, the billing alert's amounts), then
  that copy's leak check. The lists are `server/tool/open-source/public-tree.json`; each redaction
  must match exactly as often as it says, so a doc rewritten since stops the export instead of
  going out unredacted. Links to dropped docs are tidied (list items go, "Back:" links are cut,
  others keep their words) and plain mentions are listed for a person to judge.
  `--strip-lawyer-notes` takes the `for_lawyer` notes out of the legal texts, only once the
  lawyer has them. It never adds a remote or pushes.

  ```bash
  python3 -I server/tool/open-source/export.py ~/Code/stackapella-public --commit main
  python3 -I server/tool/open-source/export.py ~/Code/stackapella-public --commit main --strip-lawyer-notes --strict
  ```

  It ends with what's still missing (`LICENSE.asc`, `TRADEMARK.md`, `THIRD-PARTY-NOTICES.md`,
  `SECURITY.md`) and the reminders before publishing. **`LICENSE.asc` is Hillel's step**
  (decision 5): `gpg --clearsign --output LICENSE.asc LICENSE` with his key, committed beside
  `LICENSE`. The mobile repo can use the same script with its own rules (`--source`, `--rules`).
- **`CONTRIBUTING.md`:** inbound = outbound under the AAL (decision 7), the courses' issues and pull
  requests under `CONTENT-LICENSE` (how to propose a fix, who reviews it), no pull requests to the
  other content folders, the house rules and the tests to run.
- **The README's "Running it yourself"** section: the requirements and install steps of 5.3,
  what a fresh install connects to (the CDN libraries said plainly, decision 6), content and the
  name, and the 5.5 items still to come, so it says only what's true today.

## 8. Section 5.5, built (2026-10-28)

Built by the backend agent, tested in `server/tests/Feature/Ops/SelfHostTest.php`; stackapella.com
is unchanged (every setting is off unless set):

1. `SELF_HOSTED=true` (`server/config/selfhost.php`) gives every account Plus (item 1). Hiding the
   Plus and checkout screens is still the frontend's.
2. Item 2 stays as the README says it: S3-compatible storage is required.
3. `BEHIND_CLOUDFLARE` gates the Cloudflare check: unset, on unless self-hosted (item 3).
4. Self-hosted, the legal pages are the operator's (`resources/legal/local/`), or say they aren't
   written yet (item 4).
5. Our contact, founder and social links are `SITE_*` settings; the mail addresses, the security
   contact and the VAPID subject already were (item 5). The help and site words that name
   `contact@stackapella.com` in a sentence are the landing agent's.
6. Item 6 (the AAL credit in the footer and About) isn't built yet.
7. The app and the demo seed run without the content folders (`server/config/content.php`, item 7).
8. Items 8 and 9 (the self-hosting terms page and the README) were done before.

---

[Docs guide](../README.md#a-guide-to-the-docs)
