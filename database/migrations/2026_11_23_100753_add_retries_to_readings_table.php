<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->json('pending_retry_word_ids')->nullable()->after('reading_time_ms');
            $table->foreignId('syllable_help_word_id')->nullable()->after('reading_time_ms')->constrained('words')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('syllable_help_word_id');
            $table->dropColumn('pending_retry_word_ids');
        });
    }
};
