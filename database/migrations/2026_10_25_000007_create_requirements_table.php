<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('type', ['functional', 'non_functional', 'constraint'])->default('functional');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['proposed', 'approved', 'rejected', 'deferred'])->default('proposed');
            $table->boolean('in_scope')->default(false);
            $table->foreignId('origin_change_request_id')->nullable()->constrained('change_requests')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'code'], 'uq_req_code');
            $table->index(['project_id', 'status'], 'idx_req_status');
        });

        // Scope guard at database level: only approved requirements may be in scope.
        DB::statement("ALTER TABLE requirements ADD CONSTRAINT chk_req_scope CHECK (in_scope = 0 OR status = 'approved')");
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
