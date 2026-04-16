<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;

class AddChoiceListIdToExistingSurveyRows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-choice-list-id-to-existing-survey-rows';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        SurveyRow::all()
            ->filter(fn ($surveyRow) => Str::contains($surveyRow->type, 'select'))
            ->each(function (SurveyRow $surveyRow) {
                $choiceListName = collect(explode(' ', trim($surveyRow->type)))->last();

                $surveyRow->choiceList()->associate($surveyRow->xlsformModuleVersion->choiceLists()->where('list_name', '=', $choiceListName)->first());

                $surveyRow->save();

            });
    }
}
