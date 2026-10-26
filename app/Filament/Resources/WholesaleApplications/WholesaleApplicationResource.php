<?php

namespace App\Filament\Resources\WholesaleApplications;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\WholesaleApplications\Pages\ListWholesaleApplications;
use App\Models\WholesaleApplication;
use App\Services\Wholesale\WholesaleApprovalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WholesaleApplicationResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage wholesale';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Wholesale';

    protected static ?string $model = WholesaleApplication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

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

    public static function getNavigationBadge(): ?string
    {
        return (string) WholesaleApplication::where('status', 'pending')->count() ?: null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('business_name'), TextEntry::make('business_type'), TextEntry::make('tax_number'),
            TextEntry::make('contact_name'), TextEntry::make('contact_phone'), TextEntry::make('user.email')->label('Login email'),
            TextEntry::make('business_address'), TextEntry::make('city'),
            TextEntry::make('notes')->columnSpanFull(), TextEntry::make('status')->badge(), TextEntry::make('admin_notes'),
            TextEntry::make('document_original_name')->label('Document')->placeholder('None uploaded'),
        ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_name')->searchable(), TextColumn::make('user.name')->label('Contact')->searchable(), TextColumn::make('city'),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])])
            ->recordActions([
                ViewAction::make(),
                Action::make('document')->label('Document')->icon('heroicon-o-document')->visible(fn ($record) => (bool) $record->document_path)
                    ->url(fn ($record) => route('admin.wholesale-document', $record), shouldOpenInNewTab: true),
                Action::make('approve')->color('success')->icon('heroicon-o-check')->requiresConfirmation()
                    ->visible(fn ($record) => $record->status !== 'approved')
                    ->action(function ($record) {
                        app(WholesaleApprovalService::class)->approve($record, auth()->user());
                        Notification::make()->title('Approved. The customer has been emailed.')->success()->send();
                    }),
                Action::make('reject')->color('danger')->icon('heroicon-o-x-mark')
                    ->visible(fn ($record) => $record->status !== 'rejected')
                    ->schema([Textarea::make('notes')->label('Reason (shown to the applicant)')])
                    ->action(function ($record, array $data) {
                        app(WholesaleApprovalService::class)->reject($record, auth()->user(), $data['notes'] ?? null);
                        Notification::make()->title('Rejected. Wholesale access removed.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWholesaleApplications::route('/')];
    }
}
