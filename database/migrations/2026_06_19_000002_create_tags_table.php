<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A workspace-scoped registry of every tag name ever used. Tags here live
     * independently of any task: once created they stay available as suggestions
     * for the whole workspace, even after every task that used them is retagged
     * or deleted. (Per-task tags still live in the `tasks.tags` JSON column —
     * this table is the durable global vocabulary that backs autocomplete.)
     */
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            // One row per name per workspace — the firstOrCreate contract.
            $table->unique(['workspace_id', 'name']);
        });

        $this->backfillFromExistingTasks();
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }

    /**
     * Seed the pool from tags already present on tasks so the existing
     * vocabulary is immediately available workspace-wide on first load.
     */
    private function backfillFromExistingTasks(): void
    {
        $rows = DB::table('tasks')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->whereNotNull('tasks.tags')
            ->whereNotNull('projects.workspace_id')
            ->select('projects.workspace_id', 'tasks.tags')
            ->get();

        $now    = now();
        $seen   = [];
        $insert = [];

        foreach ($rows as $row) {
            $tags = json_decode($row->tags, true);
            if (!is_array($tags)) {
                continue;
            }
            foreach ($tags as $name) {
                $name = strtolower(preg_replace('/\s+/', '-', trim((string) $name)));
                if ($name === '') {
                    continue;
                }
                $key = $row->workspace_id . '|' . $name;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $insert[] = [
                    'workspace_id' => $row->workspace_id,
                    'name'         => $name,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ];
            }
        }

        foreach (array_chunk($insert, 500) as $chunk) {
            DB::table('tags')->insertOrIgnore($chunk);
        }
    }
};
