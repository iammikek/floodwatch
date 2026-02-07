<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::create('location_bookmarks', function (Blueprint $table) use ($driver) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('location');
            $table->float('lat');
            $table->float('lng');
            $table->string('region')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('user_id');

            if (in_array($driver, ['mysql', 'mariadb'])) {
                $table->unsignedBigInteger('user_id_when_default')->nullable()->storedAs('IF(is_default, user_id, NULL)');
                $table->unique('user_id_when_default');
            }
        });

        if (in_array($driver, ['sqlite', 'pgsql'])) {
            $where = $driver === 'pgsql' ? 'WHERE is_default = true' : 'WHERE is_default = 1';
            DB::statement("CREATE UNIQUE INDEX location_bookmarks_one_default_per_user ON location_bookmarks (user_id) {$where}");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('location_bookmarks');
    }
};
