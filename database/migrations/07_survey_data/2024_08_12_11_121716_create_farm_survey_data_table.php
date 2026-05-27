<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {

    // Note: below error occurred if section below column "internet" is uncommented
    // PDOException::("SQLSTATE[42000]: Syntax error or access violation: 1118 Row size too large (> 8126). Changing some columns to TEXT or BLOB may help. In current row format, BLOB prefix of 0 bytes is stored inline."

    // Google searched below stackoverflow thread:
    // MySQL: Error Code: 1118 Row size too large (> 8126). Changing some columns to TEXT or BLOB
    // https://stackoverflow.com/questions/22637733/mysql-error-code-1118-row-size-too-large-8126-changing-some-columns-to-te

    // Solution:
    // Run below SQL in TablePlus
    // SET GLOBAL innodb_strict_mode = 0;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farm_survey_data', function (Blueprint $table) {
            $table->id();

            $table->json('properties')->nullable();

            $table->text('start')->nullable();
            $table->text('end')->nullable();
            $table->text('today')->nullable();
            $table->text('deviceid')->nullable();
            $table->text('inquirer')->nullable();

            $table->text('household_survey_date')->nullable();
            $table->unsignedBigInteger('farm_id')->nullable();

            // location
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();
            $table->text('gps_location_alt')->nullable();
//
//            $table->decimal('irrigation_percentage_month_1', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_2', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_3', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_4', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_5', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_6', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_7', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_8', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_9', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_10', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_11', 5, 2)->nullable();
//            $table->decimal('irrigation_percentage_month_12', 5, 2)->nullable();
//
//            $table->text('consent_interview')->nullable();
//            $table->text('consent_recording')->nullable();
//            $table->text('consent_photos')->nullable();
//            $table->text('consent_photo_publishing')->nullable();
//            $table->text('respondent_first_name')->nullable();
//            $table->text('respondent_last_name')->nullable();
//            $table->text('hhh_relationship')->nullable();
//            $table->text('hhh_relationship_other')->nullable();
//            $table->text('decision_maker_relationship')->nullable();
//            $table->text('decision_maker_relationship_other')->nullable();
//
//            $table->integer('birthyear')->nullable();
//            $table->integer('age')->nullable();
//            $table->decimal('years_in_community', 20, 2)->nullable();
//
//            $table->text('gender')->nullable();
//            $table->text('marital_status')->nullable();
//            $table->text('marital_status_other')->nullable();
//            $table->text('ethnicity')->nullable();
//            $table->text('read_write')->nullable();
//            $table->text('highest_education_attendance')->nullable();
//            $table->text('grade')->nullable();
//            $table->text('primary_occupation')->nullable();
//            $table->text('primary_occupation_other')->nullable();
//            $table->text('additional_occupation_yn')->nullable();
//            $table->text('additional_occupation')->nullable();
//            $table->text('additional_occupation_other')->nullable();
//
//            $table->integer('male_adults')->nullable();
//            $table->integer('female_adults')->nullable();
//            $table->integer('male_elderly')->nullable();
//            $table->integer('female_elderly')->nullable();
//            $table->integer('male_children')->nullable();
//            $table->integer('female_children')->nullable();
//            $table->integer('hhsize')->nullable();
//            $table->integer('males')->nullable();
//            $table->integer('females')->nullable();
//            $table->integer('children')->nullable();
//
//            $table->text('highest_education_male')->nullable();
//            $table->text('highest_education_female')->nullable();
//            $table->text('school_children')->nullable();
//            $table->text('agricultural_training')->nullable();
//            $table->text('business_training')->nullable();
//            $table->text('other_training_yn')->nullable();
//            $table->text('other_training_specify')->nullable();
//            $table->text('agricultural_research')->nullable();
//            $table->text('project_name')->nullable();
//            $table->text('ae_understand')->nullable();
//
//            $table->text('factor1')->nullable();
//            $table->text('factor2')->nullable();
//            $table->text('factor3')->nullable();
//            $table->text('factor4')->nullable();
//            $table->text('factor5')->nullable();
//            $table->text('factor6')->nullable();
//            $table->text('factor7')->nullable();
//            $table->text('factor8')->nullable();
//            $table->text('factor9')->nullable();
//            $table->text('factor10')->nullable();
//            $table->text('factor11')->nullable();
//            $table->text('factor12')->nullable();
//            $table->text('factor13')->nullable();
//
//            $table->text('wellbeing1')->nullable();
//            $table->text('wellbeing2')->nullable();
//            $table->text('wellbeing3')->nullable();
//            $table->text('wellbeing4')->nullable();
//            $table->text('wellbeing5')->nullable();
//            $table->text('wellbeing6')->nullable();
//            $table->text('wellbeing7')->nullable();
//            $table->text('wellbeing8')->nullable();
//            $table->text('wellbeing9')->nullable();
//            $table->text('wellbeing10')->nullable();
//            $table->text('wellbeing11')->nullable();
//            $table->text('wellbeing12')->nullable();
//
//            $table->text('hhwomen_agency_step_now')->nullable();
//            $table->text('agency_text1')->nullable();
//            $table->text('hhwomen_agency_step_10yrs')->nullable();
//            $table->text('agency_text2')->nullable();
//            $table->text('hhmen_agency_step_now')->nullable();
//            $table->text('agency_text3')->nullable();
//            $table->text('hhmen_agency_step_10yrs')->nullable();
//            $table->text('agency_text4')->nullable();
//            $table->text('comm_women_agency_step_now')->nullable();
//            $table->text('comm_women_different')->nullable();
//            $table->text('different_women_text')->nullable();
//            $table->text('comm_men_agency_step_now')->nullable();
//            $table->text('comm_men_different')->nullable();
//            $table->text('different_men_text')->nullable();
//            $table->text('changes_for_agency_text')->nullable();
//
//            $table->integer('share_extension_workers')->nullable();
//            $table->integer('share_consumers')->nullable();
//            $table->integer('share_traders')->nullable();
//            $table->integer('share_govt')->nullable();
//            $table->integer('share_ngos')->nullable();
//            $table->integer('share_farmers')->nullable();
//            $table->integer('share_researchers')->nullable();
//
//            $table->text('activities_land_management')->nullable();
//            $table->text('influence_land_management')->nullable();
//            $table->text('land_management_view')->nullable();
//            $table->text('associations')->nullable();
//            $table->text('assoications_other')->nullable();
//            $table->text('association_effectiveness')->nullable();
//            $table->text('association_effectiveness_text')->nullable();
//            $table->text('area_unit')->nullable();
//
//            $table->decimal('area_unit_ha_conversion', 24, 6)->nullable();
//            $table->decimal('area_owned', 24, 6)->nullable();
//            $table->decimal('area_owned_ha', 24, 6)->nullable();
//            $table->decimal('area_leased', 24, 6)->nullable();
//            $table->decimal('area_leased_ha', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_ha', 24, 6)->nullable();
//            $table->decimal('total_land', 24, 6)->nullable();
//            $table->decimal('total_land_ha', 24, 6)->nullable();
//            $table->decimal('area_owned_male', 24, 6)->nullable();
//            $table->decimal('area_owned_male_ha', 24, 6)->nullable();
//            $table->decimal('area_owned_female', 24, 6)->nullable();
//            $table->decimal('area_owned_female_ha', 24, 6)->nullable();
//            $table->decimal('area_owned_male_perc', 24, 6)->nullable();
//            $table->decimal('area_owned_female_perc', 24, 6)->nullable();
//            $table->decimal('area_leased_male', 24, 6)->nullable();
//            $table->decimal('area_leased_male_ha', 24, 6)->nullable();
//            $table->decimal('area_leased_female', 24, 6)->nullable();
//            $table->decimal('area_leased_female_ha', 24, 6)->nullable();
//            $table->decimal('area_leased_male_perc', 24, 6)->nullable();
//            $table->decimal('area_leased_female_perc', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_male', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_male_ha', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_female', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_female_ha', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_male_perc', 24, 6)->nullable();
//            $table->decimal('area_holds_use_rights_female_perc', 24, 6)->nullable();
//            $table->decimal('total_land_male', 24, 6)->nullable();
//            $table->decimal('total_land_female', 24, 6)->nullable();
//            $table->decimal('total_land_male_ha', 24, 6)->nullable();
//            $table->decimal('total_land_female_ha', 24, 6)->nullable();
//            $table->decimal('total_land_male_perc', 24, 6)->nullable();
//            $table->decimal('total_land_female_perc', 24, 6)->nullable();
//            $table->decimal('land_security_perception', 24, 6)->nullable();
//            $table->decimal('area_at_threat', 24, 6)->nullable();
//            $table->decimal('area_at_threat_ha', 24, 6)->nullable();
//
//            $table->text('shocks')->nullable();
//            $table->text('shocks_other')->nullable();
//            $table->text('coping_strategies')->nullable();
//            $table->text('coping_strategies_other')->nullable();
//            $table->text('capacity_to_recover')->nullable();
//            $table->text('agricultural_loss_insurance')->nullable();
//            $table->text('insurance_covers')->nullable();
//            $table->text('insurance_covers_other')->nullable();
//
//            $table->text('support_banks')->nullable();
//            $table->text('support_community_leaders')->nullable();
//            $table->text('support_other_community')->nullable();
//            $table->text('support_my_community')->nullable();
//            $table->text('support_farmer_coops')->nullable();
//            $table->text('support_farmer_org')->nullable();
//            $table->text('support_local_govt')->nullable();
//            $table->text('support_moneylenders')->nullable();
//            $table->text('support_national_govt')->nullable();
//            $table->text('support_ngos')->nullable();
//            $table->text('support_other_associations')->nullable();
//            $table->text('support_shops')->nullable();
//            $table->text('support_other')->nullable();
//            $table->text('support_other_specify')->nullable();
//
//            $table->text('income_sources')->nullable();
//            $table->integer('income_count')->nullable();
//            $table->text('subsidy')->nullable();
//            $table->text('income_sources_other')->nullable();
//
//            $table->integer('income_crops')->nullable();
//            $table->integer('income_livestock')->nullable();
//            $table->integer('income_fish')->nullable();
//            $table->integer('income_family_business')->nullable();
//            $table->integer('income_casual_labour')->nullable();
//            $table->integer('income_formal_labour')->nullable();
//            $table->integer('income_cash')->nullable();
//            $table->integer('income_leasing')->nullable();
//            $table->integer('income_subsidy')->nullable();
//            $table->integer('income_other')->nullable();
//            $table->integer('income_sum')->nullable();
//
//            $table->text('sufficient_income')->nullable();
//            $table->text('income_stability')->nullable();
//            $table->text('farm_loss')->nullable();
//            $table->text('farm_loss_text')->nullable();
//
//            $table->integer('cars')->nullable();
//            $table->integer('motorbikes')->nullable();
//            $table->integer('bicycles')->nullable();
//            $table->integer('gas_cookers')->nullable();
//            $table->integer('electric_cookers')->nullable();
//            $table->integer('mobile_phones')->nullable();
//            $table->integer('smartphones')->nullable();
//            $table->integer('ox_plough')->nullable();
//            $table->integer('tractors')->nullable();
//            $table->integer('plows')->nullable();
//            $table->integer('seed_drills')->nullable();
//            $table->integer('crop_facilities')->nullable();
//
//            $table->text('asset_other')->nullable();
//            $table->integer('asset_other_count')->nullable();
//
//            $table->text('credit_access')->nullable();
//            $table->text('credit_source')->nullable();
//            $table->text('credit_source_other')->nullable();
//            $table->text('investment')->nullable();
//            $table->text('investment_other')->nullable();
//
//            $table->text('debt')->nullable();
//            $table->text('debt_repayment')->nullable();
//            $table->text('baked')->nullable();
//            $table->text('grains')->nullable();
//            $table->text('tubers')->nullable();
//            $table->text('pulses')->nullable();
//            $table->text('vita_veg')->nullable();
//            $table->text('darkgreen')->nullable();
//            $table->text('otherveg')->nullable();
//            $table->text('vita_fruit')->nullable();
//            $table->text('citrus')->nullable();
//            $table->text('otherfruit')->nullable();
//            $table->text('sweet_foods')->nullable();
//            $table->text('other_sweet')->nullable();
//            $table->text('eggs')->nullable();
//            $table->text('cheese')->nullable();
//            $table->text('yogurt')->nullable();
//            $table->text('processed_meats')->nullable();
//            $table->text('red_meat_ruminant')->nullable();
//            $table->text('red_meat_non_ruminant')->nullable();
//            $table->text('poultry')->nullable();
//            $table->text('seafood')->nullable();
//            $table->text('nuts')->nullable();
//            $table->text('salty')->nullable();
//            $table->text('noodles')->nullable();
//            $table->text('deep_fried_foods')->nullable();
//            $table->text('milk')->nullable();
//            $table->text('hot_drinks')->nullable();
//            $table->text('fruit_drinks')->nullable();
//            $table->text('sugary_drnks')->nullable();
//            $table->text('fast_food')->nullable();
//
//            $table->text('access_healthy_food')->nullable();
//            $table->text('access_diverse_food')->nullable();
//            $table->text('access_seasonal_food')->nullable();
//            $table->text('access_traditional_food')->nullable();
//            $table->text('food_expenditure_percent')->nullable();
//            $table->text('free_school_meals')->nullable();
//            $table->text('roof_material')->nullable();
//            $table->text('roof_material_other')->nullable();
//            $table->text('wall_material')->nullable();
//            $table->text('wall_material_other')->nullable();
//
//            $table->text('piped_drinking_water')->nullable();
//            $table->text('piped_toilet')->nullable();
//            $table->text('electricity')->nullable();
//            $table->text('waste_collection')->nullable();
//            $table->text('phone_reception')->nullable();
//            $table->text('internet')->nullable();
//
//            $table->text('distanceunit_farmland')->nullable();
//            $table->text('transportation_farmland')->nullable();
//            $table->integer('distance_farmland')->nullable();
//            $table->text('distanceunit_freshwater')->nullable();
//            $table->text('transportation_freshwater')->nullable();
//            $table->integer('distance_freshwater')->nullable();
//            $table->text('distanceunit_school')->nullable();
//            $table->text('transportation_school')->nullable();
//            $table->integer('distance_school')->nullable();
//            $table->text('distanceunit_hospital')->nullable();
//            $table->text('transportation_hospital')->nullable();
//            $table->integer('distance_hospital')->nullable();
//            $table->text('distanceunit_livestock')->nullable();
//            $table->text('transportation_livestock')->nullable();
//            $table->integer('distance_livestock')->nullable();
//            $table->text('distanceunit_crops')->nullable();
//            $table->text('transportation_crops')->nullable();
//            $table->integer('distance_crops')->nullable();
//            $table->text('distanceunit_transport')->nullable();
//            $table->text('transportation_transport')->nullable();
//            $table->integer('distance_transport')->nullable();
//            $table->text('distanceunit_road')->nullable();
//            $table->text('transportation_road')->nullable();
//            $table->integer('distance_road')->nullable();
//
//            $table->text('natural_vegetation')->nullable();
//            $table->text('bushland')->nullable();
//            $table->text('fallow_land')->nullable();
//            $table->text('hedgerows')->nullable();
//            $table->text('grassland')->nullable();
//            $table->text('ponds')->nullable();
//            $table->text('forest_patches')->nullable();
//            $table->text('wetlands')->nullable();
//            $table->text('woodlots')->nullable();
//            $table->text('other_land_covering')->nullable();
//            $table->text('other_landscape_features')->nullable();
//
//            $table->text('bushland_diversity')->nullable();
//            $table->text('fallow_land_diversity')->nullable();
//            $table->text('hedgerows_diversity')->nullable();
//            $table->text('grassland_diversity')->nullable();
//            $table->text('forest_patches_diversity')->nullable();
//            $table->text('wetlands_diversity')->nullable();
//            $table->text('woodlots_diversity')->nullable();
//            $table->text('pollinator_diversity')->nullable();
//            $table->text('pest_diversity')->nullable();
//            $table->text('pest_enemy_diversity')->nullable();
//            $table->text('mammal_diversity')->nullable();
//            $table->text('tree_cover')->nullable();
//            $table->text('tree_diversity')->nullable();
//
//            $table->integer('farming_startyear')->nullable();
//            $table->text('previous_landcover')->nullable();
//            $table->integer('female_adult_permanent')->nullable();
//            $table->integer('female_adult_seasonal')->nullable();
//            $table->integer('male_elderly_permanent')->nullable();
//            $table->integer('male_elderly_seasonal')->nullable();
//            $table->integer('female_elderly_permanent')->nullable();
//            $table->integer('female_elderly_seasonal')->nullable();
//            $table->integer('male_children_permanent')->nullable();
//            $table->integer('male_children_seasonal')->nullable();
//            $table->integer('female_children_permanent')->nullable();
//            $table->integer('female_children_seasonal')->nullable();
//
//            $table->text('hired_labour')->nullable();
//            $table->text('crops')->nullable();
//            $table->integer('crops_count')->nullable();
//
//            $table->decimal('total_crop_area', 24, 6)->nullable();
//            $table->decimal('total_crop_area_ha', 24, 6)->nullable();
//            $table->text('total_crop_area_note')->nullable();
//
//            $table->integer('crop_loss_perc')->nullable();
//            $table->text('crop_loss_reason')->nullable();
//
//            $table->text('seed_source')->nullable();
//            $table->text('seed_type')->nullable();
//            $table->text('slope')->nullable();
//            $table->text('erosion')->nullable();
//            $table->text('soil_fertility')->nullable();
//            $table->text('sf_methods')->nullable();
//            $table->text('chem_fert_unit')->nullable();
//
//            $table->decimal('chem_fert_unit_kg_conversion', 20, 2)->nullable();
//            $table->text('chem_fert_unit_label')->nullable();
//            $table->text('chem_fert_unit_label_english')->nullable();
//            $table->decimal('chem_fert_applied', 20, 2)->nullable();
//            $table->decimal('chem_fert_applied_kg', 20, 2)->nullable();
//            $table->decimal('chem_fert_area', 20, 2)->nullable();
//            $table->decimal('chem_fert_area_ha', 20, 2)->nullable();
//            $table->decimal('chem_fert_applied_per_area', 20, 2)->nullable();
//            $table->decimal('chem_fert_kg_ha', 20, 2)->nullable();
//
//            $table->text('organic_fert_source')->nullable();
//            $table->text('own_organic_fert_unit')->nullable();
//
//            $table->decimal('own_organic_fert_unit_kg_conversion', 20, 2)->nullable();
//            $table->text('own_organic_fert_unit_label')->nullable();
//            $table->text('own_organic_fert_unit_label_english')->nullable();
//            $table->decimal('own_organic_fert_applied', 20, 2)->nullable();
//            $table->decimal('own_organic_fert_applied_kg', 20, 2)->nullable();
//            $table->decimal('own_organic_fert_area', 20, 2)->nullable();
//            $table->decimal('own_organic_fert_area_ha', 20, 2)->nullable();
//            $table->decimal('own_organic_fert_applied_per_area', 20, 2)->nullable();
//            $table->decimal('own_organic_fert_kg_ha', 20, 2)->nullable();
//            $table->text('bought_organic_fert_unit')->nullable();
//
//            $table->decimal('bought_organic_fert_unit_kg_conversion', 20, 2)->nullable();
//            $table->text('bought_organic_fert_unit_label')->nullable();
//            $table->text('bought_organic_fert_unit_label_english')->nullable();
//            $table->decimal('bought_organic_fert_applied', 20, 2)->nullable();
//            $table->decimal('bought_organic_fert_applied_kg', 20, 2)->nullable();
//            $table->decimal('bought_organic_fert_area', 24, 6)->nullable();
//            $table->decimal('bought_organic_fert_area_ha', 24, 6)->nullable();
//            $table->decimal('bought_organic_fert_applied_per_area', 20, 2)->nullable();
//            $table->decimal('bought_organic_fert_kg_ha', 20, 2)->nullable();
//
//            $table->text('sf_practices_list')->nullable();
//            $table->integer('sf_practices_count')->nullable();
//            $table->text('sf_practices_other')->nullable();
//            $table->text('pest_methods')->nullable();
//            $table->text('chemical_name')->nullable();
//            $table->text('chemical_unit')->nullable();
//
//            $table->decimal('chemical_unit_kg_conversion', 20, 2)->nullable();
//            $table->text('chemical_unit_label')->nullable();
//            $table->text('chemical_unit_label_english')->nullable();
//            $table->decimal('chemical_applied', 20, 2)->nullable();
//            $table->decimal('chemical_applied_kg', 20, 2)->nullable();
//            $table->decimal('chemical_area', 20, 2)->nullable();
//            $table->decimal('chemical_area_ha', 20, 2)->nullable();
//            $table->decimal('chemical_applied_per_area', 20, 2)->nullable();
//            $table->decimal('chemical_kg_ha', 20, 2)->nullable();
//
//            $table->text('non_chemical_name')->nullable();
//            $table->text('non_chemical_unit')->nullable();
//
//            $table->decimal('non_chemical_unit_kg_conversion', 20, 2)->nullable();
//            $table->text('non_chemical_unit_label')->nullable();
//            $table->text('non_chemical_unit_label_english')->nullable();
//            $table->decimal('non_chemical_applied', 20, 2)->nullable();
//            $table->decimal('non_chemical_applied_kg', 20, 2)->nullable();
//            $table->decimal('non_chemical_area', 24, 6)->nullable();
//            $table->decimal('non_chemical_area_ha', 24, 6)->nullable();
//            $table->decimal('non_chemical_applied_per_area', 20, 2)->nullable();
//            $table->decimal('non_chemical_kg_ha', 20, 2)->nullable();
//
//            $table->text('pd_practices_list')->nullable();
//            $table->integer('pd_practices_count')->nullable();
//
//            $table->text('pd_practices_other')->nullable();
//            $table->text('subsidies')->nullable();
//            $table->text('subsidy_inputs')->nullable();
//
//            $table->decimal('livestock_land_own', 24, 6)->nullable();
//            $table->decimal('livestock_land_own_ha', 24, 6)->nullable();
//            $table->text('livestock_land_own_ha_note')->nullable();
//            $table->decimal('livestock_land_share', 24, 6)->nullable();
//            $table->decimal('livestock_land_share_ha', 24, 6)->nullable();
//            $table->text('livestock_land_share_ha_note')->nullable();
//
//            $table->text('livestock_species')->nullable();
//            $table->text('livestock_other_name')->nullable();
//            $table->text('livestock_source')->nullable();
//
//            $table->text('exotic_local')->nullable();
//            $table->text('dry_feed')->nullable();
//            $table->text('vaccinations')->nullable();
//            $table->text('antibiotics')->nullable();
//            $table->text('disease_injury')->nullable();
//            $table->text('disease_management')->nullable();
//            $table->text('disease_management_other')->nullable();
//            $table->text('animal_health')->nullable();
//            $table->text('animal_health_management')->nullable();
//
//            $table->integer('animal_health_management_count')->nullable();
//            $table->text('animal_health_management_other')->nullable();
//            $table->text('grazing_land_practices')->nullable();
//            $table->integer('grazing_practice_count')->nullable();
//            $table->text('grazing_land_practices_other')->nullable();
//
//            $table->text('fish_production_location')->nullable();
//            $table->text('fish_production_location_other')->nullable();
//
//            $table->decimal('fish_area', 24, 6)->nullable();
//            $table->decimal('fish_area_ha', 24, 6)->nullable();
//            $table->text('fish_species')->nullable();
//            $table->integer('fish_other_count')->nullable();
//            $table->text('fish_other_name')->nullable();
//            $table->text('spawn_source')->nullable();
//            $table->text('fish_feed_source')->nullable();
//            $table->text('fish_feed_source_other')->nullable();
//            $table->text('fish_feed_type')->nullable();
//            $table->text('fish_feed_type_other')->nullable();
//            $table->text('fish_disease')->nullable();
//            $table->text('fish_disease_management')->nullable();
//            $table->text('fish_disease_management_other')->nullable();
//            $table->text('fish_land_practice')->nullable();
//
//            $table->decimal('fish_land_practice_count', 24, 6)->nullable();
//            $table->text('fish_land_practice_other')->nullable();
//            $table->text('relationship_actions')->nullable();
//            $table->integer('relationship_actions_count')->nullable();
//            $table->text('relationship_actions_other')->nullable();
//            $table->text('temperature_change')->nullable();
//            $table->text('rainfall_amount_change')->nullable();
//            $table->text('rainfall_timing_change')->nullable();
//            $table->text('flooding')->nullable();
//            $table->text('drought')->nullable();
//
//            $table->text('irrigation_yn')->nullable();
//            $table->text('irrigation_methods')->nullable();
//            $table->text('irrigation_methods_other')->nullable();
//            $table->text('irrigation_source')->nullable();
//            $table->text('irrigation_source_other')->nullable();
//            $table->text('irrigation_seasons')->nullable();
//            $table->text('irrigation_months')->nullable();
//            $table->integer('irrigation_percentage')->nullable();
//
//            $table->text('livestock_water_source')->nullable();
//            $table->text('livestock_water_source_other')->nullable();
//            $table->text('rainwater_harvesting')->nullable();
//            $table->text('rainwater_harvesting_other')->nullable();
//            $table->text('normal_year')->nullable();
//            $table->integer('months_with_stress')->nullable();
//
//            $table->text('flood_year')->nullable();
//            $table->text('drought_year')->nullable();
//            $table->text('irrigation_energy_types')->nullable();
//            $table->text('irrigation_energy_types_other')->nullable();
//            $table->text('tillage_energy_types')->nullable();
//            $table->text('tillage_energy_types_other')->nullable();
//            $table->text('cooking_energy_types')->nullable();
//            $table->text('cooking_energy_types_other')->nullable();
//            $table->text('food_energy_types')->nullable();
//            $table->text('food_energy_types_other')->nullable();
//            $table->text('energy_source')->nullable();
//            $table->text('fieldwork_survey_date')->nullable();
//
//            // fieldwork location
//            $table->decimal('fieldwork_latitude', 11, 8)->nullable();
//            $table->decimal('fieldwork_longitude', 11, 8)->nullable();
//            $table->integer('fieldwork_altitude')->nullable();
//            $table->decimal('fieldwork_accuracy', 9, 4)->nullable();
//
//            $table->text('gps_fieldwork_location_alt')->nullable();
//            $table->text('consent_fieldwork_1')->nullable();
//            $table->text('consent_fieldwork_2')->nullable();
//            $table->text('consent_fieldwork_3')->nullable();
//            $table->text('respondent_first_name_fieldwork')->nullable();
//            $table->text('respondent_last_name_fieldwork')->nullable();
//            $table->text('hhh_relationship_fieldwork')->nullable();
//            $table->text('hhh_relationship_other_fieldwork')->nullable();
//            $table->text('decision_maker_relationship_fieldwork')->nullable();
//            $table->text('decision_maker_relationship_other_fieldwork')->nullable();
//
//            $table->integer('birthyear_fieldwork')->nullable();
//            $table->integer('age_fieldwork')->nullable();
//
//            $table->text('gender_fieldwork')->nullable();
//            $table->text('data_types')->nullable();
//
//            $table->integer('livestock_count')->nullable();
//            $table->integer('fish_count')->nullable();
//            $table->integer('ecological_practices_count')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_survey_data');
    }
};
