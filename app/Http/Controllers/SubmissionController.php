<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Team;
use App\Models\Dataset;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Filament\Facades\Filament;
use App\Models\SampleFrame\Farm;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Models\SampleFrame\Location;
use Illuminate\Support\Facades\Process;
use App\Models\SampleFrame\LocationLevel;
use Stats4sd\FilamentOdkLink\Models\OdkLink\DatasetVariable;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\EntityValue;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplateSection;
use Stats4sd\FilamentOdkLink\Services\OdkLinkService;

class SubmissionController extends Controller
{
    // This function will be called when there are new submissions to be pulled from ODK central
    public static function process(Submission $submission): void
    {
        // check if test or live data
        /** @var Team $team */
        $team = $submission->xlsformVersion->xlsform->owner;

        if (!$team->pilot_complete) {
            $submission->test_data = true;
            $submission->save();
        }

        static::handleLocationData($submission, $team);

        /** @var Farm $farm */
        $farm = $submission->primaryDataSubject;
        $farm->updateCompletionStatus();

        // TODO: consolidate to 1 default consent module?
        if (isset($submission->content['consent_group'])) {
            $consent = $submission->content['consent_group']['consent_1'];
        } else {
            $consent = $submission->content['consent']['consent_interview'];
        }

        $farm->refused = $consent == '0';
        $farm->save();

        // application specific business logic goes here
        // find survey start, survey end, survey duration in minutes
        if (isset($submission->content['start']) && isset($submission->content['end'])) {

            $surveyStart = Carbon::parse($submission->content['start']);
            $surveyEnd = Carbon::parse($submission->content['end']);
            $surveyDuration = $surveyStart->diffInMinutes($surveyEnd);

            $submission->survey_started_at = $surveyStart;
            $submission->survey_ended_at = $surveyEnd;
            $submission->survey_duration = $surveyDuration;
            $submission->save();

        }
        if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Household')) {
            static::processHouseholdSubmission($submission);
        }

