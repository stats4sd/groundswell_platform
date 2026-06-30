<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's xlsform_modules.row_names column
// (003_create_xlsform_modules_table.php). Cast to 'collection' on XlsformModule;
// written by XlsformModuleImport and read by GetsModuleNamesPerRow.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xlsform_modules', function (Blueprint $table) {
            $table->json('row_names')->nullable()->after('can_be_replaced');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlsform_modules', function (Blueprint $table) {
            $table->dropColumn('row_names');
        });
    }
};
