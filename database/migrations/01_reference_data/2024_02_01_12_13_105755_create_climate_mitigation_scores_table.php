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
        Schema::create('climate_mitigation_scores', function (Blueprint $table) {
            $table->id();
            $table->string('region_id')->constrained('regions');
            $table->foreignId('ag_practice_id')->constrained('ag_practices');
            $table->decimal('score', 10, 4);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('climate_mitigation_scores');
    }
};
