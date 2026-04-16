<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class ChoiceListEntriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('choice_list_entries')->delete();

        DB::table('choice_list_entries')->insert(array (
            0 =>
            array (
                'id' => 1,
                'choice_list_id' => 1,
                'owner_id' => NULL,
                'name' => '1',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:20',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            1 =>
            array (
                'id' => 2,
                'choice_list_id' => 1,
                'owner_id' => NULL,
                'name' => '0',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:20',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            2 =>
            array (
                'id' => 3,
                'choice_list_id' => 2,
                'owner_id' => NULL,
                'name' => 'black_tea',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            3 =>
            array (
                'id' => 4,
                'choice_list_id' => 2,
                'owner_id' => NULL,
                'name' => 'green_tea',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            4 =>
            array (
                'id' => 5,
                'choice_list_id' => 2,
                'owner_id' => NULL,
                'name' => 'cola',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            5 =>
            array (
                'id' => 6,
                'choice_list_id' => 2,
                'owner_id' => NULL,
                'name' => 'diet_cola',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            6 =>
            array (
                'id' => 7,
                'choice_list_id' => 2,
                'owner_id' => NULL,
                'name' => 'juice',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            7 =>
            array (
                'id' => 8,
                'choice_list_id' => 3,
                'owner_id' => NULL,
                'name' => 'very_good',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            8 =>
            array (
                'id' => 9,
                'choice_list_id' => 3,
                'owner_id' => NULL,
                'name' => 'good',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            9 =>
            array (
                'id' => 10,
                'choice_list_id' => 3,
                'owner_id' => NULL,
                'name' => 'neutral',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            10 =>
            array (
                'id' => 11,
                'choice_list_id' => 3,
                'owner_id' => NULL,
                'name' => 'bad',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            11 =>
            array (
                'id' => 12,
                'choice_list_id' => 3,
                'owner_id' => NULL,
                'name' => 'very_bad',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:21',
                'updated_at' => '2025-01-07 16:56:27',
            ),
            12 =>
            array (
                'id' => 13,
                'choice_list_id' => 4,
                'owner_id' => NULL,
                'name' => '1',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:22',
                'updated_at' => '2025-01-07 16:56:28',
            ),
            13 =>
            array (
                'id' => 14,
                'choice_list_id' => 4,
                'owner_id' => NULL,
                'name' => '0',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 16:56:22',
                'updated_at' => '2025-01-07 16:56:28',
            ),
            14 =>
            array (
                'id' => 15,
                'choice_list_id' => 5,
                'owner_id' => NULL,
                'name' => '1',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            15 =>
            array (
                'id' => 16,
                'choice_list_id' => 5,
                'owner_id' => NULL,
                'name' => '0',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            16 =>
            array (
                'id' => 17,
                'choice_list_id' => 6,
                'owner_id' => NULL,
                'name' => 'clay',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            17 =>
            array (
                'id' => 18,
                'choice_list_id' => 6,
                'owner_id' => NULL,
                'name' => 'loam',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            18 =>
            array (
                'id' => 19,
                'choice_list_id' => 6,
                'owner_id' => NULL,
                'name' => 'sand',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            19 =>
            array (
                'id' => 20,
                'choice_list_id' => 6,
                'owner_id' => NULL,
                'name' => 'silt',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            20 =>
            array (
                'id' => 21,
                'choice_list_id' => 6,
                'owner_id' => NULL,
                'name' => 'other',
                'properties' => '[]',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            21 =>
            array (
                'id' => 22,
                'choice_list_id' => 7,
                'owner_id' => NULL,
                'name' => 'wheat',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            22 =>
            array (
                'id' => 23,
                'choice_list_id' => 7,
                'owner_id' => NULL,
                'name' => 'maize',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            23 =>
            array (
                'id' => 24,
                'choice_list_id' => 7,
                'owner_id' => NULL,
                'name' => 'sorghum',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            24 =>
            array (
                'id' => 25,
                'choice_list_id' => 7,
                'owner_id' => NULL,
                'name' => 'quinoa',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
            25 =>
            array (
                'id' => 26,
                'choice_list_id' => 7,
                'owner_id' => NULL,
                'name' => 'cowpea',
                'properties' => '{"localisable": "yes"}',
                'cascade_filter' => NULL,
                'updated_during_import' => 0,
                'created_at' => '2025-01-07 17:00:48',
                'updated_at' => '2025-01-07 17:00:51',
            ),
        ));


    }
}
