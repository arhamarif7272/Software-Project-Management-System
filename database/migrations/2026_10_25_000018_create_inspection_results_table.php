<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained('checklist_items')->cascadeOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->enum('inspection_type', ['inspection', 'unit_test', 'integration_test', 'system_test', 'uat'])->default('inspection');
            $table->enum('result', ['pass', 'fail', 'not_applicable']);
            $table->date('inspected_on');
            $table->text('notes')->nullable();
            $table->foreignId('defect_id')->nullable()->constrained('defects')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
