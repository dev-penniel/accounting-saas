<?php

namespace Database\Seeders;

use App\Services\CategoryService;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(CategoryService $categoryService): void
    {
        $categoryService->createDefaultsForAllUsers();
    }
}
