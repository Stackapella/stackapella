# Roadmap L audit (Operations, Legal duties): what's still open after 2026-10-28

Fixed and on main: refunds take Plus's own payment and refund once; saved replies; refund policy
store clause; security.txt on both hosts; secrets list and doctor checks; notice email facts and
redress; appeal decisions emailed; Sentry monitors; link previews; community-guide alerts.

## Frontend (97)
- No admin People screen: suspend, recover, trust, refund (Plus and coaching) exist only as API.
- No admin appeals queue or decide screen; no /notice?token= page; no in-app notices list or
  "Ask for a review"; the admin reason field says "kept in the audit log" but it's shown to the
  person as the notice's note.
- No network features panel screen (admin.network.show is ready); six network flags have no label
  in Switches.
- The gallery opt-in (inGallery) isn't sent by the share sheet, so N1 can't count.
- "Send diagnostics" isn't built (no audio-error buffer, no button). The server takes it
  (`support.create`, category `diagnostics`, `context.audioErrors`), now also from an account with
  no email (2026-10-29).
- No legal links (terms, privacy, accessibility) in the app's Settings.
- The accessibility pass, then the statement's date and method.

## Backend (next)
- **Done (2026-10-28):** the accessibility statement's timing fallback ("Use an estimate", then
  timing by ear) and captions (only with words); the terms' open parts (4d) and virtual choir
  (4e), a stopped share going within minutes, Ideas being 18+; the network panel's N1 counting
  only opt-ins that can list, the digest line's numbers, and N5 and N6 counted before they open
  (who could open parts; the song with the most adults who could join).
- **Done (2026-10-29):** people.show has the plan (`plan`: Plus, source, until); `admin.people.payment` reads the latest Plus payment for the refund panel; a support message shows its sender's plan beside the link to their page. A refund from admin ends Plus at once (H24, Hillel: "Always ends Plus").
- **Done (2026-10-29):** the credit link for beta singers (`credit_links_profile`, agreed with the
  landing agent): `creditHandle` only when opted in, credited by name, public and the gallery open.
- **Scope cut, checked (2026-10-28):** the web app and the Flutter apps have no screens or links for
  coaching, Event Schedule, the gym or Invoice Ninja (only the API's operation lists), and neither
  offers Sign in with Event Schedule, so the gym and the books need no flag of their own until
  their screens are built. The one leak, the pricing page's "Buy take reviews and lessons from
  coaches" row, now shows only once the `coaching` flag is on. The site's coaching feature page
  and "on the way" list say it's coming soon, as decided.
- **Done:** rows 21 and 22, H23 and H24 in docs/launch-checklist.md.

## Hillel / lawyer
- The support inbox mailbox (contact@) must really receive replies to answer emails.
- Terms acceptance: built (2026-10-29): the version and time each account agreed; a new version is asked once. Whether it should block is the lawyer's call.
- Placeholders: filled or hidden (2026-10-29, Hillel's answers; docs/decisions.md). Still for the
  lawyer: minimum age 16 and parental OK, liability, jurisdiction, the copyright agent, the EU
  transfer safeguard; the DSA out-of-court wording; the accessibility enforcement route.
- Red River Valley (Kerrigan), Sweetheart (1910), Michael Row (1867), Swing Low (1872) source checks.
