<?php
// database/migrations/2025_06_22_rename_data_to_form_data_in_applications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Substitui a coluna `data` por `form_data`.
     * Mantém os valores existentes.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // 1) Cria a nova coluna JSON
            $table->json('form_data')->nullable()->after('user_id');
        });

        // 2) Copia os valores
        DB::statement('UPDATE applications SET form_data = data');

        // 3) Remove a coluna antiga
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('data');
        });
    }

    /**
     * Reverte a mudança, voltando a coluna para `data`.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->json('data')->nullable()->after('user_id');
        });

        DB::statement('UPDATE applications SET data = form_data');

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });
    }
};
