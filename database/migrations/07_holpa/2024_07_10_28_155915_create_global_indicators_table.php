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
        Schema::create('global_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('theme_id')->nullable()->constrained('themes')->cascadeOnDelete()->cascadeOnUpdate();
            //$table->string('type'); // "Core (required) indicators" or "Optional indicators"
            $table->text('name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_indicators');
    }
};
