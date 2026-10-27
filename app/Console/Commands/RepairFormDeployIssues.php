<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

// One-off data repair for the staging issues of 2026-10-26, see docs/plans/2026-10-26-odk-form-deploy-fixes.md.
class RepairFormDeployIssues extends Command
{
    protected $signature = 'odk:repair-form-deploy-issues
        {--apply : Write the changes. Without it the command only reports what it would do}
        {--only= : Run a single repair: "labels" (R1) or "modules" (R2)}';

    protected $description = 'Refill missing choice labels (R1) and remove module versions that belong to another template from forms (R2)';

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['labels', 'modules'], true)) {
            $this->error('--only must be "labels" or "modules".');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');

        $this->comment($apply
            ? 'APPLY: the changes will be written.'
            : 'DRY RUN: nothing will be changed. Run again with --apply to make the changes.');

        if ($only !== 'modules') {
            $this->repairLabels($apply);
        }

        if ($only !== 'labels') {
            $this->repairModules($apply);
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
}
