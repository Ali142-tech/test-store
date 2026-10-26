<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * SAMPLE (demo) reviews so a presentation site does not look empty.
 *
 * They are NOT real customer feedback. Every row is flagged is_sample = 1, shows a visible "Sample review" tag, is
 * excluded from search-engine structured data, comes from non-login placeholder accounts (@sample.invalid), and never
 * seeds in production. Remove them any time with: php artisan reviews:purge-samples
 */
class SampleReviewsSeeder extends Seeder
{
    private const NAMES = ['Ahmed R.', 'Sana K.', 'Bilal M.', 'Hira S.', 'Usman T.', 'Ayesha N.', 'Faisal H.', 'Zainab A.', 'Hamza Q.', 'Mariam F.'];

    /** category => [[rating, title, body], ...] : generic, product-type specific, no delivery/shop claims. */
    private const POOL = [
        'Power Banks' => [
            [5, 'Charges my phone several times', 'Holds a lot of charge and tops up my phone quickly. Easy to carry in a bag.'],
            [4, 'Good for travel', 'Does the job on trips. A little heavy but the capacity makes up for it.'],
            [5, 'Does what it says', 'Charged two devices at once without getting very hot. Happy with it.'],
            [3, 'Okay', 'Works fine, but I wish the indicator lights were clearer.'],
        ],
        'Chargers & Adapters' => [
            [5, 'Fast and compact', 'Charges noticeably faster than my old adapter and it stays cool.'],
            [4, 'Solid charger', 'Fits well in the socket and charges my phone quickly. Good value.'],
            [5, 'Works great', 'Plug in and go. Fast charging works as expected with my phone.'],
            [3, 'Fine for the price', 'Charges well, though the body feels a bit light.'],
        ],
        'Cables' => [
            [5, 'Sturdy cable', 'Feels well made and charges at a good speed. No problems so far.'],
            [4, 'Good length', 'Nice length for the bedside. Connectors fit snugly.'],
            [5, 'Value for money', 'Does everything I need for the price. Would buy again.'],
            [3, 'Decent', 'Works well. Time will tell how it holds up.'],
        ],
        'Earbuds & Headphones' => [
            [5, 'Clear sound', 'Good bass and clear calls. Pairing with my phone was instant.'],
            [4, 'Comfortable fit', 'Sit comfortably for long listening and the battery lasts well.'],
            [5, 'Great for the price', 'Sound is better than I expected at this price. Case is neat too.'],
            [3, 'Average', 'Sound is okay. Microphone could be better in noisy places.'],
        ],
        'Wireless Chargers' => [
            [5, 'Convenient', 'Just place the phone and it starts charging. Great for my desk.'],
            [4, 'Reliable', 'Charges steadily and does not get too warm. Looks neat.'],
            [4, 'Handy on the bedside', 'Easy to use and tidy. Charging is slower than a cable, as expected.'],
        ],
        'Car Accessories' => [
            [5, 'Handy in the car', 'Fits the socket firmly and charges my phone fast on the road.'],
            [4, 'Works well', 'Charges two devices at once during trips. Solid build.'],
            [5, 'Good buy', 'Compact and does the job. Happy with it.'],
            [3, 'Fine', 'Works as described. Wish it had an indicator light.'],
        ],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('SampleReviewsSeeder never runs in production.');

            return;
        }

        $users = collect(self::NAMES)->map(function ($name, $i) {
            return User::firstOrCreate(['email' => 'sample'.($i + 1).'@sample.invalid'], [
                'name' => $name, 'password' => Hash::make(Str::random(40)), // nobody can sign in as a sample account
            ]);
        });

        $products = Product::active()->inStock()->whereHas('images')->with('category')->orderBy('id')->get()
            ->groupBy(fn ($p) => $p->category?->name)->map(fn ($g) => $g->values());

        foreach (self::POOL as $category => $pool) {
            foreach (($products[$category] ?? collect())->take(14) as $n => $product) {
                // 1-3 reviews per product, varied but deterministic.
                foreach (range(0, $n % 3) as $k) {
                    [$rating, $title, $body] = $pool[($n + $k * 2) % count($pool)];
                    $user = $users[($n * 3 + $k) % $users->count()];
                    if (Review::where('product_id', $product->id)->where('user_id', $user->id)->exists()) {
                        continue;
                    }
                    $review = Review::create(['product_id' => $product->id, 'user_id' => $user->id, 'rating' => $rating, 'title' => $title, 'body' => $body]);
                    $review->forceFill(['status' => 'approved', 'is_sample' => true, 'created_at' => now()->subDays(3 + ($n * 5 + $k * 11) % 40)])->save();
                }
            }
        }
    }
}
