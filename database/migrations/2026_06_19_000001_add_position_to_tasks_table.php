<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an explicit, persisted ordering for cards within a board column.
     *
     * Before this, a column's cards rendered in whatever order the rows came
     * back from the DB (effectively insertion order). `position` makes the
     * order a first-class, user-controllable value — the column-scoped analogue
     * of board_columns.position.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('board_column_id');
        });

        // Backfill existing cards so the current board doesn't reshuffle on the
        // first load. Each column gets a contiguous 0..n-1 sequence following the
        // current insertion order (id ascending). Only top-level tasks are board
        // cards; subtasks keep the default 0 (they're never reordered as cards).
        $columnIds = DB::table('tasks')
            ->whereNotNull('board_column_id')
            ->whereNull('parent_task_id')
            ->distinct()
            ->pluck('board_column_id');

        foreach ($columnIds as $columnId) {
            $ids = DB::table('tasks')
                ->where('board_column_id', $columnId)
                ->whereNull('parent_task_id')
                ->orderBy('id')
                ->pluck('id');

            foreach ($ids as $index => $id) {
                DB::table('tasks')->where('id', $id)->update(['position' => $index]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
