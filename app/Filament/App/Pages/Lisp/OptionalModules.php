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
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class OptionalModules extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;
    use WithCompletionStatusBar;

    public string $completionProp = 'optional_modules_complete';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return t('Localisation: Optional Modules');
    }

    protected static string $view = 'filament.app.pages.lisp.optional-modules';

    public function getSummary(): string
    {
        return t('Please select the optional modules to be included in your survey.');
    }

    public Team $team;

    public ?array $data = [];

    // Within-request cache — not serialized by Livewire, rebuilt each render cycle
    private ?Collection $selectedVersionIds = null;

    public function mount(): void
    {
        $this->team = HelperService::getCurrentOwner();

        $this->form->fill([
            'xlsform_id' => $this->team->xlsforms()->first()?->id,
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
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
            'summary'     => $this->getSummary(),
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
                    ->label(fn () => t('Survey Form'))
                    ->options(fn () => $this->team->xlsforms()->pluck('title', 'id'))
                    ->live()
                    ->afterStateUpdated(function () {
                        $this->selectedVersionIds = null;
                        $this->resetTable();
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

    private function getSelectedVersionIds(): Collection
    {
        if ($this->selectedVersionIds !== null) {
            return $this->selectedVersionIds;
        }

        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return $this->selectedVersionIds = collect();
        }

        return $this->selectedVersionIds = $xlsform->xlsformModuleVersions()
            ->pluck('xlsform_module_versions.id');
    }

    private function isSelected(XlsformModuleVersion $record): bool
    {
        return $this->getSelectedVersionIds()->contains($record->id);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function addModule(XlsformModuleVersion $record): void
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return;
        }

        $maxOrder = $xlsform->xlsformModuleVersions()
            ->max('selected_xlsform_module_versions.order') ?? 0;

        $xlsform->xlsformModuleVersions()->attach($record->id, ['order' => $maxOrder + 1]);
        $xlsform->update(['draft_needs_update' => true]);

        $this->selectedVersionIds = null;
    }

    public function removeModule(XlsformModuleVersion $record): void
    {
        $xlsform = $this->getSelectedXlsform();

        if (! $xlsform) {
            return;
        }

        $xlsform->xlsformModuleVersions()->detach($record->id);
        $xlsform->update(['draft_needs_update' => true]);

        $this->selectedVersionIds = null;
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public function table(Table $table): Table
    {
        $xlsformSelected = ($this->data['xlsform_id'] ?? null) !== null;

        return $table
            ->query(
                XlsformModuleVersion::query()
                    ->whereNull('xlsform_module_id')
                    ->whereNull('owner_id')
            )
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
            ->actions([
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
