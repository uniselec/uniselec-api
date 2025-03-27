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
            // Remover a chave estrangeira e a coluna antiga
            $table->dropForeign(['academic_unity_id']);
            $table->dropColumn('academic_unity_id');

            // Adicionar o novo campo JSON que representa uma única unidade acadêmica
            $table->json('academic_unit')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('courses', function (Blueprint $table) {
            // Remover o campo JSON
            $table->dropColumn('academic_unit');

            // Recriar a coluna academic_unity_id e sua foreign key
            $table->unsignedBigInteger('academic_unity_id')->after('name');
            $table->foreign('academic_unity_id')
                  ->references('id')
                  ->on('academic_unities')
                  ->onDelete('cascade');
        });
    }
};
