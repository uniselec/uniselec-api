<?php
// database/seeders/UsersTableSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $faker        = Faker::create('pt_BR');
        $numUsers     = 20;
        $enemBase     = 231000000000;
        $selectionId  = 1;

        $users        = [];
        $applications = [];

        for ($i = 1; $i <= $numUsers; $i++) {
            $course           = DB::table('courses')->find(1);
            $acCategory = DB::table('admission_categories')
                ->where('name', 'AC')
                ->first();
            $extraCategories = DB::table('admission_categories')
                ->where('name', '!=', 'AC')
                ->inRandomOrder()
                ->take(rand(0, 1))
                ->get();
            $categorySamples = collect([$acCategory])
                ->merge($extraCategories)
                ->values();
            $name             = $faker->name;
            $email            = $faker->unique()->safeEmail;
            $cpf              = $i <= 15 ? substr((string)($enemBase + $i - 1), -11) : $faker->cpf(false);

            $users[] = [
                'id'              => $i,
                'name'            => $name,
                'email'           => $email,
                'cpf'             => $cpf,
                'password'        => Hash::make('password123'),
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ];

            $applications[] = [
                'user_id'             => $i,
                'process_selection_id' => $selectionId,
                'form_data' => json_encode([
                    'edital'                => 'Edital nº 04/2024 - PROCESSO SELETIVO UNILAB – PERÍODO LETIVO 2024.1 Curso Medicina',
                    'position'              => $course,
                    'name'                  => $name,
                    'email'                 => $email,
                    'cpf'                   => $cpf,
                    'birthdate'             => $faker->date('Y-m-d', '2006-12-31'),
                    'sex'                   => $faker->randomElement(['Masculino', 'Feminino']),
                    'phone1'                => $faker->phoneNumber,
                    'address'               => $faker->streetAddress,
                    'uf'                    => $faker->stateAbbr,
                    'city'                  => $faker->city,
                    'enem'                  => strval($enemBase + $i - 1),
                    'enem_year'             =>  2023,
                    'admission_categories'  => $categorySamples,
                    'bonus'                => DB::table('bonus_options')->inRandomOrder()->first(),
                    'updated_at'            => now()->format('Y-m-d H:i:s'),
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // upsert users
        DB::table('users')->upsert($users, ['id'], ['name', 'email', 'cpf', 'updated_at']);
        DB::table('applications')->upsert($applications, ['user_id', 'process_selection_id'], ['form_data', 'updated_at']);
    }
}
