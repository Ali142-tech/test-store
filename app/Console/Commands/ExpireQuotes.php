<?php

namespace App\Console\Commands;

use App\Models\Quote;
use Illuminate\Console\Command;

class ExpireQuotes extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Mark quoted/accepted quotes past their expiry date as expired';

    public function handle(): int
    {
        $n = 0;
        Quote::whereIn('status', ['quoted', 'accepted'])->where('expires_at', '<', now())->each(function (Quote $q) use (&$n) {
            $q->update(['status' => 'expired']);
            $q->events()->create(['event' => 'expired', 'note' => 'Expired automatically']);
            $n++;
        });
        $this->info("Expired {$n} quote(s).");

        return self::SUCCESS;
    }
}
