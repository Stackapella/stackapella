# Pre-launch security review

**Status (2026-10-28): reviewed, and every finding fixed or left on purpose, by the backend agent.**
A review of the whole server, not just the latest batch, against roughly OWASP ASVS level 2, before
the 30 November go or no-go. Four independent reviewers, read-only, each took one area and
reported only what they had traced end to end through the code (route, middleware, request,
controller, action, query) with a concrete attack. Each fix below has a test.

## What was checked

| Area | What was looked at |
|---|---|
| Authorisation and IDOR | Every route (`php artisan route:list`): its middleware and the ownership check behind it, across songs and templates, projects, parts and takes, the bin, groups, harmonies and shares, ideas, choirs, coaching, beta, support, notices, exports, devices and push |
| Mass assignment | Every model's `$fillable`, and every `fill`, `create`, `update` and `forceFill` fed from a request |
| Files and audio | The presigned uploads (takes, harmonies, idea and choir voices, recordings, beta masters, review media, avatars), the checks on complete, every ffmpeg and ffprobe call, temporary files, WAV parsing, images, signed URL lifetimes, the app's own file serving |
| SSRF | Every outbound request: Invoice Ninja, Event Schedule, web push and app push, Stripe, the stores, Sign in with Google and Apple, Turnstile, X and Bluesky, oEmbed, Sentry, the Cloudflare checks |
| Authentication and sessions | Sign-up, sign-in, reset, verification, email change, sign-in links, Google, Apple and Event Schedule, passkeys, the admin's second factor and sudo, account deletion, app device tokens, session fixation, CSRF, the anonymous reads without a session, the beta pass |
| Rate limits | Every write, and every expensive or enumerable read; how a limiter's key is made, and whether it can be dodged |
| Injection | Email subjects, senders and headers; every `{!! !!}` and JSON-LD on the public pages; raw SQL and LIKE; CSV |
| Logs and secrets | Every log call, `report()`, the slow-query log, Sentry's scrubbing, hidden and encrypted attributes |
| Headers | The three Content-Security-Policies, HSTS, cookies, CORS |
| Webhooks | Stripe and Stripe Connect, the App Store and Google Play, Event Schedule, Invoice Ninja, the mail bounce Worker |
| Admin | Every admin route's role, second factor and sudo, and whether each change is audited |

