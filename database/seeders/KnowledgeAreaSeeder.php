<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KnowledgeAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('knowledge_areas')->insert([
            [
                'id' => 1,
                'name' => 'Ciências da Natureza',
                'slug' => 'science_score',
                'description' => 'Ciências da Natureza e suas Tecnologias',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 2,
                'name' => 'Matemática',
                'slug' => 'math_score',
                'description' => 'Matemática e suas Tecnologias',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 3,
                'name' => 'Ciências Humanas',
                'slug' => 'humanities_score',
                'description' => 'Ciências Humanas e suas Tecnologias',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 4,
                'name' => 'Linguagens',
                'slug' => 'language_score',
                'description' => 'Linguagens, Códigos e suas Tecnologias',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'id' => 5,
                'name' => 'Redação',
                'slug' => 'writing_score',
                'description' => 'Redação',
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }
}
