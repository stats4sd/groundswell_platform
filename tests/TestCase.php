<?php

namespace Tests;

use App\Models\Holpa\Theme;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentTeamManagement\Models\Program;

abstract class TestCase extends BaseTestCase
{

    protected bool $seed = true;

    // Properties for use across multiple tests;
    public User $superAdmin;
    public User $programAdmin;
    public User $user;
    public Team $team;
    public Program $program;

    public Theme $theme;

    public XlsformTemplate $xlsformTemplate;
    public XlsformModule $xlsformModule;
}
