# Branding matrix

What an install of Stackapella serves, by the two settings that decide it. Keep it in sync with
`config/app.php` (`is_nexus`), `config/selfhost.php` and the `nexus` middleware's routes
(`server/bootstrap/app.php`).

| | stackapella.com (`IS_NEXUS=true`) | Your install (`IS_NEXUS=false`) |
|---|---|---|
| The app, its API, shared harmonies (`/h`, embeds), the gallery and coach pages | Yes | Yes |
| Help (`/help`), the legal pages, `security.txt`, unsubscribe links | Yes, ours | Yes (`SELF_HOSTED=true`: your own legal texts) |
| `/` | The landing (the app once you've used it) | The app |
| The landing, `/features`, `/for`, `/pricing`, `/about`, `/press`, `/whats-new`, `/songs`, `/guides`, `/open-source`, `/selfhost`, `sitemap.xml` | Yes | 404 |
| Our social posts of new songs | Proposed for Hillel | None |
| The licence credit (footer and Settings) | Behind the `open_source` flag | Always |

**Why:** the code is under the AAL, and you may run the app. Our marketing site (its pages, words,
images and the Stackapella brand) isn't part of what's licensed for you to serve
([TRADEMARK.md](../TRADEMARK.md), [CONTENT-LICENSE](../CONTENT-LICENSE)), so it's off unless
`IS_NEXUS` is on.

**`IS_NEXUS` unset** is worked out from the addresses: true when `APP_URL` or `PUBLIC_URL` is on
stackapella.com, or in local development and the tests; false otherwise. Setting it either way wins.

---

[Docs guide](../README.md#a-guide-to-the-docs)
