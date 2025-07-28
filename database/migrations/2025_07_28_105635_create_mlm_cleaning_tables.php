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
        // Table des sessions de nettoyage
        Schema::create('mlm_cleaning_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_code')->unique();
            $table->enum('status', ['pending', 'analyzing', 'preview', 'processing', 'completed', 'failed', 'rolled_back'])
                  ->default('pending');
            $table->enum('type', ['full', 'period', 'distributor', 'hierarchy', 'cumuls', 'grades'])
                  ->default('full');
            $table->string('period_start')->nullable();
            $table->string('period_end')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('records_analyzed')->default(0);
            $table->unsignedInteger('records_with_anomalies')->default(0);
            $table->unsignedInteger('records_corrected')->default(0);
            $table->unsignedInteger('hierarchy_issues')->default(0);
            $table->unsignedInteger('cumul_issues')->default(0);
            $table->unsignedInteger('grade_issues')->default(0);
            $table->decimal('execution_time', 10, 2)->nullable();
            $table->text('configuration')->nullable(); // JSON des options
            $table->timestamp('started_at')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('preview_generated_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('session_code');
            $table->foreign('created_by')->references('id')->on('users');
        });

        // Table des anomalies détectées
        Schema::create('mlm_cleaning_anomalies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('distributeur_id');
            $table->string('period');
            $table->enum('type', [
                'hierarchy_loop',
                'orphan_parent',
                'cumul_individual_negative',
                'cumul_collective_less_than_individual',
                'cumul_decrease',
                'grade_regression',
                'grade_skip',
                'grade_conditions_not_met',
                'missing_period',
                'duplicate_period',
                'other'
            ]);
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('field_name')->nullable();
            $table->text('current_value')->nullable();
            $table->text('expected_value')->nullable();
            $table->text('description');
            $table->json('metadata')->nullable(); // Données additionnelles
            $table->boolean('can_auto_fix')->default(false);
            $table->boolean('is_fixed')->default(false);
            $table->timestamp('detected_at');
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'type']);
            $table->index(['distributeur_id', 'period']);
            $table->index('severity');
            $table->foreign('session_id')->references('id')->on('mlm_cleaning_sessions')->onDelete('cascade');
            $table->foreign('distributeur_id')->references('id')->on('distributeurs');
        });

        // Table des logs de modifications
        Schema::create('mlm_cleaning_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('distributeur_id');
            $table->string('period');
            $table->string('table_name');
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->enum('action', ['update', 'insert', 'delete', 'skip']);
            $table->string('reason');
            $table->json('context')->nullable(); // Contexte additionnel
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->index(['session_id', 'distributeur_id']);
            $table->index(['distributeur_id', 'period']);
            $table->index('action');
            $table->foreign('session_id')->references('id')->on('mlm_cleaning_sessions')->onDelete('cascade');
            $table->foreign('distributeur_id')->references('id')->on('distributeurs');
        });

        // Table des snapshots (sauvegardes)
        Schema::create('mlm_cleaning_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->enum('type', ['full', 'partial']);
            $table->enum('status', ['creating', 'completed', 'failed', 'expired']);
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable(); // en bytes
            $table->unsignedInteger('records_count')->default(0);
            $table->json('tables_included'); // Liste des tables sauvegardées
            $table->json('metadata')->nullable();
            $table->text('compression_info')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'status']);
            $table->foreign('session_id')->references('id')->on('mlm_cleaning_sessions')->onDelete('cascade');
        });

        // Table de suivi de progression (pour l'interface temps réel)
        Schema::create('mlm_cleaning_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->string('step');
            $table->string('sub_step')->nullable();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->string('current_item')->nullable();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed']);
            $table->timestamp('started_at');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->index('session_id');
            $table->foreign('session_id')->references('id')->on('mlm_cleaning_sessions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mlm_cleaning_progress');
        Schema::dropIfExists('mlm_cleaning_snapshots');
        Schema::dropIfExists('mlm_cleaning_logs');
        Schema::dropIfExists('mlm_cleaning_anomalies');
        Schema::dropIfExists('mlm_cleaning_sessions');
    }
};
