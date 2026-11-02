<?php

use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource;
use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Pages\ListChoiceListEntries;
use App\Models\Team;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

use function Pest\Livewire\livewire;

function createChoiceListCopy(XlsformTemplate $template, string $moduleName, string $listName, array $entryNames): ChoiceList
{
    $module = XlsformModule::forceCreate([
        'xlsform_template_id' => $template->id,
        'label' => $moduleName,
        'name' => $moduleName,
    ]);

    $moduleVersion = XlsformModuleVersion::forceCreate([
        'xlsform_module_id' => $module->id,
        'name' => 'v1',
        'is_default' => true,
    ]);

    $choiceList = ChoiceList::forceCreate([
        'xlsform_module_version_id' => $moduleVersion->id,
        'list_name' => $listName,
        'is_localisable' => true,
        'has_custom_handling' => false,
    ]);

    foreach ($entryNames as $entryName) {
        ChoiceListEntry::create([
            'choice_list_id' => $choiceList->id,
            'name' => $entryName,
        ]);
    }

    return $choiceList;
}

function labelFormData(Team $team, string $text): array
{
    return [
        [
            'locale_id' => $team->locales()->value('locales.id'),
            'language_string_type_id' => LanguageStringType::where('name', 'label')->firstOrFail()->id,
            'text' => $text,
        ],
    ];
}

beforeEach(function () {
    Http::fake();
    $this->team = Team::factory()->create();

    $template = XlsformTemplate::withoutEvents(
        fn () => XlsformTemplate::forceCreate(['title' => 'Test Template'])
    );

    $this->firstList = createChoiceListCopy($template, 'crops', 'crop_list', ['1', '2']);
    $this->secondList = createChoiceListCopy($template, 'crop_intensification', 'crop_list', ['1', '2']);

    $this->user = createAppUser($this->team);
    $this->actingAs($this->user);
    withAppTenant($this->team);
});

test('same-named lists show each entry once', function () {
    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->assertCanSeeTableRecords(ChoiceListEntry::where('choice_list_id', $this->firstList->id)->get())
        ->assertCanNotSeeTableRecords(ChoiceListEntry::where('choice_list_id', $this->secondList->id)->get())
        ->assertCountTableRecords(2);
});

test('navigation has one item per list name', function () {
    $items = collect(ChoiceListEntryResource::getNavigationItems())
        ->filter(fn ($item) => $item->getLabel() === 'crop_list');

    expect($items)->toHaveCount(1);
});

test('toggle removed removes and restores every copy', function () {
    $entry = ChoiceListEntry::where('choice_list_id', $this->firstList->id)->where('name', '1')->first();

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction('Toggle Removed', $entry);

    $copyIds = ChoiceListEntry::where('name', '1')->pluck('id');

    expect($this->team->fresh()->choiceListEntriesRemovedFromContext()->pluck('choice_list_entries.id')->sort()->values())
        ->toEqual($copyIds->sort()->values());

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction('Toggle Removed', $entry);

    expect($this->team->fresh()->choiceListEntriesRemovedFromContext()->count())->toBe(0);
});

test('an entry removed in only one copy shows as removed for every copy, and restore clears it', function () {
    $firstOne = ChoiceListEntry::where('choice_list_id', $this->firstList->id)->where('name', '1')->first();
    $secondOne = ChoiceListEntry::where('choice_list_id', $this->secondList->id)->where('name', '1')->first();

    // only the non-canonical copy has a removal row
    $this->team->choiceListEntriesRemovedFromContext()->attach($secondOne);

    expect($firstOne->isRemoved($this->team->fresh()))->toBeTrue();

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction('Toggle Removed', $firstOne);

    expect($this->team->fresh()->choiceListEntriesRemovedFromContext()->count())->toBe(0);
});

