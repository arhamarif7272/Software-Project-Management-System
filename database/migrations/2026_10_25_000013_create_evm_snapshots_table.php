<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evm_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('status_date');
            $table->decimal('bac', 14, 2);
            $table->decimal('pv', 14, 2);
            $table->decimal('ev', 14, 2);
            $table->decimal('ac', 14, 2);
            $table->decimal('cv', 14, 2);          // EV - AC
            $table->decimal('sv', 14, 2);          // EV - PV
            $table->decimal('cpi', 8, 4)->nullable(); // EV / AC
            $table->decimal('spi', 8, 4)->nullable(); // EV / PV
            $table->decimal('eac', 14, 2)->nullable();
            $table->decimal('etc', 14, 2)->nullable();
            $table->decimal('vac', 14, 2)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['project_id', 'status_date'], 'uq_evm_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evm_snapshots');
    }
};
