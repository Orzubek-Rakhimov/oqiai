<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A migration the database has not run yet, standing in for the one a new
 * version brings (tests/Feature/BackupBeforeDeployTest.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deploy_probe', function (Blueprint $table) {
            $table->id();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deploy_probe');
    }
};
