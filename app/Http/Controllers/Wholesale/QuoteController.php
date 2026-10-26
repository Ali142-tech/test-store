<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        return view('wholesale.quotes.index', ['quotes' => Quote::where('user_id', $request->user()->id)->latest()->paginate(10)]);
    }

    public function create()
    {
        return view('wholesale.quotes.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.sku' => 'required|string|max:100',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'items.*.target_price' => 'nullable|numeric|min:0|max:100000000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $variants = ProductVariant::whereIn('sku', collect($data['items'])->pluck('sku'))->whereHas('product', fn ($q) => $q->where('status', 'active')->where('wholesale_enabled', true))->get()->keyBy('sku');
        $unknown = collect($data['items'])->pluck('sku')->reject(fn ($s) => $variants->has($s));
        if ($unknown->isNotEmpty()) {
            return back()->withInput()->withErrors(['items' => 'Unknown or non-wholesale SKU: '.$unknown->implode(', ')]);
        }

        $quote = Quote::create(['reference' => 'QUO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)), 'user_id' => $request->user()->id, 'customer_notes' => $data['notes'] ?? null]);
        foreach ($data['items'] as $row) {
            $quote->items()->create(['product_variant_id' => $variants[$row['sku']]->id, 'quantity' => $row['quantity'], 'target_price' => $row['target_price'] ?? null]);
        }
        $quote->events()->create(['user_id' => $request->user()->id, 'event' => 'requested', 'snapshot' => $quote->items()->get()->toArray(), 'note' => $data['notes'] ?? null]);

        return redirect()->route('quotes.show', $quote)->with('status', 'Quote request submitted.');
    }

    public function show(Request $request, string $reference)
    {
        $quote = $this->own($request, $reference);
        $this->expireIfNeeded($quote);

        return view('wholesale.quotes.show', ['quote' => $quote->load('items.variant.product', 'events')]);
    }

    public function accept(Request $request, string $reference)
    {
        $quote = $this->own($request, $reference);
        $this->expireIfNeeded($quote);
        abort_unless($quote->status === 'quoted', 422, 'This quote can no longer be accepted.');

        $quote->update(['status' => 'accepted']);
        $quote->events()->create(['user_id' => $request->user()->id, 'event' => 'accepted']);

        return redirect()->route('quotes.checkout', $quote)->with('status', 'Quote accepted. Complete your order below.');
    }

    public function reject(Request $request, string $reference)
    {
        $quote = $this->own($request, $reference);
        abort_unless(in_array($quote->status, ['requested', 'quoted'], true), 422);
        $quote->update(['status' => 'rejected']);
        $quote->events()->create(['user_id' => $request->user()->id, 'event' => 'rejected', 'note' => $request->input('note')]);

        return back()->with('status', 'Quote declined.');
    }

    private function own(Request $request, string $reference): Quote
    {
        return Quote::where('user_id', $request->user()->id)->where('reference', $reference)->firstOrFail();
    }

    private function expireIfNeeded(Quote $quote): void
    {
        if (in_array($quote->status, ['quoted', 'accepted'], true) && $quote->expires_at?->isPast()) {
            $quote->update(['status' => 'expired']);
            $quote->events()->create(['event' => 'expired']);
        }
    }
}
