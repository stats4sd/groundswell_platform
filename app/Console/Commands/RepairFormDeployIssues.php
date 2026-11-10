<?php

namespace App\Console\Commands;

use App\Services\XlsformModules\FarmInfoModuleBuilder;
use App\Services\XlsformModules\LocationsModuleBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

// One-off data repair for the staging issues of 2026-10-26, see docs/plans/2026-10-26-odk-form-deploy-fixes.md.
// R3 repairs the damage of "Replace XLSForm", see docs/plans/2026-11-09-prune-stale-rows-on-replace-xlsform.md.
class RepairFormDeployIssues extends Command
{
    protected $signature = 'odk:repair-form-deploy-issues
        {--apply : Write the changes. Without it the command only reports what it would do}
        {--only= : Run a single repair: "labels" (R1), "modules" (R2) or "versions" (R3)}';

    protected $description = 'Refill missing choice labels (R1), remove module versions that belong to another template from forms (R2), and repair emptied team module versions and missing modules (R3)';

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['labels', 'modules', 'versions'], true)) {
            $this->error('--only must be "labels", "modules" or "versions".');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->comment($apply
            ? 'APPLY: the changes will be written.'
            : 'DRY RUN: nothing will be changed. Run again with --apply to make the changes.');

        if ($only === null || $only === 'labels') {
            $this->repairLabels($apply);
        }

        if ($only === null || $only === 'modules') {
            $this->repairModules($apply);
        }

        if ($only === null || $only === 'versions') {
            $this->repairVersions($apply);
        }

        $this->comment('Done.');

        return self::SUCCESS;
    }

    // R1: AddMissingChoiceListStrings copies labels into same-named lists whose entries have none.
    private function repairLabels(bool $apply): void
    {
        $this->newLine();
        $this->info('R1: global choice entries without any label');

        XlsformTemplate::all()->each(function (XlsformTemplate $template) use ($apply) {
            $before = $this->unlabelledGlobalEntriesByList($template);

            $this->line("Template {$template->id} ({$template->title}): {$before->count()} list(s) with unlabelled global entries");

            if ($before->isEmpty()) {
                return;
            }

            $this->table(['choice_list_id', 'list_name', 'unlabelled entries'], $before->values()->all());

            if (! $apply) {
                return;
            }

            AddMissingChoiceListStrings::dispatchSync($template);

            $after = $this->unlabelledGlobalEntriesByList($template);

            $this->line("  after: {$after->count()} list(s) still have unlabelled global entries (no labelled same-named list to copy from)");
        });
    }

    /** @return Collection<int|string, array{int, mixed, int<0, max>}> [choice list id, list name, count], keyed by choice list id */
    private function unlabelledGlobalEntriesByList(XlsformTemplate $template): Collection
    {
        return ChoiceListEntry::withoutGlobalScopes()
            ->with('choiceList:id,list_name')
            ->whereNull('owner_id')
            ->whereIn('choice_list_id', $template->choiceLists()->select('choice_lists.id'))
            ->whereDoesntHave('languageStrings')
            ->get()
            ->groupBy('choice_list_id')
            ->map(fn (Collection $entries, int $choiceListId) => [$choiceListId, $entries->first()->choiceList->list_name, $entries->count()]);
    }

    // R2: detach selected module versions whose module belongs to a different template than the form,
    // then let syncWithTemplate() attach the default version for any module the form is left without.
    private function repairModules(bool $apply): void
    {
        $this->newLine();
        $this->info('R2: module versions that belong to another template');

        $changed = 0;

        Xlsform::with(['xlsformTemplate.xlsformModules', 'xlsformModuleVersions.xlsformModule'])
            ->orderBy('id')
            ->get()
            ->each(function (Xlsform $xlsform) use ($apply, &$changed) {
                $wrongVersions = $xlsform->xlsformModuleVersions->filter(
                    fn (XlsformModuleVersion $version) => $version->xlsformModule !== null
                        && $version->xlsformModule->xlsform_template_id !== $xlsform->xlsform_template_id
                );

                $this->warnAboutDuplicateVersions($xlsform, $wrongVersions);

                if ($wrongVersions->isEmpty()) {
                    return;
                }

                $changed++;

                $remainingModuleIds = $xlsform->xlsformModuleVersions
                    ->diff($wrongVersions)
                    ->pluck('xlsform_module_id');

                $modulesToRefill = $xlsform->xlsformTemplate->xlsformModules
                    ->reject(fn (XlsformModule $module) => $remainingModuleIds->contains($module->id));

                $this->line("Form {$xlsform->id} ({$xlsform->title}, owner {$xlsform->owner_id}, template {$xlsform->xlsform_template_id})");

                $this->table(
                    ['version id', 'name', 'module id', 'belongs to template'],
                    $wrongVersions->map(fn (XlsformModuleVersion $version) => [
                        $version->id,
                        $version->name,
                        $version->xlsform_module_id,
                        $version->xlsformModule->xlsform_template_id,
                    ])->values()->all(),
                );

                $this->line('  modules that get their default version if missing: '.$modulesToRefill->pluck('name')->implode(', '));

                if (! $apply) {
                    return;
                }

                DB::transaction(function () use ($xlsform, $wrongVersions) {
                    $xlsform->xlsformModuleVersions()->detach($wrongVersions->pluck('id')->all());
                    $xlsform->load('xlsformModuleVersions');
                    $xlsform->syncWithTemplate();
                    $xlsform->updateQuietly(['draft_needs_update' => true]);
                });

                $this->line("  detached {$wrongVersions->count()} version(s) and marked the form for a draft update");
            });

        $this->line($changed === 0 ? 'No form selects a module version of another template.' : "{$changed} form(s) ".($apply ? 'repaired.' : 'would be repaired.'));
    }

    // Reported only: choosing between two versions of the same module is not something this command should guess.
    private function warnAboutDuplicateVersions(Xlsform $xlsform, Collection $wrongVersions): void
    {
        $duplicates = $xlsform->xlsformModuleVersions
            ->diff($wrongVersions)
            ->whereNotNull('xlsform_module_id')
            ->groupBy('xlsform_module_id')
            ->filter(fn (Collection $versions) => $versions->count() > 1);

        foreach ($duplicates as $moduleId => $versions) {
            $this->warn("Form {$xlsform->id} selects {$versions->count()} versions of module {$moduleId} (".$versions->pluck('id')->implode(', ').'): not changed, check by hand.');
        }
    }

    // R3: "Replace XLSForm" used to delete the survey rows of team-owned module versions, and never added the modules
    // the new file introduced to the existing team forms. Repair both.
    private function repairVersions(bool $apply): void
    {
        $this->newLine();
        $this->info('R3: emptied team-owned module versions and missing modules');

        $this->repairEmptiedVersions($apply);
        $this->repairMissingModules($apply);
    }

    private function repairEmptiedVersions(bool $apply): void
    {
        $emptiedVersions = $this->emptiedTeamVersions();

        if ($emptiedVersions->isEmpty()) {
            $this->line('No team-owned module version has lost its survey rows.');

            return;
        }

        $rebuiltLocalVersions = [];

        $emptiedVersions->each(function (XlsformModuleVersion $version) use ($apply, &$rebuiltLocalVersions) {
            $formCount = DB::table('selected_xlsform_module_versions')
                ->where('xlsform_module_version_id', $version->id)
                ->count();

            $this->line("Version {$version->id} ({$version->name}), module {$version->xlsformModule->name}, team {$version->owner_id}, selected by {$formCount} form(s)");

            if (str_starts_with($version->name, 'Local ')) {
                $this->rebuildLocalVersion($version, $apply, $rebuiltLocalVersions);

                return;
            }

            $this->resetClonedVersion($version, $apply);
        });
    }

    /** @return Collection<int, XlsformModuleVersion> team-owned versions with no survey rows whose module has rows in its default version */
    private function emptiedTeamVersions(): Collection
    {
        return XlsformModuleVersion::query()
            ->whereNotNull('owner_id')
            ->whereNotNull('xlsform_module_id')
            ->where('is_default', false)
            ->withCount('surveyRows')
            ->with('xlsformModule.defaultXlsformVersion')
            ->orderBy('id')
            ->get()
            ->filter(fn (XlsformModuleVersion $version) => $version->survey_rows_count === 0)
            ->filter(function (XlsformModuleVersion $version) {
                $defaultVersionId = $version->xlsformModule?->defaultXlsformVersion?->id;

                return $defaultVersionId !== null
                    && DB::table('survey_rows')->where('xlsform_module_version_id', $defaultVersionId)->exists();
            })
            ->values();
    }

    // The builders fill every Local version of the team in one go (one per template), so each team and module is rebuilt once.
    /** @param array<string, true> $rebuiltLocalVersions */
    private function rebuildLocalVersion(XlsformModuleVersion $version, bool $apply, array &$rebuiltLocalVersions): void
    {
        $builder = match ($version->xlsformModule->name) {
            'locations' => LocationsModuleBuilder::class,
            'farm info' => FarmInfoModuleBuilder::class,
            default => null,
        };

        if ($builder === null) {
            $this->warn("  no builder knows module \"{$version->xlsformModule->name}\": not changed, check by hand.");

            return;
        }

        // The owner is resolved through the Team model, so a deleted (soft-deleted) team is not found: nothing to rebuild for it.
        if ($version->owner === null) {
            $this->warn("  team {$version->owner_id} was not found (deleted?): not changed.");

            return;
        }

        $this->line('  rebuild the Local version with '.class_basename($builder).' and mark the team\'s forms of this template for a draft update');

        if (! $apply) {
            return;
        }

        $rebuildKey = "{$version->owner_id}:{$version->xlsformModule->name}";

        if (! isset($rebuiltLocalVersions[$rebuildKey])) {
            $builder::populate($version->owner);
            $rebuiltLocalVersions[$rebuildKey] = true;
        }

        Xlsform::query()
            ->where('owner_id', $version->owner_id)
            ->where('xlsform_template_id', $version->xlsformModule->xlsform_template_id)
            ->get()
            ->each(fn (Xlsform $xlsform) => $xlsform->updateQuietly(['draft_needs_update' => true]));

        $this->line('  rebuilt.');
    }

    // The team's own edits of this module went with its rows, so there is nothing left to keep: use the global version again.
    // The Informed Consent / HDDS page clones it again the next time the team opens it.
    private function resetClonedVersion(XlsformModuleVersion $version, bool $apply): void
    {
        $defaultVersion = $version->xlsformModule->defaultXlsformVersion;

        $hasTeamEntries = ChoiceListEntry::withoutGlobalScopes()
            ->whereIn('choice_list_id', $version->choiceLists()->select('choice_lists.id'))
            ->whereNotNull('owner_id')
            ->exists();

        if ($hasTeamEntries) {
            $this->warn('  the copy still holds choice entries added by the team: not changed, check by hand.');

            return;
        }

        $this->line("  switch the forms back to the global version {$defaultVersion->id} at the same position, delete the empty copy and mark the forms for a draft update");

        if (! $apply) {
            return;
        }

        DB::transaction(function () use ($version, $defaultVersion) {
            DB::table('selected_xlsform_module_versions')
                ->where('xlsform_module_version_id', $version->id)
                ->get()
                ->each(function (object $selection) use ($defaultVersion) {
                    $xlsform = Xlsform::withoutGlobalScopes()->find($selection->xlsform_id);

                    $xlsform->xlsformModuleVersions()->syncWithoutDetaching([$defaultVersion->id => ['order' => $selection->order]]);
                    $xlsform->updateQuietly(['draft_needs_update' => true]);
                });

            // Not every database has a foreign key from the selections to the version (the local one did not), so nothing may rely on a cascade.
            DB::table('selected_xlsform_module_versions')
                ->where('xlsform_module_version_id', $version->id)
                ->delete();

            // Through the models, so the language strings of the copied entries (no foreign key either) go with them.
            $version->choiceLists()->get()->each(fn (ChoiceList $choiceList) => $choiceList->delete());

            $version->delete();
        });

        $this->line('  reset.');
    }

    // Modules the new file added reach existing forms only through syncWithTemplate(), which nothing triggered after a Replace.
    private function repairMissingModules(bool $apply): void
    {
        $changed = 0;

        Xlsform::with(['xlsformTemplate.xlsformModules.defaultXlsformVersion', 'xlsformModuleVersions'])
            ->orderBy('id')
            ->get()
            ->each(function (Xlsform $xlsform) use ($apply, &$changed) {
                $missingModules = $xlsform->xlsformTemplate->xlsformModules
                    ->filter(fn (XlsformModule $module) => $module->defaultXlsformVersion !== null)
                    ->filter(fn (XlsformModule $module) => $xlsform->xlsformModuleVersions->doesntContain('xlsform_module_id', $module->id));

                if ($missingModules->isEmpty()) {
                    return;
                }

                $changed++;

                $this->line("Form {$xlsform->id} ({$xlsform->title}, owner {$xlsform->owner_id}, template {$xlsform->xlsform_template_id}) is missing: ".$missingModules->pluck('name')->implode(', '));

                if (! $apply) {
                    return;
                }

                DB::transaction(function () use ($xlsform) {
                    $xlsform->syncWithTemplate();
                    $xlsform->updateQuietly(['draft_needs_update' => true]);
                });

                $this->line('  attached and marked for a draft update.');
            });

        $this->line($changed === 0 ? 'No form is missing a module of its template.' : "{$changed} form(s) ".($apply ? 'repaired.' : 'would be repaired.'));
    }
}
