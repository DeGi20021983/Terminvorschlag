<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wish_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('slot_id')->constrained('slots')->restrictOnDelete();
            $table->unsignedTinyInteger('priority');
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->unique(['offer_id', 'user_id', 'priority']);
            $table->unique(['offer_id', 'user_id', 'slot_id']);
        });

        Schema::create('wish_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->string('status', 16)->default('proposed');
            $table->foreignId('calculated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('calculated_at');
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();

            $table->index(['offer_id', 'status']);
        });

        Schema::create('wish_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wish_run_id')->constrained('wish_runs')->cascadeOnDelete();
            $table->foreignId('offer_id')->constrained('offers')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('slots')->restrictOnDelete();
            $table->string('outcome', 16);
            $table->boolean('by_lot')->default(false);
            $table->string('alternative_response', 16)->nullable();
            $table->timestamps();

            $table->unique(['wish_run_id', 'user_id']);
        });

        // Los-Protokoll: jede Auslosung mit Kandidaten und Gewinner.
        Schema::create('wish_draws', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wish_run_id')->constrained('wish_runs')->cascadeOnDelete();
            $table->foreignId('slot_id')->constrained('slots')->restrictOnDelete();
            $table->unsignedTinyInteger('round');
            $table->json('candidate_user_ids');
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wish_draws');
        Schema::dropIfExists('wish_assignments');
        Schema::dropIfExists('wish_runs');
        Schema::dropIfExists('wish_entries');
    }
};
