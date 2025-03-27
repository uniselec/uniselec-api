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
        Schema::dropIfExists('process_selection_courses');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_selection_courses', function (Blueprint $table) {
            //
        });
    }
};
