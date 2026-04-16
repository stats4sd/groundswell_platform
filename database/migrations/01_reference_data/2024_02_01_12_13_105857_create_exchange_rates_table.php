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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency_id')->constrained('currencies')->onDelete('cascade');
            $table->string('to_currency_id')->constrained('currencies')->onDelete('cascade')
                ->default('USD');
            $table->date('date');
            $table->decimal('rate', 10, 4);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_conversions');
    }
};
