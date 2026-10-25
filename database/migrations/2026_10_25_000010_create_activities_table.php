<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wbs_item_id')->nullable()->constrained('wbs_items')->nullOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignId('milestone_id')->nullable()->constrained('milestones')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name', 200);
            $table->unsignedInteger('duration_days');

            // Computed by ScheduleService (forward/backward pass)
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->date('late_start')->nullable();
            $table->date('late_end')->nullable();
            $table->integer('slack_days')->nullable();
            $table->boolean('is_critical')->default(false);

            // Actuals
            $table->date('actual_start')->nullable();
            $table->date('actual_end')->nullable();
            $table->decimal('percent_complete', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold'])->default('not_started');
            $table->integer('delay_days')->default(0);

            // Cost (budgeted cost of the activity)
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'code'], 'uq_act_code');
            $table->index(['project_id', 'status'], 'idx_activities_status');
        });

        DB::statement('ALTER TABLE activities ADD CONSTRAINT chk_act_pct CHECK (percent_complete BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT chk_act_cost CHECK (estimated_cost >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
