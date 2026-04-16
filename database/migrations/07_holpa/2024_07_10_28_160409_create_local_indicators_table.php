<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('local_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('global_indicator_id')->nullable()->constrained('global_indicators')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('xlsform_module_version_id')->nullable()->constrained('xlsform_module_versions')->nullOnDelete()->cascadeOnUpdate();
            $table->text('name');
            $table->string('survey')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('local_indicators');
    }
};
