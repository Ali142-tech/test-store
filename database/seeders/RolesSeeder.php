<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public const PERMISSIONS = [
        'manage catalog', 'view cost prices', 'change prices',
        'manage inventory', 'manage suppliers',
        'view orders', 'manage orders', 'process refunds',
        'manage customers', 'manage wholesale', 'manage quotes',
        'manage shipping', 'manage coupons', 'manage content',
        'moderate reviews', 'handle support', 'manage settings',
        'manage roles', 'view reports', 'view audit log',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $p) {
            Permission::findOrCreate($p, 'web');
        }

        $roles = [
            'Super Admin' => self::PERMISSIONS,
            'Store Manager' => array_diff(self::PERMISSIONS, ['manage roles', 'manage settings']),
            'Inventory Manager' => ['manage catalog', 'view cost prices', 'manage inventory', 'manage suppliers', 'view reports'],
            'Order Manager' => ['view orders', 'manage orders', 'manage shipping', 'view reports'],
            'Customer Support' => ['view orders', 'handle support', 'moderate reviews', 'manage customers'],
            'Wholesale Manager' => ['manage wholesale', 'manage quotes', 'view orders', 'change prices', 'manage customers', 'view reports'],
        ];

        foreach ($roles as $name => $perms) {
            Role::findOrCreate($name, 'web')->syncPermissions($perms);
        }
    }
}
