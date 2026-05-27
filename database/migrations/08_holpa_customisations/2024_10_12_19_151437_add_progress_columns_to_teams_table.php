∑<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('languages_complete')->after('diet_diversity_module_version_id')->default(0);
            $table->boolean('sampling_complete')->after('languages_complete')->default(0);
            $table->boolean('pba_complete')->after('sampling_complete')->default(0);
            $table->boolean('lisp_complete')->after('pba_complete')->default(0);
            $table->boolean('pilot_complete')->after('lisp_complete')->default(0);
            $table->boolean('data_collection_complete')->after('pilot_complete')->default(0);
            $table->boolean('data_analysis_complete')->after('data_collection_complete')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            //
        });
    }
};
