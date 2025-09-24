<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocation_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_selection_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('name');
            $table->enum('status', ['draft', 'published'])
                  ->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('generated_by')
                  ->nullable()
                  ->constrained('admins')
                  ->nullOnDelete();

            $table->json('remap_rules')
                  ->nullable()
                  ->comment('JSON com ordem de remanejamento de vagas entre categorias');

            $table->timestamps();

            $table->index('process_selection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocation_lists');
    }
};
