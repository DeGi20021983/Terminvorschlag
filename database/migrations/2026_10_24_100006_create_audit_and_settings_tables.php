<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Protokoll der Änderungen durch Organisatoren und Administratoren.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_username', 64);
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->json('details')->nullable();
            $table->dateTime('created_at');

            $table->index('created_at');
            $table->index(['action', 'created_at']);
        });

        // Status der Löschläufe für die Administration.
        Schema::create('purge_runs', function (Blueprint $table) {
            $table->id();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('status', 16);
            $table->unsignedInteger('anonymized_slots')->default(0);
            $table->unsignedInteger('deleted_bookings')->default(0);
            $table->unsignedInteger('deleted_wish_records')->default(0);
            $table->unsignedInteger('deleted_audit_entries')->default(0);
            $table->unsignedInteger('deleted_users')->default(0);
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // Administrative Einstellungen (Standardfrist, Erinnerung, Texte ...).
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('purge_runs');
        Schema::dropIfExists('audit_logs');
    }
};
