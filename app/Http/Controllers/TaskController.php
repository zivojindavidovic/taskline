<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\ParticipantService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService,
        private ParticipantService $participantService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $project = Project::findOrFail($request->input('project_id'));
        $workspaceUserIds = $this->workspaceUserIds($project);

        $data = $request->validate([
            'project_id'      => 'required|exists:projects,id',
            'sprint_id'       => 'nullable|exists:sprints,id',
            'board_column_id' => 'nullable|exists:board_columns,id',
            'title'           => 'required|string|max:255',
            'description'     => 'nullable|string',
            'priority'        => 'required|in:urgent,high,med,low',
            'assignee_id'     => ['nullable', Rule::in($workspaceUserIds)],
            'assignee_ids'    => 'nullable|array',
            'assignee_ids.*'  => ['integer', Rule::in($workspaceUserIds)],
            'tags'            => 'nullable|array',
            'tags.*'          => 'string|max:50',
            'start_date'      => 'nullable|date',
            'due_date'        => 'nullable|date',
            'files'           => 'nullable|array',
            'files.*'         => 'file|max:20480',
        ]);

        $assigneeIds = $data['assignee_ids'] ?? null;
        if ($assigneeIds === null && !empty($data['assignee_id'])) {
            $assigneeIds = [(int) $data['assignee_id']];
        }

        $taskNum = $project->tasks()->count() + 1;

        $task = Task::create([
            ...collect($data)->except(['files', 'assignee_ids'])->all(),
            'key'         => $project->key.'-'.$taskNum,
            'created_by'  => auth()->id(),
            'assignee_id' => $assigneeIds[0] ?? ($data['assignee_id'] ?? null),
        ]);

        if (!empty($assigneeIds)) {
            $this->taskService->setAssignees($task, $assigneeIds, auth()->id());
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store("attachments/task-{$task->id}", 'public');
                TaskAttachment::create([
                    'task_id'       => $task->id,
                    'uploaded_by'   => auth()->id(),
                    'original_name' => $file->getClientOriginalName(),
                    'disk'          => 'public',
                    'path'          => $path,
                    'mime_type'     => $file->getMimeType(),
                    'size'          => $file->getSize(),
                ]);
            }
        }

        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $project->id,
            'task_id'    => $task->id,
            'action'     => 'task.created',
            'meta'       => ['title' => $task->title],
        ]);

        return back()->with('success', "Task {$task->key} created.");
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        $workspaceUserIds = $this->workspaceUserIds($task->project);

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'description'     => 'sometimes|nullable|string',
            'priority'        => 'sometimes|in:urgent,high,med,low',
            'assignee_id'     => ['sometimes', 'nullable', Rule::in(array_merge([null], $workspaceUserIds))],
            'assignee_ids'    => 'sometimes|array',
            'assignee_ids.*'  => ['integer', Rule::in($workspaceUserIds)],
            'tags'            => 'sometimes|nullable|array',
            'tags.*'          => 'string|max:50',
            'board_column_id' => 'sometimes|nullable|exists:board_columns,id',
            'sprint_id'       => 'sometimes|nullable|exists:sprints,id',
            'project_id'      => 'sometimes|exists:projects,id',
            'start_date'      => 'sometimes|nullable|date',
            'due_date'        => 'sometimes|nullable|date',
        ]);

        $this->taskService->update($task, $data, auth()->id());

        return back();
    }

    public function participants(Task $task): JsonResponse
    {
        $this->authorizeTaskAccess($task);

        $participants = $this->participantService->forTask($task)->map(fn ($entry) => [
            'id'           => $entry['user']->id,
            'name'         => $entry['user']->name,
            'email'        => $entry['user']->email,
            'avatar_color' => $entry['user']->avatar_color,
            'roles'        => $entry['roles'],
        ]);

        return response()->json($participants);
    }

    public function move(Request $request, Task $task): RedirectResponse
    {
        $data = $request->validate([
            'board_column_id' => 'required|exists:board_columns,id',
        ]);

        $this->taskService->update($task, $data, auth()->id());

        return back();
    }

    public function complete(Task $task): RedirectResponse
    {
        $task->update([
            'completed'    => true,
            'completed_at' => now(),
            'completed_by' => auth()->id(),
        ]);

        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.completed',
        ]);

        return back()->with('success', 'Task marked as completed.');
    }

    public function uncomplete(Task $task): RedirectResponse
    {
        $task->update([
            'completed'    => false,
            'completed_at' => null,
            'completed_by' => null,
        ]);

        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.reopened',
        ]);

        return back();
    }

    public function storeSubtask(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);

        $data = $request->validate([
            'title'    => 'required|string|max:255',
            'priority' => 'nullable|in:urgent,high,med,low',
        ]);

        $this->taskService->createSubtask(
            $task,
            $data['title'],
            $data['priority'] ?? null,
            auth()->id()
        );

        return back();
    }

    public function updateSubtask(Request $request, Task $task, Task $subtask): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        abort_unless($subtask->parent_task_id === $task->id, 404);

        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'priority'    => 'sometimes|in:urgent,high,med,low',
            'assignee_id' => 'sometimes|nullable|integer|exists:users,id',
            'due_date'    => 'sometimes|nullable|date',
            'tags'        => 'sometimes|nullable|array',
            'tags.*'      => 'string|max:50',
            'description' => 'sometimes|nullable|string',
        ]);

        $this->taskService->updateSubtask($task, $subtask, $data, auth()->id());

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorizeTaskAccess($task);
        $key = $task->key;
        $this->taskService->delete($task);

        return back()->with('success', "Task {$key} deleted.");
    }

    private function authorizeTaskAccess(Task $task): void
    {
        $user    = auth()->user();
        $project = $task->project;
        abort_unless(
            $project->owner_id === $user->id ||
            $project->members()->where('users.id', $user->id)->exists(),
            403
        );
    }

    private function workspaceUserIds(Project $project): array
    {
        $workspace = $project->workspace;
        $ids = $workspace->users()->get()->pluck('id')->toArray();
        if (!in_array($workspace->owner_id, $ids)) {
            $ids[] = $workspace->owner_id;
        }
        return $ids;
    }

    public function myTasks(): Response
    {
        $tasks = Task::where('assignee_id', auth()->id())
            ->with(['project:id,name,key,color', 'boardColumn:id,name,color', 'sprint:id,name'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'med' THEN 3 WHEN 'low' THEN 4 ELSE 5 END")
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'key'         => $t->key,
                'title'       => $t->title,
                'priority'    => $t->priority,
                'completed'   => $t->completed,
                'due_date'    => $t->due_date,
                'project_id'  => $t->project_id,
                'project_key' => $t->project?->key,
                'column_name' => $t->boardColumn?->name,
                'sprint_name' => $t->sprint?->name,
            ]);

        return Inertia::render('MyTasks', ['tasks' => $tasks]);
    }
}
