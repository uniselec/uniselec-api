<?php

// database/seeders/ProcessSelectionSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ProcessSelectionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Seleciona só os dois cursos desejados
        $courses = DB::table('courses')
            ->whereIn('id', [20, 8])
            ->get()
            ->map(function ($course) {
                $vacancies = $course->id == 20
                    ? ['AC' => 10, 'LB - Q' => 1, 'LI - Q' => 1, 'LB - EP' => 1, 'LI - EP' => 1, 'LB - PCD' => 1, 'LB - PPI' => 1, 'LI - PCD' => 1, 'LI - PPI' => 1]
                    : ['AC' => 20, 'LB - Q' => 3, 'LI - Q' => 3, 'LB - EP' => 3, 'LI - EP' => 2, 'LB - PCD' => 3, 'LB - PPI' => 3, 'LI - PCD' => 3, 'LI - PPI' => 3];

                return [
                    'id'        => $course->id,
                    'name'      => $course->name,
                    'modality'  => $course->modality,
                    'academic_unit' => json_decode($course->academic_unit, true),
                    'vacanciesByCategory' => $vacancies,
                ];
            });

        $categories = DB::table('admission_categories')->get()->values();
        $bonuses    = DB::table('bonus_options')->get()->values();

        DB::table('process_selections')->updateOrInsert(
            ['id' => 1],
            [
                'status'               => 'active',
                'name'                 => 'Edital nº 04/2024 - PROCESSO SELETIVO UNILAB – PERÍODO LETIVO 2024.1 Curso Medicina',
                'description'          => 'Seleção para cursos de Medicina em Baturité 2024',
                'start_date'           => '2024-08-17 06:00:00',
                'end_date'             => '2025-09-30 06:00:00',
                'type'                 => 'enem_score',
                'courses'              => $courses->toJson(JSON_UNESCAPED_UNICODE),
                'admission_categories' => $categories->toJson(JSON_UNESCAPED_UNICODE),
                'allowed_enem_years'   => json_encode([2023]),
                'bonus_options'        => $bonuses->toJson(JSON_UNESCAPED_UNICODE),
                'current_step'         => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]
        );
    }
}
