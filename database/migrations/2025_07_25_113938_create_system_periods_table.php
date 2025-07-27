<?php
// database/migrations/2025_01_26_create_system_periods_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('system_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique(); // Format: YYYY-MM
            $table->enum('status', ['open', 'validation', 'closed'])->default('open');
            $table->date('opened_at');
            $table->date('validation_started_at')->nullable();
            $table->date('closed_at')->nullable();
            $table->unsignedBigInteger('closed_by_user_id')->nullable();
            $table->json('closure_summary')->nullable(); // Stats de clôture
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->foreign('closed_by_user_id')->references('id')->on('users');
            $table->index(['status', 'is_current']);
        });

        // Table pour les seuils minimum de PV par grade
        Schema::create('bonus_thresholds', function (Blueprint $table) {
            $table->id();
            $table->integer('grade')->unique();
            $table->integer('minimum_pv')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insérer les seuils par défaut
        DB::table('bonus_thresholds')->insert([
            ['grade' => 1, 'minimum_pv' => 100, 'description' => 'Distributeur 1 étoile'],
            ['grade' => 2, 'minimum_pv' => 200, 'description' => 'Distributeur 2 étoiles'],
            ['grade' => 3, 'minimum_pv' => 300, 'description' => 'Distributeur 3 étoiles'],
            ['grade' => 4, 'minimum_pv' => 500, 'description' => 'Distributeur 4 étoiles'],
            ['grade' => 5, 'minimum_pv' => 750, 'description' => 'Distributeur 5 étoiles'],
            ['grade' => 6, 'minimum_pv' => 1000, 'description' => 'Distributeur 6 étoiles'],
            ['grade' => 7, 'minimum_pv' => 1500, 'description' => 'Distributeur 7 étoiles'],
            ['grade' => 8, 'minimum_pv' => 2000, 'description' => 'Distributeur 8 étoiles'],
            ['grade' => 9, 'minimum_pv' => 2500, 'description' => 'Distributeur 9 étoiles'],
            ['grade' => 10, 'minimum_pv' => 3000, 'description' => 'Distributeur 10 étoiles'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('bonus_thresholds');
        Schema::dropIfExists('system_periods');
    }
};
