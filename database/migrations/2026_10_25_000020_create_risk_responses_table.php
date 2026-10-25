<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('risk_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('risk_responses')->cascadeOnDelete();
            $table->enum('node_type', ['potential_problem', 'preventive_action', 'corrective_action', 'contingency']);
            $table->string('description', 400);
            $table->foreignId('owner_id')->nullable()->constrained('team_members')->nullOnDelete();
            $table->enum('status', ['planned', 'in_progress', 'done'])->default('planned');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_responses');
    }
};
