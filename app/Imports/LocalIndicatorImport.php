<?php

namespace App\Imports;

use App\Models\Holpa\Domain;
use App\Models\Holpa\LocalIndicator;
use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithValidation;

class LocalIndicatorImport implements SkipsEmptyRows, ToCollection, WithHeadingRow, WithMultipleSheets, WithValidation
{
    public function __construct(public Team $team) {}

    public function sheets(): array
    {
        return [
            'indicators' => $this,
        ];
    }

    public function collection(Collection $rows): void
    {

        $domains = Domain::all();

        foreach ($rows as $row) {

            $domain = $domains->filter(fn (Domain $domain) => $domain->name === $row['domain'])->first(); // names should be unique

            LocalIndicator::create([
                'name' => $row['name'],
                'domain_id' => $domain->id,
                'team_id' => $this->team->id,
            ]);
        }
    }

    public function isEmptyWhen(array $row): bool
    {
        return $row['name'] === '';
    }

    public function rules(): array
    {
        return [
            'domain' => 'required|exists:domains,name',
            'name' => 'required|string|max:255',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'domain' => 'The domain field is required.',
            'name' => 'The name field is required.',
        ];
    }
}
