<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Amounts stay in PKR; these remember which currency/rate the customer saw (PKR per 1 unit).
            $table->string('display_currency', 3)->nullable()->after('currency');
            $table->decimal('display_rate', 14, 6)->nullable()->after('display_currency');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['display_currency', 'display_rate']));
    }
};
