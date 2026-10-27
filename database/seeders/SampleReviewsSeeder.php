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
        'Game Consoles' => [
            [5, 'Smooth and fast', 'Loads games quickly and runs quietly. Setup took only a few minutes.'],
            [4, 'Great for the family', 'Plenty of storage and the graphics look excellent on a good TV.'],
            [5, 'Worth the upgrade', 'Noticeably better performance than my old console. Very happy.'],
            [3, 'Good, a bit pricey', 'Performs well, though I wish it came with a second controller.'],
        ],
        'Controllers & Gamepads' => [
            [5, 'Comfortable grip', 'Sticks and triggers feel precise and the battery lasts through long sessions.'],
            [4, 'Solid build', 'Connects instantly and the buttons feel responsive.'],
            [5, 'Great in competitive games', 'Low latency and a good layout. Easy to pair.'],
            [3, 'Decent', 'Works well; the d-pad could be a little firmer.'],
        ],
        'Gaming Keyboards' => [
            [5, 'Satisfying to type on', 'Great key feel and the RGB looks sharp. Good for both gaming and work.'],
            [4, 'Fast and responsive', 'Keys register instantly and the build is sturdy.'],
            [5, 'Excellent value', 'Hot-swappable and well built. Better than I expected.'],
            [3, 'Good but loud', 'Typing feels great, though the switches are on the louder side.'],
        ],
        'Gaming Mice' => [
            [5, 'Light and precise', 'Tracks perfectly and the shape fits my hand well.'],
            [4, 'Great sensor', 'Wireless connection is stable and the battery lasts a long time.'],
            [5, 'Perfect for FPS', 'Very light with accurate clicks. Highly recommended.'],
            [3, 'Fine', 'Works well, software could be simpler.'],
        ],
        'Gaming Headsets & Headphones' => [
            [5, 'Immersive sound', 'Clear highs, strong bass and a comfortable fit for long sessions.'],
            [4, 'Comfortable and clear', 'The microphone is clear and noise cancelling works well.'],
            [5, 'Excellent quality', 'Sound is excellent for both games and music.'],
            [3, 'Good overall', 'Sound is great, though the earcups get warm after a few hours.'],
        ],
        'Gaming Chairs' => [
            [5, 'Very comfortable', 'Good back support and easy to adjust. Great for long sessions.'],
            [4, 'Sturdy build', 'Assembly took a while but the chair feels solid.'],
            [4, 'Looks great', 'Comfortable cushioning and smooth reclining.'],
        ],
        'VR Headsets' => [
            [5, 'Amazing experience', 'Sharp visuals and comfortable to wear. Setup was straightforward.'],
            [4, 'Fun for the whole family', 'Great library of games and tracking works well.'],
            [5, 'Impressive', 'Immersive and smooth. A real step up.'],
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
            foreach (($products[$category] ?? collect())->take(12) as $n => $product) {
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
