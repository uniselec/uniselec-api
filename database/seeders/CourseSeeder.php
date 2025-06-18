<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Mesma unidade acadêmica para todos os cursos
        $academicUnit = json_encode([
            "id"          => 1,
            "name"        => "Liberdade",
            "description" => "Campus de Liberdade",
            "state"       => "CE",
            "created_at"  => "2025-06-18T16:36:27.000000Z",
            "updated_at"  => "2025-06-18T16:36:27.000000Z",
        ]);

        // Cursos presenciais
        $inPerson = [
            'Administração Pública',
            'Agronomia',
            'Antropologia',
            'Bacharelado em Humanidades – BHU',
            'Ciências Biológicas – Licenciatura',
            'Ciências da Natureza e Matemática',
            'Ciências Sociais',
            'Enfermagem',
            'Engenharia de Alimentos',
            'Engenharia de Computação',
            'Engenharia de Energias',
            'Farmácia',
            'Física',
            'História',
            'Letras – Língua Portuguesa',
            'Letras – Língua Inglesa',
            'Licenciatura em Educação Escolar Quilombola',
            'Licenciatura Intercultural Indígena',
            'Matemática – Licenciatura',
            'Medicina',
            'Pedagogia – Licenciatura',
            'Química – Licenciatura',
            'Relações Internacionais',
            'Serviço Social',
            'Sociologia – Licenciatura',
        ];

        // Cursos EaD
        $distance = [
            'Bacharelado em Administração Pública EaD',
            'Licenciatura Computação EaD',
            'Licenciatura Interdisciplinar em Ciências Naturais EaD',
            'Licenciatura em Letras – Língua Portuguesa EaD',
        ];

        // Monta o payload
        $records = collect($inPerson)
            ->map(fn ($course) => [
                'name'          => $course,
                'academic_unit' => $academicUnit,
                'modality'      => 'in-person',
                'created_at'    => $now,
                'updated_at'    => $now,
            ])
            ->merge(
                collect($distance)->map(fn ($course) => [
                    'name'          => $course,
                    'academic_unit' => $academicUnit,
                    'modality'      => 'distance',
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ])
            )
            ->all();

        DB::table('courses')->insert($records);
    }
}
