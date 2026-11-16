<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_lottery_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('run_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('winner_count')->default(0);
            $table->unsignedInteger('waitlist_count')->default(0);
            $table->jsonb('result_snapshot');
            $table->timestampTz('executed_at');
            $table->timestamps();
            $table->index(['event_id', 'executed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lottery_runs');
    }
};
