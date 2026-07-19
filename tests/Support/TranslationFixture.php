<?php

namespace Tests\Support;

use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Language;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModule;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class TranslationFixture
{
    public Team $team;

    public XlsformTemplate $template;

    public XlsformModuleVersion $moduleVersion;

    public Locale $englishLocale;

    public Locale $frenchLocale;

    public Locale $spanishLocale;

    public Locale $abkhazianLocale;

    /** @var Collection<int, SurveyRow> */
    public Collection $surveyRows;

    /** @var Collection<int, ChoiceListEntry> */
    public Collection $choiceListEntries;

    /**
     * Template with 3 default locales (English, French, Spanish), 2 survey rows and 2 choice
     * entries with 'label' strings per default locale, owned by a team that has selected
     * English, French and Abkhazian (NOT Spanish), plus a team-created Abkhazian locale
     * ("Abkhazian (test)") with no language strings yet.
     */
    public static function make(): self
    {
        Http::fake();

        return Model::withoutEvents(function (): self {
            $fixture = new self;

            $fixture->team = Team::factory()->create();

            $fixture->template = XlsformTemplate::forceCreate(['title' => 'Translation Test Form']);
            $fixture->template->owner()->associate($fixture->team);
            $fixture->template->save();

            $module = XlsformModule::create([
                'xlsform_template_id' => $fixture->template->id,
                'label' => 'Main module',
                'name' => 'main',
            ]);

            $fixture->moduleVersion = XlsformModuleVersion::create([
                'xlsform_module_id' => $module->id,
                'name' => 'v1',
                'is_default' => true,
            ]);

            Xlsform::create([
                'xlsform_template_id' => $fixture->template->id,
                'owner_id' => $fixture->team->id,
            ]);

            $fixture->surveyRows = collect([
                ['name' => 'q1', 'type' => 'text', 'row_number' => 1],
                ['name' => 'q2', 'type' => 'integer', 'row_number' => 2],
            ])->map(fn (array $attributes): SurveyRow => SurveyRow::create([
                ...$attributes,
                'xlsform_module_version_id' => $fixture->moduleVersion->id,
            ]));

            $choiceList = ChoiceList::create([
                'xlsform_module_version_id' => $fixture->moduleVersion->id,
                'list_name' => 'yes_no',
            ]);

            $fixture->choiceListEntries = collect(['yes', 'no'])
                ->map(fn (string $name): ChoiceListEntry => ChoiceListEntry::create([
                    'choice_list_id' => $choiceList->id,
                    'name' => $name,
                ]));

            $fixture->englishLocale = $fixture->createDefaultLocale('en');
            $fixture->frenchLocale = $fixture->createDefaultLocale('fr');
            $fixture->spanishLocale = $fixture->createDefaultLocale('es');

            $abkhazian = Language::where('iso_alpha2', 'ab')->firstOrFail();

            $fixture->abkhazianLocale = Locale::create([
                'language_id' => $abkhazian->id,
                'is_default' => false,
                'creator_id' => $fixture->team->id,
                'description' => 'Abkhazian (test)',
            ]);
            $fixture->abkhazianLocale->xlsformModuleVersions()->attach($fixture->moduleVersion->id);

            $fixture->team->languages()->attach([
                $fixture->englishLocale->language_id => ['locale_id' => $fixture->englishLocale->id],
                $fixture->frenchLocale->language_id => ['locale_id' => $fixture->frenchLocale->id],
                $abkhazian->id => ['locale_id' => $fixture->abkhazianLocale->id],
            ]);

            $fixture->createDefaultLanguageStrings();

            return $fixture;
        });
    }

    private function createDefaultLocale(string $isoAlpha2): Locale
    {
        $language = Language::where('iso_alpha2', $isoAlpha2)->firstOrFail();

        $locale = Locale::create([
            'language_id' => $language->id,
            'is_default' => true,
        ]);

        $locale->xlsformModuleVersions()->attach($this->moduleVersion->id);

        return $locale;
    }

    private function createDefaultLanguageStrings(): void
    {
        $labelType = LanguageStringType::where('name', 'label')->firstOrFail();

        $defaultLocales = collect([$this->englishLocale, $this->frenchLocale, $this->spanishLocale]);

        $defaultLocales->each(function (Locale $locale) use ($labelType): void {
            $languageName = $locale->language->name;

            $this->surveyRows->each(fn (SurveyRow $surveyRow) => LanguageString::create([
                'locale_id' => $locale->id,
                'language_string_type_id' => $labelType->id,
                'linked_entry_id' => $surveyRow->id,
                'linked_entry_type' => SurveyRow::class,
                'text' => "{$languageName} label for {$surveyRow->name}",
            ]));

            $this->choiceListEntries->each(fn (ChoiceListEntry $entry) => LanguageString::create([
                'locale_id' => $locale->id,
                'language_string_type_id' => $labelType->id,
                'linked_entry_id' => $entry->id,
                'linked_entry_type' => ChoiceListEntry::class,
                'text' => "{$languageName} label for {$entry->name}",
            ]));
        });
    }
}
