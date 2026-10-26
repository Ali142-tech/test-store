<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    Filament::setCurrentPanel('admin');
});

it('renders the dashboard, settings and every resource list page for a Super Admin', function () {
    seedShipping();
    makeVariant();
    $admin = staff('Super Admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
    $this->actingAs($admin)->get('/admin/store-settings')->assertOk();

    foreach (Filament::getResources() as $resource) {
        $this->actingAs($admin)->get($resource::getUrl('index'))->assertOk();
    }
});

it('renders every create and edit page that exists for a Super Admin', function () {
    seedShipping();
    $v = makeVariant();
    $admin = staff('Super Admin');
    $records = [
        \App\Filament\Resources\Products\ProductResource::class => $v->product,
        \App\Filament\Resources\ShippingZones\ShippingZoneResource::class => \App\Models\ShippingZone::first(),
        \App\Filament\Resources\Users\UserResource::class => $admin,
        \App\Filament\Resources\Roles\RoleResource::class => \Spatie\Permission\Models\Role::first(),
    ];

    foreach (Filament::getResources() as $resource) {
        $pages = $resource::getPages();
        if (isset($pages['create'])) {
            $this->actingAs($admin)->get($resource::getUrl('create'))->assertOk();
        }
        if (isset($pages['edit']) && isset($records[$resource])) {
            $this->actingAs($admin)->get($resource::getUrl('edit', ['record' => $records[$resource]]))->assertOk();
        }
    }
});

it('keeps customers and guests out of the admin panel', function () {
    $this->get('/admin')->assertRedirect();
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('enforces role permissions on resource pages (server-side)', function (string $role, string $resource, int $status) {
    $this->actingAs(staff($role))->get($resource::getUrl('index'))->assertStatus($status);
})->with([
    ['Customer Support', \App\Filament\Resources\Products\ProductResource::class, 403],
    ['Customer Support', \App\Filament\Resources\Roles\RoleResource::class, 403],
    ['Customer Support', \App\Filament\Resources\AuditLogs\AuditLogResource::class, 403],
    ['Customer Support', \App\Filament\Resources\ContactMessages\ContactMessageResource::class, 200],
    ['Inventory Manager', \App\Filament\Resources\Products\ProductResource::class, 200],
    ['Inventory Manager', \App\Filament\Resources\Orders\OrderResource::class, 403],
    ['Inventory Manager', \App\Filament\Resources\Coupons\CouponResource::class, 403],
    ['Order Manager', \App\Filament\Resources\Orders\OrderResource::class, 200],
    ['Order Manager', \App\Filament\Resources\Products\ProductResource::class, 403],
    ['Order Manager', \App\Filament\Resources\WholesaleApplications\WholesaleApplicationResource::class, 403],
    ['Wholesale Manager', \App\Filament\Resources\WholesaleApplications\WholesaleApplicationResource::class, 200],
    ['Wholesale Manager', \App\Filament\Resources\Quotes\QuoteResource::class, 200],
    ['Wholesale Manager', \App\Filament\Resources\StockMovements\StockMovementResource::class, 403],
    ['Store Manager', \App\Filament\Resources\Products\ProductResource::class, 200],
    ['Store Manager', \App\Filament\Resources\Roles\RoleResource::class, 403],
    ['Store Manager', \App\Filament\Resources\AuditLogs\AuditLogResource::class, 200],
]);

it('limits the settings page to staff with manage settings', function () {
    $this->actingAs(staff('Store Manager'))->get('/admin/store-settings')->assertForbidden();
    $this->actingAs(staff('Super Admin'))->get('/admin/store-settings')->assertOk();
});

it('serves wholesale documents only to authorised staff', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    \Illuminate\Support\Facades\Storage::disk('local')->put('wholesale-documents/x.pdf', 'secret');
    $u = wholesaleUser();
    $u->wholesaleApplication->update(['document_path' => 'wholesale-documents/x.pdf', 'document_original_name' => 'licence.pdf']);
    $url = route('admin.wholesale-document', $u->wholesaleApplication);

    $this->get($url)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    $this->actingAs($u)->get($url)->assertForbidden(); // even the owner uses no public path
    $this->actingAs(staff('Order Manager'))->get($url)->assertForbidden();
    $this->actingAs(staff('Wholesale Manager'))->get($url)->assertOk()->assertDownload('licence.pdf');
    expect(\App\Models\AuditLog::where('action', 'Wholesale.document_viewed')->count())->toBe(1);
});

it('does not expose the private documents directory over the public web root', function () {
    expect(config('filesystems.disks.local.root'))->toBe(storage_path('app/private'))
        ->and(config('filesystems.disks.public.root'))->toBe(storage_path('app/public'));
});
