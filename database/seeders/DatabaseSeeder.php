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

        $this->call([
            AcademicUnitSeeder::class,
            AdmissionCategorySeeder::class,
            BonusOptionSeeder::class,
            CourseSeeder::class,
            // ProcessSelectionSeeder::class,   // novo
            AdminSeeder::class,
            // UsersTableSeeder::class,
            KnowledgeAreaSeeder::class,
        ]);
    }
}
