<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_policies', function (Blueprint $table) {
            $table->unsignedInteger('manager_approval_quota')->default(0)->after('max_group_size');
        });
    }

    public function down(): void
    {
        Schema::table('event_policies', function (Blueprint $table) {
            $table->dropColumn('manager_approval_quota');
        });
    }
};
