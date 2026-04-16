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
        Schema::create('data_dictionary_entries', function (Blueprint $table) {
            $table->id();
            $table->boolean('for_indicators')->default(0);
            $table->string('worksheet')->nullable();
            $table->string('variable')->nullable();
            $table->string('theme')->nullable();
            $table->string('survey_section')->nullable();
            $table->string('indicator_number')->nullable();
            $table->string('indicator_name')->nullable();
            $table->text('question_or_definition')->nullable();
            $table->string('type')->nullable();
            $table->string('code_list')->nullable();
            $table->string('calculation')->nullable();
            $table->string('multiple_choice_option_label')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_dictionary_entries');
    }
};
