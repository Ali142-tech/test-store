<?php

namespace App\Filament\Resources\Coupons;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Coupons\Pages\CreateCoupon;
use App\Filament\Resources\Coupons\Pages\EditCoupon;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Coupon;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage coupons';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $model = Coupon::class;

    protected static ?string $recordTitleAttribute = 'code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->maxLength(50)->dehydrateStateUsing(fn ($state) => strtoupper(trim($state))),
            TextInput::make('description'),
            Select::make('type')->options(['percent' => 'Percentage off', 'fixed' => 'Fixed amount off', 'free_shipping' => 'Free shipping'])->required()->live(),
            TextInput::make('value')->numeric()->minValue(0)->required()->hidden(fn ($get) => $get('type') === 'free_shipping')->default(0)
                ->maxValue(fn ($get) => $get('type') === 'percent' ? 100 : null)->helperText('Percent (0-100) or currency amount.'),
            TextInput::make('min_subtotal')->numeric()->minValue(0)->label('Minimum spend'),
            TextInput::make('max_discount')->numeric()->minValue(0)->label('Maximum discount'),
            TextInput::make('usage_limit')->numeric()->minValue(1)->label('Total uses allowed'),
            TextInput::make('usage_limit_per_user')->numeric()->minValue(1)->label('Uses per customer'),
            Select::make('audience')->options(['all' => 'Everyone', 'retail' => 'Retail only', 'wholesale' => 'Wholesale only'])->default('all')->required(),
            Select::make('product_ids')->label('Limit to products (optional)')->multiple()->searchable()
                ->options(fn () => \App\Models\Product::orderBy('name')->pluck('name', 'id'))->helperText('Empty = applies to the whole cart.'),
            DateTimePicker::make('starts_at'), DateTimePicker::make('expires_at'),
            Toggle::make('is_active')->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold'), TextColumn::make('type')->badge(),
                TextColumn::make('value'), TextColumn::make('audience')->badge(),
                TextColumn::make('used_count')->label('Used')->formatStateUsing(fn ($state, $record) => $state.($record->usage_limit ? " / {$record->usage_limit}" : '')),
                TextColumn::make('expires_at')->dateTime('d M Y')->placeholder('Never'), IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListCoupons::route('/'), 'create' => CreateCoupon::route('/create'), 'edit' => EditCoupon::route('/{record}/edit')];
    }
}
