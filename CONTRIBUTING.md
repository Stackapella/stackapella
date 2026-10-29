# Contributing to Stackapella

Thank you for helping. Stackapella is a small project with firm house rules: they keep the code
simple to run, quick to review, and safe for the singers who use it. Please read this before a
pull request.

## Before you start

- **Bugs:** open an issue with your browser and its version, the device, headphones or speakers,
  which microphone (and how it's connected), and the timing check's result if recording is
  involved.
- **Ideas and larger changes:** open an issue first, so we can agree on the shape before you
  write it. Product decisions are Hillel's, and they're logged in `docs/decisions.md`.
- **Security problems:** never in an issue. See `SECURITY.md`, or write to
  contact@stackapella.com.

## The licence of what you send

**Inbound = outbound.** By sending a pull request, you agree that your contribution is licensed
under this repository's licence, the Attribution Assurance License in `LICENSE`, and that you have
the right to license it so. There's no separate agreement to sign.

**The courses and songs welcome your fixes.** They live in their own repository,
hillelcoren/stackapella-courses, under CC BY-SA 4.0, and inbound = outbound there too: by sending
a pull request, you agree it's licensed under CC BY-SA 4.0. Its CONTRIBUTING says how. To propose a
fix:

- **Open an issue first** for anything bigger than a typo: what's wrong in which lesson (its id,
  for example `vib-1-laser`, and the step), and what you'd change. A singer's ear is as welcome as a
  teacher's.
- **Then a pull request** that changes only that course's files, one lesson or one fix at a time,
  saying what you heard or saw that was wrong and why your change is better. The course's file
  must still pass its checks (the web tests run them).
- **Who reviews it:** the course's maintainer reviews the music and the teaching, and Stackapella
  (Hillel) merges. A change that alters how a lesson is scored, or its step types, is a code change
  and follows the house rules below.

**The other content folders take no pull requests:** the guides and what's new
(`server/resources/guides/`, `server/resources/whats-new/`) and the screenshots and share images
(`web/icons/help/`, `web/icons/og/`), which are CC BY-SA 4.0 (`CONTENT-LICENSE`), and the legal
texts (`server/resources/legal/`, all rights reserved). A mistake in one is welcome as an issue.
The name and the mark aren't open either (`TRADEMARK.md`).

## The house rules

The full set is `docs/conventions.md`. The ones that matter most:

- **No npm, ever.** No `package.json`, no bundler. Browser libraries load from a CDN, pinned to an
  exact version with an `integrity` (SRI) hash; Tailwind is its standalone binary
  (`web/tool/tailwind.sh`).
- **No new dependency** (a Composer package or a CDN library) without a short written reason in
  the pull request, agreed first.
- **MySQL only**, tests included. No SQLite on the server.
- **No analytics or telemetry**, of any kind. No bulk or recurring email.
- **Audio is scheduled on the `AudioContext` clock,** never with `setTimeout` or `setInterval`.
- **Vue templates are strings in our own modules,** never built from data, and never `v-html` for
  someone's text.
- **Laravel:** `declare(strict_types=1)`, `final` classes, typed everything; thin controllers (a
  form request or policy, one action, an API resource); no `env()` outside `config/`; no route
  closures; no data-changing GETs; no empty `catch`; every model has a factory.
- **The API contract** (`contract/api.md`) describes every route; a change to it comes with its
  example and a line in `contract/log.md`.
- **Words:** every word the app shows comes from `web/locales/en.json` or `server/lang/`. Plain
  English, and no em dashes in the interface or the docs.

## Before you send it

From `server/`:

```bash
php artisan test --compact        # on MySQL; makes its own database
vendor/bin/pint                   # code style
vendor/bin/phpstan analyse        # Larastan, level 8
```

From `web/`:

```bash
tool/test.sh                      # the headless-browser tests
tool/flows.sh                     # the flow scripts, when you changed a screen
```

Turn on the commit hook once per clone (`git config core.hooksPath .githooks`). It needs
[gitleaks](https://github.com/gitleaks/gitleaks) 8.25 or later, and stops secrets, keys, data
dumps and recordings from being committed.

## Commits and pull requests

- Small, focused commits, each styled "Area: plain-English outcome", like
  `Studio: the count-in follows the song's tempo`. No co-author or tool trailers.
- One change per pull request, with what you tested and on which browsers or devices.
- Tests for backend logic, and a headless-browser test or flow for frontend behaviour.
- Keep the README's setup steps true if you change them.

The full setup is in the README ("Running the server" and "Running the app").