test('toggle removed flags the team xlsforms as needing a draft update, both ways', function () {
    $xlsform = Xlsform::withoutEvents(fn () => Xlsform::forceCreate([
        'xlsform_template_id' => $this->firstList->xlsformModuleVersion->xlsformModule->xlsform_template_id,
        'owner_id' => $this->team->id,
        'draft_needs_update' => false,
    ]));

    $entry = ChoiceListEntry::where('choice_list_id', $this->firstList->id)->where('name', '1')->first();

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction('Toggle Removed', $entry);

    expect($xlsform->fresh()->draft_needs_update)->toBeTruthy();

    $xlsform->updateQuietly(['draft_needs_update' => false]);

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction('Toggle Removed', $entry);

    expect($xlsform->fresh()->draft_needs_update)->toBeTruthy();
});

test('create adds the entry and its labels to every copy', function () {
    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction(CreateAction::class, data: [
            'name' => 'quinoa',
            'languageStrings' => labelFormData($this->team, 'Quinoa'),
        ])
        ->assertHasNoTableActionErrors();

    foreach ([$this->firstList, $this->secondList] as $list) {
        $entry = ChoiceListEntry::where('choice_list_id', $list->id)->where('name', 'quinoa')->first();

        expect($entry)->not->toBeNull()
            ->and($entry->owner_id)->toBe($this->team->id)
            ->and($entry->languageStrings()->value('text'))->toBe('Quinoa');
    }
});

test('create rejects a name already used in another copy', function () {
    ChoiceListEntry::create([
        'choice_list_id' => $this->secondList->id,
        'owner_id' => $this->team->id,
        'name' => 'quinoa',
    ]);

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction(CreateAction::class, data: [
            'name' => 'quinoa',
            'languageStrings' => labelFormData($this->team, 'Quinoa'),
        ])
        ->assertHasTableActionErrors(['name' => 'unique']);
});

test('edit and delete propagate to every copy', function () {
    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction(CreateAction::class, data: [
            'name' => 'quinoa',
            'languageStrings' => labelFormData($this->team, 'Quinoa'),
        ]);

    $entry = ChoiceListEntry::where('choice_list_id', $this->firstList->id)->where('name', 'quinoa')->first();

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction(EditAction::class, $entry, data: [
            'name' => 'amaranth',
            'languageStrings' => [
                "record-{$entry->languageStrings()->value('id')}" => labelFormData($this->team, 'Amaranth')[0],
            ],
        ])
        ->assertHasNoTableActionErrors();

    foreach ([$this->firstList, $this->secondList] as $list) {
        $copy = ChoiceListEntry::where('choice_list_id', $list->id)->where('name', 'amaranth')->first();

        expect($copy)->not->toBeNull()
            ->and($copy->languageStrings()->value('text'))->toBe('Amaranth');
    }

    expect(ChoiceListEntry::where('name', 'quinoa')->count())->toBe(0);

    livewire(ListChoiceListEntries::class, ['choiceListName' => 'crop_list'])
        ->callTableAction(DeleteAction::class, $entry);

    expect(ChoiceListEntry::where('name', 'amaranth')->count())->toBe(0);
});

describe('choice-lists:sync-removed-entries', function () {

    beforeEach(function () {
        $this->firstOne = ChoiceListEntry::where('choice_list_id', $this->firstList->id)->where('name', '1')->first();
        $this->secondOne = ChoiceListEntry::where('choice_list_id', $this->secondList->id)->where('name', '1')->first();

        $this->team->choiceListEntriesRemovedFromContext()->attach($this->firstOne);
    });

    test('dry run changes nothing', function () {
        $this->artisan('choice-lists:sync-removed-entries')->assertSuccessful();

        expect($this->team->choiceListEntriesRemovedFromContext()->count())->toBe(1);
    });

    test('apply removes the entry from every copy and is idempotent', function () {
        $this->artisan('choice-lists:sync-removed-entries', ['--apply' => true])->assertSuccessful();

        expect($this->team->choiceListEntriesRemovedFromContext()->pluck('choice_list_entries.id')->sort()->values()->all())
            ->toEqual(collect([$this->firstOne->id, $this->secondOne->id])->sort()->values()->all());

        $this->artisan('choice-lists:sync-removed-entries', ['--apply' => true])->assertSuccessful();

        expect($this->team->choiceListEntriesRemovedFromContext()->count())->toBe(2);
    });

});
