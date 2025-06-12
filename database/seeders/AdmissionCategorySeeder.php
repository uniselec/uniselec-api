<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdmissionCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('admission_categories')->insert([
            [
                'name'        => 'LB - PPI',
                'description' => 'LB - PPI: Candidatos autodeclarados pretos, pardos ou indígenas, com renda familiar bruta per capita igual ou inferior a 1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LB - Q',
                'description' => 'LB - Q: Candidatos autodeclarados quilombolas, com renda familiar bruta per capita igual ou inferior a 1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LB - PCD',
                'description' => 'LB - PCD: Candidatos com deficiência, que tenham renda familiar bruta per capita igual ou inferior a 1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LB - EP',
                'description' => 'LB - EP: Candidatos com renda familiar bruta per capita igual ou inferior a 1 salário mínimo que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LI - PPI',
                'description' => 'LI - PPI: Candidatos autodeclarados pretos, pardos ou indígenas, independentemente da renda, que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LI - Q',
                'description' => 'LI - Q: Candidatos autodeclarados quilombolas, independentemente da renda, que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LI - PCD',
                'description' => 'LI - PCD: Candidatos com deficiência, independentemente da renda, que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'LI - EP',
                'description' => 'LI - EP: Candidatos que, independentemente da renda, tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'AC',
                'description' => 'AC: Ampla Concorrência',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
