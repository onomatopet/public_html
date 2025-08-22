<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Supprimer les anciennes valeurs
        DB::table('bonus_thresholds')->truncate();

        // Insérer les bonnes valeurs selon votre logique métier
        DB::table('bonus_thresholds')->insert([
            [
                'grade' => 1,
                'minimum_pv' => 0,
                'description' => 'Distributeur 1 étoile - Non éligible',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 2,
                'minimum_pv' => 0,
                'description' => 'Distributeur 2 étoiles - Toujours éligible',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 3,
                'minimum_pv' => 10,
                'description' => 'Distributeur 3 étoiles - Minimum 10 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 4,
                'minimum_pv' => 15,
                'description' => 'Distributeur 4 étoiles - Minimum 15 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 5,
                'minimum_pv' => 30,
                'description' => 'Distributeur 5 étoiles - Minimum 30 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 6,
                'minimum_pv' => 50,
                'description' => 'Distributeur 6 étoiles - Minimum 50 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 7,
                'minimum_pv' => 100,
                'description' => 'Distributeur 7 étoiles - Minimum 100 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 8,
                'minimum_pv' => 150,
                'description' => 'Distributeur 8 étoiles - Minimum 150 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 9,
                'minimum_pv' => 180,
                'description' => 'Distributeur 9 étoiles - Minimum 180 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'grade' => 10,
                'minimum_pv' => 180,
                'description' => 'Distributeur 10 étoiles - Minimum 180 PV',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // On ne fait rien car on ne veut pas revenir aux mauvaises valeurs
    }
};
