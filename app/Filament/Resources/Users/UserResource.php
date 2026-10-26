<?php

namespace App\Filament\Resources\Users;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Customers and staff accounts. Role assignment and staff creation require "manage roles". */
class UserResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage customers';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'user';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->can('manage roles');
    }

    /** Staff accounts can only be edited by someone with "manage roles" (prevents support staff taking over admins). */
    public static function canEdit(Model $record): bool
    {
        $u = auth()->user();

        return $u && $u->can('manage customers') && (! $record->isStaff() || $u->can('manage roles'));
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->can('manage roles') && $record->id !== auth()->id();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('phone'),
            TextInput::make('password')->password()->revealable()->minLength(8)
                ->required(fn (string $operation) => $operation === 'create')->dehydrated(fn ($state) => filled($state))
                ->visible(fn () => (bool) auth()->user()?->can('manage roles')),
            Toggle::make('is_active')->label('Account active')->helperText('Deactivated users cannot sign in.'),
            Select::make('roles')->relationship('roles', 'name', fn ($q) => auth()->user()->hasRole('Super Admin') ? $q : $q->where('name', '!=', 'Super Admin'))->multiple()->preload()->label('Staff roles')
                ->visible(fn () => (bool) auth()->user()?->can('manage roles')),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(), TextColumn::make('phone'),
                TextColumn::make('customer_type')->badge()->color(fn ($state) => $state === 'wholesale' ? 'warning' : 'gray'),
                TextColumn::make('roles.name')->badge()->placeholder('Customer'),
                IconColumn::make('is_active')->boolean(), TextColumn::make('created_at')->dateTime('d M Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('customer_type')->options(['retail' => 'Retail', 'wholesale' => 'Wholesale']),
                SelectFilter::make('staff')->options(['1' => 'Staff only'])->query(fn ($q, ?array $data) => ($data['value'] ?? null) ? $q->whereHas('roles') : $q),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'create' => CreateUser::route('/create'), 'edit' => EditUser::route('/{record}/edit')];
    }
}
