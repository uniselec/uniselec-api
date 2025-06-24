<?php
// database/seeders/CourseSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $now     = Carbon::now();
        $unit    = DB::table('academic_units')->find(1);
        $unitObj = json_encode($unit);

        $inPerson = [
            'Administração Pública','Agronomia','Antropologia','Bacharelado em Humanidades – BHU',
            'Ciências Biológicas – Licenciatura','Ciências da Natureza e Matemática','Ciências Sociais',
            'Enfermagem','Engenharia de Alimentos','Engenharia de Computação','Engenharia de Energias',
            'Farmácia','Física','História','Letras – Língua Portuguesa','Letras – Língua Inglesa',
            'Licenciatura em Educação Escolar Quilombola','Licenciatura Intercultural Indígena',
            'Matemática – Licenciatura','Medicina','Pedagogia – Licenciatura','Química – Licenciatura',
            'Relações Internacionais','Serviço Social','Sociologia – Licenciatura',
        ];

        $distance = [
            'Bacharelado em Administração Pública EaD',
            'Licenciatura Computação EaD',
            'Licenciatura Interdisciplinar em Ciências Naturais EaD',
            'Licenciatura em Letras – Língua Portuguesa EaD',
        ];

        $records = collect($inPerson)->concat($distance)->values()->map(function ($course, $index) use ($now, $unitObj, $inPerson) {
            return [
                'id'            => $index + 1,                                      // id fixo
                'name'          => $course,
                'academic_unit' => $unitObj,
                'modality'      => $index < count($inPerson) ? 'in-person' : 'distance',
                'created_at'    => $now,
                'updated_at'    => $now,
            ];
        })->all();

        DB::table('courses')->upsert($records, ['id'], ['name', 'academic_unit', 'modality', 'updated_at']);
    }
}
