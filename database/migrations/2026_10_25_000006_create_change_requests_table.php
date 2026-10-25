<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title', 200);
            $table->text('description');
            $table->text('justification');
            $table->string('requested_by', 150);
            $table->decimal('cost_impact', 14, 2)->default(0);
            $table->integer('schedule_impact_days')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'implemented'])->default('pending');
            $table->text('decision_notes')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->decimal('budget_before', 14, 2)->nullable();
            $table->decimal('budget_after', 14, 2)->nullable();
            $table->date('deadline_before')->nullable();
            $table->date('deadline_after')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code'], 'uq_cr_code');
            $table->index(['project_id', 'status'], 'idx_cr_status');
        });

        DB::statement('ALTER TABLE change_requests ADD CONSTRAINT chk_cr_cost CHECK (cost_impact >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
