<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convocation_list_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('convocation_list_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('application_id')
                  ->cascadeOnDelete();

            $table->foreignId('course_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('admission_category_id')
                  ->constrained('admission_categories');

            $table->foreignId('seat_id')
                  ->nullable()
                  ->constrained('convocation_list_seats')
                  ->nullOnDelete();

            $table->unsignedInteger('ranking_at_generation');

            $table->enum('status', ['eligible', 'convoked', 'skipped'])
                  ->default('eligible');

            $table->timestamps();

            $table->index(['course_id', 'admission_category_id'], 'cla_modality_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convocation_list_applications');
    }
};