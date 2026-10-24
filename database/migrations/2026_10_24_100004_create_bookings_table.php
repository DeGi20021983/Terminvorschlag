<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->foreignId('slot_id')->constrained('slots')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('replaces_booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->string('status', 16)->default('confirmed');
            $table->string('source', 16);
            $table->dateTime('booked_at');
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('reminder_sent_at')->nullable();

            // Ein Termin hat höchstens eine bestätigte Buchung. Der eindeutige Index
            // verhindert Doppelbuchungen auch dann, wenn zwei Anfragen gleichzeitig ankommen.
            $table->unsignedBigInteger('active_slot_id')->nullable()
                ->storedAs("case when status = 'confirmed' then slot_id end");
            $table->unique('active_slot_id', 'bookings_active_slot_unique');

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['offer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
