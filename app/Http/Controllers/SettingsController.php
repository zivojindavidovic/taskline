<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $user = request()->user();
        $workspace = $user->currentWorkspace
            ?? \App\Models\Workspace::where('owner_id', $user->id)->oldest()->first()
            ?? \App\Models\Workspace::whereHas('users', fn ($q) => $q->where('users.id', $user->id))->oldest()->first();

        $members = [];
        if ($workspace) {
            $members = $workspace->users()
                ->select('users.id', 'users.name', 'users.email')
                ->withPivot('role')
                ->get()
                ->map(fn ($u) => [
                    'id'    => $u->id,
                    'name'  => $u->name,
                    'email' => $u->email,
                    'role'  => $u->pivot->role,
                ])->toArray();
        }

        return Inertia::render('Settings', [
            'members' => $members,
        ]);
    }

    public function updateWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403, 'Only the workspace owner can update settings.');

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);

        $workspace->update($data);

        return back()->with('success', 'Workspace updated.');
    }

    public function destroyWorkspace(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($workspace && $workspace->owner_id === $user->id, 403, 'Only the workspace owner can delete the workspace.');

        $data = $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($data['confirmation'] !== $workspace->name) {
            return back()->withErrors(['confirmation' => 'Workspace name does not match.']);
        }

        // Clear current_workspace_id for all members who had this workspace active
        $workspace->users()->each(function ($u) use ($workspace) {
            if ($u->current_workspace_id === $workspace->id) {
                $u->update(['current_workspace_id' => null]);
            }
        });

        $user->update(['current_workspace_id' => null]);
        $workspace->delete();

        return redirect()->route('dashboard')->with('success', 'Workspace deleted.');
    }
}
