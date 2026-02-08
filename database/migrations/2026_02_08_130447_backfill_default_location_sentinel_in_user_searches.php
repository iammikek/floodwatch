<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('user_searches')
            ->where('location', 'Somerset Levels')
            ->update(['location' => 'default']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('user_searches')
            ->where('location', 'default')
            ->update(['location' => 'Somerset Levels']);
    }
};
