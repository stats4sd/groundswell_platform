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
        Schema::table('xlsform_versions', function (Blueprint $table) {
            $table->dropForeign('xlsform_versions_xlsform_id_foreign');
            $table->foreign('xlsform_id')->references('id')->on('xlsforms')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlsform_versions', function (Blueprint $table) {
            //
        });
    }
};
