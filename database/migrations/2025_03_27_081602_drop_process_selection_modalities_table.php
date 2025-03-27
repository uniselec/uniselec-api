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
        Schema::dropIfExists('process_selection_modalities');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_selection_modalities', function (Blueprint $table) {
            //
        });
    }
};
