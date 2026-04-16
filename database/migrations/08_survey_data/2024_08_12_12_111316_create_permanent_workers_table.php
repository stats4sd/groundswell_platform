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
        Schema::create('permanent_workers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->boolean('household_members')->nullable();
            $table->integer('perm_labour_group_num')->nullable();
            $table->text('perm_labour_group_name')->nullable();
            $table->decimal('perm_labour_hours', 20, 2)->nullable();
            $table->text('perm_labour_activities')->nullable();
            $table->text('perm_labour_activities_other')->nullable();
            $table->text('perm_labour_crop_activities')->nullable();
            $table->text('perm_labour_crop_activities_other')->nullable();
            $table->text('perm_labour_livestock_activities')->nullable();
            $table->text('perm_labour_livestock_activities_other')->nullable();
            $table->text('perm_labour_fish_activities')->nullable();
            $table->text('perm_labour_fish_activities_other')->nullable();
            $table->text('perm_labour_trees_activities')->nullable();
            $table->text('perm_labour_trees_activities_other')->nullable();
            $table->text('perm_labour_honey_activities')->nullable();
            $table->text('perm_labour_honey_activities_other')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permanent_workers');
    }
};
