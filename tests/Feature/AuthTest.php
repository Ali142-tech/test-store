<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('registers a retail customer without any email verification and logs them in', function () {
    Notification::fake();
    $this->post('/register', ['name' => 'Sara', 'email' => 'sara@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertRedirect('/account');

    $u = User::where('email', 'sara@example.com')->first();
    expect($u->customer_type)->toBe('retail')->and($u->password)->not->toBe('password123')->and($u->marketing_consent)->toBeFalse();
    Notification::assertNothingSent();
    $this->assertAuthenticatedAs($u);
});

it('validates registration input', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $this->post('/register', ['name' => '', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'nope'])
        ->assertSessionHasErrors(['name', 'email', 'password']);
});

it('cannot register as wholesale or staff by posting extra fields', function () {
    $this->post('/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'password_confirmation' => 'password123',
        'customer_type' => 'wholesale', 'is_active' => 0, 'role' => 'Super Admin']);
    $u = User::where('email', 'x@example.com')->first();
    expect($u->customer_type)->toBe('retail')->and($u->isStaff())->toBeFalse();
});

it('logs in with valid credentials, rejects bad ones, and blocks deactivated accounts', function () {
    $u = User::factory()->create(['password' => 'password123']);
    $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    $this->assertGuest();

    $this->post('/login', ['email' => $u->email, 'password' => 'password123'])->assertRedirect(route('account.dashboard'));
    $this->assertAuthenticatedAs($u);

    auth()->logout();
    $u->forceFill(['is_active' => false])->save();
    $this->post('/login', ['email' => $u->email, 'password' => 'password123'])->assertSessionHasErrors('email');
});

it('rate limits repeated failed logins', function () {
    $u = User::factory()->create(['password' => 'password123']);
    foreach (range(1, 5) as $i) {
        $this->post('/login', ['email' => $u->email, 'password' => 'bad']);
    }
    $this->post('/login', ['email' => $u->email, 'password' => 'password123'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('sends a reset link without revealing whether the account exists, and resets the password', function () {
    Notification::fake();
    $u = User::factory()->create();
    $a = $this->post('/forgot-password', ['email' => $u->email]);
    $b = $this->post('/forgot-password', ['email' => 'nobody@example.com']);
    expect(session('status'))->toBe('If that email is registered, a reset link is on its way.');
    Notification::assertSentTo($u, ResetPassword::class);
    Notification::assertCount(1);

    $token = \Illuminate\Support\Facades\Password::createToken($u);
    $this->post('/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1'])->assertRedirect('/login');
    expect(\Illuminate\Support\Facades\Hash::check('newpassword1', $u->fresh()->password))->toBeTrue();
});

it('lets a new, unverified customer use the account area straight away', function () {
    $u = User::factory()->unverified()->create();
    $this->actingAs($u)->get('/account')->assertOk();
    $this->get('/email/verify')->assertNotFound();
});

it('redirects guests away from account pages', function () {
    foreach (['/account', '/account/orders', '/account/wishlist', '/account/profile'] as $uri) {
        $this->get($uri)->assertRedirect('/login');
    }
});

it('manages profile, addresses and wishlist for the signed-in user only', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $p = makeVariant()->product;

    $this->actingAs($a)->post('/account/addresses', ['full_name' => 'A', 'phone' => '1', 'line1' => 'x', 'city' => 'Lahore'])->assertSessionHasNoErrors();
    $addr = $a->addresses()->first();
    $this->actingAs($b)->delete("/account/addresses/{$addr->id}")->assertNotFound();
    expect($a->addresses()->count())->toBe(1);

    $this->actingAs($a)->post("/account/wishlist/{$p->slug}");
    expect($a->wishlistItems()->count())->toBe(1)->and($b->wishlistItems()->count())->toBe(0);
    $this->actingAs($a)->post("/account/wishlist/{$p->slug}");
    expect($a->wishlistItems()->count())->toBe(0);

    $this->actingAs($a)->put('/account/profile', ['name' => 'New Name', 'email' => $a->email, 'phone' => '0300'])->assertSessionHasNoErrors();
    expect($a->fresh()->name)->toBe('New Name');
});

it('lets only delivered-order customers review a product, and holds reviews for moderation', function () {
    seedShipping();
    $v = makeVariant(['wholesale_enabled' => false]);
    $u = User::factory()->create();
    $this->actingAs($u)->post("/account/reviews/{$v->product->slug}", ['rating' => 5, 'body' => 'great'])->assertForbidden();

    $this->actingAs($u)->post('/cart/add', ['variant_id' => $v->id, 'quantity' => 1]);
    $this->actingAs($u)->post('/checkout', checkoutPayload());
    $order = \App\Models\Order::first();
    foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $s) {
        app(\App\Services\Orders\OrderService::class)->transition($order->fresh(), $s);
    }

    $this->actingAs($u)->post("/account/reviews/{$v->product->slug}", ['rating' => 5, 'body' => 'great'])->assertSessionHasNoErrors();
    $review = \App\Models\Review::first();
    expect($review->status)->toBe('pending')->and($review->is_verified_purchase)->toEqual(true);
    expect($this->get(route('product', $v->product))->viewData('reviews'))->toBeEmpty(); // not shown until approved
    $review->forceFill(['status' => 'approved'])->save();
    $this->get(route('product', $v->product))->assertSee('great')->assertSee('Verified purchase');
});
