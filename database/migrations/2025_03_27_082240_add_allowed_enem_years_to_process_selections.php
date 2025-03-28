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
        Schema::table('process_selections', function (Blueprint $table) {
            // Adiciona o campo JSON para armazenar os anos permitidos para as notas do ENEM
            $table->json('allowed_enem_years')
                  ->nullable()
                  ->after('modalities');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('process_selections', function (Blueprint $table) {
            //
        });
    }
};
