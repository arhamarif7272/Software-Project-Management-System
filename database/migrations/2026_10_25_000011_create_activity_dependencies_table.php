<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();     // successor
            $table->foreignId('predecessor_id')->constrained('activities')->cascadeOnDelete();
            $table->enum('dependency_type', ['FS', 'SS', 'FF', 'SF'])->default('FS');
            $table->integer('lag_days')->default(0);

            $table->unique(['activity_id', 'predecessor_id'], 'uq_dep');
        });

        DB::statement('ALTER TABLE activity_dependencies ADD CONSTRAINT chk_dep_self CHECK (activity_id <> predecessor_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_dependencies');
    }
};
