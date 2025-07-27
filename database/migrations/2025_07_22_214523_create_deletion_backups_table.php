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
        Schema::create('deletion_backups', function (Blueprint $table) {
            $table->id();
            $table->string('backup_id')->unique()->comment('ID unique du backup');
            $table->string('entity_type', 50)->comment('Type d\'entité sauvegardée');
            $table->unsignedBigInteger('entity_id')->comment('ID de l\'entité sauvegardée');
            $table->json('backup_data')->comment('Données complètes du backup');
            $table->string('file_path')->nullable()->comment('Chemin du fichier de backup');
            $table->unsignedBigInteger('created_by')->comment('Utilisateur qui a créé le backup');
            $table->timestamp('restored_at')->nullable()->comment('Date de restauration');
            $table->unsignedBigInteger('restored_by')->nullable()->comment('Utilisateur qui a restauré');
            $table->timestamps();

            // Index
            $table->index(['entity_type', 'entity_id']);
            $table->index('backup_id');
            $table->index('created_at');
            $table->index('restored_at');

            // Clés étrangères
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('restored_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_backups');
    }
};
