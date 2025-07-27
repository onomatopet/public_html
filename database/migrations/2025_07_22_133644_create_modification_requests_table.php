<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modification_requests', function (Blueprint $table) {
            $table->id();
            
            // Informations sur l'entité modifiée
            $table->string('entity_type', 50); // distributeur, achat, bonus, etc.
            $table->unsignedBigInteger('entity_id');
            $table->string('modification_type', 50); // change_parent, manual_grade, adjust_cumul, etc.
            
            // Utilisateurs impliqués
            $table->unsignedBigInteger('requested_by_id');
            $table->unsignedBigInteger('approved_by_id')->nullable();
            
            // Statut du workflow
            $table->enum('status', ['pending', 'approved', 'rejected', 'executed', 'cancelled'])
                  ->default('pending');
            
            // Données de la modification
            $table->json('original_values'); // Valeurs avant modification
            $table->json('new_values'); // Nouvelles valeurs proposées
            $table->json('changes_summary'); // Résumé des changements
            
            // Validation et impact
            $table->json('validation_data')->nullable(); // Résultats de validation
            $table->json('impact_analysis')->nullable(); // Analyse d'impact
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            
            // Justifications
            $table->text('reason'); // Raison de la modification
            $table->text('notes')->nullable(); // Notes additionnelles
            $table->text('rejection_reason')->nullable();
            
            // Timestamps
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Expiration de la demande
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('requested_by_id')->references('id')->on('users');
            $table->foreign('approved_by_id')->references('id')->on('users');
            
            // Indexes
            $table->index(['entity_type', 'entity_id']);
            $table->index(['status', 'created_at']);
            $table->index('risk_level');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modification_requests');
    }
};