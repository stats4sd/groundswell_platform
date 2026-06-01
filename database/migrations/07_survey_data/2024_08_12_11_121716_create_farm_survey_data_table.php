<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {

    // Note: below error occurred if section below column "internet" is uncommented
    // PDOException::("SQLSTATE[42000]: Syntax error or access violation: 1118 Row size too large (> 8126). Changing some columns to TEXT or BLOB may help. In current row format, BLOB prefix of 0 bytes is stored inline."

    // Google searched below stackoverflow thread:
    // MySQL: Error Code: 1118 Row size too large (> 8126). Changing some columns to TEXT or BLOB
    // https://stackoverflow.com/questions/22637733/mysql-error-code-1118-row-size-too-large-8126-changing-some-columns-to-te

    // Solution:
    // Run below SQL in TablePlus
    // SET GLOBAL innodb_strict_mode = 0;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('farm_survey_data', function (Blueprint $table) {
            $table->id();

            $table->json('properties')->nullable();

            $table->text('start')->nullable();
            $table->text('end')->nullable();
            $table->text('today')->nullable();
            $table->text('deviceid')->nullable();
            $table->text('inquirer')->nullable();

            $table->text('household_survey_date')->nullable();
            $table->unsignedBigInteger('farm_id')->nullable();

            // location
            $table->decimal('latitude', 11, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->integer('altitude')->nullable();
            $table->decimal('accuracy', 9, 4)->nullable();
            $table->text('gps_location_alt')->nullable();

            $table->foreignId('submission_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_survey_data');
    }
};