        if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Fieldwork')) {
            static::processFieldworkSubmission($submission);
        }


        // only run R scripts if both surveys are complete for the farm
        if ($submission->test_data) {
            $farmDone = $farm->household_pilot_completed && $farm->fieldwork_pilot_completed;
        } else {
            $farmDone = $farm->household_form_completed && $farm->fieldwork_form_completed;
        }


        if ($farmDone) {

            // Run R scripts
            $RscriptPath = config('services.R.rscript_path');
            $agOut = Process::path(base_path('packages/holpa-r-scripts'))
                ->run($RscriptPath . ' data_processing/holpa_agroecology_scores.R');
            $perfOut = Process::path(base_path('packages/holpa-r-scripts'))
                ->run($RscriptPath . ' data_processing/key_performance_indicators.R');

        }


        // check through the new entities and update the dataset_variables list
        $newEntities = $submission->entities->load('dataset.variables', 'values');

        foreach ($newEntities as $entity) {
            $dataset = $entity->dataset;

            // get list of existing variable names in the dataset
            $existingVariables = $dataset->variables->sortBy('order')->pluck('name');

            // get list of variable names in the entity
            $entityVariables = $entity->values->sortBy('id')->pluck('dataset_variable_name');

            for ($i = 0; $i < count($entityVariables); $i++) {
                $currentVariable = $entityVariables[$i];

                if ($i > 0) {
                    $previousVariable = $entityVariables[$i - 1];
                } else {
                    $previousVariable = null;
                }

                if ($existingVariables->contains($currentVariable)) {
                    // variable exists; do nothing
                    continue;
                } else {

                    // new list with inserted new variable
                    $updatedList = [];
                    $j = 0;


                    // new variable found; add it to dataset variables after previousVariable
                    if($previousVariable == null) {
                        // insert at beginning
                        $updatedList[$j] = $currentVariable;
                        $j++;
                    }

                    foreach ($existingVariables as $var) {
                        $updatedList[$j] = $var;
                        if ($var == $previousVariable) {
                            $j++;
                            $updatedList[$j] = $currentVariable;
                        }
                        $j++;
                    }

                    $existingVariables = collect($updatedList);

                }

            }

            // replace database list with new list
            $existingVariables->each(function ($varName, $index) use ($dataset) {
                DatasetVariable::updateOrCreate(
                    [
                        'dataset_id' => $dataset->id,
                        'name' => $varName,
                        'label' => $varName,
                    ],
                    [
                        'order' => $index,
                    ]
                );
            });


        }


    }

    public static function processHouseholdSubmission(Submission $submission): void
    {
        $farmDataEntity = $submission->rootEntity;

        // custom handling to fill in 12 months irrigation percentage in farm_survey_data record
        $farmDataEntity->addValues(SubmissionController::getIrrigationData($submission));

        // create records from nested repeat groups for seasonal_worker_seasons

        $permanentWorkersEntities = $submission->entities()->whereHas('dataset', fn($query) => $query->where('name', 'Permanent Workers'))
            ->get()
            ->each(function (Entity $entity) {
                // harmonise variable names from "perm_labourer_" to "perm_labour_"
                foreach ($entity->values as $value) {
                    if (Str::contains($value->dataset_variable_name, 'perm_labourer_')) {
                        $newKey = Str::replace('perm_labourer_', 'perm_labour_', $value->dataset_variable_name);
                        $value->dataset_variable_name = $newKey;
                        $value->save();
                    }
                }
            });

        $seasonalWorkersDataset = Dataset::firstWhere('name', 'Seasonal Workers in a Season');
        $farmDataEntity->addChildEntities(SubmissionController::handleSeasonalWorkersData($submission), $seasonalWorkersDataset);
        $farmDataEntity->addChildEntities(SubmissionController::handleSeasonalLaboursData($submission), $seasonalWorkersDataset);

        $farmProductsDataset = Dataset::firstWhere('name', 'Products');
        $farmDataEntity->addChildEntities(SubmissionController::handleProductsData($submission), $farmProductsDataset);
        $farmDataEntity->addChildEntities(SubmissionController::handleOtherProductData($submission), $farmProductsDataset);

        // handle multi-selects for new / updated data

    }

    public static function processFieldworkSubmission(Submission $submission): void
    {
        // no entities have been created yet, because the ODK package skips creating entities if the 'root' section is not linked to a dataset.
        $siteDataset = Dataset::firstWhere('name', 'Fieldwork Sites');


        $farmInfo = [
            'farm_id' => $submission->primaryDataSubject->id,
            'farm_code' => $submission->primaryDataSubject->team_code,
            'farm_name' => $submission->primaryDataSubject->identifiers['name'] ?? ($submission->primaryDataSubject->identifiers->first() ?? null),
        ];

        // create site entities from repeat groups
        if (isset($submission->content['survey']['field_survey']['sites'])) {
            $sitesData = $submission->content['survey']['field_survey']['sites'];

            // there should be 3 sites. Create a new entity for each
            foreach ($sitesData as $siteData) {
                $siteEntity = Entity::create([
                    'submission_id' => $submission->id,
                    'dataset_id' => $siteDataset->id,
                    'parent_id' => null,
                    'owner_id' => $submission->owner->id,
                ]);

                $farmInfoEntityValues = collect($farmInfo)->map(function ($value, $key) {
                    return EntityValue::make([
                        'dataset_variable_name' => $key,
                        'value' => $value,
                    ]);
                });

                // add farmInfo to entity
                $siteEntity->addValues($farmInfoEntityValues);

                // get and flatten the rest of the site data from the submission content

                $siteFormSection = $siteDataset->xlsformTemplateSections->first();
                $schema = $siteFormSection->schema->where('type', '!=', 'structure');

                // get all choices lists once to avoid multiple db calls when preparing select_multiple values
                $xlsform = $submission->xlsform;
                $choices = $xlsform->choiceLists()
                    ->with('choiceListEntries', function ($query) use ($xlsform) {
                        $query
                            ->whereHas('owner', function ($query) use ($xlsform) {
                                $query->where('id', $xlsform->owner->id);
                            })
                            ->orWhereNull('owner_id');
                    })
                    ->get();

                $entityValues = [];

                $siteData = ['site_root' => $siteData];
                $siteRepeatPath = '/survey/field_survey/sites';

                foreach ($schema as $schemaItem) {


                    // remove the repeat group path from the beginning of the schema item path
                    $path = Str::replaceFirst($siteRepeatPath, '', $schemaItem['path']);

                    // replace '/' with '.' to match the way Arr::get works with nested arrays
                    $path = Str::replace('/', '.', $path);
                    $path = 'site_root' . $path;

                    $value = Arr::get($siteData, $path, null);


                    if ($schemaItem['type'] != 'repeat' && $value !== null && $value != '' && !is_array($value)) {
                        $entityValues[] = EntityValue::make([
                            'dataset_variable_name' => $schemaItem['name'],
                            'value' => $value,
                        ]);


                        // for select_multiples, add binary/ boolean columns for each possible response
                        $odkLinkService = app()->make(OdkLinkService::class);
                        $booleanEntityValues = $odkLinkService->makeMultiSelectBooleans($siteEntity, $schemaItem, $choices, $value);
                        $entityValues = array_merge($entityValues, $booleanEntityValues);
                    } else {
                    }
                }

                $siteEntity->addValues(collect($entityValues));

            }

        }
    }

    // ******************** //

    // custom handling for irrigation data
    // to fill in irrigation percentage for 12 months in farm_survey_data record
    /** @return Collection<EntityValue> */
    public static function getIrrigationData(Submission $submission): Collection
    {
        // existence check for irrigation data in submission content
        if (!isset($submission->content['survey']['water']['irrigation_season_repeat'])) {
            return collect();
        }

        // initialise irrigation result, assume zero percentage irrigation for all months at the beginning
        $irrigationValues = new Collection;

        for ($i = 1; $i <= 12; $i++) {
            $irrigationValues['irrigation_percentage_month_' . $i] = EntityValue::make([
                'dataset_variable_name' => 'irrigation_percentage_month_' . $i,
                'value' => 0,
            ]);
        }

        // get irrigation data from submission JSON content
        $irrigationRepeats = $submission->content['survey']['water']['irrigation_season_repeat'];

        // handle irrigation repeat groups one by one, overwrite percentage for a month if it has higher percentage
        foreach ($irrigationRepeats as $irrigationRepeat) {
            $irrigationValues = SubmissionController::prepareIrrigationData($irrigationValues, $irrigationRepeat);
        }

        return $irrigationValues->values();

    }

    /** @return Collection<EntityValue> */
    public static function prepareIrrigationData(Collection $irrigationValues, array $irrigation): Collection
    {
        $irrigationPercentage = $irrigation['irrigation_percentage'];

        // do nothing if irrigation_percentage is null or "null"
        if ($irrigationPercentage == null || $irrigationPercentage == 'null') {
            return $irrigationValues;
        }

        $irrigationMonths = str_getcsv($irrigation['irrigation_months'], ' ');

        foreach ($irrigationMonths as $irrigationMonth) {
            // if a month is selected multiple times, take the higher value.
            if ($irrigationPercentage > $irrigationValues['irrigation_percentage_month_' . $irrigationMonth]['value']) {

                $irrigationValues['irrigation_percentage_month_' . $irrigationMonth]['value'] = $irrigationPercentage;
            }
        }

        return $irrigationValues;
    }


    // custom handling for seasonal_worker_seasons data
    // create records from nested repeat groups for seasonal_worker_seasons
    // /survey/income/labour/household_mm_labour/seasonal_workers/seasonal_workers_s
    /** @return Collection<array> */
    public static function handleSeasonalWorkersData(Submission $submission): Collection
    {

        // existence check for nested repeat group data in submission content
        if (!isset($submission->content['survey']['labour']['household_mm_labour']['seasonal_workers'])) {
            return collect();
        }

        return collect($submission->content['survey']['labour']['household_mm_labour']['seasonal_workers'])
            ->map(function (array $seasonalWorker): ?Collection {

                if (!isset($seasonalWorker['seasonal_workers_s'])) {
                    return null;
                }

                return collect($seasonalWorker['seasonal_workers_s'])
                    ->map(function (array $seasonalWorkerItem) use ($seasonalWorker) {
                        $seasonalWorkerItem['seasonal_labour_group_num'] = $seasonalWorker['seasonal_labour_group_num'];
                        $seasonalWorkerItem['seasonal_labour_group_name'] = $seasonalWorker['seasonal_labour_group_name'];
                        $seasonalWorkerItem['household_members'] = true;

                        return $seasonalWorkerItem;
                    });

            })
            ->filter()
            ->flatten(1);

    }

    // ******************** //

    // custom handling for seasonal_worker_seasons data
    // create records from nested repeat groups for seasonal_worker_seasons
    // /survey/income/labour/labourers/sesaonal_labourers/seasonal_labourers_s/
    /** @return Collection<<array> */
    public static function handleSeasonalLaboursData(Submission $submission): Collection
    {

        // existence check for nested repeat group data in submission content
        if (!isset($submission->content['survey']['labour']['labourers']['sesaonal_labourers'])) {
            return collect();
        }

        return collect($submission->content['survey']['labour']['labourers']['sesaonal_labourers'])
            ->map(function (array $seasonalLabour): ?Collection {
                if (!isset($seasonalLabour['seasonal_labourers_s'])) {
                    return null;
                }

                return collect($seasonalLabour['seasonal_labourers_s'])
                    ->map(function (array $seasonalLabourItem) use ($seasonalLabour) {
                        $seasonalLabourItem['seasonal_labour_group_num'] = $seasonalLabour['seasonal_labour_group_num'] ?? null;
                        $seasonalLabourItem['seasonal_labour_group_name'] = $seasonalLabour['seasonal_labour_group_name'] ?? $seasonalLabour['seasonal_labourer_group'];
                        $seasonalLabourItem['household_members'] = false;

                        // harmonise var names between "seasonal labour" and "seasonal labourer" sections
                        foreach ($seasonalLabourItem as $key => $value) {
                            if (Str::contains($key, 'seasonal_labourer_')) {

                                $newKey = Str::replace('seasonal_labourer_', 'seasonal_labour_', $key);
                                $seasonalLabourItem[$newKey] = $value;
                                unset($seasonalLabourItem[$key]);
                            }
                        }

                        return $seasonalLabourItem;
                    });

            })
            ->filter()
            ->flatten(1);
    }

    // custom handling for products data
    public static function handleProductsData(Submission $submission): Collection
    {

        // get farm_products
        if (!isset($submission->content['survey']['farm_characteristics']['farm_products'])) {
            return collect();
        }

        $farmProductsList = $submission->content['survey']['farm_characteristics']['farm_products'];

        // split farm products, handle them one by one
        return collect(str_getcsv($farmProductsList, ' '))
            ->map(function (string $farmProduct) use ($submission): ?array {

                if ($farmProduct === 'other') {
                    return null;
                }

                // special handling for "crops", change it from "crops" to "crop" before handling submission content to match odk form variable names
                if ($farmProduct === 'crops') {
                    $farmProduct = 'crop';
                }

                $useData = $submission->content['survey']['farm_characteristics']["{$farmProduct}_use"];
                $salesData = $submission->content['survey']['farm_characteristics']["{$farmProduct}_sales_grp"];

                $result = [
                    'product_name' => $farmProduct,
                ];

                foreach ($useData as $key => $value) {
                    $newKey = Str::replace($farmProduct . '_', '', $key);
                    $result[$newKey] = $value;
                }

                foreach ($salesData as $key => $value) {
                    $newKey = Str::replace($farmProduct . '_', '', $key);
                    $result[$newKey] = $value;
                }

                return $result;
            })
            ->filter();
    }

    public static function handleOtherProductData(Submission $submission): Collection
    {
        if (!isset($submission->content['survey']['farm_characteristics']['other_product_use_sales'])) {
            return collect();
        }

        return collect($submission->content['survey']['farm_characteristics']['other_product_use_sales'])
            ->map(function (array $otherProduct): ?array {

                // name is included in the other_product_use_sales repeat group, so we don't need to set it.
                $result = [];

                foreach ($otherProduct as $key => $value) {
                    $newKey = Str::replace('other_prod_', '', $key);
                    $result[$newKey] = $value;
                }

                return $result;
            })
            ->filter();
    }

    public static function handleLocationData(Submission $submission, Team $team): void
    {

        $locationData = $submission->content['context']['location'];

        /** @var Collection<LocationLevel> $locationLevels */
        $locationLevels = $team->locationLevels;

        /** @var ?Location $parentLocation */
        $parentLocation = null;


        // loop through location levels; find or create locations as needed;
        foreach ($locationLevels as $level) {

            $odkName = Str::of($level->name)->slug('_')->singular();

            /** @var ?Location $location */
            $location = $level->locations()
                ->where('code', $locationData["{$odkName}_id"])
                ->first();

            if ($location) {
                if ($level->has_farms) {
                    // location exists; use it as parent for next level
                    $parentLocation = $location;
                }
            } else {

                // if _id from form is -999, then it's a new entry with no pre-defined code;
                if ($locationData["{$odkName}_id"] == '-999') {

                    $code = Str::slug($locationData["{$odkName}_name"]);
                } else {
                    // in this case the location should exist, but doesn't for some reason; we can re-create it from the odk data
                    $code = $locationData["{$odkName}_id"];
                }

                $newLocation = $level->locations()->create([
                    'code' => $code,
                    'name' => $locationData["{$odkName}_name"],
                    'parent_id' => $parentLocation?->id,
                    'owner_id' => $team->id,
                ]);

                if ($level->has_farms) {
                    $parentLocation = $newLocation;
                }
            }
        }

        $farmId = $submission->content['context']['farm_location']['farm_id'];

        // Key identifier is 'farm_id' in form; farm_code in database, (code is unique at team-level; id is unique at database level).

        $farm = Farm::where('location_id', $parentLocation->id)
            ->where('team_code', $submission->content['context']['farm_location']['farm_id'])
            ->first();

        if ($farm) {
            $submission->primaryDataSubject()->associate($farm);
        } else {

            // farm is not found; create it!
            // if _id from form is -999, then it's a new entry with no pre-defined code;
            if ($farmId == '-999') {

                $code = Str::slug($submission->content['context']['farm_location']['farm_name']);
            } else {
                // in this case the location should exist, but doesn't for some reason; we can re-create it from the odk data
                $code = $farmId;
            }
            $farm = $parentLocation->farms()
                ->create([
                    'owner_id' => $team->id,
                    'team_code' => $farmId,
                    'identifiers' => ['name' => $submission->content['context']['farm_location']['farm_name']],
                    'latitude' => $submission->content['context']['location_confirm']['gps']['coordinates'][0],
                    'longitude' => $submission->content['context']['location_confirm']['gps']['coordinates'][1],
                    'altitude' => $submission->content['context']['location_confirm']['gps']['coordinates'][2],
                    'accuracy' => $submission->content['context']['location_confirm']['gps']['properties']['accuracy'],
                ]);

            $submission->primaryDataSubject()->associate($farm);
        }

        $submission->save();
    }
}
