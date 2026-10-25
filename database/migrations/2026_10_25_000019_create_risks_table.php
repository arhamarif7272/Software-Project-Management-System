<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('category', ['technical', 'schedule', 'cost', 'quality', 'scope', 'resource', 'external'])->default('technical');
            $table->unsignedTinyInteger('likelihood');   // 1..5
            $table->unsignedTinyInteger('impact');       // 1..5
            $table->unsignedTinyInteger('score')->storedAs('likelihood * impact');
            $table->enum('level', ['low', 'medium', 'high', 'extreme'])->nullable();
            $table->enum('status', ['identified', 'mitigating', 'occurred', 'closed'])->default('identified');
            $table->foreignId('owner_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'code'], 'uq_risk_code');
        });

        DB::statement('ALTER TABLE risks ADD CONSTRAINT chk_risk_l CHECK (likelihood BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE risks ADD CONSTRAINT chk_risk_i CHECK (impact BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('risks');
    }
};
