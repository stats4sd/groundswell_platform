<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('temp_results', function (Blueprint $table) {
            $table->id();
            $table->string('country_id')->constrained()->nullable();
            $table->string('gender')->nullable();
            $table->string('education_level')->nullable();
            $table->integer('age')->nullable();
            $table->decimal('farm_size', 10, 2)->nullable();
            // gps
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();


            // AE Scores
            $table->integer('recycling_1_score')->nullable();
            $table->integer('recycling_2_score')->nullable();
            $table->integer('recycling_3_score')->nullable();
            $table->integer('recycling_4_score')->nullable();
            $table->integer('recycling_5_score')->nullable();
            $table->integer('overall_recycling_score')->nullable();

            $table->integer('input_reduction_1_score')->nullable();
            $table->integer('input_reduction_2_score')->nullable();
            $table->integer('input_reduction_3_score')->nullable();
            $table->integer('input_reduction_4_score')->nullable();
            $table->integer('input_reduction_5_score')->nullable();
            $table->integer('input_reduction_6_score')->nullable();
            $table->integer('overall_input_reduction_score')->nullable();

            $table->integer('soil_health_score')->nullable();
            $table->integer('overall_soil_health_score')->nullable();

            $table->integer('animal_health_1_score')->nullable();
            $table->integer('animal_health_2_score')->nullable();
            $table->integer('animal_health_3_score')->nullable();
            $table->integer('overall_animal_health_score')->nullable();

            $table->integer('overall_biodiversity_score')->nullable();

            $table->integer('synergy_1_score')->nullable();
            $table->integer('synergy_2_score')->nullable();
            $table->integer('synergy_3_score')->nullable();
            $table->integer('synergy_4_score')->nullable();
            $table->integer('synergy_5_score')->nullable();
            $table->integer('synergy_6_score')->nullable();
            $table->integer('overall_synergy_score')->nullable();

            $table->integer('economic_diversification_score')->nullable();
            $table->integer('overall_economic_diversification_score')->nullable();

            $table->integer('co_creation_knowledge_1_score')->nullable();
            $table->integer('co_creation_knowledge_2_score')->nullable();
            $table->integer('co_creation_knowledge_3_score')->nullable();
            $table->integer('co_creation_knowledge_4_score')->nullable();
            $table->integer('co_creation_knowledge_5_score')->nullable();
            $table->integer('co_creation_knowledge_6_score')->nullable();
            $table->integer('co_creation_knowledge_7_score')->nullable();
            $table->integer('overall_co_creation_knowledge_score')->nullable();

            $table->integer('governance_1_score')->nullable();
            $table->integer('governance_2_score')->nullable();
            $table->integer('governance_3_score')->nullable();
            $table->integer('overall_governance_score')->nullable();

            $table->integer('social_values_diet_1_score')->nullable();
            $table->integer('social_values_diet_2_score')->nullable();
            $table->integer('social_values_diet_3_score')->nullable();
            $table->integer('social_values_diet_4_score')->nullable();
            $table->integer('overall_social_values_diet_score')->nullable();

            $table->integer('overall_fairness_score')->nullable();

            $table->integer('overall_connectivity_score')->nullable();

            $table->integer('participation_score')->nullable();
            $table->integer('overall_participation_score')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_results');
    }
};
