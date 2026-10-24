<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Merkt sich, dass der Organisator nach Ablauf der Wunschfrist erinnert wurde.
            $table->dateTime('wish_due_notified_at')->nullable()->after('wishes_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('wish_due_notified_at');
        });
    }
};
