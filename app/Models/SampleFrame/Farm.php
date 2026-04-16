<?php

namespace App\Models\SampleFrame;

use App\Models\SurveyData\FarmSurveyData;
use App\Models\Team;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\IsPrimaryDataSubject;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasSubmissions;

class Farm extends Model implements IsPrimaryDataSubject
{
    use HasSubmissions;

    protected $casts = [
        'identifiers' => 'collection',
        'properties' => 'collection',
        'household_form_completed' => 'boolean',
        'fieldwork_form_completed' => 'boolean',
        'refused' => 'boolean',
    ];

    protected static function booted()
    {
        static::saved(function (self $location) {
            $location->owner->xlsforms()->update(['draft_needs_update' => true]);
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getCsvContentsForOdk(?WithXlsforms $team = null): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'location_name' => $this->location?->name,
            'team_code' => $this->team_code,
            'team_code_name' => $this->identifiers ? $this->identifiers['name'] . '(No. ' . $this->team_code . ')' : 'No. ' . $this->team_code,
            'name' => $this->identifiers ? $this->identifiers['name'] : '',
            'sex' => $this->properties ? $this->properties['sex'] : '',
            'year' => $this->properties ? $this->properties['year'] : '',
            'reserve' => $this->identifiers && $this->identifiers->has('reserve') ? $this->identifiers['reserve'] : '', // value is 0 = beneficiary farm that is not a reserve; 1 = beneficiary farm that is a reserve; '' = non-beneficiary farm.
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function farmSurveyData(): HasMany
    {
        return $this->hasMany(FarmSurveyData::class);
    }

    /** @return Attribute<string, never> */
    protected function identifyingAttribute(): Attribute
    {
        return new Attribute(
            get: fn() => $this->identifiers['name'] ?? ($this->identifiers->first() ?? null),
        );
    }

    public function updateCompletionStatus(): void
    {
        // check pilot completion
        $this->submissions
            ->filter(fn(Submission $submission) => $submission->test_data)
            ->each(function (Submission $submission) {

                if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Household Form')) {
                    $this->household_pilot_completed = true;
                }

                if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Fieldwork Form')) {
                    $this->fieldwork_pilot_completed = true;
                }

                $this->save();
            });

        $this->submissions
            ->filter(fn(Submission $submission) => !$submission->test_data)
            ->each(function (Submission $submission) {

                if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Household Form')) {
                    $this->household_form_completed = true;
                }

                if (Str::contains($submission->xlsformVersion->xlsform->xlsformTemplate->title, 'HOLPA Fieldwork Form')) {
                    $this->fieldwork_form_completed = true;
                }

                $this->save();
            });


    }
}
