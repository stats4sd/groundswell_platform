<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;

/**
 * The same list_name can exist once per xlsform module version. Teams manage them as one list,
 * so every write made through the UI is applied to all same-named copies.
 */
class ChoiceListSiblingService
{
    /** @return Collection<int, int> */
    public function listIdsFor(string $listName): Collection
    {
        return ChoiceList::where('list_name', $listName)
            ->where('is_localisable', true)
            ->where('has_custom_handling', false)
            ->orderBy('id')
            ->pluck('id');
    }

    public function canonicalListId(string $listName): ?int
    {
        return $this->listIdsFor($listName)->first();
    }

    /** @return EloquentCollection<int, ChoiceListEntry> */
    public function siblingsOf(ChoiceListEntry $entry): EloquentCollection
    {
        $listName = $entry->choiceList->list_name;

        $otherListIds = $this->listIdsFor($listName)
            ->reject(fn (int $listId) => $listId === $entry->choice_list_id);

        return ChoiceListEntry::withoutGlobalScopes()
            ->whereIn('choice_list_id', $otherListIds)
            ->where('name', $entry->name)
            ->where('owner_id', $entry->owner_id)
            ->get();
    }

    public function toggleRemoved(ChoiceListEntry $entry, Team $team): void
    {
        $entries = $this->siblingsOf($entry)->push($entry);

        DB::transaction(function () use ($entry, $entries, $team) {
            if ($entry->isRemoved($team)) {
                $team->choiceListEntriesRemovedFromContext()->detach($entries->modelKeys());
            } else {
                $team->choiceListEntriesRemovedFromContext()->syncWithoutDetaching($entries->modelKeys());
            }

            // the generated forms now differ from the published ones; flag them so the team is prompted to publish
            $team->xlsforms()->update(['draft_needs_update' => true]);
        });

        $team->unsetRelation('choiceListEntriesRemovedFromContext');
    }

    public function replicateToSiblingLists(ChoiceListEntry $entry): void
    {
        $listName = $entry->choiceList->list_name;

        $otherListIds = $this->listIdsFor($listName)
            ->reject(fn (int $listId) => $listId === $entry->choice_list_id);

        DB::transaction(function () use ($entry, $otherListIds) {
            $otherListIds->each(function (int $listId) use ($entry) {
                $copy = ChoiceListEntry::withoutGlobalScopes()->firstOrCreate(
                    [
                        'choice_list_id' => $listId,
                        'name' => $entry->name,
                        'owner_id' => $entry->owner_id,
                    ],
                    ['properties' => $entry->properties],
                );

                $this->copyLanguageStrings($entry, $copy);
            });
        });
    }

    /**
     * @param  EloquentCollection<int, ChoiceListEntry>  $siblings  siblings resolved before the entry was changed
     */
    public function syncSiblingsFrom(ChoiceListEntry $entry, EloquentCollection $siblings): void
    {
        DB::transaction(function () use ($entry, $siblings) {
            $siblings->each(function (ChoiceListEntry $sibling) use ($entry) {
                $sibling->update([
                    'name' => $entry->name,
                    'properties' => $entry->properties,
                ]);

                $this->copyLanguageStrings($entry, $sibling);
            });
        });
    }

    /** @param  EloquentCollection<int, ChoiceListEntry>  $siblings */
    public function deleteSiblings(EloquentCollection $siblings): void
    {
        DB::transaction(fn () => $siblings->each->delete());
    }

    private function copyLanguageStrings(ChoiceListEntry $from, ChoiceListEntry $to): void
    {
        $from->languageStrings()->get()->each(
            fn (LanguageString $languageString) => $to->languageStrings()->updateOrCreate(
                [
                    'locale_id' => $languageString->locale_id,
                    'language_string_type_id' => $languageString->language_string_type_id,
                ],
                ['text' => $languageString->text],
            )
        );
    }
}
