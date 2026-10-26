# Project progress

_Last updated: 2026-10-26. Read this first when resuming._

## Phase status
| # | Phase | Status |
|---|---|---|
| 1 | Audit & architecture | Done (`docs/ARCHITECTURE.md`) |
| 2 | Foundation & design system | Done |
| 3 | Catalog & compatibility | Done (search, filters, variants, bundles, device pages) |
| 4 | Accounts & retail shopping | Done (auth, verification, reset, addresses, wishlist, reviews, returns) |
| 5 | Cart, checkout, orders | Done (coupons, shipping zones, tax, idempotent transactional checkout, status machine, refunds, restock) |
| 6 | Wholesale, bulk, quotes | Done (application/approval, tiers, MOQ, negotiated prices, bulk text/CSV, quotes with history, conversion) |
| 7 | Filament admin & permissions | Done (23 resources, dashboard, settings, roles, audit log) |
| 8 | Inventory, suppliers, fulfilment | Done (ledger, adjustments, POs + receiving, shipments); see gaps |
| 9 | Payments, emails, notifications | COD + bank transfer real; online gateways NOT implemented (credentials needed) |
| 10 | Marketing, SEO, performance | Done (sitemap, robots, JSON-LD, redirects, 410, canonical, newsletter, reminders, caching) |
| 11 | Testing & hardening | ~160 automated tests pass; real parallel race verified manually |
| 12 | Deployment docs | Done (`docs/DEPLOYMENT.md`, `deploy/`) |

## Verification performed
- `pest`: all tests pass (auth, pricing tiers, MOQ, cart, coupons, checkout, COD, bank transfer, webhook idempotency, stock, state transitions, refunds, quotes, wholesale access control, customer isolation, upload restrictions, admin permissions, admin workflows, SEO, commands).
- Real concurrency: 6 parallel PHP processes bought the last unit; exactly 1 order, stock 0.
- `npm run build` OK; `composer audit` / `npm audit`: no advisories.
- Headless Chrome: home (desktop), product page (phone width) rendered; found and fixed an Alpine bug; no console errors on shop, cart, login, wholesale register, checkout JS syntax-checked.
- Performance (5,000 products / 10,000 variants, in-process): /shop ~28 queries, ~100-300 ms; search ~26 queries, ~100 ms; product page ~34 ms. (The dev server adds ~0.5 s per request.) Target: <300 ms server time for catalog pages.

## Known gaps / not built (be honest with stakeholders)
- **Online payments** (JazzCash, Easypaisa, card): not implemented; needs merchant credentials. Webhook processor + reconciliation checklist exist (`docs/PAYMENTS.md`). Gateway refunds are manual.
- No real product photos: cards show a letter placeholder until images are uploaded in Admin > Products > Images. No server-side image resizing/srcset; no zoom; no sticky mobile add-to-cart bar.
- Admin "Add shipment" ships all remaining units at once (data model supports partial shipments; no per-item UI). Purchase orders are received in full only. `reserved_quantity` column is unused (stock is deducted at order time).
- Customer in-app notifications centre and referral programme not built (email only).
- Invoices are printable HTML ("save as PDF"), not generated PDFs.
- Homepage: hero/promo banners are DB-driven; featured/best-seller/new/deal sections are driven by product flags; "collections" are not a separate module; trust text comes from `store_settings` keys `trust_delivery`/`trust_warranty` (no form field yet).
- Generated Filament forms for Brand/Category/Device/Banner/Page/FAQ/Supplier are basic (logo/image fields are text paths, no slug auto-fill).
- Single flat tax rate; no per-category tax.
- Policy pages contain placeholder text: **must be replaced and legally reviewed**.
- Not browser-tested end to end (no Dusk/Playwright suite). Responsive layout was only inspected on a few pages.
- Redis not installed locally; cache/queue/session use the database driver (production template uses Redis).

## Exact next steps
1. Add real product images and replace placeholder policies/bank details in the admin.
2. Choose a card/wallet provider, obtain sandbox credentials, implement the adapter per `docs/PAYMENTS.md`.
3. Add Playwright/Dusk smoke tests for checkout and bulk order UI; run Lighthouse on a staging server.
4. Add per-item partial shipping UI and partial PO receiving if needed.
5. Deploy to staging following `docs/DEPLOYMENT.md`, test backup/restore, then go live.
