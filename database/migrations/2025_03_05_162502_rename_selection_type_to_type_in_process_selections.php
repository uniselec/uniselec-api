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
        Schema::table('process_selections', function (Blueprint $table) {
            $table->enum('type', ['sisu'])->nullable()->after('selection_type');
        });

        // Passo 2: Copiar os dados da coluna antiga para a nova
        DB::statement('UPDATE process_selections SET type = selection_type');

        // Passo 3: Remover a coluna antiga
        Schema::table('process_selections', function (Blueprint $table) {
            $table->dropColumn('selection_type');
        });

        // Passo 4: Definir a nova coluna como NOT NULL (se necessário)
        Schema::table('process_selections', function (Blueprint $table) {
            $table->enum('type', ['sisu'])->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Passo 1: Recriar a coluna antiga como ENUM
        Schema::table('process_selections', function (Blueprint $table) {
            $table->enum('selection_type', ['sisu'])->nullable()->after('type');
        });

        // Passo 2: Restaurar os dados
        DB::statement('UPDATE process_selections SET selection_type = type');

        // Passo 3: Remover a nova coluna
        Schema::table('process_selections', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
