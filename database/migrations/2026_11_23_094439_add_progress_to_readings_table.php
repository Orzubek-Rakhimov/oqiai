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
            $table->unsignedInteger('next_sentence_position')->default(0)->after('story_id');
            $table->unsignedInteger('reading_time_ms')->default(0)->after('next_sentence_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['next_sentence_position', 'reading_time_ms']);
        });
    }
};
