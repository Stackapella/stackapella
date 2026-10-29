# Security headers and the Content-Security-Policy

**Status (2026-10-27): reviewed by the backend agent after the day's changes** (Figtree from
Google Fonts, the glass canvases, webp screenshots, the media CDN for the real instruments, X and
Bluesky links). The headers come from `server/app/Http/Middleware/SecurityHeaders.php`;
`server/tests/Feature/Ops/SecurityHeadersTest.php` holds each kind of page to them.

## The three policies

| Page | Scripts | Also allowed | Framing |
|---|---|---|---|
| The public site and the server's own pages | `'self'`, and Cloudflare Turnstile (shield mode) | Google Fonts (styles and fonts), the media CDN (images and audio), `data:` images, Report posting to the app's host | Same origin; `/embed/*` by any https site; the admin never |
| The app (the PWA's shell) | `'self'`, the pinned CDN packages (each its own path, never the host), `'unsafe-eval'` for Vue's compiler, the shell's script hashes, Turnstile | Google Fonts; the media CDN (images, audio, and `connect-src` for the real instruments and the index); the private bucket for signed audio, cards and uploads; `blob:` workers, images and audio | Same origin; the admin never |
| The API | Nothing: `default-src 'none'` | | Never |

Every page has `object-src 'none'`, `base-uri 'self'`, `form-action` only to ourselves and Google's
and Apple's sign-in, `X-Content-Type-Options: nosniff`, `Referrer-Policy:
strict-origin-when-cross-origin`, `Cross-Origin-Opener-Policy: same-origin-allow-popups` (Google
and Apple sign-in popups), and HSTS for a year over https. The microphone is allowed only to the
app's own origin (the course's first lesson included); camera, location and payment never.

## What the review found

- **Today's additions fit the policy as it was:** Figtree is on fonts.googleapis.com and
  fonts.gstatic.com, both allowed; the glass canvases draw in the page (no `blob:` or workers on
  the public site); the webp screenshots are our own files; X and Bluesky are plain links and
  share popups, which need nothing; the real instruments are on the media CDN, already in the
  app's `connect-src`.
- **Fixed:** the app's `img-src` didn't allow the private bucket, so a harmony's own card (a
  signed private link, as admin's queue shows it) would have been blocked. It's allowed now, as
  the bucket already was for audio.
- **Kept, knowingly:** `style-src-attr 'unsafe-inline'` (the site's pages and Vue's `:style` set
  style attributes; style attributes can't run script). `'unsafe-eval'` in the app only, for
  Vue's template compiler (CLAUDE.md).
- **Left for Hillel:** HSTS `includeSubDomains` and preload would cover every subdomain of
  stackapella.com for a year, including any that might ever serve plain http (mail tracking,
  a third party's page). Worth it once the domain's subdomains are settled; one line to add.

---

[Back: The runbook](runbook.md) · [Docs guide](../README.md#a-guide-to-the-docs)
