<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_cultural_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('university_id')->constrained()->restrictOnDelete();
            $table->string('record_type', 60);
            $table->string('title', 255);
            $table->string('organization_name')->nullable();
            $table->text('description')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('status', 24)->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['university_id', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cultural_records');
    }
};
