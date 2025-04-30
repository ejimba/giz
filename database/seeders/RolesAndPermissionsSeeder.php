<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $entities = [
            'clients',
            'incoming sms',
            'outgoing sms',
            'settings',
            'users',
            'roles',
            'permissions',
            'activity',
        ];
        $labels = [
            'view any',
            'view',
            'create',
            'update',
            'delete',
            'restore',
            'force delete',
        ];
        $permissions = [];
        collect($entities)->each(function ($entity) use ($guard, $labels, &$permissions) {
            collect($labels)->each(function ($label) use ($entity, $guard, &$permissions) {
                $data = ['name' => $label.' '.$entity, 'guard_name' => $guard];
                $permission = Permission::firstOrCreate(['name' => $data['name']], $data);
                $permissions[] = $permission->name;
            });
        });
        $roles = [
            ['name' => 'Super User', 'guard_name' => $guard],
        ];
        collect($roles)->each(fn ($role) => Role::firstOrCreate(['name' => $role['name']], $role));
        $role = Role::where('name', 'Super User')->first();
        $role->syncPermissions($permissions);
        $user = User::orderBy('created_at')->first();
        if ($user) {
            $user->assignRole($role);
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
