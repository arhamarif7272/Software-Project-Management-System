<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('change_request_requirements', function (Blueprint $table) {
            $table->foreignId('change_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->primary(['change_request_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_request_requirements');
    }
};
