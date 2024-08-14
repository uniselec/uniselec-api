<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('pt_BR');
        $numUsers = 20;
        $enemBase = 231000000000;

        $users = [];
        $applications = [];

        for ($i = 1; $i <= $numUsers; $i++) {
            $name = $faker->name;
            $email = $faker->unique()->safeEmail;
            $cpf = $i <= 15 ? substr((string)($enemBase + $i - 1), -11) : $faker->cpf(false);

            $users[] = [
                'name' => $name,
                'email' => $email,
                'cpf' => $cpf,
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $applications[] = [
                'user_id' => $i,
                'data' => json_encode([
                    "edital" => "Edital nº 04/2024 - PROCESSO SELETIVO UNILAB – PERÍODO LETIVO 2024.1 Curso Medicina",
                    "position" => "Medicina",
                    "location_position" => $faker->city . "-" . $faker->stateAbbr,
                    "name" => $name,
                    "email" => $email,
                    "cpf" => $cpf,
                    "birtdate" => $faker->date('Y-m-d', '2006-12-31'),
                    "sex" => $faker->randomElement(['Masculino', 'Feminino']),
                    "phone1" => $faker->phoneNumber,
                    "address" => $faker->streetAddress,
                    "uf" => $faker->stateAbbr,
                    "city" => $faker->city,
                    "enem" => strval($enemBase + $i - 1),
                    "vaga" => [
                        $faker->randomElement([
                            "LB - PPI: Candidatos autodeclarados pretos, pardos ou indígenas, com renda familiar bruta per capita igual ou inferior a 1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LB - Q: Candidatos autodeclarados quilombolas, com renda familiar bruta per capita igual ou inferior a  1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LB - PCD: Candidatos com deficiência, que tenham renda familiar bruta per capita igual ou inferior a 1 salário mínimo e que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LB - EP: Candidatos com renda familiar bruta per capita igual ou inferior a 1 salário mínimo que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LI - PPI: Candidatos autodeclarados pretos, pardos ou indígenas, independentemente da renda, que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LI - Q: Candidatos autodeclarados quilombolas, independentemente da renda, tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LI - PCD: Candidatos com deficiência, independentemente da renda, que tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                            "LI - EP: Candidatos que, independentemente da renda, tenham cursado integralmente o ensino médio em escolas públicas (Lei nº 12.711/2012).",
                        ])
                    ],
                    "bonus" => [
                        "10%: Estudantes que tenham cursado integralmente o ensino médio em escolas públicas."
                    ],
                    "updated_at" => now()->format('Y-m-d H:i:s')
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('users')->insert($users);
        DB::table('applications')->insert($applications);
    }
}
