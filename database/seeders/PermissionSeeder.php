<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions

        $permissions = [
            'access-roles',
            'create-roles',
            'edit-roles',
            'delete-roles',
            'access-products',
            'create-products',
            'edit-products',
            'delete-products',
            'access-users',
            'create-users',
            'edit-users',
            'delete-users',
            'access-contacts',
            'create-contacts',
            'edit-contacts',
            'delete-contacts',
            'access-dashboard'
            
        ];

        foreach($permissions as $key => $permission)
        {
            Permission::create(['name' => $permission]);
        }
        
        // create roles and assighn permisisons
        $role = Role::create(['name' => 'General Admin']);
        $role->givePermissionTo(Permission::all());
    }
}
