<?php

namespace App\Repositories;

use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use Illuminate\Database\Eloquent\Collection;

class InvitationRepository
{
    public function find(int $id): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::find($id);
    }

    public function findByToken(string $token): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::where('token', $token)->first();
    }

    public function findByWorkspaceAndEmail(int $workspaceId, string $email): ?WorkspaceInvitation
    {
        return WorkspaceInvitation::where('workspace_id', $workspaceId)
            ->where('email', $email)
            ->first();
    }

    public function listForWorkspace(int $workspaceId): Collection
    {
        return WorkspaceInvitation::where('workspace_id', $workspaceId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(array $data): WorkspaceInvitation
    {
        return WorkspaceInvitation::create($data);
    }

    public function delete(WorkspaceInvitation $invitation): void
    {
        $invitation->delete();
    }

    public function deleteByIdForWorkspace(int $id, int $workspaceId): int
    {
        return WorkspaceInvitation::where('id', $id)
            ->where('workspace_id', $workspaceId)
            ->delete();
    }
}
