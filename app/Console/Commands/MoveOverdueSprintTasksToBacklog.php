<?php

namespace App\Console\Commands;

use App\Models\Sprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MoveOverdueSprintTasksToBacklog extends Command
{
    protected $signature = 'sprints:rollover-overdue-tasks';

    protected $description = 'Move uncompleted tasks from expired (past end_date) non-completed sprints back to the backlog.';

    public function handle(): int
    {
        $today = now()->toDateString();

        $sprints = Sprint::query()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->where('status', '!=', 'completed')
            ->get();

        $totalMoved = 0;

        foreach ($sprints as $sprint) {
            $moved = $sprint->tasks()
                ->where('completed', false)
                ->update(['sprint_id' => null]);

            if ($moved > 0) {
                $totalMoved += $moved;
                Log::info(sprintf(
                    '[sprints:rollover-overdue-tasks] Moved %d task(s) from sprint #%d "%s" to backlog.',
                    $moved,
                    $sprint->id,
                    $sprint->name,
                ));
            }
        }

        $this->info(sprintf(
            'Moved %d task(s) from %d overdue sprint(s) to backlog.',
            $totalMoved,
            $sprints->count(),
        ));

        return self::SUCCESS;
    }
}
