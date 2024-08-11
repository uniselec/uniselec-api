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
        Schema::create('application_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['approved', 'rejected'])->nullable();
            $table->enum('classification_status', ['classified', 'classifiable', 'disqualified']);
            $table->decimal('average_score', 8, 2)->nullable();
            $table->decimal('final_score', 8, 2)->nullable();
            $table->integer('ranking')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_status');
    }
};
