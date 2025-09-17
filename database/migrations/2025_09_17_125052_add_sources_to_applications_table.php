<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('name_source', ['enem', 'application'])
                  ->nullable()
                  ->default(null)
                  ->after('created_at');

            $table->enum('birthdate_source', ['enem', 'application'])
                  ->nullable()
                  ->default(null)
                  ->after('name_source');

            $table->enum('cpf_source', ['enem', 'application'])
                  ->nullable()
                  ->default(null)
                  ->after('birthdate_source');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['name_source', 'birthdate_source', 'cpf_source']);
        });
    }
};