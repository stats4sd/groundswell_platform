<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('xlsform_modules', function (Blueprint $table) {
            $table->unsignedInteger('default_order')->default(0)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlsform_module_versions', function (Blueprint $table) {
            //
        });
    }
};
