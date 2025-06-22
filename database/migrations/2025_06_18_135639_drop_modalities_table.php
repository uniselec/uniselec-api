<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove a tabela modalities.
     */
    public function up(): void
    {
        Schema::dropIfExists('modalities');
    }

    /**
     * Recria a tabela modalities caso seja necessário fazer rollback.
     */
    public function down(): void
    {
        Schema::create('modalities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }
};
