<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Adjustments on convocation_list_applications
        Schema::table('convocation_list_applications', function (Blueprint $table) {

            $table->dropColumn(['ranking_at_generation', 'ranking_in_category', 'status']);

            $table->unsignedInteger('category_ranking')
                  ->after('admission_category_id');

            $table->unsignedInteger('general_ranking')
                  ->after('category_ranking');


            $table->enum('convocation_status', [
                'pending',
                'called',
                'called_out_of_quota',
                'skipped',
            ])
            ->default('pending')
            ->after('category_ranking');

            // Result status
            $table->enum('result_status', [
                'classified',
                'classifiable',
            ])
            ->after('convocation_status')->default('classifiable');

            // Candidate response status
            $table->enum('response_status', [
                'pending',
                'accepted',
                'declined',
                'declined_other_list',
            ])
            ->default("pending")
            ->after('result_status');
        });


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('convocation_list_applications', function (Blueprint $table) {
            // Restore old columns
            $table->enum('status', ['eligible', 'convoked', 'skipped'])
                  ->default('eligible')
                  ->after('admission_category_id');
            $table->unsignedInteger('ranking_at_generation')
                  ->after('seat_id');
            $table->unsignedInteger('ranking_in_category')
                  ->nullable()
                  ->after('ranking_at_generation');

            // Remove new columns
            $table->dropColumn([
                'category_ranking',
                'convocation_status',
                'result_status',
                'response_status',
            ]);
        });

    }
};
