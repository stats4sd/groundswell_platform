<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

class SyncRemovedChoiceListEntries extends Command
{
    protected $signature = 'choice-lists:sync-removed-entries {--apply : Write the missing rows. Without this flag the command only lists them.}';

    protected $description = 'If a team removed an entry from context in any copy of a same-named choice list, remove it from all copies.';

    public function handle(): int
    {
        $missingRows = $this->findMissingRows();

        if ($missingRows->isEmpty()) {
            $this->comment('All same-named choice list copies are already in sync.');

            return self::SUCCESS;
        }

        $missingRows->each(fn (stdClass $row) => $this->info(
            "Team {$row->owner_id}: {$row->list_name} / {$row->name} (entry {$row->choice_list_entry_id})"
        ));

        if (! $this->option('apply')) {
            $this->comment("Dry run: {$missingRows->count()} row(s) would be added. Re-run with --apply to write them.");

            return self::SUCCESS;
        }

        DB::table('choice_list_entries_removed_owner')->insert(
            $missingRows->map(fn (stdClass $row) => [
                'owner_id' => $row->owner_id,
                'choice_list_entry_id' => $row->choice_list_entry_id,
            ])->all()
        );

        $this->comment("Added {$missingRows->count()} row(s).");

        return self::SUCCESS;
    }

    /** @return Collection<int, stdClass> */
    private function findMissingRows(): Collection
    {
        return collect(DB::select(<<<'SQL'
            SELECT DISTINCT
                removed.owner_id,
                sibling.id AS choice_list_entry_id,
                sibling_list.list_name,
                sibling.name
            FROM choice_list_entries_removed_owner AS removed
            JOIN choice_list_entries AS removed_entry ON removed_entry.id = removed.choice_list_entry_id
            JOIN choice_lists AS removed_list ON removed_list.id = removed_entry.choice_list_id
            JOIN choice_lists AS sibling_list
                ON sibling_list.list_name = removed_list.list_name
                AND sibling_list.id <> removed_list.id
                AND sibling_list.is_localisable = 1
                AND sibling_list.has_custom_handling = 0
            JOIN choice_list_entries AS sibling
                ON sibling.choice_list_id = sibling_list.id
                AND sibling.name = removed_entry.name
                AND (sibling.owner_id = removed_entry.owner_id OR (sibling.owner_id IS NULL AND removed_entry.owner_id IS NULL))
            LEFT JOIN choice_list_entries_removed_owner AS already_removed
                ON already_removed.choice_list_entry_id = sibling.id
                AND already_removed.owner_id = removed.owner_id
            WHERE removed_list.is_localisable = 1
                AND removed_list.has_custom_handling = 0
                AND already_removed.choice_list_entry_id IS NULL
            ORDER BY removed.owner_id, sibling_list.list_name, sibling.name
        SQL));
    }
}
