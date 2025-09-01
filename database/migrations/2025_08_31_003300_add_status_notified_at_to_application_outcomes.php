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
        Schema::table('application_outcomes', function (Blueprint $table) {
            $table->timestamp('status_notified_at')->nullable()->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_outcomes', function (Blueprint $table) {
            $table->dropColumn('status_notified_at');
        });
    }
};
