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
        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('lisp_complete', 'optional_modules_complete');
            $table->renameColumn('data_analysis_complete', 'setup_survey_complete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->renameColumn('optional_modules_complete', 'lisp_complete');
            $table->renameColumn('setup_survey_complete', 'data_analysis_complete');
        });
    }
};
