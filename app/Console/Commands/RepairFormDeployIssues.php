<?php

namespace App\Console\Commands;

use App\Services\XlsformModules\FarmInfoModuleBuilder;
use App\Services\XlsformModules\LocationsModuleBuilder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stats4sd\FilamentOdkLink\Jobs\AddMissingChoiceListStrings;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
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
        {--only= : Run a single repair: "labels" (R1), "modules" (R2), "versions" (R3) or "ends" (R4, which is never part of a full run)}
        {--expect= : With --only=ends --apply: the number of strings the dry run reported. Nothing is deleted when the count differs}';

    protected $description = 'Refill missing choice labels (R1), remove module versions that belong to another template from forms (R2), repair emptied team module versions and missing modules (R3), and remove the team strings of end group rows (R4)';

    public function handle(): int
    {
        $only = $this->option('only');

        if ($only !== null && ! in_array($only, ['labels', 'modules', 'versions', 'ends'], true)) {
            $this->error('--only must be "labels", "modules", "versions" or "ends".');

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

        if ($only === 'ends' && ! $this->repairEndRows($apply)) {
            return self::FAILURE;
        }

        $this->comment('Done.');

        return self::SUCCESS;
    }

    // R1: AddMissingChoiceListStrings copies labels into same-named lists whose entries have none, and the default-locale
    // strings into global entries that kept only their team strings.
    private function repairLabels(bool $apply): void
    {
        $this->newLine();
        $this->info('R1: global choice entries without any label or without default-locale strings');

        XlsformTemplate::all()->each(function (XlsformTemplate $template) use ($apply) {
            $unlabelled = $this->unlabelledGlobalEntries($template);
            $withoutDefault = $this->globalEntriesWithoutDefaultStrings($template);

            $unlabelledByList = $this->countByList($unlabelled);
            $withoutDefaultByList = $this->countByList($withoutDefault);

            $this->line("Template {$template->id} ({$template->title}): {$unlabelledByList->count()} list(s) with unlabelled global entries");

            if ($unlabelledByList->isNotEmpty()) {
                $this->table(['choice_list_id', 'list_name', 'unlabelled entries'], $unlabelledByList->values()->all());
            }

            $this->line("Template {$template->id} ({$template->title}): {$withoutDefaultByList->count()} list(s) with {$withoutDefault->count()} global entries without default-locale strings");

            if ($withoutDefaultByList->isNotEmpty()) {
                $this->table(['choice_list_id', 'list_name', 'entries without default strings'], $withoutDefaultByList->values()->all());
            }

            $outsideDefaultVersions = $this->globalEntriesWithoutDefaultStringsOutsideDefaultVersions($template);

            if ($outsideDefaultVersions->isNotEmpty()) {
                $this->warn("  {$outsideDefaultVersions->count()} more global entries in {$this->countByList($outsideDefaultVersions)->count()} list(s) of versions that are not default versions also lack default-locale strings: not changed.");
            }

            if (! $apply || ($unlabelled->isEmpty() && $withoutDefault->isEmpty())) {
                return;
            }

            $this->applyLabelRepair($template, $unlabelled->pluck('id')->merge($withoutDefault->pluck('id'))->unique()->values());
        });
    }

    /** @param Collection<int, int> $entryIdsBefore */
    private function applyLabelRepair(XlsformTemplate $template, Collection $entryIdsBefore): void
    {
        $startedAt = now();

        AddMissingChoiceListStrings::dispatchSync($template);

        $unlabelledAfter = $this->unlabelledGlobalEntries($template);
        $withoutDefaultAfter = $this->globalEntriesWithoutDefaultStrings($template);

        $this->line("  after: {$this->countByList($unlabelledAfter)->count()} list(s) still have unlabelled global entries (no labelled same-named list to copy from)");
        $this->line("  after: {$withoutDefaultAfter->count()} global entries still have no default-locale strings (no same-named list to copy from)");

        $repairedEntryIds = $entryIdsBefore
            ->diff($unlabelledAfter->pluck('id')->merge($withoutDefaultAfter->pluck('id')))
            ->values();

        $formCount = $this->markFormsForDraftUpdate($repairedEntryIds);

        $this->line("  {$repairedEntryIds->count()} entries repaired; {$formCount} form(s) marked for a draft update.");

        $logPath = 'repair-backups/choice-label-repair-template-'.$template->id.'-'.$startedAt->format('Ymd-His').'.json';

        Storage::disk('local')->put($logPath, json_encode([
            'started_at' => $startedAt->toDateTimeString(),
            'template_id' => $template->id,
            'repaired_entry_ids' => $repairedEntryIds->all(),
            'undo' => 'delete the language_strings created at or after started_at that belong to these choice list entries',
        ], JSON_PRETTY_PRINT));

        $this->line('  log for an undo: '.Storage::disk('local')->path($logPath));
    }

    /** @return Collection<int, ChoiceListEntry> global entries of the template's lists that have no string at all */
    private function unlabelledGlobalEntries(XlsformTemplate $template): Collection
    {
        return ChoiceListEntry::withoutGlobalScopes()
            ->with('choiceList:id,list_name')
            ->whereNull('owner_id')
            ->whereIn('choice_list_id', $template->choiceLists()->select('choice_lists.id'))
            ->whereDoesntHave('languageStrings')
            ->get();
    }

    /** @return Collection<int, ChoiceListEntry> global entries of default module versions that have no default-locale string */
    private function globalEntriesWithoutDefaultStrings(XlsformTemplate $template): Collection
    {
        return ChoiceListEntry::withoutGlobalScopes()
            ->with('choiceList:id,list_name')
            ->whereNull('owner_id')
            ->whereIn('choice_list_id', $template->choiceListsForImport()->select('choice_lists.id'))
            ->whereDoesntHave('languageStrings', fn (Builder $query) => $query->whereHas('locale', fn (Builder $locale) => $locale->where('is_default', true)))
            ->get();
    }

    /** @return Collection<int, ChoiceListEntry> the same, for versions that are not default versions (reported only) */
    private function globalEntriesWithoutDefaultStringsOutsideDefaultVersions(XlsformTemplate $template): Collection
    {
        return ChoiceListEntry::withoutGlobalScopes()
            ->with('choiceList:id,list_name')
            ->whereNull('owner_id')
            ->whereIn('choice_list_id', $template->choiceLists()->where('xlsform_module_versions.is_default', false)->select('choice_lists.id'))
            ->whereDoesntHave('languageStrings', fn (Builder $query) => $query->whereHas('locale', fn (Builder $locale) => $locale->where('is_default', true)))
            ->get();
    }

    /**
     * @param  Collection<int, ChoiceListEntry>  $entries
     * @return Collection<(int|string), array{int, string, int}> [choice list id, list name, count], keyed by choice list id
     */
    private function countByList(Collection $entries): Collection
    {
        return $entries
            ->groupBy('choice_list_id')
            ->map(fn (Collection $listEntries, int $choiceListId) => [$choiceListId, $listEntries->first()->choiceList->list_name, $listEntries->count()]);
    }

    // A choice entry saved through the model flags the drafts of its forms; the language strings written here do not,
    // so the forms that use a repaired entry are flagged by hand and the teams see that their form changed.
    /** @param Collection<int, int> $entryIds */
    private function markFormsForDraftUpdate(Collection $entryIds): int
    {
        if ($entryIds->isEmpty()) {
            return 0;
        }

        $versionIds = $entryIds
            ->chunk(500)
            ->flatMap(fn (Collection $chunk) => DB::table('choice_list_entries as entry')
                ->join('choice_lists as list', 'list.id', '=', 'entry.choice_list_id')
                ->whereIn('entry.id', $chunk->all())
                ->pluck('list.xlsform_module_version_id'))
            ->unique()
            ->values();

        $formIds = DB::table('selected_xlsform_module_versions')
            ->whereIn('xlsform_module_version_id', $versionIds->all())
            ->distinct()
            ->pluck('xlsform_id');

        Xlsform::withoutGlobalScopes()
            ->whereIn('id', $formIds->all())
            ->get()
            ->each(fn (Xlsform $xlsform) => $xlsform->updateQuietly(['draft_needs_update' => true]));

        return $formIds->count();
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

    // R4: end group and end repeat rows are never used by a form, but the translation file listed them and an old import
    // copied the begin group's title onto them. Strings of a team locale on an end row that has no default-locale string
    // are leftovers; the end rows that do have default text (for example "location2") are left alone.
    private function repairEndRows(bool $apply): bool
    {
        $this->newLine();
        $this->info('R4: team-locale strings of end group and end repeat rows without a default-locale string');

        $perLocale = $this->endRowTeamStrings()
            ->select('locale_id', DB::raw('count(*) as total'))
            ->groupBy('locale_id')
            ->orderBy('locale_id')
            ->pluck('total', 'locale_id');

        $total = (int) $perLocale->sum();

        $this->table(['locale_id', 'strings'], $perLocale->map(fn (int $count, int $localeId) => [$localeId, $count])->values()->all());
        $this->line("Total: {$total} string(s).");

        if (! $apply) {
            $this->line("To delete them run: php artisan odk:repair-form-deploy-issues --only=ends --apply --expect={$total}");

            return true;
        }

        $expected = $this->option('expect');

        if ($expected === null || ! ctype_digit((string) $expected)) {
            $this->error('--apply with --only=ends needs --expect=N, the number the dry run reported.');

            return false;
        }

        if ((int) $expected !== $total) {
            $this->error("--expect={$expected} but {$total} string(s) match: nothing was deleted. Check the data, then run the dry run again.");

            return false;
        }

        if ($total === 0) {
            $this->line('Nothing to delete.');

            return true;
        }

        $strings = $this->endRowTeamStrings()->orderBy('id')->get();

        $backupPath = 'repair-backups/end-row-strings-'.now()->format('Ymd-His').'.json';

        Storage::disk('local')->put($backupPath, json_encode($strings->map->getAttributes()->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line('Backup of the rows (restore by inserting them again): '.Storage::disk('local')->path($backupPath));

        DB::transaction(function () use ($strings) {
            $strings->pluck('id')->chunk(500)->each(fn (Collection $ids) => LanguageString::whereIn('id', $ids->all())->delete());
        });

        $this->line("Deleted {$strings->count()} string(s).");

        return true;
    }

    /** @return Builder<LanguageString> */
    private function endRowTeamStrings(): Builder
    {
        $endRowsWithoutDefaultText = SurveyRow::groupEnds()
            ->whereDoesntHave('languageStrings', fn (Builder $query) => $query->whereHas('locale', fn (Builder $locale) => $locale->where('is_default', true)))
            ->select('survey_rows.id');

        return LanguageString::query()
            ->where('linked_entry_type', (new SurveyRow)->getMorphClass())
            ->whereIn('linked_entry_id', $endRowsWithoutDefaultText)
            ->whereHas('locale', fn (Builder $locale) => $locale->where('is_default', false));
    }
}
