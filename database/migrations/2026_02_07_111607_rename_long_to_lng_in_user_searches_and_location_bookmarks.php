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
        Schema::table('user_searches', function (Blueprint $table) {
            $table->renameColumn('long', 'lng');
        });

        Schema::table('location_bookmarks', function (Blueprint $table) {
            $table->renameColumn('long', 'lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_searches', function (Blueprint $table) {
            $table->renameColumn('lng', 'long');
        });

        Schema::table('location_bookmarks', function (Blueprint $table) {
            $table->renameColumn('lng', 'long');
        });
    }
};
