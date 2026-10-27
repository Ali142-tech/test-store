<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Store policy pages. Written as sensible, standard e-commerce terms for a Pakistan-based gaming store that sells
 * to retail and wholesale customers (COD / bank transfer). They are a drafting aid, NOT legal advice: have them
 * reviewed against your real operations and applicable law before launch.
 *
 * Tokens replaced at render time (Page::safeBody): {store_name} {contact_email} {contact_phone} {contact_link}
 * so the text stays correct when Admin > Store settings change.
 */
class PolicyPagesSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->pages() as $slug => [$title, $body, $desc]) {
            $page = Page::firstOrNew(['slug' => $slug]);
            // Never overwrite a policy an administrator has already edited.
            if (! $page->exists || str_contains((string) $page->body, 'Placeholder text')) {
                $page->fill(['title' => $title, 'body' => $body, 'seo_description' => $desc, 'is_published' => true])->save();
            }
        }
    }

    /** @return array<string, array{0:string,1:string,2:string}> */
    private function pages(): array
    {
        return [
            'about' => ['About us', <<<'HTML'
<p>{store_name} is an online store for premium gaming gear: consoles (PlayStation, Xbox, Nintendo), controllers, mechanical keyboards, gaming mice, headsets, gaming monitors and VR headsets from leading brands.</p>
<h2>Retail and wholesale</h2>
<p>We serve two kinds of customers. Individual shoppers can buy single items at retail prices. Gaming shops, resellers and other businesses can apply for a wholesale account to see bulk pricing, place large orders and request custom quotations.</p>
<h2>How we list products</h2>
<ul>
<li>Every product page shows the brand, model, colour options and the specifications published for that model.</li>
<li>Prices are in Pakistani Rupees (PKR). We update them as the market changes, so the price at checkout is always the one that applies.</li>
<li>We only state what we can verify. If a detail such as a warranty period is not shown on a product page, please ask us before you buy.</li>
</ul>
<h2>Contact</h2>
<p>Questions about an order, a product or wholesale pricing? Reach us through {contact_link}.</p>
HTML, 'About our gaming gear store for retail and wholesale customers in Pakistan.'],

            'shipping-policy' => ['Shipping policy', <<<'HTML'
<p><em>Last updated: {updated_date}</em></p>
<h2>Where we deliver</h2>
<p>We deliver to addresses in Pakistan. Available cities and delivery charges are shown at checkout once you enter your address; if your city is not listed, we cannot deliver there yet.</p>
<h2>Delivery charges</h2>
<p>Delivery charges depend on your delivery zone, the shipping method you choose and, for some methods, the weight of your order. Retail and wholesale orders can have different shipping methods. Some methods are free above a minimum order value: when that applies, the free rate appears automatically at checkout. The exact charge is always shown before you place your order and is never changed afterwards.</p>
<h2>Processing and delivery time</h2>
<ul>
<li>Orders are normally prepared within 1 to 2 working days after they are confirmed.</li>
<li>Each shipping method shows an estimated delivery window at checkout. These are estimates, not guarantees; couriers can be delayed by weather, strikes, public holidays and remote locations.</li>
<li>Cash-on-delivery and bank-transfer orders follow the same process. For bank transfer, we start processing once your payment is verified.</li>
</ul>
<h2>Tracking</h2>
<p>When your order ships you receive an email with the courier name and tracking number. You can also see shipment status in your account or by using <strong>Track an order</strong> with your order reference and email.</p>
<h2>Partial shipments</h2>
<p>If some items in your order are ready before others we may ship them separately. You are never charged extra delivery for this.</p>
<h2>Receiving your order</h2>
<ul>
<li>Please check the parcel while the courier is present. If the box is visibly damaged, note it with the courier and contact us the same day.</li>
<li>If nobody is available at the address, the courier may attempt delivery again or call you. Orders returned to us because of an incorrect address or repeated failed delivery may be cancelled; any prepaid amount is refunded after deducting the actual return shipping cost.</li>
</ul>
<h2>Wholesale orders</h2>
<p>Wholesale orders may be shipped by freight or courier, with a separate rate. The rate is shown at checkout, or fixed in your quotation if you ordered through a quote.</p>
<h2>Questions</h2>
<p>Contact us through {contact_link} with your order reference.</p>
HTML, 'Delivery areas, charges, times and tracking for orders in Pakistan.'],

            'returns-and-refunds' => ['Returns & refunds policy', <<<'HTML'
<p><em>Last updated: {updated_date}</em></p>
<h2>Your right to a remedy</h2>
<p>If an item arrives damaged, defective, or is not what you ordered, we will repair, replace or refund it. This policy does not limit any rights you have under applicable consumer law.</p>
<h2>How long you have</h2>
<p>Tell us within <strong>7 days of delivery</strong> by submitting a return request from your account (<em>Orders &rarr; open the order &rarr; Request a return</em>), or contact us through {contact_link} with your order reference and clear photos if the item is damaged.</p>
<h2>What can be returned</h2>
<ul>
<li>Items that are defective, damaged on arrival, or different from the product ordered.</li>
<li>Unopened items in original, resaleable condition and packaging that you simply do not want, for retail orders only. Return shipping for a change of mind is paid by the customer.</li>
</ul>
<h2>What cannot be returned</h2>
<ul>
<li>Headsets, earbuds and other in-ear products that have been opened, for hygiene reasons, unless they are defective.</li>
<li>Items damaged by misuse, liquid, unauthorised repair, or use of an incompatible charger or cable.</li>
<li>Wholesale and quoted orders, except for defects, wrong items or damage in transit, because they are priced and stocked specially for you.</li>
</ul>
<h2>How a return works</h2>
<ol>
<li>Submit a return request. We review it and reply, normally within 2 working days.</li>
<li>If approved, we explain how to send the item back (or arrange collection).</li>
<li>When we receive and inspect it we replace it or refund you.</li>
</ol>
<h2>Refunds</h2>
<ul>
<li>Refunds go back to the original payment method where possible. Cash-on-delivery and bank-transfer refunds are paid by bank transfer to an account you provide.</li>
<li>We process approved refunds within 7 working days of receiving the item; your bank may take additional time.</li>
<li>Original delivery charges are refunded when the return is due to our error or a defect.</li>
<li>Partial refunds can apply if an item is returned with missing parts, damaged packaging or signs of use.</li>
</ul>
<h2>Cancelling an order</h2>
<p>You can ask us to cancel an order that has not shipped yet. Stock is released immediately and any payment already made is refunded.</p>
HTML, 'How to return an item, what is eligible and how refunds are paid.'],

            'privacy-policy' => ['Privacy policy', <<<'HTML'
<p><em>Last updated: {updated_date}</em></p>
<p>This policy explains what personal information {store_name} collects, why we collect it and the choices you have. By using our website you agree to this policy.</p>
<h2>Information we collect</h2>
<ul>
<li><strong>Account and order details:</strong> your name, email address, phone number, delivery and billing addresses, and the items you order.</li>
<li><strong>Wholesale applications:</strong> business name, contact person, business address, registration or tax number if you provide one, and an optional verification document.</li>
<li><strong>Messages:</strong> anything you send us through the contact form or by email.</li>
<li><strong>Technical data:</strong> basic information such as IP address, browser type and pages visited, and cookies needed to keep you signed in and to remember your cart.</li>
</ul>
<p>We do <strong>not</strong> collect or store card numbers. If an online card payment option is offered in future, payment details will be entered with the payment provider, not on our servers.</p>
<h2>How we use it</h2>
<ul>
<li>To process, deliver and support your orders, including payments, shipping and returns.</li>
<li>To create and secure your account, and to review wholesale applications.</li>
<li>To send essential messages such as order confirmations, shipping updates, password resets and quote notifications.</li>
<li>To send marketing emails (offers, new arrivals, cart reminders) <strong>only if you have agreed</strong>. You can withdraw consent at any time from your profile or with the unsubscribe link in any marketing email.</li>
<li>To prevent fraud and abuse, keep the site secure, and meet legal and accounting obligations.</li>
</ul>
<h2>Who we share it with</h2>
<p>We share only what is needed with couriers (to deliver your order), payment or banking partners (to verify and refund payments), and technology providers that host the website and send our emails. They may use the data only to provide their service to us. We do not sell your personal information. We may disclose information when required by law or to protect our rights.</p>
<h2>Cookies</h2>
<p>We use essential cookies to run the site: keeping you signed in, protecting forms against forgery and remembering your cart. We do not use them to sell advertising. You can block cookies in your browser, but the cart and sign-in will not work without them.</p>
<h2>How long we keep it</h2>
<p>We keep order and invoice records for as long as needed for tax, accounting and warranty purposes. Account information is kept until you ask us to close the account. Wholesale verification documents are kept privately and only as long as needed to review and maintain your wholesale status.</p>
<h2>Security</h2>
<p>Passwords are stored in hashed form, connections use HTTPS, wholesale documents are kept in private storage that is not accessible from the web, and staff access is limited by role. No online service is perfectly secure; please use a strong, unique password.</p>
<h2>Your choices</h2>
<ul>
<li>View and update your details from your account profile.</li>
<li>Ask us to access, correct or delete your personal information, or to close your account, through {contact_link}. We may need to keep some records where the law requires it.</li>
<li>Opt out of marketing at any time.</li>
</ul>
<h2>Children</h2>
<p>Our store is intended for adults. We do not knowingly collect information from children.</p>
<h2>Changes</h2>
<p>We may update this policy; the date above shows the latest version. Contact: {contact_link}.</p>
HTML, 'What personal information we collect, how we use it and your choices.'],

            'terms-and-conditions' => ['Terms & conditions', <<<'HTML'
<p><em>Last updated: {updated_date}</em></p>
<p>These terms apply to your use of the {store_name} website and to every order you place. By placing an order you accept them.</p>
<h2>1. Products and prices</h2>
<ul>
<li>All prices are in Pakistani Rupees (PKR). Prices and availability can change without notice; the price confirmed at checkout applies to your order.</li>
<li>We try to keep product information accurate, but images, colours and specifications are indicative. If you notice an error, tell us before ordering.</li>
<li>If a product is listed at a clearly wrong price or is unavailable, we may cancel the order and refund any payment.</li>
</ul>
<h2>2. Orders</h2>
<ul>
<li>Your order is an offer to buy. It is accepted when we confirm it. We may decline or cancel an order, for example for stock, pricing or fraud-prevention reasons, and will refund anything you paid.</li>
<li>You are responsible for giving a correct delivery address and a phone number where you can be reached.</li>
</ul>
<h2>3. Payment</h2>
<p>We accept the payment methods shown at checkout, currently cash on delivery and bank transfer. For bank transfer, your order is processed after we verify the payment and use your order reference as the payment reference.</p>
<h2>4. Delivery</h2>
<p>Delivery terms are in our Shipping policy. Risk in the goods passes to you on delivery.</p>
<h2>5. Returns, refunds and warranty</h2>
<p>These are governed by our Returns &amp; refunds policy and Warranty policy, which form part of these terms.</p>
<h2>6. Wholesale accounts and quotes</h2>
<ul>
<li>Wholesale access requires an application that we approve at our discretion, and we may withdraw access if the account is misused or the information provided was untrue.</li>
<li>Wholesale prices, minimum quantities and quotations are for business buyers only and may not be shared publicly.</li>
<li>A quotation is valid until its stated expiry date. Prices in an accepted quote are honoured on the converted order, subject to stock.</li>
</ul>
<h2>7. Your account</h2>
<p>Keep your password confidential. You are responsible for activity under your account. Tell us promptly if you suspect unauthorised use.</p>
<h2>8. Acceptable use</h2>
<p>Do not misuse the site: no scraping that burdens our servers, attempts to access other people's accounts, or submission of false orders or reviews. Reviews must be honest and relate to products you purchased.</p>
<h2>9. Intellectual property</h2>
<p>Site content, design and logos belong to {store_name} or their respective owners (including brand and product images). You may not copy or reuse them without permission.</p>
<h2>10. Liability</h2>
<p>To the extent permitted by law, we are not liable for indirect or consequential loss, and our total liability for an order is limited to the amount you paid for it. Nothing in these terms limits liability that cannot be limited by law, or your statutory consumer rights.</p>
<h2>11. Governing law</h2>
<p>These terms are governed by the laws of Pakistan, and the courts of Pakistan have jurisdiction.</p>
<h2>12. Changes and contact</h2>
<p>We may update these terms; the version in force when you order applies to that order. Contact us through {contact_link}.</p>
HTML, 'Terms that apply to using our website and placing orders.'],

            'warranty-policy' => ['Warranty policy', <<<'HTML'
<p><em>Last updated: {updated_date}</em></p>
<h2>How warranty works here</h2>
<p>Warranty terms differ by brand and product. We only state a warranty on a product page when we can honour it. <strong>If a product page does not mention a warranty, do not assume one applies: ask us before you buy</strong> through {contact_link}.</p>
<h2>Defects on arrival</h2>
<p>Whatever the warranty, items that arrive defective, damaged or different from what you ordered are covered by our Returns &amp; refunds policy: report them within 7 days of delivery.</p>
<h2>What a warranty covers</h2>
<ul>
<li>Manufacturing defects that appear during normal use within the stated warranty period.</li>
<li>Repair or replacement at our or the brand's discretion; if neither is possible, a refund of the price paid.</li>
</ul>
<h2>What it does not cover</h2>
<ul>
<li>Damage from drops, liquids, heat, misuse or unauthorised repair.</li>
<li>Normal wear (for example worn cables, scratched surfaces, reduced battery capacity over time).</li>
<li>Use with equipment that does not meet the product's specifications, or counterfeit accessories or third-party modifications.</li>
</ul>
<h2>Making a claim</h2>
<ol>
<li>Contact us with your order reference, a description of the fault and clear photos or a short video.</li>
<li>Keep the product and its packaging until we confirm next steps.</li>
<li>We will tell you how to send the item for inspection. If the claim is accepted we repair, replace or refund; if it is declined we explain why.</li>
</ol>
<h2>Authenticity</h2>
<p>We only mark a product as authenticity-verified when we have verified it. Where a product page makes no such statement, it makes no authenticity claim.</p>
HTML, 'What warranty covers, what it does not and how to make a claim.'],
        ];
    }
}
