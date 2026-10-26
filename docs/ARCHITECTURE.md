# Voltix — Architecture & Roadmap

"Voltix" is a **placeholder brand name**; it is stored in `store_settings` / `config('store.name')` and can be changed without code edits.

## 1. Repository audit (Phase 1)

| Item | Finding |
|---|---|
| Repo state | Empty directory, not a git repo → greenfield |
| PHP | 8.2.12 (XAMPP) → **Laravel 13 needs 8.3+, so Laravel 12 was chosen** |
| Laravel | 12.x (monolith) |
| Admin | Filament 4 |
| Roles | spatie/laravel-permission 6 |
| Tests | Pest 3 |
| DB | MySQL (XAMPP) — `store`, `store_testing` |
| Node | 24 / npm 11 (Vite, Tailwind 4 from the Laravel starter) |
| Not installed | Redis (cache/queue use `database` driver; Redis switch is config-only) |

## 2. Architecture decisions

* **Monolith**: Blade + Tailwind + Alpine on the storefront, Filament at `/admin`.
* **Every product has ≥1 variant.** Price, SKU, stock and tiers live on `product_variants`. A "simple" product is a product with one default variant. This removes "does it have variants?" branching in cart, stock and orders.
* **Money** is `decimal(12,2)`, currency PKR (configurable). All pricing is computed server-side by `App\Services\Pricing\PriceResolver`; the browser never submits a price.
* **Price resolution order** (retail vs wholesale enforced server-side):
  1. Negotiated price (`wholesale_prices`) — approved wholesale users only
  2. Quantity tier (`price_tiers`) — approved wholesale users only, when `wholesale_enabled`
  3. Sale price (if within schedule)
  4. Regular price
* **Stock**: `product_variants.stock_quantity` is only changed through `StockService`, which writes a `stock_movements` row in the same transaction and locks the row (`lockForUpdate`).
* **Order snapshots**: `order_items` copy name/SKU/variant/price/discount/tax so history survives product edits.
* **Payments**: `PaymentGateway` interface + `payments`/`payment_transactions`. COD and bank transfer are real; JazzCash/Easypaisa/card are *not* implemented until merchant credentials exist.
* **Authorization**: Policies + Filament resource permissions via spatie roles (Super Admin, Store Manager, Inventory Manager, Order Manager, Customer Support, Wholesale Manager). Cost price is gated by the `view cost prices` permission.

## 3. Schema (migrations in `database/migrations`)

```
users ─┬─< addresses
       ├─1 wholesale_applications (status: pending/approved/rejected)
       ├─< wholesale_prices >─ product_variants   (negotiated)
       ├─< carts ─< cart_items >─ product_variants
       ├─< wishlist_items >─ products
       ├─< orders ─┬─< order_items (snapshots) ─< return_requests
       │           ├─< order_status_histories
       │           ├─< payments ─< payment_transactions ; payments ─< refunds
       │           └─< shipments ─< shipment_items
       ├─< quotes ─< quote_items
       └─< reviews >─ products

brands ─< products >─ categories (self-referencing tree)
products ─< product_variants ─< price_tiers
products ─< product_images ; products >─< device_models >─ device_brands
products >─< products (related, via product_relations) ; bundle_items (bundle product → variants)
product_variants ─< stock_movements ; suppliers ─< purchase_orders ─< purchase_order_items
coupons ; shipping_zones ─< shipping_methods ; slug_redirects
content: banners, pages, faqs, home_sections, newsletter_subscribers, contact_messages, store_settings, audit_logs
```

## 4. Storefront sitemap

`/` · `/shop` · `/category/{slug}` · `/brand/{slug}` · `/device/{brand}/{model}` · `/product/{slug}` · `/search` · `/deals` · `/new-arrivals` · `/best-sellers` · `/bundles` · `/cart` · `/checkout` · `/order/confirmation/{ref}` · `/track-order` · `/login` `/register` · `/account/*` (dashboard, orders, addresses, wishlist, reviews, returns, wholesale) · `/wholesale/register` · `/wholesale/*` (dashboard, bulk-order, CSV upload, quotes) · `/pages/{slug}` (about, contact, FAQ, policies) · `/sitemap.xml` · `/robots.txt` · 404/500.

## 5. Admin modules (Filament)

Dashboard · Products(+variants, tiers, images) · Categories · Brands · Device brands/models · Inventory (stock adjust, movements) · Suppliers & Purchase Orders · Orders · Payments & Refunds · Shipping zones/methods & Shipments · Customers · Wholesale applications/pricing · Quotes · Coupons · Reviews · Returns · Banners/Home sections/Pages/FAQs · Newsletter · Contact messages · Settings · Roles/Users · Audit log.

## 6. Workflows

**Retail**: browse → add to cart (server validates stock) → checkout (details, address, shipping, payment) → transaction: lock variants, recompute prices, decrement stock + movement, create order/items/payment → confirmation email → admin processes/ships → delivered. COD payments settle on delivery; bank transfer stays `pending` until admin verifies.

**Wholesale**: apply (business details + optional document in private storage) → admin approves → wholesale pricing/tier table visible, MOQ enforced → quick-order / CSV bulk cart → checkout or **request quote** → admin replies with quote (expiry) → customer accepts → quote converts to order at the quoted prices.

## 7. Phase checklist

- [x] **P1** Audit, architecture, schema design
- [x] **P2** Foundation: install, design system, layout, core migrations
- [x] **P3** Catalog: models, seeders, shop/category/brand/device/product pages, search & filters
- [x] **P4** Auth, accounts, wishlist, cart, coupons, checkout, order history
- [x] **P5** Wholesale: applications, tiers, MOQ, bulk cart, CSV, quotes
- [x] **P6** Filament admin, roles, inventory, suppliers, shipping, reports
- [x] **P7** Payments, emails, tracking
- [x] **P8** Promotions, bundles, SEO, safe caching
- [x] **P9** Test + harden
- [x] **P10** Deployment guide

## 8. Business decisions needing owner input (defaults chosen)

| Decision | Default used |
|---|---|
| Brand name / logo | "Voltix" placeholder |
| Currency | PKR |
| Guest checkout | Enabled |
| Tax | 0% (configurable) |
| Shipping | Flat Rs 250, free over Rs 5,000 (retail), editable |
| Wholesale MOQ / min order | Per-variant MOQ; min order value Rs 25,000 (setting) |
| Payment gateways | COD + bank transfer only; JazzCash/Easypaisa/card need merchant credentials |
| Wholesale approval | Manual by admin |
| Courier | Manual tracking entry; courier API later via `ShipmentProvider` |
| Policy text | Seeded placeholder copy — **must be reviewed by you/legal before launch** |
