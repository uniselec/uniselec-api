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
        Schema::table('process_selections', function (Blueprint $table) {
            $table->timestamp('preliminary_result_date')->nullable()->after('end_date'); // data do resultado preliminar
            $table->timestamp('appeal_start_date')->nullable()->after('preliminary_result_date'); // início do período de recurso
            $table->timestamp('appeal_end_date')->nullable()->after('appeal_start_date'); // fim do período de recurso
            $table->timestamp('final_result_date')->nullable()->after('appeal_end_date'); // data do resultado final
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_selections', function (Blueprint $table) {
            $table->dropColumn([
                'preliminary_result_date',
                'appeal_start_date',
                'appeal_end_date',
                'final_result_date',
            ]);
        });
    }
};
