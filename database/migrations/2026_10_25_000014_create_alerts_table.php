<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['cost_overrun', 'schedule_delay', 'budget_threshold', 'critical_defect', 'deadline_risk', 'scope']);
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->string('message', 500);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
