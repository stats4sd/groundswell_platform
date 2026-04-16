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
        Schema::create('code_book_entries', function (Blueprint $table) {
            $table->id();
            $table->string('list_name')->nullable();
            $table->string('name')->nullable();
            $table->text('label_en')->nullable();
            $table->text('indicator_value')->nullable();
            $table->text('climate_pillar')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_book_entries');
    }
};
