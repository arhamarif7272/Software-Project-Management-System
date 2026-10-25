<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('role', 100)->nullable();
            $table->string('organization', 150)->nullable();
            $table->enum('influence', ['low', 'medium', 'high'])->default('medium');
            $table->enum('interest', ['low', 'medium', 'high'])->default('medium');
            $table->string('contact', 150)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stakeholders');
    }
};
