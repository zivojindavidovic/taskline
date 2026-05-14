<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);

        $user = auth()->user();

        $workspace = Workspace::create([
            'name'     => $data['name'],
            'color'    => $data['color'],
            'owner_id' => $user->id,
        ]);

        // Add owner as a member too
        $workspace->users()->attach($user->id, ['role' => 'owner']);

        // Switch to the new workspace
        $user->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard')->with('success', "Workspace \"{$workspace->name}\" created.");
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'workspace_id' => 'required|integer',
        ]);

        $user = auth()->user();
        $workspace = Workspace::findOrFail($data['workspace_id']);

        // Verify user belongs to this workspace
        abort_unless(
            $workspace->owner_id === $user->id ||
            $workspace->users()->where('users.id', $user->id)->exists(),
            403
        );

        $user->update(['current_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard')->with('success', "Switched to \"{$workspace->name}\".");
    }
}
