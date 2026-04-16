<?php

namespace App\Exports\DataExport;
use App\Models\SampleFrame\Farm;
use App\Models\Team;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Dataset;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Entity;

class DatasetExport implements FromCollection, WithHeadings, WithTitle
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
        return $this->entities->map(function (Entity $entity) {

            // get the farm_id and farm_name from the owner relationship
            /** @var Farm $farm */
            $farm = $entity->submission->primaryDataSubject;

            $row = [
                'farm_id' => $farm ? $farm->team_code : null,
                'farm_name' => $farm ? $farm->identifying_attribute : null,
            ];

            foreach ($this->headings as $heading) {
                $value = $entity->values->firstWhere('dataset_variable_name', $heading);
                $row[$heading] = $value ? $value->value : null;
            }
            return $row;
        });
    }

    public function headings(): array
    {
        return array_merge(['farm_id', 'farm_name'], $this->headings);
    }

    public function title(): string
    {
        return $this->dataset->name;
    }
}
