<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class BonusOptionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('bonus_options')->insert([
            [
                'name'        => '10% para alunos de escolas públicas',
                'description' => 'Estudantes que tenham cursado integralmente o ensino médio em escolas públicas.',
                'value'       => 10.00,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => '20% para alunos de escolas públicas do Baturité',
                'description' => 'Estudantes que tenham cursado e concluído integralmente o ensino médio em instituições de ensino, públicas ou privadas, localizadas na região do Maciço do Baturité.',
                'value'       => 20.00,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
