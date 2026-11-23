<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Only when the microphone check last passed is
     * kept; the test recording itself is never stored (ADR-0002).
     */
    public function up(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->timestamp('microphone_checked_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('children', function (Blueprint $table) {
            $table->dropColumn('microphone_checked_at');
        });
    }
};
