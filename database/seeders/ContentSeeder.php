<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

/** Store settings, FAQs and default delivery. Review every value in Admin > Store settings. */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // Policy pages (about, shipping, returns, privacy, terms, warranty) are seeded by PolicyPagesSeeder.
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
            'store_name' => 'DigiPower LLC', 'rate_usd' => '280', 'rate_gbp' => '370', 'rate_eur' => '325', 'extra_usd' => '30', 'currency' => 'PKR', 'currency_symbol' => 'Rs',
            'announcement' => 'Prices in PKR · Retail and wholesale accounts available',
            'contact_email' => 'Mushaf@digipowerllc.com', 'contact_phone' => '+1 858 832 0759 / +92 302 7171164', 'whatsapp_number' => '923027171164',
            'tax_rate' => '0', 'guest_checkout' => '1',
            'wholesale_min_order_value' => '0',
        ];
        $settings += [
            'payment_cod_enabled' => '1', 'payment_cod_wholesale' => '0', 'payment_bank_enabled' => '1',
            'bank_name' => '', 'bank_account_title' => '', 'bank_account_number' => '',
            'bank_instructions' => 'Use your order reference as the payment reference and email the receipt to us.',
            'bank_international_note' => 'Payments from outside Pakistan: please use Taptap Send or Remitly to send the amount to the account above, and use your order reference as the payment reference.',
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
