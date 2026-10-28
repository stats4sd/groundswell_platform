<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Throwable;

class BackfillTemplateSettings extends Command
{
    protected $signature = 'odk:backfill-template-settings
        {--apply : Write the changes. Without it the command only reports what it would do}';

    protected $description = 'Fill instance_name and default_language of the xlsform templates from the settings sheet of their stored xlsx file';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $this->comment($apply
            ? 'APPLY: the changes will be written.'
            : 'DRY RUN: nothing will be changed. Run again with --apply to make the changes.');

        $changed = 0;
        $unchanged = 0;
        $skipped = 0;
        $failed = 0;

        $templates = XlsformTemplate::orderBy('id')->get();

        $templates->each(function (XlsformTemplate $template) use ($apply, &$changed, &$unchanged, &$skipped, &$failed) {
            $this->info("Template {$template->id} ({$template->title})");

            try {
                $settings = $template->readSettingsFromFile();
            } catch (Throwable $exception) {
                $this->error("  failed to read the file: {$exception->getMessage()}");
                $failed++;

                return;
            }

            if ($settings === null) {
                $this->line('  skipped: no stored xlsx file, or the file has no settings sheet');
                $skipped++;

                return;
            }

            $rows = collect($settings)->map(fn (?string $fromFile, string $column) => [
                $column,
                $template->{$column} ?? '(empty)',
                $fromFile ?? '(empty)',
                $template->{$column} === $fromFile ? 'unchanged' : 'change',
            ]);

            $this->table(['column', 'current', 'from file', ''], $rows->values()->all());

            if ($rows->every(fn (array $row) => $row[3] === 'unchanged')) {
                $unchanged++;

                return;
            }

            $changed++;

            if ($apply) {
                $template->updateQuietly($settings);
            }
        });

        $verb = $apply ? 'changed' : 'would be changed';

        $this->comment("{$templates->count()} template(s): {$changed} {$verb}, {$unchanged} unchanged, {$skipped} skipped, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
