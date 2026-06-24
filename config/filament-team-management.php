<?php

// config for Stats4sd/FilamentTeamManagement
use Spatie\Permission\Models\Role;
use Stats4sd\FilamentTeamManagement\Models\Program;
use Stats4sd\FilamentTeamManagement\Models\Team;
use Stats4sd\FilamentTeamManagement\Models\User;

return [
    'use_programs' => env('FILAMENT_TEAM_MANAGEMENT_USE_PROGRAMS', false),

    'models' => [
        'user' => env('FILAMENT_TEAM_MANAGEMENT_USER_MODEL', User::class),
        'team' => env('FILAMENT_TEAM_MANAGEMENT_TEAM_MODEL', Team::class),
        'program' => env('FILAMENT_TEAM_MANAGEMENT_PROGRAM_MODEL', Program::class),
        'role' => Role::class,
    ],

    // When using custom table names for your users or teams table, you can set them here
    'table_names' => [
        'users' => 'users',
        'teams' => 'teams',
        'programs' => 'programs',
        'program_members' => 'program_user',
        'program_team' => 'program_team',
        'team_members' => 'team_members',
    ],

    // When using custom foreign keys for your users or teams table, you can set them here
    'column_names' => [
        'users_foreign_key' => 'user_id',
        'teams_foreign_key' => 'team_id',
        'programs_foreign_key' => 'program_id',
    ],
];
