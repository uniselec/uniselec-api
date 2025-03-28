<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE process_selections
            CHANGE COLUMN modalities admission_categories longtext
            CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
        ALTER TABLE process_selections
        CHANGE COLUMN admission_categories modalities longtext
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
        CHECK (json_valid(`modalities`))
    ");
    }
};
