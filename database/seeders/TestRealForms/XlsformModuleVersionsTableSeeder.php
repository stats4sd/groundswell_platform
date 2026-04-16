<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class XlsformModuleVersionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('xlsform_module_versions')->delete();

        \DB::table('xlsform_module_versions')->insert([
            0 =>
                [
                    'id' => 1,
                    'xlsform_module_id' => 1,
                    'owner_id' => NULL,
                    'name' => 'Global metadata',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            1 =>
                [
                    'id' => 2,
                    'xlsform_module_id' => 2,
                    'owner_id' => NULL,
                    'name' => 'Global location',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            2 =>
                [
                    'id' => 3,
                    'xlsform_module_id' => 3,
                    'owner_id' => NULL,
                    'name' => 'Global introduction',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            3 =>
                [
                    'id' => 4,
                    'xlsform_module_id' => 4,
                    'owner_id' => NULL,
                    'name' => 'Global agroecology_perspective',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            4 =>
                [
                    'id' => 5,
                    'xlsform_module_id' => 5,
                    'owner_id' => NULL,
                    'name' => 'Global human_wellbeing',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            5 =>
                [
                    'id' => 6,
                    'xlsform_module_id' => 6,
                    'owner_id' => NULL,
                    'name' => 'Global farmer_agency',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            6 =>
                [
                    'id' => 7,
                    'xlsform_module_id' => 7,
                    'owner_id' => NULL,
                    'name' => 'Global knowledge_co_creation',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            7 =>
                [
                    'id' => 8,
                    'xlsform_module_id' => 8,
                    'owner_id' => NULL,
                    'name' => 'Global governance',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            8 =>
                [
                    'id' => 9,
                    'xlsform_module_id' => 9,
                    'owner_id' => NULL,
                    'name' => 'Global participation',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            9 =>
                [
                    'id' => 10,
                    'xlsform_module_id' => 10,
                    'owner_id' => NULL,
                    'name' => 'Global land_tenure',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            10 =>
                [
                    'id' => 11,
                    'xlsform_module_id' => 11,
                    'owner_id' => NULL,
                    'name' => 'Global resilience',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            11 =>
                [
                    'id' => 12,
                    'xlsform_module_id' => 12,
                    'owner_id' => NULL,
                    'name' => 'Global income',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            12 =>
                [
                    'id' => 13,
                    'xlsform_module_id' => 13,
                    'owner_id' => NULL,
                    'name' => 'Global assets',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            13 =>
                [
                    'id' => 14,
                    'xlsform_module_id' => 14,
                    'owner_id' => NULL,
                    'name' => 'Global access_to_credit',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:29',
                    'updated_at' => '2025-06-16 17:27:29',
                ],
            14 =>
                [
                    'id' => 15,
                    'xlsform_module_id' => 15,
                    'owner_id' => NULL,
                    'name' => 'Global diet_quality',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            15 =>
                [
                    'id' => 16,
                    'xlsform_module_id' => 16,
                    'owner_id' => NULL,
                    'name' => 'Global socio_economic',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            16 =>
                [
                    'id' => 17,
                    'xlsform_module_id' => 17,
                    'owner_id' => NULL,
                    'name' => 'Global biodiversity',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            17 =>
                [
                    'id' => 18,
                    'xlsform_module_id' => 18,
                    'owner_id' => NULL,
                    'name' => 'Global farm_characteristics',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            18 =>
                [
                    'id' => 19,
                    'xlsform_module_id' => 19,
                    'owner_id' => NULL,
                    'name' => 'Global labour',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            19 =>
                [
                    'id' => 20,
                    'xlsform_module_id' => 20,
                    'owner_id' => NULL,
                    'name' => 'Global crop_production',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            20 =>
                [
                    'id' => 21,
                    'xlsform_module_id' => 21,
                    'owner_id' => NULL,
                    'name' => 'Global soil_characteristics',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            21 =>
                [
                    'id' => 22,
                    'xlsform_module_id' => 22,
                    'owner_id' => NULL,
                    'name' => 'Global inputs_subsidy',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            22 =>
                [
                    'id' => 23,
                    'xlsform_module_id' => 23,
                    'owner_id' => NULL,
                    'name' => 'Global livestock_production',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            23 =>
                [
                    'id' => 24,
                    'xlsform_module_id' => 24,
                    'owner_id' => NULL,
                    'name' => 'Global fish_production',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            24 =>
                [
                    'id' => 25,
                    'xlsform_module_id' => 25,
                    'owner_id' => NULL,
                    'name' => 'Global synergies',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            25 =>
                [
                    'id' => 26,
                    'xlsform_module_id' => 26,
                    'owner_id' => NULL,
                    'name' => 'Global climate_change',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            26 =>
                [
                    'id' => 27,
                    'xlsform_module_id' => 27,
                    'owner_id' => NULL,
                    'name' => 'Global water',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            27 =>
                [
                    'id' => 28,
                    'xlsform_module_id' => 28,
                    'owner_id' => NULL,
                    'name' => 'Global energy',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            28 =>
                [
                    'id' => 29,
                    'xlsform_module_id' => 29,
                    'owner_id' => NULL,
                    'name' => 'Global survey_end',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:27:30',
                    'updated_at' => '2025-06-16 17:27:30',
                ],
            29 =>
                [
                    'id' => 30,
                    'xlsform_module_id' => 30,
                    'owner_id' => NULL,
                    'name' => 'Global fieldwork_metadata',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:29:15',
                    'updated_at' => '2025-06-16 17:29:15',
                ],
            30 =>
                [
                    'id' => 31,
                    'xlsform_module_id' => 31,
                    'owner_id' => NULL,
                    'name' => 'Global location',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:29:15',
                    'updated_at' => '2025-06-16 17:29:15',
                ],
            31 =>
                [
                    'id' => 32,
                    'xlsform_module_id' => 32,
                    'owner_id' => NULL,
                    'name' => 'Global fieldwork_introduction',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:29:15',
                    'updated_at' => '2025-06-16 17:29:15',
                ],
            32 =>
                [
                    'id' => 33,
                    'xlsform_module_id' => 33,
                    'owner_id' => NULL,
                    'name' => 'Global fieldwork_site_info',
                    'is_default' => 1,
                    'country_id' => NULL,
                    'processing' => 0,
                    'created_at' => '2025-06-16 17:29:15',
                    'updated_at' => '2025-06-16 17:29:15',
                ],
            33 => [
                'id' => 34,
                'xlsform_module_id' => null,
                'owner_id' => 3,
                'name' => 'Local Context',
                'is_default' => 0,
                'country_id' => NULL,
                'processing' => 0,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ],
            34 => [
                'id' => 35,
                'xlsform_module_id' => null,
                'owner_id' => 4,
                'name' => 'Local Context',
                'is_default' => 0,
                'country_id' => NULL,
                'processing' => 0,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ],
            35 => [
                'id' => 36,
                'xlsform_module_id' => null,
                'owner_id' => 5,
                'name' => 'Local Context',
                'is_default' => 0,
                'country_id' => NULL,
                'processing' => 0,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ],

        ]);


    }
}
