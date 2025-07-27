<?php

// database/migrations/2025_01_01_000006_add_timestamps_to_user_role_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_role', function (Blueprint $table) {
            if (!Schema::hasColumn('user_role', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down()
    {
        Schema::table('user_role', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
