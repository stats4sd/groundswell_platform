<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration for the package's required_media.links_to_dataset column
// (011_create_required_media_table.php). Cast to boolean on RequiredMedia;
// set during XlsformTemplateChoiceListImport, read in OdkFormMediaService, and drives
// field visibility in XlsformTemplateForm.
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('required_media', function (Blueprint $table) {
            $table->boolean('links_to_dataset')->default(false)->after('updated_during_import');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('required_media', function (Blueprint $table) {
            $table->dropColumn('links_to_dataset');
        });
    }
};
