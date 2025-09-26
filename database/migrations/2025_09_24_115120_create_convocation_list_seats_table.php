<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocation_list_seats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('convocation_list_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('seat_code', 30)->unique();

            $table->foreignId('course_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('origin_admission_category_id')
                  ->constrained('admission_categories');

            $table->foreignId('current_admission_category_id')
                  ->constrained('admission_categories');

            $table->enum('status', ['open', 'reserved', 'filled'])
                  ->default('open');

            $table->foreignId('application_id')
                  ->nullable()
                  ->constrained('applications')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['course_id', 'current_admission_category_id'], 'cls_modality_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocation_list_seats');
    }
};