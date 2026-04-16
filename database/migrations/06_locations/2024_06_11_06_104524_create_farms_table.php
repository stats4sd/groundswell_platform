<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('teams')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('team_code'); // should be unique per team

            // identifiers
            $table->json('identifiers')->nullable(); // identifiers - by default, we consider this personally identifying information of the farm.

            // location
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();

            // Probably temporary until we figure out a generalised approach
            $table->boolean('household_form_completed')->default(false)->comment('Is household form completed for this farm?');
            $table->boolean('fieldwork_form_completed')->default(false)->comment('Is fieldwork form completed for this farm?');

            $table->boolean('household_pilot_completed')->default(false)->comment('Is household form completed for this farm with a pilot-test submission?');
            $table->boolean('fieldwork_pilot_completed')->default(false)->comment('Is fieldwork form completed for this farm with a pilot test submission?');

            $table->boolean('refused')->default(false);

            $table->json('properties')->nullable(); // other properties;

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farms');
    }
};
