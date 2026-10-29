# mudeo is now Stackapella: telling mudeo's users

**Status:** planned, not started. The admin tool and the early-access link are being built; the email goes out once they're live and Hillel has decided mudeo's future.

Stackapella is mudeo v2: the same idea (stack your own voice into a harmony), rebuilt audio-only
and much easier to learn with. mudeo's users are its natural first audience, so they get one
personal email from Hillel, **inviting them into the beta early** with a secret link, before Stackapella opens to everyone.

Back: [Decisions](decisions.md) · Next: [Roadmap](roadmap.md)

## Decided

- **One email, once.** It's the single exception to "no bulk or recurring email to users"
  (`docs/decisions.md`, 2026-10-27, "telling mudeo's users"). There's no follow-up and no
  newsletter.
- **Sent through the app from `stackapella.com`** (changed 2026-10-29, Hillel: "we don't have mudeo
  domain any more"; it was a mudeo. subdomain, and before that Hillel's Gmail): admin's **Send the
  next batch** button sends the next 50 by Cloudflare Email Sending, each email to one person (never
  Bcc), from "Hillel from Stackapella <hillel@stackapella.com>" with Reply-To
  contact@stackapella.com, so replies reach Hillel. stackapella.com's SPF and DKIM already cover
  Cloudflare's sending (checked 2026-10-29). The daily cap, the 50-at-a-time batches and the stop on
  a high bounce rate are what keep an old list's bounces from weighing on the account emails.
- **Before the launch, with a secret early-access link** (below). Sign-ups stay paused for everyone else; the link lets mudeo's people in, so they're the first real singers and their feedback shapes the launch.
- **Nobody gets a Stackapella account or data moved over.** The email invites; people sign up
  themselves.

## Still for Hillel to decide

1. **mudeo's future: decided.** mudeo shut down a few months before this email (2026). The email
   says so plainly, explains why (video couldn't be synced without latency, and many people wanted audio only, which also makes the sync exact), and offers to send anyone their mudeo videos.
2. **Still open: the postal address** for the email's footer (US law asks for one in commercial email).
3. **Still open: whether to leave out mudeo users who already have a Stackapella account** (the admin tool
   can match by email).


## The early-access link

- **One secret link for the mudeo email,** like `https://beta.stackapella.com/early/<long random code>`.
  Opening it lets that person make an account **even while sign-ups are paused** (`pause_signups`),
  the same way a beta singer's invite does. It doesn't make them a beta singer.
- **Safe if it's forwarded:** a cap on how many accounts it can make (say 500, changeable in
  admin), a per-address limit, every sign-up through it marked "from mudeo", and the link can be
  **paused or replaced with a new code** in admin at once (the old code then shows a kind
  "this link has closed" page). Shield mode's human check still applies during an attack.
- **Only shown in the email.** It's never linked from anywhere public, and the page sends noindex.
- Admin shows how many joined through it, and the account's "from mudeo" mark feeds the launch
  counts.
- At the launch, when sign-ups open for everyone, the link simply keeps working (or is retired).

## The list

**From mudeo's database** (MySQL on the go_itsallwidgets server, database `mudeo`). Hillel runs
this read-only export there and uploads the CSV in admin; nobody else connects to that server:

```sql
SELECT id, name, email, created_at, updated_at
FROM users
WHERE deleted_at IS NULL
  AND is_flagged = 0
  AND email IS NOT NULL AND email <> ''
ORDER BY updated_at DESC;
```

Exported as CSV with a header row, for example with
`mysql mudeo -e "<the query>" -B | tr '\t' ',' > mudeo-users.csv`.

**Cleaning, on import:**
- emails lower-cased, trimmed, de-duplicated, and checked for a valid shape;
- placeholder or relay addresses dropped (`@privaterelay.appleid.com` stays, since Apple relays
  it; obvious test addresses like `@example.com` go);
- addresses already on the Stackapella suppression list (bounced or complained) are skipped;
- **most recently active first,** so the people most likely to care, and least likely to bounce,
  get it first.

**What's kept, and for how long:** the email, the name (for nothing but display in admin), the
mudeo user id, mudeo's created and updated dates, and our own sent, opted-out and bounced
marks. It's kept only for this. The whole list is deleted 90 days after the last batch is sent
(a scheduled clean-up), except the opt-outs, which are added to the suppression list so the app
never mails them anything but account email they ask for.

## The admin tool: Admin > mudeo email

Admin only, and every action is in the audit log.

- **Import:** upload the CSV (sudo). It shows how many were imported, skipped and why.
- **Counts:** total, sent, still to send, opted out, bounced, and how many were sent today.
- **The next batch:** the next 50 addresses as one comma-separated line, with **Copy**. Paste it
  into Gmail's **Bcc** (put your own address in To).
- **Mark these 50 as sent:** records the batch and the time, then shows the next 50. **Undo the
  last batch** if it didn't go.
- **Today's limit:** a free Gmail account may send to about 500 people a day (Workspace about
  2,000). The tool shows today's count, and warns from 400. It refuses a batch that would take
  today past 450, unless Hillel says he's on Workspace.
