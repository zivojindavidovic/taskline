<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Task;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $projectIds = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $myTasks = Task::where('assignee_id', $user->id)
            ->where('completed', false)
            ->whereIn('project_id', $projectIds)
            ->with(['project', 'boardColumn', 'assignee'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'med' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->take(5)
            ->get();

        $awaitingCompletion = Task::whereIn('project_id', $projectIds)
            ->whereHas('boardColumn', fn ($q) => $q->where('name', 'Done'))
            ->where('completed', false)
            ->with(['project', 'assignee', 'boardColumn'])
            ->get();

        // In-progress tasks assigned to the current user (delta on the "In progress" card).
        $inProgressMine = Task::whereIn('project_id', $projectIds)
            ->where('assignee_id', $user->id)
            ->whereHas('boardColumn', fn ($q) => $q->where('name', 'In Progress'))
            ->where('completed', false)
            ->count();

        // Sprint context for the "Completed this sprint" card.
        $activeSprintIds = Sprint::whereIn('project_id', $projectIds)
            ->where('status', 'active')
            ->pluck('id');

        $completedSprint = $activeSprintIds->isEmpty() ? 0
            : Task::whereIn('project_id', $projectIds)
                ->whereIn('sprint_id', $activeSprintIds)
                ->where('completed', true)
                ->count();

        // Most recently ended completed sprint per project, for the delta.
        $lastSprintIds = Sprint::whereIn('project_id', $projectIds)
            ->where('status', 'completed')
            ->get(['id', 'project_id', 'end_date'])
            ->groupBy('project_id')
            ->map(fn ($g) => $g->sortByDesc('end_date')->first()->id)
            ->values();

        $completedLastSprint = $lastSprintIds->isEmpty() ? null
            : Task::whereIn('project_id', $projectIds)
                ->whereIn('sprint_id', $lastSprintIds)
                ->where('completed', true)
                ->count();

        if ($activeSprintIds->isEmpty()) {
            $sprintDelta = 'no active sprint';
        } elseif ($completedLastSprint === null) {
            $sprintDelta = 'first sprint';
        } else {
            $d = $completedSprint - $completedLastSprint;
            $sign = $d > 0 ? '+' : ($d < 0 ? '−' : '±');
            $sprintDelta = $sign.abs($d).' vs last sprint';
        }

        $stats = [
            'open'           => Task::whereIn('project_id', $projectIds)->where('completed', false)->count(),
            'inProgress'     => Task::whereIn('project_id', $projectIds)
                ->whereHas('boardColumn', fn ($q) => $q->where('name', 'In Progress'))
                ->where('completed', false)
                ->count(),
            'awaiting'        => $awaitingCompletion->count(),
            'projectCount'    => $projectIds->count(),
            'inProgressMine'  => $inProgressMine,
            'completedSprint' => $completedSprint,
            'sprintDelta'     => $sprintDelta,
        ];

        $mapTask = fn ($t) => [
            'id'            => $t->id,
            'uuid'          => $t->uuid,
            'key'           => $t->key,
            'title'         => $t->title,
            'priority'      => $t->priority,
            'completed'     => $t->completed,
            'due_date'      => $t->due_date,
            'project_id'    => $t->project_id,
            'project_name'  => $t->project?->name,
            'project_key'   => $t->project?->key,
            'column_name'   => $t->boardColumn?->name,
            'column_color'  => $t->boardColumn?->color,
            'assignee_name' => $t->assignee?->name,
        ];

        return Inertia::render('Dashboard', [
            'stats'              => $stats,
            'myTasks'            => $myTasks->map($mapTask)->values(),
            'awaitingCompletion' => $awaitingCompletion->map($mapTask)->values(),
        ]);
    }
}
