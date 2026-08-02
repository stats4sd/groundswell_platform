<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imports', function (Blueprint $table) {
            // Import::user() has always pointed at a column that did not exist. Nullable
            // because every existing row predates it, and a future system-triggered import
            // may have no user behind it at all.
            $table->foreignId('user_id')->nullable()->after('team_id')->constrained()->nullOnDelete();

            $table->timestamp('finished_at')->nullable()->after('errors');
        });
    }
};
