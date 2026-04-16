<?php

namespace Database\Seeders\TestRealForms;

use Illuminate\Database\Seeder;

class XlsformModulesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('xlsform_modules')->delete();

        \DB::table('xlsform_modules')->insert(array (
            0 =>
            array (
                'id' => 1,
                'xlsform_template_id' => 1,
                'label' => 'metadata',
                'name' => 'metadata',
                'default_order' => 1,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            1 =>
            array (
                'id' => 2,
                'xlsform_template_id' => 1,
                'label' => 'location',
                'name' => 'location',
                'default_order' => 2,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            2 =>
            array (
                'id' => 3,
                'xlsform_template_id' => 1,
                'label' => 'introduction',
                'name' => 'introduction',
                'default_order' => 3,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            3 =>
            array (
                'id' => 4,
                'xlsform_template_id' => 1,
                'label' => 'agroecology_perspective',
                'name' => 'agroecology_perspective',
                'default_order' => 4,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            4 =>
            array (
                'id' => 5,
                'xlsform_template_id' => 1,
                'label' => 'human_wellbeing',
                'name' => 'human_wellbeing',
                'default_order' => 5,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            5 =>
            array (
                'id' => 6,
                'xlsform_template_id' => 1,
                'label' => 'farmer_agency',
                'name' => 'farmer_agency',
                'default_order' => 6,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            6 =>
            array (
                'id' => 7,
                'xlsform_template_id' => 1,
                'label' => 'knowledge_co_creation',
                'name' => 'knowledge_co_creation',
                'default_order' => 7,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            7 =>
            array (
                'id' => 8,
                'xlsform_template_id' => 1,
                'label' => 'governance',
                'name' => 'governance',
                'default_order' => 8,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            8 =>
            array (
                'id' => 9,
                'xlsform_template_id' => 1,
                'label' => 'participation',
                'name' => 'participation',
                'default_order' => 9,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            9 =>
            array (
                'id' => 10,
                'xlsform_template_id' => 1,
                'label' => 'land_tenure',
                'name' => 'land_tenure',
                'default_order' => 10,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            10 =>
            array (
                'id' => 11,
                'xlsform_template_id' => 1,
                'label' => 'resilience',
                'name' => 'resilience',
                'default_order' => 11,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            11 =>
            array (
                'id' => 12,
                'xlsform_template_id' => 1,
                'label' => 'income',
                'name' => 'income',
                'default_order' => 12,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            12 =>
            array (
                'id' => 13,
                'xlsform_template_id' => 1,
                'label' => 'assets',
                'name' => 'assets',
                'default_order' => 13,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            13 =>
            array (
                'id' => 14,
                'xlsform_template_id' => 1,
                'label' => 'access_to_credit',
                'name' => 'access_to_credit',
                'default_order' => 14,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            14 =>
            array (
                'id' => 15,
                'xlsform_template_id' => 1,
                'label' => 'diet_quality',
                'name' => 'diet_quality',
                'default_order' => 15,
                'created_at' => '2025-06-16 17:27:29',
                'updated_at' => '2025-06-16 17:27:29',
            ),
            15 =>
            array (
                'id' => 16,
                'xlsform_template_id' => 1,
                'label' => 'socio_economic',
                'name' => 'socio_economic',
                'default_order' => 16,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            16 =>
            array (
                'id' => 17,
                'xlsform_template_id' => 1,
                'label' => 'biodiversity',
                'name' => 'biodiversity',
                'default_order' => 17,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            17 =>
            array (
                'id' => 18,
                'xlsform_template_id' => 1,
                'label' => 'farm_characteristics',
                'name' => 'farm_characteristics',
                'default_order' => 18,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            18 =>
            array (
                'id' => 19,
                'xlsform_template_id' => 1,
                'label' => 'labour',
                'name' => 'labour',
                'default_order' => 19,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            19 =>
            array (
                'id' => 20,
                'xlsform_template_id' => 1,
                'label' => 'crop_production',
                'name' => 'crop_production',
                'default_order' => 20,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            20 =>
            array (
                'id' => 21,
                'xlsform_template_id' => 1,
                'label' => 'soil_characteristics',
                'name' => 'soil_characteristics',
                'default_order' => 21,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            21 =>
            array (
                'id' => 22,
                'xlsform_template_id' => 1,
                'label' => 'inputs_subsidy',
                'name' => 'inputs_subsidy',
                'default_order' => 22,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            22 =>
            array (
                'id' => 23,
                'xlsform_template_id' => 1,
                'label' => 'livestock_production',
                'name' => 'livestock_production',
                'default_order' => 23,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            23 =>
            array (
                'id' => 24,
                'xlsform_template_id' => 1,
                'label' => 'fish_production',
                'name' => 'fish_production',
                'default_order' => 24,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            24 =>
            array (
                'id' => 25,
                'xlsform_template_id' => 1,
                'label' => 'synergies',
                'name' => 'synergies',
                'default_order' => 25,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            25 =>
            array (
                'id' => 26,
                'xlsform_template_id' => 1,
                'label' => 'climate_change',
                'name' => 'climate_change',
                'default_order' => 26,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            26 =>
            array (
                'id' => 27,
                'xlsform_template_id' => 1,
                'label' => 'water',
                'name' => 'water',
                'default_order' => 27,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            27 =>
            array (
                'id' => 28,
                'xlsform_template_id' => 1,
                'label' => 'energy',
                'name' => 'energy',
                'default_order' => 28,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            28 =>
            array (
                'id' => 29,
                'xlsform_template_id' => 1,
                'label' => 'survey_end',
                'name' => 'survey_end',
                'default_order' => 29,
                'created_at' => '2025-06-16 17:27:30',
                'updated_at' => '2025-06-16 17:27:30',
            ),
            29 =>
            array (
                'id' => 30,
                'xlsform_template_id' => 2,
                'label' => 'fieldwork_metadata',
                'name' => 'fieldwork_metadata',
                'default_order' => 1,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ),
            30 =>
            array (
                'id' => 31,
                'xlsform_template_id' => 2,
                'label' => 'location',
                'name' => 'location',
                'default_order' => 2,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ),
            31 =>
            array (
                'id' => 32,
                'xlsform_template_id' => 2,
                'label' => 'fieldwork_introduction',
                'name' => 'fieldwork_introduction',
                'default_order' => 3,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ),
            32 =>
            array (
                'id' => 33,
                'xlsform_template_id' => 2,
                'label' => 'fieldwork_site_info',
                'name' => 'fieldwork_site_info',
                'default_order' => 4,
                'created_at' => '2025-06-16 17:29:15',
                'updated_at' => '2025-06-16 17:29:15',
            ),
        ));


    }
}
