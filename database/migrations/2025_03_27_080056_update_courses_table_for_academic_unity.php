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
        Schema::table('courses', function (Blueprint $table) {
            // Removendo as colunas antigas
            $table->dropColumn(['campus', 'state']);

            // Adicionando a chave estrangeira para academic_unities
            $table->unsignedBigInteger('academic_unity_id')->after('modality');

            $table->foreign('academic_unity_id')
                ->references('id')
                ->on('academic_unities')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            // Removendo a chave estrangeira e a coluna nova
            $table->dropForeign(['academic_unity_id']);
            $table->dropColumn('academic_unity_id');

            // Reinserindo as colunas removidas (ajuste os tipos conforme necessário)
            $table->string('campus')->after('modality');
            $table->string('state', 2)->after('campus');
        });
    }
};
