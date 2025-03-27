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
        Schema::create('process_selection_modalities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('process_selection_id');
            $table->unsignedBigInteger('modality_id');
            // Caso você queira guardar informações extras, como vagas, pode adicionar:
            $table->integer('vacancies')->default(0);
            $table->timestamps();

            // Definindo as chaves estrangeiras
            $table->foreign('process_selection_id')
                  ->references('id')
                  ->on('process_selections')
                  ->onDelete('cascade');

            $table->foreign('modality_id')
                  ->references('id')
                  ->on('modalities')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_selection_modalities');
    }
};
