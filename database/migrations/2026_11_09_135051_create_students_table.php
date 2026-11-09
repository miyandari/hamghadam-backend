<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->foreignId('university_id')->constrained()->restrictOnDelete();
    $table->string('student_number', 50);
    $table->string('field_of_study')->nullable();
    $table->string('degree')->nullable();
    $table->unsignedSmallInteger('entry_year')->nullable();
    $table->string('status')->default('active');
    $table->timestamps();
    $table->unique(['university_id', 'student_number']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
