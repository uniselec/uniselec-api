<?php
// database/seeders/AcademicUnitSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AcademicUnitSeeder extends Seeder
{
    public function run(): void
    {
        $now   = Carbon::now();
        $units = [
            ['id' => 1, 'name' => 'Liberdade',  'description' => 'Campus de Liberdade',  'state' => 'CE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Auroras',    'description' => 'Campus das Auroras',   'state' => 'CE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 3, 'name' => 'Baturite',   'description' => 'Campus do Baturite',   'state' => 'CE', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 4, 'name' => 'Malês',      'description' => 'Campus dos Malês',     'state' => 'BA', 'created_at' => $now, 'updated_at' => $now],
            ['id' => 5, 'name' => 'Palmares',   'description' => 'Campus dos Palmares',  'state' => 'CE', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('academic_units')->upsert($units, ['id'], ['name', 'description', 'state', 'updated_at']);
    }
}

