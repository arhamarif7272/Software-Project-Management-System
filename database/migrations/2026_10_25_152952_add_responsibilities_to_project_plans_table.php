<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_plans', function (Blueprint $table) {
            // Add the missing column
            $table->text('responsibilities')->nullable()->after('quality_approach');
        });
    }

    public function down(): void
    {
        Schema::table('project_plans', function (Blueprint $table) {
            $table->dropColumn('responsibilities');
        });
    }
};