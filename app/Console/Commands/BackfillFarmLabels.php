<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\OdkFarmEntityService;
use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;

class BackfillFarmLabels extends Command
{
    protected $signature = 'odk:backfill-farm-labels
        {--team=* : Only these team ids. Defaults to every team with an ODK project}
        {--name-property= : Sanitized name of the entity property holding the farmer name. Saved on the team. Defaults to the name already saved on the team}
        {--apply : Write the changes. Without it the command only reports what it would do}';

    protected $description = 'Relabel existing farm entities on ODK Central as "ID: {code} / Name: {name}", using the team\'s farmer name property';

    public function handle(OdkFarmEntityService $service): int
    {
        $apply = (bool) $this->option('apply');

        $this->comment($apply
            ? 'APPLY: the changes will be written.'
            : 'DRY RUN: nothing will be changed. Run again with --apply to make the changes.');

        $teamIds = array_filter((array) $this->option('team'));

        $teams = Team::query()
            ->when($teamIds !== [], fn ($query) => $query->whereIn('id', $teamIds))
            ->orderBy('id')
            ->get()
            ->filter(fn (Team $team) => $team->odkProject()->exists());

        $totals = ['updated' => 0, 'update' => 0, 'unchanged' => 0, 'no_name' => 0, 'failed' => 0];
        $skippedTeams = 0;

        foreach ($teams as $team) {
            $this->info("Team {$team->id} ({$team->name})");

            $nameProperty = $this->option('name-property') ?: $team->farm_name_property;

            if (! $nameProperty) {
                $this->line('  skipped: no farmer name property (pass --name-property)');
                $skippedTeams++;

                continue;
            }

            $dataset = Dataset::where('name', OdkFarmEntityService::LOCAL_DATASET_NAME.'_'.$team->id)->where('owner_id', $team->id)->first();

            if ($dataset === null || ! $dataset->variables()->where('name', $nameProperty)->exists()) {
                $this->error("  skipped: '{$nameProperty}' is not a property of this team's farm dataset");
                $skippedTeams++;

                continue;
            }

            if ($apply && $team->farm_name_property !== $nameProperty) {
                $team->update(['farm_name_property' => $nameProperty]);
            }

            $results = $service->relabelFarms($team, $nameProperty, $apply);

            $changes = collect($results)->whereIn('status', ['update', 'updated', 'failed']);

            if ($changes->isNotEmpty()) {
                $this->table(
                    ['farm code', 'current label', 'new label', 'status'],
                    $changes->map(fn (array $result) => [$result['code'], $result['current'], $result['new'], $result['status'].(isset($result['error']) ? ': '.$result['error'] : '')])->values()->all(),
                );
            }

            foreach (collect($results)->countBy('status') as $status => $count) {
                $totals[$status] += $count;
            }
        }

        $verb = $apply ? 'updated' : 'would be updated';
        $changed = $apply ? $totals['updated'] : $totals['update'];

        $this->comment("{$changed} {$verb}, {$totals['unchanged']} unchanged, {$totals['no_name']} without a name, {$totals['failed']} failed, {$skippedTeams} team(s) skipped.");

        return $totals['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
