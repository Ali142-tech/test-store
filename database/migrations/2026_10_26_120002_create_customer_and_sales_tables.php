<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('full_name');
            $table->string('phone', 30);
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('state')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 2)->default('PK');
            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);
            $table->timestamps();
        });

        Schema::create('wholesale_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('business_name');
            $table->string('business_type')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('contact_name');
            $table->string('contact_phone', 30);
            $table->text('business_address');
            $table->string('city');
            $table->string('document_path')->nullable(); // private disk
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index(); // pending|approved|rejected
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wholesale_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_variant_id']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->string('coupon_code', 50)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamps();
            $table->unique(['cart_id', 'product_variant_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description')->nullable();
            $table->string('type', 20); // percent|fixed|free_shipping
            $table->decimal('value', 12, 2)->default(0);
            $table->decimal('min_subtotal', 12, 2)->nullable();
            $table->decimal('max_discount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->string('audience', 20)->default('all'); // all|retail|wholesale
            $table->json('product_ids')->nullable();  // null = all products
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('cities')->nullable();     // null = all cities in country
            $table->string('country', 2)->default('PK');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('audience', 20)->default('all'); // all|retail|wholesale
            $table->decimal('base_rate', 12, 2)->default(0);
            $table->decimal('per_kg_rate', 12, 2)->default(0);
            $table->decimal('free_over', 12, 2)->nullable();
            $table->unsignedSmallInteger('min_days')->nullable();
            $table->unsignedSmallInteger('max_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->string('customer_type', 20)->default('retail')->index();
            $table->string('email');
            $table->string('phone', 30);
            $table->string('status', 20)->default('pending')->index(); // pending|confirmed|processing|shipped|delivered|cancelled|refunded
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid|pending|paid|failed|refunded|partially_refunded
            $table->string('fulfillment_status', 20)->default('unfulfilled'); // unfulfilled|partial|fulfilled
            $table->string('currency', 3)->default('PKR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->decimal('refunded_total', 12, 2)->default(0);
            $table->string('coupon_code', 50)->nullable();
            $table->string('shipping_method_name')->nullable();
            $table->json('shipping_address');
            $table->json('billing_address');
            $table->text('customer_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->foreignId('quote_id')->nullable();
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'placed_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            // snapshots
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('sku');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('price_source', 20)->default('regular'); // regular|sale|tier|negotiated|quote
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('refunded_quantity')->default(0);
            $table->unsignedInteger('shipped_quantity')->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('method', 30); // cod|bank_transfer|jazzcash|easypaisa|card
            $table->string('status', 20)->default('pending')->index(); // pending|successful|failed|cancelled|refunded
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('PKR');
            $table->string('reference')->nullable();
            $table->string('proof_path')->nullable(); // bank-transfer receipt, private disk
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 30);
            $table->string('provider_reference')->nullable();
            $table->string('event_id')->nullable(); // webhook idempotency key
            $table->string('type', 20); // attempt|webhook|refund|reconcile
            $table->string('status', 20);
            $table->decimal('amount', 12, 2)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->text('reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('courier')->nullable();
            $table->string('tracking_number')->nullable()->index();
            $table->string('tracking_url')->nullable();
            $table->string('status', 20)->default('pending'); // pending|in_transit|delivered|returned
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
        });

        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->text('reason');
            $table->string('status', 20)->default('requested'); // requested|approved|rejected|received|refunded
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('requested')->index(); // requested|quoted|accepted|rejected|expired|converted|revised
            $table->text('customer_notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->decimal('quoted_total', 12, 2)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('revision')->default(1);
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('target_price', 12, 2)->nullable();
            $table->decimal('quoted_price', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('quote_id')->references('id')->on('quotes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropForeign(['quote_id']));
        foreach (['quote_items', 'quotes', 'return_requests', 'shipment_items', 'shipments', 'refunds', 'payment_transactions', 'payments', 'order_status_histories', 'order_items', 'orders', 'shipping_methods', 'shipping_zones', 'coupons', 'cart_items', 'carts', 'wishlist_items', 'wholesale_prices', 'wholesale_applications', 'addresses'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
