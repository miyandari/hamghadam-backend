<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_criteria', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->string('name');
    $table->text('description')->nullable();
    $table->unsignedSmallInteger('weight')->default(1);
    $table->unsignedSmallInteger('max_score')->default(100);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_criteria');
    }
};
