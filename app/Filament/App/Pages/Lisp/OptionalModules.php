<?php

namespace App\Filament\App\Pages\Lisp;

use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class OptionalModules extends Page implements HasForms
{
    use InteractsWithForms;
    use WithCompletionStatusBar;

    public string $completionProp = 'optional_modules_complete';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Localisation: Optional Modules';

    protected static string $view = 'filament.app.pages.lisp.optional-modules';

    protected ?string $summary = 'Please select the optional modules to be included in your survey.';

    public Team $team;

    public ?array $data = [];

    public Collection $availableModules;
    public Collection $xlsformModules;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();
        $this->availableModules = new Collection();
        $this->xlsformModules = new Collection();

        $this->form->fill([
            'xlsform_id' => $this->team->xlsforms()->first()?->id,
        ]);

        $this->setupLists();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => 'Survey Dashboard',
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getHeader(): ?\Illuminate\Contracts\View\View
    {
        return view('components.small-header', [
            'heading'     => $this->getHeading(),
            'subheading'  => $this->getSubheading(),
            'actions'     => $this->getHeaderActions(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'summary'     => $this->summary,
        ]);
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('xlsform_id')
                    ->label('Survey Form')
                    ->options(fn () => $this->team->xlsforms()->pluck('title', 'id'))
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->setupLists();
                    })
                    ->placeholder('Select a survey form...')
                    ->required(),
            ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function getSelectedXlsform(): ?Xlsform
    {
        $id = $this->data['xlsform_id'] ?? null;

        return $id ? Xlsform::find($id) : null;
    }

    private function setupLists(): void
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            $this->availableModules = new Collection();
            $this->xlsformModules = new Collection();

            return;
        }

        $addedIds = $xlsform->xlsformModuleVersions()->pluck('xlsform_module_versions.id');

        $this->availableModules = XlsformModuleVersion::whereNull('xlsform_module_id')
            ->whereNull('owner_id')
            ->whereNotIn('id', $addedIds)
            ->with('surveyRows')
            ->get();

        $this->xlsformModules = $xlsform->xlsformModuleVersions()
            ->with('surveyRows')
            ->get();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function updateOrder(array $order): void
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return;
        }

        $orderWithKeys = collect($order)->mapWithKeys(fn ($item, $key) => [$item => ['order' => $key]]);
        $xlsform->xlsformModuleVersions()->sync($orderWithKeys);
        $xlsform->update(['draft_needs_update' => true]);

        $this->setupLists();
    }

    public function removeModule(int $moduleVersionId): void
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return;
        }

        $xlsform->xlsformModuleVersions()->detach($moduleVersionId);
        $xlsform->update(['draft_needs_update' => true]);

        $this->setupLists();
    }

    public function confirmOrdering(): void
    {
        $this->redirect(SurveyDashboard::getUrl());
    }
}
