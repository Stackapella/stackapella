# Invoice Ninja and Stackapella together

**Status:** planned, not started (2026-10-26). Built in M10 (2026-10-27): both books, and Plus paid by invoice on request.

Part of [backend-plan.md](backend-plan.md), which holds the shared context, dependencies, open
decisions and verification. It's part of the launch, in roadmap M10. It builds on
[coaching-marketplace-plan.md](coaching-marketplace-plan.md).

## Context

Hillel asked for an Invoice Ninja integration. Event Schedule already has one, which is the
reference here:
- `app/Utils/InvoiceNinja.php`: the client (company, clients, products, invoices, webhooks), the
  `X-API-TOKEN` header, and URL normalising;
- `app/Http/Controllers/InvoiceNinjaController.php`: unlinking and webhooks;
- `app/Services/Payments/Gateways/InvoiceNinjaGateway.php`;
- the encrypted `invoiceninja_api_key`, `invoiceninja_api_url` and `invoiceninja_webhook_secret`
  on `User`.

In Stackapella, money moves in two places:
- **coaches sell their services** (take reviews, lessons, ongoing coaching, exercise packs) through
  Stripe Connect;
- **Stackapella sells Plus** through Cashier, and earns a commission on coaching.

Invoice Ninja keeps the books for both, so nobody copies sales out of Stripe by hand.

## Direct, not through Event Schedule

**Stackapella connects to Invoice Ninja directly.** Nobody needs Event Schedule for it. A coach can
use:
- Invoice Ninja only;
- Event Schedule only (for lessons);
- both;
- or neither.

The two connections are separate, each made in Stackapella's settings.

**A coach who uses both:**
- **Each sale is recorded once, by Stackapella,** because Stackapella takes the payment (lessons
  included).
- A lesson booked in Event Schedule is marked as paid elsewhere (`payment: external`,
  [event-schedule-integration-plan.md](event-schedule-integration-plan.md)).
- **Event Schedule must not also invoice it** through its own Invoice Ninja connection. That's one
  more item on the list of Event Schedule changes.

## What it does

### 1. A coach's books (the main use)
- **Connecting:** a coach goes to Coach settings, then **Connect Invoice Ninja**, and enters an API
  token and, for a selfhosted install, its URL (hosted is `invoicing.co`). Stackapella checks it
  by reading the company, shows "Connected to <company name>", and stores the token encrypted.
- **Every coaching sale is recorded:**
  - **a client:** the singer, found by email and currency, or created (as ES `findClient` and
    `createClient`);
  - **an invoice:** the offering as a line item (for example "Take review: Danny Boy, alto" or
    "Lesson, 3 Nov, 45 minutes"), with the tax Stripe Tax charged and the Stackapella reference in
    `po_number`;
  - **a payment** against it for the amount paid, with the Stripe charge as the reference. The
    invoice shows as paid.
- **Stackapella's commission is recorded as an expense,** with Stackapella as the vendor, so the
  coach's books show what they earned and what they paid in fees.
- **Refunds** (a declined review, a missed deadline, a cancelled lesson) are recorded as refunds on
  that payment, so the books match Stripe.
- **Packages** record one invoice per monthly payment.
- **Invoice emails:**
  - **off by default**, because Stripe already sends the singer a receipt, and two emails for one
    payment confuse people;
  - **"Also email singers an invoice from Invoice Ninja"** turns them on (ES `send_email` against
    `mark_sent`), for coaches whose clients need a proper invoice.
- **When connecting,** "Record my past sales" fills in the last 12 months once.
- **Who's told what:** at checkout, a coach with Invoice Ninja connected shows "Your coach keeps
  their records in Invoice Ninja". The singer is the coach's client, and the marketplace terms
  cover it.

