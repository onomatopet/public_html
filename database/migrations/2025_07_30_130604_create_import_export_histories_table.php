<?php

// 2025_07_30_create_import_export_histories_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_export_histories', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['import', 'export']);
            $table->string('entity_type', 50);
            $table->string('filename');
            $table->string('file_path')->nullable();
            $table->integer('file_size')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('user_id');
            $table->json('options')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index
            $table->index(['type', 'entity_type']);
            $table->index('status');
            $table->index('user_id');
            $table->index('created_at');

            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_export_histories');
    }
};
