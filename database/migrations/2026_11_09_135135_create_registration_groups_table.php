<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('leader_user_id')->constrained('users')->restrictOnDelete();
    $table->string('name');
    $table->string('invite_code', 32)->unique();
    $table->unsignedSmallInteger('max_members')->default(1);
    $table->string('status')->default('pending');
    $table->timestamps();
    $table->unique(['event_id', 'leader_user_id']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_groups');
    }
};
