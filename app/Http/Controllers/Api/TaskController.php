<?php

namespace App\Http\Controllers\Api;

use App\Events\TaskCreated;
use App\Events\TaskDeleted;
use App\Events\TaskUpdated;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        $tasks = $project->tasks()
            ->with(['assignee:id,name,email', 'boardColumn', 'sprint', 'comments.user', 'comments.replies.user'])
            ->when($request->sprint_id, fn ($q, $v) => $q->where('sprint_id', $v))
            ->get();

        return response()->json($tasks);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorizeMember($project);

        $sprint = $project->sprints()->where('id', $request->sprint_id)->firstOrFail();
        abort_if($sprint->locked, 422, 'Sprint is locked. No new tasks can be added.');

        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'board_column_id' => 'nullable|exists:board_columns,id',
            'sprint_id'       => 'nullable|exists:sprints,id',
            'assignee_id'     => 'nullable|exists:users,id',
            'priority'        => 'in:urgent,high,med,low',
            'tags'            => 'nullable|array',
            'tags.*'          => 'string|max:50',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
        ]);

        // Generate task key: PROJECT_KEY-{count+1}
        $count = $project->tasks()->count() + 1;
        $key = $project->key . '-' . $count;

        $task = $project->tasks()->create([
            ...$validated,
            'key'        => $key,
            'created_by' => $request->user()->id,
            'priority'   => $validated['priority'] ?? 'med',
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'task_id'    => $task->id,
            'action'     => 'task.created',
            'meta'       => ['key' => $key, 'title' => $task->title],
        ]);

        $task->load(['assignee:id,name,email', 'boardColumn', 'sprint']);
        broadcast(new TaskCreated($task))->toOthers();

        return response()->json($task, 201);
    }

    public function show(Project $project, Task $task): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($task->project_id !== $project->id, 404);

        return response()->json(
            $task->load(['assignee:id,name,email', 'boardColumn', 'sprint', 'comments.user', 'comments.replies.user', 'auditLogs.user'])
        );
    }

    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($task->project_id !== $project->id, 404);

        if ($task->sprint?->locked) {
            abort(422, 'Sprint is locked. Task cannot be edited.');
        }

        $validated = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'nullable|string',
            'board_column_id' => 'nullable|exists:board_columns,id',
            'sprint_id'       => 'nullable|exists:sprints,id',
            'assignee_id'     => 'nullable|exists:users,id',
            'priority'        => 'sometimes|in:urgent,high,med,low',
            'tags'            => 'nullable|array',
            'tags.*'          => 'string|max:50',
            'completed'       => 'sometimes|boolean',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
        ]);

        // Handle completion state change
        if (array_key_exists('completed', $validated)) {
            if ($validated['completed'] && ! $task->completed) {
                $validated['completed_at'] = now();
                $validated['completed_by'] = $request->user()->id;
                $action = 'task.completed';
            } elseif (! $validated['completed'] && $task->completed) {
                $validated['completed_at'] = null;
                $validated['completed_by'] = null;
                $action = 'task.reopened';
            }
        }

        $task->update($validated);

        if (isset($action)) {
            AuditLog::create([
                'user_id'    => $request->user()->id,
                'project_id' => $project->id,
                'task_id'    => $task->id,
                'action'     => $action,
            ]);
        }

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'project_id' => $project->id,
            'task_id'    => $task->id,
            'action'     => 'task.updated',
            'meta'       => $validated,
        ]);

        $task->load(['assignee:id,name,email', 'boardColumn', 'sprint']);
        broadcast(new TaskUpdated($task))->toOthers();

        return response()->json($task);
    }

    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeMember($project);
        abort_if($task->project_id !== $project->id, 404);

        if ($task->sprint?->locked) {
            abort(422, 'Sprint is locked. Task cannot be deleted.');
        }

        $taskId = $task->id;
        $task->delete();

        broadcast(new TaskDeleted($project->id, $taskId))->toOthers();

        return response()->json(null, 204);
    }

    private function authorizeMember(Project $project): void
    {
        $user = auth()->user();
        $ok = $project->owner_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
        abort_unless($ok, 403);
    }
}
