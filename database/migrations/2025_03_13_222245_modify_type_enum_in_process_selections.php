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
    public function up()
    {
        DB::statement("ALTER TABLE process_selections MODIFY COLUMN type ENUM('sisu', 'enem_score') NOT NULL;");
    }


    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement("ALTER TABLE process_selections MODIFY COLUMN type ENUM('sisu') NOT NULL;");
    }
};
