<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AcademicUnitSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('academic_units')->insert([
            [
                'name'        => 'Liberdade',
                'description' => 'Campus de Liberdade',
                'state'       => 'CE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Auroras',
                'description' => 'Campus das Auroras',
                'state'       => 'CE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Baturite',
                'description' => 'Campus do Baturite',
                'state'       => 'CE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Malês',
                'description' => 'Campus dos Malês',
                'state'       => 'BA',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'name'        => 'Palmares',
                'description' => 'Campus dos Palmares',
                'state'       => 'CE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
