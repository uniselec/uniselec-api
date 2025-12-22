<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admins')->updateOrInsert(
            ['email' => 'admin@selecoes-staging.unilab.edu.br'],
            [
                'name'       => 'Jefferson Uchoa Ponte',
                'role'       => 'promoter',
                'password'   => Hash::make('rootroot'),
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]
        );
    }
}
