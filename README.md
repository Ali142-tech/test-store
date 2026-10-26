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
