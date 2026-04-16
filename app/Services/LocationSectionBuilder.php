<?php

namespace App\Services;

use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class LocationSectionBuilder
{

    public static function createCustomLocationModuleVersion(Team $team): void
    {
        $defaultLocationModuleId = XlsformModuleVersion::firstWhere('name', 'Global location')->id;

        $locationModuleVersion = $team->xlsformModuleVersions()
            ->updateOrCreate([
                'xlsform_module_id' => $defaultLocationModuleId,
                'name' => "{$team->name} Locations",
            ]);

        // Check that the team has locations + farms. If not, ensure we're using the default / test module for locations.
        if($team->locations()->count() === 0 || $team->farms()->count() === 0) {
            $team->xlsforms->each(function (Xlsform $xlsform) use ($defaultLocationModuleId, $locationModuleVersion) {
                $xlsform->xlsformModuleVersions()->detach($locationModuleVersion->id);
                $xlsform->xlsformModuleVersions()->sync([$locationModuleVersion->id => ['order' => 2]], false);
            });

            return;
        }

        static::createCustomSurveyRows($locationModuleVersion);
        static::createCustomChoiceLists($locationModuleVersion);

        $team->xlsforms->each(function (Xlsform $xlsform) use ($defaultLocationModuleId, $locationModuleVersion) {

            $xlsform->xlsformModuleVersions()->detach($defaultLocationModuleId);
            $xlsform->xlsformModuleVersions()->sync([$locationModuleVersion->id => ['order' => 2]], false);
        });

    }

    public static function createCustomSurveyRows(XlsformModuleVersion $locationModuleVersion): void
    {
        $team = $locationModuleVersion->owner;

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'row_number' => 1,
            ],
            [
                'type' => 'begin_group',
                'name' => 'location',
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'row_number' => 2,
            ],
            [
                'type' => 'note',
                'name' => 'location_note',
                'properties' => collect([
                    'label::English (en)' => 'Please select the correct location using the following questions.',
                    'hint::English (en)' => 'This will filter the list of farms to make it easier to select the correct farm',
                ]),
            ],
        );

        $levelCount = 0;

        // TODO: use parent/children relationship to work through location levels in a sensible order to handle more complex setups.
        foreach ($team->locationLevels as $locationLevel) {

            $parentLevel = $locationLevel->parent;

            $locationModuleVersion->surveyRows()->updateOrCreate(
                [
                    'name' => "group_location_level_{$locationLevel->slug}",
                    'type' => 'begin_group',
                ],
                [
                    'row_number' => $levelCount * 5 + 3,
                ]);

            $locationModuleVersion->surveyRows()->updateOrCreate(
                [
                    'name' => "{$locationLevel->slug}_id",
                    'type' => "select_one {$locationLevel->slug}",
                ],
                [
                    'required' => true,
                    'row_number' => $levelCount * 5 + 4,
                    'choice_filter' => $parentLevel ? 'location=${' . $parentLevel->slug . '_id} or location=-999' : '',
                    'properties' => collect([
                        'label::English (en)' => "{$locationLevel->name}",
                    ]),
                ]);

            $locationModuleVersion->surveyRows()->updateOrCreate(
                [
                    'name' => "new_{$locationLevel->slug}",
                    'type' => 'text',
                ],
                [
                    'required' => true,
                    'relevant' => '${' . $locationLevel->slug . '_id} = -999',
                    'properties' => collect([
                        'label::English (en)' => "Enter the name of the {$locationLevel->name}",
                    ]),
                    'row_number' => $levelCount * 5 + 5,
                ]);

            $locationModuleVersion->surveyRows()->updateOrCreate(
                [
                    'name' => "{$locationLevel->slug}_name",
                    'type' => 'calculate',
                ],
                [
                    'calculation' => 'coalesce(${new_' . $locationLevel->slug . '}, jr:choice-name(${' . $locationLevel->slug . '_id}, \'${' . $locationLevel->slug . '_id}\'))',
                    'row_number' => $levelCount * 5 + 6,
                ]);

            $locationModuleVersion->surveyRows()->updateOrCreate(
                [
                    'name' => "group_location_level_{$locationLevel->slug}",
                    'type' => 'end_group',
                ],
                [
                    'row_number' => $levelCount * 5 + 7,
                ]);

            $levelCount++;
        }

        // TODO: make this work with multiple possible levels for farms (e.g. a survey with villages as the rural low level and clusters as the urban one.)
        $farmLevel = $team->locationLevels->filter(fn(LocationLevel $locationLevel) => $locationLevel->has_farms)->first();

        $confirmationText = "You have selected the following farm:" . PHP_EOL;

        foreach ($team->locationLevels as $locationLevel) {

            $confirmationText .= $locationLevel->name . ': ${' . $locationLevel->slug . '_name},' . PHP_EOL;
        }

        $confirmationText .= 'Farm ID: ${farm_id}' . PHP_EOL . 'Farm Name: ${farm_name} ' . PHP_EOL . PHP_EOL . 'Is this the correct farm?';

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'location',
                'type' => 'end_group',
            ],
            [
                'row_number' => $levelCount * 5 + 8,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'final_location_id',
                'type' => 'calculate',
            ],
            [
                'calculation' => '${' . $farmLevel->slug . '_id}',
                'row_number' => $levelCount * 5 + 9,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'farm_location',
                'type' => 'begin_group',
            ],
            [
                'appearance' => 'field-list',
                'row_number' => $levelCount * 5 + 10,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'farm_id',
                'type' => 'select_one farms',
            ],
            [
                'required' => true,
                'relevant' => '${final_location_id} != -999',
                'choice_filter' => 'location=${' . $farmLevel->slug . '_id} or location=-999',
                'row_number' => $levelCount * 5 + 11,
                'properties' => collect([
                    'label::English (en)' => "Please select the farm you are visiting",
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'new_farm',
                'type' => 'text',
            ],
            [
                'required' => true,
                'row_number' => $levelCount * 5 + 12,
                'relevant' => '${farm_id}= -999',
                'properties' => collect([
                    'label::English (en)' => "Please provide a name for the farm",
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'farm_name',
                'type' => 'calculate',
            ],
            [
                'calculation' => 'coalesce(${new_farm}, jr:choice-name(${farm_id}, \'${farm_id}\'))',
                'row_number' => $levelCount * 5 + 13,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'farm_location',
                'type' => 'end_group',
            ],
            [
                'row_number' => $levelCount * 5 + 14,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'location_confirm',
                'type' => 'begin_group',
            ],
            [
                'row_number' => $levelCount * 5 + 15,
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'confirm_farm',
                'type' => 'select_one yn',
            ],
            [
                'required' => true,
                'row_number' => $levelCount * 5 + 16,
                'properties' => collect([
                    'label::English (en)' => $confirmationText,
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'reselect_farm_note',
                'type' => 'note',
            ],
            [
                'required' => true,
                'relevant' => '${confirm_farm} = 0',
                'row_number' => $levelCount * 5 + 17,
                'properties' => collect([
                    'label::English (en)' => "Please go back to the farm selection question and select the correct farm.",
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'gps',
                'type' => 'geopoint',
            ],
            [
                'row_number' => $levelCount * 5 + 18,
                'properties' => collect([
                    'label::English (en)' => "**Please take a GPS point**",
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'gps_location_alt',
                'type' => 'text',
            ],
            [
                'row_number' => $levelCount * 5 + 19,
                'properties' => collect([
                    'label::English (en)' => "**If the GPS point you just took was not at the location of the household, please specify where it was taken.",
                ]),
            ]);

        $locationModuleVersion->surveyRows()->updateOrCreate(
            [
                'name' => 'location_confirm',
                'type' => 'end_group',
            ],
            [
                'row_number' => $levelCount * 5 + 20,
            ]);

    }

    public static function createCustomChoiceLists(XlsformModuleVersion $locationModuleVersion): void
    {
        $team = $locationModuleVersion->owner;

        foreach ($team->locationLevels as $locationLevel) {

            $choiceList = ChoiceList::firstOrCreate([
                'xlsform_module_version_id' => $locationModuleVersion->id,
                'list_name' => $locationLevel->slug,
            ]);

            foreach ($locationLevel->locations as $location) {

                // not using relationship so we can use the choice_list_id as a upsert prop
                ChoiceListEntry::updateOrCreate(
                    [
                        'name' => $location->id,
                        'choice_list_id' => $choiceList->id,
                    ],
                    [
                        'owner_id' => $team->id,
                        'cascade_filter' => $location->parent->id ?? null,
                        'properties' => $location->parent ? collect([
                            'location' => $location->parent->id,
                            'label::English (en)' => $location->name,
                        ]) : [],
                    ]
                );
            }

            // add the na / -999 entries
            ChoiceListEntry::updateOrCreate(
                [
                    'name' => '-999',
                    'choice_list_id' => $choiceList->id,
                ],
                [
                    'owner_id' => $team->id,
                    'properties' => collect([
                        'label::English (en)' => $locationLevel->name . ' not found in list',
                        'location' => '-999',
                    ]),
                ]
            );
        }

        $farmChoiceList = ChoiceList::firstOrCreate([
            'xlsform_module_version_id' => $locationModuleVersion->id,
            'list_name' => 'farms',
        ]);
        foreach ($team->farms as $farm) {
            ChoiceListEntry::updateOrCreate(
                [
                    'name' => $farm->id,
                    'choice_list_id' => $farmChoiceList->id,
                ],
                [
                    'owner_id' => $team->id,
                    'cascade_filter' => $farm->location->id,
                    'properties' => collect([
                        'location' => $farm->location->id,
                        'label::English (en)' => $farm->identifying_attribute,
                    ]),
                ]
            );
        }

        // add the na / -999 entries
        ChoiceListEntry::updateOrCreate(
            [
                'name' => '-999',
                'choice_list_id' => $farmChoiceList->id,
            ],
            [
                'owner_id' => $team->id,
                'properties' => collect([
                    'location' => '-999',
                    'label::English (en)' => 'farm not found in list',
                ]),
            ]
        );

    }

}
