<?php

use App\Models\StoreSetting;
use App\Models\User;

it('creates a verified Super Admin who can open the panel, using a hidden password prompt', function () {
    $this->artisan('store:create-admin', ['email' => 'Owner@Example.com', '--name' => 'The Owner', '--store-name' => 'Acme Mobile', '--contact-email' => 'help@acme.test'])
        ->expectsQuestion('Password (min 12 characters, hidden)', 'Str0ng-Passw0rd-Test')
        ->expectsQuestion('Repeat password', 'Str0ng-Passw0rd-Test')
        ->assertSuccessful();

    $u = User::where('email', 'owner@example.com')->first();
    expect($u->hasRole('Super Admin'))->toBeTrue()->and($u->hasVerifiedEmail())->toBeTrue()->and($u->is_active)->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('Str0ng-Passw0rd-Test', $u->password))->toBeTrue();
    expect(StoreSetting::where('key', 'store_name')->value('value'))->toBe('Acme Mobile')
        ->and(StoreSetting::where('key', 'contact_email')->value('value'))->toBe('help@acme.test');

    $this->actingAs($u)->get('/admin')->assertOk();
});

it('rejects weak passwords, mismatches and bad emails without creating anything', function () {
    $this->artisan('store:create-admin', ['email' => 'a@example.com'])
        ->expectsQuestion('Password (min 12 characters, hidden)', 'short')->expectsQuestion('Repeat password', 'short')->assertFailed();
    $this->artisan('store:create-admin', ['email' => 'a@example.com'])
        ->expectsQuestion('Password (min 12 characters, hidden)', 'Str0ng-Passw0rd-Test')->expectsQuestion('Repeat password', 'Different-Passw0rd-1')->assertFailed();
    $this->artisan('store:create-admin', ['email' => 'not-an-email'])->assertFailed();

    expect(User::count())->toBe(0);
});

it('asks before upgrading an existing account and never silently changes it', function () {
    $existing = User::factory()->create(['email' => 'exists@example.com']);
    $this->artisan('store:create-admin', ['email' => 'exists@example.com'])
        ->expectsConfirmation('A user with exists@example.com already exists. Make them a Super Admin and set a new password?', 'no')->assertFailed();
    expect($existing->fresh()->hasRole('Super Admin'))->toBeFalse();
});
