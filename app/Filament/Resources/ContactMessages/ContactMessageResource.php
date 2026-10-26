<?php

namespace App\Filament\Resources\ContactMessages;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ContactMessageResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'handle support';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) ContactMessage::where('status', 'new')->count() ?: null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name'), TextEntry::make('email')->copyable(), TextEntry::make('phone'), TextEntry::make('subject'),
            TextEntry::make('message')->columnSpanFull(), TextEntry::make('created_at')->dateTime(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(), TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(),
                TextColumn::make('subject')->limit(30), IconColumn::make('is_wholesale_lead')->boolean()->label('Wholesale lead'),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'new' ? 'warning' : 'success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['new' => 'New', 'handled' => 'Handled'])])
            ->recordActions([
                ViewAction::make(),
                Action::make('handled')->label('Mark handled')->icon('heroicon-o-check')->visible(fn ($record) => $record->status === 'new')->action(fn ($record) => $record->update(['status' => 'handled'])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListContactMessages::route('/')];
    }
}
