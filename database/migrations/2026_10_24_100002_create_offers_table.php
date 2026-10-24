<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('online_url', 500)->nullable();
            $table->string('contact_name', 150);
            $table->string('target_group', 200)->nullable();
            $table->string('area', 120)->nullable();
            $table->string('allocation_mode', 16)->default('instant');
            $table->string('status', 16)->default('draft');
            $table->dateTime('bookable_from');
            $table->dateTime('bookable_until');
            $table->dateTime('wish_deadline')->nullable();
            $table->dateTime('wishes_released_at')->nullable();
            $table->boolean('one_booking_per_person')->default(true);
            $table->boolean('reminders_enabled')->default(true);
            $table->string('cancellation_note', 500)->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('owner_user_id');
            $table->index(['status', 'bookable_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
