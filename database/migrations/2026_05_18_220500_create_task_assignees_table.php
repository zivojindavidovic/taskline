<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        // Backfill: every task with a legacy assignee_id becomes an entry in the pivot.
        DB::table('tasks')
            ->whereNotNull('assignee_id')
            ->select('id', 'assignee_id', 'created_at', 'updated_at')
            ->orderBy('id')
            ->chunk(500, function ($rows) {
                $now = now();
                $insert = $rows->map(fn ($r) => [
                    'task_id'    => $r->id,
                    'user_id'    => $r->assignee_id,
                    'created_at' => $r->created_at ?? $now,
                    'updated_at' => $r->updated_at ?? $now,
                ])->all();
                DB::table('task_assignees')->insertOrIgnore($insert);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignees');
    }
};