- **Opt-outs and bounces:** search an address, then mark it **opted out** (they replied "no more")
  or **bounced** (Gmail's "Address not found"). They leave the list. Paste several bounced
  addresses at once.

## Sending it (through the app)

- **One button, one batch:** "Send the next 50". A daily cap (default 200, editable) and a
  pause between batches; it refuses past the cap. Most recently active first.
- **Each email is personal and individual:** "Hi <first name>," (or "Hi," with no name), to one
  address, with the person's own early-access link in it if wanted.
- **Unsubscribe that works:** a one-click unsubscribe link in the footer and the
  `List-Unsubscribe` and `List-Unsubscribe-Post` headers (what Gmail and Yahoo expect from bulk
  senders). Unsubscribing adds the address to the suppression list at once.
- **Bounces:** permanent bounces Cloudflare reports at send time mark the address bounced and
  suppress it. Stop for the day if more than 5% of a batch bounces (the button shows the rate).
- **Plain and light:** text plus a simple HTML version, one link, no images or tracking pixels.
- **Before the first batch:** send a test to Hillel, check it in Gmail (SPF, DKIM and DMARC
  pass, not in spam).

## Sending it (the earlier Gmail plan, kept for reference)

1. Start slowly: **one batch of 50 on day 1,** then a few batches a day. If Gmail warns about
   "unusual activity" or bounces pile up, stop for a day.
2. **Bcc only.** Never To or Cc, so nobody sees anyone else's address.
3. Send from the Gmail website or app as yourself. Keep the email plain (no images or
   attachments) with one link, written out in full (no link shorteners).
4. **Reply to everyone who answers.** Mark opt-outs the same day.

## The email (draft)

**Subject:** mudeo is now Stackapella

> Hi [first name],
>
> It's Hillel, who made mudeo. You signed up to mudeo to record yourself singing and stack the
> parts into a harmony. Thank you for being part of it.
>
> mudeo closed a few months ago. The honest reason: I could never get the videos to line up
> without a delay. Each phone and browser adds its own lag to recording, and with video on top,
> the parts never quite sat together the way a harmony needs to. And many of you told me you
> mostly wanted the sound anyway.
>
> So I rebuilt it from the ground up as **Stackapella**: the same idea, audio-only this time.
> Without video, every part now lines up exactly in time, and it's built to help you actually
> learn harmony:
>
> - short lessons with a guide voice, where your notes light up as you hit them;
> - ready-made arrangements to learn one part at a time, some sung by real singers;
> - record each part over the others, exactly in time, and hear the whole song come together.
>
> It isn't open to everyone yet, but as a mudeo singer you're invited in early. This link lets
> you make an account now:
> https://beta.stackapella.com/early/[code]
>
> It runs in your browser on your phone or computer, nothing to install, and it's free to start.
> As one of the first singers in, your feedback really shapes it: tell me what's confusing or
> what you'd love it to do.
>
> **Your mudeo videos:** if you'd like a copy of any videos you made on mudeo, just reply to this
> email and I'll send them to you.
>
> I'd love to hear what you make. Just reply to this email; I read every one.
>
> Hillel
>
> ---
> You're getting this one email because you had a mudeo account. It's the only one; I won't
> email you again. Unsubscribe in one click: [unsubscribe link]
> [Postal address]

## Building it

- **Backend:** sending through the app (the batch button's operation, the daily cap, per-person email with List-Unsubscribe, the unsubscribe endpoint, bounce marking); the early-access link (a code, a cap, pause and replace, the "from mudeo" mark, letting sign-up through while paused); a `mudeo_contacts` table; the CSV import with the cleaning above; the admin
  operations (counts, the next batch, mark sent, undo the last batch, mark opted out or bounced,
  the daily limit); opt-outs added to the suppression list; the clean-up 90 days after the last
  batch; tests for each.
- **Frontend:** the "mudeo email" screen in admin, with Send a test to me, Send the next batch, the counts, the bounce rate and the link's controls (Copy kept as a fallback), in the
  styleguide's admin patterns.
- **The contract:** `admin.mudeo.*`, agreed between the two agents.
