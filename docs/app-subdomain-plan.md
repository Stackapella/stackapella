# The app moves to app.stackapella.com

**Status:** planned 2026-10-27, being built (backend first, then the frontend's half). From Hillel's
decision "the app moves to app.stackapella.com" ([decisions.md](decisions.md)).

## What goes where

| Host | Before launch | After launch | What it serves |
|---|---|---|---|
| **The app** | `app.stackapella.com` | the same | The PWA and its pages, sign-in (`/auth/*`, `/signin`), the admin, the API, the email links (verify, reset, invites, `/early/<code>`, `/preview/<code>`, `/notice`) |
| **The public site** | `beta.stackapella.com` | `stackapella.com` | The landing (`/`), `/pricing`, `/help/*`, `/songs/*`, `/guides/*`, `/legal/*`, shared harmonies (`/h/*`) and their embed and oEmbed, the coach pages, `/@<handle>` and `/explore`, `sitemap.xml`, `robots.txt`, `security.txt`, the mudeo email's `/unsubscribe/*` |
| **Both** | | | `/up`, the webhooks (Stripe, Stripe Connect, the mail Worker, Event Schedule, Invoice Ninja: their URLs already point at the beta host), `/log/errors`, and, for a while, the API (see "Old installs") |

One App Platform app and one Laravel app serve both hosts. The routes say which host they belong
to.

## Settings

- `APP_URL=https://app.stackapella.com`: the app's address, so every URL Laravel makes (email
  links, OAuth callbacks, upload links) is on the app's host.
- `PUBLIC_URL=https://beta.stackapella.com` (`https://stackapella.com` at launch): the public
  site's address, for its routes, canonical URLs and the sitemap.
- **Unset, or the same as `APP_URL`, everything is on one host, as today.** Local development and
  the tests run that way unless a test splits them.
- `SESSION_DOMAIN` stays unset: the session and `XSRF-TOKEN` cookies belong to the app's host
  alone.
- `BETA_PASS_DOMAIN=.stackapella.com`: the beta pass is the one cookie both hosts read (below).
- `APP_REDIRECT_HOSTS` is unchanged: other names (www, an old domain) go to the public site.

## How each host behaves

- **On the public host,** a request for an app page (anything the app shell serves, `/admin`,
  `/auth/*`, `/signin`, the email links) is redirected to the app's host with its path and query:
  a 301 for GET, a 308 otherwise. Old installs are the exception (below).
- **On the app's host,** a request for a public page is redirected to the public host the same
  way. `/` on the app's host is the app, never the landing. `robots.txt` there says
  `Disallow: /`, and its pages are never in the sitemap.
- **The landing is always `/` on the public host.** The "first visit" rule and the `sa_app` marker
  stop mattering. Its links into the app (Start singing, Open the app, Learn this harmony, Try
  Plus) are absolute URLs on the app's host, made by the server (each view model's `links`, the
  song page's `startUrl`).
- **Before launch** (`COMING_SOON`): the app's host keeps the gate and the beta pass, as now. The
  public host's other pages stay as they are (search engines are kept out by `SITE_INDEXING`).
  **Suggested:** its `/` shows coming soon to anyone without the beta pass, and the landing to
  anyone with it. That's why the pass cookie is set for `.stackapella.com`, so both hosts read it.
  It holds nothing but a signed expiry.
- **Content-Security-Policy:** the app's policy on the app's host. The pages' policy on the public
  host, whose `connect-src` adds the app's origin (for Report). Each host's `frame-ancestors` as
  today.

## Report from a shared page

The public page can't read the app's `XSRF-TOKEN` cookie, so it can't send the CSRF header.
**Decided:** `reports.create` (and `auth.config`, for the Turnstile key) accept a cross-origin
request from the public host's origin only. That's CORS with no credentials, and CSRF isn't
needed for that one route. The limiter, the honeypot and shield mode's Turnstile still apply.
Such a report comes in signed out (counted by network), as a visitor's always has.

## Old installs

A PWA installed from `beta.stackapella.com` keeps its songs in that origin's storage (IndexedDB),
which `app.stackapella.com` can't read. **Songs that were never saved to an account would be left
behind by a plain redirect.** So:
- **the API keeps answering on the public host for now** (its session cookie is that host's own),
  so an old install keeps working until it moves;
- **an app page asked for on the public host** gets a small "Stackapella has moved" page, which the
  frontend builds: it offers to bring the songs along (the old origin's data handed to the new one,
  for example through a window opened on the app's host and `postMessage`, or a download and
  re-import), then sends the person to the app's host;
- **once the beta's installs have moved** (Hillel's call, after a few weeks), the API on the public
  host and the moving page give way to plain redirects.

## The frontend's half (agreed with stackapella-97)

- the manifest's `start_url` and `scope` on the app's host (the manifest is served from there);
- the moving page, and moving each device's songs to the new origin;
- the public pages' markup using the absolute app links the server gives, and Report posting to the
  app's origin without credentials;
- no more `sa_app` marker (it can stay; it does no harm);
- the service worker on the old origin unregistering itself after the move.

## Done when

- each host's routing is tested: app pages on the public host redirect (apart from the old
  installs' API and moving page), public pages on the app's host redirect, the landing is at the
  public root, robots and the sitemap are right per host;
- email links, OAuth callbacks and upload links are on the app's host, and share links,
  unsubscribe and canonical URLs on the public host;
- Report works from a shared page across origins;
- the env is set in one deploy, and Google's and Apple's redirect URIs on the app's host are added
  (Hillel, with 9c).

---

[Back: Decisions](decisions.md) · [Docs guide](../README.md#a-guide-to-the-docs)