### 2. Stackapella's own books (Hillel, in admin)
- **Connecting:** Hillel connects Stackapella's own Invoice Ninja company in admin, behind sudo.
- **What's recorded:**
  - Plus payments (from Cashier's `invoice.paid` webhooks) as invoices with payments, with Stripe
    Tax's amounts;
  - refunds;
  - **commission income** from coaching, recorded as one invoice per coach per month, with the
    sales listed.
- **Invoice emails are off:** Stripe sends Plus receipts.

### 3. Later: paying by invoice
Some customers need an invoice to pay against, by bank transfer, such as choirs, schools and
companies.
- **Plus or a future choir plan** could be paid by an Invoice Ninja invoice. Paying it turns the
  plan on, through Invoice Ninja's payment webhook (ES `webhook`).
- **A coach's organisation clients** could be billed by invoice instead of Connect. Stackapella's
  commission is then billed to the coach monthly.

Both are open decisions, not part of the first build.

## How it's built

- **`Services/InvoiceNinja/InvoiceNinjaClient`:** a port of ES `app/Utils/InvoiceNinja.php`:
  - it uses Laravel's `Http`, not raw curl;
  - it keeps ES's lessons:
    - `normalizeApiUrl` builds the API base properly. ES once used `rtrim($url, 'api/v1')`, which
      strips characters, not a suffix, and cut off hosts ending in a, p, i, v or 1;
    - **a 200 reply isn't proof that something was created:** the client checks that the
      returned record has an ID;
    - errors map to plain reasons (unreachable, TLS, a bad token, not allowed), shown to the coach
      in words;
  - **a selfhosted URL is supplied by a user,** so every call goes through an SSRF-safe fetcher
    (iaw `SafeFetcher`: the resolved IP pinned, private ranges refused), enforced by an
    architecture test, as iaw does.
- **`invoice_ninja_connections`:**
  - the owner (a coach's user, or the platform);
  - the API URL, the encrypted token, the company name;
  - the email setting and the default currency;
  - the status, the last error, and the webhook secret (for paying by invoice, later).
- **Jobs, queued so a sale never waits on Invoice Ninja:**
  - `RecordSale`, `RecordRefund`, `RecordFee` and `RecordMonthlyCommission`;
  - **idempotent:** the Stackapella reference goes in `po_number` and the IDs are stored, so a
    retry finds the invoice instead of making a second one;
  - three tries with backoff.
- **When it fails:**
  - the coach's dashboard shows "2 sales not recorded in Invoice Ninja", with the reason and
    **Retry**;
  - a bad token pauses recording and asks the coach to reconnect;
  - failures appear on the integration health screen and in Hillel's digest.
- **Disconnecting:**
  - a best-effort clean-up of any webhook, which never blocks: ES's `unlink` lesson, where an
    unreachable install left an account stuck as "connected";
  - then the credentials are cleared. What's already recorded stays in Invoice Ninja.
- **Currencies:** one client per singer and currency, as ES does. Amounts are in the sale's
  currency.

## Review (misses, UX, code fit)

**Missed, now covered:**
- **Recorded twice by both apps** when a coach connects Invoice Ninja to Stackapella and to Event
  Schedule: lessons paid through Stackapella aren't invoiced by Event Schedule.
- **Duplicates on retry:** prevented with `po_number` and the stored IDs.
- **Two emails for one payment:** invoice emails are off by default.
- **SSRF through a selfhosted URL:** the safe fetcher.
- **The coach's net earnings:** the commission is an expense.
- **Refunds keep the books matching Stripe.**
- **A failure never blocks a sale or a refund.** It's retried and shown.
- **ES's URL-normalising bug is not repeated.**

**UX:**
- Connecting is one screen with an immediate check ("Connected to Sarah's Studio").
- The dashboard shows the recording status per sale.
- A backfill runs on connecting.
- Errors are in plain words.

**Code fit:**
- **No new dependency:** raw `Http`, as the other clients.
- **It's an Action per operation,** with `Http::fake` tests. The client is a Service, and the
  connections are kept encrypted, as ES keeps them.
- **It plugs into the existing money events:**
  - coaching payments and refunds (`docs/coaching-marketplace-plan.md`, F3);
  - Cashier's webhooks (`docs/backend-plan.md`, D8).

## Order of work
1. **Stackapella's own books** (M10; Plus billing and take reviews already exist).
2. **Coaches' books.** Lessons and packages are recorded as they arrive.
3. **Later:** paying by invoice, if Hillel decides to.

## Verification
- **PHPUnit with `Http::fake`:**
  - a paid review makes one client, one invoice with its payment, and one fee expense;
  - a retry after a timeout makes no duplicate;
  - a refund records a refund;
  - email is sent only when the coach turned it on;
  - `normalizeApiUrl` handles hosted, selfhosted, trailing `api/v1` and hosts ending in a, p, i,
    v or 1 (ported from ES's tests);
  - a URL resolving to a private address is refused;
  - a bad token pauses recording;
  - disconnecting works when the install is unreachable.
- **By Hillel:** connect a test company on `invoicing.co`, buy a take review as a test singer,
  refund it, and check the invoice, payment, expense and refund in Invoice Ninja.

---

[Back: Event Schedule](event-schedule-integration-plan.md) · [Docs guide](../README.md#a-guide-to-the-docs) · [Next: The audio spike](audio-spike.md)
