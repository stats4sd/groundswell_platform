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
        Schema::create('gni_entries', function (Blueprint $table) {
            $table->id();
            $table->string('country_id')->constrained('countries');
            $table->string('currency_id')->constrained('currencies');
            $table->year('year');
            $table->decimal('gni', 30, 10);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gni');
    }
};
