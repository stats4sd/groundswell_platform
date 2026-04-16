<?php

namespace App\Filament\Actions;

use App\Exports\DataExport\FarmSurveyDataExport;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportDataAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->action(function () {

            $filePath = $this->exportAll();

            return $this->download($filePath);
        });
    }

    public function exportAll(): string
    {

        // $filePath = 'HOLPA-data-export' . '-' . now()->toDateTimeString() . '.xlsx';
        $filePath = 'HOLPA-data-export.xlsx';
        Excel::store(new FarmSurveyDataExport, $filePath);

        return $filePath;
    }

    public function download(string $filePath): StreamedResponse
    {
        return Storage::download($filePath);
    }
}
