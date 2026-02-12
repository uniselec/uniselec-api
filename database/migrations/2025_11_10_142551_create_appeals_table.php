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
        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->onDelete('cascade');
            $table->text('justification'); // Candidate's justification text
            $table->text('decision')->nullable(); // Evaluator's textual decision or comments
            $table->enum('status', ['submitted', 'accepted', 'rejected'])->default('submitted');
            $table->string('reviewed_by')->nullable(); // Name of the person who reviewed the appeal
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appeals');
    }
};
