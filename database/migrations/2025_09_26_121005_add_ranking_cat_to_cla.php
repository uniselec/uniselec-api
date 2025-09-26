<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('convocation_list_applications', function (Blueprint $table) {
            $table->unsignedInteger('ranking_in_category')
                  ->nullable()
                  ->after('ranking_at_generation');
            $table->index(['admission_category_id','ranking_in_category'], 'idx_cla_cat_rank');
        });
    }

    public function down(): void
    {
        Schema::table('convocation_list_applications', function (Blueprint $table) {
            $table->dropIndex('idx_cla_cat_rank');
            $table->dropColumn('ranking_in_category');
        });
    }
};