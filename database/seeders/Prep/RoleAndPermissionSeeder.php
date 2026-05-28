<?php

namespace Database\Seeders\Prep;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create roles
        $superAdminRole = Role::updateOrCreate(['name' => 'Super Admin']);
        $globalViewerRole = Role::updateOrCreate(['name' => 'Global Viewer']);
        $programAdminRole = Role::updateOrCreate(['name' => 'Program Admin']);
        $programViewerRole = Role::updateOrCreate(['name' => 'Program Viewer']);
        $teamAdminRole = Role::updateOrCreate(['name' => 'Team Admin']);


        // create permissions
        $permissions = [
            ['name' => 'access admin panel', 'guard_name' => 'web'],
            ['name' => 'access program admin panel', 'guard_name' => 'web'],
            ['name' => 'view all programs', 'guard_name' => 'web'],
            ['name' => 'view all teams', 'guard_name' => 'web'],
            ['name' => 'view admin panel dashboard', 'guard_name' => 'web'],
            ['name' => 'view programs', 'guard_name' => 'web'],
            ['name' => 'maintain programs', 'guard_name' => 'web'],
            ['name' => 'view teams', 'guard_name' => 'web'],
            ['name' => 'maintain teams', 'guard_name' => 'web'],
            ['name' => 'view users', 'guard_name' => 'web'],
            ['name' => 'maintain users', 'guard_name' => 'web'],
            ['name' => 'view domains', 'guard_name' => 'web'],
            ['name' => 'maintain domains', 'guard_name' => 'web'],
            ['name' => 'view global indicators', 'guard_name' => 'web'],
            ['name' => 'maintain global indicators', 'guard_name' => 'web'],
            ['name' => 'view themes', 'guard_name' => 'web'],
            ['name' => 'maintain themes', 'guard_name' => 'web'],
            ['name' => 'view datasets', 'guard_name' => 'web'],
            ['name' => 'maintain datasets', 'guard_name' => 'web'],
            ['name' => 'view xlsform modules', 'guard_name' => 'web'],
            ['name' => 'maintain xlsform modules', 'guard_name' => 'web'],
            ['name' => 'view xlsform module versions', 'guard_name' => 'web'],
            ['name' => 'maintain xlsform module versions', 'guard_name' => 'web'],
            ['name' => 'view xlsform templates', 'guard_name' => 'web'],
            ['name' => 'maintain xlsform templates', 'guard_name' => 'web'],
            ['name' => 'view choice lists', 'guard_name' => 'web'],
            ['name' => 'maintain choice lists', 'guard_name' => 'web'],
            ['name' => 'view program admin panel dashboard', 'guard_name' => 'web'],
            ['name' => 'view my program', 'guard_name' => 'web'],
            ['name' => 'maintain my program', 'guard_name' => 'web'],
            ['name' => 'view survey dashboard', 'guard_name' => 'web'],
            ['name' => 'view team selection box', 'guard_name' => 'web'],
            ['name' => 'view my team', 'guard_name' => 'web'],
            ['name' => 'maintain my team', 'guard_name' => 'web'],
            ['name' => 'view download user guide', 'guard_name' => 'web'],
            ['name' => 'view my account', 'guard_name' => 'web'],
            ['name' => 'maintain my account', 'guard_name' => 'web'],
            ['name' => 'view survey country and languages', 'guard_name' => 'web'],
            ['name' => 'view select country and languages', 'guard_name' => 'web'],
            ['name' => 'maintain select country and languages', 'guard_name' => 'web'],
            ['name' => 'view survey translations', 'guard_name' => 'web'],
            ['name' => 'maintain survey translations', 'guard_name' => 'web'],
            ['name' => 'view survey locations', 'guard_name' => 'web'],
            ['name' => 'view manage location levels', 'guard_name' => 'web'],
            ['name' => 'maintain manage location levels', 'guard_name' => 'web'],
            ['name' => 'view list of farms', 'guard_name' => 'web'],
            ['name' => 'maintain list of farms', 'guard_name' => 'web'],
            ['name' => 'view context questions', 'guard_name' => 'web'],
            ['name' => 'maintain context questions', 'guard_name' => 'web'],
            ['name' => 'view place-based adaptations', 'guard_name' => 'web'],
            ['name' => 'view adapt time frame', 'guard_name' => 'web'],
            ['name' => 'maintain adapt time frame', 'guard_name' => 'web'],
            ['name' => 'view adapt diet quality module', 'guard_name' => 'web'],
            ['name' => 'maintain adapt diet quality module', 'guard_name' => 'web'],
            ['name' => 'view initial pilot', 'guard_name' => 'web'],
            ['name' => 'maintain initial pilot', 'guard_name' => 'web'],
            ['name' => 'view lisp', 'guard_name' => 'web'],
            ['name' => 'view lisp workshop', 'guard_name' => 'web'],
            ['name' => 'maintain lisp workshop', 'guard_name' => 'web'],
            ['name' => 'view customise indicators', 'guard_name' => 'web'],
            ['name' => 'view upload local indicators', 'guard_name' => 'web'],
            ['name' => 'maintain upload local indicators', 'guard_name' => 'web'],
            ['name' => 'view match with existing global indicators', 'guard_name' => 'web'],
            ['name' => 'maintain match with existing global indicators', 'guard_name' => 'web'],
            ['name' => 'view add custom survey questions', 'guard_name' => 'web'],
            ['name' => 'maintain add custom survey questions', 'guard_name' => 'web'],
            ['name' => 'view place custom questions in survey', 'guard_name' => 'web'],
            ['name' => 'maintain place custom questions in survey', 'guard_name' => 'web'],
            ['name' => 'view pilot', 'guard_name' => 'web'],
            ['name' => 'maintain pilot', 'guard_name' => 'web'],
            ['name' => 'view data collection', 'guard_name' => 'web'],
            ['name' => 'view set up the survey', 'guard_name' => 'web'],
            ['name' => 'maintain set up the survey', 'guard_name' => 'web'],
            ['name' => 'view monitor data collection', 'guard_name' => 'web'],
            ['name' => 'view download data', 'guard_name' => 'web'],
            ['name' => 'maintain download data', 'guard_name' => 'web'],
        ];



        Permission::upsert($permissions, ['name', 'guard_name']);


        // create permissions, then clear all role assignments before re-assigning below
        // (prevents removed permissions from persisting across seeder runs)
        foreach ([$superAdminRole, $globalViewerRole, $programAdminRole, $programViewerRole, $teamAdminRole] as $role) {
            $role->syncPermissions([]);
        }

        // assign all permissions to Super Admin role
        // Super Admin = Admin Panel, Program Admin Panel, App Panel, with view permissions and maintain permissions
        $superAdminRole->givePermissionTo(array_column($permissions, 'name'));


        // assign permissions to Global Viewer role
        // Global Viewer = Super Admin with view permissions, without maintain permissions
        $globalViewerRole->givePermissionTo([
            'access admin panel',
            'access program admin panel',
            'view all programs',
            'view all teams',
            'view admin panel dashboard',
            'view programs',
            'view teams',
            'view users',
            'view domains',
            'view global indicators',
            'view themes',
            'view datasets',
            'view xlsform modules',
            'view xlsform module versions',
            'view xlsform templates',
            'view choice lists',
            'view program admin panel dashboard',
            'view my program',
            'view survey dashboard',
            'view team selection box',
            'view my team',
            'view download user guide',
            'view my account',
            'maintain my account',
            'view survey country and languages',
            'view select country and languages',
            'view survey translations',
            'view survey locations',
            'view manage location levels',
            'view list of farms',
            'view context questions',
            'view place-based adaptations',
            'view adapt time frame',
            'view adapt diet quality module',
            'view initial pilot',
            'view lisp',
            'view lisp workshop',
            'view customise indicators',
            'view upload local indicators',
            'view match with existing global indicators',
            'view add custom survey questions',
            'view place custom questions in survey',
            'view pilot',
            'view data collection',
            'view set up the survey',
            'view monitor data collection',
            'view download data',
        ]);

        // assign permissions to Program Admin role
        // Program Admin = Program Admin Panel + App Panel, view permissions and maintain permissions
        $programAdminRole->givePermissionTo([
            'access program admin panel',
            'view program admin panel dashboard',
            'view my program',
            'maintain my program',
            'view survey dashboard',
            'view team selection box',
            'view my team',
            'maintain my team',
            'view download user guide',
            'view my account',
            'maintain my account',
            'view survey country and languages',
            'view select country and languages',
            'maintain select country and languages',
            'view survey translations',
            'maintain survey translations',
            'view survey locations',
            'view manage location levels',
            'maintain manage location levels',
            'view list of farms',
            'maintain list of farms',
            'view context questions',
            'maintain context questions',
            'view place-based adaptations',
            'view adapt time frame',
            'maintain adapt time frame',
            'view adapt diet quality module',
            'maintain adapt diet quality module',
            'view initial pilot',
            'maintain initial pilot',
            'view lisp',
            'view lisp workshop',
            'maintain lisp workshop',
            'view customise indicators',
            'view upload local indicators',
            'maintain upload local indicators',
            'view match with existing global indicators',
            'maintain match with existing global indicators',
            'view add custom survey questions',
            'maintain add custom survey questions',
            'view place custom questions in survey',
            'maintain place custom questions in survey',
            'view pilot',
            'maintain pilot',
            'view data collection',
            'view set up the survey',
            'maintain set up the survey',
            'view monitor data collection',
            'view download data',
            'maintain download data',
        ]);

        // assign permissions to Program Viewer role
        // Program Viewer = Program Admin with view permissions, without maintain permissions
        $programViewerRole->givePermissionTo([
            'access program admin panel',
            'view program admin panel dashboard',
            'view my program',
            'view survey dashboard',
            'view team selection box',
            'view my team',
            'view download user guide',
            'view my account',
            'maintain my account',
            'view survey country and languages',
            'view select country and languages',
            'view survey translations',
            'view survey locations',
            'view manage location levels',
            'view list of farms',
            'view context questions',
            'view place-based adaptations',
            'view adapt time frame',
            'view adapt diet quality module',
            'view initial pilot',
            'view lisp',
            'view lisp workshop',
            'view customise indicators',
            'view upload local indicators',
            'view match with existing global indicators',
            'view add custom survey questions',
            'view place custom questions in survey',
            'view pilot',
            'view data collection',
            'view set up the survey',
            'view monitor data collection',
            'view download data',
        ]);

        // assign permissions to Team Admin role
        // Team Admin = App Panel, with view permissions and maintain permissions
        $teamAdminRole->givePermissionTo([
            'view survey dashboard',
            'view team selection box',
            'view my team',
            'maintain my team',
            'view download user guide',
            'view my account',
            'maintain my account',
            'view survey country and languages',
            'view select country and languages',
            'maintain select country and languages',
            'view survey translations',
            'maintain survey translations',
            'view survey locations',
            'view manage location levels',
            'maintain manage location levels',
            'view list of farms',
            'maintain list of farms',
            'view context questions',
            'maintain context questions',
            'view place-based adaptations',
            'view adapt time frame',
            'maintain adapt time frame',
            'view adapt diet quality module',
            'maintain adapt diet quality module',
            'view initial pilot',
            'maintain initial pilot',
            'view lisp',
            'view lisp workshop',
            'maintain lisp workshop',
            'view customise indicators',
            'view upload local indicators',
            'maintain upload local indicators',
            'view match with existing global indicators',
            'maintain match with existing global indicators',
            'view add custom survey questions',
            'maintain add custom survey questions',
            'view place custom questions in survey',
            'maintain place custom questions in survey',
            'view pilot',
            'maintain pilot',
            'view data collection',
            'view set up the survey',
            'maintain set up the survey',
            'view monitor data collection',
            'view download data',
            'maintain download data',
        ]);
    }
}
