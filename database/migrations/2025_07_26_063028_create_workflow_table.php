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
        Schema::create('workflow_logs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7);
            $table->string('step', 50);
            $table->string('action', 50);
            $table->enum('status', ['started', 'completed', 'failed'])->default('started');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_seconds')->nullable();
            $table->json('details')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            // Index
            $table->index(['period', 'created_at']);
            $table->index('status');
            $table->index('step');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_logs');
    }
};
