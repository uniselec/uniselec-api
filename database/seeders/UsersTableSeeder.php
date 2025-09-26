<?php
// database/seeders/UsersTableSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;
use Carbon\Carbon;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker     = Faker::create('pt_BR');
        $processId = 1;
        $enemBase  = 231000000000;
        $now       = Carbon::now();

        /* -----------------------------------------------------------------
         | 1. Somente cursos permitidos → id 1 (Administração Pública-Liberdade)
         |    e id 8 (Enfermagem-Liberdade)
         * -----------------------------------------------------------------*/
        $courses = DB::table('courses')
            ->whereIn('id', [1, 8])
            ->get()
            ->keyBy('id');               // acesso rápido por id

        $allCategories   = DB::table('admission_categories')->get();
        $acCategory      = $allCategories->firstWhere('name', 'AC');
        $otherCategories = $allCategories->where('name', '!=', 'AC')->values();
        $bonuses         = DB::table('bonus_options')->get();

        for ($i = 1; $i <= 20; $i++) {

            /* ---------- Usuário ---------- */
            $name  = $faker->name;
            $email = $faker->unique()->safeEmail;
            $cpf   = $i <= 15
                     ? substr((string) ($enemBase + $i - 1), -11)
                     : preg_replace('/\D/', '', $faker->cpf(false));

            DB::table('users')->updateOrInsert(
                ['id' => $i],
                [
                    'name'              => $name,
                    'email'             => $email,
                    'cpf'               => $cpf,
                    'password'          => Hash::make('rootroot'),
                    'email_verified_at' => $now,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]
            );

            /* ---------- Curso (apenas 1 ou 8) ---------- */
            $course   = $courses->random();  // agora sorteia só entre 1 e 8
            $position = [
                'id'            => $course->id,
                'name'          => $course->name,
                'academic_unit' => json_decode($course->academic_unit, true),
                'modality'      => $course->modality,
                'created_at'    => Carbon::parse($course->created_at)->toISOString(),
                'updated_at'    => Carbon::parse($course->updated_at)->toISOString(),
            ];

            /* ---------- Categorias ---------- */
            $extraCat   = $otherCategories->random(rand(0, 1));
            $categories = collect([$acCategory])
                ->merge($extraCat)
                ->map(fn ($c) => [
                    'id'         => $c->id,
                    'name'       => $c->name,
                    'description'=> $c->description,
                    'created_at' => Carbon::parse($c->created_at)->toISOString(),
                    'updated_at' => Carbon::parse($c->updated_at)->toISOString(),
                ])
                ->values()
                ->all();

            /* ---------- Bônus ---------- */
            $bonus     = $bonuses->random();
            $bonusArr  = [
                'id'          => $bonus->id,
                'name'        => $bonus->name,
                'description' => $bonus->description,
                'value'       => $bonus->value,
                'created_at'  => Carbon::parse($bonus->created_at)->toISOString(),
                'updated_at'  => Carbon::parse($bonus->updated_at)->toISOString(),
            ];

            /* ---------- Application ---------- */
            DB::table('applications')->updateOrInsert(
                ['user_id' => $i, 'process_selection_id' => $processId],
                [
                    'form_data' => json_encode([
                        'edital'               => "Edital nº 04/2024 - PROCESSO SELETIVO UNILAB – {$course->name}",
                        'position'             => $position,
                        'name'                 => $name,
                        'email'                => $email,
                        'cpf'                  => $cpf,
                        'birthdate'            => $faker->date('Y-m-d', '2006-12-31'),
                        'sex'                  => $faker->randomElement(['Masculino','Feminino']),
                        'phone1'               => preg_replace('/\D/', '', $faker->cellphoneNumber()),
                        'address'              => $faker->streetAddress,
                        'uf'                   => $faker->stateAbbr,
                        'city'                 => $faker->city,
                        'enem'                 => (string) ($enemBase + $i - 1),
                        'enem_year'            => 2023,
                        'admission_categories' => $categories,
                        'bonus'                => $bonusArr,
                        'updated_at'           => $now->toDateTimeString(),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}