<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('scope_statement')->nullable();
            $table->text('schedule_approach')->nullable();
            $table->text('cost_approach')->nullable();
            $table->text('quality_approach')->nullable();
            $table->decimal('cpi_threshold', 4, 2)->default(0.90);
            $table->decimal('spi_threshold', 4, 2)->default(0.90);
            $table->decimal('budget_alert_pct', 5, 2)->default(90.00);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_plans');
    }
};
