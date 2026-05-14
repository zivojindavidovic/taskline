<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $projectIds = Project::where('owner_id', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->pluck('id');

        $logs = AuditLog::whereIn('project_id', $projectIds)
            ->orWhereNull('project_id')
            ->with(['user:id,name,email', 'project:id,name,key,color', 'task:id,key,title'])
            ->latest()
            ->paginate(50);

        return Inertia::render('AuditLog', ['logs' => $logs]);
    }
}
