<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's datasets.custom_key column
// (000_create_datasets_table.php). The package admin DatasetForm renders
// TextInput::make('custom_key'); saving a dataset through that form would fail without it.
//
// Added alongside the app's existing primary_key column, NOT as a replacement: the package's
// migration renamed primary_key -> custom_key but package code still reads dataset->primary_key
// (Entity, EntityExport, DatasetInfoList), so the app keeps primary_key. (Flag upstream.)
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->string('custom_key')->nullable()->after('primary_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('datasets', function (Blueprint $table) {
            $table->dropColumn('custom_key');
        });
    }
};
