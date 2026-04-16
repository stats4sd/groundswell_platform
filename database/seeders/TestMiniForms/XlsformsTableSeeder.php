<?php

namespace Database\Seeders\TestMiniForms;

use Illuminate\Database\Seeder;

class XlsformsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('xlsforms')->delete();

        \DB::table('xlsforms')->insert(array (
            0 =>
            array (
                'id' => 8,
                'xlsform_template_id' => 1,
                'owner_id' => 3,
                'odk_project_id' => 1447,
                'title' => 'Test Mini HOLPA Household Survey',
                'odk_id' => 'test-mini-holpa-household-survey',
                'odk_draft_token' => 'ff1!Wpah0tK05eoEQy4xIqq18pa7n6pm3VpeqHZCirScCDA00kXWyCjXXgDimDpp',
                'odk_version_id' => '2025-02-12 11:53:12',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => 'S1AgfdTdgC32QafurcCPFTJBsxbqpuF',
                'enketo_id' => 'OJPiZWfcn8ChEhaFDavgdd8Y4jiKLYR',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "time_frame", "path": "/time_frame", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "coffee", "path": "/coffee", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "coffee_number", "path": "/coffee_number", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "drinks", "path": "/drinks", "type": "string", "binary": null, "value_type": "select_multiple drinks", "selectMultiple": true}, {"name": "drinks_rpt_count", "path": "/drinks_rpt_count", "type": "string", "binary": null, "selectMultiple": null}, {"name": "drinks_rpt", "path": "/drinks_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_id", "path": "/drinks_rpt/drink_id", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drinks_rpt_grp", "path": "/drinks_rpt/drinks_rpt_grp", "type": "structure", "binary": null, "value_type": "begin group", "selectMultiple": null}, {"name": "drink_name", "path": "/drinks_rpt/drinks_rpt_grp/drink_name", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drink_lk", "path": "/drinks_rpt/drinks_rpt_grp/drink_lk", "type": "string", "binary": null, "value_type": "select_one lk", "selectMultiple": null}, {"name": "why_drink", "path": "/drinks_rpt/drinks_rpt_grp/why_drink", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "drink_comment_rpt", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-02-12 11:53:12',
            ),
            1 =>
            array (
                'id' => 9,
                'xlsform_template_id' => 1,
                'owner_id' => 4,
                'odk_project_id' => 1448,
                'title' => 'Test Mini HOLPA Household Survey',
                'odk_id' => 'test-mini-holpa-household-survey',
                'odk_draft_token' => 'uuBuTXpnmHL!xAKSlodgjqbeImpKOwjhs5T3ZvpvsZ4ep!zAsFW4GIKLiU0nD5Ze',
                'odk_version_id' => '2025-02-12 11:21:04',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => '6kLtDdw59xrR6cU9NsANHiSlsnbyuKb',
                'enketo_id' => 'o6GY2dMdII7WVWjX9VH7anciREcaOhk',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "time_frame", "path": "/time_frame", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "coffee", "path": "/coffee", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "coffee_number", "path": "/coffee_number", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "drinks", "path": "/drinks", "type": "string", "binary": null, "value_type": "select_multiple drinks", "selectMultiple": true}, {"name": "drinks_rpt_count", "path": "/drinks_rpt_count", "type": "string", "binary": null, "selectMultiple": null}, {"name": "drinks_rpt", "path": "/drinks_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_id", "path": "/drinks_rpt/drink_id", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drinks_rpt_grp", "path": "/drinks_rpt/drinks_rpt_grp", "type": "structure", "binary": null, "value_type": "begin group", "selectMultiple": null}, {"name": "drink_name", "path": "/drinks_rpt/drinks_rpt_grp/drink_name", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drink_lk", "path": "/drinks_rpt/drinks_rpt_grp/drink_lk", "type": "string", "binary": null, "value_type": "select_one lk", "selectMultiple": null}, {"name": "why_drink", "path": "/drinks_rpt/drinks_rpt_grp/why_drink", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "drink_comment_rpt", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-02-12 11:21:04',
            ),
            2 =>
            array (
                'id' => 10,
                'xlsform_template_id' => 1,
                'owner_id' => 5,
                'odk_project_id' => 1449,
                'title' => 'Test Mini HOLPA Household Survey',
                'odk_id' => 'test-mini-holpa-household-survey',
                'odk_draft_token' => 'rZHXa3i8kYOsR2Ah4TId!$oN5ssMx4VaHylKkS2ePyD70f!E80zYIjamF$QamTFy',
                'odk_version_id' => '2025-02-12 11:21:17',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => 'Lz2gzooONaEvOas4DKRdy3eKbI5CTyZ',
                'enketo_id' => '7nkf0aiEaiJVPthZ95B2pHUKGJ7pTwy',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "time_frame", "path": "/time_frame", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "coffee", "path": "/coffee", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "coffee_number", "path": "/coffee_number", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "drinks", "path": "/drinks", "type": "string", "binary": null, "value_type": "select_multiple drinks", "selectMultiple": true}, {"name": "drinks_rpt_count", "path": "/drinks_rpt_count", "type": "string", "binary": null, "selectMultiple": null}, {"name": "drinks_rpt", "path": "/drinks_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_id", "path": "/drinks_rpt/drink_id", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drinks_rpt_grp", "path": "/drinks_rpt/drinks_rpt_grp", "type": "structure", "binary": null, "value_type": "begin group", "selectMultiple": null}, {"name": "drink_name", "path": "/drinks_rpt/drinks_rpt_grp/drink_name", "type": "string", "binary": null, "value_type": "calculate", "selectMultiple": null}, {"name": "drink_lk", "path": "/drinks_rpt/drinks_rpt_grp/drink_lk", "type": "string", "binary": null, "value_type": "select_one lk", "selectMultiple": null}, {"name": "why_drink", "path": "/drinks_rpt/drinks_rpt_grp/why_drink", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "drink_comment_rpt", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt", "type": "repeat", "binary": null, "value_type": "begin repeat", "selectMultiple": null}, {"name": "drink_comment", "path": "/drinks_rpt/drinks_rpt_grp/drink_comment_rpt/drink_comment", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:45',
                'updated_at' => '2025-02-12 11:21:17',
            ),
            3 =>
            array (
                'id' => 11,
                'xlsform_template_id' => 2,
                'owner_id' => 3,
                'odk_project_id' => 1447,
                'title' => 'Test Mini HOLPA Fieldwork Survey',
                'odk_id' => 'test-mini-holpa-fieldwork-survey',
                'odk_draft_token' => 'dHc5je25NHLNgtljlESSz5StmL9o98Q0bErSPivPrQIt8a7$aeB4l!exSev5x!6c',
                'odk_version_id' => '2025-02-12 12:01:40',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => 'GGLDz3htLDatE1otcBmksEITp7IlmAf',
                'enketo_id' => 'r9GVBnTNAAvPpg45n9v9WFiqzBYWrYI',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork", "path": "/fieldwork", "type": "structure", "binary": null, "value_type": "begin_group", "selectMultiple": null}, {"name": "field_count", "path": "/fieldwork/field_count", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork_repeat", "path": "/fieldwork/fieldwork_repeat", "type": "repeat", "binary": null, "value_type": "begin_repeat", "selectMultiple": null}, {"name": "soil_type", "path": "/fieldwork/fieldwork_repeat/soil_type", "type": "string", "binary": null, "value_type": "select_one soils", "selectMultiple": null}, {"name": "crop_types", "path": "/fieldwork/fieldwork_repeat/crop_types", "type": "string", "binary": null, "value_type": "select_one crops", "selectMultiple": null}, {"name": "interesting_features", "path": "/fieldwork/fieldwork_repeat/interesting_features", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-02-12 12:01:40',
            ),
            4 =>
            array (
                'id' => 12,
                'xlsform_template_id' => 2,
                'owner_id' => 4,
                'odk_project_id' => 1448,
                'title' => 'Test Mini HOLPA Fieldwork Survey',
                'odk_id' => 'test-mini-holpa-fieldwork-survey',
                'odk_draft_token' => 'G7XazhDSyPxWSCua0Al2xeeqklv3KQ1t6BxOpt6c1yKY6ShRE7uUbCW8H!5FBww4',
                'odk_version_id' => '2025-02-12 11:21:09',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => 'sURAabzHEsXIc6FQUScmicMoL8cfNTX',
                'enketo_id' => 'oHDbbeN2CEPRcnYElKKAmJvh6EH6qkt',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork", "path": "/fieldwork", "type": "structure", "binary": null, "value_type": "begin_group", "selectMultiple": null}, {"name": "field_count", "path": "/fieldwork/field_count", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork_repeat", "path": "/fieldwork/fieldwork_repeat", "type": "repeat", "binary": null, "value_type": "begin_repeat", "selectMultiple": null}, {"name": "soil_type", "path": "/fieldwork/fieldwork_repeat/soil_type", "type": "string", "binary": null, "value_type": "select_one soils", "selectMultiple": null}, {"name": "crop_types", "path": "/fieldwork/fieldwork_repeat/crop_types", "type": "string", "binary": null, "value_type": "select_one crops", "selectMultiple": null}, {"name": "interesting_features", "path": "/fieldwork/fieldwork_repeat/interesting_features", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-02-12 11:21:10',
            ),
            5 =>
            array (
                'id' => 13,
                'xlsform_template_id' => 2,
                'owner_id' => 5,
                'odk_project_id' => 1449,
                'title' => 'Test Mini HOLPA Fieldwork Survey',
                'odk_id' => 'test-mini-holpa-fieldwork-survey',
                'odk_draft_token' => 'HDlmhQRx58r4RKfzqB1ViFPhjvAWQi6dFaqDNFvjqofC6ihfbEtcHhB0pr5arLDl',
                'odk_version_id' => '2025-02-12 11:21:22',
                'has_draft' => '1',
                'is_active' => '1',
                'enketo_draft_id' => 'tHLvRgPmUD2mZz1YGlS6peq3ap1SkbZ',
                'enketo_id' => 'tHapo9X4Ibtj7SyEnTpOmYZGo24a9je',
                'processing' => 0,
                'odk_error' => NULL,
                'schema' => '[{"name": "starttime", "path": "/starttime", "type": "dateTime", "binary": null, "value_type": "start", "selectMultiple": null}, {"name": "endtime", "path": "/endtime", "type": "dateTime", "binary": null, "value_type": "end", "selectMultiple": null}, {"name": "todaydate", "path": "/todaydate", "type": "date", "binary": null, "value_type": "today", "selectMultiple": null}, {"name": "deviceid", "path": "/deviceid", "type": "string", "binary": null, "value_type": "deviceid", "selectMultiple": null}, {"name": "na_note1", "path": "/na_note1", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "name_of_person", "path": "/name_of_person", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "age", "path": "/age", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork", "path": "/fieldwork", "type": "structure", "binary": null, "value_type": "begin_group", "selectMultiple": null}, {"name": "field_count", "path": "/fieldwork/field_count", "type": "int", "binary": null, "value_type": "integer", "selectMultiple": null}, {"name": "fieldwork_repeat", "path": "/fieldwork/fieldwork_repeat", "type": "repeat", "binary": null, "value_type": "begin_repeat", "selectMultiple": null}, {"name": "soil_type", "path": "/fieldwork/fieldwork_repeat/soil_type", "type": "string", "binary": null, "value_type": "select_one soils", "selectMultiple": null}, {"name": "crop_types", "path": "/fieldwork/fieldwork_repeat/crop_types", "type": "string", "binary": null, "value_type": "select_one crops", "selectMultiple": null}, {"name": "interesting_features", "path": "/fieldwork/fieldwork_repeat/interesting_features", "type": "string", "binary": null, "value_type": "text", "selectMultiple": null}, {"name": "photo_yn", "path": "/photo_yn", "type": "string", "binary": null, "value_type": "select_one yn", "selectMultiple": null}, {"name": "photo", "path": "/photo", "type": "binary", "binary": true, "value_type": "photo", "selectMultiple": null}, {"name": "gps", "path": "/gps", "type": "geopoint", "binary": null, "value_type": "geopoint", "selectMultiple": null}, {"name": "na_note2", "path": "/na_note2", "type": "string", "binary": null, "value_type": "note", "selectMultiple": null}, {"name": "meta", "path": "/meta", "type": "structure", "binary": null, "selectMultiple": null}, {"name": "instanceID", "path": "/meta/instanceID", "type": "string", "binary": null, "selectMultiple": null}, {"name": "instanceName", "path": "/meta/instanceName", "type": "string", "binary": null, "selectMultiple": null}]',
                'has_latest_template' => 1,
                'has_latest_media' => 1,
                'created_at' => '2025-01-07 17:15:46',
                'updated_at' => '2025-02-12 11:21:23',
            ),
        ));


    }
}
