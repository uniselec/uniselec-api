<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("
            ALTER TABLE `convocation_list_applications`
            MODIFY COLUMN `response_status`
            ENUM('pending','accepted','declined','declined_other_list','rejected')
            NOT NULL DEFAULT 'pending'
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE `convocation_list_applications`
            MODIFY COLUMN `response_status`
            ENUM('pending','accepted','declined','declined_other_list')
            NOT NULL DEFAULT 'pending'
        ");
    }
};
