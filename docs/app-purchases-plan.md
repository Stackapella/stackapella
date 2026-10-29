# Buying Plus in the apps (a decision for Hillel)

**Status (2026-10-27): written by the backend agent for Hillel to decide. Nothing is built.** The
native apps ([decisions.md](decisions.md), "Native mobile apps") need an answer before they ship:
do they sell Plus through Apple's and Google's billing, or not at first? The rules change often
(court rulings in the US, the DMA in the EU); what's below is as of late October 2026, with
sources, and **the lawyer and each store's review should confirm it before launch.**

## The question

Plus is sold on the web through Stripe ($4.99 a month or $39.99 a year at launch,
[pricing-plan.md](pricing-plan.md)). The apps could:

- **A. Sell Plus in the apps** with Apple In-App Purchase and Google Play Billing, beside the web;
- **B. Not sell Plus in the apps**, but unlock it for people who bought it on the web;
- **C. Not have Plus in the apps at all** at first (the free tier only, even for web subscribers).

## What the stores allow

### Apple (App Store Review Guidelines)

- **3.1.1:** to unlock features or content in an app, "you must use in-app purchase."
- **3.1.3(b) Multiplatform Services:** an app may let people use subscriptions bought on the web
  "provided those items are also available as in-app purchases within the app." **So B isn't
  allowed on iOS:** an app that unlocks web Plus must also sell Plus in-app.
- **3.1.3(a) Reader apps** (magazines, books, audio, music, video) may skip in-app purchase. Plus is
  practice tools and a song library, not "previously purchased content" to read or play, so
  claiming this is a risk Apple's review would likely refuse. Not recommended.
- **3.1.3(f) Free stand-alone apps** covers only VoIP, cloud storage, email and web hosting.
- **Links to the web (anti-steering):**
  - **United States storefront:** since the 2025 Epic v. Apple contempt ruling, apps may include
    buttons and links to buy on the web, and the guidelines say the entitlements "are not
    required" there. The Ninth Circuit (December 2025) upheld that, but said Apple may charge a
    commission limited to its genuine costs, and may require the in-app purchase option to be as
    prominent as the link. Apple has proposed a commission on linked purchases (reported as 15%,
    5% for the Small Business Program); Epic opposes anything above 0%, the district court hasn't
    set a rate, and **for now linked US web purchases carry no commission.** The Supreme
    Court took Apple's contempt appeal (June 2026). This can change.
  - **EU (DMA), from October 1, 2026:** Apple's new terms charge 15% on purchases made through a
    link to the developer's own checkout (within seven days of the tap), 20% for alternative
    in-app payment, and 26% for Apple's in-app purchase, with reduced rates for small developers
    and for subscriptions after the first year.
  - **Elsewhere:** no links or calls to buy outside the app, except through Apple's link
    entitlements where offered.
- **Subscriptions (3.1.2):** they must work on all the person's devices where the app is
  available, and say clearly what the person gets before they subscribe.

### Google Play (Payments policy)

- An app that "require[s] or accept[s] payment" for in-app features must use Play Billing, unless
  it's in an alternative billing or external offers program.
- **Leading users to other payment methods** isn't allowed except through those programs, which
  now cover the US, the UK and the EEA (since mid-2026), with their own service fees.
- **An app that sells nothing but lets people use what they bought on the web** (B) isn't
  addressed in the policy text; it's common and generally accepted, but must not link or point to
  buying on the web. **Check with Google's policy team before relying on it.**

## Fees

| | Apple | Google |
|---|---|---|
| In-app subscription | 30% in a subscriber's first year, 15% after; **15% from day one** in the Small Business Program (under $1M a year, enrolment needed) | **15% from day one** for every subscription |
| On $39.99 a year at 15% | about $6 to the store, $34 to us | the same |
| A link to the web (US) | 0% for now (rate pending in court) | external offers fee, from October 2026 (contested) |
| A link to the web (EU) | 15% (10% reduced tiers) | its external offers fee |

