<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_project_filters', function (Blueprint $table) {
            $table->string('view_mode', 16)->default('active')->after('unassigned');
            $table->foreignId('view_sprint_id')->nullable()->after('view_mode')
                ->constrained('sprints')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_project_filters', function (Blueprint $table) {
            $table->dropForeign(['view_sprint_id']);
            $table->dropColumn(['view_mode', 'view_sprint_id']);
        });
    }
};
