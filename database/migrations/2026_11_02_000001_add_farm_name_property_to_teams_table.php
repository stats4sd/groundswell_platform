<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The sanitized ODK property name (e.g. `farmer_name`) that holds a farm's name, when the team
// chose a farmer name column on import. Set => farm entity labels are "ID: {code} / Name: {name}";
// null => the label is just the farm code. See docs/plans/2026-10-29-farm-entity-label-with-name.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('farm_name_property')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('farm_name_property');
        });
    }
};
