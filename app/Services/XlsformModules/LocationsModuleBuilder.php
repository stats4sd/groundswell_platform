<?php

namespace App\Services\XlsformModules;

use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class LocationsModuleBuilder
{
    public static function populate(Team $team): void
    {
        $moduleVersion = XlsformModuleVersion::firstOrCreate([
            'owner_id' => $team->id,
            'name' => 'Local locations',
        ]);

        $levels = static::orderedLevels($team);

        static::buildSurveyRows($moduleVersion, $team, $levels);
        static::buildChoiceLists($moduleVersion, $team, $levels);
    }

    /** @return array<int, LocationLevel> keyed by 1-indexed position, root first */
    protected static function orderedLevels(Team $team): array
    {
        $byPos = [];

        foreach ($team->locationLevels()->get() as $level) {
            $byPos[$level->getPos()] = $level;
        }

        ksort($byPos);

        return $byPos;
    }

    /** @param array<int, LocationLevel> $levels */
    protected static function buildSurveyRows(XlsformModuleVersion $moduleVersion, Team $team, array $levels): void
    {
        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'location', 'type' => 'begin_group'],
            ['row_number' => 1],
        );

        $rowNumber = 2;

        foreach ($levels as $pos => $level) {
            $parentPos = $pos - 1;
            $choiceFilter = $parentPos >= 1 ? 'filter=${loc'.$parentPos.'}' : '';

            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => 'loc'.$pos, 'type' => 'select_one loc'.$pos],
                [
                    'required' => true,
                    'choice_filter' => $choiceFilter,
                    'properties' => collect(static::labelProperties($team, $level->name)),
                    'row_number' => $rowNumber++,
                ],
            );

            $moduleVersion->surveyRows()->updateOrCreate(
                ['name' => 'loc'.$pos.'_name', 'type' => 'calculate'],
                [
                    'calculation' => 'jr:choice-name(${loc'.$pos.'}, \'${loc'.$pos.'}\')',
                    'row_number' => $rowNumber++,
                ],
            );
        }

        $moduleVersion->surveyRows()->updateOrCreate(
            ['name' => 'location', 'type' => 'end_group'],
            ['row_number' => $rowNumber++],
        );

        static::deleteStaleSurveyRows($moduleVersion, count($levels));
    }

    protected static function deleteStaleSurveyRows(XlsformModuleVersion $moduleVersion, int $maxPos): void
    {
        $staleIds = $moduleVersion->surveyRows()
            ->where('name', 'like', 'loc%')
            ->get()
            ->filter(function ($row) use ($maxPos) {
                if (! preg_match('/^loc(\d+)(_name)?$/', $row->name, $matches)) {
                    return false;
                }

                return (int) $matches[1] > $maxPos;
            })
            ->pluck('id');

        $moduleVersion->surveyRows()->whereIn('id', $staleIds)->delete();
    }

    /** @param array<int, LocationLevel> $levels */
    protected static function buildChoiceLists(XlsformModuleVersion $moduleVersion, Team $team, array $levels): void
    {
        foreach ($levels as $pos => $level) {
            $choiceList = ChoiceList::firstOrCreate([
                'xlsform_module_version_id' => $moduleVersion->id,
                'list_name' => 'loc'.$pos,
            ]);

            foreach ($level->locations as $location) {
                ChoiceListEntry::updateOrCreate(
                    [
                        'name' => $location->id,
                        'choice_list_id' => $choiceList->id,
                    ],
                    [
                        'owner_id' => $team->id,
                        'cascade_filter' => $location->parent_id,
                        'properties' => collect(static::labelProperties($team, $location->name))
                            ->put('filter', $location->parent_id),
                    ],
                );
            }
        }

        static::deleteStaleChoiceLists($moduleVersion, count($levels));
    }

    protected static function deleteStaleChoiceLists(XlsformModuleVersion $moduleVersion, int $maxPos): void
    {
        $moduleVersion->choiceLists()
            ->where('list_name', 'like', 'loc%')
            ->get()
            ->filter(function (ChoiceList $choiceList) use ($maxPos) {
                if (! preg_match('/^loc(\d+)$/', $choiceList->list_name, $matches)) {
                    return false;
                }

                return (int) $matches[1] > $maxPos;
            })
            ->each(fn (ChoiceList $choiceList) => $choiceList->delete());
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
