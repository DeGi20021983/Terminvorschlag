<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Benutzer werden ohne Passwort angelegt: Die Anmeldung erfolgt über IIS
 * (Windows-Authentifizierung). Die Tabelle hält nur die Daten, die für die
 * Anzeige und die Rollenprüfung nötig sind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username', 64)->unique();
            $table->string('display_name')->nullable();
            $table->string('department')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_organizer')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
