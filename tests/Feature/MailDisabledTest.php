<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    config(['store.mail_enabled' => false]);
    seedShipping();
});

it('sends nothing when an order is placed and still places it', function () {
    Mail::fake();
    $v = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 5]);
    $this->post('/cart/add', ['variant_id' => $v->id, 'quantity' => 1]);
    $this->post('/checkout', checkoutPayload())->assertRedirect();

    expect(Order::count())->toBe(1);
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('registers customers without sending anything', function () {
    Mail::fake();
    $this->post('/register', ['name' => 'No Mail', 'email' => 'nomail@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertRedirect('/account');
    Mail::assertNothingSent();
    expect(User::where('email', 'nomail@example.com')->exists())->toBeTrue();
});

it('hides password reset while there is no email service', function () {
    $this->get('/login')->assertOk()->assertDontSee('Forgot password');
    $this->get('/forgot-password')->assertNotFound();
    $this->post('/forgot-password', ['email' => 'a@b.com'])->assertNotFound();
    $this->get('/reset-password/abc')->assertNotFound();
});

it('does not promise emails on customer pages', function () {
    $this->get('/track-order')->assertOk()->assertDontSee('confirmation email');
});

it('keeps the mail commands harmless', function () {
    Mail::fake();
    $this->artisan('stock:low-alert')->assertSuccessful();
    Mail::assertNothingSent();
});
