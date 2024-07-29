<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'name' => 'Francisca Usuario Teste',
            'email' => 'root@dsgoextractor.com',
            'cpf' => '25982023680',
            'password' => Hash::make('root'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('users')->insert([
            'name' => 'Francisco4 Usuario Teste',
            'email' => 'root4@dsgoextractor.com',
            'cpf' => '25787968409',
            'password' => Hash::make('root'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        DB::table('users')->insert([
            'name' => 'Francisco4 Usuario Teste',
            'email' => 'root6@dsgoextractor.com',
            'cpf' => '03519017369',
            'password' => Hash::make('root'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);


        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
