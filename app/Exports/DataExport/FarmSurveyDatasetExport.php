<?php

namespace App\Exports\DataExport;

use App\Models\Dataset;
use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class FarmSurveyDatasetExport implements FromCollection, WithHeadings, WithTitle
{

    public array $headings;
    public Collection $entities;


    public function __construct(public Team $team, public Dataset $dataset)
    {
        $this->headings = $this->dataset->variables()
            ->orderBy('order')
            ->pluck('name')->toArray();

        $this->entities = $this->dataset->entities()
            ->whereHas('owner', function ($query) {
                $query->where('teams.id', $this->team->id);
            })
            ->with('values')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection(): Collection
    {
        return $this->entities->map(function ($entity) {
            $row = [];
            foreach ($this->headings as $heading) {
                $value = $entity->values->firstWhere('dataset_variable_name', $heading);
                $row[$heading] = $value ? $value->value : null;
            }
            return $row;
        });
    }

    public function headings(): array
    {
        return $this->headings;
    }


    public function title(): string
    {
        return 'Farm Survey Data';
    }
}
