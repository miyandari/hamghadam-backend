<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_lottery_factor_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('factor_name', 120);
            $table->decimal('coefficient', 8, 3);
            $table->string('status', 20)->default('verified');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'factor_name'], 'event_user_factor_unique');
            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_lottery_factor_awards');
    }
};
