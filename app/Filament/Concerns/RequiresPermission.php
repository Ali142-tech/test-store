<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Ties a Filament resource to a spatie permission. Filament enforces these checks on every page and
 * action on the server, so hiding/denying is not just a UI concern.
 *
 * Set `$permission` (full access) and optionally `$readPermission` (view-only access).
 */
trait RequiresPermission
{
    private static function allowed(?string $permission): bool
    {
        $user = auth()->user();

        return $user && $permission && $user->can($permission);
    }

    public static function canViewAny(): bool
    {
        return static::allowed(static::$permission) || static::allowed(static::$readPermission ?? null);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return static::allowed(static::$permission);
    }

    public static function canEdit(Model $record): bool
    {
        return static::allowed(static::$permission);
    }

    public static function canDelete(Model $record): bool
    {
        return static::allowed(static::$permission);
    }

    public static function canDeleteAny(): bool
    {
        return static::allowed(static::$permission);
    }
}
