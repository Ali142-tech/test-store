<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\User;
use Illuminate\Console\Command;

class PurgeSampleReviews extends Command
{
    protected $signature = 'reviews:purge-samples';

    protected $description = 'Delete demo sample reviews and their placeholder accounts (real reviews are never touched)';

    public function handle(): int
    {
        $reviews = Review::where('is_sample', true)->delete();
        $users = User::where('email', 'like', '%@sample.invalid')->doesntHave('orders')->forceDelete();
        $this->info("Deleted {$reviews} sample review(s) and {$users} placeholder account(s).");

        return self::SUCCESS;
    }
}
