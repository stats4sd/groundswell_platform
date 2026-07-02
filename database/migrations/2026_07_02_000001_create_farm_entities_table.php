<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// New, ODK-Central-Entities-backed replacement for `farms`, built alongside the existing
// table/model/resource so both can run side by side until the new page is proven out.
// See docs/plans/odk-entities-farm-crud.md. Identifiers/properties are not columns here -
// they live in ODK Central and are read through the OData feed rather than cached locally.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('teams')->cascadeOnDelete()->cascadeOnUpdate();
            // Nullable: farms discovered from ODK Central that weren't created through this
            // app (e.g. by a registration form's `entities` sheet) have no app-side location match.
            $table->foreignId('location_id')->nullable()->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('team_code');

            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();

            $table->string('odk_uuid')->nullable()->unique();
            $table->unsignedInteger('odk_version')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_entities');
    }
};
