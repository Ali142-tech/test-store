<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class LowStockAlert extends Command
{
    protected $signature = 'stock:low-alert';

    protected $description = 'Email inventory staff a digest of variants at or below their low-stock threshold';

    public function handle(): int
    {
        $rows = ProductVariant::with('product')->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('type', 'simple')->where('status', 'active'))
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->orderBy('stock_quantity')->get();

        if ($rows->isEmpty()) {
            $this->info('No low-stock variants.');

            return self::SUCCESS;
        }

        $body = $rows->map(fn ($v) => "{$v->sku} - {$v->displayName()}: {$v->stock_quantity} left (threshold {$v->low_stock_threshold})")->implode("\n");
        $to = User::permission('manage inventory')->where('is_active', true)->pluck('email');
        foreach ($to as $email) {
            Mail::raw("Low-stock digest:\n\n{$body}", fn ($m) => $m->to($email)->subject('Low stock: '.$rows->count().' variant(s)'));
        }
        $this->info("Alert sent to {$to->count()} staff for {$rows->count()} variants.");

        return self::SUCCESS;
    }
}
