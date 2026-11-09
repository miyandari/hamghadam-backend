<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->foreignId('university_id')->constrained()->restrictOnDelete();
    $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
    $table->string('title');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('category')->nullable();
    $table->string('venue')->nullable();
    $table->dateTimeTz('starts_at')->nullable();
    $table->dateTimeTz('ends_at')->nullable();
    $table->dateTimeTz('registration_starts_at')->nullable();
    $table->dateTimeTz('registration_ends_at')->nullable();
    $table->unsignedInteger('capacity')->default(0);
    $table->string('status')->default('draft');
    $table->boolean('is_group_registration_allowed')->default(false);
    $table->timestamps();
    $table->softDeletes();
    $table->index(['university_id', 'status']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
