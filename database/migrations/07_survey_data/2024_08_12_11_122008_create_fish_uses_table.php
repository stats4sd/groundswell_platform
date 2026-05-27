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
        Schema::create('fish_uses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->text('fish_use_name')->nullable();
            $table->text('fish_use_label')->nullable();
            $table->text('fish_use_unit')->nullable();
            $table->text('fish_use_unit_other')->nullable();
            $table->text('fish_use_unit_label')->nullable();
            $table->decimal('fish_use_quantity', 20, 2)->nullable();
            $table->text('fish_use_output')->nullable();
            $table->text('fish_use_output_other')->nullable();
            $table->decimal('fish_sums', 20, 2)->nullable();
            $table->decimal('fish_amount_consumption', 20, 2)->nullable();
            $table->decimal('fish_amount_livestock', 20, 2)->nullable();
            $table->decimal('fish_amount_on_farm', 20, 2)->nullable();
            $table->decimal('fish_amount_consumer', 20, 2)->nullable();
            $table->decimal('fish_amount_coop', 20, 2)->nullable();
            $table->decimal('fish_amount_market', 20, 2)->nullable();
            $table->decimal('fish_amount_trader', 20, 2)->nullable();
            $table->decimal('fish_amount_gifts', 20, 2)->nullable();
            $table->decimal('fish_amount_wasted', 20, 2)->nullable();
            $table->decimal('fish_amount_other', 20, 2)->nullable();
            $table->decimal('fish_price_consumer', 20, 2)->nullable();
            $table->decimal('fish_price_coop', 20, 2)->nullable();
            $table->decimal('fish_price_market', 20, 2)->nullable();
            $table->decimal('fish_price_trader', 20, 2)->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fish_uses');
    }
};
