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
            $table->float('accuracy')->nullable()->after('finished_at');
            $table->unsignedTinyInteger('stars')->nullable()->after('accuracy');
            $table->float('reading_speed')->nullable()->after('stars');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dropColumn(['accuracy', 'stars', 'reading_speed']);
        });
    }
};
