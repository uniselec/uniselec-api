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
            $table->timestamp('last_applications_processed_at')
                ->nullable()
                ->after('bonus_options');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_selections', function (Blueprint $table) {
            $table->dropColumn('last_applications_processed_at');
        });
    }
};
