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
        Schema::rename('academic_unities', 'academic_units');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_unities', function (Blueprint $table) {
            //
        });
    }
};
