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
        Schema::create('seasonal_worker_seasons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->boolean('household_members')->nullable();
            $table->text('seasonal_labour_group_name')->nullable();
            $table->text('seasonal_labour_months')->nullable();
            $table->integer('seasonal_labour_months_count')->nullable();
            $table->integer('seasonal_labour_n_working')->nullable();
            $table->integer('seasonal_labour_hours')->nullable();
            $table->text('seasonal_labour_activities')->nullable();
            $table->text('seasonal_labour_activities_other')->nullable();
            $table->text('seasonal_labour_crop_activities')->nullable();
            $table->text('seasonal_labour_crop_activities_other')->nullable();
            $table->text('seasonal_labour_livestock_activities')->nullable();
            $table->text('seasonal_labour_livestock_activities_other')->nullable();
            $table->text('seasonal_labour_fish_activities')->nullable();
            $table->text('seasonal_labour_fish_activities_other')->nullable();
            $table->text('seasonal_labour_trees_activities')->nullable();
            $table->text('seasonal_labour_trees_activities_other')->nullable();
            $table->text('seasonal_labour_honey_activities')->nullable();
            $table->text('seasonal_labour_honey_activities_other')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasonal_worker_seasons');
    }
};
