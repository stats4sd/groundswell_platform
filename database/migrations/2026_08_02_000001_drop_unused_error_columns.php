<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // hasColumn guards let this no-op on fresh databases built from the
        // edited create-table migrations, which no longer define these columns.
        if (Schema::hasColumn('xlsform_templates', 'odk_error')) {
            Schema::table('xlsform_templates', function (Blueprint $table) {
                $table->dropColumn('odk_error');
            });
        }

        if (Schema::hasColumn('xlsforms', 'odk_error')) {
            Schema::table('xlsforms', function (Blueprint $table) {
                $table->dropColumn('odk_error');
            });
        }

        foreach (['errors', 'processed', 'entries'] as $column) {
            if (Schema::hasColumn('submissions', $column)) {
                Schema::table('submissions', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