The stores handle sales tax and VAT on their own sales (they're the seller), which the web leaves
to us through Stripe.

## One Plus across the web and the apps

Whichever store sells it, it's one Plus on one account:

- **The server keeps each person's Plus with its source** (Stripe, Apple or Google), the one that
  ends last counting. What Plus unlocks never depends on where it was bought.
- **Apple:** the app sends the signed transaction to the server, which checks it with the **App
  Store Server API** (Get All Subscription Statuses, Get Transaction History, signed JWS). **App
  Store Server Notifications V2** tell the server about renewals, failed renewals, expiry,
  refunds (`REFUND`) and lost access (`REVOKE`, which includes Family Sharing).
- **Google:** the app sends the purchase token; the server checks it with the **Play Developer
  API** (`purchases.subscriptionsv2.get`) and **must acknowledge a new subscription within three
  days**, or Google refunds it. **Real-time developer notifications** (through Pub/Sub) report
  renewals, cancellations and refunds; `voidedpurchases.list` catches anything missed.
- **Bought twice** (web and app): the server sees both; the app tells the person and links them
  to cancel one (Apple's and Google's subscriptions can only be cancelled in their own settings).
  We never charge twice knowingly.
- **New secrets for Hillel:** an App Store Connect API key (for the Server API), the Play
  Console service account (for the Developer API), and a Pub/Sub topic. Each is set up in the
  store's own console.

## Refunds, family sharing and prices

- **Refunds:** Apple decides refunds for App Store purchases; we can't refund them and only learn
  of them by notification. Google lets us refund from the Play Console or the API. Our refund
  policy (a legal page) must say store purchases are refunded through the store.
- **Family Sharing (Apple):** a subscription can be opened to the family, but it's per product
  and can't be turned off once on. Plus is tied to a Stackapella account, and a family member
  would need their own account to use it, so **leave it off** at first. Google's family sharing
  of subscriptions should be checked before launch; leave it off too.
- **Prices:** the stores let us set the same price as the web. **Recommended: the same $4.99 and
  $39.99** (fees absorbed, as the launch price already favours growth), with the store's local
  prices in other currencies. A higher app price is allowed but looks odd beside the web.
- **The trial:** the stores' introductory offers give the 7-day trial in the app.

## What the apps may say about the web

- **United States, iOS:** may say Plus is on the web and link to it (shown no more prominently
  than in-app purchase).
- **EU, iOS:** may link out under Apple's DMA terms (and pay the link fee on sales).
- **Everywhere else on iOS, and on Google Play outside its programs:** must not tell people to
  buy on the web, nor link to it. Our emails and the website may say anything (Apple allows
  communication "outside of the app").
- **In practice:** the apps' Plus screen offers in-app purchase only, everywhere, at first. The
  US link can come later, once the court sets the rate.

## Coaching

Lessons with a coach are bought on the web today. Apple's 3.1.3(d) lets real-time
person-to-person services (tutoring) use other payment methods, which covers live lessons; a
coach's review of a take is closer to digital content and may need in-app purchase. **Leave
coaching out of the apps' purchases at first** (the apps show booked lessons and reviews, and
buying stays on the web, with no link outside the US).

## Recommendation

**A: sell Plus in the apps from their first release**, at the web's price, with one Plus per account
on the server:
1. iOS: StoreKit 2 subscriptions (monthly and yearly, the 7-day trial as an introductory offer),
   the Small Business Program, Family Sharing off;
2. Android: Play Billing subscriptions, the same products;
3. the server: receipt checks and notifications for both, beside Stripe, and "bought twice"
   handling;
4. no links to the web in the apps at first.

**Why not B or C:** B breaks Apple's 3.1.3(b), so iOS would be refused. C makes the apps a
lesser Stackapella and leaves web subscribers without Plus in the app they paid for, which is the
worse first impression. A costs about 15% of app sales and some server work (roughly a milestone),
and it's what the stores expect.

**If Hillel wants the apps out sooner:** ship the first releases as free-tier apps that don't
recognise Plus at all (C), clearly saying "Plus is coming to the app", and add A in the next
release. That avoids rejection, but web subscribers will ask why.

## Decisions for Hillel

1. A, B or C (recommended: A).
2. The same price in the apps as on the web (recommended: yes).
3. Apple's Small Business Program (recommended: enrol).
4. Family Sharing (recommended: off).
5. A US link to the web later, once the court's rate is known (recommended: decide then).

## Sources

- Apple, App Store Review Guidelines, 3.1.1 to 3.1.3:
  https://developer.apple.com/app-store/review/guidelines/
- Google Play, Payments policy: https://support.google.com/googleplay/android-developer/answer/9858738
- Google Play, service fees: https://support.google.com/googleplay/android-developer/answer/112622
- The Ninth Circuit on Epic v. Apple (December 2025):
  https://www.fenwick.com/insights/publications/ninth-circuit-largely-upholds-ruling-in-epic-v-apple
- Apple's proposed link-out commission (August 2026):
  https://techcrunch.com/2026/08/14/apple-proposes-to-take-a-15-cut-of-purchases-made-outside-the-app-store/
  and https://appleinsider.com/articles/26/08/13/apples-latest-commission-rates-for-external-app-store-purchases-havent-satisfied-epic
- Apple's EU terms from October 1, 2026:
  https://www.macobserver.com/news/eu-app-store-terms-october-1-rate-card/ and
  https://thenextweb.com/news/apple-eu-app-store-fees-dma-core-technology-commission
- Google Play's 2026 billing and linking changes:
  https://www.coda.co/blog/epic-v-google-policy-update-2026/ and
  https://www.macrumors.com/2026/07/15/google-third-party-app-stores/
- The App Store Small Business Program: https://adapty.io/blog/app-store-small-business-program/
- Play Billing's three-day acknowledgement and notifications:
  https://www.revenuecat.com/blog/engineering/google-play-lifecycle/
- App Store Server API: https://developer.apple.com/documentation/appstoreserverapi

The court-driven rates (Apple's US link fee, Google's external offers fee) come from news and
developer-tool blogs, not the stores' own pages, and were still moving in October 2026.

---

[Back: Decisions](decisions.md) · [Docs guide](../README.md#a-guide-to-the-docs)
