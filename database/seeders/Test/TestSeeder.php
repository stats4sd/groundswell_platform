<?php

namespace Database\Seeders\Test;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Stats4sd\FilamentTeamManagement\Models\Program;

class TestSeeder extends Seeder
{
    /**
     * @throws RequestException
     * @throws ConnectionException
     * @throws BindingResolutionException
     */
    public function run(): void
    {
        // create programs
        $program = Program::create([
            'name' => 'Test Program',
        ]);

        $teamP1 = Team::create([
            'id' => 3,
            'name' => 'P1 Test Team 1',
        ]);
        $teamP2 = Team::create([
            'id' => 4,
            'name' => 'P1 Test Team 2',
        ]);

        $program->teams()->sync([$teamP1->id, $teamP2->id]);

        $nonProgramTeam = Team::create([
            'id' => 5,
            'name' => 'Non Program Test Team',
        ]);

        // create users
        $superAdmin = User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $globalViewer = User::create([
            'name' => 'Test Global Viewer',
            'email' => 'global_viewer@example.com',
            'password' => bcrypt('password123'),
        ]);

        $programAdmin = User::create([
            'name' => 'Test Program Admin',
            'email' => 'program_admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $programViewer = User::create([
            'name' => 'Test Program Viewer',
            'email' => 'program_viewer@example.com',
            'password' => bcrypt('password123'),
        ]);

        $teamAdmin = User::create([
            'name' => 'Test Team Admin',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        // link users to OdkCentral
        if(config('filament-odk-link.odk.url')) {
            $superAdmin->registerOnOdkCentral('password123');
            $globalViewer->registerOnOdkCentral('password123');
            $programAdmin->registerOnOdkCentral('password123');
            $programViewer->registerOnOdkCentral('password123');
            $teamAdmin->registerOnOdkCentral('password123');
        }

        // assign role to users
        $superAdmin->assignRole('Super Admin');
        $globalViewer->assignRole('Global Viewer');
        $programAdmin->assignRole('Program Admin');
        $programViewer->assignRole('Program Viewer');
        $teamAdmin->assignRole('Team Admin');

        // assign user to teams
        $programAdmin->programs()->attach($program->id);
        $programViewer->programs()->attach($program->id);
        $teamAdmin->teams()->attach($nonProgramTeam->id);

        // create local indicators
        $teams = Team::all();
    }
}
