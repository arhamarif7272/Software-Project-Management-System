<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('quality_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wbs_item_id')->nullable()->constrained('wbs_items')->nullOnDelete();
            $table->string('name', 200);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_checklists');
    }
};
