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
        Schema::create('livestock_uses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->text('livestock_use_name')->nullable();
            $table->text('livestock_use_label')->nullable();
            $table->text('livestock_use_unit')->nullable();
            $table->text('livestock_use_unit_other')->nullable();
            $table->text('livestock_use_unit_label')->nullable();
            $table->decimal('livestock_use_quantity', 20, 2)->nullable();
            $table->text('livestock_use_output')->nullable();
            $table->text('livestock_use_output_other')->nullable();
            $table->decimal('livestock_sums', 20, 2)->nullable();
            $table->decimal('livestock_amount_consumption', 20, 2)->nullable();
            $table->decimal('livestock_amount_on_farm', 20, 2)->nullable();
            $table->decimal('livestock_amount_consumer', 20, 2)->nullable();
            $table->decimal('livestock_amount_coop', 20, 2)->nullable();
            $table->decimal('livestock_amount_market', 20, 2)->nullable();
            $table->decimal('livestock_amount_trader', 20, 2)->nullable();
            $table->decimal('livestock_amount_gifts', 20, 2)->nullable();
            $table->decimal('livestock_amount_wasted', 20, 2)->nullable();
            $table->decimal('livestock_amount_other', 20, 2)->nullable();
            $table->decimal('livestock_price_consumer', 20, 2)->nullable();
            $table->decimal('livestock_price_coop', 20, 2)->nullable();
            $table->decimal('livestock_price_market', 20, 2)->nullable();
            $table->decimal('livestock_price_trader', 20, 2)->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestock_uses');
    }
};
