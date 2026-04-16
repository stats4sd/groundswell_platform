<?php

namespace Database\Seeders\Prep;

use App\Models\Reference\AgPractice;
use App\Models\Reference\ClimateMitigationScore;
use Illuminate\Database\Seeder;

class ClimateMitigationScoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Monocultures annual')->first()->id,
            'score' => 2.666666667,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Monocultures perennial')->first()->id,
            'score' => 3.833333333,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Agroforestry')->first()->id,
            'score' => 4.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Vegetation clearance')->first()->id,
            'score' => 1.666666667,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Green manure')->first()->id,
            'score' => 3.0,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Crop rotation')->first()->id,
            'score' => 3.0,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Embedded natural (hedgerows) - fallow')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Embedded natural (hedgerows) - hedgerows')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Homegarden')->first()->id,
            'score' => 4.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Intercropping')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Vegetation clearance')->first()->id,
            'score' => 1.666666667,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Mulching')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Embedded natural (hedgerows) - natural strips/vegetation')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Embedded natural (hedgerows) - pollinator strips')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Push-Pull')->first()->id,
            'score' => 4.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Other')->first()->id,
            'score' => 3.630888889,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Biochar')->first()->id,
            'score' => 2.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Drip irrigation')->first()->id,
            'score' => 2.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Microdosing')->first()->id,
            'score' => 2.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Reduced tillage')->first()->id,
            'score' => 3.66,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Rice_Biochar')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Rice_Green manure')->first()->id,
            'score' => 4.0,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Rice_Microdosing')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Rice-fish integration')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Alternate wetting and drying')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Improved feed quality')->first()->id,
            'score' => 4.0,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Planting N-fixing legumes')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Rotational grazing')->first()->id,
            'score' => 3.33,
        ]);
        ClimateMitigationScore::create([
            'region_id' => '202',
            'ag_practice_id' => AgPractice::where('name', 'Improved feed quality')->first()->id,
            'score' => 3.0,
        ]);

    }
}
