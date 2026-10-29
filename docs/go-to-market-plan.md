# Go to market: how beginners find Stackapella

**Status:** planned, not started (2026-10-26). Its pieces are placed in [roadmap.md](roadmap.md)
(under "Where each piece goes").

## Context

The launch is set low ($4.99) for growth, but no plan said how people would find the app. This one
does, within the vision's rules: no analytics, no bulk email, and private by default.

**Hillel's answers (2026-10-26):**
- **No pre-launch waitlist.** The no-bulk-email rule stays as it is. The launch is announced through
  posts, outreach and communities.
- **Web only.** No app stores for now. People install the PWA from the website, and the app guides
  them through it.
- **People can share and monetise what they make** with our arrangements, crediting "Arranged by
  Stackapella". Every video they post advertises the app.

## The loops that bring people in

1. **The share loop (built into the launch):**
   - a finished harmony becomes a share link or a vertical video;
   - the video's end card says "Made with Stackapella", with the link;
   - the link's page says "Learn this harmony".

   **Every finished song is an ad, made by a happy singer.**
2. **Search:**
   - **a public page for every song** in the library, for example "Learn the alto part of
     Shenandoah: free in your browser". It has the parts and their ranges, the 20 to 30 second
     preview clip (the same one as the catalogue's; on a Plus song never a single part or the
     notes), the first verse's words, and Start (or "On Plus"). A free song's page also plays its
     opening line in four voices, each part's notes drawn (Hillel, 2026-10-27, the design round);
   - **short guides** people search for:
     - how to sing harmony;
     - how to find your part (soprano, alto, tenor or bass);
     - how to practise a choir part at home;
     - a round for beginners.

     Each ends in a song to try.
3. **Communities, by hand** (Hillel, adapting his `growth-outreach` routine from Event Schedule):
   - **choirs and choir directors:** community and church choirs, school music teachers. "Learn your
     part at home" is their pain, and they bring whole sections;
   - a cappella groups, and university groups;
   - **singing teachers and coaches,** who sign up as coaches at launch and bring their own
     students through their profile link;
   - Reddit (r/singing, r/choir, r/acappella), where useful answers link to a song, never spam;
   - TikTok, Reels and Shorts: Hillel's own stacked harmonies, and beta singers' (with their OK).
4. **Song of the month:** a reason to post every month ("This month's free song: Shenandoah").
   Christmas songs in December are the biggest moment of the year for singing.
5. **Hillel's other products:** a mention where it's natural. Event Schedule users with choirs are
   the obvious ones, and the integration is part of the launch.

## The launch

- **Before:**
  - **beta singers' stories:** short quotes and clips, with consent, for the landing page;
  - the song pages and guides go live with the landing page (L), so search starts working before
    the announcement;
  - a press kit page: screenshots, a short video, the one-line pitch, and Hillel's contact.
- **Launch week:**
  - Show HN;
  - Product Hunt;
  - posts in the communities above, each written for that community;
  - our X and Bluesky accounts (posted by hand at launch; automatic posting comes with the gallery);
  - outreach emails to choir directors and music teachers, one at a time, by Hillel. **That's
    personal email, not bulk email.**
- **After:**
  - one community a week;
  - the song of the month;
  - Christmas;
  - featuring great harmonies (with consent).

## Knowing what works, without analytics

- **"How did you hear about us?"** is asked once, optionally, at the end of the first run: search,
  a friend's video or link, a choir, Reddit, TikTok, a teacher, other. It's counted on the server.
- **Links we post carry `?ref=`** (for example `?ref=hn`, `?ref=choir-outreach`). It's stored with
  the account at sign-up, as a first-party record only. There are no cookies for it and no outside
  tools.
- **Shares count themselves:** sign-ups that arrive from a share link or video are recorded with
  that harmony's ID.
- **The numbers that matter:**
  - finished harmonies and second songs (the vision's measure);
  - then sign-ups by source;
  - shares per finished harmony;
  - trials and upgrades, by upgrade moment.

## Installing the app (web only)

There's no app store to find it in, so the app makes installing easy:
- **After the first recorded part** (when the person has something to keep), a short card offers
  it:
  - on iPhone: "Add to Home Screen: tap Share, then Add to Home Screen", with a picture of where;
  - on Android and desktop: the browser's own install prompt.
- **Installing matters on iPhone:** Safari clears a website's storage after about 7 days without a
  visit unless the app is installed (see "Keeping songs safe" in [operations-plan.md](operations-plan.md)).
- **App stores later:** Google Play can list the PWA as a wrapped web app cheaply, and the iOS App
  Store needs a native wrapper and Apple's in-app purchases. Revisit when the numbers justify it.

## What people may do with what they make

- **People own their recordings.** They can share their harmonies anywhere, including monetised
  YouTube, TikTok or Spotify, crediting "Arranged by Stackapella" (the share videos already do).
- **The terms grant this,** for our arrangements and for the official guides sung by beta singers.
  **The beta agreement covers it** (`docs/beta-singers-plan.md`). The songs themselves are public
  domain.
- **Not allowed:** re-selling the arrangements themselves (the notes and guides) as a product, or
  claiming them as their own arrangement.

## Where each piece goes

| Milestone | Backend | Frontend |
|---|---|---|
| M3 | `ref` stored at sign-up; "How did you hear?" counted | The first run's optional question; the install card after the first part |
| M6 | Sign-ups from a share link recorded with the harmony | The end card on videos, "Learn this harmony" on the share page |
| L | **The song pages' data (`/songs/<slug>`), sitemaps**, the guides' routes | **The landing, song and guide pages** (Blade); the press kit page |
| Launch | | Hillel: Show HN, Product Hunt, communities, outreach |

## Review

**Missed before, now covered:**
- **Search:** song pages and guides, in the launch.
- **Install prompts:** needed for a web-only app.
- **Attribution without analytics:** a self-reported question, `?ref=`, and share links.
- **What users may do with their recordings:** share and monetise, with credit.
- **Launch week:** written down.

**Rules kept:**
- no analytics;
- no bulk email (outreach is personal);
- private by default (featuring a harmony needs consent);
- the community posts are useful, not spam.

---

[Back: Pricing](pricing-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The template creator](template-creator-plan.md)
