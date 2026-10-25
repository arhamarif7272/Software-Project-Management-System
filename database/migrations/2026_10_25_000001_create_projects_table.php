<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->text('objectives');
            $table->text('scope_boundaries');
            $table->decimal('approved_budget', 14, 2);
            $table->decimal('current_budget', 14, 2);
            $table->date('start_date');
            $table->date('approved_deadline');
            $table->date('current_deadline');
            $table->enum('status', ['initiation', 'planning', 'executing', 'monitoring', 'closing', 'closed'])->default('initiation');
            $table->boolean('charter_approved')->default(false);
            $table->dateTime('charter_approved_at')->nullable();
            $table->date('closure_date')->nullable();
            $table->text('closure_summary')->nullable();
            $table->text('lessons_learned')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_proj_dates CHECK (approved_deadline > start_date AND current_deadline >= start_date)');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT chk_proj_budget CHECK (approved_budget > 0 AND current_budget > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
