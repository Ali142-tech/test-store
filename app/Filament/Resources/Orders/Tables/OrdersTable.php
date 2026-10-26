<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable()->copyable()->weight('bold'),
                TextColumn::make('placed_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('customer_type')->badge()->color(fn ($state) => $state === 'wholesale' ? 'warning' : 'gray'),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'delivered' => 'success', 'cancelled', 'refunded' => 'danger', 'pending' => 'warning', default => 'info',
                }),
                TextColumn::make('payment_status')->badge()->color(fn ($state) => match ($state) {
                    'paid' => 'success', 'failed', 'refund_due' => 'danger', 'refunded', 'partially_refunded' => 'gray', default => 'warning',
                }),
                TextColumn::make('payment_method')->toggleable(),
                TextColumn::make('grand_total')->money('PKR')->sortable(),
            ])
            ->defaultSort('placed_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(array_combine(\App\Models\Order::STATUSES, array_map('ucfirst', \App\Models\Order::STATUSES))),
                SelectFilter::make('payment_status')->options(['unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refund_due' => 'Refund due', 'partially_refunded' => 'Partially refunded', 'refunded' => 'Refunded']),
                SelectFilter::make('customer_type')->options(['retail' => 'Retail', 'wholesale' => 'Wholesale']),
            ])
            ->recordActions([ViewAction::make()]);
    }
}
