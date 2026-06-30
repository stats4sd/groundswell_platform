<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's datasets.label column (000_create_datasets_table.php).
// Identifies which variable in the dataset is the default "label" (e.g. "name" or "first_name").
// Added as nullable: the package declares it NOT NULL, but the app's datasets table is already
// populated (and seeded in tests), so a non-nullable column with no default would fail.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('label')
                ->nullable()
                ->after('name')
                ->comment('which variable in the dataset is the default "label"? (e.g. "name" or "first_name")');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropColumn('label');
        });
    }
};
