<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_policies', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
    $table->unsignedInteger('min_age')->nullable();
    $table->unsignedInteger('max_age')->nullable();
    $table->unsignedInteger('min_score')->default(0);
    $table->unsignedInteger('max_group_size')->default(1);
    $table->boolean('requires_approval')->default(false);
    $table->boolean('lottery_enabled')->default(false);
    $table->jsonb('rules')->nullable();
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('event_policies');
    }
};
