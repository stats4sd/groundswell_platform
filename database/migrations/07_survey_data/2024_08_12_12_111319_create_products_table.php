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
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->string('product_id')->nullable();
            $table->text('product_name')->nullable();
            $table->text('hh_consumption')->nullable();

            // In Data Structure Excel file, variable names are hh_cooking, hh_building and hh_heating
            // In submission content, variable names are cooking, building and heating
            // Workaround: use variable names in submission content to populate data first
            $table->text('cooking')->nullable();
            $table->text('building')->nullable();
            $table->text('heating')->nullable();

            $table->text('hh_other_use')->nullable();
            $table->text('livestock_consumption')->nullable();
            $table->text('on_farm_use')->nullable();
            $table->text('sales')->nullable();
            $table->text('gifts')->nullable();
            $table->text('waster')->nullable();
            $table->text('other_use')->nullable();
            $table->text('other_use_specify')->nullable();
            $table->text('buyer')->nullable();
            $table->text('buyer_other')->nullable();
            $table->text('fair_price')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
