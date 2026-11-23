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
        Schema::create('words', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sentence_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text');
            $table->string('normalized')->index();
            $table->json('syllables');
            $table->timestamps();

            $table->unique(['sentence_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('words');
    }
};
