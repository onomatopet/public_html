<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

return new class extends Migration {
    public function up(): void {
        Log::info('Renaming level_current_tests to level_currents...');
        Schema::rename('level_current_tests', 'level_currents');
    }
    public function down(): void {
        Log::info('Renaming level_currents back to level_current_tests...');
        Schema::rename('level_currents', 'level_current_tests');
    }
};
