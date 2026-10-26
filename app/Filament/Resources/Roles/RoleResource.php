<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Roles\Pages\CreateRole;
use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = 'manage roles';

    protected static ?string $readPermission = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    /** The Super Admin role can only be changed by a Super Admin, and never deleted. */
    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && ($record->name !== 'Super Admin' || auth()->user()->hasRole('Super Admin'));
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && $record->name !== 'Super Admin';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->unique(ignoreRecord: true),
            CheckboxList::make('permissions')->relationship('permissions', 'name')->columns(3)->bulkToggleable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([TextColumn::make('name')->searchable(), TextColumn::make('permissions_count')->counts('permissions')->label('Permissions'), TextColumn::make('users_count')->counts('users')->label('Users')])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRoles::route('/'), 'create' => CreateRole::route('/create'), 'edit' => EditRole::route('/{record}/edit')];
    }
}
