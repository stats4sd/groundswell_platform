<?php

use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages\ListImports;
use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Pages\ViewImport;
use App\Filament\App\Clusters\LocationLevels\Resources\ImportResource\Widgets\RecentImportsWidget;
use App\Filament\App\Clusters\LocationLevels\Resources\LocationLevelResource\Pages\ViewLocationLevel;
use App\Models\Import;
use App\Models\SampleFrame\FarmEntity;
use App\Models\SampleFrame\Location;
use App\Models\SampleFrame\LocationLevel;
use App\Models\Team;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

// The imports page renders text that originates in spreadsheet cells and exception messages,
// for a resource whose rows belong to one team, so tenancy and escaping are the two assertions
// that matter most here.

beforeEach(function () {
    Http::fake();

    $this->team = Team::factory()->create();
    $this->user = createAppUser($this->team);
    $this->actingAs($this->user);

    withAppTenant($this->team);
});

function teamImport(Team $team, array $attributes = []): Import
{
    return Import::create([
        'team_id' => $team->id,
        'model_type' => Location::class,
        ...$attributes,
    ]);
}

// Asserted over real requests rather than through livewire(): Filament registers its tenancy
// global scope when the panel boots during a request, so a Livewire-only test would pass
// whether or not the resource is scoped at all.
describe('tenant scoping', function () {

    test('the list shows this team\'s imports and not another team\'s', function () {
        teamImport($this->team, [
            'errors' => [['row' => 1, 'attribute' => 'code', 'errors' => ['A problem in our own file.']]],
        ]);

        teamImport(Team::factory()->create(), [
            'errors' => [['row' => 1, 'attribute' => 'code', 'errors' => ['A problem in someone else\'s file.']]],
        ]);

        $this->get("/app/{$this->team->id}/location-levels/imports")
            ->assertOk()
            ->assertSee('A problem in our own file.')
            ->assertDontSee('A problem in someone else');
    });

    test('another team\'s import cannot be opened by pasting its url', function () {
        $theirs = teamImport(Team::factory()->create());

        $this->get("/app/{$this->team->id}/location-levels/imports/{$theirs->id}")
            ->assertNotFound();
    });

});

describe('the view page', function () {

    test('it names the row, the column and the message of every failure', function () {
        $import = teamImport($this->team, [
            'model_type' => FarmEntity::class,
            'errors' => [
                ['row' => 14, 'attribute' => 'loc1_code', 'errors' => ['The value is required.']],
                ['row' => 15, 'attribute' => 'farm_code', 'errors' => ['The farm code cannot be empty.']],
            ],
        ]);

        livewire(ViewImport::class, ['record' => $import->id])
            ->assertSee('14')
            ->assertSee('loc1_code')
            ->assertSee('The value is required.')
            ->assertSee('farm_code')
            ->assertSee('The farm code cannot be empty.');
    });

    test('an unattributed message is still readable, so a skipped dependent import explains itself', function () {
        $import = teamImport($this->team, [
            'model_type' => FarmEntity::class,
            'errors' => [
                ['row' => null, 'attribute' => null, 'errors' => ['The farm import was skipped because the location import it depends on failed.']],
            ],
        ]);

        livewire(ViewImport::class, ['record' => $import->id])
            ->assertSee('The farm import was skipped because the location import it depends on failed.');
    });

    test('a legacy nested payload is rendered the same as a canonical one', function () {
        $import = teamImport($this->team, [
            'errors' => [
                ['location' => ['row' => 7, 'column' => 'village_code'], 'errors' => ['Old shape message.']],
            ],
        ]);

        livewire(ViewImport::class, ['record' => $import->id])
            ->assertSee('village_code')
            ->assertSee('Old shape message.');
    });

});

