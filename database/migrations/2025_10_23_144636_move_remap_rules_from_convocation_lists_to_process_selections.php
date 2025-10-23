<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        // 1) Remover de convocation_lists
        Schema::table('convocation_lists', function (Blueprint $table) {
            if (Schema::hasColumn('convocation_lists', 'remap_rules')) {
                $table->dropColumn('remap_rules');
            }
        });

        // 2) Adicionar em process_selections
        Schema::table('process_selections', function (Blueprint $table) {
            if (!Schema::hasColumn('process_selections', 'remap_rules')) {
                $table->json('remap_rules')
                      ->nullable()
                      ->comment('JSON com ordem de remanejamento de vagas entre categorias')
                      ->after('allowed_enem_years');
            }
        });
    }

    public function down()
    {
        // 1) Restaurar em convocation_lists
        Schema::table('convocation_lists', function (Blueprint $table) {
            if (!Schema::hasColumn('convocation_lists', 'remap_rules')) {
                $table->longText('remap_rules')
                      ->nullable()
                      ->comment('JSON com ordem de remanejamento de vagas entre categorias')
                      ->after('generated_by');
            }
        });

        // 2) Remover de process_selections
        Schema::table('process_selections', function (Blueprint $table) {
            if (Schema::hasColumn('process_selections', 'remap_rules')) {
                $table->dropColumn('remap_rules');
            }
        });
    }
};
