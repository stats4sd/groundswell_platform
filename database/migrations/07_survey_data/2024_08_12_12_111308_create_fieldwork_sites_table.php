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
        Schema::create('fieldwork_sites', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->unsignedBigInteger('site_no')->nullable();

            // location
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();

            $table->integer('trees_count')->nullable();
            $table->integer('trees_richness')->nullable();
            $table->integer('trees_native')->nullable();
            $table->integer('trees_diameter')->nullable();
            $table->integer('trees_mature')->nullable();

            $table->text('trees_arrangement')->nullable();
            $table->text('vegatation_features')->nullable();
            $table->text('vegatation_features_other')->nullable();
            $table->text('vegetation_area')->nullable();
            $table->text('Soc_survey_id')->nullable();
            $table->text('production_systems')->nullable();
            $table->text('production_systems_other')->nullable();
            $table->text('structure')->nullable();
            $table->text('compaction')->nullable();
            $table->text('depth')->nullable();
            $table->text('residues')->nullable();
            $table->text('colour')->nullable();
            $table->text('moisture')->nullable();
            $table->text('cover')->nullable();
            $table->text('erosion')->nullable();
            $table->text('invertebrate')->nullable();
            $table->text('microbe')->nullable();
            $table->text('appearance_description')->nullable();
            $table->text('growth')->nullable();
            $table->text('disease_incidence')->nullable();
            $table->text('insect_incidence')->nullable();
            $table->text('enemy_abundance')->nullable();
            $table->text('weeds')->nullable();
            $table->text('natural_vegetation')->nullable();
            $table->text('management')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fieldwork_sites');
    }
};
