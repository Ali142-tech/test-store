# Deployment guide (Linux / Nginx / AWS EC2)

Nothing here is run automatically. **Never run migrations, seeders or restores on production without a verified backup.**
`DatabaseSeeder` only seeds roles, settings and default shipping in production; demo catalog and the demo admin are created **only when APP_ENV is not production**.

## 1. Server prerequisites (Ubuntu 22.04/24.04 example)
- PHP 8.2+ with: bcmath, ctype, curl, fileinfo, gd, intl, mbstring, mysql, openssl, xml, zip, redis (optional)
- Nginx, MySQL 8 (or MariaDB 10.6+), Composer 2, Node 20+ (build only), Supervisor, Certbot, Redis (optional)

## 2. First-time setup
```bash
sudo mkdir -p /var/www/store && sudo chown $USER /var/www/store && cd /var/www/store
git clone <your-repo> releases/1 && ln -sfn releases/1 current && cd current
composer install --no-dev --optimize-autoloader
# The built front end (public/build) is COMMITTED to git, so the server needs no Node.
# After changing any CSS/JS/Blade classes, run `npm run build` locally and commit public/build before pushing.
# (If you build on the server instead: `npm ci && npm run build`.) A stale public/build breaks the layout, side menu and dark theme.
cp .env.production.example .env    # fill in real values; APP_DEBUG=false
php artisan key:generate --force   # ONLY the first time. Changing APP_KEY later invalidates sessions and encrypted data.
```
Keep `storage/` and `.env` outside releases (shared dir) and symlink them into each release.

## 3. Database
```sql
CREATE DATABASE store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'store_app'@'localhost' IDENTIFIED BY '<strong password>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES, LOCK TABLES ON store.* TO 'store_app'@'localhost';
```
(After the first migration you may revoke CREATE/ALTER/DROP and use a separate migration user for deploys.)

```bash
php artisan migrate --force        # safe, additive. Take a backup first (section 8).
php artisan db:seed --class=RolesSeeder --force
php artisan db:seed --class=ContentSeeder --force   # placeholder policies/settings: EDIT them afterwards
```
**Create the first admin** (no hard-coded credentials; the password is typed at a hidden prompt):
```bash
php artisan store:create-admin you@yourdomain.com --name="Your Name" --store-name="Your Store" --contact-email=support@yourdomain.com
```
Then sign in at `/admin`, change the password, and create other staff under *Customers > Users* (roles: Store Manager, Inventory Manager, Order Manager, Customer Support, Wholesale Manager).

## 4. Storage and permissions
```bash
php artisan storage:link           # only if MEDIA_DISK=public
sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
```
`storage/app/private` holds wholesale documents and is never exposed by Nginx (see `deploy/nginx.conf`). For S3 set `MEDIA_DISK=s3` and the AWS_* variables; keep the bucket private for documents (documents never use the media disk).

## 5. Nginx + HTTPS
Copy `deploy/nginx.conf` to `/etc/nginx/sites-available/store`, enable it, then `sudo certbot --nginx -d shop.example.com`. Auto-renew is installed by Certbot. `APP_URL` must be the https URL; the app forces https URLs and `SESSION_SECURE_COOKIE=true` in production.
Open only ports 80/443 (and SSH restricted by IP) in the security group; keep MySQL/Redis private.

## 6. Queue worker and scheduler
```bash
sudo cp deploy/supervisor-queue.conf /etc/supervisor/conf.d/store-worker.conf
sudo supervisorctl reread && sudo supervisorctl update
```
Cron (`crontab -u www-data -e`): `* * * * * cd /var/www/store/current && php artisan schedule:run >> /dev/null 2>&1`
Scheduled jobs: `quotes:expire` (hourly), `carts:remind` (hourly, consent-only), `stock:low-alert` (daily 08:00), plus `model:prune`. Emails are queued: **if the worker is down, no emails are sent.**
Optional (not scheduled by default): `php artisan reviews:request` daily.

