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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('modality', ['distance', 'in-person']);
            $table->string('campus');
            $table->string('state', 2);
            $table->timestamps();
        });
        Schema::create('process_selections', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['draft', 'active', 'finished', 'archived'])->default('draft');
            $table->string('name');
            $table->string('description');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('selection_type', ['sisu'])->default('sisu');
            $table->timestamps();
        });
        Schema::create('process_selection_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_selection_id')->constrained('process_selections')->onDelete('cascade');
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->integer('vacancies');
            $table->timestamps();
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('process_selection_id')->nullable()->constrained('process_selections')->onDelete('cascade')->after('id');
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('process_selection_id')
                ->nullable()
                ->constrained('process_selections')
                ->onDelete('set null')
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
        Schema::dropIfExists('process_selections');
        Schema::dropIfExists('process_selection_courses');
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['process_selection_id']);
            $table->dropColumn('process_selection_id');
        });
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['process_selection_id']);
            $table->dropColumn('process_selection_id');
        });
    }
};
