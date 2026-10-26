<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ContentSeeder::class, PolicyPagesSeeder::class]);

        // Demo data and a known-password admin only outside production.
        if (! app()->isProduction()) {
            $this->call([RealCatalogSeeder::class, SampleReviewsSeeder::class]);

            $admin = User::firstOrCreate(['email' => 'admin@voltix.test'], [
                'name' => 'Demo Admin', 'password' => 'password',
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();
            $admin->assignRole('Super Admin');
        }
    }
}
