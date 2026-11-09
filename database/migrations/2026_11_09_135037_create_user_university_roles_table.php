<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_university_roles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('university_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->restrictOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->unique(['user_id', 'university_id', 'role_id'], 'user_university_role_unique');
});
    }

    public function down(): void
    {
        Schema::dropIfExists('user_university_roles');
    }
};
