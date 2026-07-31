<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// The legacy farms table and the abandoned HOLPA survey-data tables. Farms are now ODK
// Central entities (farm_entities); nothing has written crops / livestocks /
// farm_survey_data since HOLPA submission processing was removed. No FK constraints exist
// between any of them. See docs/plans/farm-crud-cutover-and-import-chaining.md.
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('crops');
        Schema::dropIfExists('livestocks');
        Schema::dropIfExists('farm_survey_data');
        Schema::dropIfExists('farms');
    }
};
