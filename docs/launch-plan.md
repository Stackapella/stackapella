# Launch plan: who builds what

**Status:** drafted 2026-10-29 by the coordinator from Hillel's calls ("for launch enable coaches to
sign up, they just aren't visible until we have a bunch", "include plus courses and hum a tune into
launch plan", "we don't have mudeo domain any more"). The detailed checks stay in
[launch-checklist.md](launch-checklist.md); this page says what's in the launch and which agent
owns each piece. The mobile apps stay on hold.

## What's in the launch

- Everything live on the beta today: Sing, the course, the studio, the song library (every song at
  2 to 4 singers), the song builder, sharing, groups (Plus), the leaderboards.
- **Plus open for sale** (checkout on), because the courses are Plus.
- **Plus courses:** ten at launch, lesson 1 of each free: Vibrato plus each course agent's first three. The rest stay Coming soon until after launch.
- **Hum a tune:** hum or sing a tune with no words; the app writes the notes and suggests a harmony.
- **Coaches can sign up and be approved,** but stay hidden (no directory, no search) until there
  are enough of them (roadmap N4).
- Not in the launch: the network features (gallery, leagues, public library, open parts, virtual
  choir, requests), the coach directory, the native apps.

## Who does what

### Frontend (the app)
1. Hillel's open asks: Home design, Admin > Posts, the workout warm-up and the lesson sing step, the
   studio batch.
2. Launch screens: terms acceptance at sign-up, Send diagnostics, the mudeo totals screen, the
   admin refund panel and the score-review list.
3. Plus at launch: checkout on (the Plus page, the buy moments, including a course's), with the
   backend.
4. Coach sign-up in the app (apply, profile, approval status), with no public listing.
5. The accessibility pass across the app, then the help guide's screenshots reshot.

### Backend (server and API)
1. Its queue A to G: Plus courses on the server, the social posts check, terms acceptance, launch
   dry runs, performance, HSTS and the CI secrets scan, Send diagnostics.
2. The mudeo email sends from stackapella.com (no mudeo domain): the sender, its address, and
   launch checklist H10 removed.
3. Plus checkout ready for live keys (H7): the webhooks, a test purchase and refund on test keys.
4. Coach sign-up and approval end to end, with coaches kept out of every public list.

### Marketing (the site and launch kit)
1. The launch kit refresh (press release, store listings, social posts, email copy).
2. Launch-day switches: Plus "Coming soon" to on sale, Vibrato's course page from Coming soon to
   live, Hum a tune's feature page to live, coaches' page from "Write to us" to a sign-up.
3. The help guide for every launch feature, and the screenshot pass after the frontend's reshoot.
4. The accessibility statement's facts once the frontend's pass is done.

### Courses (four agents; every course ready for launch)
- **courses (lead):** Vibrato, the lesson engine, new lesson types for all, the quality bar, and opening the courses to Plus on launch day.
- **courses-skills:** Range and registers, Breath and stamina, Sight-singing, Ear training, Perfect your pitch, Humming and lip trills; self-check Belting, Diction, Singing in another language.
- **courses-harmony:** Counterpoint, Improvising a harmony, Barbershop and tags, A cappella bass, Gospel and soul runs, Scat singing; self-check Beatboxing, Vocal percussion.
- **courses-styles:** Folk and early music, Yodeling, Musical theatre, Pop and country styling, Whistling, Live looping; self-check Overtone singing, Character voices, Accents.
- The eight self-check courses say "we don't score this yet" and are scored later.

### Arranger
1. Finish the 2 to 4 voicings (the remaining songs).
2. **Hum a tune:** the musical side (turning a hummed tune into clean notes and rhythm, the key, and
   a good harmony), built into the song builder with the frontend, which owns its screens.
3. A listen-through of every song at 2, 3 and 4 singers once the beta has them.

## Launch day switches

The day's runbook, in order, with each check and undo: [launch-day.md](launch-day.md).

- `plus_courses` on (the courses open to Plus; until then only staff see them).
- Plus checkout on, with the live Stripe keys.
- The site's launch switches (marketing): Plus on sale (the live price ids), Vibrato (`plus_courses`) and Hum a tune (`your_tune`) live, coaches' sign-up link (`coach_signup`).
- Coach sign-up on, the coach directory still off.

## Hillel's part before launch

The lawyer (H14) and the DMCA agent (H17); Stripe live keys (H7); Sentry (H6); cost alerts (H5);
the production readiness check, backup and restore drill (H1, H2); `LICENSE.asc` (H16); the last
device pass (H19); the showreel upload (H13, no setting needed now).
