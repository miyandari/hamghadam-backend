<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereRaw('LOWER(email) = ?', ['mahmia.developer@gmail.com'])
            ->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        // Keep the primary administrator's access intact on rollback.
    }
};
