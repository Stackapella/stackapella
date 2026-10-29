# The coaching marketplace: coaches selling their services in the app

**Status:** planned, not started (2026-10-26). The directory (F7) was built in N4 (2026-10-27), behind the `coach_directory` flag.

Part of [backend-plan.md](backend-plan.md), which holds the shared context, dependencies, open
decisions and verification. Its place in the order of work is in [roadmap.md](roadmap.md).

**When** ([roadmap.md](roadmap.md)):
- **at launch, without the directory (M8):** coaches apply, get approved, and sell take reviews,
  ongoing coaching, messages and exercise packs to the people who come through **their own
  profile link**, with ratings and Send to my teacher;
- live lessons arrive with Event Schedule (M9);
- **the directory, search, suggestions and featured coaches (F7) wait for a network** (roadmap
  N4), opened when the admin's network features panel says so. Coaches are listed by default when
  it opens.

**The idea:** approved coaches sell four things in the app:
1. take reviews;
2. live lessons (booked through Event Schedule, [event-schedule-integration-plan.md](event-schedule-integration-plan.md));
3. ongoing coaching;
4. exercise packs.

Singers pay in the app through Stripe Connect, and Stackapella keeps a commission. Singers rate a
coach after a finished, paid session. The automated coaching (`docs/vocal-coaching-plan.md`) is the
way in: its report gains "Get feedback from a coach".

## F1. Becoming a coach
- **The application:**
  - experience and training, voice types and styles, languages;
  - an intro (audio or a video link);
  - **a sample review:** the applicant reviews a provided take in the review workspace, which
    shows Hillel how they give feedback;
  - agreeing to the coach agreement.
- **Hillel approves or declines** in admin, with a note. Approved coaches are flagged as new until
  their first few ratings.
- **Getting paid:**
  - Stripe Connect **Express** onboarding (ES `StripeController` `link`, `complete` and `unlink`,
    with `AccountLink`);
  - Stripe does identity checks, payouts and tax forms;
  - a coach can't sell until Stripe reports `charges_enabled`.
- **The coach profile** (`/coach/<handle>`, public Blade, reached by its link; `noindex` and
  unlisted until the directory opens):
  - the bio, voice types, styles, languages, time zone;
  - the offerings with prices and turnaround;
  - ratings;
  - sample feedback (with the singer's OK), and "Next available lesson" (from Event Schedule).
- **Coaches get Plus free while active** (proposed), since their tools need it.

## F2. What coaches sell
- **Take reviews:**
  - a fixed price per take up to a length (for example 3 minutes);
  - a turnaround (2 to 7 days);
  - optional extras (a second take, a follow-up).
- **Live lessons:** an Event Schedule appointment type, linked to the coach's profile (section G).
- **Ongoing coaching:**
  - a monthly package, for example 4 reviews and messages, or 2 lessons plus reviews;
  - a Stripe subscription on the coach's connected account, with an application fee percentage;
  - unused reviews don't roll over (proposed).
- **Exercise packs:**
  - a coach's warm-ups and exercises (the coaching plan's exercise data, plus their own demo
    audio), or templates;
  - free or one-off paid;
  - bought packs appear in the singer's warm-ups library;
  - **reviewed by Hillel** before sale (the exercise data has a review gate).

## F3. Payment
- **Stripe Connect with destination charges** on Stackapella's platform: the singer pays
  Stackapella, Stripe moves the coach's share, and Stackapella keeps `application_fee_amount`.
- **The commission** is in `config/coaching.php` (proposed 15%, open).
- **Take reviews:**
  - **paid when the request is sent;**
  - the coach accepts or declines within 48 hours;
  - **a decline, no answer, or a missed deadline refunds the singer in full,** automatically (the
    deadline job);
  - the coach's share is released when the review is delivered.
- **Lessons:**
  - paid when booked, through Stackapella;
  - the booking is made in Event Schedule as already paid (section G);
  - **the coach's cancellation policy** (for example, a full refund more than 24 hours ahead)
    decides refunds when Event Schedule reports a cancellation.
- **Packages:** Stripe Billing on the connected account. Cancel any time; it lasts to the end of
  the month.
- **Disputes:** "Report a problem" on any engagement goes to admin, where Hillel can refund in full
  or in part (behind sudo, audit-logged). Stripe chargebacks are handled from the same screen.
- **Bookkeeping:** a coach can connect Invoice Ninja to record every sale, fee and refund
  ([invoice-ninja-integration-plan.md](invoice-ninja-integration-plan.md)).
- **Receipts and payouts:** Stripe's receipts, and the coach's Express dashboard. Earnings appear
  in the coach's dashboard in the app.
