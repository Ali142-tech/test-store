<?php

namespace App\Filament\Resources\Reviews;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Moderation queue: reviews are only displayed publicly once approved. Content cannot be edited by staff. */
class ReviewResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'moderate reviews';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

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
        return (string) Review::where('status', 'pending')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')->limit(30)->searchable(), TextColumn::make('user.name')->label('Customer'),
                TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', $state)),
                TextColumn::make('body')->limit(60)->wrap(), TextColumn::make('is_verified_purchase')->badge()->formatStateUsing(fn ($state) => $state ? 'Verified' : 'Unverified'),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) { 'approved' => 'success', 'rejected' => 'danger', default => 'warning' }),
                TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([SelectFilter::make('status')->options(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'])->default('pending')])
            ->recordActions([
                Action::make('approve')->color('success')->icon('heroicon-o-check')->visible(fn ($record) => $record->status !== 'approved')
                    ->action(fn (Review $r) => $r->forceFill(['status' => 'approved'])->save()),
                Action::make('reject')->color('danger')->icon('heroicon-o-x-mark')->visible(fn ($record) => $record->status !== 'rejected')
                    ->action(fn (Review $r) => $r->forceFill(['status' => 'rejected'])->save()),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListReviews::route('/')];
    }
}
