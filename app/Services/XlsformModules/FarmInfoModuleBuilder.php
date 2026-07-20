<?php

namespace App\Services\XlsformModules;

use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class FarmInfoModuleBuilder
{
    public static function populate(Team $team): void
    {

        $xlsformModules = XlsformModule::where('name', 'farm info')->get();

        foreach ($xlsformModules as $xlsformModule) {

            $moduleVersion = XlsformModuleVersion::firstOrCreate([
                'owner_id' => $team->id,
                'name' => 'Local farm info',
                'xlsform_module_id' => $xlsformModule->id,
            ]);

            $dataset = app(OdkFarmEntityService::class)->ensureDataset($team);

            $levelCount = $team->locationLevels()->count();
            $identifiers = static::variables($dataset, 'identifier');
            $properties = static::variables($dataset, 'property');

            // Groundswell specific - check for identifiers + properties required in the main form
            $requiredList = collect([
                'participant_sex',
                'participant_name',
                'participant_age',
            ]);

            $merged = $identifiers->merge($properties)->pluck('name');

            // check that the required list is in either identifiers or properties. Save as datasetvariable so next time it is immediately included.
            $missing = $requiredList->diff($merged)
            ->map(function(string $item) use ($dataset) {

                return DatasetVariable::create([
                    'dataset_id' => $dataset->id,
                    'name' => $item,
                    'label' => $item,
                    'description' => 'identifier',
                ]);
            });

            // add missing items to identifiers
            $identifiers = $identifiers->merge($missing);

            static::buildSurveyRows($moduleVersion,$team, $levelCount, $identifiers, $properties);
        }
    }

    /** @return Collection<int, DatasetVariable> */
    protected static function variables(Dataset $dataset, string $description): Collection
    {
        return $dataset->variables()->where('description', $description)->orderBy('id')->get();
    }

    /**
     * @param  Collection<int, DatasetVariable>  $identifiers
     * @param  Collection<int, DatasetVariable>  $properties
     */
    protected static function buildSurveyRows(XlsformModuleVersion $moduleVersion, Team $team, int $levelCount, Collection $identifiers, Collection $properties): void
    {
        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farms', 'type' => 'begin_group'],
            ['row_number' => 1],
        );

        $rowNumber = 2;

        $choiceFilter = $levelCount >= 1 ? 'loc'.$levelCount.'=${loc'.$levelCount.'}' : '';

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'ID', 'type' => 'select_one_from_file Farm_Summary.csv'],
            [
                'required' => true,
                'choice_filter' => $choiceFilter,
                'properties' => collect(static::labelProperties($team, 'Please select the farm you are visiting')),
                'row_number' => $rowNumber++,
            ],
        );

        $allVariables = $identifiers->concat($properties);

        foreach ($allVariables as $variable) {
            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => $variable->name, 'type' => 'calculate'],
                [
                    'calculation' => 'instance(\'Farm_Summary\')/root/item[name=${ID}]/'.$variable->name,
                    'row_number' => $rowNumber++,
                ],
            );
        }

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farmer_note', 'type' => 'note'],
            [
                'properties' => collect(static::labelProperties($team, static::noteText($identifiers, $properties))),
                'row_number' => $rowNumber++,
            ],
        );

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'farms', 'type' => 'end_group'],
            ['row_number' => $rowNumber++],
        );

        static::deleteStaleCalculateRows($moduleVersion, $allVariables);
    }

    /** @param Collection<int, DatasetVariable> $variables */
    protected static function deleteStaleCalculateRows(XlsformModuleVersion $moduleVersion, Collection $variables): void
    {
        $currentNames = $variables->map(fn (DatasetVariable $variable) => $variable->name);

        $moduleVersion->surveyRows()
            ->where('type', 'calculate')
            ->whereNotIn('name', $currentNames)
            ->delete();
    }

    /**
     * @param  Collection<int, DatasetVariable>  $identifiers
     * @param  Collection<int, DatasetVariable>  $properties
     */
    protected static function noteText(Collection $identifiers, Collection $properties): string
    {
        $lines = ['You have selected the following farm:', ''];

        foreach ($identifiers as $variable) {
            $lines[] = $variable->label.': ${'.$variable->name.'},';
        }

        $lines[] = '';

        foreach ($properties as $variable) {
            $lines[] = $variable->label.': ${'.$variable->name.'},';
        }

        $lines[] = '';
        $lines[] = 'If this is not the correct farm, please go back and reselect.';

        return implode(PHP_EOL, $lines);
    }

    /** @return array<string, string> */
    protected static function labelProperties(Team $team, string $label): array
    {
        $properties = [];

        foreach ($team->locales()->with('language')->get() as $locale) {
            $language = $locale->language;
            $properties['label::'.$language->name.' ('.$language->iso_alpha2.')'] = $label;
        }

        return $properties;
    }
}
