<?php
// database/migrations/2025_01_26_create_performance_metrics_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->index();
            $table->json('metrics');
            $table->timestamp('created_at')->index();

            // Index composé pour les requêtes
            $table->index(['period', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('performance_metrics');
    }
};
