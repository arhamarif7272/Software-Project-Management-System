<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cost_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->date('entry_date');
            $table->enum('category', ['labor', 'infrastructure', 'license', 'testing', 'other'])->default('labor');
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['project_id', 'entry_date'], 'idx_cost_date');
        });

        DB::statement('ALTER TABLE cost_entries ADD CONSTRAINT chk_cost_amt CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_entries');
    }
};
