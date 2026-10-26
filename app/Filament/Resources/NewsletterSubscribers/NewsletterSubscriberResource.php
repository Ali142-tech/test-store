<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\NewsletterSubscribers\Pages\ListNewsletterSubscribers;
use App\Models\NewsletterSubscriber;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriberResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage content';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('subscribed_at')->dateTime('d M Y')->sortable(),
                TextColumn::make('unsubscribed_at')->dateTime('d M Y')->placeholder('Active'),
            ])
            ->defaultSort('subscribed_at', 'desc')
            ->filters([TernaryFilter::make('active')->queries(true: fn ($q) => $q->whereNull('unsubscribed_at'), false: fn ($q) => $q->whereNotNull('unsubscribed_at'), blank: fn ($q) => $q)->default(true)]);
    }

    public static function getPages(): array
    {
        return ['index' => ListNewsletterSubscribers::route('/')];
    }
}
