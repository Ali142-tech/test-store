<?php

namespace App\Filament\Resources\Quotes;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Quotes\Pages\EditQuote;
use App\Filament\Resources\Quotes\Pages\ListQuotes;
use App\Models\Quote;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class QuoteResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage quotes';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Wholesale';

    protected static ?string $model = Quote::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) Quote::where('status', 'requested')->count() ?: null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Request')->columns(3)->schema([
                Placeholder::make('reference')->content(fn (?Quote $record) => $record?->reference),
                Placeholder::make('customer')->content(fn (?Quote $record) => $record?->user->name.' <'.$record?->user->email.'>'),
                Placeholder::make('status')->content(fn (?Quote $record) => ucfirst((string) $record?->status)),
                Placeholder::make('customer_notes')->content(fn (?Quote $record) => $record?->customer_notes ?: '-')->columnSpanFull(),
            ]),
            Section::make('Your response')->schema([
                Repeater::make('items')->relationship()->addable(false)->deletable(false)->reorderable(false)->columns(4)->schema([
                    Placeholder::make('sku')->content(fn ($record) => $record?->variant?->sku.' - '.$record?->variant?->product?->name),
                    Placeholder::make('qty')->label('Quantity')->content(fn ($record) => $record?->quantity),
                    Placeholder::make('target')->label('Target price')->content(fn ($record) => $record?->target_price ?? '-'),
                    TextInput::make('quoted_price')->numeric()->minValue(0)->required()->label('Quoted unit price'),
                ]),
                TextInput::make('shipping_amount')->numeric()->minValue(0)->helperText('Leave empty to use standard shipping rates; 0 = free shipping.'),
                DateTimePicker::make('expires_at')->required()->label('Valid until')->minDate(now()),
                Textarea::make('admin_notes')->label('Message to the customer'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(), TextColumn::make('user.name')->label('Customer')->searchable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'requested' => 'warning', 'quoted' => 'info', 'accepted', 'converted' => 'success', default => 'gray' }),
                TextColumn::make('quoted_total')->money('PKR'), TextColumn::make('expires_at')->dateTime('d M Y'), TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['requested' => 'Requested', 'quoted' => 'Quoted', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'expired' => 'Expired', 'converted' => 'Converted'])])
            ->recordActions([EditAction::make()->label('Respond')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListQuotes::route('/'), 'edit' => EditQuote::route('/{record}/edit')];
    }
}
