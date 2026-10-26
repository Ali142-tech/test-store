<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

/** Placeholder policy copy. Must be reviewed by the owner / legal counsel before launch. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $notice = '<p><strong>Placeholder text.</strong> Replace this with your real policy before launch.</p>';
        $pages = [
            'about' => 'About us', 'shipping-policy' => 'Shipping policy', 'returns-and-refunds' => 'Returns & refunds policy',
            'privacy-policy' => 'Privacy policy', 'terms-and-conditions' => 'Terms & conditions', 'warranty-policy' => 'Warranty policy',
        ];
        foreach ($pages as $slug => $title) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'body' => $notice]);
        }

        $faqs = [
            ['Do you offer wholesale pricing?', 'Yes. Register a wholesale account; once approved you will see tiered bulk pricing and can request quotes.'],
            ['Which payment methods do you accept?', 'Cash on delivery and bank transfer. Other methods will be listed once enabled.'],
            ['How do I know an accessory fits my phone?', 'Each product lists the compatible models. Use the device filter in the shop.'],
            ['What is your returns process?', 'See the returns & refunds policy, then submit a return request from your account.'],
        ];
        foreach ($faqs as $i => [$q, $a]) {
            Faq::firstOrCreate(['question' => $q], ['answer' => $a, 'sort_order' => $i]);
        }

        $settings = [
            'store_name' => 'Voltix', 'currency' => 'PKR', 'currency_symbol' => 'Rs',
            'announcement' => 'Free delivery over Rs 5,000 · Wholesale pricing available for shops',
            'contact_email' => 'support@example.com', 'contact_phone' => '+92 000 0000000',
            'tax_rate' => '0', 'guest_checkout' => '1',
            'wholesale_min_order_value' => '25000',
        ];
        $settings += [
            'payment_cod_enabled' => '1', 'payment_cod_wholesale' => '0', 'payment_bank_enabled' => '1',
            'bank_name' => '', 'bank_account_title' => '', 'bank_account_number' => '',
            'bank_instructions' => 'Use your order reference as the payment reference and email the receipt to us.',
        ];

        // Default delivery: all cities, retail flat rate + wholesale freight. Editable in the admin panel.
        $zone = \App\Models\ShippingZone::firstOrCreate(['name' => 'Pakistan (all cities)'], ['cities' => null, 'country' => 'PK']);
        $zone->methods()->firstOrCreate(['name' => 'Standard delivery', 'audience' => 'retail'], ['base_rate' => 250, 'free_over' => 5000, 'min_days' => 2, 'max_days' => 5]);
        $zone->methods()->firstOrCreate(['name' => 'Wholesale freight', 'audience' => 'wholesale'], ['base_rate' => 500, 'per_kg_rate' => 20, 'free_over' => 100000, 'min_days' => 3, 'max_days' => 7]);

        foreach ($settings as $k => $v) {
            StoreSetting::firstOrCreate(['key' => $k], ['value' => $v]);
        }
    }
}
