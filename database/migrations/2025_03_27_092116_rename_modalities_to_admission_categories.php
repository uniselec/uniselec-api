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
        Schema::rename('modalities', 'admission_categories');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('modalities', function (Blueprint $table) {
            //
        });
    }
};
