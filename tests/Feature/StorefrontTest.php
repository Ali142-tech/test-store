<?php

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RolesSeeder;

beforeEach(function () {
    $this->seed([RolesSeeder::class, ContentSeeder::class, PolicyPagesSeeder::class, CatalogSeeder::class]);
});

it('renders the main storefront pages', function (string $uri) {
    $this->get($uri)->assertOk();
})->with(['/', '/shop', '/deals', '/new-arrivals', '/best-sellers', '/faqs', '/pages/about', '/search?q=case']);

it('renders category, brand, device and product pages', function () {
    $this->get(route('category', Category::first()))->assertOk();
    $this->get(route('brand', Brand::first()))->assertOk();
    $p = Product::active()->has('deviceModels')->first();
    $m = $p->deviceModels->first();
    $this->get(route('device', [$m->deviceBrand, $m]))->assertOk()->assertSee($p->name);
    $this->get(route('product', $p))->assertOk()->assertSee($p->name);
});

it('returns 404 for unknown and draft products, and 410 Gone for archived ones', function () {
    $this->get('/product/nope')->assertNotFound();
    $p = Product::active()->first();
    $p->update(['status' => 'draft']);
    $this->get(route('product', $p))->assertNotFound();
    $p->update(['status' => 'archived']);
    $this->get(route('product', $p))->assertStatus(410);
});

it('hides draft products from listings and search', function () {
    $p = Product::create(['name' => 'Zzyzx Secret Case', 'slug' => 'zzyzx', 'status' => 'draft']);
    $this->get('/shop')->assertDontSee('Zzyzx');
    $this->get('/search?q=Zzyzx')->assertDontSee('Zzyzx Secret');
});

it('searches by name, SKU and compatible phone model', function () {
    $p = Product::active()->has('deviceModels')->with('variants', 'deviceModels')->first();
    $this->get('/search?q='.urlencode($p->name))->assertSee($p->name);
    $this->get('/search?q='.urlencode($p->variants->first()->sku))->assertSee($p->name);
    $this->get('/search?q='.urlencode($p->deviceModels->first()->name))->assertSee($p->name);
});

it('filters in-stock and sorts by price without errors', function () {
    foreach (['price_asc', 'price_desc', 'newest', 'popular', 'rating', 'relevance'] as $sort) {
        $this->get("/shop?sort={$sort}&in_stock=1&on_sale=1&min_price=0&max_price=999999")->assertOk();
    }
});

it('rejects invalid filter input', function () {
    $this->get('/shop?sort=drop_table')->assertSessionHasErrors('sort');
});

it('shows an empty state when nothing matches', function () {
    $this->get('/search?q=qqqqnomatchqqqq')->assertOk()->assertSee('No products match');
});

it('does not show wholesale tier prices to guests on the product page', function () {
    $p = Product::active()->where('wholesale_enabled', true)->first();
    expect($p->variants->first()->tiers)->not->toBeEmpty(); // tiers exist in the DB...

    $guest = $this->get(route('product', $p));
    expect($guest->viewData('variants')->pluck('tiers')->flatten(1))->toBeEmpty(); // ...but guests never receive them

    $retail = $this->actingAs(User::factory()->create())->get(route('product', $p));
    expect($retail->viewData('variants')->pluck('tiers')->flatten(1))->toBeEmpty();
});

it('keeps cost price out of the page source', function () {
    $p = Product::active()->first();
    expect($this->get(route('product', $p))->getContent())->not->toContain('cost_price');
});

it('shows no fake rating when a product has no reviews', function () {
    $p = Product::active()->first();
    $this->get(route('product', $p))->assertDontSee('★ 0')->assertSee('No reviews yet.');
});

it('only lets active staff into the admin panel', function () {
    $this->get('/admin')->assertRedirect();

    $customer = User::factory()->create();
    $this->actingAs($customer)->get('/admin')->assertForbidden();

    $staff = User::factory()->create();
    $staff->assignRole('Order Manager');
    $this->actingAs($staff)->get('/admin')->assertOk();

    $staff->forceFill(['is_active' => false])->save();
    $this->actingAs($staff->fresh())->get('/admin')->assertForbidden();
});

it('gives phone users a sign-in icon, a drawer with account actions, and a search toggle', function () {
    $html = $this->get('/')->getContent();
    expect($html)->toContain('aria-label="Sign in"')              // visible icon on every screen size (not hidden on mobile)
        ->and($html)->toContain('aria-label="Search"')            // phone search toggle
        ->and($html)->toContain('Create account')                 // in the mobile drawer
        ->and($html)->toContain('Wholesale accounts')
        ->and($html)->not->toMatch('/<a href="[^"]*login"[^>]*class="hidden sm:inline-flex/'); // the old desktop-only link is gone
});

it('shows account links in the drawer for signed-in customers', function () {
    $u = User::factory()->create();
    $this->actingAs($u)->get('/')->assertSee('My orders')->assertSee('Sign out')->assertSee('Wishlist');
});

it('links to the admin login from the footer', function () {
    $this->get('/')->assertSee(url('/admin/login'), false)->assertSee('Admin login');
});

it('ships a light/dark theme: pre-paint script, toggle button, dark tokens in the built CSS', function () {
    $html = $this->get('/')->getContent();
    expect($html)->toContain("localStorage.getItem('theme')")        // saved choice, else device setting, applied before first paint
        ->and($html)->toContain('data-theme-toggle')
        ->and($html)->toContain('toggleTheme()')
        ->and($html)->toContain('bg-surface');                        // views use theme tokens, not fixed white

    $css = collect(glob(public_path('build/assets/*.css')))->map(fn ($f) => file_get_contents($f))->implode('');
    expect($css)->toContain(':root.dark')->toContain('--color-surface');
});
