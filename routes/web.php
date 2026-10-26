<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\Wholesale\BulkOrderController;
use App\Http\Controllers\Wholesale\QuoteController;
use App\Http\Controllers\Wholesale\WholesaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// ---- Catalog ----
Route::controller(ShopController::class)->group(function () {
    Route::get('/shop', 'index')->name('shop');
    Route::get('/search', 'index')->name('search');
    Route::get('/category/{category:slug}', 'category')->name('category');
    Route::get('/brand/{brand:slug}', 'brand')->name('brand');
    Route::get('/device/{deviceBrand:slug}/{deviceModel:slug}', 'device')->name('device');
    Route::get('/deals', 'deals')->name('deals');
    Route::get('/new-arrivals', 'newArrivals')->name('new-arrivals');
    Route::get('/best-sellers', 'bestSellers')->name('best-sellers');
    Route::get('/bundles', 'bundles')->name('bundles');
});
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('product');

// ---- Content ----
Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('page');
Route::get('/faqs', [PageController::class, 'faqs'])->name('faqs');
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:5,1')->name('contact.store');
Route::post('/newsletter', [ContactController::class, 'subscribe'])->middleware('throttle:5,1')->name('newsletter.store');
Route::get('/newsletter/unsubscribe/{token}', [ContactController::class, 'unsubscribe'])->name('newsletter.unsubscribe');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

// ---- Auth ----
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/forgot-password', [PasswordController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'resend'])->middleware('throttle:6,1')->name('verification.send');
});

// ---- Cart & checkout (guests allowed; checkout honours the guest_checkout setting) ----
Route::controller(CartController::class)->prefix('cart')->name('cart.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('/add', 'add')->name('add');
    Route::patch('/update', 'update')->name('update');
    Route::delete('/remove/{variant}', 'remove')->name('remove');
    Route::post('/coupon', 'applyCoupon')->middleware('throttle:10,1')->name('coupon');
    Route::delete('/coupon', 'removeCoupon')->name('coupon.remove');
});
Route::controller(CheckoutController::class)->prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/', 'show')->name('show');
    Route::get('/summary', 'summary')->name('summary');
    Route::post('/', 'place')->middleware('throttle:10,1')->name('place');
});

// ---- Orders ----
Route::get('/track-order', [OrderController::class, 'trackForm'])->name('order.track');
Route::post('/track-order', [OrderController::class, 'track'])->middleware('throttle:10,1')->name('order.track.lookup');
Route::get('/order/{order:reference}', [OrderController::class, 'show'])->name('order.show');
Route::get('/order/{order:reference}/invoice', [OrderController::class, 'invoice'])->name('order.invoice');

// ---- Customer account ----
Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->controller(AccountController::class)->group(function () {
    Route::get('/', 'dashboard')->name('dashboard');
    Route::get('/orders', 'orders')->name('orders');
    Route::get('/orders/{reference}', 'order')->name('orders.show');
    Route::get('/orders/{reference}/invoice', 'invoice')->name('orders.invoice');
    Route::post('/orders/{reference}/return', 'storeReturn')->name('orders.return');
    Route::get('/profile', 'profile')->name('profile');
    Route::put('/profile', 'updateProfile')->name('profile.update');
    Route::post('/addresses', 'storeAddress')->name('addresses.store');
    Route::delete('/addresses/{address}', 'destroyAddress')->name('addresses.destroy');
    Route::get('/wishlist', 'wishlist')->name('wishlist');
    Route::post('/wishlist/{product:slug}', 'toggleWishlist')->name('wishlist.toggle');
    Route::post('/reviews/{product:slug}', 'storeReview')->name('reviews.store');
});

// ---- Wholesale ----
Route::get('/wholesale/register', [WholesaleController::class, 'showRegister'])->name('wholesale.register');
Route::post('/wholesale/register', [WholesaleController::class, 'register'])->middleware('throttle:10,1')->name('wholesale.register.store');
Route::middleware('auth')->get('/wholesale', [WholesaleController::class, 'dashboard'])->name('wholesale.dashboard');

Route::middleware(['auth', 'wholesale'])->prefix('wholesale')->group(function () {
    Route::get('/bulk-order', [BulkOrderController::class, 'show'])->name('wholesale.bulk');
    Route::post('/bulk-order', [BulkOrderController::class, 'add'])->name('wholesale.bulk.add');
    Route::get('/bulk-order/template', [BulkOrderController::class, 'template'])->name('wholesale.bulk.template');
    Route::get('/bulk-order/price', [BulkOrderController::class, 'price'])->name('wholesale.bulk.price');

    Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes.index');
    Route::get('/quotes/new', [QuoteController::class, 'create'])->name('quotes.create');
    Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
    Route::get('/quotes/{reference}', [QuoteController::class, 'show'])->name('quotes.show');
    Route::post('/quotes/{reference}/accept', [QuoteController::class, 'accept'])->name('quotes.accept');
    Route::post('/quotes/{reference}/reject', [QuoteController::class, 'reject'])->name('quotes.reject');
    Route::get('/quotes/{quote}/checkout', [CheckoutController::class, 'show'])->name('quotes.checkout');
    Route::get('/quotes/{quote}/summary', [CheckoutController::class, 'summary'])->name('quotes.summary');
    Route::post('/quotes/{quote}/checkout', [CheckoutController::class, 'place'])->middleware('throttle:10,1')->name('quotes.place');
});

// ---- Staff-only: private wholesale documents (never on the public disk) ----
Route::middleware(['auth', 'can:manage wholesale'])->get('/admin/wholesale-applications/{application}/document', function (\App\Models\WholesaleApplication $application) {
    abort_unless($application->document_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($application->document_path), 404);
    \App\Services\Audit\AuditLogger::log('Wholesale.document_viewed', $application);

    return \Illuminate\Support\Facades\Storage::disk('local')->download($application->document_path, $application->document_original_name ?: 'document');
})->name('admin.wholesale-document');
