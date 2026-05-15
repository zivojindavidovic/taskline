<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_project_filters', function (Blueprint $table) {
            $table->json('statuses')->nullable()->after('status_ids');
            $table->boolean('hide_completed')->default(false)->after('statuses');
            $table->boolean('unassigned')->default(false)->after('hide_completed');
        });
    }

    public function down(): void
    {
        Schema::table('user_project_filters', function (Blueprint $table) {
            $table->dropColumn(['statuses', 'hide_completed', 'unassigned']);
        });
    }
};
