<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('customer_type', 20)->default('retail')->after('phone')->index(); // retail|wholesale
            $table->boolean('is_active')->default(true)->after('customer_type');
            $table->boolean('marketing_consent')->default(false)->after('is_active');
            $table->softDeletes();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('device_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('device_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('release_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['device_brand_id', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('simple'); // simple|bundle
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('material')->nullable();
            $table->text('authenticity_note')->nullable();   // only verified claims
            $table->boolean('is_authentic_verified')->default(false);
            $table->text('compatibility_note')->nullable();
            $table->string('warranty')->nullable();
            $table->json('specifications')->nullable();
            $table->decimal('weight_grams', 10, 2)->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft|active|archived
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_best_seller')->default(false)->index();
            $table->boolean('wholesale_enabled')->default(false)->index();
            $table->unsignedInteger('sales_count')->default(0)->index();
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->string('seo_image')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'category_id']);
            $table->index(['status', 'brand_id']);
            $table->fullText(['name', 'short_description']);
        });

        // Every product has >= 1 variant: price, SKU and stock live here.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->index();
            $table->string('name')->nullable();          // e.g. "Black / 1m"
            $table->string('color')->nullable()->index();
            $table->json('attributes')->nullable();       // {"length":"1m","power":"20W"}
            $table->decimal('price', 12, 2);              // regular retail price
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->decimal('cost_price', 12, 2)->nullable(); // restricted
            $table->integer('stock_quantity')->default(0);
            $table->integer('reserved_quantity')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(5);
            $table->unsignedInteger('wholesale_moq')->default(1); // minimum order qty for wholesale
            $table->string('image')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('min_quantity');
            $table->unsignedInteger('max_quantity')->nullable();
            $table->decimal('price', 12, 2);
            $table->timestamps();
            $table->unique(['product_variant_id', 'min_quantity']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('device_model_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_model_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'device_model_id']);
        });

        Schema::create('product_relations', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('products')->cascadeOnDelete();
            $table->string('kind', 20)->default('related'); // related|upsell|frequently_bought
            $table->primary(['product_id', 'related_product_id', 'kind']);
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
        });

        Schema::create('slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path')->unique();
            $table->string('new_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['slug_redirects', 'bundle_items', 'product_relations', 'device_model_product', 'product_images', 'price_tiers', 'product_variants', 'products', 'device_models', 'device_brands', 'categories', 'brands'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['phone', 'customer_type', 'is_active', 'marketing_consent']);
        });
    }
};