- **Tax:** marketplace rules (who collects VAT and sales tax on coaching) go to the accountant
  before launch.

## F4. The review workspace (the heart of it)
- **What the singer sends:**
  - a take from a project, or a coaching take;
  - the template's notes, if it has them;
  - **the pitch track and scores from the device** (`pitchtrack.js`, `scoring.js`, uploaded only
    for this);
  - a question ("my high notes feel strained in the chorus").
- **What the coach sees, on desktop first and usable on a phone:**
  - the take on its waveform, with the notes and the singer's pitch line (the coaching plan's
    chart);
  - the automated scores, labelled as a guide only;
  - the mix, the take solo, or the guide for comparison.
- **What the coach gives back:**
  - **comments pinned to a moment** (click the timeline or a note): text, or **a recorded voice
    note** made in the app (a coach can sing it back to them);
  - **a demonstration take,** recorded against the same backing;
  - **a summary:** what's working, the top 2 or 3 things to work on, and what to practise;
  - **assigned exercises** from the library or their packs, added to the singer's warm-ups.
- **What the singer gets:**
  - the review opens on their take, with each comment at its moment;
  - Play from here, and Loop this phrase;
  - the coach's voice note is mixed over the backing;
  - **one follow-up question** is included.
- **Drafts save as the coach works.** Delivering is final, with a 10-minute "Unsend".
- **Privacy:** the coach sees only what was sent. Access ends 90 days after the engagement, unless
  the singer keeps it open (ongoing coaching).

## F5. Messages (ongoing coaching and lessons)
- **One thread per engagement:** text and voice notes, plus takes shared in the thread.
- **Phone numbers and emails are allowed but discouraged.** The terms say payments stay in the
  app, for both sides' protection.
- **Reportable**, with a block.

## F6. Ratings
- **Who can rate:** only after a finished, paid engagement (a delivered review, an attended lesson
  as reported by Event Schedule, or a month of coaching).
- **What a rating is:** 1 to 5 stars and an optional text. The coach can reply once, publicly.
- **Shown after 3 ratings** as an average with the count. Before that, the coach shows as "New
  coach".
- **No editing after 30 days.** Ratings can be reported; Hillel removes only those breaking the
  guidelines.

## Send to my teacher (Plus)
Hillel's call (2026-10-26): it stays, using the same review workspace.
- **How it works:** a singer on Plus invites their own teacher by link. The teacher makes a **free
  account** (not a coach profile, and no approval) and reviews the singer's takes in the same
  workspace, with pinned comments, voice notes and a summary.
- **No money goes through the app,** so there's no commission and no rating.
- **The teacher sees only that singer's shared takes.** Either side can end it.
- **It brings teachers in:** the workspace shows "Sell reviews to other singers too? Apply as a
  coach."
- **The tables:** `teacher_links` (singer, teacher, status), and `review_items` shared with paid
  engagements.

## F7. Finding a coach (when ready: roadmap N4)

At launch, people reach a coach through the coach's own profile link (shared on their website,
socials or with their students). The directory below opens when the admin's network features panel
says there are enough coaches with ratings, and enough singers.

- **`/coaches`** (public and in the app), with filters:
  - voice type, style, language, price, turnaround;
  - next lesson (from Event Schedule);
  - rating.
- **Suggested from context:** the singer's voice type and range (the coaching plan), and the
  template they're on.
- **Featured coaches** are chosen by Hillel.

## F8. Safety
- **Coaching is 18 and over at first** (proposed), for both sides.
- **Coaches are approved,** with identity checked by Stripe, but there are **no background
  checks**, and the profile says so.
- **Reports and blocks everywhere,** and suspension pauses a coach's offerings and refunds open
  work.
- **No medical claims:** the coach agreement carries the coaching plan's "stop if it hurts" line.

## F9. Tables
- `coach_profiles`:
  - the user, status (applied, approved, paused, suspended);
  - the bio fields, `stripe_account_id`, `charges_enabled`, the cancellation policy;
  - the rating average and count.
- `coach_applications`: answers, the sample review, decision, note.
- `coach_offerings`: kind (review, lesson, package, pack), price, currency, turnaround, limits,
  `es_appointment_type_id`.
- `engagements`:
  - the singer, coach, offering, status;
  - the Stripe payment or subscription, the fee, refunds;
  - due date, delivered date.
- `review_items`:
  - the take, the pitch track key, the question;
  - comments (time, note, text, voice note key), a demo take, the summary, assigned exercises.
- `messages`, `coach_ratings` (with the reply), `exercise_packs` (and their versions and
  purchases).
- `lesson_bookings`: see section G.

---

[Back: More features](more-features-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: Event Schedule](event-schedule-integration-plan.md)
