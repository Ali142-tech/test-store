<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('reference');
            $table->string('payment_method', 30)->nullable()->after('payment_status');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->decimal('shipping_amount', 12, 2)->default(0)->after('quoted_total');
        });

        Schema::create('quote_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30); // requested|quoted|revised|accepted|rejected|expired|converted
            $table->json('snapshot')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('wholesale_applications', function (Blueprint $table) {
            $table->string('document_original_name')->nullable()->after('document_path');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex(['published_at']));
        Schema::table('wholesale_applications', fn (Blueprint $t) => $t->dropColumn('document_original_name'));
        Schema::dropIfExists('quote_events');
        Schema::table('quotes', fn (Blueprint $t) => $t->dropColumn('shipping_amount'));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropColumn(['idempotency_key', 'payment_method']);
        });
    }
};
