<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** Point mail at a closed port so every send throws, exactly like a mail-provider outage. */
beforeEach(function () {
    config(['mail.default' => 'smtp', 'mail.mailers.smtp' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 2]]);
    app('mail.manager')->purge('smtp');
    seedShipping();
    $this->seed(\Database\Seeders\RolesSeeder::class);
});

it('still registers a customer when the mail server is down', function () {
    $this->post('/register', ['name' => 'Mail Down', 'email' => 'down@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'])
        ->assertRedirect(route('verification.notice'));
    expect(User::where('email', 'down@example.com')->exists())->toBeTrue();
    $this->assertAuthenticated();
});

it('tells the customer gently when the verification email cannot be resent', function () {
    $u = User::factory()->unverified()->create();
    $this->actingAs($u)->post('/email/verification-notification')->assertSessionHas('status', fn ($s) => str_contains($s, 'could not send'));
});

it('still places the order, and keeps the order, when the confirmation email fails', function () {
    $v = makeVariant(['wholesale_enabled' => false], ['stock_quantity' => 5]);
    $this->post('/cart/add', ['variant_id' => $v->id, 'quantity' => 1]);
    $this->post('/checkout', checkoutPayload())->assertRedirect();

    expect(Order::count())->toBe(1)->and($v->fresh()->stock_quantity)->toBe(4);
});

it('does not reveal mail problems on password reset', function () {
    $u = User::factory()->create();
    $this->post('/forgot-password', ['email' => $u->email])->assertSessionHas('status', 'If that email is registered, a reset link is on its way.');
});

it('still completes admin approvals, quotes and status changes when mail fails', function () {
    $admin = staff('Super Admin');
    $wu = wholesaleUser('pending');
    app(\App\Services\Wholesale\WholesaleApprovalService::class)->approve($wu->wholesaleApplication, $admin);
    expect($wu->fresh()->isApprovedWholesale())->toBeTrue();

    $v = makeVariant(['wholesale_enabled' => false]);
    $this->post('/cart/add', ['variant_id' => $v->id, 'quantity' => 1]);
    $this->post('/checkout', checkoutPayload());
    app(\App\Services\Orders\OrderService::class)->transition(Order::first(), 'confirmed');
    expect(Order::first()->status)->toBe('confirmed');
});

it('logs the mail failure instead of swallowing it silently', function () {
    \Illuminate\Support\Facades\Log::spy();
    $this->post('/register', ['name' => 'Log Me', 'email' => 'log@example.com', 'password' => 'password123', 'password_confirmation' => 'password123']);
    \Illuminate\Support\Facades\Log::shouldHaveReceived('error')->atLeast()->once();
});
