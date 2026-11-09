<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the existing account and credentials; only ensure its administrator flag.
        DB::table('users')
            ->where('email', 'mahmia.developer@gmail.com')
            ->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        // Intentionally do not revoke the primary administrator's access on rollback.
    }
};