**Found sound** (in short; the reviewers' notes are behind each line):
- **Authorisation:** no route changes data or shows private data without an ownership or role check. No IDOR was found across any of the areas above, and no mass-assignment path (sensitive columns are only ever set with `forceFill` from values the server works out).
- **Uploads:**
  - every object key is made by the server, at random;
  - a completed upload is copied to a key no link points at, then checked by size and SHA-256, then by ffprobe (WAV, 16-bit, the declared channels, rate and length) before anything is encoded;
  - every process call takes an argument list (no shell), and the share video draws all user text with GD (never ffmpeg's `drawtext`).
- **Outbound requests:** self-hosted Invoice Ninja and Event Schedule addresses must be public, `https` and pinned. Web push endpoints must be on an allowed push host, and redirects are off everywhere.
- **Sign-in:**
  - one answer for a wrong address or password, with the same work either way;
  - tokens stored as hashes, used once, with expiry;
  - accounts matched on the provider's id, never merged by email;
  - the session is regenerated at sign-in;
  - the admin needs three things (the role, the listed Google id, a fresh Google-verified email) plus a second factor, and sudo covers switches, limits, the blocklist, money, bans and deletes.
- **Webhooks:** every one checks its signature on the raw body (with a time window and deduplication where it can replay) before it has any effect.
- **Injection:** no SQL is built from input, no CSV is exported, and every public page escapes user text.
- **Logs:** the slow-query log never carries values. Sentry is scrubbed of bodies, cookies, headers, addresses and tokens, and secrets are encrypted casts and hidden attributes.
- **Headers:** the API's policy is `default-src 'none'`, the admin can never be framed, and no page allows inline script.

## What was found, and fixed

| # | Severity | Finding | Fix | Test |
|---|---|---|---|---|
| 1 | **High** | **A look-alike address found someone's account.** The database's collation ignores accents and width, so `victim@exämple.com` (or a full-width `ｅxample`) found victim@example.com's account. A reset, sign-in or verification link was then mailed to the address as typed: a domain the attacker can register. A reset or a sign-in link is an account takeover. The same cause gave each look-alike its own count of wrong passwords, and slipped past the blocklist | Every email column compares byte for byte (`utf8mb4_bin`; migration `2026_11_05_330000`); addresses were already lowercased on the way in. And every account email goes to the account's own address, never the one typed | `LookAlikeEmailTest` |
| 2 | Medium | **"Sign out everywhere" (or a new password) from the app left web sessions signed in**: an app's request runs on the array session driver, so ending sessions skipped the table | The real driver is kept beside the swap (`session.stored_driver`), and ending sessions uses it | `AppTokensTest` |
| 3 | Medium | **A choir voice with an offset of hours** (a take sent at 8 kHz with an offset of 10⁸ frames) made every rebuild of that choir's mix ask ffmpeg for hours of audio, failing on the long queue every hour | A voice placed outside any song (no song is longer than a take may be) is left out of the mix, and ffmpeg never gets one | `ChoirsTest` |
| 4 | Low | **The hourly purge deleted the copy a take was still being made from**, when it ran while the take waited on the long queue: the take failed for good | Only the copies of finished takes, voices and recordings go | `BinPurgeTest` |
| 5 | Low | **One network could keep someone out** by failing their password on purpose: 10 wrong passwords per address, from anywhere, locked everyone out for 15 minutes | Counted twice: per address and network (10 in 15 minutes, for that network), and per address everywhere (50 an hour), so the owner's own network still signs in, and guessing from many networks still stops | `SignInTest` |
| 6 | Low | **Every per-network limit started again at midnight** (the key held the date), so a daily limit allowed twice as much across it | The limiters key on the network's HMAC without the date (each key leaves the cache when its window ends anyway) | `LimitsTest` |
| 7 | Low | **A name with a line break** reached other people's email subjects | Names are refused with control characters (`OneLine`), a provider's name is cleaned, and the mail transport puts every subject and sender name on one line whatever reached it | `LookAlikeEmailTest`, `CloudflareMailTest` |
| 8 | Low, hardening | **ffmpeg guessed an upload's format from its bytes**, and an older ffmpeg reads a playlist named `.wav` as a playlist and fetches its URLs | Uploads are read only as WAV and only from local files (`-f wav`, `-protocol_whitelist file`) | `FfmpegInputTest` |
| 9 | Low | **Some admin changes weren't audited**: disconnecting Stackapella's books, retrying a books record, a support message's status, cancelling or retrying our posts | Each writes an audit entry with who and from where | `SupportAndNoticesTest`, `SocialPostsTest` |

## Left on purpose, or for someone else

- **The caps on account emails per address** (3 resets an hour, 6 sign-in links or verifications
  a day) can be used up by a stranger, so the owner's next one isn't sent for a while. Kept: the
  caps are what stop anyone using us to flood an inbox, and the owner can still sign in by
  password or with Google or Apple. Revisit if support hears of it.
- **Taking over a browser's reminder device** needs its push endpoint, a secret the push service
  gives only to that browser; a new account on the same browser taking it over is the real case.
- **An admin or editor flag on an account gives the songs free**, without the admin's three checks.
  It's free songs, not an admin power.
- **A held idea opens by its link**, as the contract says.
- **JSON-LD's escaping** (`resources/views/components/site/json-ld.blade.php`): it dropped Blade's
  `</script>` escaping. Nothing user-written reached it; **fixed** by the landing agent (3e6a793d,
  with `StructuredDataTest`), so a profile or library song added later can't break out of it.
- **Upload links signed without their length**: S3's presigned PUT doesn't sign the size, so a
  second PUT after completing can leave an object up to 5 GB until the bucket's rule removes it
  (`uploads/` after a day). **For Hillel:** a lifecycle rule on `beta/masters/` too (rejected
  masters are pruned by the app; abandoned ones aren't), and the review media's stuck uploads
  under `uploads/` are covered by the existing rule.

**For Hillel:**
- **HSTS `includeSubDomains`** (and preload) once the subdomains are settled:
  `docs/security-headers.md`.
- **The production check of the email sending path:** Cloudflare's Email API takes the address as
  given. Addresses with non-ASCII characters can't reach an account any more (finding 1), but a
  test send to an IDN address shows how Cloudflare encodes it.

---

[Back: Security headers](security-headers.md) · [Docs guide](../README.md#a-guide-to-the-docs)
