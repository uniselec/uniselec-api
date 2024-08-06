<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;


class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('pt_BR');

        for ($i = 0; $i < 150; $i++) {
            User::create([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'cpf' => $faker->cpf, // Gerando CPF usando Faker
                'password' => bcrypt('password'), // Lembre-se de mudar isso em produção
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
