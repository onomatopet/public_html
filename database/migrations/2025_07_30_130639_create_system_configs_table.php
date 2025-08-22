<?php

// 2025_07_30_create_system_configs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value');
            $table->string('type', 20)->default('string'); // string, integer, boolean, array, json
            $table->text('description')->nullable();
            $table->string('group', 50)->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            // Index
            $table->index('group');
            $table->index('is_public');
        });

        // Insérer les configurations par défaut
        DB::table('system_configs')->insert([
            [
                'key' => 'app_name',
                'value' => config('app.name', 'MLM System'),
                'type' => 'string',
                'description' => 'Nom de l\'application',
                'group' => 'general',
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'mlm_calculation_period',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Période de calcul MLM en jours',
                'group' => 'mlm',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cache_ttl',
                'value' => '3600',
                'type' => 'integer',
                'description' => 'Durée de vie du cache en secondes',
                'group' => 'performance',
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
