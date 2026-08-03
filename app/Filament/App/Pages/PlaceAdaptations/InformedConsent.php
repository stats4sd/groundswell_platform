<?php

namespace App\Filament\App\Pages\PlaceAdaptations;

use App\Filament\App\Pages\SurveyDashboard;
use App\Models\Team;
use App\Services\HelperService;
use App\Services\OdkMarkdownService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;

class InformedConsent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.app.pages.place-adaptations.informed-consent';

    protected Width|string|null $maxContentWidth = 'max-w-10xl';

    public Team $team;

    public ?array $data = [];

    /** @var array<int, array{xlsform_title: string, survey_row_id: int}> */
    public array $consentRows = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('view place-based adaptations');
    }

    public function getTitle(): string
    {
        return t('Informed consent');
    }

    public function getBreadcrumbs(): array
    {
        return [
            SurveyDashboard::getUrl() => t('Survey Dashboard'),
            PlaceAdaptationsIndex::getUrl() => t('Place-based adaptations'),
            static::getUrl() => $this->getTitle(),
        ];
    }

    public function mount(): void
    {
        $team = HelperService::getCurrentOwner();

        if ($team === null) {
            abort(404);
        }

        $this->team = $team;

        $clonedVersions = [];

        foreach ($team->informedConsentXlsforms() as $xlsform) {
            $moduleVersion = $xlsform->xlsformModuleVersions()
                ->whereHas('surveyRows', fn ($query) => $query->where('name', 'enum_intro'))
                ->first();

            if ($moduleVersion === null) {
                continue;
            }

            // First visit: the team is still pointing at the global module
            // version. Clone it so their edits are isolated, then swap the
            // pivot entries on the team's xlsforms to the team-owned version.
            if ($moduleVersion->owner_id !== $team->id) {
                $globalVersion = $moduleVersion;
                $moduleVersion = $clonedVersions[$globalVersion->id] ??= $globalVersion->cloneForOwner($team);

                foreach ($team->xlsforms()->pluck('xlsforms.id') as $xlsformId) {
                    $linked = $globalVersion->xlsforms()->wherePivot('xlsform_id', $xlsformId)->first();

                    if ($linked) {
                        $order = $linked->pivot->order;
                        $globalVersion->xlsforms()->detach($xlsformId);
                        $moduleVersion->xlsforms()->attach($xlsformId, ['order' => $order]);
                    }
                }
            }

            $surveyRow = $moduleVersion->surveyRows()->where('name', 'enum_intro')->first();

            if ($surveyRow === null) {
                continue;
            }

            $this->consentRows[] = [
                'xlsform_title' => $xlsform->title,
                'survey_row_id' => $surveyRow->id,
            ];
        }

        if ($this->consentRows === []) {
            abort(404);
        }

        $converter = app(OdkMarkdownService::class);
        $consents = [];

        foreach ($this->consentRows as $consentRow) {
            $surveyRow = SurveyRow::findOrFail($consentRow['survey_row_id']);

            foreach ($team->locales as $locale) {
                $consents[$consentRow['survey_row_id']][$locale->id] = $converter->toHtml(
                    $surveyRow->getLanguageString('hint', $locale),
                );
            }
        }

        $this->form->fill(['consents' => $consents]);
    }

    public function form(Schema $schema): Schema
    {
        $locales = $this->team->locales;
        $sections = [];

        foreach ($this->consentRows as $consentRow) {
            $surveyRow = SurveyRow::findOrFail($consentRow['survey_row_id']);
            $fields = [];

            foreach ($locales as $locale) {
                $fields[] = Placeholder::make("label_{$consentRow['survey_row_id']}_{$locale->id}")
                    ->label(t('Question label')." — {$locale->language_label}")
                    ->content($surveyRow->getLanguageString('label', $locale) ?? '—');

                $fields[] = RichEditor::make("consents.{$consentRow['survey_row_id']}.{$locale->id}")
                    ->label(t('Consent text')." — {$locale->language_label}")
                    ->toolbarButtons([['bold', 'italic'], ['undo', 'redo']])
                    ->floatingToolbars([])
                    ->helperText(t('This text is shown to participants on the data collection device. Formatting is limited to bold, italic and line breaks — anything else is removed when saving.'));
            }

            $sections[] = Section::make($consentRow['xlsform_title'])
                ->columns(1)
                ->schema($fields);
        }

        return $schema
            ->statePath('data')
            ->components($sections);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $converter = app(OdkMarkdownService::class);
        $hintTypeId = LanguageStringType::where('name', 'hint')->value('id');
        $allowedRowIds = collect($this->consentRows)->pluck('survey_row_id');

        foreach ($state['consents'] ?? [] as $surveyRowId => $localeTexts) {
            if (! $allowedRowIds->contains((int) $surveyRowId)) {
                continue;
            }

            $surveyRow = SurveyRow::findOrFail($surveyRowId);
            $hasChanges = false;

            foreach ($localeTexts as $localeId => $html) {
                $text = $converter->fromHtml($html);

                $existingText = $surveyRow->languageStrings()
                    ->where('locale_id', $localeId)
                    ->where('language_string_type_id', $hintTypeId)
                    ->value('text');

                if (trim($existingText ?? '') === trim($text)) {
                    continue;
                }

                $surveyRow->languageStrings()->updateOrCreate(
                    [
                        'locale_id' => $localeId,
                        'language_string_type_id' => $hintTypeId,
                    ],
                    ['text' => $text],
                );

                $hasChanges = true;
            }

            if ($hasChanges) {
                // Touch the SurveyRow so its saved() hook flags the team's
                // xlsforms as needing a draft update (draft_needs_update = true).
                $surveyRow->touch();
            }
        }

        Notification::make()
            ->title(t('Informed consent text saved'))
            ->success()
            ->send();
    }
}
