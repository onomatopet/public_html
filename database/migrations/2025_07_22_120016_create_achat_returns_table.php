<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajouter des colonnes à la table achats pour gérer les statuts
        Schema::table('achats', function (Blueprint $table) {
            $table->enum('status', ['active', 'cancelled', 'returned', 'partial_return'])
                  ->default('active')
                  ->after('online')
                  ->comment('Statut de l\'achat');

            $table->decimal('montant_rembourse', 12, 2)
                  ->default(0)
                  ->after('montant_total_ligne')
                  ->comment('Montant remboursé en cas de retour');

            $table->integer('qt_retournee')
                  ->default(0)
                  ->after('qt')
                  ->comment('Quantité retournée');

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('returned_at')->nullable();

            $table->index(['status', 'period']);
            $table->index('cancelled_at');
            $table->index('returned_at');
        });

        // Table pour l'historique des retours/annulations
        Schema::create('achat_return_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('achat_id');
            $table->unsignedBigInteger('requested_by_id');
            $table->unsignedBigInteger('approved_by_id')->nullable();

            $table->enum('type', ['cancellation', 'return', 'partial_return']);
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed']);

            $table->string('reason', 500);
            $table->text('notes')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->integer('quantity_to_return')->nullable();
            $table->decimal('amount_to_refund', 12, 2)->nullable();

            $table->json('validation_data')->nullable();
            $table->json('impact_analysis')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('achat_id')->references('id')->on('achats');
            $table->foreign('requested_by_id')->references('id')->on('users');
            $table->foreign('approved_by_id')->references('id')->on('users');

            $table->index(['status', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achat_return_requests');

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn([
                'status',
                'montant_rembourse',
                'qt_retournee',
                'cancelled_at',
                'returned_at'
            ]);
        });
    }
};