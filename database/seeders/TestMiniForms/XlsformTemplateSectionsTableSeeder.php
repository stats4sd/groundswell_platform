<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class XlsformTemplateSectionsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('xlsform_template_sections')->delete();

        DB::table('xlsform_template_sections')->insert(array (
            0 =>
            array (
                'id' => 1,
                'dataset_id' => 16,
                'xlsform_template_id' => 1,
                'parent_id' => NULL,
                'structure_item' => 'drinks_rpt',
                'is_repeat' => 1,
                'schema' => '{"13": {"name": "drink_id", "path": "/drinks_rpt/drink_id", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, "14": {"name": "drinks_rpt_grp", "path": "/drinks_rpt/drinks_rpt_grp", "type": "structure", "binary": null, "value_type": "begin group", "selectMultiple": null}, "15": {"name": "drink_name", "path": "/drinks_rpt/drinks_rpt_grp/drink_name", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, "16": {"name": "drink_lk", "path": "/drinks_rpt/drinks_rpt_grp/drink_lk", "type": "string", "binary": null, "value_type": "select_one lk", "selectMultiple": null}, "17": {"name": "why_drink", "path": "/drinks_rpt/drinks_rpt_grp/why_drink", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, "19": {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}}',
                'is_current' => 1,
                'created_at' => '2025-01-07 16:56:16',
                'updated_at' => '2025-01-07 16:56:16',
            ),
            1 =>
            array (
                'id' => 2,
                'dataset_id' => 17,
                'xlsform_template_id' => 1,
                'parent_id' => NULL,
                'structure_item' => 'drink_comment_rpt',
                'is_repeat' => 1,
                'schema' => '{"19": {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}}',
                'is_current' => 1,
                'created_at' => '2025-01-07 16:56:16',
                'updated_at' => '2025-01-07 16:56:16',
            ),
            2 =>
            array (
                'id' => 3,
                'dataset_id' => 18,
                'xlsform_template_id' => 1,
                'parent_id' => NULL,
                'structure_item' => 'root',
                'is_repeat' => 0,
                'schema' => '{"0": {"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, "1": {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, "2": {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, "3": {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, "4": {"name": "time_frame", "path": "/time_frame", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, "5": {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, "6": {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, "7": {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, "8": {"name": "coffee", "path": "/coffee", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, "9": {"name": "coffee_number", "path": "/coffee_number", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, "10": {"name": "drinks", "path": "/drinks", "type": "string", "binary": null, "value_type": "select_multiple drinks", "selectMultiple": true}, "11": {"name": "drinks_rpt_count", "path": "/drinks_rpt_count", "type": "string", "binary": null, "selectMultiple": null}, "20": {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, "21": {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, "22": {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, "23": {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, "25": {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}}',
                'is_current' => 1,
                'created_at' => '2025-01-07 16:56:16',
                'updated_at' => '2025-01-07 16:56:16',
            ),
            3 =>
            array (
                'id' => 4,
                'dataset_id' => 20,
                'xlsform_template_id' => 2,
                'parent_id' => NULL,
                'structure_item' => 'fieldwork_repeat',
                'is_repeat' => 1,
                'schema' => '{"10": {"name": "soil_type", "path": "/fieldwork/fieldwork_repeat/soil_type", "type": "string", "binary": null, "value_type": "select_one soils", "selectMultiple": null}, "11": {"name": "crop_types", "path": "/fieldwork/fieldwork_repeat/crop_types", "type": "string", "binary": null, "value_type": "select_one crops", "selectMultiple": null}, "12": {"name": "interesting_features", "path": "/fieldwork/fieldwork_repeat/interesting_features", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}}',
                'is_current' => 1,
                'created_at' => '2025-01-07 17:00:42',
                'updated_at' => '2025-01-07 17:00:42',
            ),
            4 =>
            array (
                'id' => 5,
                'dataset_id' => 19,
                'xlsform_template_id' => 2,
                'parent_id' => NULL,
                'structure_item' => 'root',
                'is_repeat' => 0,
                'schema' => '{"0": {"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, "1": {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, "2": {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, "3": {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, "4": {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, "5": {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, "6": {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, "8": {"name": "field_count", "path": "/fieldwork/field_count", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, "13": {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, "14": {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, "15": {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, "16": {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, "18": {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}}',
                'is_current' => 1,
                'created_at' => '2025-01-07 17:00:42',
                'updated_at' => '2025-01-07 17:00:42',
            ),
        ));


    }
}
