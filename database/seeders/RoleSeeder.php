<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'display_name' => 'مدیرکل سامانه'],
            ['name' => 'cultural_manager', 'display_name' => 'مدیر فرهنگی'],
            ['name' => 'student', 'display_name' => 'دانشجو'],
            ['name' => 'observer', 'display_name' => 'ناظر'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }
    }
}