<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')->columns(4)->schema([
                TextEntry::make('reference')->copyable(),
                TextEntry::make('status')->badge(),
                TextEntry::make('payment_status')->badge(),
                TextEntry::make('payment_method'),
                TextEntry::make('email'), TextEntry::make('phone'),
                TextEntry::make('customer_type')->badge(),
                TextEntry::make('placed_at')->dateTime(),
                TextEntry::make('customer_note')->columnSpanFull()->placeholder('-'),
            ]),
            Section::make('Items')->schema([
                RepeatableEntry::make('items')->columns(6)->schema([
                    TextEntry::make('product_name')->columnSpan(2),
                    TextEntry::make('sku'),
                    TextEntry::make('quantity'),
                    TextEntry::make('unit_price')->money('PKR'),
                    TextEntry::make('line_total')->money('PKR'),
                ])->label(''),
            ]),
            Grid::make(2)->schema([
                Section::make('Totals')->columns(2)->schema([
                    TextEntry::make('subtotal')->money('PKR'),
                    TextEntry::make('discount_total')->money('PKR'),
                    TextEntry::make('shipping_total')->money('PKR'),
                    TextEntry::make('tax_total')->money('PKR'),
                    TextEntry::make('grand_total')->money('PKR')->weight('bold'),
                    TextEntry::make('refunded_total')->money('PKR'),
                ]),
                Section::make('Delivery')->schema([
                    TextEntry::make('shipping_method_name')->label('Method'),
                    TextEntry::make('shipping_address.full_name')->label('Name'),
                    TextEntry::make('shipping_address.line1')->label('Address'),
                    TextEntry::make('shipping_address.city')->label('City'),
                    TextEntry::make('shipping_address.phone')->label('Phone'),
                ]),
            ]),
            Section::make('Shipments')->collapsed()->schema([
                RepeatableEntry::make('shipments')->columns(4)->schema([
                    TextEntry::make('courier'), TextEntry::make('tracking_number'), TextEntry::make('status')->badge(), TextEntry::make('shipped_at')->dateTime(),
                ])->label(''),
            ]),
            Section::make('History')->collapsed()->schema([
                RepeatableEntry::make('histories')->columns(4)->schema([
                    TextEntry::make('from_status')->placeholder('-'), TextEntry::make('to_status')->badge(), TextEntry::make('note')->placeholder('-'), TextEntry::make('created_at')->dateTime(),
                ])->label(''),
            ]),
        ]);
    }
}
