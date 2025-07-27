<?php
// database/migrations/2025_01_26_create_cache_custom_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Table de cache personnalisée optimisée
        Schema::create('cache_custom', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value');
            $table->timestamp('expiration')->index();
            $table->timestamp('created_at')->nullable();

            // Index pour le nettoyage
            $table->index(['expiration', 'key']);
        });

        // Si vous utilisez CACHE_DRIVER=database, créer la table cache standard
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down()
    {
        Schema::dropIfExists('cache_custom');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
