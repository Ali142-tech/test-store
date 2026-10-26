<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Pricing\PriceResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use InvalidArgumentException;

class BulkOrderController extends Controller
{
    private const MAX_ROWS = 500;

    public function __construct(private CartService $carts, private PriceResolver $prices) {}

    public function show()
    {
        return view('wholesale.bulk');
    }

    /** Quick order: textarea lines "SKU, quantity". Valid lines go to the cart; invalid ones are reported. */
    public function add(Request $request)
    {
        $request->validate(['lines' => 'nullable|string|max:20000', 'csv' => ['nullable', File::types(['csv', 'txt'])->max(1024)]]);

        $rows = [];
        if ($request->hasFile('csv')) {
            $handle = fopen($request->file('csv')->getRealPath(), 'r');
            while (($r = fgetcsv($handle)) !== false && count($rows) <= self::MAX_ROWS) {
                $rows[] = array_map('trim', $r);
            }
            fclose($handle);
        }
        foreach (preg_split('/\r\n|\r|\n/', (string) $request->input('lines')) as $line) {
            if (trim($line) !== '') {
                $rows[] = array_map('trim', str_getcsv($line));
            }
        }

        if (count($rows) > self::MAX_ROWS) {
            return back()->withErrors(['lines' => 'Too many lines (max '.self::MAX_ROWS.').']);
        }
        if ($rows === []) {
            return back()->withErrors(['lines' => 'Enter at least one SKU and quantity, or upload a CSV.']);
        }

        $cart = $this->carts->current($request->user());
        $added = 0;
        $problems = [];

        foreach ($rows as $n => $row) {
            [$sku, $qty] = [$row[0] ?? '', $row[1] ?? ''];
            if (strcasecmp($sku, 'sku') === 0 && ! ctype_digit($qty)) {
                continue; // header row
            }
            if ($sku === '' || ! ctype_digit($qty) || (int) $qty < 1) {
                $problems[] = 'Line '.($n + 1).": invalid SKU or quantity ({$sku}, {$qty}).";

                continue;
            }
            $variant = ProductVariant::where('sku', $sku)->first();
            if (! $variant) {
                $problems[] = 'Line '.($n + 1).": unknown SKU {$sku}.";

                continue;
            }
            try {
                $this->carts->add($cart, $variant->id, (int) $qty, $request->user());
                $added++;
            } catch (InvalidArgumentException $e) {
                $problems[] = 'Line '.($n + 1).": {$sku} - {$e->getMessage()}";
            }
        }

        $redirect = $added > 0 ? redirect()->route('cart.show')->with('status', "{$added} line(s) added to your cart.") : back();

        return $problems ? $redirect->withErrors(['bulk' => $problems]) : $redirect;
    }

    public function template()
    {
        return response("sku,quantity\nEXAMPLE-SKU-1,10\nEXAMPLE-SKU-2,50\n", 200, [
            'Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="bulk-order-template.csv"',
        ]);
    }

    /** Price calculator: server-side price for a SKU and quantity. */
    public function price(Request $request)
    {
        $data = $request->validate(['sku' => 'required|string|max:100', 'quantity' => 'required|integer|min:1|max:100000']);
        $variant = ProductVariant::with('product', 'tiers')->where('sku', $data['sku'])->first();
        if (! $variant || $variant->product->status !== 'active') {
            return response()->json(['error' => 'Unknown SKU'], 404);
        }
        $p = $this->prices->resolve($variant, $data['quantity'], $request->user());

        return response()->json([
            'name' => $variant->displayName(), 'unit_price' => $p['price'], 'source' => $p['source'],
            'line_total' => $this->prices->lineTotal($p['price'], $data['quantity']),
            'moq' => $this->prices->minimumQuantity($variant, $request->user()), 'available' => $variant->available(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
