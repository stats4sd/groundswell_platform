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
        Schema::create('livestocks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_survey_data_id')->nullable();
            $table->json('properties')->nullable();

            $table->unsignedInteger('livestock_id')->nullable();
            $table->text('livestock_name')->nullable();
            $table->text('livestock_other_check')->nullable();
            $table->text('livestock_label')->nullable();
            $table->integer('livestock_breeds')->nullable();
            $table->integer('number_raised')->nullable();
            $table->text('livestock_filter')->nullable();
            $table->text('livestock_uses')->nullable();
            $table->text('livestock_use_other')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livestocks');
    }
};
