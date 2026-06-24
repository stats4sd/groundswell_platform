<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Catch-up migration mirroring the package's 033_create_dataset_parents_table.php.
// Backs the inherited Dataset::parentDatasets() / childDatasets() relations (ParentDatasetPivot),
// which the app already uses (e.g. App\Exports\DataExport\FarmSurveyDataExport).
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dataset_parents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('parent_id')->constrained('datasets')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('child_id')->constrained('datasets')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dataset_parents');
    }
};
