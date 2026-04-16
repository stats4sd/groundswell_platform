<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('crops', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->text('primary_crop_id')->nullable();
            $table->text('primary_crop_name')->nullable();
            $table->integer('crop_varities')->nullable();
            $table->decimal('primary_crop_area', 24, 6)->nullable();
            $table->decimal('primary_crop_area_ha', 24, 6)->nullable();
            $table->text('primary_crop_practices')->nullable();

            $table->decimal('area_annual_mono', 24, 6)->nullable();
            $table->decimal('area_perennial_mono', 24, 6)->nullable();
            $table->decimal('area_afroforestry', 24, 6)->nullable();
            $table->decimal('area_burning', 24, 6)->nullable();
            $table->decimal('area_cover', 24, 6)->nullable();
            $table->decimal('area_rotation', 24, 6)->nullable();
            $table->decimal('area_fallow', 24, 6)->nullable();
            $table->decimal('area_hedgerows', 24, 6)->nullable();
            $table->decimal('area_garden', 24, 6)->nullable();
            $table->decimal('area_intercrop', 24, 6)->nullable();
            $table->decimal('area_landclearing', 24, 6)->nullable();
            $table->decimal('area_mulch', 24, 6)->nullable();
            $table->decimal('area_natural', 24, 6)->nullable();
            $table->decimal('area_pollinator', 24, 6)->nullable();
            $table->decimal('area_push_pull', 24, 6)->nullable();
            $table->decimal('area_other_practice', 24, 6)->nullable();

            $table->decimal('density_annual_mono', 20, 2)->nullable();
            $table->decimal('density_perennial_mono', 20, 2)->nullable();
            $table->decimal('density_afroforestry', 20, 2)->nullable();
            $table->decimal('density_burning', 20, 2)->nullable();
            $table->decimal('density_cover', 20, 2)->nullable();
            $table->decimal('density_rotation', 20, 2)->nullable();
            $table->decimal('density_fallow', 20, 2)->nullable();
            $table->decimal('density_hedgerows', 20, 2)->nullable();
            $table->decimal('density_garden', 20, 2)->nullable();
            $table->decimal('density_intercrop', 20, 2)->nullable();
            $table->decimal('density_landclearing', 20, 2)->nullable();
            $table->decimal('density_mulch', 20, 2)->nullable();
            $table->decimal('density_natural', 20, 2)->nullable();
            $table->decimal('density_pollinator', 20, 2)->nullable();
            $table->decimal('density_push_pull', 20, 2)->nullable();
            $table->decimal('density_other_practice', 20, 2)->nullable();

            $table->text('agroforestry_crops')->nullable();
            $table->text('cover_crops')->nullable();
            $table->text('rotation_crops')->nullable();
            $table->text('garden_crops')->nullable();
            $table->text('intercrop_crops')->nullable();
            $table->text('push_pull_crops')->nullable();
            $table->text('agroforestry_trees_n')->nullable();
            $table->text('hedgerows_trees_n')->nullable();
            $table->text('homegarden_trees_n')->nullable();
            $table->text('agroforestry_trees_diversity')->nullable();
            $table->text('hedgerows_trees_diversity')->nullable();
            $table->text('homegarden_trees_diversity')->nullable();
            $table->text('agroforestry_trees_spatial')->nullable();
            $table->text('hedgerows_trees_spatial')->nullable();
            $table->text('homegarden_trees_spatial')->nullable();
            $table->text('yield_unit')->nullable();

            $table->text('yield_unit_kg_conversion')->nullable();
            $table->text('yield_unit_label')->nullable();
            $table->text('yield_unit_label_english')->nullable();
            $table->text('total_yield')->nullable();
            $table->text('yield_kg')->nullable();
            $table->text('yield_weight_area')->nullable();
            $table->text('yield_kg_ha')->nullable();

            $table->decimal('yield_annual_mono', 20, 2)->nullable();
            $table->decimal('yield_perennial_mono', 20, 2)->nullable();
            $table->decimal('yield_afroforestry', 20, 2)->nullable();
            $table->decimal('yield_burning', 20, 2)->nullable();
            $table->decimal('yield_cover', 20, 2)->nullable();
            $table->decimal('yield_rotation', 20, 2)->nullable();
            $table->decimal('yield_fallow', 20, 2)->nullable();
            $table->decimal('yield_hedgerows', 20, 2)->nullable();
            $table->decimal('yield_garden', 20, 2)->nullable();
            $table->decimal('yield_intercrop', 20, 2)->nullable();
            $table->decimal('yield_landclearing', 20, 2)->nullable();
            $table->decimal('yield_mulch', 20, 2)->nullable();
            $table->decimal('yield_natural', 20, 2)->nullable();
            $table->decimal('yield_pollinator', 20, 2)->nullable();
            $table->decimal('yield_push_pull', 20, 2)->nullable();
            $table->decimal('yield_other_practice', 20, 2)->nullable();

            $table->text('primary_crop_use')->nullable();
            $table->text('primary_crop_use_other')->nullable();
            $table->text('yield_sums')->nullable();

            $table->decimal('amount_own_consumption', 20, 2)->nullable();
            $table->decimal('amount_livestock_consumption', 20, 2)->nullable();
            $table->decimal('amount_consumer', 20, 2)->nullable();
            $table->decimal('amount_coop', 20, 2)->nullable();
            $table->decimal('amount_market', 20, 2)->nullable();
            $table->decimal('amount_trader', 20, 2)->nullable();
            $table->decimal('amount_gift', 20, 2)->nullable();
            $table->decimal('amount_wasted', 20, 2)->nullable();
            $table->decimal('amount_other_use', 20, 2)->nullable();
            $table->decimal('consumer_price', 20, 2)->nullable();
            $table->decimal('coop_price', 20, 2)->nullable();
            $table->decimal('market_price', 20, 2)->nullable();
            $table->decimal('trader_price', 20, 2)->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crops');
    }
};
