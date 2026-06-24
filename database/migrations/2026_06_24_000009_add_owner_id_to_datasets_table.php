<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's datasets.owner_id FK
// (000_create_datasets_table.php). Ownership has moved from the polymorphic
// model_id/model_type morph to a single direct owner_id FK (the form owner / Team).
// Dataset::owner() is belongsTo(form_owner, 'owner_id') and HasXlsforms::datasets() is
// hasMany(Dataset::class, 'owner_id') — both inherited relations depend on this column.
//
// Resolves the owner table from the new `form_owner` config key (added to
// config/filament-odk-link.php), deliberately not the deprecated `team_model` key.
//
// Deliberate deviations from the package, justified by the app's seeded state:
//  - morph columns model_id/model_type are RETAINED (the package kept them too; the morph
//    is now dormant for datasets). Removing them is a separate, out-of-scope cleanup.
//  - the app keeps its existing unique('name') index and does NOT switch to the package's
//    composite unique(['name','owner_id']): every app dataset has owner_id = NULL, and SQL
//    treats NULLs as distinct, so a composite index would silently stop enforcing name
//    uniqueness. Revisit if per-owner datasets are introduced.
//
// Backfill: none. The app's DatasetSeeder creates global/unowned datasets, so owner_id
// stays NULL for all existing rows.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $ownerTable = (new (config('filament-odk-link.models.form_owner')))->getTable();

        Schema::table('datasets', function (Blueprint $table) use ($ownerTable) {
            $table->foreignId('owner_id')->nullable()->constrained($ownerTable)->after('model_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
