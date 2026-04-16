<?php

namespace Database\Seeders\Prep;

use App\Models\Reference\AgPracticeGroup;
use Illuminate\Database\Seeder;

class AgPracticeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cropGroup = AgPracticeGroup::create(['name' => 'Cropping Systems']);
        $riceGroup = AgPracticeGroup::create(['name' => 'Lowland Rice Systems']);
        $livestockGroup = AgPracticeGroup::create(['name' => 'Livestock Systems']);
        $fishGroup = AgPracticeGroup::create(['name' => 'Fish Systems']);

        $cropGroup->agPractices()->createMany([
            ['name' => 'Monocultures annual',],
            ['name' => 'Monocultures perennial',],
            ['name' => 'Agroforestry',],
            ['name' => 'Vegetation clearance',],
            ['name' => 'Green manure',],
            ['name' => 'Crop rotation',],
            ['name' => 'Embedded natural (hedgerows) - fallow',],
            ['name' => 'Embedded natural (hedgerows) - hedgerows',],
            ['name' => 'Homegarden',],
            ['name' => 'Intercropping',],
            ['name' => 'Vegetation clearance',],
            ['name' => 'Mulching',],
            ['name' => 'Embedded natural (hedgerows) - natural strips/vegetation',],
            ['name' => 'Embedded natural (hedgerows) - pollinator strips',],
            ['name' => 'Push-Pull',],
            ['name' => 'Other',],
            ['name' => 'Biochar',],
            ['name' => 'Drip irrigation',],
            ['name' => 'Microdosing',],
            ['name' => 'Reduced tillage',],
        ]);

        $riceGroup->agPractices()->createMany([
            ['name' => 'Rice_Biochar',],
            ['name' => 'Rice_Green manure',],
            ['name' => 'Rice_Microdosing',],
            ['name' => 'Rice-fish integration',],
            ['name' => 'Alternate wetting and drying',],
        ]);

        $livestockGroup->agPractices()->createMany([
            ['name' => 'Improved feed quality'],
            ['name' => 'Planting N-fixing legumes'],
            ['name' => 'Rotational grazing'],
        ]);

        $fishGroup->agPractices()->createMany([
            ['name' => 'Improved feed quality'],
        ]);
    }
}
