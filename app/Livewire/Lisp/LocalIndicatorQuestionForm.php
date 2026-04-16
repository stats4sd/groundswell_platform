<?php

namespace App\Livewire\Lisp;

use App\Filament\Shared\WithXlsformModuleVersionQuestionEditing;
use App\Models\Holpa\LocalIndicator;
use App\Services\HelperService;
use Awcodes\TableRepeater\Components\TableRepeater;
use Awcodes\TableRepeater\Header;
use Filament\Actions\StaticAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\LanguageStringType;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class LocalIndicatorQuestionForm extends Component implements HasActions, HasForms, HasTable
{
    use WithXlsformModuleVersionQuestionEditing;

    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithForms;
    use InteractsWithTable;

    public LocalIndicator $localIndicator;

    public XlsformModuleVersion $xlsformModuleVersion;

    public bool $expanded = true;

    public bool $processing = false;

    public array $data;

    public function mount(): void
    {
        $this->xlsformModuleVersion = $this->localIndicator->xlsformModuleVersion ?? $this->localIndicator->xlsformModuleVersion()->create([
            'name' => $this->localIndicator->name,
            'is_default' => false,
        ]);

        $this->localIndicator->xlsformModuleVersion()->associate($this->xlsformModuleVersion);
        $this->localIndicator->save();

        $this->xlsformModuleVersion->load(['surveyRows.languageStrings', 'surveyRows.choiceList.choiceListEntries.languageStrings']);

        $this->form->fill($this->xlsformModuleVersion->toArray());
    }

    // show questions in a table for better readability
    public function table(Table $table): Table
    {
        $locales = $this->localIndicator->team->locales;
        return $this->customModuleQuestionTable($table, $locales, $this->xlsformModuleVersion);
    }

    #[On('echo:xlsforms,.FilamentOdkLink.XlsformModuleVersionWasImported')]
    public function refreshTable()
    {
        $this->processing = false;
        $this->reset('xlsformModuleVersion');
    }

    #[On('XlsformModuleVersionProcessing')]
    public function disableEditing(): void
    {
        $this->processing = true;
    }


    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.lisp.local-indicator-question-form');
    }
}
