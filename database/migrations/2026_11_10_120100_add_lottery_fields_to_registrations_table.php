<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->string('selection_method', 24)->nullable()->after('registration_type');
            $table->decimal('lottery_weight', 14, 4)->default(1)->after('total_score');
            $table->unsignedInteger('lottery_rank')->nullable()->after('lottery_number');
        });
    }

    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['selection_method', 'lottery_weight', 'lottery_rank']);
        });
    }
};
