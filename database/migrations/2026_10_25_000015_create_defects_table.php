<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->enum('status', ['open', 'in_progress', 'resolved', 'verified', 'closed', 'reopened'])->default('open');
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('team_members')->nullOnDelete();
            $table->date('reported_on');
            $table->date('resolved_on')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code'], 'uq_def_code');
            $table->index(['project_id', 'severity', 'status'], 'idx_defects_open');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defects');
    }
};