## 7. Optimise and release
```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan filament:optimize      # optional
sudo supervisorctl restart store-worker:*
sudo systemctl reload php8.2-fpm
```
**Zero-downtime release:** build a new `releases/N`, link shared `.env`/`storage`, run `composer install`, `npm ci && npm run build`, `php artisan migrate --force`, caches, then `ln -sfn releases/N current` and restart workers.
**Rollback:** `ln -sfn releases/<previous> current`, restart workers. If the release included a migration, roll the code back first and only run `php artisan migrate:rollback --step=N` after confirming the migration's `down()` is safe for the data (restore from backup if not).

## 8. Backups and restore
Nightly (cron as a user that can read the DB):
```bash
mysqldump --single-transaction --routines --triggers -u backup -p store | gzip > /backups/store-$(date +%F).sql.gz
tar czf /backups/store-files-$(date +%F).tar.gz storage/app   # private documents (+ public images if MEDIA_DISK=public)
```
Ship backups off the server (S3 with versioning / another region) and keep 14+ days. **Test a restore monthly** on a scratch database:
```bash
gunzip < store-YYYY-MM-DD.sql.gz | mysql -u root -p store_restore_test
```
Restoring over production: put the site in maintenance mode (`php artisan down`), take a fresh dump of the current state first, restore, `php artisan up`.

## 9. Monitoring and logging
- Logs: `storage/logs/laravel-*.log` (daily, 14 days). Ship to CloudWatch/Papertrail if possible.
- Uptime: probe `https://shop.example.com/up` (Laravel health route).
- Alerts: failed jobs (`php artisan queue:failed`), disk space, MySQL slow query log, certificate expiry.
- Optional: Sentry/Flare via their Laravel SDKs (no extra code needed beyond the package + DSN).

## 10. Security checklist before launch
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, unique `APP_KEY` stored in a password manager
- [ ] Real SMTP configured and a test order/registration email received
- [ ] Policy pages (shipping, returns, privacy, terms, warranty) replaced with reviewed text
- [ ] Bank details entered under Admin > Store settings if bank transfer is on
- [ ] Demo data not present (`php artisan tinker --execute="echo App\Models\Product::count();"` should be 0 before you add products)
- [ ] `composer audit` and `npm audit` clean; update dependencies regularly
- [ ] MySQL user has no global privileges; DB and Redis not exposed publicly
- [ ] Backups running AND a restore tested

## Currency by country (USD / GBP / EUR)

Prices are stored and charged in PKR. Visitors from the USA/Canada see USD, the UK sees GBP, eurozone countries see EUR and everyone else (including Pakistan) sees PKR. International visitors pay an extra fee on **every unit** (default US$30) which is added server-side and then converted.

- **Run `php artisan migrate --force`** after deploying (adds `display_currency` / `display_rate` to orders).
- Set the rates and fee in **Admin > Store settings** (PKR per USD / GBP / EUR, extra fee in US$). The seeded rates (280 / 370 / 325) are placeholders: update them to your real rates. Changing a rate never changes orders already placed (each order keeps the rate it was placed with).
- Country detection: uses the `CF-IPCountry` header when the site is behind Cloudflare (recommended, free, instant); otherwise it looks the visitor's IP up once a day via ip-api.com and caches the answer. If detection fails the visitor simply sees PKR.
- Do not cache whole HTML pages at a CDN (prices depend on the visitor's country).
- Shipping rates and the free-shipping threshold are still PKR amounts, converted for display.

## Email is switched off by default

The store currently has no email service. With `STORE_MAIL_ENABLED=false` (the default) nothing is ever sent: no order or status emails, no wholesale/quote emails, no cart/review/low-stock mails, and password reset is hidden (the pages return 404). Customers register and sign in with email + password only (no email verification). To turn email on later: set `STORE_MAIL_ENABLED=true`, fill in the `MAIL_*` settings, then `php artisan config:cache`. Until then, an admin can set a customer's password in Admin > Users.
