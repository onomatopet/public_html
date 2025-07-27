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
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();

            // Entité à supprimer
            $table->string('entity_type', 50)->comment('Type d\'entité (distributeur, achat, product, etc.)');
            $table->unsignedBigInteger('entity_id')->comment('ID de l\'entité à supprimer');

            // Workflow d'approbation
            $table->unsignedBigInteger('requested_by_id')->comment('Utilisateur demandeur');
            $table->unsignedBigInteger('approved_by_id')->nullable()->comment('Utilisateur approbateur/rejeteur');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'cancelled'])
                  ->default('pending')
                  ->comment('Statut de la demande');

            // Détails de la demande
            $table->text('reason')->comment('Raison de la suppression');
            $table->json('validation_data')->nullable()->comment('Données de validation (blockers, warnings, etc.)');
            $table->json('backup_info')->nullable()->comment('Informations de backup');
            $table->text('rejection_reason')->nullable()->comment('Raison du rejet si applicable');

            // Timestamps d'workflow
            $table->timestamp('approved_at')->nullable()->comment('Date d\'approbation/rejet');
            $table->timestamp('completed_at')->nullable()->comment('Date d\'exécution');

            $table->timestamps();

            // Index pour les performances
            $table->index(['entity_type', 'entity_id'], 'idx_deletion_entity');
            $table->index(['status', 'created_at'], 'idx_deletion_status_date');
            $table->index('requested_by_id', 'idx_deletion_requester');
            $table->index('approved_by_id', 'idx_deletion_approver');

            // Clés étrangères
            $table->foreign('requested_by_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};
