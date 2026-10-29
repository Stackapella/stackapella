# Plus on live Stripe keys (H7)

**Status:** written 2026-10-29 by the backend agent (launch item 2). The code is ready: Plus,
coaching and refunds are tested against Stripe's test mode. What's left is Hillel's, in Stripe's
dashboard and App Platform, in this order. Nothing here is a secret; the values you paste are.

## 1. A dry run in test mode, on the beta

On the beta (test keys), with Stripe's test cards. Each step says what you should see.

| Do | You should see |
|---|---|
| Sign up a new account, then Plan, Start Plus, yearly, with the trial. Card `4242 4242 4242 4242`, any future date, any CVC | Back in the app as Plus (trial). Stripe, Customers: the account's email, a trialing subscription |
| In Stripe, the subscription, then "Run test clock", or end the trial now | An invoice paid; the app stays Plus. The receipt email comes from Stripe |
| Start Plus monthly on a second account with `4242...`, then in Stripe change its card to `4000 0000 0000 0341` (it attaches, and every charge on it fails) and advance the test clock a month | The renewal fails, Plus keeps working while Stripe retries, and after the last retry the account is on free |
| Admin, People, the first account, Refund (in sudo), part of the payment | The refund in Stripe, and the app says Plus ended (refunds end Plus at once: H24) |
| Pay with `4000 0000 0000 0259` (it's disputed a moment later) | Plus ends at once, and the account's trust drops to 0 (Admin, People shows it) |

If any step shows something else, send it to the coordinator before going live.

## 2. In Stripe's live mode

1. **The product and its two prices.** Products, add "Stackapella Plus", then two recurring prices:
   - yearly: USD 39.99, with currency options EUR 39.99 and GBP 34.99, tax included for EUR and GBP;
   - monthly: USD 4.99, with EUR 4.99 and GBP 4.49, tax included for EUR and GBP.

   (These are `config/billing.php`'s `amounts`; change both together if you change one.)
2. **Stripe Tax:** turn it on, add the registrations your accountant advises, and set the tax
   behaviour as above (inclusive for EUR and GBP, exclusive for USD).
3. **The platform webhook:** Developers, Webhooks, add endpoint
   `https://app.stackapella.com/webhooks/stripe` (the app's host, which never moves), with exactly these events:
   - `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
     `checkout.session.async_payment_failed`;
   - `customer.subscription.created`, `customer.subscription.updated`,
     `customer.subscription.deleted`, `customer.subscription.trial_will_end`;
   - `invoice.paid`, `invoice.payment_failed`;
   - `charge.refunded`, `charge.dispute.created`, `charge.dispute.updated`, `charge.dispute.closed`.
4. **The Connect webhook** (coaches' payouts): add endpoint `https://app.stackapella.com/webhooks/stripe-connect`,
   listening to **connected accounts**, with `account.updated`.
5. **Radar:** the rules in radar-rules.md.
6. **Checkout's terms box** (optional): Settings, Public details, the terms address
   (`https://stackapella.com/legal/terms`); only then set `STRIPE_TERMS_CHECKBOX=true`.

## 3. What to paste into App Platform (encrypted)

| Setting | From |
|---|---|
| `STRIPE_KEY` | Developers, API keys: the live publishable key (`pk_live_...`) |
| `STRIPE_SECRET` | The live secret key (`sk_live_...`); better, a restricted key with the permissions Cashier needs |
| `STRIPE_WEBHOOK_SECRET` | The platform webhook's signing secret (`whsec_...`) |
| `STRIPE_CONNECT_WEBHOOK_SECRET` | The Connect webhook's signing secret |
| `STRIPE_PRICE_YEAR`, `STRIPE_PRICE_MONTH` | The two live prices' ids (`price_...`) |

Then deploy, and run `php artisan app:doctor` in the console: Stripe, Stripe webhook and Stripe
Connect webhook pass.

## 4. The first live purchase (launch day, D5)

Buy Plus yourself with a real card, signed out first, then refund it from Admin, People (in sudo).
Stripe's live events list shows each event delivered with a 2xx.

---

[Back: The launch checklist](launch-checklist.md)
