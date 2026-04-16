<?php

namespace App\Imports;

use App\Filament\App\Clusters\Localisations\Resources\ChoiceListEntryResource\Widgets\ChoiceListEntriesInfo;
use App\Jobs\NotifyUserThatLanguageImportIsFailed;
use App\Models\User;
use App\Rules\TranslationStringHasEntity;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithUpserts;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Row;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\LanguageString;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class XlsformTemplateLanguageImport implements ToModel, WithUpserts, SkipsEmptyRows, WithStrictNullComparison, ShouldQueue, WithChunkReading, WithCalculatedFormulas, WithEvents, WithValidation
{
    protected Collection $languageStringTypes;

    public function __construct(public Locale $locale, public XlsformTemplate $xlsformTemplate, public User $importedBy)
    {
        // get these once to avoid n+1 database queries
        $this->languageStringTypes = LanguageStringType::all();
    }

    /**
     * @throws Exception
     */
    public function model(array $row): LanguageString
    {

        //Validator::make($row, $this->rules())->validate();

        // Find the corresponding language string type
        $languageStringType = $this->languageStringTypes->where('name', $row[4])->first();

        $entityModelClass = match ($row[0]) {
            'survey' => SurveyRow::class,
            'choices' => ChoiceListEntry::class,
            default => null,
        };

        $tableName = match ($row[0]) {
            'survey' => 'survey_rows',
            'choices' => 'choice_list_entries',
            default => null,
        };

        return new LanguageString([
            'locale_id' => $this->locale->id,
            'language_string_type_id' => $languageStringType->id,
            'linked_entry_id' => $row[2],
            'linked_entry_type' => $entityModelClass,
            'text' => $row[6],
           // 'updated_during_import' => 1, - TODO: do we need this?
            ]);

    }

    public function uniqueBy(): array
    {
        return [
            'locale_id',
            'language_string_type_id',
            'linked_entry_id',
            'linked_entry_type',
        ];
    }

    public function isEmptyWhen(array $row): bool
    {
        // skip when entity id or name is null;
        // or when first col is 'row type' (that is the header)
        return $row[2] === '' || $row[3] === '' || $row[0] === 'row type';
    }

    public function chunkSize(): int
    {
        return 200;
    }

    public function rules(): array
    {

        return [

            //  row type
            '0' => [
                'required',
                Rule::in(['survey', 'choices']),
            ],

            // translation type
            '4' => [
                Rule::in(LanguageStringType::all()->pluck('name')->toArray()),
            ],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {

        $validator->after(function ($validator) {
            $data = $validator->getData();
            $row = collect($data)->first();

            // check entity exists
            $entityModelClass = match ($row[0]) {
                'survey' => SurveyRow::class,
                'choices' => ChoiceListEntry::class,
                default => null,
            };

            $entityModel = $entityModelClass::find($row[2]);

            if(!$entityModel) {

                $validator->errors()->add(2, "The {$row[0]} with the name: <b>{$row[3]}</b> did not match a valid survey question or choice list entry. <br/><br/>

                Please ensure you are using the correct template to upload the translations.");
            }

        });



    }

    public function registerEvents(): array
    {
        return [
            ImportFailed::class => function (ImportFailed $event) {
                NotifyUserThatLanguageImportIsFailed::dispatch($this->locale, $this->xlsformTemplate, $this->importedBy, $event->getException()->getMessage());
            },
        ];
    }
}
