<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Anonyme Statistik: bleibt nach dem Löschlauf erhalten, auch wenn die Buchung gelöscht wurde.
            $table->boolean('was_booked')->default(false);
            $table->timestamps();

            $table->index(['offer_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
