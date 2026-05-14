<?php

namespace App\Http\Controllers;

use App\Models\Project;
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
            ->take(8)
            ->get();

        $awaitingCompletion = Task::whereIn('project_id', $projectIds)
            ->whereHas('boardColumn', fn ($q) => $q->where('name', 'Done'))
            ->where('completed', false)
            ->with(['project', 'assignee', 'boardColumn'])
            ->get();

        $stats = [
            'open'      => Task::whereIn('project_id', $projectIds)->where('completed', false)->count(),
            'completed' => Task::whereIn('project_id', $projectIds)->where('completed', true)->count(),
            'inProgress' => Task::whereIn('project_id', $projectIds)
                ->whereHas('boardColumn', fn ($q) => $q->where('name', 'In Progress'))
                ->where('completed', false)
                ->count(),
            'awaiting'  => $awaitingCompletion->count(),
        ];

        $mapTask = fn ($t) => [
            'id'            => $t->id,
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
