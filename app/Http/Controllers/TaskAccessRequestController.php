<?php

namespace App\Http\Controllers;

use App\Events\TaskAccessRequestUpdated;
use App\Models\AuditLog;
use App\Models\Task;
use App\Models\TaskAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskAccessRequestController extends Controller
{
    /**
     * Pending access requests for a task, plus whether the current user is
     * allowed to act on them. Drives the "Access requests" panel section.
     */
    public function index(Task $task): JsonResponse
    {
        $this->authorizeMember($task);

        $requests = $task->accessRequests()
            ->where('status', TaskAccessRequest::STATUS_PENDING)
            ->with('requester:id,name,email,avatar_color')
            ->latest()
            ->get()
            ->map(fn (TaskAccessRequest $r) => [
                'id'           => $r->id,
                'message'      => $r->message,
                'requested_at' => $r->created_at?->diffForHumans(),
                'user'         => [
                    'id'           => $r->requester?->id,
                    'name'         => $r->requester?->name,
                    'email'        => $r->requester?->email,
                    'avatar_color' => $r->requester?->avatar_color,
                ],
            ]);

        return response()->json([
            'can_manage' => $this->canManage($task),
            'requests'   => $requests,
        ]);
    }

    /**
     * A non-member asks for access. Idempotent: a repeat (or a re-request after
     * a decline) flips the same row back to pending rather than erroring.
     */
    public function store(Request $request, Task $task): JsonResponse
    {
        abort_if($this->canAccessTask($task), 422, 'You already have access to this task.');

        $data = $request->validate([
            'message' => 'nullable|string|max:500',
        ]);

        $accessRequest = TaskAccessRequest::updateOrCreate(
            ['task_id' => $task->id, 'user_id' => auth()->id()],
            [
                'message'     => $data['message'] ?? null,
                'status'      => TaskAccessRequest::STATUS_PENDING,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ],
        );

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.access_requested',
        ]);

        broadcast(new TaskAccessRequestUpdated(
            $task->id,
            $task->project_id,
            (int) auth()->id(),
            'requested',
        ))->toOthers();

        return response()->json([
            'status'  => $accessRequest->status,
            'message' => 'Access request sent.',
        ], 201);
    }

    /**
     * Owner/admin approves — grants TASK-LEVEL access and unlocks just this task.
     *
     * The approved request row IS the grant (see Task::isAccessibleBy). We do not
     * add the user to the project or workspace: least-privilege, they get exactly
     * one task. They reach it through the inbox link / direct URL — it never
     * appears on the project board or in their dashboards.
     */
    public function approve(Task $task, TaskAccessRequest $accessRequest): JsonResponse
    {
        $this->authorizeManage($task);
        $this->assertBelongsToTask($task, $accessRequest);

        $accessRequest->update([
            'status'      => TaskAccessRequest::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'project_id' => $task->project_id,
            'task_id'    => $task->id,
            'action'     => 'task.access_granted',
            'meta'       => ['granted_to' => $accessRequest->user_id],
        ]);

        // Project channel → managers' pending list updates; grantee's user
        // channel → their locked panel unlocks live.
        broadcast(new TaskAccessRequestUpdated(
            $task->id,
            $task->project_id,
            (int) $accessRequest->user_id,
            'approved',
        ))->toOthers();

        return response()->json(['status' => $accessRequest->status]);
    }

    /**
     * Owner/admin declines — the request leaves the pending list. Declining a
     * row that was previously approved revokes the grant (status is no longer
     * "approved", so Task::isAccessibleBy returns false again).
     */
    public function decline(Task $task, TaskAccessRequest $accessRequest): JsonResponse
    {
        $this->authorizeManage($task);
        $this->assertBelongsToTask($task, $accessRequest);

        $accessRequest->update([
            'status'      => TaskAccessRequest::STATUS_DECLINED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        broadcast(new TaskAccessRequestUpdated(
            $task->id,
            $task->project_id,
            (int) $accessRequest->user_id,
            'declined',
        ))->toOthers();

        return response()->json(['status' => $accessRequest->status]);
    }

    // ── Access helpers ──────────────────────────────────────────────────────

    private function canAccessTask(Task $task): bool
    {
        // Task-level: project owner/member OR an approved grant for this task.
        return $task->isAccessibleBy(auth()->user());
    }

    /** Only the project owner or an admin member can resolve requests. */
    private function canManage(Task $task): bool
    {
        $user    = auth()->user();
        $project = $task->project;

        return $project->owner_id === $user->id
            || $project->members()
                ->where('users.id', $user->id)
                ->wherePivotIn('role', ['owner', 'admin'])
                ->exists();
    }

    private function authorizeMember(Task $task): void
    {
        abort_unless($this->canAccessTask($task), 403);
    }

    private function authorizeManage(Task $task): void
    {
        abort_unless($this->canManage($task), 403);
    }

    private function assertBelongsToTask(Task $task, TaskAccessRequest $accessRequest): void
    {
        abort_unless($accessRequest->task_id === $task->id, 404);
    }
}
