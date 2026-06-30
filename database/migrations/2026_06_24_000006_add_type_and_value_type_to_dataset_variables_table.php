<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's dataset_variables.type and .value_type columns
// (016_create_dataset_variables_table.php). Both are written by
// DatasetVariable::upsert(...) in XlsformTemplate; value_type is read in
// OdkSubmissionService to detect select_multiple fields.
//
// `type` is added nullable() on purpose, NOT matching the package literally: the package
// migration declares `$table->string('type')->nulllable()` (typo, three L's), which Laravel's
// fluent __call silently swallows, leaving the column NOT NULL with no default upstream — a bug.
// The app's dataset_variables table is already populated/seeded and the upsert does not always
// supply `type`, so a NOT NULL column with no default would fail here.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dataset_variables', function (Blueprint $table) {
            $table->string('type')->nullable()->after('label');
            $table->string('value_type')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dataset_variables', function (Blueprint $table) {
            $table->dropColumn(['type', 'value_type']);
        });
    }
};
