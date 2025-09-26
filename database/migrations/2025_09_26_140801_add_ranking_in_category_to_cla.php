<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration {
    public function up(): void
    {
        Schema::table('convocation_list_applications', function (Blueprint $table) {
            $table->unique(
                ['convocation_list_id', 'application_id', 'admission_category_id'],
                'cla_unique_per_list2'
            );
        });
    }

    public function down(): void
    {
        Schema::table('convocation_list_applications', function (Blueprint $table) {
            $table->dropUnique('cla_unique_per_list');
            $table->dropColumn('ranking_in_category');
        });
    }
};
