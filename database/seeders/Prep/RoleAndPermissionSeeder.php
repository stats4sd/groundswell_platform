<?php

namespace Database\Seeders\Prep;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create roles
        $superAdminRole = Role::create(['name' => 'Super Admin']);
        $programAdminRole = Role::create(['name' => 'Program Admin']);


        // create permissions
        $permissions = [
            ['name' => 'access admin panel'],
            ['name' => 'access program admin panel'],
            ['name' => 'view all prorgrams'],
            ['name' => 'view all teams'],
        ];

        $superAdminRole->permissions()->createMany($permissions);
        $programAdminRole->givePermissionTo('access program admin panel');
    }
}
