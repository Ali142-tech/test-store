<?php

use App\Models\StockMovement;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockService;

it('records a movement for every stock change', function () {
    $v = makeVariant([], ['stock_quantity' => 10]);
    $svc = new StockService;

    $svc->remove($v->id, 3);
    $svc->add($v->id, 5, 'return');

    expect($v->fresh()->stock_quantity)->toBe(12)
        ->and(StockMovement::where('product_variant_id', $v->id)->pluck('quantity_change')->all())->toBe([-3, 5])
        ->and(StockMovement::where('product_variant_id', $v->id)->latest('id')->first()->quantity_after)->toBe(12);
});

it('refuses to oversell and leaves stock untouched', function () {
    $v = makeVariant([], ['stock_quantity' => 2]);

    expect(fn () => (new StockService)->remove($v->id, 3))->toThrow(InsufficientStockException::class);
    expect($v->fresh()->stock_quantity)->toBe(2)
        ->and(StockMovement::count())->toBe(0);
});

it('allows selling exactly the remaining stock but not one more', function () {
    $v = makeVariant([], ['stock_quantity' => 1]);
    $svc = new StockService;

    $svc->remove($v->id, 1);
    expect(fn () => $svc->remove($v->id, 1))->toThrow(InsufficientStockException::class);
    expect($v->fresh()->stock_quantity)->toBe(0);
});
