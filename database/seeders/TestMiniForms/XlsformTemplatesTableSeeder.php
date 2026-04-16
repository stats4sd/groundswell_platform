<?php

namespace Database\Seeders\TestMiniForms;

use DB;
use Illuminate\Database\Seeder;

class XlsformTemplatesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run(): void
    {


        DB::table('xlsform_templates')->delete();

        DB::table('xlsform_templates')->insert(array (
            0 =>
            array (
                'id' => 1,
                'title' => 'Test Mini HOLPA Household Survey',
                'description' => NULL,
                'available' => 1,
                'owner_id' => 1,
                'owner_type' => 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform',
                'odk_id' => 'test-mini-holpa-household-survey',
                'odk_draft_token' => 'd5IE17AXdR3OtfjCmAP5KrACjLwi6MGqdDFnzG5n4vPsI4HrvZgCaVddtFfGmFoA',
                'has_draft' => '1',
                'enketo_draft_id' => 'gNJf4V7KsZZBtdsKhGSISb5adDYbimV',
                'draft_needs_update' => 1,
                'odk_error' => NULL,
                'odk_version_id' => '2025-01-07 16:56:14',
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "time_frame", "path": "/time_frame", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "coffee", "path": "/coffee", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "coffee_number", "path": "/coffee_number", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "drinks", "path": "/drinks", "type": "string", "binary": null, "value_type": "select_multiple drinks", "selectMultiple": true}, {"name": "drinks_rpt_count", "path": "/drinks_rpt_count", "type": "string", "binary": null, "selectMultiple": null}, {"name": "drinks_rpt", "path": "/drinks_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_id", "path": "/drinks_rpt/drink_id", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drinks_rpt_grp", "path": "/drinks_rpt/drinks_rpt_grp", "type": "structure", "binary": null, "value_type": "begin group", "selectMultiple": null}, {"name": "drink_name", "path": "/drinks_rpt/drinks_rpt_grp/drink_name", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drink_lk", "path": "/drinks_rpt/drinks_rpt_grp/drink_lk", "type": "string", "binary": null, "value_type": "select_one lk", "selectMultiple": null}, {"name": "why_drink", "path": "/drinks_rpt/drinks_rpt_grp/why_drink", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "drink_comment_rpt", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}]',
                'main_dataset_id' => NULL,
                'created_at' => '2025-01-07 16:56:14',
                'updated_at' => '2025-01-07 17:15:45',
                'deleted_at' => NULL,
            ),
            1 =>
            array (
                'id' => 2,
                'title' => 'Test Mini HOLPA Fieldwork Survey',
                'description' => NULL,
                'available' => 1,
                'owner_id' => 1,
                'owner_type' => 'Stats4sd\\FilamentOdkLink\\Models\\OdkLink\\Platform',
                'odk_id' => 'test-mini-holpa-fieldwork-survey',
                'odk_draft_token' => 'J!olL6xdC0ipL2Th5U2GXDOU9xChQ2StZNuTe8qtcDHUfIkmmXXrSg8YgQtLPlpt',
                'has_draft' => '1',
                'enketo_draft_id' => 'vfgAB4s38sCszMqT3vWZGxcezMZNMMv',
                'draft_needs_update' => 0,
                'odk_error' => NULL,
                'odk_version_id' => '2025-01-07 17:00:41',
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork", "path": "/fieldwork", "type": "structure", "binary": null, "value_type": "begin_group", "selectMultiple": null}, {"name": "field_count", "path": "/fieldwork/field_count", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork_repeat", "path": "/fieldwork/fieldwork_repeat", "type": "repeat", "binary": null, "value_type": "begin_repeat", "selectMultiple": null}, {"name": "soil_type", "path": "/fieldwork/fieldwork_repeat/soil_type", "type": "string", "binary": null, "value_type": "select_one soils", "selectMultiple": null}, {"name": "crop_types", "path": "/fieldwork/fieldwork_repeat/crop_types", "type": "string", "binary": null, "value_type": "select_one crops", "selectMultiple": null}, {"name": "interesting_features", "path": "/fieldwork/fieldwork_repeat/interesting_features", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}]',
                'main_dataset_id' => NULL,
                'created_at' => '2025-01-07 17:00:40',
                'updated_at' => '2025-01-07 17:15:46',
                'deleted_at' => NULL,
            ),
        ));


    }
}
