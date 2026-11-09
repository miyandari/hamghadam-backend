<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
    $table->unsignedInteger('position');
    $table->string('status')->default('waiting');
    $table->dateTimeTz('offered_at')->nullable();
    $table->dateTimeTz('expires_at')->nullable();
    $table->timestamps();
    $table->unique(['event_id', 'user_id']);
    $table->index(['event_id', 'status', 'position']);
});
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
