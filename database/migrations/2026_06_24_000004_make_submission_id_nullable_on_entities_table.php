<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration aligning entities.submission_id with the package (015_create_entities_table.php),
// where the column is nullable. Entities may now exist without an originating submission
// (e.g. imported / cloned / universal-dataset entities).
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable()->change();
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropForeign(['submission_id']);
        });

        Schema::table('entities', function (Blueprint $table) {
            $table->foreignId('submission_id')->nullable(false)->change();
            $table->foreign('submission_id')->references('id')->on('submissions')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }
};
