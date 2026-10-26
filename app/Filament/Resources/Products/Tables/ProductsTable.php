<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->limit(40),
                TextColumn::make('variants.sku')->label('SKUs')->badge()->limitList(2)->searchable(),
                TextColumn::make('brand.name')->sortable(),
                TextColumn::make('category.name')->sortable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'active' => 'success', 'archived' => 'gray', default => 'warning' }),
                TextColumn::make('variants_sum_stock_quantity')->sum('variants', 'stock_quantity')->label('Stock')->sortable(),
                IconColumn::make('wholesale_enabled')->boolean()->label('Wholesale'),
                IconColumn::make('is_featured')->boolean()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived']),
                SelectFilter::make('category')->relationship('category', 'name'),
                SelectFilter::make('brand')->relationship('brand', 'name'),
                TernaryFilter::make('wholesale_enabled'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
