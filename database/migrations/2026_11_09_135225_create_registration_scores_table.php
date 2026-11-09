<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_scores', function (Blueprint $table) {
    $table->id();
    $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
    $table->foreignId('scoring_criterion_id')->constrained('scoring_criteria')->cascadeOnDelete();
    $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
    $table->unsignedSmallInteger('score');
    $table->text('note')->nullable();
    $table->timestamps();
    $table->unique(['registration_id', 'scoring_criterion_id']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_scores');
    }
};
