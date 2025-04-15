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

        DB::table('admins')->insert([
            'name' => 'Jefferson Uchoa Ponte',
            'email' => 'jefponte@gmail.com',
            'password' => Hash::make('rootroot'),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

    }
}
