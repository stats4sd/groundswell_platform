<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration mirroring the package's 031_create_locale_owners_table.php.
// Pivot linking locales to form owners (teams). The package migration's down() drops the
// wrong table ('language_owner'); the down() here correctly drops 'locale_owner'.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teamTable = (new (config('filament-odk-link.models.team_model')))->getTable();

        Schema::create('locale_owner', function (Blueprint $table) use ($teamTable) {
            $table->id();
            $table->foreignId('locale_id')->constrained('locales')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('owner_id')->constrained($teamTable)->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locale_owner');
    }
};
