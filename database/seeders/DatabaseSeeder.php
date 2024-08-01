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
        // DB::table('users')->insert([
        //     'name' => 'Jefferson Uchoa Ponte',
        //     'email' => 'jefponte@gmail.com',
        //     'cpf' => '03519017369',
        //     'password' => Hash::make('cafe@123A'),
        //     'created_at' => Carbon::now(),
        //     'updated_at' => Carbon::now(),
        // ]);
        DB::table('admins')->insert([
            'name' => 'Jefferson Uchoa Ponte',
            'email' => 'jefponte@unilab.edu.br',
            'password' => Hash::make('f@ccionados@123A'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        DB::table('admins')->insert([
            'name' => 'Thiago Gomes',
            'email' => 'thiago@unilab.edu.br',
            'password' => Hash::make('f@ccionados@123A'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

    }
}
