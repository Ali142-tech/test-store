<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AuditLogResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'view audit log';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->dateTime(), TextEntry::make('user.name')->placeholder('system'), TextEntry::make('action'),
            TextEntry::make('subject_type'), TextEntry::make('subject_id'), TextEntry::make('ip_address'),
            TextEntry::make('changes')->columnSpanFull()->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))->html(false)->extraAttributes(['style' => 'white-space:pre-wrap;font-family:monospace']),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('user.name')->placeholder('system')->searchable(),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('subject_type')->formatStateUsing(fn ($state, $record) => $state ? class_basename($state).' #'.$record->subject_id : '-'),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
