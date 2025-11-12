<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appeal_documents', function (Blueprint $table) {
            $table->id();
            // Relation to the appeal
            $table->foreignId('appeal_id')->constrained()->onDelete('cascade');
            // File data
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appeal_documents');
    }
};
