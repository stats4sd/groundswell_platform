<?php

namespace Database\Seeders\Test;

use App\Models\Holpa\GlobalIndicator;
use App\Models\Holpa\LocalIndicator;
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
            'name' => 'P1 Test Team',
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
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $admin = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        $programAdmin = User::create([
            'name' => 'Test Program Admin',
            'email' => 'program_admin@example.com',
            'password' => bcrypt('password123'),
        ]);

        // link users to OdkCentral
        if(config('filament-odk-link.odk.url')) {
            $user->registerOnOdkCentral('password123');
            $admin->registerOnOdkCentral('password123');
            $programAdmin->registerOnOdkCentral('password123');

        }

        // assign role to users
        $admin->assignRole('Super Admin');
        $programAdmin->assignRole('Program Admin');

        // assign user to teams
        $user->teams()->attach($nonProgramTeam->id);
        $programAdmin->programs()->attach($program->id);

        // create local indicators
        $teams = Team::all();

    }
}
