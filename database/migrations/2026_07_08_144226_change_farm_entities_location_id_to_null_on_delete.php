<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// farm_entities.location_id previously cascadeOnDelete()'d, inherited unchanged from the
// old farms table - but unlike Farm, FarmEntity is only a structural link (the real farm
// content lives on ODK Central), so a database-level cascade hard-deleting it whenever a
// Location is deleted (Location has no SoftDeletes) was a real bug, not a reset: it bypassed
// FarmEntity's own SoftDeletes, and could leave farm import's local-only dedup unable to see
// farms that still exist on Central. See docs/plans/odk-entities-farm-crud.md. Deleting a
// location now just clears location_id on any linked farm instead of destroying it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_entities', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });

        Schema::table('farm_entities', function (Blueprint $table) {
            $table->foreign('location_id')
                ->references('id')->on('locations')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('farm_entities', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
        });

        Schema::table('farm_entities', function (Blueprint $table) {
            $table->foreign('location_id')
                ->references('id')->on('locations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
