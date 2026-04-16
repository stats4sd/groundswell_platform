<?php

namespace App\Console\Commands;

use App\Events\TestEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use phpDocumentor\Reflection\DocBlock\Tags\Template;
use Stats4sd\FilamentOdkLink\Events\XlsformDraftWasDeployed;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'For running test or one-time commands that do not need to be kept during development';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        dump('Use this as a scratch space for testing things!');

        TestEvent::dispatch();
        XlsformDraftWasDeployed::dispatch(4);
    }
}
