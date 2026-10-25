<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wbs_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('wbs_items')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('wbs_code', 30);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->enum('level_type', ['project', 'phase', 'deliverable', 'work_package'])->default('work_package');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'wbs_code'], 'uq_wbs_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wbs_items');
    }
};