describe('escaping of error text', function () {

    // Error messages are built from spreadsheet cell contents, so this pair guards the
    // ->html() decision on the messages entry: sanitised markup renders, script tags do not.
    // A refactor to HtmlString (which CanFormatState passes through unsanitised) fails the
    // first of these while still passing the second.
    test('a script tag in a message never reaches the page', function () {
        $import = teamImport($this->team, [
            'errors' => [
                ['row' => 2, 'attribute' => 'code', 'errors' => ['<script>alert(1)</script>']],
            ],
        ]);

        livewire(ViewImport::class, ['record' => $import->id])
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    });

    test('safe markup in a message still renders as markup', function () {
        $import = teamImport($this->team, [
            'errors' => [
                ['row' => 2, 'attribute' => 'code', 'errors' => ['The <b>code</b> column is empty.']],
            ],
        ]);

        livewire(ViewImport::class, ['record' => $import->id])
            ->assertSee('<b>code</b>', escape: false);
    });

});

// The standalone location import closes its modal without saying anything, so this page had no
// feedback surface of its own at all before the widget was mounted on it.
describe('the recent imports widget on a location level page', function () {

    test('it reports a failed location import on the page the import was started from', function () {
        $level = LocationLevel::create([
            'owner_id' => $this->team->id,
            'name' => 'Village',
            'has_farms' => true,
        ]);

        teamImport($this->team, [
            'errors' => [['row' => 14, 'attribute' => 'village_code', 'errors' => ['The value is required.']]],
        ]);

        livewire(ViewLocationLevel::class, ['record' => $level->slug])
            ->assertSeeLivewire(RecentImportsWidget::class);
    });

    // The widget is lazy, so mounting it proves nothing about what it renders once loaded.
    test('the widget itself lists the team\'s imports and links each to its own page', function () {
        $import = teamImport($this->team, [
            'errors' => [['row' => 14, 'attribute' => 'village_code', 'errors' => ['The value is required.']]],
        ]);

        livewire(RecentImportsWidget::class)
            ->assertCanSeeTableRecords([$import])
            ->assertSee('The value is required.')
            ->assertSee('Failed');
    });

    test('it stays hidden on that page until the team has imported something', function () {
        $level = LocationLevel::create([
            'owner_id' => $this->team->id,
            'name' => 'Village',
            'has_farms' => true,
        ]);

        livewire(ViewLocationLevel::class, ['record' => $level->slug])
            ->assertDontSeeLivewire(RecentImportsWidget::class);
    });

});

describe('the derived status filter', function () {

    test('it selects failed, complete and in-progress imports from columns rather than a status field', function () {
        $failed = teamImport($this->team, [
            'errors' => [['row' => 1, 'attribute' => 'code', 'errors' => ['Required.']]],
        ]);
        $complete = teamImport($this->team, ['success' => true]);
        $pending = teamImport($this->team);

        livewire(ListImports::class)
            ->filterTable('status', 'failed')
            ->assertCanSeeTableRecords([$failed])
            ->assertCanNotSeeTableRecords([$complete, $pending]);

        livewire(ListImports::class)
            ->filterTable('status', 'complete')
            ->assertCanSeeTableRecords([$complete])
            ->assertCanNotSeeTableRecords([$failed, $pending]);

        livewire(ListImports::class)
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$failed, $complete]);
    });

    test('an import left pending for over an hour is filterable as interrupted', function () {
        $stale = teamImport($this->team);
        $stale->update(['created_at' => now()->subHours(2)]);

        $fresh = teamImport($this->team);

        livewire(ListImports::class)
            ->filterTable('status', 'stale')
            ->assertCanSeeTableRecords([$stale])
            ->assertCanNotSeeTableRecords([$fresh]);
    });

    test('the model type filter matches the stored class name, not the pluralised accessor', function () {
        $locations = teamImport($this->team);
        $farms = teamImport($this->team, ['model_type' => FarmEntity::class]);

        livewire(ListImports::class)
            ->filterTable('model_type', Location::class)
            ->assertCanSeeTableRecords([$locations])
            ->assertCanNotSeeTableRecords([$farms]);
    });

});
