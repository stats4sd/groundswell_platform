<?php

namespace App\Filament\App\Pages\Lisp;

use Illuminate\Contracts\View\View;
use Filament\Support\Enums\Width;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use App\Filament\App\Pages\SurveyDashboard;
use App\Filament\Shared\WithCompletionStatusBar;
use App\Models\Team;
use App\Services\HelperService;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
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

    public function getTitle(): string
    {
        return t('Localisation: Optional Modules');
    }

    protected string $view = 'filament.app.pages.lisp.optional-modules';

    public function getSummary(): string
    {
        return t('Please select the optional modules to be included in your survey.');
    }

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
            'xlsform_id' => $this->team->xlsforms()
                ->whereRaw('LOWER(title) NOT LIKE ?', ['%' . config('optional_modules.farm_registration_form_title') . '%'])
                ->first()?->id,
        ]);

        $this->setupLists();
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            static::getUrl() => static::getTitle(),
        ];
    }

    public function getHeader(): ?View
    {
        return view('components.small-header', [
            'heading'     => $this->getHeading(),
            'subheading'  => $this->getSubheading(),
            'actions'     => $this->getHeaderActions(),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'summary'     => $this->getSummary(),
        ]);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Select::make('xlsform_id')
                    ->label(fn () => t('Survey Form'))
                    ->options(fn () => $this->team->xlsforms()
                        ->whereRaw('LOWER(title) NOT LIKE ?', ['%' . config('optional_modules.farm_registration_form_title') . '%'])
                        ->pluck('title', 'id'))
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->setupLists();
                    })
                    ->placeholder(fn () => t('Select a survey form...'))
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

    private function getModuleKeywords(): array
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return [];
        }

        $title = strtolower($xlsform->title);

        if (str_contains($title, config('optional_modules.global_indicators_form_title'))) {
            return config('optional_modules.global_indicators_modules');
        }

        if (str_contains($title, config('optional_modules.womans_form_form_title'))) {
            return config('optional_modules.womans_form_modules');
        }

        return [];
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        $xlsformSelected = ($this->data['xlsform_id'] ?? null) !== null;

        return $table
            ->query(function () {
                $query = XlsformModuleVersion::query()
                    ->whereNull('xlsform_module_id')
                    ->whereNull('owner_id');

                $keywords = $this->getModuleKeywords();

                if (empty($keywords)) {
                    return $query->whereRaw('0 = 1');
                }

                return $query->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhereRaw('LOWER(name) LIKE ?', ['%' . $keyword . '%']);
                    }
                });
            })
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('survey_rows_count')
                    ->counts('surveyRows')
                    ->label(fn () => t('# Questions')),
                IconColumn::make('is_selected')
                    ->label(fn () => t('In Survey'))
                    ->boolean()
                    ->state(fn (XlsformModuleVersion $record): bool => $this->isSelected($record)),
            ])
            ->recordActions([
                Action::make('add')
                    ->label(fn () => t('Add to Survey'))
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->visible(fn (XlsformModuleVersion $record): bool => $xlsformSelected && ! $this->isSelected($record))
                    ->action(fn (XlsformModuleVersion $record) => $this->addModule($record)),
                Action::make('remove')
                    ->label(fn () => t('Remove'))
                    ->icon('heroicon-o-minus-circle')
                    ->color('danger')
                    ->visible(fn (XlsformModuleVersion $record): bool => $xlsformSelected && $this->isSelected($record))
                    ->action(fn (XlsformModuleVersion $record) => $this->removeModule($record)),
            ])
            ->paginated(false);
    }
}
