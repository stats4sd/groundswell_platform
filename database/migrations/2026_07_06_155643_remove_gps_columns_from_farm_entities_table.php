<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GPS moved from local-only columns to Central-synced entity properties (fixed keys,
// same pattern as team_code) - see docs/plans/farm-entities-simplify-and-gps-sync.md.
// Confirmed no existing non-null GPS data before this ran.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_entities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'altitude', 'accuracy']);
        });
    }

    public function down(): void
    {
        Schema::table('farm_entities', function (Blueprint $table) {
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();
        });
    }
};
