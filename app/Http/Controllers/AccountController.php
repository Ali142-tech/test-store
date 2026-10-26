<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return view('account.dashboard', [
            'recent' => $user->orders()->latest('placed_at')->limit(5)->get(),
            'orderCount' => $user->orders()->count(),
            'wishlistCount' => $user->wishlistItems()->count(),
        ]);
    }

    public function orders(Request $request)
    {
        return view('account.orders', ['orders' => $request->user()->orders()->latest('placed_at')->paginate(10)]);
    }

    /** Scoped to the signed-in user's own orders: another customer's reference yields 404. */
    public function order(Request $request, string $reference)
    {
        $order = $request->user()->orders()->where('reference', $reference)->with('items', 'shipments', 'payments')->firstOrFail();

        return view('orders.show', ['order' => $order, 'bank' => [], 'account' => true]);
    }

    public function invoice(Request $request, string $reference)
    {
        $order = $request->user()->orders()->where('reference', $reference)->with('items')->firstOrFail();

        return view('orders.invoice', compact('order'));
    }

    public function profile(Request $request)
    {
        return view('account.profile', ['addresses' => $request->user()->addresses]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'phone' => 'nullable|string|max:30',
            'marketing_consent' => 'nullable|boolean',
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($request->user()->id)],
        ]);
        $user = $request->user();
        $user->fill(['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'marketing_consent' => $request->boolean('marketing_consent')]);
        if ($data['email'] !== $user->email) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
        } else {
            $user->save();
        }

        return back()->with('status', 'Profile updated.');
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'label' => 'nullable|string|max:50', 'full_name' => 'required|string|max:120', 'phone' => 'required|string|max:30',
            'line1' => 'required|string|max:190', 'line2' => 'nullable|string|max:190', 'city' => 'required|string|max:100',
            'state' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20',
        ]);
        $first = ! $request->user()->addresses()->exists();
        $request->user()->addresses()->create($data + ['country' => 'PK', 'is_default_shipping' => $first, 'is_default_billing' => $first]);

        return back()->with('status', 'Address saved.');
    }

    public function destroyAddress(Request $request, int $address)
    {
        $request->user()->addresses()->whereKey($address)->firstOrFail()->delete();

        return back()->with('status', 'Address removed.');
    }

    public function wishlist(Request $request)
    {
        $products = Product::active()->whereIn('id', $request->user()->wishlistItems()->pluck('product_id'))
            ->with(['defaultVariant', 'images', 'brand'])->withAvg('reviews', 'rating')->withCount('reviews')->get();

        return view('account.wishlist', compact('products'));
    }

    public function toggleWishlist(Request $request, Product $product)
    {
        $existing = $request->user()->wishlistItems()->where('product_id', $product->id);
        $existing->exists() ? $existing->delete() : $request->user()->wishlistItems()->create(['product_id' => $product->id]);

        return back();
    }

    /** Reviews are only accepted from customers with a delivered order containing the product. */
    public function storeReview(Request $request, Product $product)
    {
        $data = $request->validate(['rating' => 'required|integer|min:1|max:5', 'title' => 'nullable|string|max:120', 'body' => 'nullable|string|max:2000']);
        $item = OrderItem::where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $request->user()->id)->where('status', 'delivered'))->first();

        abort_unless($item, 403, 'You can review products after they have been delivered to you.');

        $review = Review::updateOrCreate(
            ['product_id' => $product->id, 'user_id' => $request->user()->id],
            $data + ['order_item_id' => $item->id],
        );
        $review->forceFill(['is_verified_purchase' => true, 'status' => 'pending'])->save(); // moderated before display

        return back()->with('status', 'Thanks! Your review will appear once approved.');
    }

    public function storeReturn(Request $request, string $reference)
    {
        $order = $request->user()->orders()->where('reference', $reference)->where('status', 'delivered')->firstOrFail();
        $data = $request->validate(['order_item_id' => 'required|integer', 'quantity' => 'required|integer|min:1', 'reason' => 'required|string|max:1000']);
        $item = $order->items()->findOrFail($data['order_item_id']);

        $alreadyRequested = ReturnRequest::where('order_item_id', $item->id)->whereNotIn('status', ['rejected'])->sum('quantity');
        if ($data['quantity'] > $item->quantity - $alreadyRequested) {
            return back()->withErrors(['quantity' => 'That quantity exceeds what you can return for this item.']);
        }

        ReturnRequest::create($data + ['order_id' => $order->id, 'user_id' => $request->user()->id]);

        return back()->with('status', 'Return request submitted. We will contact you shortly.');
    }
}
