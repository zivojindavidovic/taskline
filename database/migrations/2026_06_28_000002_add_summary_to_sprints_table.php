<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot of what shipped vs. what rolled over to the backlog, captured
     * when a sprint is completed. Kept as a record because completing a sprint
     * detaches its unfinished tasks (sprint_id = null), so the breakdown can no
     * longer be derived from live task rows after the fact.
     */
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->json('summary')->nullable()->after('goal');
        });
    }

    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropColumn('summary');
        });
    }
};
