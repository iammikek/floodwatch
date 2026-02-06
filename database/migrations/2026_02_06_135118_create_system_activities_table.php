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
        Schema::create('system_activities', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // flood_warning, road_closure, road_reopened, river_level_elevated
            $table->string('description');
            $table->string('severity')->default('moderate'); // low, moderate, high, severe
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable(); // road, flood_area_id, etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_activities');
    }
};
