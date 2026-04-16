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
        Schema::table('xlsform_modules', function (Blueprint $table) {
            $table->boolean('can_be_extended')->default(false);
            $table->boolean('can_be_replaced')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('xlsform_modules', function (Blueprint $table) {
            $table->dropColumn('can_be_extended');
            $table->dropColumn('can_be_replaced');
        });
    }
};
