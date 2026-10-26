# Voltix: mobile accessories store (retail + wholesale)

Laravel 12 monolith: Blade + Tailwind 4 + Alpine storefront, Filament 4 admin at `/admin`, MySQL, Pest tests. "Voltix" is a placeholder brand name (Admin > Store settings).

## Run locally
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate       # set DB_* (MySQL); create the databases `store` and `store_testing`
php artisan migrate --seed                              # demo catalog + roles + default shipping (non-production only)
npm run build                                           # or `npm run dev`
php artisan storage:link
php artisan serve                                       # http://127.0.0.1:8000
```
Run the queue worker for emails (`php artisan queue:work`) or set `QUEUE_CONNECTION=sync` while developing; `MAIL_MAILER=log` writes mail to `storage/logs`.

**Admin access (local demo only):** `admin@voltix.test` / `password` is created by the seeder **only when APP_ENV is not production**. In production create the first admin with the steps in `docs/DEPLOYMENT.md`; no credentials are hard-coded.

## Tests
```bash
./vendor/bin/pest          # ~160 tests; uses the MySQL database `store_testing`
npm run build              # production asset build
composer audit && npm audit
```

## Documentation
- `docs/ARCHITECTURE.md`: decisions, schema, workflows
- `docs/DEPLOYMENT.md`: Nginx / queues / scheduler / backups / rollback (configs in `deploy/`)
- `docs/PAYMENTS.md`: what payment methods are real, and how to add gateways
- `docs/REPORTING.md`: dashboard metric definitions
- `PROJECT_PROGRESS.md`: status, known gaps, next steps

## Key rules enforced on the server
Prices, discounts, shipping, tax and totals are always recomputed server-side (`OrderCalculator`, `PriceResolver`). Wholesale pricing/MOQ/quotes require an *approved* application (`User::isApprovedWholesale()`, `wholesale` middleware). Stock changes only through `StockService` (row lock + ledger). Orders snapshot name/SKU/price. Staff permissions are checked per resource/action; cost prices and price edits need dedicated permissions.

## Catalogue seeding (real data)
`php artisan catalog:scrape` (dev tool) collects real Pakistani listings from PriceOye (name, brand, PKR price, colour options, product image) into `database/seed-assets/` (catalog.json + images). `RealCatalogSeeder` then loads them offline: **273 products, 491 colour variants, 248 real photos**, from Anker, Samsung, Joyroom, Baseus, Xiaomi, Choetech and 30+ other brands (power banks, chargers, cables, earbuds, wireless and car chargers). Cases, screen protectors, lens protectors, stands and watch bands are not seeded (no source collected).

- **Prices change often.** Re-run the scraper or edit in Admin > Products before selling.
- **Images belong to their owners** (PriceOye, the brands). Confirm you may use them on a live store; every image keeps its `source_url`. 25 products have no photo because the source only has a placeholder.
- **Unknown, so not invented:** specifications, warranty, ratings, sales counts, sale prices, wholesale tiers, cost prices. Opening stock is unknown: every variant starts at 25 (`RealCatalogSeeder::OPENING_STOCK`); set real counts with Adjust stock. Shipping rates seeded by `ContentSeeder` are placeholders to edit.

### Colours, specs and reviews
The scraper also collects, per product: the **specifications** the source publishes, each **colour option with its own price, availability and photo gallery** (the product page switches photos and price when you pick a colour; cards show colour dots), This store shows only its own customers' reviews (after delivery, moderated): reviews from other sites are deliberately NOT imported or displayed, and no page links to another store. Variants the source lists as sold out start at stock 0; ~40% of products are fully sold out at the source, so listings show available products first.

### Policy pages
`PolicyPagesSeeder` writes About, Shipping, Returns & refunds, Privacy, Terms and Warranty pages with tokens (`{store_name}`, `{contact_email}`, `{contact_link}`, `{updated_date}`) filled from Admin > Store settings. They are standard-practice drafts for a Pakistani accessories store (e.g. 7-day return window): **have them reviewed against your real operations and applicable law, and edit them in Admin > Pages.** Seeding never overwrites pages you have edited.
